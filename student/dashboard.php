<?php

session_start();

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "student"
) {
    header("Location: ../auth/login.php");
    exit();
}

require_once "../config/database.php";

$user_id = (int) $_SESSION["user_id"];


/* =====================================================
   GET STUDENT DETAILS
===================================================== */

$stmt = $conn->prepare("
    SELECT
        s.full_name,
        s.registration_no,
        s.roll_no,
        s.semester,
        s.student_status,
        c.course_name,
        c.course_code
    FROM students s
    LEFT JOIN courses c
        ON c.id = s.course_id
    WHERE s.user_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    die("Student account not found.");
}

$student = $result->fetch_assoc();

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

    <title>Student Dashboard</title>


    <style>

        /* =================================================
           GLOBAL
        ================================================= */

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
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


        .student-page {

            min-height: 100vh;

        }


        /* =================================================
           MAIN CONTENT
        ================================================= */

        .student-content {

            width: 100%;

            max-width: 1150px;

            margin: 0 auto;

            padding: 32px 30px 50px;

        }


        /* =================================================
           BREADCRUMB
        ================================================= */

        .page-breadcrumb {

            display: flex;

            align-items: center;

            gap: 7px;

            margin-bottom: 18px;

            color: #64748b;

            font-size: 13px;

        }

        .page-breadcrumb span:first-child {

            color: #172554;

            font-weight: 600;

        }


        /* =================================================
           WELCOME
        ================================================= */

        .welcome-card {

            position: relative;

            overflow: hidden;

            background:
                linear-gradient(
                    135deg,
                    #172554 0%,
                    #1e3a8a 55%,
                    #2563eb 100%
                );

            color: #ffffff;

            padding: 30px;

            border-radius: 15px;

            margin-bottom: 25px;

            box-shadow:
                0 12px 30px
                rgba(23, 37, 84, 0.18);

        }


        .welcome-card::before {

            content: "";

            position: absolute;

            width: 220px;

            height: 220px;

            border-radius: 50%;

            background:
                rgba(255, 255, 255, 0.06);

            right: -70px;

            top: -90px;

        }


        .welcome-card::after {

            content: "";

            position: absolute;

            width: 130px;

            height: 130px;

            border-radius: 50%;

            background:
                rgba(255, 255, 255, 0.05);

            right: 140px;

            bottom: -90px;

        }


        .welcome-content {

            position: relative;

            z-index: 2;

        }


        .welcome-small {

            margin: 0 0 7px;

            font-size: 12px;

            font-weight: 600;

            letter-spacing: 1px;

            text-transform: uppercase;

            opacity: 0.82;

        }


        .welcome-card h2 {

            margin: 0;

            font-size: 28px;

            font-weight: 700;

        }


        .welcome-card p {

            margin: 9px 0 0;

            max-width: 650px;

            font-size: 14px;

            line-height: 1.6;

            opacity: 0.88;

        }


        /* =================================================
           SECTION HEADING
        ================================================= */

        .section-heading {

            display: flex;

            align-items: center;

            justify-content: space-between;

            margin: 28px 0 14px;

        }


        .section-heading h3 {

            margin: 0;

            font-size: 19px;

            color: #172554;

        }


        .section-heading span {

            color: #64748b;

            font-size: 12px;

        }


        /* =================================================
           ACADEMIC INFORMATION BOX
        ================================================= */

        .academic-card {

            background: #ffffff;

            border: 1px solid #e5eaf1;

            border-radius: 13px;

            padding: 24px;

            box-shadow:
                0 4px 15px
                rgba(15, 23, 42, 0.05);

        }


        .academic-row {

            display: grid;

            grid-template-columns:
                220px 1fr;

            align-items: center;

            min-height: 52px;

            border-bottom:
                1px solid #edf0f4;

        }


        .academic-row:last-child {

            border-bottom: none;

        }


        .academic-label {

            color: #64748b;

            font-size: 13px;

            font-weight: 600;

        }


        .academic-value {

            color: #172033;

            font-size: 14px;

            font-weight: 600;

        }


        /* =================================================
           STATUS
        ================================================= */

        .status-badge {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 5px 10px;

            border-radius: 20px;

            background: #ecfdf5;

            color: #047857;

            font-size: 12px;

            font-weight: 700;

        }


        .status-dot {

            width: 6px;

            height: 6px;

            border-radius: 50%;

            background: #10b981;

        }


        /* =================================================
           MARKS CARD
        ================================================= */

        .marks-card {

            background: #ffffff;

            border: 1px solid #e5eaf1;

            border-radius: 13px;

            padding: 25px;

            margin-top: 18px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 25px;

            box-shadow:
                0 4px 15px
                rgba(15, 23, 42, 0.05);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease;

        }


        .marks-card:hover {

            transform: translateY(-2px);

            box-shadow:
                0 9px 24px
                rgba(15, 23, 42, 0.08);

        }


        .marks-left {

            display: flex;

            align-items: center;

            gap: 17px;

        }


        .marks-icon {

            width: 54px;

            height: 54px;

            flex-shrink: 0;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 11px;

            background: #eff6ff;

            color: #172554;

            font-size: 22px;

        }


        .marks-text h3 {

            margin: 0 0 5px;

            color: #172554;

            font-size: 18px;

        }


        .marks-text p {

            margin: 0;

            color: #64748b;

            font-size: 13px;

            line-height: 1.5;

        }


        .marks-button {

            display: inline-flex;

            align-items: center;

            gap: 8px;

            flex-shrink: 0;

            background: #172554;

            color: #ffffff;

            text-decoration: none;

            padding: 11px 19px;

            border-radius: 7px;

            font-size: 13px;

            font-weight: 600;

            transition:
                background 0.2s ease,
                transform 0.2s ease;

        }


        .marks-button:hover {

            background: #1e3a8a;

            transform: translateY(-1px);

        }


        /* =================================================
           FOOTER NOTE
        ================================================= */

        .dashboard-note {

            margin-top: 20px;

            text-align: center;

            color: #94a3b8;

            font-size: 11px;

        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 700px) {

            .student-content {

                padding:
                    22px 15px 35px;

            }


            .welcome-card {

                padding: 25px 22px;

            }


            .welcome-card h2 {

                font-size: 23px;

            }


            .academic-row {

                grid-template-columns: 1fr;

                gap: 5px;

                padding: 13px 0;

            }


            .marks-card {

                flex-direction: column;

                align-items: flex-start;

            }


            .marks-button {

                width: 100%;

                justify-content: center;

            }

        }

    </style>

