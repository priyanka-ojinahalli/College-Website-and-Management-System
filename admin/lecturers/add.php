<?php

session_start();

/* =====================================================
   ADMIN LOGIN CHECK
===================================================== */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../../auth/login.php");
    exit();
}


/* =====================================================
   DATABASE
===================================================== */

require_once "../../config/database.php";


$error = "";
$photo_filename = null;
$photo_absolute_path = null;


/* =====================================================
   GET DEPARTMENTS
===================================================== */

$departments = [];

$department_result = $conn->query("
    SELECT id, department_name
    FROM departments
    ORDER BY department_name ASC
");

if ($department_result) {

    while ($department = $department_result->fetch_assoc()) {

        $departments[] = $department;

    }

}


/* =====================================================
   ADD LECTURER
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /* =================================================
       GET FORM DATA
    ================================================= */

    $lecturer_id = trim(
        $_POST["lecturer_id"] ?? ""
    );

    $full_name = trim(
        $_POST["full_name"] ?? ""
    );

    $email = trim(
        $_POST["email"] ?? ""
    );

    $phone = trim(
        $_POST["phone"] ?? ""
    );

    $department_id = (int)(
        $_POST["department_id"] ?? 0
    );

    $designation = trim(
        $_POST["designation"] ?? ""
    );

    $password = $_POST["password"] ?? "";

    $confirm_password =
        $_POST["confirm_password"] ?? "";


    /* =================================================
       REQUIRED VALIDATION
    ================================================= */

    if (
        $lecturer_id === "" ||
        $full_name === "" ||
        $email === "" ||
        $department_id <= 0 ||
        $designation === "" ||
        $password === "" ||
        $confirm_password === ""
    ) {

        $error =
            "Please fill all required fields.";

    }


    /* =================================================
       EMAIL VALIDATION
    ================================================= */

    if ($error === "") {

        if (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error =
                "Please enter a valid email address.";

        }

    }


    /* =================================================
       PHONE VALIDATION
    ================================================= */

    if (
        $error === "" &&
        $phone !== ""
    ) {

        if (
            !preg_match(
                "/^[0-9]{10}$/",
                $phone
            )
        ) {

            $error =
                "Phone number must contain exactly 10 digits.";

        }

    }


    /* =================================================
       PHOTO VALIDATION (OPTIONAL)
    ================================================= */

    if ($error === "" && isset($_FILES["photo"])) {

        $photo_upload = $_FILES["photo"];

        if ($photo_upload["error"] !== UPLOAD_ERR_NO_FILE) {

            if ($photo_upload["error"] !== UPLOAD_ERR_OK) {

                $error = "The photo could not be uploaded. Please try again.";

            } elseif ($photo_upload["size"] > 5 * 1024 * 1024) {

                $error = "Photo must be 5 MB or smaller.";

            } else {

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $photo_mime = $finfo->file($photo_upload["tmp_name"]);

                $allowed_photo_types = [
                    "image/jpeg" => "jpg",
                    "image/png"  => "png",
                    "image/webp" => "webp"
                ];

                if (!isset($allowed_photo_types[$photo_mime])) {

                    $error = "Please upload a JPG, PNG, or WEBP image.";

                } elseif (@getimagesize($photo_upload["tmp_name"]) === false) {

                    $error = "The uploaded file is not a valid image.";

                } else {

                    try {
                        $photo_filename = "lecturer_" . bin2hex(random_bytes(16)) . "." .
                            $allowed_photo_types[$photo_mime];
                    } catch (Exception $e) {
                        $error = "Unable to prepare the photo upload. Please try again.";
                    }

                }

            }

        }

    }


    /* =================================================
       LECTURER ID VALIDATION
    ================================================= */

    if ($error === "") {

        if (
            !preg_match(
                "/^[A-Za-z0-9\/_-]{3,30}$/",
                $lecturer_id
            )
        ) {

            $error =
                "Lecturer ID contains invalid characters.";

        }

    }


    /* =================================================
       PASSWORD VALIDATION
    ================================================= */

    if ($error === "") {

        if (strlen($password) < 8) {

            $error =
                "Password must contain at least 8 characters.";

        }

    }


    /* =================================================
       CONFIRM PASSWORD
    ================================================= */

    if ($error === "") {

        if (
            $password !== $confirm_password
        ) {

            $error =
                "Passwords do not match.";

        }

    }


    /* =================================================
       CHECK DUPLICATE LECTURER ID
    ================================================= */

    if ($error === "") {

        $check = $conn->prepare("
            SELECT id
            FROM lecturers
            WHERE lecturer_id = ?
            LIMIT 1
        ");

        $check->bind_param(
            "s",
            $lecturer_id
        );

        $check->execute();

        $check_result =
            $check->get_result();

        if (
            $check_result->num_rows > 0
        ) {

            $error =
                "This Lecturer ID already exists.";

        }

        $check->close();

    }


    /* =================================================
       CHECK DUPLICATE EMAIL IN USERS
    ================================================= */

    if ($error === "") {

        $check = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $check->bind_param(
            "s",
            $email
        );

        $check->execute();

        $check_result =
            $check->get_result();

        if (
            $check_result->num_rows > 0
        ) {

            $error =
                "This email address is already registered.";

        }

        $check->close();

    }


    /* =================================================
       CREATE USER + LECTURER
    ================================================= */

    if ($error === "") {

        /*
         * Use a transaction.
         *
         * If either INSERT fails,
         * both records will be rolled back.
         */

        $conn->begin_transaction();


        try {

            /* =========================================
               SAVE PHOTO FILE (IF PROVIDED)
            ========================================= */

            if ($photo_filename !== null) {

                $photo_directory = __DIR__ . "/../../images/faculty/";

                if (
                    !is_dir($photo_directory) &&
                    !mkdir($photo_directory, 0755, true) &&
                    !is_dir($photo_directory)
                ) {
                    throw new Exception("Unable to create the faculty photo folder.");
                }

                $photo_absolute_path = $photo_directory . $photo_filename;

                if (!move_uploaded_file(
                    $_FILES["photo"]["tmp_name"],
                    $photo_absolute_path
                )) {
                    $photo_absolute_path = null;
                    throw new Exception("Unable to save the lecturer photo.");
                }

            }


            /* =========================================
               CREATE LOGIN ACCOUNT
            ========================================= */

            /*
             * Lecturer login username = email
             */

            $username = $email;


            /*
             * Never store the plain password.
             */

            $hashed_password =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


            $user_stmt = $conn->prepare("

                INSERT INTO users
                (
                    username,
                    email,
                    password,
                    role
                )

                VALUES
                (
                    ?,
                    ?,
                    ?,
                    'lecturer'
                )

            ");


            if (!$user_stmt) {

                throw new Exception(
                    "Unable to prepare lecturer login account."
                );

            }


            $user_stmt->bind_param(
                "sss",
                $username,
                $email,
                $hashed_password
            );


            if (
                !$user_stmt->execute()
            ) {

                throw new Exception(
                    "Unable to create lecturer login account: " .
                    $user_stmt->error
                );

            }


            /* =========================================
               GET USER ID
            ========================================= */

            $user_id =
                $conn->insert_id;


            $user_stmt->close();


            /* =========================================
               CREATE LECTURER RECORD
            ========================================= */

            $lecturer_stmt = $conn->prepare("

                INSERT INTO lecturers
                (
                    user_id,
                    lecturer_id,
                    full_name,
                    email,
                    phone,
                    department_id,
                    designation,
                    photo
                )

                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )

            ");


            if (!$lecturer_stmt) {

                throw new Exception(
                    "Unable to prepare lecturer record."
                );

            }


            $lecturer_stmt->bind_param(
                "issssiss",
                $user_id,
                $lecturer_id,
                $full_name,
                $email,
                $phone,
                $department_id,
                $designation,
                $photo_filename
            );


            if (
                !$lecturer_stmt->execute()
            ) {

                throw new Exception(
                    "Unable to create lecturer record: " .
                    $lecturer_stmt->error
                );

            }


            $lecturer_stmt->close();


            /* =========================================
               EVERYTHING SUCCESSFUL
            ========================================= */

            $conn->commit();


            header(
                "Location: index.php?added=1"
            );

            exit();


        } catch (Exception $e) {


            /*
             * Undo everything if something fails.
             */

            $conn->rollback();

            if ($photo_absolute_path !== null && is_file($photo_absolute_path)) {
                @unlink($photo_absolute_path);
            }

            $error =
                $e->getMessage();

        }

    }

}

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Add Lecturer - Admin</title>


    <link
        rel="stylesheet"
        href="../../css/admin.css?v=11"
    >


    <style>

        .lecturer-form-card {

            background: #ffffff;

            padding: 30px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

            max-width: 1000px;

            margin-top: 25px;

        }


        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;

        }


        .form-group {

            margin-bottom: 0;

        }


        .form-group.full {

            grid-column: 1 / -1;

        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            font-weight: bold;

            color: #374151;

        }


        .required {

            color: #dc2626;

        }


        .form-group input,
        .form-group select {

            width: 100%;

            padding: 11px;

            border: 1px solid #d1d5db;

            border-radius: 5px;

            font-family: inherit;

            font-size: 15px;

            box-sizing: border-box;

        }


        .form-group input:focus,
        .form-group select:focus {

            outline: none;

            border-color: #172554;

            box-shadow:
                0 0 0 2px
                rgba(23, 37, 84, 0.1);

        }


        .help-text {

            font-size: 12px;

            color: #6b7280;

            margin-top: 5px;

        }


        .login-info {

            background: #eff6ff;

            border-left:
                4px solid #172554;

            padding: 15px;

            margin-bottom: 25px;

            border-radius: 5px;

        }


        .login-info strong {

            color: #172554;

        }


        .alert.error {

            max-width: 1000px;

            background: #fee2e2;

            color: #991b1b;

            padding: 12px 15px;

            border-radius: 5px;

            margin-top: 20px;

            margin-bottom: 15px;

        }


        .password-strength {

            font-size: 12px;

            margin-top: 5px;

        }


        .valid-field {

            border-color:
                #16a34a !important;

        }


        .invalid-field {

            border-color:
                #dc2626 !important;

        }


        .form-actions {

            display: flex;

            gap: 10px;

            margin-top: 30px;

            padding-top: 20px;

            border-top:
                1px solid #e5e7eb;

        }


        .btn-primary {

            border: none;

            cursor: pointer;

        }


        .show-password {

            margin-top: 7px;

            font-size: 13px;

            color: #4b5563;

        }


        @media (max-width: 700px) {

            .form-grid {

                grid-template-columns: 1fr;

            }


            .form-group.full {

                grid-column: auto;

            }

        }

    </style>

</head>


<body>


<div class="dashboard">


    <!-- =================================================
         SIDEBAR
    ================================================= -->

    <?php

    include "../../includes/admin_sidebar.php";

    ?>


    <!-- =================================================
         MAIN CONTENT
    ================================================= -->

    <div class="main-content">


        <!-- HEADER -->

        <?php

        include "../../includes/admin_header.php";

        ?>


        <!-- CONTENT -->

        <div class="content">


            <h2>
                Add Lecturer
            </h2>


            <p class="welcome">

                Create a new lecturer profile and login account.

            </p>


            <?php if ($error !== ""): ?>

                <div class="alert error">

                    <?php

                    echo htmlspecialchars(
                        $error
                    );

                    ?>

                </div>

            <?php endif; ?>


            <div class="lecturer-form-card">


                <!-- =================================================
                     LOGIN INFORMATION
                ================================================= -->

                <div class="login-info">

                    <strong>
                        Lecturer Login Information
                    </strong>

                    <p style="margin-top: 7px;">

                        The lecturer will log in using
                        their registered
                        <strong>email address</strong>
                        and password.

                    </p>

                    <p style="margin-top: 5px;">

                        After entering the email and password,
                        an OTP will be sent to the lecturer's
                        registered email address.

                    </p>

                </div>


                <!-- =================================================
                     FORM
                ================================================= -->

                <form
                    method="POST"
                    action=""
                    enctype="multipart/form-data"
                    id="lecturerForm"
                    novalidate
                >


                    <div class="form-grid">


                        <!-- =================================================
                             LECTURER ID
                        ================================================= -->

                        <div class="form-group">

                            <label>

                                Lecturer ID

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                name="lecturer_id"
                                required
                                maxlength="30"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "lecturer_id"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="Example: LEC001"
                            >


                            <div class="help-text">

                                Unique ID for the lecturer.

                            </div>

                        </div>


                        <!-- =================================================
                             FULL NAME
                        ================================================= -->

                        <div class="form-group">

                            <label>

                                Full Name

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                name="full_name"
                                required
                                maxlength="100"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "full_name"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="Lecturer full name"
                            >

                        </div>


                        <!-- =================================================
                             EMAIL
                        ================================================= -->

                        <div class="form-group">

                            <label>

                                Email Address

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input
                                type="email"
                                name="email"
                                id="email"
                                required
                                maxlength="150"
                                autocomplete="email"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "email"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="lecturer@gmail.com"
                            >


                            <div
                                class="help-text"
                                id="emailHelp"
                            >

                                This email will be used for
                                lecturer login and OTP verification.

                            </div>

                        </div>


                        <!-- =================================================
                             PHONE
                        ================================================= -->

                        <div class="form-group">

                            <label>
                                Phone Number
                            </label>


                            <input
                                type="tel"
                                name="phone"
                                id="phone"
                                maxlength="10"
                                pattern="[0-9]{10}"
                                inputmode="numeric"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "phone"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="10-digit mobile number"
                            >


                            <div class="help-text">

                                Enter exactly 10 digits.

                            </div>

                        </div>


                        <!-- =================================================
                             PROFILE PHOTO (OPTIONAL)
                        ================================================= -->

                        <div class="form-group full">

                            <label for="photo">
                                Lecturer Photo
                            </label>

                            <input
                                type="file"
                                name="photo"
                                id="photo"
                                accept="image/jpeg,image/png,image/webp"
                            >

                            <div class="help-text">
                                Optional. JPG, PNG, or WEBP only; maximum size 5 MB.
                                Save uploads in the images/faculty folder automatically.
                            </div>

                            <div style="margin-top: 12px;">
                                <img
                                    id="photoPreview"
                                    src=""
                                    alt="Selected lecturer photo preview"
                                    style="display:none; max-width:150px; max-height:150px; object-fit:cover; border-radius:8px; border:1px solid #d1d5db;"
                                >
                            </div>

                        </div>


                        <!-- =================================================
                             DEPARTMENT
                        ================================================= -->

                        <div class="form-group">

                            <label>

                                Department

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <select
                                name="department_id"
                                required
                            >

                                <option value="">

                                    Select Department

                                </option>


                                <?php foreach (
                                    $departments
                                    as $department
                                ): ?>


                                    <option
                                        value="<?php

                                            echo (int)
                                                $department[
                                                    "id"
                                                ];

                                        ?>"

                                        <?php

                                        if (
                                            (int)(
                                                $_POST[
                                                    "department_id"
                                                ] ?? 0
                                            ) ===
                                            (int)$department[
                                                "id"
                                            ]
                                        ) {

                                            echo "selected";

                                        }

                                        ?>
                                    >

                                        <?php

                                        echo htmlspecialchars(
                                            $department[
                                                "department_name"
                                            ]
                                        );

                                        ?>

                                    </option>


                                <?php endforeach; ?>


                            </select>


                            <div class="help-text">

                                Select BCA, BBA or B.Com.

                            </div>

                        </div>


                        <!-- =================================================
                             DESIGNATION
                        ================================================= -->

                        <div class="form-group">

                            <label>

                                Designation

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <select
                                name="designation"
                                required
                            >

                                <option value="">

                                    Select Designation

                                </option>


                                <option
                                    value="Lecturer"

                                    <?php

                                    if (
                                        (
                                            $_POST[
                                                "designation"
                                            ] ?? ""
                                        ) === "Lecturer"
                                    ) {

                                        echo "selected";

                                    }

                                    ?>
                                >

                                    Lecturer

                                </option>


                                <option
                                    value="Assistant Professor"

                                    <?php

                                    if (
                                        (
                                            $_POST[
                                                "designation"
                                            ] ?? ""
                                        ) ===
                                        "Assistant Professor"
                                    ) {

                                        echo "selected";

                                    }

                                    ?>
                                >

                                    Assistant Professor

                                </option>


                                <option
                                    value="Associate Professor"

                                    <?php

                                    if (
                                        (
                                            $_POST[
                                                "designation"
                                            ] ?? ""
                                        ) ===
                                        "Associate Professor"
                                    ) {

                                        echo "selected";

                                    }

                                    ?>
                                >

                                    Associate Professor

                                </option>


                                <option
                                    value="Professor"

                                    <?php

                                    if (
                                        (
                                            $_POST[
                                                "designation"
                                            ] ?? ""
                                        ) === "Professor"
                                    ) {

                                        echo "selected";

                                    }

                                    ?>
                                >

                                    Professor

                                </option>


                                <option
                                    value="HOD"

                                    <?php

                                    if (
                                        (
                                            $_POST[
                                                "designation"
                                            ] ?? ""
                                        ) === "HOD"
                                    ) {

                                        echo "selected";

                                    }

                                    ?>
                                >

                                    HOD

                                </option>


                            </select>

                        </div>


                        <!-- =================================================
                             PASSWORD
                        ================================================= -->

                        <div class="form-group">

                            <label>

                                Initial Password

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input
                                type="password"
                                name="password"
                                id="password"
                                required
                                minlength="8"
                                maxlength="100"
                                autocomplete="new-password"
                                placeholder="Minimum 8 characters"
                            >


                            <div
                                class="password-strength"
                                id="passwordHelp"
                            >

                                Minimum 8 characters.

                            </div>


                            <div class="show-password">

                                <label
                                    style="
                                        display:inline;
                                        font-weight:normal;
                                    "
                                >

                                    <input
                                        type="checkbox"
                                        id="showPassword"
                                    >

                                    Show password

                                </label>

                            </div>

                        </div>


                        <!-- =================================================
                             CONFIRM PASSWORD
                        ================================================= -->

                        <div class="form-group">

                            <label>

                                Confirm Password

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input
                                type="password"
                                name="confirm_password"
                                id="confirmPassword"
                                required
                                minlength="8"
                                maxlength="100"
                                autocomplete="new-password"
                                placeholder="Re-enter password"
                            >


                            <div
                                class="help-text"
                                id="confirmHelp"
                            >

                                Enter the same password again.

                            </div>

                        </div>


                    </div>


                    <!-- =================================================
                         FORM ACTIONS
                    ================================================= -->

                    <div class="form-actions">


                        <button
                            type="submit"
                            class="btn-primary"
                        >

                            Add Lecturer

                        </button>


                        <a
                            href="index.php"
                            class="btn-secondary"
                        >

                            Cancel

                        </a>


                    </div>


                </form>


            </div>


        </div>


    </div>


</div>


<script>

/* =====================================================
   LECTURER PHOTO PREVIEW
===================================================== */

const photoInput = document.getElementById("photo");
const photoPreview = document.getElementById("photoPreview");

photoInput.addEventListener("change", function () {
    const file = this.files && this.files[0];

    if (!file) {
        photoPreview.src = "";
        photoPreview.style.display = "none";
        return;
    }

    const allowedTypes = ["image/jpeg", "image/png", "image/webp"];

    if (!allowedTypes.includes(file.type)) {
        alert("Please choose a JPG, PNG, or WEBP image.");
        this.value = "";
        photoPreview.src = "";
        photoPreview.style.display = "none";
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        alert("Photo must be 5 MB or smaller.");
        this.value = "";
        photoPreview.src = "";
        photoPreview.style.display = "none";
        return;
    }

    photoPreview.src = URL.createObjectURL(file);
    photoPreview.style.display = "block";
});


/* =====================================================
   EMAIL VALIDATION
===================================================== */

const emailInput =
    document.getElementById("email");

const emailHelp =
    document.getElementById("emailHelp");


emailInput.addEventListener(
    "input",
    function () {

        const email =
            emailInput.value.trim();


        if (email === "") {

            emailInput.classList.remove(
                "valid-field",
                "invalid-field"
            );

            emailHelp.textContent =
                "This email will be used for lecturer login and OTP verification.";

            return;

        }


        if (
            emailInput.checkValidity()
        ) {

            emailInput.classList.remove(
                "invalid-field"
            );

            emailInput.classList.add(
                "valid-field"
            );

            emailHelp.textContent =
                "Valid email address.";

        } else {

            emailInput.classList.remove(
                "valid-field"
            );

            emailInput.classList.add(
                "invalid-field"
            );

            emailHelp.textContent =
                "Please enter a valid email address.";

        }

    }
);


/* =====================================================
   PASSWORD VISIBILITY
===================================================== */

const passwordInput =
    document.getElementById("password");

const confirmPasswordInput =
    document.getElementById(
        "confirmPassword"
    );

const showPassword =
    document.getElementById("showPassword");


showPassword.addEventListener(
    "change",
    function () {

        const type =
            this.checked
                ? "text"
                : "password";


        passwordInput.type =
            type;

        confirmPasswordInput.type =
            type;

    }
);


/* =====================================================
   PASSWORD STRENGTH
===================================================== */

const passwordHelp =
    document.getElementById(
        "passwordHelp"
    );


passwordInput.addEventListener(
    "input",
    function () {

        const password =
            this.value;


        if (password.length === 0) {

            passwordHelp.textContent =
                "Minimum 8 characters.";

            return;

        }


        if (password.length < 8) {

            passwordHelp.textContent =
                "Password must contain at least 8 characters.";

            passwordInput.classList.remove(
                "valid-field"
            );

            passwordInput.classList.add(
                "invalid-field"
            );

        } else {

            passwordHelp.textContent =
                "Password length is valid.";

            passwordInput.classList.remove(
                "invalid-field"
            );

            passwordInput.classList.add(
                "valid-field"
            );

        }

    }
);


/* =====================================================
   CONFIRM PASSWORD
===================================================== */

const confirmHelp =
    document.getElementById(
        "confirmHelp"
    );


confirmPasswordInput.addEventListener(
    "input",
    function () {

        if (
            this.value === ""
        ) {

            confirmHelp.textContent =
                "Enter the same password again.";

            return;

        }


        if (
            this.value ===
            passwordInput.value
        ) {

            confirmHelp.textContent =
                "Passwords match.";

            this.classList.remove(
                "invalid-field"
            );

            this.classList.add(
                "valid-field"
            );

        } else {

            confirmHelp.textContent =
                "Passwords do not match.";

            this.classList.remove(
                "valid-field"
            );

            this.classList.add(
                "invalid-field"
            );

        }

    }
);


/* =====================================================
   FORM VALIDATION
===================================================== */

document
    .getElementById("lecturerForm")
    .addEventListener(
        "submit",
        function (event) {


            /* EMAIL */

            if (
                emailInput.value.trim() === "" ||
                !emailInput.checkValidity()
            ) {

                event.preventDefault();

                alert(
                    "Please enter a valid email address."
                );

                emailInput.focus();

                return;

            }


            /* PHONE */

            const phone =
                document
                    .getElementById("phone")
                    .value
                    .trim();


            if (
                phone !== "" &&
                !/^[0-9]{10}$/.test(phone)
            ) {

                event.preventDefault();

                alert(
                    "Phone number must contain exactly 10 digits."
                );

                document
                    .getElementById("phone")
                    .focus();

                return;

            }


            /* PASSWORD */

            if (
                passwordInput.value.length < 8
            ) {

                event.preventDefault();

                alert(
                    "Password must contain at least 8 characters."
                );

                passwordInput.focus();

                return;

            }


            /* CONFIRM PASSWORD */

            if (
                passwordInput.value !==
                confirmPasswordInput.value
            ) {

                event.preventDefault();

                alert(
                    "Passwords do not match."
                );

                confirmPasswordInput.focus();

                return;

            }

        }
    );

</script>


</body>

</html>