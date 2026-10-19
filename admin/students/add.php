<?php

session_start();

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../../auth/login.php");
    exit();
}

require_once "../../config/database.php";

$error = "";


/* =====================================================
   GET COURSES
===================================================== */

$courses = [];

$course_result = $conn->query("
    SELECT id, course_name, course_code
    FROM courses
    ORDER BY course_name ASC
");

if ($course_result) {

    while ($course = $course_result->fetch_assoc()) {
        $courses[] = $course;
    }

}


/* =====================================================
   ADD STUDENT
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* -------------------------------------------------
       GET FORM DATA
    ------------------------------------------------- */

    $registration_no = trim($_POST["registration_no"] ?? "");
    $roll_no         = trim($_POST["roll_no"] ?? "");
    $student_id      = trim($_POST["student_id"] ?? "");
    $full_name       = trim($_POST["full_name"] ?? "");
    $email           = trim($_POST["email"] ?? "");
    $phone           = trim($_POST["phone"] ?? "");
    $gender          = trim($_POST["gender"] ?? "");
    $date_of_birth   = trim($_POST["date_of_birth"] ?? "");
    $address         = trim($_POST["address"] ?? "");
    $father_name     = trim($_POST["father_name"] ?? "");
    $mother_name     = trim($_POST["mother_name"] ?? "");
    $guardian_phone  = trim($_POST["guardian_phone"] ?? "");
    $abc_id          = trim($_POST["abc_id"] ?? "");
    $student_status  = trim($_POST["student_status"] ?? "Active");
    $course_id       = (int) ($_POST["course_id"] ?? 0);
    $semester        = (int) ($_POST["semester"] ?? 0);
    $admission_year  = trim($_POST["admission_year"] ?? "");


    /* =================================================
       REQUIRED FIELD VALIDATION
    ================================================= */

    if (
        $registration_no === "" ||
        $student_id === "" ||
        $full_name === "" ||
        $email === "" ||
        $course_id <= 0 ||
        $semester < 1 ||
        $semester > 6 ||
        $admission_year === ""
    ) {

        $error = "Please fill all required fields.";

    }


    /* =================================================
       EMAIL VALIDATION
    ================================================= */

    if ($error === "") {

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error =
                "Please enter a valid email address. Example: student@gmail.com";

        }

    }


    /* =================================================
       PHONE VALIDATION
    ================================================= */

    if ($error === "" && $phone !== "") {

        if (!preg_match("/^[0-9]{10}$/", $phone)) {

            $error =
                "Phone number must contain exactly 10 digits.";

        }

    }


    /* =================================================
       GUARDIAN PHONE VALIDATION
    ================================================= */

    if ($error === "" && $guardian_phone !== "") {

        if (!preg_match("/^[0-9]{10}$/", $guardian_phone)) {

            $error =
                "Guardian phone number must contain exactly 10 digits.";

        }

    }


    /* =================================================
       REGISTRATION NUMBER VALIDATION
    ================================================= */

    if ($error === "") {

        if (!preg_match(
            "/^[A-Za-z0-9\/_-]{3,30}$/",
            $registration_no
        )) {

            $error =
                "Registration number contains invalid characters.";

        }

    }


    /* =================================================
       CHECK DUPLICATE REGISTRATION NUMBER
    ================================================= */

    if ($error === "") {

        $check = $conn->prepare("
            SELECT id
            FROM students
            WHERE registration_no = ?
            LIMIT 1
        ");

        $check->bind_param(
            "s",
            $registration_no
        );

        $check->execute();

        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {

            $error =
                "This registration number already exists.";

        }

        $check->close();

    }


    /* =================================================
       CHECK DUPLICATE STUDENT ID
    ================================================= */

    if ($error === "") {

        $check = $conn->prepare("
            SELECT id
            FROM students
            WHERE student_id = ?
            LIMIT 1
        ");

        $check->bind_param(
            "s",
            $student_id
        );

        $check->execute();

        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {

            $error =
                "This Student ID already exists.";

        }

        $check->close();

    }


    /* =================================================
       CHECK USERNAME IN USERS TABLE
       Registration Number = Username
    ================================================= */

    if ($error === "") {

        $check = $conn->prepare("
            SELECT id
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $check->bind_param(
            "s",
            $registration_no
        );

        $check->execute();

        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {

            $error =
                "This registration number is already being used as a login username.";

        }

        $check->close();

    }


    /* =================================================
       CREATE USER + STUDENT
    ================================================= */

    if ($error === "") {

        /*
         * Start database transaction.
         *
         * If anything fails, both records are rolled back.
         */

        $conn->begin_transaction();


        try {

            /* =========================================
               CREATE STUDENT LOGIN ACCOUNT
            ========================================= */

            $username = $registration_no;

            /*
             * Initial password is the registration number.
             *
             * Example:
             *
             * Username: BCA2026-001
             * Password: BCA2026-001
             */

            $initial_password = $registration_no;

            $hashed_password =
                password_hash(
                    $initial_password,
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
                    'student'
                )

            ");


            if (!$user_stmt) {

                throw new Exception(
                    "Unable to prepare user account."
                );

            }


            $user_stmt->bind_param(
                "sss",
                $username,
                $email,
                $hashed_password
            );


            if (!$user_stmt->execute()) {

                throw new Exception(
                    "Unable to create student login account: " .
                    $user_stmt->error
                );

            }


            /* =========================================
               GET NEW USER ID
            ========================================= */

            $user_id =
                $conn->insert_id;


            $user_stmt->close();


            /* =========================================
               CREATE STUDENT RECORD
            ========================================= */

            $student_stmt = $conn->prepare("

                INSERT INTO students
                (

                    user_id,
                    registration_no,
                    roll_no,
                    student_id,
                    full_name,
                    email,
                    phone,
                    gender,
                    date_of_birth,
                    address,
                    father_name,
                    mother_name,
                    guardian_phone,
                    abc_id,
                    student_status,
                    course_id,
                    semester,
                    admission_year

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
                    ?,
                    ?,
                    ?,
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


            if (!$student_stmt) {

                throw new Exception(
                    "Unable to prepare student record."
                );

            }


            /*
             * 18 values:
             *
             * user_id       = integer
             * registration  = string
             * roll_no       = string
             * student_id    = string
             * full_name     = string
             * email         = string
             * phone         = string
             * gender        = string
             * dob           = string
             * address       = string
             * father        = string
             * mother        = string
             * guardian      = string
             * abc_id        = string
             * status        = string
             * course_id     = integer
             * semester      = integer
             * admission     = string
             */

            $student_stmt->bind_param(

                "issssssssssssssiis",

                $user_id,
                $registration_no,
                $roll_no,
                $student_id,
                $full_name,
                $email,
                $phone,
                $gender,
                $date_of_birth,
                $address,
                $father_name,
                $mother_name,
                $guardian_phone,
                $abc_id,
                $student_status,
                $course_id,
                $semester,
                $admission_year

            );


            if (!$student_stmt->execute()) {

                throw new Exception(
                    "Unable to create student record: " .
                    $student_stmt->error
                );

            }


            $student_stmt->close();


            /* =========================================
               EVERYTHING SUCCESSFUL
            ========================================= */

            $conn->commit();


            /*
             * Redirect to students list.
             */

            header(
                "Location: index.php?added=1"
            );

            exit();


        } catch (Exception $e) {

            /*
             * Undo user creation if student creation fails.
             */

            $conn->rollback();

            $error = $e->getMessage();

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

    <title>Add Student - Admin</title>


    <link
        rel="stylesheet"
        href="../../css/admin.css?v=9"
    >


    <style>

        .student-form-card {

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
        .form-group select,
        .form-group textarea {

            width: 100%;

            padding: 11px;

            border: 1px solid #d1d5db;

            border-radius: 5px;

            font-family: inherit;

            font-size: 15px;

        }


        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {

            outline: none;

            border-color: #172554;

            box-shadow:
                0 0 0 2px
                rgba(23, 37, 84, 0.1);

        }


        .form-group textarea {

            min-height: 90px;

            resize: vertical;

        }


        .help-text {

            font-size: 12px;

            color: #6b7280;

            margin-top: 5px;

        }


        .form-actions {

            display: flex;

            gap: 10px;

            margin-top: 30px;

            padding-top: 20px;

            border-top:
                1px solid #e5e7eb;

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


        .valid-field {

            border-color: #16a34a !important;

        }


        .invalid-field {

            border-color: #dc2626 !important;

        }


        .login-info {

            background: #eff6ff;

            border-left: 4px solid #172554;

            padding: 15px;

            margin-bottom: 25px;

            border-radius: 5px;

        }


        .login-info strong {

            color: #172554;

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


    <!-- SIDEBAR -->

    <?php include "../../includes/admin_sidebar.php"; ?>


    <!-- MAIN CONTENT -->

    <div class="main-content">


        <!-- HEADER -->

        <?php include "../../includes/admin_header.php"; ?>


        <!-- CONTENT -->

        <div class="content">


            <h2>Add Student</h2>


            <p class="welcome">

                Add a new student to the college.

            </p>


            <?php if ($error !== ""): ?>

                <div class="alert error">

                    <?php

                    echo htmlspecialchars($error);

                    ?>

                </div>

            <?php endif; ?>


            <div class="student-form-card">


                <!-- LOGIN INFORMATION -->

                <div class="login-info">

                    <strong>
                        Student Login Information
                    </strong>

                    <p style="margin-top: 7px;">

                        The student's
                        <strong>
                            Registration Number
                        </strong>
                        will be used as the login username.

                        The initial password will also be the
                        Registration Number.

                    </p>

                    <p style="margin-top: 5px;">

                        The student can change the password
                        after logging in.

                    </p>

                </div>


                <form
                    method="POST"
                    action=""
                    id="studentForm"
                    novalidate
                >


                    <div class="form-grid">


                        <!-- REGISTRATION NUMBER -->

                        <div class="form-group">

                            <label>

                                Registration Number

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                name="registration_no"
                                required
                                maxlength="30"
                                autocomplete="username"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "registration_no"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="Example: BCA2026-001"
                            >


                            <div class="help-text">

                                This will also be the student's
                                login username and initial password.

                            </div>

                        </div>


                        <!-- ROLL NUMBER -->

                        <div class="form-group">

                            <label>
                                Roll Number
                            </label>


                            <input
                                type="text"
                                name="roll_no"
                                maxlength="20"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "roll_no"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="Example: 01"
                            >

                        </div>


                        <!-- STUDENT ID -->

                        <div class="form-group">

                            <label>

                                Student ID

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                name="student_id"
                                required
                                maxlength="30"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "student_id"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="Example: BCA001"
                            >

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
                                        $_POST[
                                            "full_name"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="Student full name"
                            >

                        </div>


                        <!-- EMAIL -->

                        <div class="form-group">

                            <label>

                                Email

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
                                placeholder="student@gmail.com"
                            >


                            <div
                                class="help-text"
                                id="emailHelp"
                            >

                                Enter a valid email address.

                            </div>

                        </div>


                        <!-- PHONE -->

                        <div class="form-group">

                            <label>
                                Phone
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

                        </div>


                        <!-- GENDER -->

                        <div class="form-group">

                            <label>
                                Gender
                            </label>


                            <select name="gender">

                                <option value="">
                                    Select Gender
                                </option>


                                <option
                                    value="Male"

                                    <?php

                                    if (
                                        (
                                            $_POST[
                                                "gender"
                                            ] ?? ""
                                        ) === "Male"
                                    ) {
                                        echo "selected";
                                    }

                                    ?>
                                >
                                    Male
                                </option>


                                <option
                                    value="Female"

                                    <?php

                                    if (
                                        (
                                            $_POST[
                                                "gender"
                                            ] ?? ""
                                        ) === "Female"
                                    ) {
                                        echo "selected";
                                    }

                                    ?>
                                >
                                    Female
                                </option>


                                <option
                                    value="Other"

                                    <?php

                                    if (
                                        (
                                            $_POST[
                                                "gender"
                                            ] ?? ""
                                        ) === "Other"
                                    ) {
                                        echo "selected";
                                    }

                                    ?>
                                >
                                    Other
                                </option>

                            </select>

                        </div>


                        <!-- DATE OF BIRTH -->

                        <div class="form-group">

                            <label>
                                Date of Birth
                            </label>


                            <input
                                type="date"
                                name="date_of_birth"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "date_of_birth"
                                        ] ?? ""
                                    );

                                ?>"
                            >

                        </div>


                        <!-- FATHER -->

                        <div class="form-group">

                            <label>
                                Father's Name
                            </label>


                            <input
                                type="text"
                                name="father_name"
                                maxlength="100"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "father_name"
                                        ] ?? ""
                                    );

                                ?>"
                            >

                        </div>


                        <!-- MOTHER -->

                        <div class="form-group">

                            <label>
                                Mother's Name
                            </label>


                            <input
                                type="text"
                                name="mother_name"
                                maxlength="100"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "mother_name"
                                        ] ?? ""
                                    );

                                ?>"
                            >

                        </div>


                        <!-- GUARDIAN PHONE -->

                        <div class="form-group">

                            <label>
                                Guardian Phone
                            </label>


                            <input
                                type="tel"
                                name="guardian_phone"
                                maxlength="10"
                                pattern="[0-9]{10}"
                                inputmode="numeric"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "guardian_phone"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="10-digit mobile number"
                            >

                        </div>


                        <!-- ABC ID -->

                        <div class="form-group">

                            <label>
                                ABC ID
                            </label>


                            <input
                                type="text"
                                name="abc_id"
                                maxlength="50"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "abc_id"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="Academic Bank of Credits ID"
                            >

                        </div>


                        <!-- COURSE -->

                        <div class="form-group">

                            <label>

                                Course

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <select
                                name="course_id"
                                required
                            >

                                <option value="">
                                    Select Course
                                </option>


                                <?php foreach (
                                    $courses as $course
                                ): ?>


                                    <option
                                        value="<?php

                                            echo (int)
                                                $course["id"];

                                        ?>"

                                        <?php

                                        if (
                                            (int)(
                                                $_POST[
                                                    "course_id"
                                                ] ?? 0
                                            ) ===
                                            (int)$course["id"]
                                        ) {
                                            echo "selected";
                                        }

                                        ?>
                                    >

                                        <?php

                                        echo htmlspecialchars(
                                            $course[
                                                "course_code"
                                            ] .
                                            " - " .
                                            $course[
                                                "course_name"
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


                        <!-- SEMESTER -->

                        <div class="form-group">

                            <label>

                                Semester

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <select
                                name="semester"
                                required
                            >

                                <option value="">
                                    Select Semester
                                </option>


                                <?php for (
                                    $i = 1;
                                    $i <= 6;
                                    $i++
                                ): ?>


                                    <option
                                        value="<?php
                                            echo $i;
                                        ?>"

                                        <?php

                                        if (
                                            (int)(
                                                $_POST[
                                                    "semester"
                                                ] ?? 0
                                            ) === $i
                                        ) {
                                            echo "selected";
                                        }

                                        ?>
                                    >

                                        Semester
                                        <?php echo $i; ?>

                                    </option>


                                <?php endfor; ?>


                            </select>


                            <div class="help-text">

                                Semester 1-2 = 1st Year,
                                3-4 = 2nd Year,
                                5-6 = 3rd Year.

                            </div>

                        </div>


                        <!-- ADMISSION YEAR -->

                        <div class="form-group">

                            <label>

                                Admission Year

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <select
                                name="admission_year"
                                required
                            >

                                <option value="">
                                    Select Year
                                </option>


                                <?php

                                $current_year =
                                    (int)date("Y");


                                for (
                                    $year =
                                        $current_year;

                                    $year >=
                                        $current_year - 10;

                                    $year--
                                ):

                                ?>


                                    <option
                                        value="<?php
                                            echo $year;
                                        ?>"

                                        <?php

                                        if (
                                            (
                                                $_POST[
                                                    "admission_year"
                                                ] ?? ""
                                            ) == $year
                                        ) {
                                            echo "selected";
                                        }

                                        ?>
                                    >

                                        <?php
                                        echo $year;
                                        ?>

                                    </option>


                                <?php endfor; ?>


                            </select>

                        </div>


                        <!-- STATUS -->

                        <div class="form-group">

                            <label>
                                Student Status
                            </label>


                            <select
                                name="student_status"
                            >

                                <option value="Active">
                                    Active
                                </option>

                                <option value="Graduated">
                                    Graduated
                                </option>

                                <option value="Discontinued">
                                    Discontinued
                                </option>

                            </select>

                        </div>


                        <!-- ADDRESS -->

                        <div class="form-group full">

                            <label>
                                Address
                            </label>


                            <textarea
                                name="address"
                                maxlength="500"
                                placeholder="Student address"
                            ><?php

                                echo htmlspecialchars(
                                    $_POST[
                                        "address"
                                    ] ?? ""
                                );

                            ?></textarea>

                        </div>


                    </div>


                    <!-- FORM ACTIONS -->

                    <div class="form-actions">


                        <button
                            type="submit"
                            class="btn-primary"
                        >
                            Add Student
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
                "Enter a valid email address.";

            return;

        }


        if (emailInput.checkValidity()) {

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
                "Please enter a valid email, e.g. student@gmail.com";

        }

    }
);


/* =====================================================
   FORM VALIDATION
===================================================== */

document
    .getElementById("studentForm")
    .addEventListener(
        "submit",
        function (event) {

            const email =
                emailInput.value.trim();


            if (
                email === "" ||
                !emailInput.checkValidity()
            ) {

                event.preventDefault();

                emailInput.focus();

                emailHelp.textContent =
                    "Please enter a valid email address.";

                emailInput.classList.add(
                    "invalid-field"
                );

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


            /* REGISTRATION NUMBER */

            const registration =
                document
                    .querySelector(
                        '[name="registration_no"]'
                    )
                    .value
                    .trim();


            if (
                !/^[A-Za-z0-9/_-]{3,30}$/
                    .test(registration)
            ) {

                event.preventDefault();

                alert(
                    "Please enter a valid registration number."
                );

                return;

            }

        }
    );

</script>


</body>

</html>