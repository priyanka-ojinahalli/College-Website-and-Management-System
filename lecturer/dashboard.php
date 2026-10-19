<?php

session_start();

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "lecturer"
) {
    header("Location: ../auth/login.php");
    exit();
}

require_once "../config/database.php";


/* =====================================================
   GET LOGGED-IN LECTURER
===================================================== */

$user_id = (int) $_SESSION["user_id"];

$lecturer_name = "Lecturer";
$lecturer_db_id = 0;


$stmt = $conn->prepare("
    SELECT
        id,
        lecturer_id,
        full_name,
        email
    FROM lecturers
    WHERE user_id = ?
    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param(
        "i",
        $user_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $lecturer = $result->fetch_assoc();

        $lecturer_db_id =
            (int) $lecturer["id"];

        $lecturer_name =
            $lecturer["full_name"];

    }

    $stmt->close();
}


/* =====================================================
   COUNT ASSIGNED SUBJECTS
===================================================== */

$total_subjects = 0;

if ($lecturer_db_id > 0) {

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM subjects
        WHERE lecturer_id = ?
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $lecturer_db_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {

            $total_subjects =
                (int) $row["total"];

        }

        $stmt->close();

    }

}


/* =====================================================
   COUNT STUDENTS
===================================================== */

$total_students = 0;

if ($lecturer_db_id > 0) {

    /*
     * A lecturer's students are determined by:
     *
     * subjects.course_id = students.course_id
     * subjects.semester = students.semester
     */

    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT s.id) AS total

        FROM students s

        INNER JOIN subjects sub
            ON sub.course_id = s.course_id
            AND sub.semester = s.semester

        WHERE sub.lecturer_id = ?
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $lecturer_db_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {

            $total_students =
                (int) $row["total"];

        }

        $stmt->close();

    }

}


/* =====================================================
   GET ASSIGNED SUBJECTS
===================================================== */

$subjects = [];

if ($lecturer_db_id > 0) {

    $stmt = $conn->prepare("
        SELECT
            sub.id,
            sub.subject_name,
            sub.subject_code,
            sub.semester,

            c.course_name,
            c.course_code

        FROM subjects sub

        INNER JOIN courses c
            ON c.id = sub.course_id

        WHERE sub.lecturer_id = ?

        ORDER BY
            c.course_name ASC,
            sub.semester ASC,
            sub.subject_name ASC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $lecturer_db_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {

            $subjects[] = $row;

        }

        $stmt->close();

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

    <title>Lecturer Dashboard</title>


    <!-- ADMIN CSS -->

    <link
        rel="stylesheet"
        href="../css/admin.css?v=10"
    >


    <style>

        /* =================================================
           LECTURER HEADER
           SAME STRUCTURE AS ADMIN HEADER
        ================================================= */

        .admin-header {

            height: 80px;

            background: #ffffff;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 30px;

            box-sizing: border-box;

            box-shadow:
                0 2px 8px
                rgba(0, 0, 0, 0.08);

        }


        /* =================================================
           COLLEGE HEADER
        ================================================= */

        .college-header {

            display: flex;

            flex-direction: column;

            justify-content: center;

        }


        .college-header h1 {

            margin: 0;

            padding: 0;

            font-size: 28px;

            line-height: 1.2;

            font-weight: 700;

            color: #000000;

        }


        .college-header p {

            margin: 2px 0 0;

            padding: 0;

            font-size: 20px;

            line-height: 1.2;

            color: #4b5563;

        }


        /* =================================================
           RIGHT SIDE LECTURER
        ================================================= */

        .user-info {

            margin-left: auto;

            display: flex;

            align-items: center;

            gap: 7px;

            white-space: nowrap;

            font-size: 18px;

            color: #111111;

        }


        .user-icon {

            font-size: 20px;

            line-height: 1;

        }


        /* =================================================
           DASHBOARD CONTENT
        ================================================= */

        .lecturer-dashboard {

            padding: 25px;

        }


        /* =================================================
           WELCOME
        ================================================= */

        .welcome-section {

            margin-bottom: 25px;

        }


        .welcome-section h2 {

            margin: 0 0 6px;

            color: #111827;

        }


        .welcome-section p {

            margin: 0;

            color: #6b7280;

        }


        /* =================================================
           STATISTICS
        ================================================= */

        .stats-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;

            margin-bottom: 30px;

        }


        .stat-card {

            background: #ffffff;

            padding: 22px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

        }


        .stat-card h3 {

            margin: 0;

            font-size: 14px;

            font-weight: 600;

            color: #6b7280;

        }


        .stat-number {

            margin-top: 8px;

            font-size: 30px;

            font-weight: bold;

            color: #172554;

        }


        /* =================================================
           SUBJECTS CARD
        ================================================= */

        .subjects-card {

            background: #ffffff;

            padding: 25px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

        }


        .subjects-card h3 {

            margin: 0 0 20px;

            color: #172554;

        }


        /* =================================================
           SUBJECT GRID
        ================================================= */

        .subject-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;

        }


        .subject-item {

            background: #f8fafc;

            border: 1px solid #e5e7eb;

            border-radius: 8px;

            padding: 18px;

        }


        .subject-item h4 {

            margin: 0 0 8px;

            color: #172554;

            font-size: 17px;

        }


        .subject-code {

            margin-bottom: 10px;

            color: #6b7280;

            font-size: 13px;

        }


        .subject-info {

            color: #374151;

            font-size: 14px;

            line-height: 1.7;

        }


        .subject-actions {

            margin-top: 15px;

        }


        .btn-primary {

            display: inline-block;

            padding: 9px 15px;

            background: #172554;

            color: #ffffff;

            text-decoration: none;

            border-radius: 5px;

            font-size: 13px;

            font-weight: 600;

        }


        .btn-primary:hover {

            background: #1e3a8a;

        }


        /* =================================================
           NO SUBJECTS
        ================================================= */

        .no-data {

            padding: 20px;

            text-align: center;

            color: #6b7280;

            background: #f8fafc;

            border-radius: 6px;

        }


        /* =================================================
           MOBILE
        ================================================= */

        @media (max-width: 700px) {

            .stats-grid {

                grid-template-columns: 1fr;

            }


            .subject-grid {

                grid-template-columns: 1fr;

            }


            .admin-header {

                padding: 0 15px;

            }


            .college-header h1 {

                font-size: 22px;

            }


            .college-header p {

                font-size: 15px;

            }


            .user-info {

                font-size: 15px;

            }

        }

    </style>

