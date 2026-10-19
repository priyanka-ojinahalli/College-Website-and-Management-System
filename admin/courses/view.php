<?php

session_start();

/* =====================================================
   ADMIN LOGIN CHECK
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
   DATABASE
===================================================== */

require_once "../../config/database.php";


/* =====================================================
   GET COURSE ID
===================================================== */

$course_id = (int)($_GET["id"] ?? 0);


if ($course_id <= 0) {

    header("Location: index.php");
    exit();

}


/* =====================================================
   FETCH COURSE
===================================================== */

$stmt = $conn->prepare("
    SELECT
        id,
        course_name,
        course_code
    FROM courses
    WHERE id = ?
    LIMIT 1
");


$stmt->bind_param(
    "i",
    $course_id
);


$stmt->execute();


$result = $stmt->get_result();


if ($result->num_rows !== 1) {

    $stmt->close();

    header("Location: index.php");
    exit();

}


$course = $result->fetch_assoc();


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

    <title>
        View Course - Admin
    </title>


    <link
        rel="stylesheet"
        href="../../css/admin.css?v=22"
    >


    <style>

        .page-header {

            margin-bottom: 20px;

        }


        .course-view-card {

            background: #ffffff;

            max-width: 850px;

            padding: 30px;

            margin-top: 25px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

        }


        .course-title {

            display: flex;

            align-items: center;

            gap: 15px;

            padding-bottom: 20px;

            margin-bottom: 25px;

            border-bottom:
                1px solid #e5e7eb;

        }


        .course-icon {

            width: 55px;

            height: 55px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #eff6ff;

            color: #172554;

            border-radius: 10px;

            font-size: 20px;

            font-weight: bold;

        }


        .course-title h3 {

            margin: 0;

            color: #172554;

            font-size: 23px;

        }


        .course-title p {

            margin: 5px 0 0;

            color: #6b7280;

        }


        .details-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;

        }


        .detail-box {

            background: #f8fafc;

            border: 1px solid #e5e7eb;

            border-radius: 7px;

            padding: 18px;

        }


        .detail-label {

            display: block;

            font-size: 13px;

            color: #6b7280;

            margin-bottom: 6px;

        }


        .detail-value {

            font-size: 16px;

            font-weight: 600;

            color: #172554;

        }


        .course-code {

            display: inline-block;

            background: #dbeafe;

            color: #1e40af;

            padding: 5px 10px;

            border-radius: 5px;

            font-weight: 700;

        }


        .form-actions {

            display: flex;

            gap: 10px;

            margin-top: 30px;

            padding-top: 20px;

            border-top:
                1px solid #e5e7eb;

        }


        .btn-primary,
        .btn-secondary {

            display: inline-block;

            padding: 10px 17px;

            border-radius: 5px;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;

        }


        .btn-primary {

            background: #172554;

            color: #ffffff;

        }


        .btn-primary:hover {

            background: #1e3a8a;

        }


        .btn-secondary {

            background: #e5e7eb;

            color: #374151;

        }


        .btn-secondary:hover {

            background: #d1d5db;

        }


        @media (max-width: 700px) {

            .details-grid {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>


<body>


<div class="dashboard">


    <!-- =================================================
         SIDEBAR
    ================================================= -->

    <?php

    include "../../includes/admin_sidebar.php";

    ?>


    <!-- =================================================
         MAIN CONTENT
    ================================================= -->

    <div class="main-content">


        <!-- HEADER -->

        <?php

        include "../../includes/admin_header.php";

        ?>


        <div class="content">


            <!-- PAGE HEADER -->

            <div class="page-header">

                <h2>
                    Course Details
                </h2>

                <p class="welcome">

                    View complete information about
                    this course.

                </p>

            </div>


            <!-- COURSE CARD -->

            <div class="course-view-card">


                <!-- TITLE -->

                <div class="course-title">


                    <div class="course-icon">

                        <?php

                        echo htmlspecialchars(
                            strtoupper(
                                substr(
                                    $course[
                                        "course_code"
                                    ],
                                    0,
                                    3
                                )
                            )
                        );

                        ?>

                    </div>


                    <div>

                        <h3>

                            <?php

                            echo htmlspecialchars(
                                $course[
                                    "course_name"
                                ]
                            );

                            ?>

                        </h3>


                        <p>

                            Course Information

                        </p>

                    </div>


                </div>


                <!-- DETAILS -->

                <div class="details-grid">


                    <!-- COURSE CODE -->

                    <div class="detail-box">

                        <span class="detail-label">

                            Course Code

                        </span>


                        <span class="course-code">

                            <?php

                            echo htmlspecialchars(
                                $course[
                                    "course_code"
                                ]
                            );

                            ?>

                        </span>

                    </div>


                    <!-- COURSE NAME -->

                    <div class="detail-box">

                        <span class="detail-label">

                            Course Name

                        </span>


                        <span class="detail-value">

                            <?php

                            echo htmlspecialchars(
                                $course[
                                    "course_name"
                                ]
                            );

                            ?>

                        </span>

                    </div>


                    <!-- COURSE ID -->

                    <div class="detail-box">

                        <span class="detail-label">

                            Course ID

                        </span>


                        <span class="detail-value">

                            <?php

                            echo (int)$course["id"];

                            ?>

                        </span>

                    </div>


                </div>


                <!-- ACTIONS -->

                <div class="form-actions">


                    <a
                        href="edit.php?id=<?php
                            echo (int)$course["id"];
                        ?>"
                        class="btn-primary"
                    >

                        Edit Course

                    </a>


                    <a
                        href="index.php"
                        class="btn-secondary"
                    >

                        Back to Courses

                    </a>


                </div>


            </div>


        </div>


    </div>


</div>


</body>

</html>