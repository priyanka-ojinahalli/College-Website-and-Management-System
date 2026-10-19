<?php

session_start();

require_once "../config/database.php";

/* Principal access only */
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "principal") {
    header("Location: ../auth/login.php");
    exit;
}


/* ---------------------------------------------------
   TOTAL LECTURERS
--------------------------------------------------- */

$totalLecturers = 0;

$tableCheck = $conn->query("SHOW TABLES LIKE 'lecturers'");

if ($tableCheck && $tableCheck->num_rows > 0) {

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM lecturers
    ");

    if ($result) {
        $row = $result->fetch_assoc();
        $totalLecturers = (int)$row["total"];
    }
}


/* ---------------------------------------------------
   PENDING QUESTIONS
--------------------------------------------------- */

$pendingQuestions = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM principal_questions
    WHERE status = 'Pending'
");

if ($result) {

    $row = $result->fetch_assoc();

    $pendingQuestions = (int)$row["total"];
}


/* ---------------------------------------------------
   ANSWERED QUESTIONS
--------------------------------------------------- */

$answeredQuestions = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM principal_questions
    WHERE status = 'Answered'
");

if ($result) {

    $row = $result->fetch_assoc();

    $answeredQuestions = (int)$row["total"];
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

    <title>Principal Dashboard</title>

    <link
        rel="stylesheet"
        href="../css/admin.css"
    >

    <style>

        /* ------------------------------------------------
           MAIN CONTENT
        ------------------------------------------------ */

        .admin-content {

            margin-left: 250px;

            padding: 30px;

            min-height: calc(100vh - 95px);

            background: #f8fafc;

            box-sizing: border-box;
        }


        /* ------------------------------------------------
           HEADER
        ------------------------------------------------ */

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


        /* ------------------------------------------------
           PAGE HEADING
        ------------------------------------------------ */

        .dashboard-heading {

            margin-bottom: 25px;
        }


        .dashboard-heading h1 {

            margin: 0 0 5px;

            color: #172554;

            font-size: 28px;
        }


        .dashboard-heading p {

            margin: 0;

            color: #64748b;

            font-size: 14px;
        }


        /* ------------------------------------------------
           SUMMARY CARDS
        ------------------------------------------------ */

        .summary-grid {

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 20px;

            margin-bottom: 30px;
        }


        .summary-card {

            background: #ffffff;

            border: 1px solid #e2e8f0;

            border-radius: 10px;

            padding: 22px;

            box-shadow:
                0 4px 12px
                rgba(15, 23, 42, 0.05);
        }


        .summary-card .card-label {

            color: #64748b;

            font-size: 14px;

            margin-bottom: 10px;
        }


        .summary-card .card-number {

            color: #172554;

            font-size: 30px;

            font-weight: 700;
        }


        /* ------------------------------------------------
           MODULE SECTION
        ------------------------------------------------ */

        .section-box {

            background: #ffffff;

            border: 1px solid #e2e8f0;

            border-radius: 10px;

            padding: 25px;

            margin-bottom: 30px;

            box-shadow:
                0 4px 12px
                rgba(15, 23, 42, 0.05);
        }


        .section-box h2 {

            margin: 0 0 20px;

            color: #172554;

            font-size: 21px;
        }


        /* ------------------------------------------------
           MODULE CARDS
        ------------------------------------------------ */

        .module-grid {

            display: grid;

            grid-template-columns: repeat(3, 1fr);

            gap: 18px;
        }


        .module-card {

            display: block;

            text-decoration: none;

            background: #f8fafc;

            border: 1px solid #e2e8f0;

            border-radius: 8px;

            padding: 20px;

            transition: 0.2s ease;
        }


        .module-card:hover {

            transform: translateY(-3px);

            box-shadow:
                0 7px 18px
                rgba(15, 23, 42, 0.08);
        }


        .module-card .icon {

            font-size: 28px;

            margin-bottom: 10px;
        }


        .module-card h3 {

            margin: 0 0 5px;

            color: #172554;

            font-size: 16px;
        }


        .module-card p {

            margin: 0;

            color: #64748b;

            font-size: 13px;

            line-height: 1.5;
        }


        /* ------------------------------------------------
           RESPONSIVE
        ------------------------------------------------ */

        @media (max-width: 1000px) {

            .summary-grid {

                grid-template-columns: repeat(2, 1fr);
            }

            .module-grid {

                grid-template-columns: repeat(2, 1fr);
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
        }


        @media (max-width: 600px) {

            .summary-grid {

                grid-template-columns: 1fr;
            }

            .module-grid {

                grid-template-columns: 1fr;
            }

            .header-college h1 {

                font-size: 20px;
            }

            .header-user {

                font-size: 13px;
            }
        }

    </style>

</head>


<body>


<!-- PRINCIPAL SIDEBAR -->

<?php include "includes/principal_sidebar.php"; ?>


<!-- PRINCIPAL HEADER -->

<?php include "includes/principal_header.php"; ?>


<!-- MAIN CONTENT -->

<div class="admin-content">


    <!-- PAGE TITLE -->

    <div class="dashboard-heading">

        <h1>Principal Dashboard</h1>

        <p>
            College academic and management overview
        </p>

    </div>


    <!-- SUMMARY -->

    <div class="summary-grid">


        <!-- TOTAL LECTURERS -->

        <div class="summary-card">

            <div class="card-label">
                Total Lecturers
            </div>

            <div class="card-number">

                <?php echo $totalLecturers; ?>

            </div>

        </div>


        <!-- PENDING QUESTIONS -->

        <div class="summary-card">

            <div class="card-label">
                Pending Questions
            </div>

            <div class="card-number">

                <?php echo $pendingQuestions; ?>

            </div>

        </div>


        <!-- ANSWERED QUESTIONS -->

        <div class="summary-card">

            <div class="card-label">
                Answered Questions
            </div>

            <div class="card-number">

                <?php echo $answeredQuestions; ?>

            </div>

        </div>


    </div>


    <!-- PRINCIPAL MODULES -->

    <div class="section-box">


        <h2>Principal Modules</h2>


        <div class="module-grid">


            <!-- STUDENTS -->

            <a
                href="student/index.php"
                class="module-card"
            >

                <div class="icon">
                    👨‍🎓
                </div>

                <h3>
                    Students
                </h3>

                <p>
                    View students according to
                    course and academic year.
                </p>

            </a>


            <!-- ACADEMICS -->

            <a
                href="academics.php"
                class="module-card"
            >

                <div class="icon">
                    📚
                </div>

                <h3>
                    Academics
                </h3>

                <p>
                    View marks uploaded by
                    lecturers for all students.
                </p>

            </a>


            <!-- LECTURERS -->

            <a
                href="lecturers.php"
                class="module-card"
            >

                <div class="icon">
                    👨‍🏫
                </div>

                <h3>
                    Lecturers
                </h3>

                <p>
                    View lecturer details and
                    allotted subjects.
                </p>

            </a>


            <!-- ATTENDANCE -->

            <a
                href="attendance.php"
                class="module-card"
            >

                <div class="icon">
                    📅
                </div>

                <h3>
                    Attendance
                </h3>

                <p>
                    View monthly attendance
                    shortage of students.
                </p>

            </a>


            <!-- ASK PRINCIPAL -->

            <a
                href="questions.php"
                class="module-card"
            >

                <div class="icon">
                    💬
                </div>

                <h3>
                    Ask the Principal
                </h3>

                <p>
                    View and respond to
                    questions from students.
                </p>

            </a>


            <!-- PROFILE -->

            <a
                href="#"
                class="module-card"
            >

                <div class="icon">
                    ⚙️
                </div>

                <h3>
                    Profile
                </h3>

                <p>
                    Manage Principal profile
                    and account settings.
                </p>

            </a>


        </div>

    </div>


</div>



</body>

</html>