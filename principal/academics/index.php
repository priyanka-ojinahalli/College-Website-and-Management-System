<?php

session_start();

require_once "../../config/database.php";

/* Principal access only */
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "principal") {
    header("Location: ../../auth/login.php");
    exit;
}


/* -------------------------------------------------------
   SELECTED FILTERS
------------------------------------------------------- */

$selectedCourse = isset($_GET["course_id"]) ? (int)$_GET["course_id"] : 0;
$selectedYear   = isset($_GET["year"]) ? (int)$_GET["year"] : 0;


/* -------------------------------------------------------
   GET COURSES
------------------------------------------------------- */

$courses = [];

$result = $conn->query("
    SELECT id, course_name, course_code
    FROM courses
    ORDER BY course_name ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}


/* -------------------------------------------------------
   YEAR / SEMESTER MAPPING
------------------------------------------------------- */

$yearSemesters = [
    1 => [1, 2],
    2 => [3, 4],
    3 => [5, 6]
];


/* -------------------------------------------------------
   EXAMINATIONS
------------------------------------------------------- */

$examinations = [];

$result = $conn->query("
    SELECT *
    FROM examinations
    ORDER BY id ASC
");


if ($result) {

    while ($row = $result->fetch_assoc()) {

    if (isset($row["name"])) {
        $row["display_name"] = $row["name"];
    } elseif (isset($row["examination_name"])) {
        $row["display_name"] = $row["examination_name"];
    } elseif (isset($row["exam_name"])) {
        $row["display_name"] = $row["exam_name"];
    } elseif (isset($row["title"])) {
        $row["display_name"] = $row["title"];
    } else {
        $row["display_name"] = "Examination " . $row["id"];
    }

    $examinations[] = $row;
}

}


/* -------------------------------------------------------
   SUBJECTS + STUDENTS + MARKS
------------------------------------------------------- */

$examData = [];

if (
    $selectedCourse > 0 &&
    isset($yearSemesters[$selectedYear])
) {

    $semesters = $yearSemesters[$selectedYear];

    $semester1 = $semesters[0];
    $semester2 = $semesters[1];


    /*
     * Get students belonging to selected
     * course and academic year.
     */
    $students = [];

    $stmt = $conn->prepare("
        SELECT
            id,
            registration_no,
            full_name,
            semester
        FROM students
        WHERE course_id = ?
        AND semester IN (?, ?)
        ORDER BY full_name ASC
    ");

    $stmt->bind_param(
        "iii",
        $selectedCourse,
        $semester1,
        $semester2
    );

    $stmt->execute();

    $studentResult = $stmt->get_result();

    while ($row = $studentResult->fetch_assoc()) {
        $students[] = $row;
    }

    $stmt->close();


    /*
     * Process each examination separately.
     */
    foreach ($examinations as $exam) {

        $examId = (int)$exam["id"];


        /*
         * Get subjects for selected course/year
         * for which marks have been uploaded
         * for this examination.
         */
        $subjects = [];

        $stmt = $conn->prepare("
            SELECT DISTINCT
                s.id,
                s.subject_name,
                s.subject_code,
                s.semester
            FROM subjects s
            INNER JOIN marks m
                ON m.subject_id = s.id
            INNER JOIN students st
                ON st.id = m.student_id
            WHERE s.course_id = ?
            AND s.semester IN (?, ?)
            AND m.examination_id = ?
            AND st.course_id = ?
            AND st.semester IN (?, ?)
            ORDER BY s.semester ASC, s.subject_name ASC
        ");

        $stmt->bind_param(
            "iiiiiii",
            $selectedCourse,
            $semester1,
            $semester2,
            $examId,
            $selectedCourse,
            $semester1,
            $semester2
        );

        $stmt->execute();

        $subjectResult = $stmt->get_result();

        while ($row = $subjectResult->fetch_assoc()) {
            $subjects[] = $row;
        }

        $stmt->close();


        /*
         * Show examination only if marks
         * have actually been uploaded.
         */
        if (count($subjects) === 0) {
            continue;
        }


        /*
         * Get marks for this examination.
         */
        $marks = [];

        $stmt = $conn->prepare("
            SELECT
                student_id,
                subject_id,
                marks
            FROM marks
            WHERE examination_id = ?
            AND student_id IN (
                SELECT id
                FROM students
                WHERE course_id = ?
                AND semester IN (?, ?)
            )
        ");

        $stmt->bind_param(
            "iiii",
            $examId,
            $selectedCourse,
            $semester1,
            $semester2
        );

        $stmt->execute();

        $markResult = $stmt->get_result();

        while ($row = $markResult->fetch_assoc()) {

            $studentId = (int)$row["student_id"];
            $subjectId = (int)$row["subject_id"];

            $marks[$studentId][$subjectId] = $row["marks"];
        }

        $stmt->close();


        $examData[] = [
            "id" => $examId,
            "name" => $exam["display_name"],
            "subjects" => $subjects,
            "students" => $students,
            "marks" => $marks
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Principal Academics</title>

<link rel="stylesheet" href="../../css/admin.css">

<style>

/* -------------------------------------------------------
   HEADER
------------------------------------------------------- */

.admin-header {
    margin-left: 250px;
    width: calc(100% - 250px);
    height: 95px;
    box-sizing: border-box;

    display: flex;
    justify-content: space-between;
    align-items: center;

    padding: 15px 30px;

    background: #ffffff;
}

.header-college h1 {
    margin: 0;
    font-size: 28px;
    color: #172554;
}

.header-college p {
    margin: 5px 0 0;
    color: #475569;
}

.header-user {
    display: flex;
    align-items: center;
    gap: 8px;

    font-size: 16px;
    color: #172554;

    white-space: nowrap;
}


/* -------------------------------------------------------
   CONTENT
------------------------------------------------------- */

.admin-content {
    margin-left: 250px;

    padding: 30px;

    min-height: calc(100vh - 95px);

    background: #f8fafc;

    box-sizing: border-box;
}

.page-heading {
    margin-bottom: 25px;
}

.page-heading h1 {
    margin: 0 0 5px;

    font-size: 28px;

    color: #172554;
}

.page-heading p {
    margin: 0;

    color: #64748b;

    font-size: 14px;
}


/* -------------------------------------------------------
   FILTER BOX
------------------------------------------------------- */

.filter-box {
    background: #ffffff;

    border: 1px solid #e2e8f0;

    border-radius: 10px;

    padding: 25px;

    margin-bottom: 30px;

    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}

.filter-box h2 {
    margin: 0 0 20px;

    color: #172554;

    font-size: 20px;
}

.filter-form {
    display: flex;

    align-items: flex-end;

    gap: 20px;

    flex-wrap: wrap;
}

.filter-group {
    display: flex;

    flex-direction: column;

    gap: 7px;
}

.filter-group label {
    font-size: 13px;

    font-weight: 600;

    color: #475569;
}

.filter-group select {
    width: 220px;

    padding: 10px 12px;

    border: 1px solid #cbd5e1;

    border-radius: 6px;

    background: #ffffff;

    font-size: 14px;
}

.filter-group select:focus {
    outline: none;

    border-color: #172554;
}

.btn-view {
    padding: 10px 20px;

    border: none;

    border-radius: 6px;

    background: #172554;

    color: #ffffff;

    font-size: 14px;

    cursor: pointer;
}

.btn-view:hover {
    background: #1e3a8a;
}


/* -------------------------------------------------------
   EXAMINATION SECTION
------------------------------------------------------- */

.exam-section {
    background: #ffffff;

    border: 1px solid #e2e8f0;

    border-radius: 10px;

    padding: 25px;

    margin-bottom: 30px;

    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}

.exam-title {
    margin: 0 0 20px;

    color: #172554;

    font-size: 21px;
}

.exam-subtitle {
    margin: -12px 0 20px;

    color: #64748b;

    font-size: 13px;
}


/* -------------------------------------------------------
   TABLE
------------------------------------------------------- */

.table-wrapper {
    width: 100%;

    overflow-x: auto;
}

.marks-table {
    width: 100%;

    border-collapse: collapse;

    min-width: 700px;
}

.marks-table th {
    background: #172554;

    color: #ffffff;

    padding: 12px;

    text-align: left;

    font-size: 13px;

    white-space: nowrap;
}

.marks-table td {
    padding: 11px 12px;

    border-bottom: 1px solid #e2e8f0;

    color: #334155;

    font-size: 13px;
}

.marks-table tbody tr:hover {
    background: #f8fafc;
}

.marks-table th:first-child,
.marks-table td:first-child {
    width: 55px;

    text-align: center;
}

.registration {
    font-weight: 600;

    color: #172554;
}

.student-name {
    font-weight: 600;
}

.subject-code {
    display: block;

    margin-top: 3px;

    color: #94a3b8;

    font-size: 11px;

    font-weight: normal;
}

.mark-value {
    font-weight: 600;

    text-align: center;
}


/* -------------------------------------------------------
   NO DATA
------------------------------------------------------- */

.no-data {
    background: #ffffff;

    border: 1px solid #e2e8f0;

    border-radius: 10px;

    padding: 35px;

    text-align: center;

    color: #64748b;
}

.no-data h3 {
    margin: 0 0 8px;

    color: #172554;
}


/* -------------------------------------------------------
   RESPONSIVE
------------------------------------------------------- */

@media (max-width: 800px) {

    .admin-header {
        margin-left: 0;

        width: 100%;
    }

    .admin-content {
        margin-left: 0;

        padding: 20px;
    }

    .filter-form {
        flex-direction: column;

        align-items: stretch;
    }

    .filter-group select {
        width: 100%;
    }

    .btn-view {
        width: 100%;
    }

}

</style>

</head>


<body>


<?php include "../includes/principal_sidebar.php"; ?>

<?php include "../includes/principal_header.php"; ?>


<div class="admin-content">


    <div class="page-heading">

        <h1>Academics</h1>

        <p>
            View examination marks uploaded by lecturers.
        </p>

    </div>


    <!-- FILTER -->

    <div class="filter-box">

        <h2>Select Course and Year</h2>

        <form method="GET" class="filter-form">


            <div class="filter-group">

                <label for="course_id">
                    Course
                </label>

                <select name="course_id" id="course_id" required>

                    <option value="">
                        Select Course
                    </option>

                    <?php foreach ($courses as $course): ?>

                        <option
                            value="<?php echo (int)$course["id"]; ?>"
                            <?php
                            echo $selectedCourse == $course["id"]
                                ? "selected"
                                : "";
                            ?>
                        >

                            <?php
                            echo htmlspecialchars(
                                $course["course_name"]
                            );
                            ?>

                            <?php if (!empty($course["course_code"])): ?>

                                -
                                <?php
                                echo htmlspecialchars(
                                    $course["course_code"]
                                );
                                ?>

                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="filter-group">

                <label for="year">
                    Academic Year
                </label>

                <select name="year" id="year" required>

                    <option value="">
                        Select Year
                    </option>

                    <option
                        value="1"
                        <?php echo $selectedYear === 1 ? "selected" : ""; ?>
                    >
                        1st Year
                    </option>

                    <option
                        value="2"
                        <?php echo $selectedYear === 2 ? "selected" : ""; ?>
                    >
                        2nd Year
                    </option>

                    <option
                        value="3"
                        <?php echo $selectedYear === 3 ? "selected" : ""; ?>
                    >
                        3rd Year
                    </option>

                </select>

            </div>


            <button type="submit" class="btn-view">
                View Marks
            </button>


        </form>

    </div>


    <?php if ($selectedCourse > 0 && $selectedYear > 0): ?>


        <?php if (count($examData) > 0): ?>


            <?php foreach ($examData as $exam): ?>


                <div class="exam-section">


                    <h2 class="exam-title">

                        <?php
                        echo htmlspecialchars($exam["name"]);
                        ?>

                        – Subjects

                    </h2>


                    <p class="exam-subtitle">

                        Marks uploaded for the selected course and
                        academic year.

                    </p>


                    <div class="table-wrapper">

                        <table class="marks-table">

                            <thead>

                                <tr>

                                    <th>
                                        Sl. No.
                                    </th>

                                    <th>
                                        Registration No.
                                    </th>

                                    <th>
                                        Student Name
                                    </th>


                                    <?php foreach ($exam["subjects"] as $subject): ?>

                                        <th>

                                            <?php
                                            echo htmlspecialchars(
                                                $subject["subject_name"]
                                            );
                                            ?>

                                            <?php if (!empty($subject["subject_code"])): ?>

                                                <span class="subject-code">

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $subject["subject_code"]
                                                    );
                                                    ?>

                                                </span>

                                            <?php endif; ?>

                                        </th>

                                    <?php endforeach; ?>

                                </tr>

                            </thead>


                            <tbody>


                                <?php if (count($exam["students"]) > 0): ?>


                                    <?php $sl = 1; ?>


                                    <?php foreach ($exam["students"] as $student): ?>


                                        <tr>

                                            <td>
                                                <?php echo $sl++; ?>
                                            </td>


                                            <td class="registration">

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


                                                <td class="mark-value">

                                                    <?php

                                                    $studentId =
                                                        (int)$student["id"];

                                                    $subjectId =
                                                        (int)$subject["id"];

                                                    if (
                                                        isset(
                                                            $exam["marks"]
                                                            [$studentId]
                                                            [$subjectId]
                                                        )
                                                    ) {

                                                        echo htmlspecialchars(
                                                            $exam["marks"]
                                                            [$studentId]
                                                            [$subjectId]
                                                        );

                                                    } else {

                                                        echo "—";

                                                    }

                                                    ?>

                                                </td>


                                            <?php endforeach; ?>


                                        </tr>


                                    <?php endforeach; ?>


                                <?php else: ?>


                                    <tr>

                                        <td
                                            colspan="<?php echo 3 + count($exam["subjects"]); ?>"
                                            style="text-align:center;"
                                        >

                                            No students found for the
                                            selected course and year.

                                        </td>

                                    </tr>


                                <?php endif; ?>


                            </tbody>

                        </table>

                    </div>

                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <div class="no-data">

                <h3>
                    No Marks Uploaded
                </h3>

                <p>
                    No examination marks have been uploaded yet for
                    the selected course and academic year.
                </p>

            </div>


        <?php endif; ?>


    <?php endif; ?>


</div>



</body>

</html>