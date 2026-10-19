<?php

session_start();

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "lecturer"
) {
    header("Location: ../../auth/login.php");
    exit();
}

require_once "../../config/database.php";


/* =========================================================
   GET ACTUAL LECTURER ID
========================================================= */

$user_id = (int)$_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT id
     FROM lecturers
     WHERE user_id = ?
     LIMIT 1"
);

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$lecturer = $result->fetch_assoc();

$stmt->close();

if (!$lecturer) {
    die("Lecturer profile not found.");
}

$lecturer_id = (int)$lecturer["id"];


/* =========================================================
   VARIABLES
========================================================= */

$message = "";
$error = "";

$edit_attendance_id = 0;

$month = isset($_POST["month"])
    ? (int)$_POST["month"]
    : (int)date("n");

$year = isset($_POST["year"])
    ? (int)$_POST["year"]
    : (int)date("Y");

$subject_id = isset($_POST["subject_id"])
    ? (int)$_POST["subject_id"]
    : 0;

$total_attendance = isset($_POST["total_attendance"])
    ? (int)$_POST["total_attendance"]
    : 0;


/* =========================================================
   MONTHS
========================================================= */

$months = [
    1 => "January",
    2 => "February",
    3 => "March",
    4 => "April",
    5 => "May",
    6 => "June",
    7 => "July",
    8 => "August",
    9 => "September",
    10 => "October",
    11 => "November",
    12 => "December"
];


/* =========================================================
   GET ASSIGNED SUBJECTS
========================================================= */

$subjects = [];

$stmt = $conn->prepare(
    "SELECT
        s.id,
        s.subject_name,
        s.subject_code,
        s.course_id,
        s.semester,
        c.course_name
     FROM subjects s
     LEFT JOIN courses c
        ON c.id = s.course_id
     WHERE s.lecturer_id = ?
     ORDER BY
        c.course_name,
        s.semester,
        s.subject_name"
);

$stmt->bind_param("i", $lecturer_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}

$stmt->close();


/* =========================================================
   DELETE ATTENDANCE
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_attendance"])
) {

    $delete_id = (int)$_POST["delete_attendance"];

    if ($delete_id > 0) {

        $conn->begin_transaction();

        try {

            /* Verify attendance belongs to lecturer */

            $stmt = $conn->prepare(
                "SELECT id
                 FROM attendance_monthly
                 WHERE id = ?
                 AND lecturer_id = ?
                 LIMIT 1"
            );

            $stmt->bind_param(
                "ii",
                $delete_id,
                $lecturer_id
            );

            $stmt->execute();

            $result = $stmt->get_result();
            $attendance = $result->fetch_assoc();

            $stmt->close();


            if (!$attendance) {

                throw new Exception(
                    "Attendance record not found."
                );
            }


            /* Delete student records first */

            $stmt = $conn->prepare(
                "DELETE FROM attendance_records
                 WHERE attendance_id = ?"
            );

            $stmt->bind_param(
                "i",
                $delete_id
            );

            $stmt->execute();
            $stmt->close();


            /* Delete monthly record */

            $stmt = $conn->prepare(
                "DELETE FROM attendance_monthly
                 WHERE id = ?
                 AND lecturer_id = ?"
            );

            $stmt->bind_param(
                "ii",
                $delete_id,
                $lecturer_id
            );

            $stmt->execute();
            $stmt->close();


            $conn->commit();

            $message =
                "Attendance deleted successfully.";

        } catch (Throwable $e) {

            $conn->rollback();

            $error =
                "Unable to delete attendance: "
                . $e->getMessage();
        }
    }
}


/* =========================================================
   LOAD ATTENDANCE FOR EDIT
========================================================= */

