<?php

session_start();

/* =========================================================
   PRINCIPAL ACCESS CHECK
   ========================================================= */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "principal"
) {
    header("Location: ../../auth/login.php");
    exit();
}

require_once "../../config/database.php";


/* =========================================================
   FILTER VALUES
   ========================================================= */

$course_id = isset($_GET["course_id"])
    ? (int)$_GET["course_id"]
    : 0;

$semester = isset($_GET["semester"])
    ? (int)$_GET["semester"]
    : 0;

$month = isset($_GET["month"])
    ? (int)$_GET["month"]
    : 0;

$year = isset($_GET["year"])
    ? (int)$_GET["year"]
    : (int)date("Y");


/* =========================================================
   GET COURSES
   ========================================================= */

$courses = [];

$stmt = $conn->prepare(
    "SELECT id, course_name
     FROM courses
     ORDER BY course_name ASC"
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}

$stmt->close();


/* =========================================================
   GET STUDENTS
   ========================================================= */

$students = [];

if ($course_id > 0 && $semester > 0) {

    $stmt = $conn->prepare(
        "SELECT
            id,
            registration_no,
            full_name,
            course_id,
            semester
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
   GET SUBJECTS WITH UPLOADED ATTENDANCE
   ========================================================= */

$subjects = [];

if ($course_id > 0 && $semester > 0) {

    $sql = "
        SELECT DISTINCT
            sub.id,
            sub.subject_name,
            sub.subject_code
        FROM attendance_monthly am
        INNER JOIN subjects sub
            ON sub.id = am.subject_id
        WHERE am.course_id = ?
        AND am.semester = ?
        AND am.year = ?
    ";

    if ($month >= 1 && $month <= 12) {
        $sql .= " AND am.month = ?";
    }

    $sql .= "
        ORDER BY sub.subject_name ASC
    ";

    $stmt = $conn->prepare($sql);

    if ($month >= 1 && $month <= 12) {

        $stmt->bind_param(
            "iiii",
            $course_id,
            $semester,
            $year,
            $month
        );

    } else {

        $stmt->bind_param(
            "iii",
            $course_id,
            $semester,
            $year
        );
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }

    $stmt->close();
}


/* =========================================================
   GET ATTENDANCE DATA
   ========================================================= */

$attendance = [];

if (
    $course_id > 0 &&
    $semester > 0 &&
    count($students) > 0 &&
    count($subjects) > 0
) {

    $sql = "
        SELECT
            ar.student_id,
            am.subject_id,
            SUM(ar.total_attendance) AS total_attendance,
            SUM(ar.total_present) AS total_present
        FROM attendance_records ar
        INNER JOIN attendance_monthly am
            ON am.id = ar.attendance_id
        WHERE am.course_id = ?
        AND am.semester = ?
        AND am.year = ?
    ";

    if ($month >= 1 && $month <= 12) {
        $sql .= " AND am.month = ?";
    }

    $sql .= "
        GROUP BY
            ar.student_id,
            am.subject_id
    ";

    $stmt = $conn->prepare($sql);

    if ($month >= 1 && $month <= 12) {

        $stmt->bind_param(
            "iiii",
            $course_id,
            $semester,
            $year,
            $month
        );

    } else {

        $stmt->bind_param(
            "iii",
            $course_id,
            $semester,
            $year
        );
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $student_id = (int)$row["student_id"];
        $subject_id = (int)$row["subject_id"];

        $total_attendance =
            (int)$row["total_attendance"];

        $total_present =
            (int)$row["total_present"];

        $percentage = 0;

        if ($total_attendance > 0) {

            $percentage =
                ($total_present / $total_attendance) * 100;
        }

        $attendance[$student_id][$subject_id] = [
            "total_attendance" => $total_attendance,
            "total_present" => $total_present,
            "percentage" => $percentage
        ];
    }

    $stmt->close();
}


/* =========================================================
   MONTH NAMES
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

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Attendance | Principal</title>

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

        .attendance-title {
            margin-bottom: 25px;
        }

        .attendance-title h2 {
            margin: 0;
            color: #172554;
        }

        .attendance-title p {
            margin-top: 8px;
            color: #64748b;
        }

        .attendance-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 25px;
        }

        .filter-grid {
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
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
        }

        .filter-button {
            padding: 10px 20px;
            background: #172554;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .filter-button:hover {
            background: #1e3a8a;
        }

        .reset-button {
            display: inline-block;
            margin-top: 10px;
            color: #475569;
            text-decoration: none;
            font-size: 14px;
        }

        .attendance-info {
            margin-bottom: 18px;
            color: #475569;
        }

        .attendance-info strong {
            color: #172554;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        .attendance-table th {
            background: #172554;
            color: white;
            padding: 13px 10px;
            text-align: center;
            white-space: nowrap;
        }

        .attendance-table th:first-child,
        .attendance-table th:nth-child(2) {
            text-align: left;
        }

        .attendance-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
        }

        .attendance-table td:first-child,
        .attendance-table td:nth-child(2) {
            text-align: left;
        }

        .attendance-table tr:hover {
            background: #f8fafc;
        }

        .subject-heading {
            display: block;
            font-size: 13px;
        }

        .subject-code {
            display: block;
            font-size: 11px;
            margin-top: 3px;
            opacity: 0.8;
        }

        .percentage {
            font-weight: 700;
            padding: 4px 7px;
            border-radius: 4px;
        }

        .safe {
            color: #15803d;
        }

        .shortage {
            color: #dc2626;
            background: #fee2e2;
        }

        .not-uploaded {
            color: #94a3b8;
        }

        .legend {
            display: flex;
            gap: 20px;
            margin-top: 18px;
            flex-wrap: wrap;
            color: #475569;
            font-size: 14px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .legend-box {
            width: 15px;
            height: 15px;
            border-radius: 3px;
        }

        .legend-safe {
            background: #dcfce7;
        }

        .legend-shortage {
            background: #fee2e2;
        }

        .legend-not-uploaded {
            background: #f1f5f9;
        }

        .no-data {
            padding: 30px;
            text-align: center;
            color: #64748b;
        }

        @media (max-width: 900px) {

            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 600px) {

            .attendance-page {
                padding: 15px;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>


<?php include "../includes/principal_sidebar.php"; ?>


<main class="main-content">

    <div class="attendance-page">


        <!-- PAGE TITLE -->

        <div class="attendance-title">

            <h2>📅 Attendance</h2>

            <p>
                View student attendance subject-wise.
            </p>

        </div>


        <!-- FILTER -->

        <div class="attendance-card">

            <form method="GET">

                <div class="filter-grid">


                    <!-- COURSE -->

                    <div class="form-group">

                        <label>Course</label>

                        <select name="course_id" required>

                            <option value="">
                                Select Course
                            </option>

                            <?php foreach ($courses as $course): ?>

                                <option
                                    value="<?php echo (int)$course["id"]; ?>"
                                    <?php
                                    echo $course_id ===
                                        (int)$course["id"]
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


                    <!-- SEMESTER -->

                    <div class="form-group">

                        <label>Semester</label>

                        <select name="semester" required>

                            <option value="">
                                Select Semester
                            </option>

                            <?php for ($i = 1; $i <= 6; $i++): ?>

                                <option
                                    value="<?php echo $i; ?>"
                                    <?php
                                    echo $semester === $i
                                        ? "selected"
                                        : "";
                                    ?>
                                >

                                    Semester <?php echo $i; ?>

                                </option>

                            <?php endfor; ?>

                        </select>

                    </div>


                    <!-- MONTH -->

                    <div class="form-group">

                        <label>Month</label>

                        <select name="month">

                            <option value="0">
                                All Months
                            </option>

                            <?php foreach ($months as $number => $name): ?>

                                <option
                                    value="<?php echo $number; ?>"
                                    <?php
                                    echo $month === $number
                                        ? "selected"
                                        : "";
                                    ?>
                                >

                                    <?php echo $name; ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- ATTENDANCE YEAR -->

                    <div class="form-group">

                        <label>Attendance Year</label>

                        <input
                            type="number"
                            name="year"
                            value="<?php echo $year; ?>"
                            min="2000"
                            max="2100"
                            required
                        >

                    </div>

                </div>


                <div style="margin-top:20px;">

                    <button
                        type="submit"
                        class="filter-button"
                    >
                        🔍 View Attendance
                    </button>

                    <br>

                    <a
                        href="index.php"
                        class="reset-button"
                    >
                        Reset Filters
                    </a>

                </div>

            </form>

        </div>


        <?php if ($course_id > 0 && $semester > 0): ?>


            <div class="attendance-card">


                <?php

                $selected_course_name = "";

                foreach ($courses as $course) {

                    if (
                        (int)$course["id"] ===
                        $course_id
                    ) {

                        $selected_course_name =
                            $course["course_name"];

                        break;
                    }
                }

                ?>


                <div class="attendance-info">

                    <strong>
                        Attendance Report
                    </strong>

                    &nbsp;|&nbsp;

                    <?php
                    echo htmlspecialchars(
                        $selected_course_name
                    );
                    ?>

                    &nbsp;|&nbsp;

                    Semester <?php echo $semester; ?>

                    &nbsp;|&nbsp;

                    <?php

                    if ($month > 0) {

                        echo htmlspecialchars(
                            $months[$month]
                        );

                    } else {

                        echo "All Months";

                    }

                    ?>

                    &nbsp;|&nbsp;

                    <?php echo $year; ?>

                </div>


                <?php if (count($students) === 0): ?>


                    <div class="no-data">

                        No students found for the selected
                        course and semester.

                    </div>


                <?php elseif (count($subjects) === 0): ?>


                    <div class="no-data">

                        No attendance has been uploaded
                        for the selected course, semester
                        and attendance year.

                    </div>


                <?php else: ?>


                    <div class="table-wrapper">

                        <table class="attendance-table">

                            <thead>

                                <tr>

                                    <th>
                                        Reg No
                                    </th>

                                    <th>
                                        Student Name
                                    </th>


                                    <?php foreach ($subjects as $subject): ?>

                                        <th>

                                            <span class="subject-heading">

                                                <?php
                                                echo htmlspecialchars(
                                                    $subject["subject_name"]
                                                );
                                                ?>

                                            </span>

                                            <?php if (
                                                !empty(
                                                    $subject["subject_code"]
                                                )
                                            ): ?>

                                                <span class="subject-code">

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $subject[
                                                            "subject_code"
                                                        ]
                                                    );
                                                    ?>

                                                </span>

                                            <?php endif; ?>

                                        </th>

                                    <?php endforeach; ?>


                                    <th>
                                        Overall
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                            <?php foreach ($students as $student): ?>

                                <?php

                                $student_id =
                                    (int)$student["id"];

                                $overall_total = 0;
                                $overall_present = 0;

                                ?>


                                <tr>

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
                                            $student["full_name"]
                                        );
                                        ?>

                                    </td>


                                    <?php foreach ($subjects as $subject): ?>

                                        <?php

                                        $subject_id =
                                            (int)$subject["id"];

                                        $data =
                                            $attendance[
                                                $student_id
                                            ][$subject_id]
                                            ?? null;

                                        ?>


                                        <td>

                                            <?php if ($data): ?>

                                                <?php

                                                $percentage =
                                                    (float)$data[
                                                        "percentage"
                                                    ];

                                                $overall_total +=
                                                    (int)$data[
                                                        "total_attendance"
                                                    ];

                                                $overall_present +=
                                                    (int)$data[
                                                        "total_present"
                                                    ];

                                                ?>


                                                <span
                                                    class="
                                                        percentage
                                                        <?php
                                                        echo $percentage < 75
                                                            ? "shortage"
                                                            : "safe";
                                                        ?>
                                                    "
                                                >

                                                    <?php
                                                    echo number_format(
                                                        $percentage,
                                                        2
                                                    );
                                                    ?>%

                                                </span>


                                            <?php else: ?>

                                                <span
                                                    class="not-uploaded"
                                                >
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                    <?php endforeach; ?>


                                    <!-- OVERALL -->

                                    <td>

                                        <?php

                                        if ($overall_total > 0) {

                                            $overall_percentage =
                                                (
                                                    $overall_present /
                                                    $overall_total
                                                ) * 100;

                                        } else {

                                            $overall_percentage = 0;

                                        }

                                        ?>


                                        <?php if ($overall_total > 0): ?>

                                            <span
                                                class="
                                                    percentage
                                                    <?php
                                                    echo $overall_percentage < 75
                                                        ? "shortage"
                                                        : "safe";
                                                    ?>
                                                "
                                            >

                                                <?php
                                                echo number_format(
                                                    $overall_percentage,
                                                    2
                                                );
                                                ?>%

                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="not-uploaded"
                                            >
                                                —
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>


                            <?php endforeach; ?>


                            </tbody>

                        </table>

                    </div>


                    <!-- LEGEND -->

                    <div class="legend">

                        <div class="legend-item">

                            <span
                                class="legend-box legend-safe"
                            ></span>

                            75% or above

                        </div>


                        <div class="legend-item">

                            <span
                                class="legend-box legend-shortage"
                            ></span>

                            Below 75% — Shortage

                        </div>


                        <div class="legend-item">

                            <span
                                class="legend-box legend-not-uploaded"
                            ></span>

                            Attendance not uploaded

                        </div>

                    </div>


                <?php endif; ?>


            </div>


        <?php endif; ?>


    </div>

</main>



</body>

</html>