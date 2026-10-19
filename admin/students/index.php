<?php

session_start();

/* =====================================================
   ADMIN AUTHENTICATION
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
   DATABASE CONNECTION
===================================================== */

require_once "../../config/database.php";


/* =====================================================
   DELETE STUDENT
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_id"])) {

    $student_id = (int) $_POST["delete_id"];

    if ($student_id > 0) {

        $stmt = $conn->prepare(
            "DELETE FROM students WHERE id = ?"
        );

        if ($stmt) {

            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    header("Location: index.php");
    exit();
}


/* =====================================================
   FILTER VALUES
===================================================== */

$search = trim($_GET["search"] ?? "");

$course_id = (int) ($_GET["course_id"] ?? 0);

$semester = (int) ($_GET["semester"] ?? 0);

$status = trim($_GET["status"] ?? "");

$admission_year = trim(
    $_GET["admission_year"] ?? ""
);


/* =====================================================
   GET COURSES
===================================================== */

$courses = [];

$course_result = $conn->query(
    "SELECT id, course_name, course_code
     FROM courses
     ORDER BY course_name ASC"
);

if ($course_result) {

    while ($course = $course_result->fetch_assoc()) {

        $courses[] = $course;

    }
}


/* =====================================================
   GET ADMISSION YEARS
===================================================== */

$years = [];

$year_result = $conn->query(
    "SELECT DISTINCT admission_year
     FROM students
     WHERE admission_year IS NOT NULL
     AND admission_year <> ''
     ORDER BY admission_year DESC"
);

if ($year_result) {

    while ($year = $year_result->fetch_assoc()) {

        $years[] = $year["admission_year"];

    }
}


/* =====================================================
   TOTAL STUDENTS
===================================================== */

$total_students = 0;

$total_result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM students"
);

if ($total_result) {

    $row = $total_result->fetch_assoc();

    $total_students = (int) $row["total"];
}


/* =====================================================
   COURSE STATISTICS
===================================================== */

$course_stats = [

    "BCA" => [
        "total" => 0,
        "year1" => 0,
        "year2" => 0,
        "year3" => 0
    ],

    "BBA" => [
        "total" => 0,
        "year1" => 0,
        "year2" => 0,
        "year3" => 0
    ],

    "BCOM" => [
        "total" => 0,
        "year1" => 0,
        "year2" => 0,
        "year3" => 0
    ]

];


/*
   Semester mapping:

   Semester 1 + 2 = 1st Year
   Semester 3 + 4 = 2nd Year
   Semester 5 + 6 = 3rd Year
*/

$stats_sql = "

    SELECT

        CASE

            WHEN UPPER(
                REPLACE(c.course_code, '.', '')
            ) = 'BCA'

            THEN 'BCA'


            WHEN UPPER(
                REPLACE(c.course_code, '.', '')
            ) = 'BBA'

            THEN 'BBA'


            WHEN UPPER(
                REPLACE(c.course_code, '.', '')
            ) = 'BCOM'

            THEN 'BCOM'


            ELSE 'OTHER'

        END AS course_type,


        COUNT(s.id) AS total_students,


        SUM(
            CASE

                WHEN s.semester IN (1, 2)
                THEN 1

                ELSE 0

            END
        ) AS year1,


        SUM(
            CASE

                WHEN s.semester IN (3, 4)
                THEN 1

                ELSE 0

            END
        ) AS year2,


        SUM(
            CASE

                WHEN s.semester IN (5, 6)
                THEN 1

                ELSE 0

            END
        ) AS year3


    FROM courses c


    LEFT JOIN students s

        ON s.course_id = c.id


    GROUP BY course_type

";


$stats_result = $conn->query($stats_sql);


if ($stats_result) {

    while ($row = $stats_result->fetch_assoc()) {

        $course_type = $row["course_type"];

        if (
            isset(
                $course_stats[$course_type]
            )
        ) {

            $course_stats[$course_type]["total"] =
                (int) $row["total_students"];


            $course_stats[$course_type]["year1"] =
                (int) $row["year1"];


            $course_stats[$course_type]["year2"] =
                (int) $row["year2"];


            $course_stats[$course_type]["year3"] =
                (int) $row["year3"];

        }
    }
}


/* =====================================================
   BUILD STUDENT QUERY
===================================================== */

$sql = "

    SELECT

        s.*,

        c.course_name,

        c.course_code

    FROM students s

    LEFT JOIN courses c

        ON s.course_id = c.id

    WHERE 1 = 1

";


$params = [];

$types = "";


/* =====================================================
   SEARCH
===================================================== */