</head>


<body>


<div class="dashboard">


    <!-- =================================================
         LECTURER SIDEBAR
    ================================================= -->

    <?php

    include "../includes/lecturer_sidebar.php";

    ?>


    <!-- =================================================
         MAIN CONTENT
    ================================================= -->

    <div class="main-content">


        <!-- =================================================
             HEADER
        ================================================= -->

        <header class="admin-header">


            <!-- COLLEGE INFORMATION -->

            <div class="college-header">

                <h1>
                    Smt. Sharadamma V. Kotbal
                </h1>

                <p>
                    BBA, BCA &amp; B.Com College,
                    Koppal, Karnataka
                </p>

            </div>


            <!-- LECTURER -->

            <div class="user-info">

                <span class="user-icon">
                    👤
                </span>

                <strong>
                    Lecturer
                </strong>

            </div>


        </header>


        <!-- =================================================
             DASHBOARD CONTENT
        ================================================= -->

        <div class="lecturer-dashboard">


            <!-- =================================================
                 WELCOME
            ================================================= -->

            <div class="welcome-section">

                <h2>

                    Welcome,

                    <?php

                    echo htmlspecialchars(
                        $lecturer_name
                    );

                    ?>!

                </h2>


                <p>

                    Manage your assigned subjects
                    and student marks from your dashboard.

                </p>

            </div>


            <!-- =================================================
                 STATISTICS
            ================================================= -->

            <div class="stats-grid">


                <!-- ASSIGNED SUBJECTS -->

                <div class="stat-card">

                    <h3>
                        Assigned Subjects
                    </h3>

                    <div class="stat-number">

                        <?php

                        echo $total_subjects;

                        ?>

                    </div>

                </div>


                <!-- STUDENTS -->

                <div class="stat-card">

                    <h3>
                        Students
                    </h3>

                    <div class="stat-number">

                        <?php

                        echo $total_students;

                        ?>

                    </div>

                </div>


            </div>


            <!-- =================================================
                 ASSIGNED SUBJECTS
            ================================================= -->

            <div class="subjects-card">


                <h3>
                    My Assigned Subjects
                </h3>


                <?php if (
                    count($subjects) > 0
                ): ?>


                    <div class="subject-grid">


                        <?php foreach (
                            $subjects as $subject
                        ): ?>


                            <div class="subject-item">


                                <!-- SUBJECT NAME -->

                                <h4>

                                    <?php

                                    echo htmlspecialchars(
                                        $subject[
                                            "subject_name"
                                        ]
                                    );

                                    ?>

                                </h4>


                                <!-- SUBJECT CODE -->

                                <div class="subject-code">

                                    <?php

                                    if (
                                        !empty(
                                            $subject[
                                                "subject_code"
                                            ]
                                        )
                                    ) {

                                        echo htmlspecialchars(
                                            $subject[
                                                "subject_code"
                                            ]
                                        );

                                    } else {

                                        echo "No subject code";

                                    }

                                    ?>

                                </div>


                                <!-- COURSE / SEMESTER -->

                                <div class="subject-info">


                                    <strong>
                                        Course:
                                    </strong>


                                    <?php

                                    echo htmlspecialchars(
                                        $subject[
                                            "course_code"
                                        ]
                                        . " - "
                                        . $subject[
                                            "course_name"
                                        ]
                                    );

                                    ?>


                                    <br>


                                    <strong>
                                        Semester:
                                    </strong>


                                    <?php

                                    echo (int)
                                        $subject[
                                            "semester"
                                        ];

                                    ?>


                                </div>


                                <!-- BUTTON -->

                                <div class="subject-actions">

                                    <a
                                        href="subjects/index.php"
                                        class="btn-primary"
                                    >
                                        View Subject
                                    </a>

                                </div>


                            </div>


                        <?php endforeach; ?>


                    </div>


                <?php else: ?>


                    <div class="no-data">

                        No subjects have been assigned
                        to you yet.

                    </div>


                <?php endif; ?>


            </div>


        </div>


    </div>


</div>


</body>

</html>