if (
    isset($_GET["edit"]) &&
    (int)$_GET["edit"] > 0
) {

    $edit_attendance_id =
        (int)$_GET["edit"];


    $stmt = $conn->prepare(
        "SELECT
            id,
            month,
            year,
            subject_id
         FROM attendance_monthly
         WHERE id = ?
         AND lecturer_id = ?
         LIMIT 1"
    );

    $stmt->bind_param(
        "ii",
        $edit_attendance_id,
        $lecturer_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $edit_record =
        $result->fetch_assoc();

    $stmt->close();


    if ($edit_record) {

        $month =
            (int)$edit_record["month"];

        $year =
            (int)$edit_record["year"];

        $subject_id =
            (int)$edit_record["subject_id"];

    } else {

        $error =
            "Attendance record not found.";

        $edit_attendance_id = 0;
    }
}


/* =========================================================
   SELECTED SUBJECT
========================================================= */

$selected_subject = null;
$course_id = 0;
$semester = 0;

foreach ($subjects as $subject) {

    if (
        (int)$subject["id"] ===
        $subject_id
    ) {

        $selected_subject = $subject;

        $course_id =
            (int)$subject["course_id"];

        $semester =
            (int)$subject["semester"];

        break;
    }
}


/* =========================================================
   GET STUDENTS
========================================================= */

$students = [];

if ($selected_subject) {

    $stmt = $conn->prepare(
        "SELECT
            id,
            registration_no,
            full_name
         FROM students
         WHERE course_id = ?
         AND semester = ?
         ORDER BY registration_no ASC"
    );

    $stmt->bind_param(
        "ii",
        $course_id,
        $semester
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }

    $stmt->close();
}


/* =========================================================
   LOAD EXISTING STUDENT ATTENDANCE
========================================================= */

$existing_attendance = [];

if ($edit_attendance_id > 0) {

    $stmt = $conn->prepare(
        "SELECT
            student_id,
            total_attendance,
            total_present,
            percentage
         FROM attendance_records
         WHERE attendance_id = ?"
    );

    $stmt->bind_param(
        "i",
        $edit_attendance_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $existing_attendance[
            (int)$row["student_id"]
        ] = $row;

        if ($total_attendance <= 0) {

            $total_attendance =
                (int)$row["total_attendance"];
        }
    }

    $stmt->close();
}


/* =========================================================
   SAVE / UPDATE ATTENDANCE
========================================================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["save_attendance"])
) {

    $month =
        (int)$_POST["month"];

    $year =
        (int)$_POST["year"];

    $subject_id =
        (int)$_POST["subject_id"];

    $total_attendance =
        (int)$_POST["total_attendance"];

    $attendance_id =
        isset($_POST["attendance_id"])
        ? (int)$_POST["attendance_id"]
        : 0;

    $present_values =
        $_POST["total_present"] ?? [];


    /* =====================================================
       VALIDATION
    ===================================================== */

    if ($month < 1 || $month > 12) {

        $error =
            "Please select a valid month.";

    } elseif ($year < 2000 || $year > 2100) {

        $error =
            "Please enter a valid year.";

    } elseif ($subject_id <= 0) {

        $error =
            "Please select a subject.";

    } elseif ($total_attendance <= 0) {

        $error =
            "Total attendance must be greater than 0.";

    } else {


        /* =================================================
           VERIFY SUBJECT
        ================================================= */

        $stmt = $conn->prepare(
            "SELECT
                id,
                course_id,
                semester
             FROM subjects
             WHERE id = ?
             AND lecturer_id = ?
             LIMIT 1"
        );

        $stmt->bind_param(
            "ii",
            $subject_id,
            $lecturer_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $verified_subject =
            $result->fetch_assoc();

        $stmt->close();


        if (!$verified_subject) {

            $error =
                "You are not assigned to this subject.";

        } else {

            $course_id =
                (int)$verified_subject["course_id"];

            $semester =
                (int)$verified_subject["semester"];


            /* =============================================
               GET STUDENTS
            ============================================= */

            $student_ids = [];

            $stmt = $conn->prepare(
                "SELECT id
                 FROM students
                 WHERE course_id = ?
                 AND semester = ?
                 ORDER BY registration_no"
            );

            $stmt->bind_param(
                "ii",
                $course_id,
                $semester
            );

            $stmt->execute();

            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {

                $student_ids[] =
                    (int)$row["id"];
            }

            $stmt->close();


            if (count($student_ids) === 0) {

                $error =
                    "No students found for this subject.";

            } else {


                /* =========================================
                   VALIDATE PRESENT VALUES
                ========================================= */

                foreach ($student_ids as $student_id) {

                    $present =
                        isset(
                            $present_values[$student_id]
                        )
                        ? (int)$present_values[$student_id]
                        : 0;


                    if ($present < 0) {

                        $error =
                            "Present attendance cannot be negative.";

                        break;
                    }


                    if (
                        $present >
                        $total_attendance
                    ) {

                        $error =
                            "Present attendance cannot be greater than total attendance.";

                        break;
                    }
                }


                /* =========================================
                   SAVE
                ========================================= */

                if ($error === "") {

                    $conn->begin_transaction();

                    try {


                        /* =================================
                           EDIT EXISTING MONTHLY RECORD
                        ================================= */

                        if ($attendance_id > 0) {

                            /* Verify ownership */

                            $stmt = $conn->prepare(
                                "SELECT id
                                 FROM attendance_monthly
                                 WHERE id = ?
                                 AND lecturer_id = ?
                                 LIMIT 1"
                            );

                            $stmt->bind_param(
                                "ii",
                                $attendance_id,
                                $lecturer_id
                            );

                            $stmt->execute();

                            $result =
                                $stmt->get_result();

                            $check =
                                $result->fetch_assoc();

                            $stmt->close();


                            if (!$check) {

                                throw new Exception(
                                    "Invalid attendance record."
                                );
                            }


                            /* Update monthly record */

                            $stmt = $conn->prepare(
                                "UPDATE attendance_monthly
                                 SET
                                    month = ?,
                                    year = ?,
                                    course_id = ?,
                                    subject_id = ?,
                                    semester = ?
                                 WHERE id = ?
                                 AND lecturer_id = ?"
                            );

                            $stmt->bind_param(
                                "iiiiiii",
                                $month,
                                $year,
                                $course_id,
                                $subject_id,
                                $semester,
                                $attendance_id,
                                $lecturer_id
                            );

                            $stmt->execute();
                            $stmt->close();


                        } else {


                            /* =================================
                               CHECK DUPLICATE MONTHLY RECORD
                            ================================= */

                            $stmt = $conn->prepare(
                                "SELECT id
                                 FROM attendance_monthly
                                 WHERE month = ?
                                 AND year = ?
                                 AND subject_id = ?
                                 AND semester = ?
                                 LIMIT 1"
                            );

                            $stmt->bind_param(
                                "iiii",
                                $month,
                                $year,
                                $subject_id,
                                $semester
                            );

                            $stmt->execute();

                            $result =
                                $stmt->get_result();

                            $existing_month =
                                $result->fetch_assoc();

                            $stmt->close();


                            if ($existing_month) {

                                $attendance_id =
                                    (int)$existing_month["id"];


                                $stmt = $conn->prepare(
                                    "UPDATE attendance_monthly
                                     SET
                                        course_id = ?,
                                        lecturer_id = ?
                                     WHERE id = ?"
                                );

                                $stmt->bind_param(
                                    "iii",
                                    $course_id,
                                    $lecturer_id,
                                    $attendance_id
                                );

                                $stmt->execute();
                                $stmt->close();

                            } else {


                                /* =================================
                                   CREATE MONTHLY RECORD
                                ================================= */

                                $stmt = $conn->prepare(
                                    "INSERT INTO attendance_monthly
                                    (
                                        month,
                                        year,
                                        course_id,
                                        subject_id,
                                        semester,
                                        lecturer_id
                                    )
                                    VALUES (?, ?, ?, ?, ?, ?)"
                                );

                                $stmt->bind_param(
                                    "iiiiii",
                                    $month,
                                    $year,
                                    $course_id,
                                    $subject_id,
                                    $semester,
                                    $lecturer_id
                                );

                                $stmt->execute();

                                $attendance_id =
                                    $stmt->insert_id;

                                $stmt->close();
                            }
                        }


                        /* =================================
                           SAVE EACH STUDENT
                        ================================= */

                        foreach ($student_ids as $student_id) {

                            $present =
                                isset(
                                    $present_values[$student_id]
                                )
                                ? (int)$present_values[$student_id]
                                : 0;


                            /* Check existing student record */

                            $stmt = $conn->prepare(
                                "SELECT id
                                 FROM attendance_records
                                 WHERE attendance_id = ?
                                 AND student_id = ?
                                 LIMIT 1"
                            );

                            $stmt->bind_param(
                                "ii",
                                $attendance_id,
                                $student_id
                            );

                            $stmt->execute();

                            $result =
                                $stmt->get_result();

                            $existing_record =
                                $result->fetch_assoc();

                            $stmt->close();


                            if ($existing_record) {

                                $record_id =
                                    (int)$existing_record["id"];


                                /* UPDATE */

                                $stmt = $conn->prepare(
                                    "UPDATE attendance_records
                                     SET
                                        total_attendance = ?,
                                        total_present = ?
                                     WHERE id = ?"
                                );

                                $stmt->bind_param(
                                    "iii",
                                    $total_attendance,
                                    $present,
                                    $record_id
                                );

                                $stmt->execute();
                                $stmt->close();

                            } else {


                                /* INSERT */

                                $stmt = $conn->prepare(
                                    "INSERT INTO attendance_records
                                    (
                                        attendance_id,
                                        student_id,
                                        total_attendance,
                                        total_present
                                    )
                                    VALUES (?, ?, ?, ?)"
                                );

                                $stmt->bind_param(
                                    "iiii",
                                    $attendance_id,
                                    $student_id,
                                    $total_attendance,
                                    $present
                                );

                                $stmt->execute();
                                $stmt->close();
                            }
                        }


                        $conn->commit();

                        /*
                         * Redirect after saving.
                         * This prevents duplicate submission
                         * when refreshing the page.
                         */

                        header(
                            "Location: index.php?saved=1"
                        );

                        exit();


                    } catch (Throwable $e) {

                        $conn->rollback();

                        $error =
                            "Unable to save attendance: "
                            . $e->getMessage();
                    }
                }
            }
        }
    }
}


/* =========================================================
   SUCCESS MESSAGE
========================================================= */

if (isset($_GET["saved"])) {

    $message =
        "Attendance saved successfully.";
}


/* =========================================================
   GET SAVED ATTENDANCE LIST
========================================================= */

$saved_attendance = [];

$stmt = $conn->prepare(
    "SELECT
        am.id,
        am.month,
        am.year,
        am.semester,
        s.subject_name,
        s.subject_code,
        c.course_name,

        COUNT(ar.id) AS student_count

     FROM attendance_monthly am

     INNER JOIN subjects s
        ON s.id = am.subject_id

     LEFT JOIN courses c
        ON c.id = am.course_id

     LEFT JOIN attendance_records ar
        ON ar.attendance_id = am.id

     WHERE am.lecturer_id = ?

     GROUP BY
        am.id,
        am.month,
        am.year,
        am.semester,
        s.subject_name,
        s.subject_code,
        c.course_name

     ORDER BY
        am.year DESC,
        am.month DESC,
        am.id DESC"
);

$stmt->bind_param(
    "i",
    $lecturer_id
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $saved_attendance[] =
        $row;
}

$stmt->close();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Attendance | Lecturer</title>

    <link
        rel="stylesheet"
        href="../../css/admin.css?v=4"
    >

    <style>

        .attendance-page {
            padding: 30px;
            background: #f8fafc;
            min-height: calc(100vh - 100px);
        }

        .attendance-title h2 {
            color: #172554;
            margin-bottom: 5px;
        }

        .attendance-title p {
            color: #64748b;
        }

        .attendance-card {
            background: #ffffff;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }

        .message-success {
            background: #dcfce7;
            color: #166534;
            padding: 13px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .message-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 13px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            align-items: end;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #334155;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
        }

        .load-button,
        .save-button,
        .update-button {
            padding: 11px 20px;
            border: none;
            border-radius: 6px;
            color: #ffffff;
            cursor: pointer;
            font-weight: 600;
            margin-top: 20px;
        }

        .load-button {
            background: #172554;
        }

        .save-button {
            background: #16a34a;
        }

        .update-button {
            background: #2563eb;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        .attendance-table th {
            background: #172554;
            color: white;
            padding: 12px;
            text-align: left;
        }

        .attendance-table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }

        .attendance-table input {
            width: 90px;
            padding: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
        }

        .shortage-row {
            background: #fee2e2 !important;
        }

        .percentage-cell {
            font-weight: 700;
        }

        .shortage-section {
            margin-top: 20px;
            padding: 18px;
            background: #fff7ed;
            border-left: 5px solid #f97316;
            border-radius: 6px;
        }

        .shortage-section h3 {
            margin-top: 0;
            color: #c2410c;
        }

        .saved-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }

        .saved-table th {
            background: #172554;
            color: white;
            padding: 12px;
            text-align: left;
        }

        .saved-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .saved-table tr:hover {
            background: #f8fafc;
        }

        .edit-button {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 7px 12px;
            border-radius: 5px;
            text-decoration: none;
            margin-right: 5px;
        }

        .delete-button {
            background: #dc2626;
            color: white;
            padding: 7px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .no-data {
            text-align: center;
            color: #64748b;
            padding: 30px;
        }

        .editing-label {
            display: inline-block;
            background: #dbeafe;
            color: #1d4ed8;
            padding: 7px 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        @media (max-width: 900px) {

            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 600px) {

            .attendance-page {
                padding: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>


<?php include "../../includes/lecturer_sidebar.php"; ?>


<main class="main-content">

    <div class="attendance-page">


        <div class="attendance-title">

            <h2>📅 Monthly Attendance</h2>

            <p>
                Upload and manage attendance for your assigned subjects.
            </p>

        </div>


        <?php if ($message !== ""): ?>

            <div class="message-success">

                <?php
                echo htmlspecialchars($message);
                ?>

            </div>

        <?php endif; ?>


        <?php if ($error !== ""): ?>

            <div class="message-error">

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             ATTENDANCE FORM
        ================================================== -->

        <div class="attendance-card">

            <?php if ($edit_attendance_id > 0): ?>

                <div class="editing-label">
                    ✏️ Editing Attendance
                </div>

            <?php endif; ?>


            <form method="POST">

                <?php if ($edit_attendance_id > 0): ?>

                    <input
                        type="hidden"
                        name="attendance_id"
                        value="<?php echo $edit_attendance_id; ?>"
                    >

                <?php endif; ?>


                <div class="form-grid">


                    <div class="form-group">

                        <label>Month</label>

                        <select name="month" required>

                            <?php foreach ($months as $number => $name): ?>

                                <option
                                    value="<?php echo $number; ?>"
                                    <?php
                                    echo
                                    $month === $number
                                    ? "selected"
                                    : "";
                                    ?>
                                >

                                    <?php echo $name; ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>Year</label>

                        <input
                            type="number"
                            name="year"
                            value="<?php echo $year; ?>"
                            min="2000"
                            max="2100"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>Subject</label>

                        <select
                            name="subject_id"
                            required
                        >

                            <option value="">
                                Select Subject
                            </option>

                            <?php foreach ($subjects as $subject): ?>

                                <option
                                    value="<?php echo (int)$subject["id"]; ?>"
                                    <?php
                                    echo
                                    $subject_id ===
                                    (int)$subject["id"]
                                    ? "selected"
                                    : "";
                                    ?>
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $subject["subject_name"]
                                    );
                                    ?>

                                    -
                                    <?php
                                    echo htmlspecialchars(
                                        $subject["course_name"]
                                    );
                                    ?>

                                    -
                                    Sem
                                    <?php
                                    echo (int)$subject["semester"];
                                    ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>Total Attendance</label>

                        <input
                            type="number"
                            id="totalAttendance"
                            name="total_attendance"
                            value="<?php echo $total_attendance; ?>"
                            min="1"
                            required
                        >

                    </div>


                </div>


                <button
                    type="submit"
                    name="load_students"
                    class="load-button"
                >
                    Load Students
                </button>

            </form>

        </div>


        <!-- =================================================
             STUDENT ATTENDANCE
        ================================================== -->

        <?php if (
            $subject_id > 0 &&
            count($students) > 0
        ): ?>

            <div class="attendance-card">

                <form method="POST">


                    <input
                        type="hidden"
                        name="month"
                        value="<?php echo $month; ?>"
                    >

                    <input
                        type="hidden"
                        name="year"
                        value="<?php echo $year; ?>"
                    >

                    <input
                        type="hidden"
                        name="subject_id"
                        value="<?php echo $subject_id; ?>"
                    >


                    <?php if ($edit_attendance_id > 0): ?>

                        <input
                            type="hidden"
                            name="attendance_id"
                            value="<?php echo $edit_attendance_id; ?>"
                        >

                    <?php endif; ?>


                    <input
                        type="hidden"
                        id="saveTotalAttendance"
                        name="total_attendance"
                        value="<?php echo $total_attendance; ?>"
                    >


                    <div class="table-wrapper">

                        <table class="attendance-table">

                            <thead>

                                <tr>

                                    <th>Sl. No.</th>

                                    <th>Registration No.</th>

                                    <th>Student Name</th>

                                    <th>Total Attendance</th>

                                    <th>Total Present</th>

                                    <th>Percentage</th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach (
                                $students as $index => $student
                            ): ?>


                                <?php

                                $student_id =
                                    (int)$student["id"];

                                $saved =
                                    $existing_attendance[
                                        $student_id
                                    ] ?? null;

                                $saved_present =
                                    $saved
                                    ? (int)$saved[
                                        "total_present"
                                    ]
                                    : 0;

                                $saved_percentage =
                                    $saved
                                    ? (float)$saved[
                                        "percentage"
                                    ]
                                    : 0;

                                ?>


                                <tr
                                    data-student-row
                                    class="<?php
                                    echo
                                    (
                                        $saved &&
                                        $saved_percentage < 75
                                    )
                                    ? "shortage-row"
                                    : "";
                                    ?>"
                                >

                                    <td>
                                        <?php
                                        echo $index + 1;
                                        ?>
                                    </td>

                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $student[
                                                "registration_no"
                                            ]
                                        );
                                        ?>

                                    </td>

                                    <td>

                                        <?php
                                        echo htmlspecialchars(
                                            $student[
                                                "full_name"
                                            ]
                                        );
                                        ?>

                                    </td>

                                    <td>

                                        <input
                                            type="number"
                                            class="total-attendance-input"
                                            name="total_attendance_row[<?php echo $student_id; ?>]"
                                            value="<?php echo $total_attendance; ?>"
                                            min="1"
                                            required
                                        >

                                    </td>

                                    <td>

                                        <input
                                            type="number"
                                            class="present-input"
                                            name="total_present[<?php echo $student_id; ?>]"
                                            value="<?php echo $saved_present; ?>"
                                            min="0"
                                            required
                                        >

                                    </td>

                                    <td class="percentage-cell">

                                        <span class="percentage-value">

                                            <?php
                                            echo number_format(
                                                $saved_percentage,
                                                2
                                            );
                                            ?>%

                                        </span>

                                    </td>

                                </tr>


                            <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                    <!-- SHORTAGE -->

                    <div class="shortage-section">

                        <h3>
                            ⚠ Attendance Shortage
                        </h3>

                        <ul id="shortageList">

                            <li>
                                No students have attendance shortage.
                            </li>

                        </ul>

                    </div>


                    <button
                        type="submit"
                        name="save_attendance"
                        class="<?php
                        echo $edit_attendance_id > 0
                            ? "update-button"
                            : "save-button";
                        ?>"
                    >

                        <?php
                        echo $edit_attendance_id > 0
                            ? "💾 Update Attendance"
                            : "💾 Save Attendance";
                        ?>

                    </button>


                </form>

            </div>


        <?php elseif ($subject_id > 0): ?>


            <div class="attendance-card">

                <p class="no-data">
                    No students found for the selected
                    course and semester.
                </p>

            </div>


        <?php endif; ?>


        <!-- =================================================
             SAVED ATTENDANCE TABLE
        ================================================== -->

        <div class="attendance-card">

            <h2>
                📋 Saved Attendance
            </h2>

            <p style="color:#64748b;">
                Attendance records already uploaded by you.
            </p>


            <?php if (
                count($saved_attendance) > 0
            ): ?>


                <div class="table-wrapper">

                    <table class="saved-table">

                        <thead>

                            <tr>

                                <th>Sl. No.</th>

                                <th>Month</th>

                                <th>Year</th>

                                <th>Course</th>

                                <th>Semester</th>

                                <th>Subject</th>

                                <th>Students</th>

                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $saved_attendance
                            as $index => $attendance
                        ): ?>


                            <tr>

                                <td>
                                    <?php
                                    echo $index + 1;
                                    ?>
                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $months[
                                            (int)$attendance[
                                                "month"
                                            ]
                                        ] ?? ""
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo (int)$attendance[
                                        "year"
                                    ];
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $attendance[
                                            "course_name"
                                        ] ?? "-"
                                    );
                                    ?>

                                </td>

                                <td>

                                    <?php
                                    echo (int)$attendance[
                                        "semester"
                                    ];
                                    ?>

                                </td>

                                <td>

                                    <strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $attendance[
                                                "subject_name"
                                            ]
                                        );
                                        ?>

                                    </strong>

                                    <?php
                                    if (
                                        !empty(
                                            $attendance[
                                                "subject_code"
                                            ]
                                        )
                                    ):
                                    ?>

                                        <br>

                                        <small>

                                            <?php
                                            echo htmlspecialchars(
                                                $attendance[
                                                    "subject_code"
                                                ]
                                            );
                                            ?>

                                        </small>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <?php
                                    echo (int)$attendance[
                                        "student_count"
                                    ];
                                    ?>

                                </td>

                                <td>

                                    <a
                                        href="index.php?edit=<?php echo (int)$attendance["id"]; ?>"
                                        class="edit-button"
                                    >
                                        ✏️ Edit
                                    </a>


                                    <form
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="return confirm('Delete this attendance record? This will delete attendance for all students for this month and subject.');"
                                    >

                                        <input
                                            type="hidden"
                                            name="delete_attendance"
                                            value="<?php echo (int)$attendance["id"]; ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="delete-button"
                                        >
                                            🗑️ Delete
                                        </button>

                                    </form>

                                </td>

                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php else: ?>


                <div class="no-data">

                    No attendance has been uploaded yet.

                </div>


            <?php endif; ?>


        </div>


    </div>

