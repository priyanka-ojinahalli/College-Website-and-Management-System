<?php

session_start();

/* =====================================================
   STUDENT LOGIN CHECK
===================================================== */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "student"
) {
    header("Location: ../auth/login.php");
    exit();
}


/* =====================================================
   DATABASE
===================================================== */

require_once "../config/database.php";


/* =====================================================
   GET LOGGED-IN STUDENT
===================================================== */

$user_id = (int) $_SESSION["user_id"];

$stmt = $conn->prepare("
    SELECT
        id,
        full_name,
        registration_no,
        course_id,
        semester
    FROM students
    WHERE user_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$student_result = $stmt->get_result();

if ($student_result->num_rows !== 1) {
    $stmt->close();
    die("Student record not found.");
}

$student = $student_result->fetch_assoc();

$stmt->close();


$student_id = (int) $student["id"];
$course_id  = (int) $student["course_id"];
$semester   = (int) $student["semester"];


/* =====================================================
   GET COURSE NAME
===================================================== */

$course_name = "";

$stmt = $conn->prepare("
    SELECT course_name
    FROM courses
    WHERE id = ?
    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param("i", $course_id);
    $stmt->execute();

    $course_result = $stmt->get_result();

    if ($course_result->num_rows === 1) {

        $course_row = $course_result->fetch_assoc();

        $course_name = $course_row["course_name"];
    }

    $stmt->close();
}


/* =====================================================
   GET SUBJECTS FOR COURSE + SEMESTER
===================================================== */

$subjects = [];

$stmt = $conn->prepare("
    SELECT
        id,
        subject_name,
        subject_code
    FROM subjects
    WHERE course_id = ?
      AND semester = ?
    ORDER BY id ASC
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $course_id,
    $semester
);

$stmt->execute();

$subject_result = $stmt->get_result();

while ($row = $subject_result->fetch_assoc()) {

    $subjects[(int) $row["id"]] = [
        "id" => (int) $row["id"],
        "name" => $row["subject_name"],
        "code" => $row["subject_code"]
    ];
}

$stmt->close();


/* =====================================================
   GET STUDENTS OF SAME COURSE + SEMESTER
===================================================== */

$students = [];

$stmt = $conn->prepare("
    SELECT
        id,
        full_name,
        registration_no
    FROM students
    WHERE course_id = ?
      AND semester = ?
      AND student_status = 'Active'
    ORDER BY registration_no ASC
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $course_id,
    $semester
);

$stmt->execute();

$student_result = $stmt->get_result();

while ($row = $student_result->fetch_assoc()) {

    $students[(int) $row["id"]] = [
        "id" => (int) $row["id"],
        "full_name" => $row["full_name"],
        "registration_no" => $row["registration_no"]
    ];
}

$stmt->close();


/* =====================================================
   GET EXAMINATIONS HAVING UPLOADED MARKS
===================================================== */

$examinations = [];

$sql = "
    SELECT
        e.id AS examination_id,
        e.exam_name,
        MAX(m.updated_at) AS published_at

    FROM marks m

    INNER JOIN subjects s
        ON s.id = m.subject_id

    INNER JOIN examinations e
        ON e.id = m.examination_id

    WHERE s.course_id = ?
      AND s.semester = ?

    GROUP BY
        e.id,
        e.exam_name

    ORDER BY
        e.id ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $course_id,
    $semester
);

$stmt->execute();

$exam_result = $stmt->get_result();

while ($row = $exam_result->fetch_assoc()) {

    $examinations[(int) $row["examination_id"]] = [
        "id" => (int) $row["examination_id"],
        "name" => $row["exam_name"],
        "published_at" => $row["published_at"]
    ];
}

$stmt->close();


/* =====================================================
   GET ALL MARKS
===================================================== */

$marks = [];

$sql = "
    SELECT
        m.student_id,
        m.subject_id,
        m.examination_id,
        m.marks

    FROM marks m

    INNER JOIN subjects s
        ON s.id = m.subject_id

    WHERE s.course_id = ?
      AND s.semester = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $course_id,
    $semester
);

$stmt->execute();

$marks_result = $stmt->get_result();

