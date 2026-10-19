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
   GET STUDENT ID
===================================================== */

$id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


if ($id <= 0) {

    header("Location: index.php");
    exit();

}


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
        ON s.course_id = c.id

    WHERE s.id = ?

    LIMIT 1

");


$stmt->bind_param("i", $id);

$stmt->execute();

$result = $stmt->get_result();

$student = $result->fetch_assoc();

$stmt->close();


/* =====================================================
   STUDENT NOT FOUND
===================================================== */

if (!$student) {

    header("Location: index.php");
    exit();

}


/* =====================================================
   CALCULATE YEAR
===================================================== */

$semester = (int) (
    $student["semester"] ?? 0
);


if ($semester == 1 || $semester == 2) {

    $year = "1st Year";

} elseif ($semester == 3 || $semester == 4) {

    $year = "2nd Year";

} elseif ($semester == 5 || $semester == 6) {

    $year = "3rd Year";

} else {

    $year = "-";

}


/* =====================================================
   COURSE DISPLAY
===================================================== */

$course_code =
    $student["course_code"] ?? "";

$course_name =
    $student["course_name"] ?? "";


if ($course_code !== "") {

    $display_course = $course_code;

} elseif ($course_name !== "") {

    $display_course = $course_name;

} else {

    $display_course = "-";

}


/* =====================================================
   STATUS
===================================================== */

