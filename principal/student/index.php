<?php

session_start();

require_once "../../config/database.php";

/* Principal access only */
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "principal") {
    header("Location: ../../auth/login.php");
    exit;
}


/* ---------------------------------------------------
   GET SELECTED FILTERS
--------------------------------------------------- */

$selectedCourse = isset($_GET["course"]) ? (int)$_GET["course"] : 0;
$selectedYear   = isset($_GET["year"]) ? (int)$_GET["year"] : 0;


/* ---------------------------------------------------
   TOTAL STUDENTS
--------------------------------------------------- */

$totalStudents = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM students
");

if ($result) {
    $row = $result->fetch_assoc();
    $totalStudents = (int)$row["total"];
}


/* ---------------------------------------------------
   COURSE-WISE STUDENT COUNT + YEAR-WISE COUNT
--------------------------------------------------- */

$courseStats = [];

$sql = "
    SELECT
        c.id,
        c.course_name,
        c.course_code,

        COUNT(s.id) AS total_students,

        SUM(
            CASE
                WHEN s.semester IN (1, 2) THEN 1
                ELSE 0
            END
        ) AS first_year,

        SUM(
            CASE
                WHEN s.semester IN (3, 4) THEN 1
                ELSE 0
            END
        ) AS second_year,

        SUM(
            CASE
                WHEN s.semester IN (5, 6) THEN 1
                ELSE 0
            END
        ) AS third_year

    FROM courses c

    LEFT JOIN students s
        ON s.course_id = c.id

    GROUP BY
        c.id,
        c.course_name,
        c.course_code

    ORDER BY c.course_name
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $courseStats[] = $row;

    }
}


/* ---------------------------------------------------
   COURSE DROPDOWN
--------------------------------------------------- */

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


/* ---------------------------------------------------
   STUDENT LIST
--------------------------------------------------- */

$students = [];

if ($selectedCourse > 0 && in_array($selectedYear, [1, 2, 3], true)) {

    /*
        Year mapping:

        1st Year = Semester 1, 2
        2nd Year = Semester 3, 4
        3rd Year = Semester 5, 6
    */

    if ($selectedYear === 1) {

        $semesters = [1, 2];

    } elseif ($selectedYear === 2) {

        $semesters = [3, 4];

    } else {

        $semesters = [5, 6];

    }


    $sql = "
        SELECT
            s.id,
            s.registration_no,
            s.roll_no,
            s.student_id,
            s.full_name,
            s.email,
            s.phone,
            s.gender,
            s.semester,
            c.course_name,
            c.course_code

        FROM students s

        INNER JOIN courses c
            ON s.course_id = c.id

        WHERE s.course_id = ?
        AND s.semester IN (?, ?)

        ORDER BY s.semester ASC, s.full_name ASC
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param(
            "iii",
            $selectedCourse,
            $semesters[0],
            $semesters[1]
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {

            $students[] = $row;

        }

        $stmt->close();
    }
}


/* ---------------------------------------------------
   SELECTED COURSE NAME
--------------------------------------------------- */

$selectedCourseName = "";

if ($selectedCourse > 0) {

    foreach ($courses as $course) {

        if ((int)$course["id"] === $selectedCourse) {

            $selectedCourseName = $course["course_name"];

            break;
        }
    }
}


/* ---------------------------------------------------
   YEAR NAME
--------------------------------------------------- */

$selectedYearName = "";

