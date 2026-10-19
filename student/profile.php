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
        s.*,
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
    die("Student profile not found.");
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

    <title>Student Profile</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #111827;
        }


        .student-page {
            min-height: 100vh;
        }


        /* =================================================
           PROFILE CONTENT
        ================================================= */

        .profile-content {
            padding: 30px;
            max-width: 1100px;
            margin: auto;
        }


        .profile-title {
            margin-bottom: 20px;
        }


        .profile-title h2 {
            margin: 0;
            color: #172554;
            font-size: 24px;
        }


        .profile-title p {
            margin: 5px 0 0;
            color: #6b7280;
            font-size: 14px;
        }


        /* =================================================
           PROFILE CARD
        ================================================= */

        .profile-card {
            background: #ffffff;
            border-radius: 10px;
            padding: 25px;
            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);
        }


        .section-title {
            margin: 0 0 18px;
            color: #172554;
            font-size: 18px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 10px;
        }


        .profile-grid {
            display: grid;
            grid-template-columns:
                repeat(2, 1fr);
            gap: 16px 25px;
        }


        .profile-item {
            padding: 12px;
            background: #f8fafc;
            border-radius: 6px;
        }


        .profile-label {
            display: block;
            color: #6b7280;
            font-size: 12px;
            margin-bottom: 5px;
        }


        .profile-value {
            color: #111827;
            font-size: 14px;
            font-weight: 600;
            word-break: break-word;
        }


        .full-width {
            grid-column: 1 / -1;
        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 800px) {

            .profile-content {
                padding: 20px 15px;
            }

            .profile-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: auto;
            }

        }

    </style>

</head>


<body>


<div class="student-page">


    <?php
    include "../includes/student_header.php";
    ?>


    <main class="profile-content">


        <div class="profile-title">

            <h2>
                My Profile
            </h2>

            <p>
                View your registered college information.
            </p>

        </div>


        <div class="profile-card">


            <!-- PERSONAL INFORMATION -->

            <h3 class="section-title">
                Personal Information
            </h3>


            <div class="profile-grid">


                <div class="profile-item">

                    <span class="profile-label">
                        Full Name
                    </span>

                    <span class="profile-value">

                        <?php
                        echo htmlspecialchars(
                            $student["full_name"] ?? "-"
                        );
                        ?>

                    </span>

                </div>


                <div class="profile-item">

                    <span class="profile-label">
                        Student ID
                    </span>

                    <span class="profile-value">

                        <?php
                        echo htmlspecialchars(
                            $student["student_id"] ?? "-"
                        );
                        ?>

                    </span>

                </div>


                <div class="profile-item">

                    <span class="profile-label">
                        Registration Number
                    </span>

                    <span class="profile-value">

                        <?php
                        echo htmlspecialchars(
                            $student["registration_no"] ?? "-"
                        );
                        ?>

                    </span>

                </div>


                <div class="profile-item">

                    <span class="profile-label">
                        Roll Number
                    </span>

                    <span class="profile-value">

                        <?php
                        echo htmlspecialchars(
                            $student["roll_no"] ?? "-"
                        );
                        ?>

                    </span>

                </div>


                <div class="profile-item">

                    <span class="profile-label">
                        Gender
                    </span>

                    <span class="profile-value">

                        <?php
                        echo htmlspecialchars(
                            $student["gender"] ?? "-"
                        );
                        ?>

                    </span>

                </div>


                <div class="profile-item">

                    <span class="profile-label">
                        Date of Birth
                    </span>

                    <span class="profile-value">

                        <?php
                        echo htmlspecialchars(
                            $student["date_of_birth"] ?? "-"
                        );
                        ?>

                    </span>

                </div>


            </div>


            <br>


            <!-- CONTACT INFORMATION -->

            <h3 class="section-title">
                Contact Information
            </h3>


            <div class="profile-grid">


                <div class="profile-item">

                    <span class="profile-label">
                        Email
                    </span>

                    <span class="profile-value">

                        <?php
                        echo htmlspecialchars(
                            $student["email"] ?? "-"
                        );
                        ?>

                    </span>

                </div>


                <div class="profile-item">

                    <span class="profile-label">
                        Phone
                    </span>

                    <span class="profile-value">

                        <?php
                        echo htmlspecialchars(
                            $student["phone"] ?? "-"
                        );
                        ?>

                    </span>

                </div>


                <div class="profile-item full-width">

                    <span class="profile-label">
                        Address
                    </span>

                    <span class="profile-value">

                        <?php
                        echo nl2br(
                            htmlspecialchars(
                                $student["address"] ?? "-"
                            )
                        );
                        ?>

                    </span>

                </div>


            </div>


            <br>


            <!-- FAMILY INFORMATION -->

            <h3 class="section-title">
                Family Information
            </h3>


            <div class="profile-grid">


                <div class="profile-item">

                    <span class="profile-label">
                        Father's Name
                    </span>

                    <span class="profile-value">

                        <?php
                        echo htmlspecialchars(
                            $student["father_name"] ?? "-"
                        );
                        ?>

                    </span>

                </div>


                <div class="profile-item">

                    <span class="profile-label">
                        Mother's Name
                    </span>

                    <span class="profile-value">

                        <?php
                        echo htmlspecialchars(
                            $student["mother_name"] ?? "-"
                        );
                        ?>

                    </span>

                </div>


                <div class="profile-item">

                    <span class="profile-label">
                        Guardian Phone
                    </span>

                    <span class="profile-value">

                        <?php
                        echo htmlspecialchars(
                            $student["guardian_phone"] ?? "-"
                        );
                        ?>

                    </span>

                </div>


            </div>


            <br>


            <!-- ACADEMIC INFORMATION -->

            <h3 class="section-title">
                Academic Information
            </h3>


            <div class="profile-grid">


                <div class="profile-item">

                    <span class="profile-label">
                        Course
                    </span>

                    <span class="profile-value">

                        <?php

                        $course_code =
                            $student["course_code"] ?? "";

                        $course_name =
                            $student["course_name"] ?? "";

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

                    </span>

                </div>


                <div class="profile-item">

                    <span class="profile-label">
                        Semester
                    </span>

                    <span class="profile-value">

                        <?php
                        echo htmlspecialchars(
                            (string)
                            ($student["semester"] ?? "-")
                        );
                        ?>

                    </span>

                </div>


                <div class="profile-item">

                    <span class="profile-label">
                        Admission Year
                    </span>

                    <span class="profile-value">

                        <?php
                        echo htmlspecialchars(
                            (string)
                            ($student["admission_year"] ?? "-")
                        );
                        ?>

                    </span>

                </div>


                <div class="profile-item">

                    <span class="profile-label">
                        ABC ID
                    </span>

                    <span class="profile-value">

                        <?php
                        echo htmlspecialchars(
                            $student["abc_id"] ?? "-"
                        );
                        ?>

                    </span>

                </div>


                <div class="profile-item">

                    <span class="profile-label">
                        Student Status
                    </span>

                    <span class="profile-value">

                        <?php
                        echo htmlspecialchars(
                            $student["student_status"] ?? "-"
                        );
                        ?>

                    </span>

                </div>


            </div>


        </div>


    </main>


</div>


</body>

</html>