</main>




<script>

/* =========================================================
   ATTENDANCE JAVASCRIPT
========================================================= */

const totalInputs =
    document.querySelectorAll(
        ".total-attendance-input"
    );

const presentInputs =
    document.querySelectorAll(
        ".present-input"
    );

const totalAttendance =
    document.getElementById(
        "totalAttendance"
    );

const saveTotal =
    document.getElementById(
        "saveTotalAttendance"
    );

const shortageList =
    document.getElementById(
        "shortageList"
    );


/* =========================================================
   FILL TOTAL ATTENDANCE
========================================================= */

function fillAllTotalAttendance(value) {

    totalInputs.forEach(function(input) {

        input.value = value;

    });


    if (saveTotal) {

        saveTotal.value = value;

    }

}


/* =========================================================
   CALCULATE
========================================================= */

function calculateAllPercentages() {

    const rows =
        document.querySelectorAll(
            "[data-student-row]"
        );

    const shortageStudents = [];


    rows.forEach(function(row) {

        const totalInput =
            row.querySelector(
                ".total-attendance-input"
            );

        const presentInput =
            row.querySelector(
                ".present-input"
            );

        const percentageCell =
            row.querySelector(
                ".percentage-value"
            );


        const total =
            parseFloat(
                totalInput.value
            ) || 0;

        let present =
            parseFloat(
                presentInput.value
            ) || 0;


        if (
            present > total &&
            total > 0
        ) {

            present = total;

            presentInput.value =
                total;
        }


        let percentage = 0;


        if (total > 0) {

            percentage =
                (present / total) * 100;

        }


        percentageCell.textContent =
            percentage.toFixed(2) + "%";


        if (percentage < 75) {

            row.classList.add(
                "shortage-row"
            );


            shortageStudents.push({

                name:
                    row.cells[2]
                        .textContent
                        .trim(),

                percentage:
                    percentage

            });

        } else {

            row.classList.remove(
                "shortage-row"
            );

        }

    });


    if (!shortageList) {
        return;
    }


    shortageList.innerHTML = "";


    if (
        shortageStudents.length === 0
    ) {

        const li =
            document.createElement("li");

        li.textContent =
            "No students have attendance shortage.";

        shortageList.appendChild(li);

    } else {

        shortageStudents.forEach(
            function(student) {

                const li =
                    document.createElement("li");

                li.textContent =
                    student.name +
                    " - " +
                    student.percentage
                        .toFixed(2) +
                    "%";

                shortageList.appendChild(li);

            }
        );

    }

}


/* =========================================================
   MAIN TOTAL
========================================================= */

if (totalAttendance) {

    totalAttendance.addEventListener(
        "input",
        function() {

            fillAllTotalAttendance(
                this.value
            );

            calculateAllPercentages();

        }
    );

}


/* =========================================================
   ROW TOTAL
========================================================= */

totalInputs.forEach(function(input) {

    input.addEventListener(
        "input",
        function() {

            fillAllTotalAttendance(
                this.value
            );

            if (totalAttendance) {

                totalAttendance.value =
                    this.value;

            }

            calculateAllPercentages();

        }
    );

});


/* =========================================================
   PRESENT
========================================================= */

presentInputs.forEach(function(input) {

    input.addEventListener(
        "input",
        calculateAllPercentages
    );

});


/* =========================================================
   INITIAL
========================================================= */

calculateAllPercentages();

</script>


</body>

</html>