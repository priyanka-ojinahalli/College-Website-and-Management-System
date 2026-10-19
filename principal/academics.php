<?php

session_start();

require_once "../config/database.php";

/* =========================
   PRINCIPAL ACCESS
========================= */

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "principal") {
    header("Location: ../auth/login.php");
    exit;
}


/* =========================
   FILTERS
========================= */

$selectedCourse   = isset($_GET["course"]) ? (int)$_GET["course"] : 0;
$selectedSemester = isset($_GET["semester"]) ? (int)$_GET["semester"] : 0;


/* =========================
   COURSES
========================= */

$courses = [];

$result = $conn->query("
    SELECT id, course_name, course_code
    FROM courses
    ORDER BY course_name
");

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }

}


/* =========================
   DATA FOR MARKS
========================= */

$students = [];
$exams = [];
$marksData = [];

if ($selectedCourse > 0 && $selectedSemester >= 1 && $selectedSemester <= 6) {


    /* =========================
       STUDENTS
    ========================= */

    $stmt = $conn->prepare("
        SELECT
            id,
            registration_no,
            full_name
        FROM students
        WHERE course_id = ?
        AND semester = ?
        ORDER BY full_name ASC
    ");

    $stmt->bind_param(
        "ii",
        $selectedCourse,
        $selectedSemester
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }

    $stmt->close();


    /* =========================
       MARKS
       Get all uploaded marks
       for selected course/semester
    ========================= */

    $stmt = $conn->prepare("
        SELECT
            m.student_id,
            m.subject_id,
            m.examination_id,
            m.marks,

            s.subject_name,
            s.subject_code,

            e.exam_name

        FROM marks m

        INNER JOIN subjects s
            ON m.subject_id = s.id

        INNER JOIN examinations e
            ON m.examination_id = e.id

        INNER JOIN students st
            ON m.student_id = st.id

        WHERE s.course_id = ?
        AND s.semester = ?
        AND st.course_id = ?
        AND st.semester = ?

        ORDER BY e.id ASC, s.subject_name ASC
    ");

    $stmt->bind_param(
        "iiii",
        $selectedCourse,
        $selectedSemester,
        $selectedCourse,
        $selectedSemester
    );

    $stmt->execute();

    $result = $stmt->get_result();


    while ($row = $result->fetch_assoc()) {

        $examId = $row["examination_id"];
        $subjectId = $row["subject_id"];
        $studentId = $row["student_id"];


        /* =========================
           EXAMINATION LIST
        ========================= */

        if (!isset($exams[$examId])) {

            $exams[$examId] = [
                "id" => $examId,
                "name" => $row["exam_name"],
                "subjects" => []
            ];

        }


        /* =========================
           SUBJECT LIST
           Only subjects for which
           marks have been uploaded
        ========================= */

        if (!isset($exams[$examId]["subjects"][$subjectId])) {

            $exams[$examId]["subjects"][$subjectId] = [
                "id" => $subjectId,
                "name" => $row["subject_name"],
                "code" => $row["subject_code"]
            ];

        }


        /* =========================
           MARKS DATA
        ========================= */

        $marksData[$examId][$studentId][$subjectId] = $row["marks"];

    }

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Academics - Principal Dashboard</title>

    <link rel="stylesheet" href="../css/admin.css">

    <style>

        body {
            background: #f8fafc;
        }

        .principal-content {
            margin-left: 250px;
            padding: 30px;
        }

        .page-title {
            margin-bottom: 25px;
        }

        .page-title h1 {
            color: #172554;
            margin-bottom: 5px;
        }

        .page-title p {
            color: #64748b;
        }


        /* =========================
           FILTER BOX
        ========================= */

        .filter-box {
            background: white;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(15, 23, 42, 0.05);
        }

        .filter-box h2 {
            color: #172554;
            margin-bottom: 20px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .filter-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #334155;
        }

        .filter-group select {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: white;
            font-size: 14px;
        }

        .filter-button {
            padding: 11px 24px;
            border: none;
            border-radius: 6px;
            background: #172554;
            color: white;
            font-weight: 600;
            cursor: pointer;
        }

        .filter-button:hover {
            background: #1e3a8a;
        }


        /* =========================
           EXAMINATION SECTION
        ========================= */

        .exam-section {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(15, 23, 42, 0.05);
        }

        .exam-header {
            background: #172554;
            color: white;
            padding: 18px 22px;
        }

        .exam-header h2 {
            margin: 0;
            font-size: 20px;
        }

        .exam-header p {
            margin-top: 4px;
            color: #cbd5e1;
            font-size: 13px;
        }


        /* =========================
           MARKS TABLE
        ========================= */

        .table-wrapper {
            overflow-x: auto;
        }

        .marks-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        .marks-table th {
            background: #f1f5f9;
            color: #172554;
            padding: 13px 12px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
            font-size: 13px;
            white-space: nowrap;
        }

        .marks-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #475569;
            font-size: 14px;
        }

        .marks-table tbody tr:hover {
            background: #f8fafc;
        }

        .student-name {
            font-weight: 600;
            color: #172554 !important;
        }

        .mark-value {
            font-weight: 600;
            color: #1e3a8a !important;
            text-align: center;
        }

        .no-mark {
            color: #94a3b8 !important;
            text-align: center;
        }


        /* =========================
           EMPTY MESSAGE
        ========================= */

        .empty-box {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 45px 20px;
            text-align: center;
            color: #64748b;
        }

        .empty-box h3 {
            color: #172554;
            margin-bottom: 8px;
        }


        /* =========================
           SIDEBAR
        ========================= */

        .principal-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: #172554;
            color: white;
            padding: 25px 15px;
            z-index: 1000;
        }

        .principal-sidebar h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 20px;
        }

        .principal-sidebar a {
            display: block;
            color: #e2e8f0;
            text-decoration: none;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 5px;
        }

        .principal-sidebar a:hover,
        .principal-sidebar a.active {
            background: #1e3a8a;
            color: white;
        }


        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 800px) {

            .principal-sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .principal-content {
                margin-left: 0;
                padding: 20px;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>


<!-- =========================
     PRINCIPAL SIDEBAR
========================= -->

<div class="principal-sidebar">

    <h2>Principal Panel</h2>

    <a href="index.php">
        Dashboard
    </a>

    <a href="index.php">
        Students
    </a>

    <a href="academics.php" class="active">
        Academics
    </a>

    <a href="#">
        Lecturers
    </a>

    <a href="#">
        Attendance
    </a>

    <a href="#">
        Ask the Principal
    </a>

    <a href="../auth/logout.php">
        Logout
    </a>

</div>


<!-- =========================
     MAIN CONTENT
========================= -->

<div class="principal-content">


    <div class="page-title">

        <h1>Academics</h1>

        <p>
            View marks uploaded by lecturers for all students.
        </p>

    </div>


    <!-- =========================
         FILTER
    ========================= -->

    <div class="filter-box">

        <h2>Select Course & Semester</h2>

        <form method="GET" class="filter-form">


            <div class="filter-group">

                <label for="course">
                    Course
                </label>

                <select
                    name="course"
                    id="course"
                    required
                >

                    <option value="">
                        -- Select Course --
                    </option>

                    <?php foreach ($courses as $course): ?>

                        <option
                            value="<?php echo $course["id"]; ?>"
                            <?php
                            echo ($selectedCourse == $course["id"])
                                ? "selected"
                                : "";
                            ?>
                        >

                            <?php
                            echo htmlspecialchars(
                                $course["course_name"]
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="filter-group">

                <label for="semester">
                    Semester
                </label>

                <select
                    name="semester"
                    id="semester"
                    required
                >

                    <option value="">
                        -- Select Semester --
                    </option>

                    <?php for ($i = 1; $i <= 6; $i++): ?>

                        <option
                            value="<?php echo $i; ?>"
                            <?php
                            echo ($selectedSemester == $i)
                                ? "selected"
                                : "";
                            ?>
                        >

                            Semester <?php echo $i; ?>

                        </option>

                    <?php endfor; ?>

                </select>

            </div>


            <div>

                <button
                    type="submit"
                    class="filter-button"
                >
                    View Marks
                </button>

            </div>

        </form>

    </div>


    <!-- =========================
         MARKS
    ========================= -->

    <?php if ($selectedCourse > 0 && $selectedSemester > 0): ?>


        <?php if (count($exams) > 0): ?>


            <?php foreach ($exams as $exam): ?>


                <div class="exam-section">


                    <!-- EXAM TITLE -->

                    <div class="exam-header">

                        <h2>
                            <?php
                            echo htmlspecialchars(
                                $exam["name"]
                            );
                            ?>
                        </h2>

                        <p>
                            Subject-wise marks
                        </p>

                    </div>


                    <!-- TABLE -->

                    <div class="table-wrapper">

                        <table class="marks-table">

                            <thead>

                                <tr>

                                    <th>
                                        Sl.No
                                    </th>

                                    <th>
                                        Registration No
                                    </th>

                                    <th>
                                        Student Name
                                    </th>


                                    <?php foreach ($exam["subjects"] as $subject): ?>

                                        <th>

                                            <?php
                                            echo htmlspecialchars(
                                                $subject["name"]
                                            );
                                            ?>

                                            <?php if (!empty($subject["code"])): ?>

                                                <br>

                                                <small>
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $subject["code"]
                                                    );
                                                    ?>
                                                </small>

                                            <?php endif; ?>

                                        </th>

                                    <?php endforeach; ?>


                                </tr>

                            </thead>


                            <tbody>


                                <?php if (count($students) > 0): ?>


                                    <?php $sl = 1; ?>


                                    <?php foreach ($students as $student): ?>


                                        <tr>


                                            <td>
                                                <?php echo $sl++; ?>
                                            </td>


                                            <td>
                                                <?php
                                                echo htmlspecialchars(
                                                    $student["registration_no"]
                                                );
                                                ?>
                                            </td>


                                            <td class="student-name">

                                                <?php
                                                echo htmlspecialchars(
                                                    $student["full_name"]
                                                );
                                                ?>

                                            </td>


                                            <?php foreach ($exam["subjects"] as $subject): ?>


                                                <?php

                                                $studentId =
                                                    $student["id"];

                                                $subjectId =
                                                    $subject["id"];

                                                $hasMark =
                                                    isset(
                                                        $marksData[
                                                            $exam["id"]
                                                        ][$studentId]
                                                        [$subjectId]
                                                    );

                                                ?>


                                                <?php if ($hasMark): ?>

                                                    <td class="mark-value">

                                                        <?php
                                                        echo htmlspecialchars(
                                                            $marksData[
                                                                $exam["id"]
                                                            ][$studentId]
                                                            [$subjectId]
                                                        );
                                                        ?>

                                                    </td>

                                                <?php else: ?>

                                                    <td class="no-mark">
                                                        —
                                                    </td>

                                                <?php endif; ?>


                                            <?php endforeach; ?>


                                        </tr>


                                    <?php endforeach; ?>


                                <?php else: ?>


                                    <tr>

                                        <td
                                            colspan="<?php echo 3 + count($exam["subjects"]); ?>"
                                            class="no-mark"
                                        >

                                            No students found for
                                            this course and semester.

                                        </td>

                                    </tr>


                                <?php endif; ?>


                            </tbody>

                        </table>

                    </div>


                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <div class="empty-box">

                <h3>
                    No Marks Uploaded Yet
                </h3>

                <p>
                    There are no marks uploaded for this
                    course and semester yet.
                </p>

            </div>


        <?php endif; ?>


    <?php else: ?>


        <div class="empty-box">

            <h3>
                Select Course and Semester
            </h3>

            <p>
                Select a course and semester above
                to view uploaded marks.
            </p>

        </div>


    <?php endif; ?>


</div>


</body>

</html>