while ($row = $marks_result->fetch_assoc()) {

    $sid = (int) $row["student_id"];
    $subid = (int) $row["subject_id"];
    $eid = (int) $row["examination_id"];

    if (!isset($marks[$eid])) {
        $marks[$eid] = [];
    }

    if (!isset($marks[$eid][$sid])) {
        $marks[$eid][$sid] = [];
    }

    $marks[$eid][$sid][$subid] = $row["marks"];
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

    <title>My Examination Marks</title>


    <style>

        /* =================================================
           GLOBAL
        ================================================= */

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                "Segoe UI",
                Arial,
                sans-serif;

            background: #f4f7fb;

            color: #172033;
        }


        /* =================================================
           MAIN PAGE
        ================================================= */

        .marks-page {

            max-width: 1360px;

            margin: 0 auto;

            padding: 38px 40px 60px;
        }


        /* =================================================
           BREADCRUMB
        ================================================= */

        .breadcrumb {

            display: flex;

            align-items: center;

            gap: 8px;

            margin-bottom: 22px;

            font-size: 14px;
        }


        .breadcrumb strong {

            color: #172554;
        }


        .breadcrumb span {

            color: #64748b;
        }


        /* =================================================
           MY EXAMINATION MARKS BANNER
           LIGHT BLUE
        ================================================= */

        .page-intro {

            background: #dbeafe;

            color: #172554;

            padding: 38px 38px;

            border-radius: 17px;

            margin-bottom: 30px;

            border: 1px solid #bfdbfe;

            box-shadow:
                0 8px 20px
                rgba(30, 64, 175, 0.08);
        }


        .page-intro h2 {

            margin: 0 0 8px;

            font-size: 30px;

            font-weight: 700;

            color: #172554;
        }


        .page-intro p {

            margin: 0;

            font-size: 15px;

            line-height: 1.6;

            color: #334155;
        }


        /* =================================================
           STUDENT INFORMATION
        ================================================= */

        .student-info {

            background: #ffffff;

            border: 1px solid #e2e8f0;

            border-radius: 15px;

            padding: 25px 28px;

            margin-bottom: 32px;

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 30px;

            box-shadow:
                0 5px 18px
                rgba(15, 23, 42, 0.05);
        }


        .student-info-item {

            display: flex;

            flex-direction: column;

            gap: 5px;
        }


        .student-info-label {

            font-size: 12px;

            color: #64748b;

            text-transform: uppercase;

            font-weight: 600;

            letter-spacing: 0.3px;
        }


        .student-info-value {

            font-size: 16px;

            color: #172033;

            font-weight: 700;
        }


        .student-info-item:last-child {

            text-align: right;
        }


        /* =================================================
           EXAMINATION CARD
        ================================================= */

        .exam-card {

            background: #ffffff;

            border: 1px solid #dfe5ee;

            border-radius: 15px;

            margin-bottom: 30px;

            overflow: hidden;

            box-shadow:
                0 7px 22px
                rgba(15, 23, 42, 0.06);
        }


        /* =================================================
           EXAMINATION TITLE HEADER
        ================================================= */

        .exam-header {

            background: #f8fafc;

            border-bottom: 1px solid #e2e8f0;

            padding: 19px 23px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;
        }


        .exam-title {

            font-size: 18px;

            font-weight: 700;

            color: #172554;
        }


        .exam-course {

            color: #475569;

            font-weight: 600;
        }


        .exam-date {

            font-size: 12px;

            color: #64748b;

            white-space: nowrap;
        }


        /* =================================================
           TABLE WRAPPER
        ================================================= */

        .marks-table-wrapper {

            width: 100%;

            overflow-x: auto;
        }


        /* =================================================
           MARKS TABLE
        ================================================= */

        .marks-table {

            width: 100%;

            min-width: 850px;

            border-collapse: collapse;
        }


        /* =================================================
           TABLE HEADER - LIGHT BLUE
        ================================================= */

        .marks-table th {

            background: #bfdbfe;

            color: #172554;

            padding: 13px 14px;

            border-right: 1px solid #93c5fd;

            font-size: 12px;

            font-weight: 700;

            text-align: center;

            white-space: nowrap;
        }


        .marks-table th:first-child,
        .marks-table th:nth-child(2) {

            text-align: left;
        }


        /* =================================================
           TABLE BODY
        ================================================= */

        .marks-table td {

            padding: 13px 14px;

            border-bottom: 1px solid #edf1f5;

            border-right: 1px solid #edf1f5;

            font-size: 13px;

            text-align: center;

            white-space: nowrap;
        }


        .marks-table td:first-child,
        .marks-table td:nth-child(2) {

            text-align: left;
        }


        .marks-table tbody tr:last-child td {

            border-bottom: none;
        }


        .marks-table tbody tr:hover {

            background: #f8fafc;
        }


        /* =================================================
           REGISTRATION NUMBER
        ================================================= */

        .registration-number {

            font-weight: 600;

            color: #475569;
        }


        /* =================================================
           STUDENT NAME
        ================================================= */

        .student-name {

            font-weight: 600;

            color: #172033;
        }


        /* =================================================
           CURRENT STUDENT
        ================================================= */

        .current-student {

            background: #eff6ff !important;
        }


        .current-student .student-name {

            color: #172554;

            font-weight: 700;
        }


        /* =================================================
           SUBJECT NAME
        ================================================= */

        .subject-name {

            display: block;

            font-weight: 700;
        }


        /* =================================================
           SUBJECT CODE
        ================================================= */

        .subject-code {

            display: block;

            margin-top: 3px;

            font-size: 10px;

            color: #475569;

            font-weight: 600;
        }


        /* =================================================
           MARK VALUE
        ================================================= */

        .mark-value {

            font-weight: 700;

            color: #172554;
        }


        .no-mark {

            color: #94a3b8;
        }


        /* =================================================
           NO MARKS
        ================================================= */

        .no-marks {

            background: #ffffff;

            border: 1px solid #e2e8f0;

            border-radius: 15px;

            padding: 60px 25px;

            text-align: center;

            box-shadow:
                0 5px 18px
                rgba(15, 23, 42, 0.05);
        }


        .no-marks-icon {

            font-size: 40px;

            margin-bottom: 12px;
        }


        .no-marks h3 {

            margin: 0 0 8px;

            color: #172554;

            font-size: 20px;
        }


        .no-marks p {

            margin: 0;

            color: #64748b;

            font-size: 14px;
        }


        /* =================================================
           FOOTER NOTE
        ================================================= */

        .marks-note {

            margin-top: 25px;

            text-align: center;

            color: #94a3b8;

            font-size: 12px;
        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 750px) {

            .marks-page {

                padding:
                    25px 15px 45px;
            }


            .page-intro {

                padding:
                    28px 23px;
            }


            .page-intro h2 {

                font-size: 24px;
            }


            .student-info {

                grid-template-columns: 1fr;

                gap: 18px;
            }


            .student-info-item:last-child {

                text-align: left;
            }


            .exam-header {

                align-items: flex-start;

                flex-direction: column;
            }

        }

    </style>

