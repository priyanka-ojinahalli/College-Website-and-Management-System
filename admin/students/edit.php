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
$student = null;


/* =====================================================
   GET STUDENT ID
===================================================== */

$id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


if ($id <= 0) {

    header("Location: index.php");
    exit();

}


/* =====================================================
   GET STUDENT
===================================================== */

$stmt = $conn->prepare("

    SELECT
        s.*,
        u.username AS login_username,
        u.email AS user_email

    FROM students s

    LEFT JOIN users u
        ON s.user_id = u.id

    WHERE s.id = ?

    LIMIT 1

");

$stmt->bind_param("i", $id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header("Location: index.php");
    exit();

}

$student = $result->fetch_assoc();

$stmt->close();


/* =====================================================
   GET COURSES
===================================================== */

$courses = [];

$course_result = $conn->query("

    SELECT
        id,
        course_name,
        course_code

    FROM courses

    ORDER BY course_name ASC

");

if ($course_result) {

    while ($course = $course_result->fetch_assoc()) {

        $courses[] = $course;

    }

}


/* =====================================================
   UPDATE STUDENT
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /* -------------------------------------------------
       GET FORM DATA
    ------------------------------------------------- */

    $registration_no = trim(
        $_POST["registration_no"] ?? ""
    );

    $roll_no = trim(
        $_POST["roll_no"] ?? ""
    );

    $student_id = trim(
        $_POST["student_id"] ?? ""
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

    $gender = trim(
        $_POST["gender"] ?? ""
    );

    $date_of_birth = trim(
        $_POST["date_of_birth"] ?? ""
    );

    $address = trim(
        $_POST["address"] ?? ""
    );

    $father_name = trim(
        $_POST["father_name"] ?? ""
    );

    $mother_name = trim(
        $_POST["mother_name"] ?? ""
    );

    $guardian_phone = trim(
        $_POST["guardian_phone"] ?? ""
    );

    $abc_id = trim(
        $_POST["abc_id"] ?? ""
    );

    $student_status = trim(
        $_POST["student_status"] ?? "Active"
    );

    $course_id = (int) (
        $_POST["course_id"] ?? 0
    );

    $semester = (int) (
        $_POST["semester"] ?? 0
    );

    $admission_year = trim(
        $_POST["admission_year"] ?? ""
    );


    /* =================================================
       REQUIRED VALIDATION
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

        $error =
            "Please fill all required fields.";

    }


    /* =================================================
       EMAIL VALIDATION
    ================================================= */

    if ($error === "") {

        if (!filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )) {

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

        if (!preg_match(
            "/^[0-9]{10}$/",
            $phone
        )) {

            $error =
                "Phone number must contain exactly 10 digits.";

        }

    }


    /* =================================================
       GUARDIAN PHONE VALIDATION
    ================================================= */

    if (
        $error === "" &&
        $guardian_phone !== ""
    ) {

        if (!preg_match(
            "/^[0-9]{10}$/",
            $guardian_phone
        )) {

            $error =
                "Guardian phone number must contain exactly 10 digits.";

        }

    }


    /* =================================================
       CHECK DUPLICATE REGISTRATION NUMBER
       EXCLUDE CURRENT STUDENT
    ================================================= */

    if ($error === "") {

        $check = $conn->prepare("

            SELECT id

            FROM students

            WHERE registration_no = ?
            AND id != ?

            LIMIT 1

        ");

        $check->bind_param(
            "si",
            $registration_no,
            $id
        );

        $check->execute();

        $check_result =
            $check->get_result();

        if ($check_result->num_rows > 0) {

            $error =
                "This registration number already exists.";

        }

        $check->close();

    }


    /* =================================================
       CHECK DUPLICATE STUDENT ID
       EXCLUDE CURRENT STUDENT
    ================================================= */

    if ($error === "") {

        $check = $conn->prepare("

            SELECT id

            FROM students

            WHERE student_id = ?
            AND id != ?

            LIMIT 1

        ");

        $check->bind_param(
            "si",
            $student_id,
            $id
        );

        $check->execute();

        $check_result =
            $check->get_result();

        if ($check_result->num_rows > 0) {

            $error =
                "This Student ID already exists.";

        }

        $check->close();

    }


    /* =================================================
       UPDATE STUDENT + USER
    ================================================= */

    if ($error === "") {

        $conn->begin_transaction();

        try {

            /* =========================================
               UPDATE STUDENTS TABLE
            ========================================= */

            $student_stmt = $conn->prepare("

                UPDATE students

                SET

                    registration_no = ?,
                    roll_no = ?,
                    student_id = ?,
                    full_name = ?,
                    email = ?,
                    phone = ?,
                    gender = ?,
                    date_of_birth = ?,
                    address = ?,
                    father_name = ?,
                    mother_name = ?,
                    guardian_phone = ?,
                    abc_id = ?,
                    student_status = ?,
                    course_id = ?,
                    semester = ?,
                    admission_year = ?

                WHERE id = ?

            ");


            if (!$student_stmt) {

                throw new Exception(
                    "Unable to prepare student update."
                );

            }

            $student_stmt->bind_param(
              "sssssssssssssssiis",
           
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
                $admission_year,
                $id

            );


            if (!$student_stmt->execute()) {

                throw new Exception(
                    "Unable to update student: " .
                    $student_stmt->error
                );

            }


            $student_stmt->close();


            /* =========================================
               UPDATE USER ACCOUNT
            ========================================= */

            if (
                !empty($student["user_id"])
            ) {

                $user_stmt = $conn->prepare("

                    UPDATE users

                    SET
                        username = ?,
                        email = ?

                    WHERE id = ?

                ");


                if (!$user_stmt) {

                    throw new Exception(
                        "Unable to prepare user update."
                    );

                }


                $user_stmt->bind_param(

                    "ssi",

                    $registration_no,
                    $email,
                    $student["user_id"]

                );


                if (!$user_stmt->execute()) {

                    throw new Exception(
                        "Unable to update login account: " .
                        $user_stmt->error
                    );

                }


                $user_stmt->close();

            }


            /* =========================================
               COMMIT
            ========================================= */

            $conn->commit();


            header(
                "Location: index.php?updated=1"
            );

            exit();


        } catch (Exception $e) {

            $conn->rollback();

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

    <title>Edit Student - Admin</title>


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
                rgba(0,0,0,0.08);

            max-width: 1000px;

            margin-top: 25px;

        }


        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;

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


        .form-group textarea {

            min-height: 90px;

            resize: vertical;

        }


        .login-info {

            background: #eff6ff;

            border-left:
                4px solid #172554;

            padding: 15px;

            margin-bottom: 25px;

            border-radius: 5px;

        }


        .form-actions {

            display: flex;

            gap: 10px;

            margin-top: 30px;

            padding-top: 20px;

            border-top:
                1px solid #e5e7eb;

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


            <h2>Edit Student</h2>


            <p class="welcome">

                Update student information.

            </p>


            <?php if ($error !== ""): ?>

                <div class="alert error">

                    <?php
                    echo htmlspecialchars($error);
                    ?>

                </div>

            <?php endif; ?>


            <div class="student-form-card">


                <div class="login-info">

                    <strong>
                        Student Login
                    </strong>

                    <p style="margin-top: 7px;">

                        Registration Number is the
                        student's login username.

                    </p>

                    <p style="margin-top: 5px;">

                        Changing the registration number
                        will also change the login username.

                        The password will not be changed.

                    </p>

                </div>


                <form
                    method="POST"
                    action=""
                >


                    <div class="form-grid">


                        <!-- REGISTRATION NUMBER -->

                        <div class="form-group">

                            <label>

                                Registration Number
                                <span class="required">*</span>

                            </label>


                            <input
                                type="text"
                                name="registration_no"
                                required
                                maxlength="30"
                                value="<?php

                                echo htmlspecialchars(
                                    $_POST[
                                        "registration_no"
                                    ]
                                    ??
                                    $student[
                                        "registration_no"
                                    ]
                                );

                                ?>"
                            >

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
                                    $_POST["roll_no"]
                                    ??
                                    $student["roll_no"]
                                );

                                ?>"
                            >

                        </div>


                        <!-- STUDENT ID -->

                        <div class="form-group">

                            <label>

                                Student ID
                                <span class="required">*</span>

                            </label>


                            <input
                                type="text"
                                name="student_id"
                                required
                                maxlength="30"
                                value="<?php

                                echo htmlspecialchars(
                                    $_POST["student_id"]
                                    ??
                                    $student["student_id"]
                                );

                                ?>"
                            >

                        </div>


                        <!-- FULL NAME -->

                        <div class="form-group">

                            <label>

                                Full Name
                                <span class="required">*</span>

                            </label>


                            <input
                                type="text"
                                name="full_name"
                                required
                                maxlength="100"
                                value="<?php

                                echo htmlspecialchars(
                                    $_POST["full_name"]
                                    ??
                                    $student["full_name"]
                                );

                                ?>"
                            >

                        </div>


                        <!-- EMAIL -->

                        <div class="form-group">

                            <label>

                                Email
                                <span class="required">*</span>

                            </label>


                            <input
                                type="email"
                                name="email"
                                required
                                maxlength="150"
                                value="<?php

                                echo htmlspecialchars(
                                    $_POST["email"]
                                    ??
                                    $student["email"]
                                );

                                ?>"
                            >

                        </div>


                        <!-- PHONE -->

                        <div class="form-group">

                            <label>
                                Phone
                            </label>


                            <input
                                type="tel"
                                name="phone"
                                maxlength="10"
                                value="<?php

                                echo htmlspecialchars(
                                    $_POST["phone"]
                                    ??
                                    $student["phone"]
                                );

                                ?>"
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

                                    $gender =
                                        $_POST["gender"]
                                        ??
                                        $student["gender"];

                                    echo
                                        $gender === "Male"
                                        ? "selected"
                                        : "";

                                    ?>
                                >
                                    Male
                                </option>


                                <option
                                    value="Female"

                                    <?php

                                    echo
                                        $gender === "Female"
                                        ? "selected"
                                        : "";

                                    ?>
                                >
                                    Female
                                </option>


                                <option
                                    value="Other"

                                    <?php

                                    echo
                                        $gender === "Other"
                                        ? "selected"
                                        : "";

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
                                    ]
                                    ??
                                    $student[
                                        "date_of_birth"
                                    ]
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
                                    ]
                                    ??
                                    $student[
                                        "father_name"
                                    ]
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
                                    ]
                                    ??
                                    $student[
                                        "mother_name"
                                    ]
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
                                value="<?php

                                echo htmlspecialchars(
                                    $_POST[
                                        "guardian_phone"
                                    ]
                                    ??
                                    $student[
                                        "guardian_phone"
                                    ]
                                );

                                ?>"
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
                                    $_POST["abc_id"]
                                    ??
                                    $student["abc_id"]
                                );

                                ?>"
                            >

                        </div>


                        <!-- COURSE -->

                        <div class="form-group">

                            <label>

                                Course
                                <span class="required">*</span>

                            </label>


                            <?php

                            $selected_course =
                                (int)(
                                    $_POST["course_id"]
                                    ??
                                    $student["course_id"]
                                );

                            ?>


                            <select
                                name="course_id"
                                required
                            >

                                <option value="">
                                    Select Course
                                </option>


                                <?php foreach (
                                    $courses
                                    as $course
                                ): ?>

                                    <option
                                        value="<?php
                                            echo (int)
                                                $course["id"];
                                        ?>"

                                        <?php

                                        echo
                                            $selected_course
                                            ===
                                            (int)$course["id"]
                                            ? "selected"
                                            : "";

                                        ?>
                                    >

                                        <?php

                                        echo htmlspecialchars(
                                            $course[
                                                "course_code"
                                            ]
                                            . " - "
                                            .
                                            $course[
                                                "course_name"
                                            ]
                                        );

                                        ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- SEMESTER -->

                        <div class="form-group">

                            <label>

                                Semester
                                <span class="required">*</span>

                            </label>


                            <?php

                            $selected_semester =
                                (int)(
                                    $_POST["semester"]
                                    ??
                                    $student["semester"]
                                );

                            ?>


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

                                        echo
                                            $selected_semester
                                            === $i
                                            ? "selected"
                                            : "";

                                        ?>
                                    >

                                        Semester
                                        <?php echo $i; ?>

                                    </option>

                                <?php endfor; ?>

                            </select>

                        </div>


                        <!-- ADMISSION YEAR -->

                        <div class="form-group">

                            <label>

                                Admission Year
                                <span class="required">*</span>

                            </label>


                            <input
                                type="number"
                                name="admission_year"
                                required
                                min="2000"
                                max="<?php
                                    echo date("Y");
                                ?>"
                                value="<?php

                                echo htmlspecialchars(
                                    $_POST[
                                        "admission_year"
                                    ]
                                    ??
                                    $student[
                                        "admission_year"
                                    ]
                                );

                                ?>"
                            >

                        </div>


                        <!-- STATUS -->

                        <div class="form-group">

                            <label>
                                Student Status
                            </label>


                            <?php

                            $selected_status =
                                $_POST[
                                    "student_status"
                                ]
                                ??
                                $student[
                                    "student_status"
                                ];

                            ?>


                            <select
                                name="student_status"
                            >

                                <option
                                    value="Active"

                                    <?php

                                    echo
                                        $selected_status
                                        === "Active"
                                        ? "selected"
                                        : "";

                                    ?>
                                >
                                    Active
                                </option>


                                <option
                                    value="Graduated"

                                    <?php

                                    echo
                                        $selected_status
                                        === "Graduated"
                                        ? "selected"
                                        : "";

                                    ?>
                                >
                                    Graduated
                                </option>


                                <option
                                    value="Discontinued"

                                    <?php

                                    echo
                                        $selected_status
                                        === "Discontinued"
                                        ? "selected"
                                        : "";

                                    ?>
                                >
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
                            ><?php

                            echo htmlspecialchars(
                                $_POST["address"]
                                ??
                                $student["address"]
                            );

                            ?></textarea>

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


</body>

</html>