if ($selectedYear === 1) {

    $selectedYearName = "1st Year";

} elseif ($selectedYear === 2) {

    $selectedYearName = "2nd Year";

} elseif ($selectedYear === 3) {

    $selectedYearName = "3rd Year";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Students - Principal Panel</title>

    <link rel="stylesheet" href="../../css/admin.css">

    <style>

        /* Main content */

        .admin-content {

            margin-left: 250px;

            padding: 30px;

            min-height: calc(100vh - 95px);

            background: #f8fafc;

            box-sizing: border-box;

        }


        /* Header */

        .admin-header {

            margin-left: 250px;

            width: calc(100% - 250px);

            box-sizing: border-box;

            height: 95px;

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


        /* Page heading */

        .page-heading {

            margin-bottom: 25px;

        }


        .page-heading h1 {

            margin: 0 0 5px;

            color: #172554;

            font-size: 28px;

        }


        .page-heading p {

            margin: 0;

            color: #64748b;

            font-size: 14px;

        }


        /* Total students */

        .total-box {

            background: #ffffff;

            border: 1px solid #e2e8f0;

            border-radius: 10px;

            padding: 22px;

            margin-bottom: 25px;

            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);

        }


        .total-box h3 {

            margin: 0 0 8px;

            color: #64748b;

            font-size: 15px;

            font-weight: 500;

        }


        .total-number {

            font-size: 32px;

            font-weight: 700;

            color: #172554;

        }


        /* Course cards */

        .section-box {

            background: #ffffff;

            border: 1px solid #e2e8f0;

            border-radius: 10px;

            padding: 25px;

            margin-bottom: 25px;

            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);

        }


        .section-box h2 {

            margin: 0 0 20px;

            color: #172554;

            font-size: 21px;

        }


        .course-grid {

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 20px;

        }


        .course-card {

            background: #f8fafc;

            border: 1px solid #e2e8f0;

            border-radius: 9px;

            padding: 20px;

        }


        .course-card h3 {

            margin: 0 0 5px;

            color: #172554;

            font-size: 18px;

        }


        .course-code {

            color: #64748b;

            font-size: 13px;

            margin-bottom: 15px;

        }


        .course-total {

            font-size: 27px;

            font-weight: 700;

            color: #1e3a8a;

            margin-bottom: 15px;

        }


        .course-total span {

            font-size: 13px;

            font-weight: 400;

            color: #64748b;

        }


        .year-list {

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 8px;

        }


        .year-box {

            background: #ffffff;

            border: 1px solid #e2e8f0;

            border-radius: 6px;

            padding: 10px 6px;

            text-align: center;

        }


        .year-box small {

            display: block;

            color: #64748b;

            font-size: 11px;

            margin-bottom: 4px;

        }


        .year-box strong {

            color: #172554;

            font-size: 18px;

        }


        /* Filter */

        .filter-grid {

            display: grid;

            grid-template-columns: 1fr 1fr auto;

            gap: 18px;

            align-items: end;

        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            color: #334155;

            font-size: 14px;

            font-weight: 600;

        }


        .form-group select {

            width: 100%;

            padding: 11px 12px;

            border: 1px solid #cbd5e1;

            border-radius: 6px;

            background: #ffffff;

            color: #334155;

            font-size: 14px;

            box-sizing: border-box;

        }


        .view-button {

            display: inline-block;

            padding: 11px 22px;

            border: none;

            border-radius: 6px;

            background: #172554;

            color: #ffffff;

            font-size: 14px;

            font-weight: 600;

            cursor: pointer;

            text-decoration: none;

        }


        .view-button:hover {

            background: #1e3a8a;

        }


        .clear-button {

            display: inline-block;

            margin-left: 8px;

            padding: 11px 18px;

            border-radius: 6px;

            border: 1px solid #cbd5e1;

            color: #334155;

            background: #ffffff;

            text-decoration: none;

            font-size: 14px;

        }


        /* Selected result */

        .result-heading {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 18px;

        }


        .result-heading h2 {

            margin: 0;

        }


        .student-count {

            color: #64748b;

            font-size: 14px;

        }


        /* Table */

        .table-wrapper {

            width: 100%;

            overflow-x: auto;

        }


        .student-table {

            width: 100%;

            border-collapse: collapse;

            min-width: 850px;

        }


        .student-table th {

            background: #172554;

            color: #ffffff;

            padding: 12px;

            text-align: left;

            font-size: 13px;

            white-space: nowrap;

        }


        .student-table td {

            padding: 12px;

            border-bottom: 1px solid #e2e8f0;

            color: #334155;

            font-size: 13px;

        }


        .student-table tbody tr:hover {

            background: #f8fafc;

        }


        .no-students {

            text-align: center;

            padding: 30px;

            color: #64748b;

        }


        /* Responsive */

        @media (max-width: 1100px) {

            .course-grid {

                grid-template-columns: 1fr;

            }

        }


        @media (max-width: 800px) {

            .admin-content {

                margin-left: 0;

                padding: 20px;

            }

            .admin-header {

                margin-left: 0;

                width: 100%;

            }

            .filter-grid {

                grid-template-columns: 1fr;

            }

            .year-list {

                grid-template-columns: 1fr 1fr 1fr;

            }

        }
        .view-student-button {
    display: inline-block;
    padding: 7px 14px;
    background: #172554;
    color: #ffffff;
    border-radius: 5px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
}