</head>


<body>


<?php

/* =====================================================
   STUDENT HEADER
===================================================== */

include "../includes/student_header.php";

?>


<main class="marks-page">


    <!-- =================================================
         BREADCRUMB
    ================================================= -->

    <div class="breadcrumb">

        <strong>
            Student
        </strong>

        <span>
            /
        </span>

        <span>
            My Marks
        </span>

    </div>


    <!-- =================================================
         PAGE INTRO
    ================================================= -->

    <section class="page-intro">

        <h2>
            My Examination Marks
        </h2>

        <p>
            View your examination performance
            for all subjects. Marks uploaded by
            your lecturers will appear automatically.
        </p>

    </section>


    <!-- =================================================
         STUDENT INFORMATION
    ================================================= -->

    <section class="student-info">


        <div class="student-info-item">

            <span class="student-info-label">
                Student Name
            </span>

            <span class="student-info-value">

                <?php

                echo htmlspecialchars(
                    $student["full_name"]
                );

                ?>

            </span>

        </div>


        <div class="student-info-item">

            <span class="student-info-label">
                Registration Number
            </span>

            <span class="student-info-value">

                <?php

                echo htmlspecialchars(
                    $student["registration_no"]
                );

                ?>

            </span>

        </div>


    </section>


    <!-- =================================================
         EXAMINATION TABLES
    ================================================= -->

    <?php if (empty($examinations)): ?>


        <div class="no-marks">

            <div class="no-marks-icon">
                📊
            </div>


            <h3>
                No Examination Marks Available
            </h3>


            <p>
                Examination marks uploaded by lecturers
                will appear here automatically.
            </p>

        </div>


    <?php else: ?>


        <?php foreach ($examinations as $exam): ?>


            <section class="exam-card">


                <!-- =================================================
                     EXAMINATION HEADER
                ================================================= -->

                <div class="exam-header">


                    <div>

                        <span class="exam-title">

                            <?php

                            echo htmlspecialchars(
                                $exam["name"]
                            );

                            ?>

                        </span>


                        <span class="exam-course">

                            —
                            <?php

                            echo htmlspecialchars(
                                $course_name
                            );

                            ?>

                            — Semester

                            <?php

                            echo htmlspecialchars(
                                $semester
                            );

                            ?>

                        </span>

                    </div>


                    <div class="exam-date">

                        Date Published:

                        <?php

                        if (
                            !empty(
                                $exam["published_at"]
                            )
                        ) {

                            echo htmlspecialchars(
                                date(
                                    "d M Y",
                                    strtotime(
                                        $exam["published_at"]
                                    )
                                )
                            );

                        } else {

                            echo "-";

                        }

                        ?>

                    </div>


                </div>


                <!-- =================================================
                     MARKS TABLE
                ================================================= -->

                <div class="marks-table-wrapper">


                    <table class="marks-table">


                        <thead>

                            <tr>


                                <th>
                                    Reg. No
                                </th>


                                <th>
                                    Student Name
                                </th>


                                <?php foreach ($subjects as $subject): ?>


                                    <th>

                                        <span class="subject-name">

                                            <?php

                                            echo htmlspecialchars(
                                                $subject["name"]
                                            );

                                            ?>

                                        </span>


                                        <?php

                                        if (
                                            !empty(
                                                $subject["code"]
                                            )
                                        ):

                                        ?>

                                            <span class="subject-code">

                                                <?php

                                                echo htmlspecialchars(
                                                    $subject["code"]
                                                );

                                                ?>

                                            </span>

                                        <?php endif; ?>


                                    </th>


                                <?php endforeach; ?>


                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach (
                                $students as $row_student
                            ): ?>


                                <tr
                                    class="<?php

                                    if (
                                        (int)
                                        $row_student["id"]
                                        === $student_id
                                    ) {

                                        echo "current-student";

                                    }

                                    ?>"
                                >


                                    <!-- REGISTRATION NUMBER -->

                                    <td>

                                        <span
                                            class="registration-number"
                                        >

                                            <?php

                                            echo htmlspecialchars(
                                                $row_student[
                                                    "registration_no"
                                                ]
                                            );

                                            ?>

                                        </span>

                                    </td>


                                    <!-- STUDENT NAME -->

                                    <td>

                                        <span
                                            class="student-name"
                                        >

                                            <?php

                                            echo htmlspecialchars(
                                                $row_student[
                                                    "full_name"
                                                ]
                                            );

                                            ?>

                                        </span>

                                    </td>


                                    <!-- SUBJECT MARKS -->

                                    <?php foreach (
                                        $subjects as $subject
                                    ): ?>


                                        <td>


                                            <?php

                                            $sid =
                                                (int)
                                                $row_student["id"];

                                            $subid =
                                                (int)
                                                $subject["id"];

                                            $eid =
                                                (int)
                                                $exam["id"];


                                            if (
                                                isset(
                                                    $marks[$eid]
                                                ) &&
                                                isset(
                                                    $marks[$eid][$sid]
                                                ) &&
                                                array_key_exists(
                                                    $subid,
                                                    $marks[$eid][$sid]
                                                )
                                            ):

                                            ?>


                                                <span
                                                    class="mark-value"
                                                >

                                                    <?php

                                                    echo htmlspecialchars(
                                                        $marks[
                                                            $eid
                                                        ][
                                                            $sid
                                                        ][
                                                            $subid
                                                        ]
                                                    );

                                                    ?>

                                                </span>


                                            <?php else: ?>


                                                <span
                                                    class="no-mark"
                                                >
                                                    —
                                                </span>


                                            <?php endif; ?>


                                        </td>


                                    <?php endforeach; ?>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            </section>


        <?php endforeach; ?>


    <?php endif; ?>


    <!-- =================================================
         NOTE
    ================================================= -->

    <div class="marks-note">

        Marks are displayed automatically after
        being uploaded by the respective lecturer.

    </div>


</main>


</body>

</html>