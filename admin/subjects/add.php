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
   GET LECTURERS
===================================================== */

$lecturers = [];

$lecturer_result = $conn->query("
    SELECT id, lecturer_id, full_name, email
    FROM lecturers
    ORDER BY full_name ASC
");

if ($lecturer_result) {

    while ($lecturer = $lecturer_result->fetch_assoc()) {
        $lecturers[] = $lecturer;
    }

}


/* =====================================================
   ADD SUBJECT
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $subject_name = trim(
        $_POST["subject_name"] ?? ""
    );

    $subject_code = trim(
        $_POST["subject_code"] ?? ""
    );

    $course_id = (int)(
        $_POST["course_id"] ?? 0
    );

    $semester = (int)(
        $_POST["semester"] ?? 0
    );

    $lecturer_id = (int)(
        $_POST["lecturer_id"] ?? 0
    );


    /* =================================================
       REQUIRED VALIDATION
    ================================================= */

    if (
        $subject_name === "" ||
        $course_id <= 0 ||
        $semester < 1 ||
        $semester > 6
    ) {

        $error =
            "Please fill all required fields.";

    }


    /* =================================================
       SUBJECT NAME VALIDATION
    ================================================= */

    if ($error === "") {

        if (strlen($subject_name) > 150) {

            $error =
                "Subject name must not exceed 150 characters.";

        }

    }


    /* =================================================
       SUBJECT CODE VALIDATION
    ================================================= */

    if ($error === "" && $subject_code !== "") {

        if (strlen($subject_code) > 50) {

            $error =
                "Subject code must not exceed 50 characters.";

        }

    }


    /* =================================================
       CHECK COURSE EXISTS
    ================================================= */

    if ($error === "") {

        $check_course = $conn->prepare("
            SELECT id
            FROM courses
            WHERE id = ?
            LIMIT 1
        ");

        $check_course->bind_param(
            "i",
            $course_id
        );

        $check_course->execute();

        $course_result_check =
            $check_course->get_result();

        if (
            $course_result_check->num_rows !== 1
        ) {

            $error =
                "Selected course does not exist.";

        }

        $check_course->close();

    }


    /* =================================================
       CHECK LECTURER EXISTS
    ================================================= */

    if (
        $error === "" &&
        $lecturer_id > 0
    ) {

        $check_lecturer = $conn->prepare("
            SELECT id
            FROM lecturers
            WHERE id = ?
            LIMIT 1
        ");

        $check_lecturer->bind_param(
            "i",
            $lecturer_id
        );

        $check_lecturer->execute();

        $lecturer_result_check =
            $check_lecturer->get_result();

        if (
            $lecturer_result_check->num_rows !== 1
        ) {

            $error =
                "Selected lecturer does not exist.";

        }

        $check_lecturer->close();

    }


    /* =================================================
       CHECK DUPLICATE SUBJECT
       Same course + semester + subject name
    ================================================= */

    if ($error === "") {

        $check = $conn->prepare("
            SELECT id
            FROM subjects
            WHERE course_id = ?
              AND semester = ?
              AND subject_name = ?
            LIMIT 1
        ");

        $check->bind_param(
            "iis",
            $course_id,
            $semester,
            $subject_name
        );

        $check->execute();

        $check_result =
            $check->get_result();

        if ($check_result->num_rows > 0) {

            $error =
                "This subject already exists for the selected course and semester.";

        }

        $check->close();

    }


    /* =================================================
       CHECK DUPLICATE SUBJECT CODE
    ================================================= */

    if (
        $error === "" &&
        $subject_code !== ""
    ) {

        $check = $conn->prepare("
            SELECT id
            FROM subjects
            WHERE subject_code = ?
            LIMIT 1
        ");

        $check->bind_param(
            "s",
            $subject_code
        );

        $check->execute();

        $check_result =
            $check->get_result();

        if ($check_result->num_rows > 0) {

            $error =
                "This subject code already exists.";

        }

        $check->close();

    }


    /* =================================================
       INSERT SUBJECT
    ================================================= */

    if ($error === "") {

        $stmt = $conn->prepare("
            INSERT INTO subjects
            (
                subject_name,
                subject_code,
                course_id,
                semester,
                lecturer_id
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                NULLIF(?, 0)
            )
        ");

        if (!$stmt) {

            $error =
                "Unable to prepare subject query.";

        } else {

            $stmt->bind_param(
                "ssiii",
                $subject_name,
                $subject_code,
                $course_id,
                $semester,
                $lecturer_id
            );


            if ($stmt->execute()) {

                header(
                    "Location: index.php?added=1"
                );

                exit();

            } else {

                $error =
                    "Unable to add subject: " .
                    $stmt->error;

            }


            $stmt->close();

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

    <title>Add Subject - Admin</title>


    <link
        rel="stylesheet"
        href="../../css/admin.css?v=10"
    >


    <style>

        .subject-form-card {

            background: #ffffff;

            padding: 30px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

            max-width: 850px;

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


        .form-actions {

            display: flex;

            gap: 10px;

            margin-top: 30px;

            padding-top: 20px;

            border-top:
                1px solid #e5e7eb;

        }


        .btn-primary {

            background: #172554;

            color: #ffffff;

            border: none;

            padding: 11px 20px;

            border-radius: 6px;

            cursor: pointer;

            font-size: 14px;

            font-weight: 600;

        }


        .btn-primary:hover {

            background: #1e3a8a;

        }


        .btn-secondary {

            background: #e5e7eb;

            color: #374151;

            padding: 11px 20px;

            border-radius: 6px;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;

        }


        .btn-secondary:hover {

            background: #d1d5db;

        }


        .alert.error {

            max-width: 850px;

            background: #fee2e2;

            color: #991b1b;

            padding: 12px 15px;

            border-radius: 6px;

            margin-top: 20px;

            margin-bottom: 15px;

        }


        .info-box {

            background: #eff6ff;

            border-left: 4px solid #172554;

            padding: 15px;

            margin-bottom: 25px;

            border-radius: 5px;

            color: #374151;

        }


        .info-box strong {

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


            <h2>Add Subject</h2>

            <p class="welcome">

                Add a subject and assign it to a course,
                semester and lecturer.

            </p>


            <?php if ($error !== ""): ?>

                <div class="alert error">

                    <?php

                    echo htmlspecialchars($error);

                    ?>

                </div>

            <?php endif; ?>


            <div class="subject-form-card">


                <!-- INFORMATION -->

                <div class="info-box">

                    <strong>
                        Subject Management
                    </strong>

                    <p style="margin: 7px 0 0;">

                        Select the course and semester to
                        which this subject belongs.

                        You can also assign a lecturer now.

                    </p>

                    <p style="margin: 7px 0 0;">

                        Lecturer assignment can also be
                        changed later from the Edit Subject page.

                    </p>

                </div>


                <form
                    method="POST"
                    action=""
                >


                    <div class="form-grid">


                        <!-- SUBJECT NAME -->

                        <div class="form-group">

                            <label>

                                Subject Name

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                name="subject_name"
                                maxlength="150"
                                required
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "subject_name"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="Example: Database Management System"
                            >

                        </div>


                        <!-- SUBJECT CODE -->

                        <div class="form-group">

                            <label>
                                Subject Code
                            </label>


                            <input
                                type="text"
                                name="subject_code"
                                maxlength="50"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "subject_code"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="Example: BCA301"
                            >


                            <div class="help-text">

                                Optional, but recommended.

                            </div>

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

                                            (
                                                $course[
                                                    "course_code"
                                                ]
                                                ? $course[
                                                    "course_code"
                                                ] . " - "
                                                : ""
                                            )

                                            .

                                            $course[
                                                "course_name"
                                            ]

                                        );

                                        ?>

                                    </option>


                                <?php endforeach; ?>


                            </select>


                            <div class="help-text">

                                Example: BCA, BBA or B.Com.

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


                        <!-- LECTURER -->

                        <div class="form-group full">

                            <label>
                                Assign Lecturer
                            </label>


                            <select
                                name="lecturer_id"
                            >

                                <option value="0">

                                    Not Assigned

                                </option>


                                <?php foreach (
                                    $lecturers as $lecturer
                                ): ?>


                                    <option
                                        value="<?php

                                            echo (int)
                                                $lecturer["id"];

                                        ?>"

                                        <?php

                                        if (
                                            (int)(
                                                $_POST[
                                                    "lecturer_id"
                                                ] ?? 0
                                            ) ===
                                            (int)$lecturer["id"]
                                        ) {
                                            echo "selected";
                                        }

                                        ?>
                                    >

                                        <?php

                                        echo htmlspecialchars(

                                            $lecturer[
                                                "full_name"
                                            ]

                                            .

                                            (
                                                !empty(
                                                    $lecturer[
                                                        "lecturer_id"
                                                    ]
                                                )

                                                ? " (" .
                                                  $lecturer[
                                                      "lecturer_id"
                                                  ] .
                                                  ")"

                                                : ""
                                            )

                                        );

                                        ?>

                                    </option>


                                <?php endforeach; ?>


                            </select>


                            <div class="help-text">

                                You can leave this as
                                "Not Assigned" and assign a
                                lecturer later.

                            </div>

                        </div>


                    </div>


                    <!-- FORM ACTIONS -->

                    <div class="form-actions">


                        <button
                            type="submit"
                            class="btn-primary"
                        >
                            Add Subject
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