$status =
    $student["student_status"] ?? "Active";

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
        View Student - Admin
    </title>


    <link
        rel="stylesheet"
        href="../../css/admin.css?v=7"
    >


    <style>

        /* =================================================
           STUDENT PROFILE
        ================================================= */

        .student-profile-card {

            background: #ffffff;

            padding: 30px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

            max-width: 950px;

            margin-top: 25px;

        }


        .student-profile-header {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            padding-bottom: 25px;

            border-bottom:
                1px solid #e5e7eb;

        }


        .student-profile-header h2 {

            margin: 0;

            font-size: 25px;

            color: #172554;

        }


        .student-profile-header p {

            margin-top: 6px;

            color: #666;

        }


        /* =================================================
           PROFILE GRID
        ================================================= */

        .student-details {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;

            margin-top: 25px;

        }


        .detail-box {

            padding: 16px;

            background: #f8fafc;

            border: 1px solid #e5e7eb;

            border-radius: 7px;

        }


        .detail-label {

            font-size: 12px;

            color: #6b7280;

            margin-bottom: 6px;

            font-weight: bold;

            text-transform:
                uppercase;

        }


        .detail-value {

            font-size: 15px;

            color: #111827;

            word-break: break-word;

        }


        /* =================================================
           STATUS
        ================================================= */

        .status-active {

            color: #166534;

            font-weight: bold;

        }


        .status-graduated {

            color: #1d4ed8;

            font-weight: bold;

        }


        .status-discontinued {

            color: #b91c1c;

            font-weight: bold;

        }


        /* =================================================
           BUTTONS
        ================================================= */

        .profile-actions {

            display: flex;

            gap: 10px;

            margin-top: 30px;

            padding-top: 20px;

            border-top:
                1px solid #e5e7eb;

        }


        .btn-back {

            display: inline-block;

            padding: 11px 20px;

            background: #e5e7eb;

            color: #111827;

            text-decoration: none;

            border-radius: 5px;

        }


        .btn-back:hover {

            background: #d1d5db;

        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 700px) {

            .student-details {

                grid-template-columns: 1fr;

            }


            .student-profile-header {

                display: block;

            }


            .student-profile-header .btn-primary {

                display: inline-block;

                margin-top: 15px;

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


            <!-- PAGE TITLE -->

            <div>

                <h2>
                    Student Profile
                </h2>

                <p class="welcome">
                    View complete student information.
                </p>

            </div>


            <!-- =================================================
                 PROFILE CARD
            ================================================= -->

            <div class="student-profile-card">


                <div class="student-profile-header">


                    <div>

                        <h2>

                            <?php
                            echo htmlspecialchars(
                                $student["full_name"] ?? "-"
                            );
                            ?>

                        </h2>


                        <p>

                            Registration No:
                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $student[
                                        "registration_no"
                                    ] ?? "-"
                                );
                                ?>

                            </strong>

                        </p>

                    </div>


                    <a
                        href="edit.php?id=<?php
                            echo $id;
                        ?>"
                        class="btn-primary"
                    >

                        Edit Student

                    </a>


                </div>


                <!-- =================================================
                     STUDENT DETAILS
                ================================================= -->

                <div class="student-details">


                    <!-- REGISTRATION NUMBER -->

                    <div class="detail-box">

                        <div class="detail-label">
                            Registration Number
                        </div>

                        <div class="detail-value">

                            <?php
                            echo htmlspecialchars(
                                $student[
                                    "registration_no"
                                ] ?? "-"
                            );
                            ?>

                        </div>

                    </div>


                    <!-- STUDENT ID -->

                    <div class="detail-box">

                        <div class="detail-label">
                            Student ID
                        </div>

                        <div class="detail-value">

                            <?php
                            echo htmlspecialchars(
                                $student[
                                    "student_id"
                                ] ?? "-"
                            );
                            ?>

                        </div>

                    </div>


                    <!-- FULL NAME -->

                    <div class="detail-box">

                        <div class="detail-label">
                            Full Name
                        </div>

                        <div class="detail-value">

                            <?php
                            echo htmlspecialchars(
                                $student[
                                    "full_name"
                                ] ?? "-"
                            );
                            ?>

                        </div>

                    </div>


                    <!-- GENDER -->

                    <div class="detail-box">

                        <div class="detail-label">
                            Gender
                        </div>

                        <div class="detail-value">

                            <?php
                            echo htmlspecialchars(
                                $student[
                                    "gender"
                                ] ?? "-"
                            );
                            ?>

                        </div>

                    </div>


                    <!-- EMAIL -->

                    <div class="detail-box">

                        <div class="detail-label">
                            Email
                        </div>

                        <div class="detail-value">

                            <?php
                            echo htmlspecialchars(
                                $student[
                                    "email"
                                ] ?? "-"
                            );
                            ?>

                        </div>

                    </div>


                    <!-- PHONE -->

                    <div class="detail-box">

                        <div class="detail-label">
                            Phone
                        </div>

                        <div class="detail-value">

                            <?php
                            echo htmlspecialchars(
                                $student[
                                    "phone"
                                ] ?? "-"
                            );
                            ?>

                        </div>

                    </div>


                    <!-- COURSE -->

                    <div class="detail-box">

                        <div class="detail-label">
                            Course
                        </div>

                        <div class="detail-value">

                            <?php
                            echo htmlspecialchars(
                                $display_course
                            );
                            ?>

                        </div>

                    </div>


                    <!-- YEAR -->

                    <div class="detail-box">

                        <div class="detail-label">
                            Year
                        </div>

                        <div class="detail-value">

                            <?php
                            echo htmlspecialchars(
                                $year
                            );
                            ?>

                        </div>

                    </div>


                    <!-- SEMESTER -->

                    <div class="detail-box">

                        <div class="detail-label">
                            Semester
                        </div>

                        <div class="detail-value">

                            <?php

                            if ($semester > 0) {

                                echo
                                    "Semester " .
                                    $semester;

                            } else {

                                echo "-";

                            }

                            ?>

                        </div>

                    </div>


                    <!-- ADMISSION YEAR -->

                    <div class="detail-box">

                        <div class="detail-label">
                            Admission Year
                        </div>

                        <div class="detail-value">

                            <?php
                            echo htmlspecialchars(
                                $student[
                                    "admission_year"
                                ] ?? "-"
                            );
                            ?>

                        </div>

                    </div>


                    <!-- STATUS -->

                    <div class="detail-box">

                        <div class="detail-label">
                            Student Status
                        </div>

                        <div class="detail-value">


                            <?php

                            $status_class = "";

                            if ($status === "Active") {

                                $status_class =
                                    "status-active";

                            } elseif (
                                $status === "Graduated"
                            ) {

                                $status_class =
                                    "status-graduated";

                            } elseif (
                                $status === "Discontinued"
                            ) {

                                $status_class =
                                    "status-discontinued";

                            }

                            ?>


                            <span
                                class="<?php
                                    echo $status_class;
                                ?>"
                            >

                                <?php
                                echo htmlspecialchars(
                                    $status
                                );
                                ?>

                            </span>


                        </div>

                    </div>


                </div>


                <!-- =================================================
                     ACTIONS
                ================================================= -->

                <div class="profile-actions">


                    <a
                        href="index.php"
                        class="btn-back"
                    >

                        ← Back to Students

                    </a>


                    <a
                        href="edit.php?id=<?php
                            echo $id;
                        ?>"
                        class="btn-primary"
                    >

                        Edit Student

                    </a>


                </div>


            </div>


        </div>


    </div>


</div>


</body>

</html>