if ($search !== "") {

    $sql .= "

        AND (

            s.registration_no LIKE ?

            OR s.student_id LIKE ?

            OR s.full_name LIKE ?

            OR s.email LIKE ?

            OR s.phone LIKE ?

        )

    ";


    $search_value =
        "%" . $search . "%";


    $params[] = $search_value;

    $params[] = $search_value;

    $params[] = $search_value;

    $params[] = $search_value;

    $params[] = $search_value;


    $types .= "sssss";
}


/* =====================================================
   COURSE FILTER
===================================================== */

if ($course_id > 0) {

    $sql .= "

        AND s.course_id = ?

    ";

    $params[] = $course_id;

    $types .= "i";
}


/* =====================================================
   SEMESTER FILTER
===================================================== */

if ($semester > 0) {

    $sql .= "

        AND s.semester = ?

    ";

    $params[] = $semester;

    $types .= "i";
}


/* =====================================================
   STATUS FILTER
===================================================== */

if ($status !== "") {

    $sql .= "

        AND s.student_status = ?

    ";

    $params[] = $status;

    $types .= "s";
}


/* =====================================================
   ADMISSION YEAR FILTER
===================================================== */

if ($admission_year !== "") {

    $sql .= "

        AND s.admission_year = ?

    ";

    $params[] = $admission_year;

    $types .= "s";
}


/* =====================================================
   ORDER
===================================================== */

$sql .= "

    ORDER BY s.id DESC

";


/* =====================================================
   PREPARE QUERY
===================================================== */

$stmt = $conn->prepare($sql);


if (!$stmt) {

    die(
        "Student query error: " .
        htmlspecialchars($conn->error)
    );
}


/* =====================================================
   BIND FILTER PARAMETERS
===================================================== */

if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


/* =====================================================
   EXECUTE
===================================================== */

$stmt->execute();

$result = $stmt->get_result();

?>


<!DOCTYPE html>

<html lang="en">


