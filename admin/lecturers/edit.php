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
$success = "";


/* =====================================================
   GET LECTURER ID
===================================================== */

$id = (int)($_GET["id"] ?? 0);

if ($id <= 0) {
    header("Location: index.php");
    exit();
}


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
   GET LECTURER
===================================================== */

$stmt = $conn->prepare("
    SELECT
        l.id,
        l.user_id,
        l.lecturer_id,
        l.full_name,
        l.email,
        l.phone,
        l.department_id,
        l.designation,
        l.photo
    FROM lecturers l
    WHERE l.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    $stmt->close();

    header("Location: index.php");
    exit();

}

$lecturer = $result->fetch_assoc();

$stmt->close();


/* =====================================================
   UPDATE LECTURER
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

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


    /* =================================================
       VALIDATION
    ================================================= */

    if (
        $lecturer_id === "" ||
        $full_name === "" ||
        $email === "" ||
        $department_id <= 0 ||
        $designation === ""
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
       PHOTO UPLOAD VALIDATION (OPTIONAL)
    ================================================= */

    $new_photo_filename = null;
    $new_photo_path = null;
    $old_photo_filename = $lecturer["photo"] ?? null;

    if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] !== UPLOAD_ERR_NO_FILE) {

        $photo_upload = $_FILES["photo"];

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
                    $new_photo_filename = "lecturer_" . bin2hex(random_bytes(16)) . "." .
                        $allowed_photo_types[$photo_mime];
                } catch (Exception $e) {
                    $error = "Unable to prepare the photo upload. Please try again.";
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
       CHECK DUPLICATE LECTURER ID
    ================================================= */

    if ($error === "") {

        $check = $conn->prepare("
            SELECT id
            FROM lecturers
            WHERE lecturer_id = ?
            AND id != ?
            LIMIT 1
        ");

        $check->bind_param(
            "si",
            $lecturer_id,
            $id
        );

        $check->execute();

        $check_result =
            $check->get_result();

        if ($check_result->num_rows > 0) {

            $error =
                "This Lecturer ID is already used by another lecturer.";

        }

        $check->close();

    }


    /* =================================================
       CHECK DUPLICATE EMAIL
    ================================================= */

    if ($error === "") {

        $check = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            AND id != ?
            LIMIT 1
        ");

        $check->bind_param(
            "si",
            $email,
            $lecturer["user_id"]
        );

        $check->execute();

        $check_result =
            $check->get_result();

        if ($check_result->num_rows > 0) {

            $error =
                "This email address is already registered.";

        }

        $check->close();

    }


    /* =================================================
       UPDATE
    ================================================= */

    if ($error === "") {

        $conn->begin_transaction();

        try {

            /* =========================================
               SAVE REPLACEMENT PHOTO (IF PROVIDED)
            ========================================= */

            if ($new_photo_filename !== null) {
                $photo_directory = __DIR__ . "/../../images/faculty/";

                if (
                    !is_dir($photo_directory) &&
                    !mkdir($photo_directory, 0755, true) &&
                    !is_dir($photo_directory)
                ) {
                    throw new Exception("Unable to create the faculty photo folder.");
                }

                $new_photo_path = $photo_directory . $new_photo_filename;

                if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $new_photo_path)) {
                    $new_photo_path = null;
                    throw new Exception("Unable to save the lecturer photo.");
                }
            }

            $photo_to_save = $new_photo_filename !== null
                ? $new_photo_filename
                : ($lecturer["photo"] ?? null);


            /* =========================================
               UPDATE USERS TABLE
            ========================================= */

            $user_stmt = $conn->prepare("
                UPDATE users
                SET
                    username = ?,
                    email = ?
                WHERE id = ?
                AND role = 'lecturer'
            ");

            if (!$user_stmt) {

                throw new Exception(
                    "Unable to prepare user update."
                );

            }

            $user_stmt->bind_param(
                "ssi",
                $email,
                $email,
                $lecturer["user_id"]
            );

            if (!$user_stmt->execute()) {

                throw new Exception(
                    "Unable to update lecturer login account: " .
                    $user_stmt->error
                );

            }

            $user_stmt->close();


            /* =========================================
               UPDATE LECTURERS TABLE
            ========================================= */

            $lecturer_stmt = $conn->prepare("
                UPDATE lecturers
                SET
                    lecturer_id = ?,
                    full_name = ?,
                    email = ?,
                    phone = ?,
                    department_id = ?,
                    designation = ?,
                    photo = ?
                WHERE id = ?
            ");

            if (!$lecturer_stmt) {

                throw new Exception(
                    "Unable to prepare lecturer update."
                );

            }

            $lecturer_stmt->bind_param(
                "ssssissi",
                $lecturer_id,
                $full_name,
                $email,
                $phone,
                $department_id,
                $designation,
                $photo_to_save,
                $id
            );

            if (!$lecturer_stmt->execute()) {

                throw new Exception(
                    "Unable to update lecturer: " .
                    $lecturer_stmt->error
                );

            }

            $lecturer_stmt->close();


            /* =========================================
               SAVE
            ========================================= */

            $conn->commit();

            // Remove the old image only after the database update succeeds.
            if (
                $new_photo_filename !== null &&
                !empty($old_photo_filename)
            ) {
                $old_photo_path = __DIR__ . "/../../images/faculty/" .
                    basename($old_photo_filename);

                if (is_file($old_photo_path)) {
                    @unlink($old_photo_path);
                }
            }

            header(
                "Location: index.php?updated=1"
            );

            exit();

        } catch (Exception $e) {

            $conn->rollback();

            if ($new_photo_path !== null && is_file($new_photo_path)) {
                @unlink($new_photo_path);
            }

            $error =
                $e->getMessage();

        }

    }


    /*
     * Keep entered values visible if
     * validation fails.
     */

    $lecturer["lecturer_id"] =
        $lecturer_id;

    $lecturer["full_name"] =
        $full_name;

    $lecturer["email"] =
        $email;

    $lecturer["phone"] =
        $phone;

    $lecturer["department_id"] =
        $department_id;

    $lecturer["designation"] =
        $designation;

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

    <title>Edit Lecturer - Admin</title>


    <link
        rel="stylesheet"
        href="../../css/admin.css?v=12"
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


        .alert.error {

            max-width: 1000px;

            background: #fee2e2;

            color: #991b1b;

            padding: 12px 15px;

            border-radius: 5px;

            margin-top: 20px;

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


        @media (max-width: 700px) {

            .form-grid {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>


<body>


<div class="dashboard">


    <!-- SIDEBAR -->

    <?php

    include "../../includes/admin_sidebar.php";

    ?>


    <div class="main-content">


        <!-- HEADER -->

        <?php

        include "../../includes/admin_header.php";

        ?>


        <div class="content">


            <h2>
                Edit Lecturer
            </h2>


            <p class="welcome">

                Update lecturer profile and login information.

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


                <form
                    method="POST"
                    action=""
                    enctype="multipart/form-data"
                    id="editLecturerForm"
                    novalidate
                >


                    <div class="form-grid">


                        <!-- LECTURER ID -->

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
                                        $lecturer[
                                            "lecturer_id"
                                        ]
                                    );

                                ?>"
                            >


                            <div class="help-text">

                                Unique lecturer identification number.

                            </div>

                        </div>


                        <!-- FULL NAME -->

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
                                        $lecturer[
                                            "full_name"
                                        ]
                                    );

                                ?>"
                            >

                        </div>


                        <!-- EMAIL -->

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
                                value="<?php

                                    echo htmlspecialchars(
                                        $lecturer[
                                            "email"
                                        ]
                                    );

                                ?>"
                            >


                            <div
                                class="help-text"
                                id="emailHelp"
                            >

                                This is also the lecturer's login email.

                            </div>

                        </div>


                        <!-- PHONE -->

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
                                        $lecturer[
                                            "phone"
                                        ] ?? ""
                                    );

                                ?>"
                            >


                            <div class="help-text">

                                Enter exactly 10 digits.

                            </div>

                        </div>


                        <!-- CURRENT / NEW PHOTO -->

                        <div class="form-group">
                            <label for="photo">Lecturer Photo</label>

                            <?php if (!empty($lecturer["photo"])): ?>
                                <div style="margin-bottom: 12px;">
                                    <img
                                        src="../../images/faculty/<?php echo rawurlencode(basename($lecturer["photo"])); ?>"
                                        alt="Current lecturer photo"
                                        style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #d1d5db;"
                                    >
                                </div>
                            <?php else: ?>
                                <p class="help-text">No photo uploaded yet.</p>
                            <?php endif; ?>

                            <input
                                type="file"
                                name="photo"
                                id="photo"
                                accept="image/jpeg,image/png,image/webp"
                            >

                            <div class="help-text">
                                Optional. Choose a new image to replace the current photo.
                                JPG, PNG, or WEBP; maximum 5 MB.
                            </div>

                            <div style="margin-top:12px;">
                                <img
                                    id="photoPreview"
                                    src=""
                                    alt="New lecturer photo preview"
                                    style="display:none;width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #d1d5db;"
                                >
                            </div>
                        </div>


                        <!-- DEPARTMENT -->

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
                                            (int)$lecturer[
                                                "department_id"
                                            ] ===
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

                        </div>


                        <!-- DESIGNATION -->

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

                                <?php

                                $designations = [

                                    "Lecturer",

                                    "Assistant Professor",

                                    "Associate Professor",

                                    "Professor",

                                    "HOD"

                                ];

                                foreach (
                                    $designations
                                    as $designation
                                ):

                                ?>

                                    <option
                                        value="<?php
                                            echo htmlspecialchars(
                                                $designation
                                            );
                                        ?>"

                                        <?php

                                        if (
                                            $lecturer[
                                                "designation"
                                            ] ===
                                            $designation
                                        ) {

                                            echo "selected";

                                        }

                                        ?>
                                    >

                                        <?php

                                        echo htmlspecialchars(
                                            $designation
                                        );

                                        ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                    </div>


                    <!-- ACTIONS -->

                    <div class="form-actions">


                        <button
                            type="submit"
                            class="btn-primary"
                        >

                            Save Changes

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
   EMAIL VALIDATION
===================================================== */

const form =
    document.getElementById(
        "editLecturerForm"
    );

const email =
    document.getElementById("email");

const phone =
    document.getElementById("phone");

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


form.addEventListener(
    "submit",
    function(event) {

        if (
            !email.checkValidity()
        ) {

            event.preventDefault();

            alert(
                "Please enter a valid email address."
            );

            email.focus();

            return;

        }


        if (
            phone.value.trim() !== "" &&
            !/^[0-9]{10}$/.test(
                phone.value.trim()
            )
        ) {

            event.preventDefault();

            alert(
                "Phone number must contain exactly 10 digits."
            );

            phone.focus();

        }

    }
);

</script>


</body>

</html>