.view-student-button:hover {
    background: #1e3a8a;
}


        @media (max-width: 600px) {

            .header-college h1 {

                font-size: 20px;

            }

            .header-user {

                font-size: 13px;

            }

            .year-list {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>


<body>


<?php include "../includes/principal_sidebar.php"; ?>


<?php include "../includes/principal_header.php"; ?>


<div class="admin-content">


    <!-- PAGE HEADING -->

    <div class="page-heading">

        <h1>Students</h1>

        <p>View college students according to course and year.</p>

    </div>


    <!-- TOTAL STUDENTS -->

    <div class="total-box">

        <h3>Total Students</h3>

        <div class="total-number">

            <?php echo $totalStudents; ?>

        </div>

    </div>


    <!-- COURSE STATISTICS -->

    <div class="section-box">

        <h2>Students According to Course</h2>


        <div class="course-grid">


            <?php if (count($courseStats) > 0): ?>


                <?php foreach ($courseStats as $course): ?>


                    <div class="course-card">


                        <h3>

                            <?php

                            echo htmlspecialchars(
                                $course["course_name"]
                            );

                            ?>

                        </h3>


                        <div class="course-code">

                            <?php

                            echo htmlspecialchars(
                                $course["course_code"] ?? ""
                            );

                            ?>

                        </div>


                        <div class="course-total">

                            <?php

                            echo (int)$course["total_students"];

                            ?>

                            <span>Students</span>

                        </div>


                        <div class="year-list">


                            <div class="year-box">

                                <small>1st Year</small>

                                <strong>

                                    <?php

                                    echo (int)$course["first_year"];

                                    ?>

                                </strong>

                            </div>


                            <div class="year-box">

                                <small>2nd Year</small>

                                <strong>

                                    <?php

                                    echo (int)$course["second_year"];

                                    ?>

                                </strong>

                            </div>


                            <div class="year-box">

                                <small>3rd Year</small>

                                <strong>

                                    <?php

                                    echo (int)$course["third_year"];

                                    ?>

                                </strong>

                            </div>


                        </div>


                    </div>


                <?php endforeach; ?>


            <?php else: ?>


                <p>No courses found.</p>


            <?php endif; ?>


        </div>

    </div>


    <!-- FILTER -->

    <div class="section-box">

        <h2>View Students</h2>


        <form method="GET" action="index.php">


            <div class="filter-grid">


                <!-- COURSE -->

                <div class="form-group">

                    <label for="course">

                        Select Course

                    </label>


                    <select name="course" id="course" required>

                        <option value="">

                            Select Course

                        </option>


                        <?php foreach ($courses as $course): ?>


                            <option

                                value="<?php echo (int)$course["id"]; ?>"

                                <?php

                                if (
                                    $selectedCourse ===
                                    (int)$course["id"]
                                ) {

                                    echo "selected";

                                }

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


                <!-- YEAR -->

                <div class="form-group">

                    <label for="year">

                        Select Year

                    </label>


                    <select name="year" id="year" required>

                        <option value="">

                            Select Year

                        </option>


                        <option

                            value="1"

                            <?php

                            echo $selectedYear === 1
                                ? "selected"
                                : "";

                            ?>

                        >

                            1st Year

                        </option>


                        <option

                            value="2"

                            <?php

                            echo $selectedYear === 2
                                ? "selected"
                                : "";

                            ?>

                        >

                            2nd Year

                        </option>


                        <option

                            value="3"

                            <?php

                            echo $selectedYear === 3
                                ? "selected"
                                : "";

                            ?>

                        >

                            3rd Year

                        </option>

                    </select>

                </div>


                <!-- BUTTON -->

                <div>

                    <button
                        type="submit"
                        class="view-button"
                    >
                        View Students
                    </button>


                    <a
                        href="index.php"
                        class="clear-button"
                    >
                        Clear
                    </a>

                </div>


            </div>


        </form>

    </div>


    <!-- STUDENT RESULT -->

    <?php if ($selectedCourse > 0 && $selectedYear > 0): ?>


        <div class="section-box">


            <div class="result-heading">

                <h2>

                    <?php

                    echo htmlspecialchars(
                        $selectedCourseName
                    );

                    ?>

                    -

                    <?php

                    echo htmlspecialchars(
                        $selectedYearName
                    );

                    ?>

                </h2>


                <div class="student-count">

                    <?php echo count($students); ?>

                    Students

                </div>

            </div>


            <?php if (count($students) > 0): ?>


                <div class="table-wrapper">


                    <table class="student-table">


                        <thead>

                            <tr>

                                <th>Sl. No.</th>

                                <th>Registration No.</th>

                                <th>Roll No.</th>

                                <th>Student ID</th>

                                <th>Name</th>

                                <th>Email</th>

                                <th>Phone</th>

                                <th>Semester</th>
                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php

                            $slNo = 1;

                            ?>


                            <?php foreach ($students as $student): ?>


                                <tr>


                                    <td>

                                        <?php echo $slNo++; ?>

                                    </td>


                                    <td>

                                        <?php

                                        echo htmlspecialchars(
                                            $student["registration_no"]
                                        );

                                        ?>

                                    </td>


                                    <td>

                                        <?php

                                        echo htmlspecialchars(
                                            $student["roll_no"] ?? "-"
                                        );

                                        ?>

                                    </td>


                                    <td>

                                        <?php

                                        echo htmlspecialchars(
                                            $student["student_id"]
                                        );

                                        ?>

                                    </td>


                                    <td>

                                        <strong>

                                            <?php

                                            echo htmlspecialchars(
                                                $student["full_name"]
                                            );

                                            ?>

                                        </strong>

                                    </td>


                                    <td>

                                        <?php

                                        echo htmlspecialchars(
                                            $student["email"]
                                        );

                                        ?>

                                    </td>


                                    <td>

                                        <?php

                                        echo htmlspecialchars(
                                            $student["phone"] ?? "-"
                                        );

                                        ?>

                                    </td>


                                    <td>

                                        Semester

                                        <?php

                                        echo (int)$student["semester"];

                                        ?>

                                    </td>
                                    <td>

                                        <a
                                          href="view.php?id=<?php echo (int)$student["id"]; ?>"
                                          class="view-student-button"
                                        >
                                           View
                                        </a>

                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php else: ?>


                <div class="no-students">

                    No students found for the selected course and year.

                </div>


            <?php endif; ?>


        </div>


    <?php endif; ?>


</div>



</body>

</html>