</head>


<body>


<div class="student-page">


    <?php

    include "../includes/student_header.php";

    ?>


    <main class="student-content">


        <!-- =================================================
             BREADCRUMB
        ================================================= -->

        <div class="page-breadcrumb">

            <span>
                Student
            </span>

            <span>
                /
            </span>

            <span>
                Dashboard
            </span>

        </div>


        <!-- =================================================
             WELCOME
        ================================================= -->

        <section class="welcome-card">

            <div class="welcome-content">

                <p class="welcome-small">
                    Student Portal
                </p>


                <h2>

                    Welcome,

                    <?php

                    echo htmlspecialchars(
                        $student["full_name"]
                    );

                    ?>

                </h2>


                <p>

                    Welcome to your student portal.
                    View your academic information
                    and examination marks from here.

                </p>

            </div>

        </section>


        <!-- =================================================
             ACADEMIC INFORMATION
        ================================================= -->

        <div class="section-heading">

            <h3>
                Academic Information
            </h3>

            <span>
                Current student details
            </span>

        </div>


        <div class="academic-card">


            <!-- REGISTRATION NUMBER -->

            <div class="academic-row">

                <div class="academic-label">
                    Registration Number
                </div>

                <div class="academic-value">

                    <?php

                    echo htmlspecialchars(
                        $student["registration_no"]
                        ?? "-"
                    );

                    ?>

                </div>

            </div>


            <!-- ROLL NUMBER -->

            <div class="academic-row">

                <div class="academic-label">
                    Roll Number
                </div>

                <div class="academic-value">

                    <?php

                    echo htmlspecialchars(
                        $student["roll_no"]
                        ?? "-"
                    );

                    ?>

                </div>

            </div>


            <!-- COURSE -->

            <div class="academic-row">

                <div class="academic-label">
                    Course
                </div>

                <div class="academic-value">

                    <?php

                    $course_code =
                        $student["course_code"]
                        ?? "";

                    $course_name =
                        $student["course_name"]
                        ?? "";


                    if ($course_code !== "") {

                        echo htmlspecialchars(
                            $course_code
                            . " - "
                            . $course_name
                        );

                    } else {

                        echo htmlspecialchars(
                            $course_name ?: "-"
                        );

                    }

                    ?>

                </div>

            </div>


            <!-- SEMESTER -->

            <div class="academic-row">

                <div class="academic-label">
                    Semester
                </div>

                <div class="academic-value">

                    Semester

                    <?php

                    echo htmlspecialchars(
                        (string)
                        (
                            $student["semester"]
                            ?? "-"
                        )
                    );

                    ?>

                </div>

            </div>


            <!-- STUDENT STATUS -->

            <div class="academic-row">

                <div class="academic-label">
                    Student Status
                </div>

                <div class="academic-value">

                    <span class="status-badge">

                        <span class="status-dot"></span>

                        <?php

                        echo htmlspecialchars(
                            $student["student_status"]
                            ?? "Active"
                        );

                        ?>

                    </span>

                </div>

            </div>


        </div>


        <!-- =================================================
             MARKS
        ================================================= -->

        <div class="section-heading">

            <h3>
                Academic Results
            </h3>

            <span>
                Examination performance
            </span>

        </div>


        <section class="marks-card">


            <div class="marks-left">


                <div class="marks-icon">
                    📊
                </div>


                <div class="marks-text">

                    <h3>
                        Examination Marks
                    </h3>

                    <p>

                        View IA, preparatory,
                        assignment and project marks
                        uploaded by your lecturers.

                    </p>

                </div>


            </div>


            <a
                href="marks.php"
                class="marks-button"
            >

                View Marks

                <span>
                    →
                </span>

            </a>


        </section>


        <div class="dashboard-note">

            Your examination marks will appear
            automatically after they are uploaded
            by your lecturer.

        </div>


    </main>


</div>


</body>

</html>