<head>

    <meta charset="UTF-8">


    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >


    <title>
        Students - Admin
    </title>


    <link
        rel="stylesheet"
        href="../../css/admin.css?v=6"
    >


    <style>


        /* =================================================
           STUDENT STATISTICS
        ================================================= */

        .student-stats {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

            margin-top: 25px;

            margin-bottom: 25px;

        }


        .student-stat-card {

            background: #ffffff;

            padding: 22px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0,0,0,0.08);

        }


        .student-stat-card h3 {

            margin: 0;

            font-size: 16px;

            color: #555;

        }


        .student-stat-card .number {

            font-size: 32px;

            font-weight: bold;

            margin-top: 12px;

            color: #172554;

        }


        /* =================================================
           YEAR SUMMARY
        ================================================= */

        .year-summary {

            margin-top: 12px;

            display: flex;

            flex-direction: column;

            gap: 5px;

            font-size: 12px;

            color: #666;

        }


        .year-summary span {

            display: block;

        }


        /* =================================================
           PAGE TOP
        ================================================= */

        .page-top {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            gap: 20px;

        }


        /* =================================================
           FILTER CARD
        ================================================= */

        .student-filter-card {

            background: #ffffff;

            padding: 20px;

            border-radius: 10px;

            margin-top: 25px;

            margin-bottom: 25px;

            box-shadow:
                0 2px 10px
                rgba(0,0,0,0.08);

        }


        .filter-form {

            display: grid;

            grid-template-columns:
                2fr
                1fr
                1fr
                1fr
                1fr
                auto;

            gap: 12px;

            align-items: end;

        }


        .filter-group label {

            display: block;

            font-weight: bold;

            font-size: 13px;

            margin-bottom: 6px;

        }


        .filter-group input,
        .filter-group select {

            width: 100%;

            padding: 10px;

            border: 1px solid #d1d5db;

            border-radius: 5px;

            font-family: inherit;

            font-size: 14px;

        }


        .filter-buttons {

            display: flex;

            gap: 8px;

        }


        .btn-filter {

            padding: 10px 16px;

            border: none;

            border-radius: 5px;

            background: #172554;

            color: white;

            cursor: pointer;

            white-space: nowrap;

        }


        .btn-filter:hover {

            background: #1e3a8a;

        }


        .btn-clear {

            padding: 10px 16px;

            border-radius: 5px;

            background: #e5e7eb;

            color: #111827;

            text-decoration: none;

            white-space: nowrap;

        }


        .btn-clear:hover {

            background: #d1d5db;

        }


        /* =================================================
           TABLE
        ================================================= */

        .table-card {

            background: #ffffff;

            padding: 25px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0,0,0,0.08);

            overflow-x: auto;

        }


        .admin-table {

            width: 100%;

            border-collapse:
                collapse;

        }


        .admin-table th {

            background: #172554;

            color: white;

            padding: 14px 12px;

            text-align: left;

            font-size: 14px;

            white-space: nowrap;

        }


        .admin-table td {

            padding: 13px 12px;

            border-bottom:
                1px solid #e5e7eb;

            font-size: 14px;

            vertical-align: middle;

        }


        .admin-table tr:hover {

            background: #f8fafc;

        }


        .no-data {

            text-align: center;

            padding: 30px !important;

            color: #666;

            font-size: 15px;

        }


        /* =================================================
           ACTION BUTTONS
        ================================================= */

        .action-edit {

            display: inline-block;

            padding: 6px 10px;

            background: #172554;

            color: white;

            text-decoration: none;

            border-radius: 4px;

            font-size: 13px;

            margin-right: 5px;

        }


        .action-edit:hover {

            background: #1e3a8a;

        }


        .action-delete {

            display: inline-block;

            padding: 6px 10px;

            background: #dc2626;

            color: white;

            text-decoration: none;

            border-radius: 4px;

            font-size: 13px;

        }


        .action-delete:hover {

            background: #b91c1c;

        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 1200px) {

            .filter-form {

                grid-template-columns:
                    repeat(3, 1fr);

            }

        }


        @media (max-width: 1000px) {

            .student-stats {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        @media (max-width: 700px) {

            .page-top {

                flex-direction: column;

                align-items: flex-start;

            }


            .student-stats {

                grid-template-columns: 1fr;

            }


            .filter-form {

                grid-template-columns: 1fr;

            }


            .filter-buttons {

                width: 100%;

            }


            .btn-filter,
            .btn-clear {

                flex: 1;

                text-align: center;

            }

        }


    </style>

</head>


<body>


<div class="dashboard">


    <!-- =================================================
         SIDEBAR
    ================================================= -->

    <?php include "../../includes/admin_sidebar.php"; ?>


    <!-- =================================================
         MAIN CONTENT
    ================================================= -->

    <div class="main-content">


        <!-- HEADER -->

        <?php include "../../includes/admin_header.php"; ?>


        <!-- CONTENT -->

        <div class="content">


            <!-- =================================================
                 PAGE TITLE
            ================================================= -->

            <div class="page-top">


                <div>

                    <h2>
                        Students
                    </h2>


                    <p class="welcome">

                        View and manage college students.

                    </p>

                </div>


                <a
                    href="add.php"
                    class="btn-primary"
                >

                    + Add Student

                </a>


            </div>



            <!-- =================================================
                 STUDENT STATISTICS
            ================================================= -->

            <div class="student-stats">


                <!-- TOTAL -->

                <div class="student-stat-card">

                    <h3>
                        Total Students
                    </h3>


                    <div class="number">

                        <?php
                        echo $total_students;
                        ?>

                    </div>


                    <div class="year-summary">

                        <span>

                            BCA:
                            <?php
                            echo
                                $course_stats["BCA"]["total"];
                            ?>

                        </span>


                        <span>

                            BBA:
                            <?php
                            echo
                                $course_stats["BBA"]["total"];
                            ?>

                        </span>


                        <span>

                            B.Com:
                            <?php
                            echo
                                $course_stats["BCOM"]["total"];
                            ?>

                        </span>

                    </div>

                </div>



                <!-- BCA -->

                <div class="student-stat-card">

                    <h3>
                        BCA Students
                    </h3>


                    <div class="number">

                        <?php
                        echo
                            $course_stats["BCA"]["total"];
                        ?>

                    </div>


                    <div class="year-summary">

                        <span>

                            1st Year:
                            <?php
                            echo
                                $course_stats["BCA"]["year1"];
                            ?>

                        </span>


                        <span>

                            2nd Year:
                            <?php
                            echo
                                $course_stats["BCA"]["year2"];
                            ?>

                        </span>


                        <span>

                            3rd Year:
                            <?php
                            echo
                                $course_stats["BCA"]["year3"];
                            ?>

                        </span>

                    </div>

                </div>



                <!-- BBA -->

                <div class="student-stat-card">

                    <h3>
                        BBA Students
                    </h3>


                    <div class="number">

                        <?php
                        echo
                            $course_stats["BBA"]["total"];
                        ?>

                    </div>


                    <div class="year-summary">

                        <span>

                            1st Year:
                            <?php
                            echo
                                $course_stats["BBA"]["year1"];
                            ?>

                        </span>


                        <span>

                            2nd Year:
                            <?php
                            echo
                                $course_stats["BBA"]["year2"];
                            ?>

                        </span>


                        <span>

                            3rd Year:
                            <?php
                            echo
                                $course_stats["BBA"]["year3"];
                            ?>

                        </span>

                    </div>

                </div>



                <!-- B.COM -->

                <div class="student-stat-card">

                    <h3>
                        B.Com Students
                    </h3>


                    <div class="number">

                        <?php
                        echo
                            $course_stats["BCOM"]["total"];
                        ?>

                    </div>


                    <div class="year-summary">

                        <span>

                            1st Year:
                            <?php
                            echo
                                $course_stats["BCOM"]["year1"];
                            ?>

                        </span>


                        <span>

                            2nd Year:
                            <?php
                            echo
                                $course_stats["BCOM"]["year2"];
                            ?>

                        </span>


                        <span>

                            3rd Year:
                            <?php
                            echo
                                $course_stats["BCOM"]["year3"];
                            ?>

                        </span>

                    </div>

                </div>


            </div>



            <!-- =================================================
                 SEARCH & FILTERS
            ================================================= -->

            <div class="student-filter-card">


                <form
                    method="GET"
                    action="index.php"
                    class="filter-form"
                >


                    <!-- SEARCH -->

                    <div class="filter-group">

                        <label>
                            Search
                        </label>


                        <input
                            type="text"
                            name="search"
                            placeholder="Registration no, name, email..."
                            value="<?php
                                echo htmlspecialchars(
                                    $search
                                );
                            ?>"
                        >

                    </div>



                    <!-- COURSE -->

                    <div class="filter-group">

                        <label>
                            Course
                        </label>


                        <select name="course_id">


                            <option value="">
                                All Courses
                            </option>


                            <?php
                            foreach (
                                $courses
                                as $course
                            ):
                            ?>


                                <option
                                    value="<?php
                                        echo
                                            (int)
                                            $course["id"];
                                    ?>"
                                    <?php

                                    echo (
                                        $course_id ==
                                        $course["id"]
                                    )
                                    ? "selected"
                                    : "";

                                    ?>
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $course["course_code"]
                                    );
                                    ?>

                                </option>


                            <?php
                            endforeach;
                            ?>


                        </select>

                    </div>



                    <!-- SEMESTER -->

                    <div class="filter-group">

                        <label>
                            Semester
                        </label>


                        <select name="semester">


                            <option value="">
                                All Semesters
                            </option>


                            <?php
                            for (
                                $i = 1;
                                $i <= 6;
                                $i++
                            ):
                            ?>


                                <option
                                    value="<?php
                                        echo $i;
                                    ?>"
                                    <?php

                                    echo (
                                        $semester == $i
                                    )
                                    ? "selected"
                                    : "";

                                    ?>
                                >

                                    Semester
                                    <?php
                                    echo $i;
                                    ?>

                                </option>


                            <?php
                            endfor;
                            ?>


                        </select>

                    </div>



                    <!-- STATUS -->

                    <div class="filter-group">

                        <label>
                            Status
                        </label>


                        <select name="status">


                            <option value="">
                                All Status
                            </option>


                            <option
                                value="Active"
                                <?php

                                echo (
                                    $status ===
                                    "Active"
                                )
                                ? "selected"
                                : "";

                                ?>
                            >

                                Active

                            </option>


                            <option
                                value="Graduated"
                                <?php

                                echo (
                                    $status ===
                                    "Graduated"
                                )
                                ? "selected"
                                : "";

                                ?>
                            >

                                Graduated

                            </option>


                            <option
                                value="Discontinued"
                                <?php

                                echo (
                                    $status ===
                                    "Discontinued"
                                )
                                ? "selected"
                                : "";

                                ?>
                            >

                                Discontinued

                            </option>


                        </select>

                    </div>



                    <!-- ADMISSION YEAR -->

                    <div class="filter-group">

                        <label>
                            Admission Year
                        </label>


                        <select name="admission_year">


                            <option value="">
                                All Years
                            </option>


                            <?php
                            foreach (
                                $years
                                as $year
                            ):
                            ?>


                                <option
                                    value="<?php
                                        echo htmlspecialchars(
                                            $year
                                        );
                                    ?>"
                                    <?php

                                    echo (
                                        $admission_year ==
                                        $year
                                    )
                                    ? "selected"
                                    : "";

                                    ?>
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $year
                                    );
                                    ?>

                                </option>


                            <?php
                            endforeach;
                            ?>


                        </select>

                    </div>



                    <!-- BUTTONS -->

                    <div class="filter-buttons">


                        <button
                            type="submit"
                            class="btn-filter"
                        >

                            Search / Filter

                        </button>


                        <a
                            href="index.php"
                            class="btn-clear"
                        >

                            Clear

                        </a>


                    </div>


                </form>


            </div>



            <!-- =================================================
                 STUDENTS TABLE
            ================================================= -->

            <div class="table-card">


                <table class="admin-table">


                    <thead>

                        <tr>

                            <th>
                                ID
                            </th>


                            <th>
                                Registration No.
                            </th>


                            <th>
                                Student ID
                            </th>


                            <th>
                                Name
                            </th>


                            <th>
                                Email
                            </th>


                            <th>
                                Phone
                            </th>


                            <th>
                                Gender
                            </th>


                            <th>
                                Course
                            </th>


                            <th>
                                Semester
                            </th>


                            <th>
                                Admission Year
                            </th>


                            <th>
                                Status
                            </th>


                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>



                    <tbody>


                    <?php
                    if (
                        $result &&
                        $result->num_rows > 0
                    ):
                    ?>


                        <?php
                        while (
                            $student =
                            $result->fetch_assoc()
                        ):
                        ?>


                            <tr>


                                <!-- ID -->

                                <td>

                                    <?php
                                    echo
                                        (int)
                                        $student["id"];
                                    ?>

                                </td>



                                <!-- REGISTRATION -->

                                <td>

                                    <strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $student[
                                                "registration_no"
                                            ] ?? ""
                                        );
                                        ?>

                                    </strong>

                                </td>



                                <!-- STUDENT ID -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $student[
                                            "student_id"
                                        ] ?? ""
                                    );
                                    ?>

                                </td>



                                <!-- NAME -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $student[
                                            "full_name"
                                        ] ?? ""
                                    );
                                    ?>

                                </td>



                                <!-- EMAIL -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $student[
                                            "email"
                                        ] ?? ""
                                    );
                                    ?>

                                </td>



                                <!-- PHONE -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $student[
                                            "phone"
                                        ] ?? ""
                                    );
                                    ?>

                                </td>



                                <!-- GENDER -->

                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $student[
                                            "gender"
                                        ] ?? ""
                                    );
                                    ?>

                                </td>



                                <!-- COURSE -->

                                <td>

                                    <?php

                                    $course_name =
                                        $student[
                                            "course_name"
                                        ] ?? "";

                                    $course_code =
                                        $student[
                                            "course_code"
                                        ] ?? "";


                                    if (
                                        !empty(
                                            $course_code
                                        )
                                    ) {

                                        echo htmlspecialchars(
                                            $course_code
                                        );

                                    } else {

                                        echo htmlspecialchars(
                                            $course_name
                                        );

                                    }

                                    ?>

                                </td>



                                <!-- SEMESTER -->

                                <td>

                                    <?php

                                    if (
                                        !empty(
                                            $student[
                                                "semester"
                                            ]
                                        )
                                    ) {

                                        echo
                                            "Semester " .
                                            (int)
                                            $student[
                                                "semester"
                                            ];

                                    } else {

                                        echo "-";

                                    }

                                    ?>

                                </td>



                                <!-- ADMISSION YEAR -->

                                <td>

                                    <?php

                                    echo !empty(
                                        $student[
                                            "admission_year"
                                        ]
                                    )

                                    ? htmlspecialchars(
                                        $student[
                                            "admission_year"
                                        ]
                                    )

                                    : "-";

                                    ?>

                                </td>



                                <!-- STATUS -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $student[
                                            "student_status"
                                        ] ?? "Active"
                                    );

                                    ?>

                                </td>



                                <!-- ACTIONS -->

                                <td>

                                    <a
                                        href="view.php?id=<?php
                                           echo (int) $student["id"];
                                        ?>"
                                        class="action-view"
                                    >
                                         View
                                    </a>
                                    <a
                                        href="edit.php?id=<?php
                                            echo (int)
                                                $student["id"];
                                        ?>"
                                        class="action-edit"
                                    >

                                        Edit

                                    </a>


                                    <form
                                        method="POST"
                                        action="index.php"
                                        style="display:inline;"
                                        onsubmit="return confirm('Are you sure you want to delete this student?');"
                                    >


                                        <input
                                            type="hidden"
                                            name="delete_id"
                                            value="<?php
                                                echo (int)
                                                    $student["id"];
                                            ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="action-delete"
                                            style="
                                                border:none;
                                                cursor:pointer;
                                            "
                                        >

                                            Delete

                                        </button>


                                    </form>


                                </td>


                            </tr>


                        <?php
                        endwhile;
                        ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="12"
                                class="no-data"
                            >

                                No students found.

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>


                </table>


            </div>


        </div>


    </div>


</div>


</body>

</html>