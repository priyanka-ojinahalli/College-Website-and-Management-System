<?php

session_start();

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "lecturer"
) {
    header("Location: ../../auth/login.php");
    exit();
}

require_once "../../config/database.php";


/* =====================================================
   GET LOGGED-IN LECTURER
===================================================== */

$user_id = (int) $_SESSION["user_id"];

$lecturer_id = 0;
$lecturer_name = "Lecturer";


$stmt = $conn->prepare("
    SELECT id, full_name
    FROM lecturers
    WHERE user_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $lecturer = $result->fetch_assoc();

    $lecturer_id = (int) $lecturer["id"];

    $lecturer_name = $lecturer["full_name"];
}

$stmt->close();


/* =====================================================
   GET ASSIGNED SUBJECTS
===================================================== */

$subjects = [];

if ($lecturer_id > 0) {

    $stmt = $conn->prepare("
        SELECT
            sub.id,
            sub.subject_name,
            sub.subject_code,
            sub.semester,
            c.course_name,
            c.course_code
        FROM subjects sub
        LEFT JOIN courses c
            ON c.id = sub.course_id
        WHERE sub.lecturer_id = ?
        ORDER BY
            c.course_name ASC,
            sub.semester ASC,
            sub.subject_name ASC
    ");

    if (!$stmt) {
        die("Database error: " . $conn->error);
    }

    $stmt->bind_param(
        "i",
        $lecturer_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $subjects[] = $row;

    }

    $stmt->close();
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

    <title>My Subjects - Lecturer</title>


    <link
        rel="stylesheet"
        href="../../css/admin.css?v=10"
    >


    <style>

        /* =================================================
           HEADER
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
           PAGE CONTENT
        ================================================= */

        .page-content {

            padding: 25px;

        }


        .page-title {

            margin: 0;

            color: #111827;

        }


        .page-description {

            margin-top: 6px;

            color: #6b7280;

        }


        /* =================================================
           SUBJECT GRID
        ================================================= */

        .subject-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;

            margin-top: 25px;

        }


        /* =================================================
           SUBJECT CARD
        ================================================= */

        .subject-card {

            background: #ffffff;

            border-radius: 10px;

            padding: 22px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

            border: 1px solid #e5e7eb;

        }


        .subject-card h3 {

            margin: 0 0 8px;

            color: #172554;

            font-size: 19px;

        }


        .subject-code {

            display: inline-block;

            background: #eff6ff;

            color: #1e3a8a;

            padding: 4px 8px;

            border-radius: 4px;

            font-size: 12px;

            font-weight: 600;

            margin-bottom: 15px;

        }


        .subject-details {

            line-height: 1.8;

            color: #374151;

            font-size: 14px;

        }


        .subject-details strong {

            color: #111827;

        }


        /* =================================================
           ACTION
        ================================================= */

        .subject-action {

            margin-top: 18px;

            padding-top: 15px;

            border-top:
                1px solid #e5e7eb;

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

        .no-subjects {

            background: #ffffff;

            padding: 35px;

            margin-top: 25px;

            text-align: center;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

            color: #6b7280;

        }


        .no-subjects h3 {

            color: #374151;

            margin-bottom: 8px;

        }


        .no-subjects p {

            margin: 0;

        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 1000px) {

            .subject-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        @media (max-width: 650px) {

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
         SIDEBAR
    ================================================= -->

    <?php

    include "../../includes/lecturer_sidebar.php";

    ?>


    <!-- =================================================
         MAIN CONTENT
    ================================================= -->

    <div class="main-content">


        <!-- =================================================
             HEADER
        ================================================= -->

        <header class="admin-header">


            <div class="college-header">

                <h1>
                    Smt. Sharadamma V. Kotbal
                </h1>

                <p>
                    BBA, BCA &amp; B.Com College,
                    Koppal, Karnataka
                </p>

            </div>


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
             CONTENT
        ================================================= -->

        <div class="page-content">


            <h2 class="page-title">
                My Subjects
            </h2>


            <p class="page-description">

                Welcome,

                <?php

                echo htmlspecialchars(
                    $lecturer_name
                );

                ?>.

                Here are the subjects assigned to you.

            </p>


            <?php if (count($subjects) > 0): ?>


                <div class="subject-grid">


                    <?php foreach ($subjects as $subject): ?>


                        <div class="subject-card">


                            <!-- SUBJECT NAME -->

                            <h3>

                                <?php

                                echo htmlspecialchars(
                                    $subject["subject_name"]
                                );

                                ?>

                            </h3>


                            <!-- SUBJECT CODE -->

                            <?php if (
                                !empty(
                                    $subject["subject_code"]
                                )
                            ): ?>

                                <div class="subject-code">

                                    <?php

                                    echo htmlspecialchars(
                                        $subject["subject_code"]
                                    );

                                    ?>

                                </div>

                            <?php endif; ?>


                            <!-- SUBJECT DETAILS -->

                            <div class="subject-details">


                                <div>

                                    <strong>
                                        Course:
                                    </strong>

                                    <?php

                                    echo htmlspecialchars(
                                        $subject["course_code"]
                                        . " - "
                                        . $subject["course_name"]
                                    );

                                    ?>

                                </div>


                                <div>

                                    <strong>
                                        Semester:
                                    </strong>

                                    <?php

                                    echo (int)
                                        $subject["semester"];

                                    ?>

                                </div>


                            </div>


                            <!-- ACTION -->

                            <div class="subject-action">

                                <a
                                    href="students.php?subject_id=<?php echo (int) $subject["id"]; ?>"
                                    class="btn-primary"
                                >
                                    View Students
                                </a>

                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <div class="no-subjects">

                    <h3>
                        No Subjects Assigned
                    </h3>

                    <p>
                        No subjects have been assigned
                        to you yet.
                        Please contact the administrator.
                    </p>

                </div>


            <?php endif; ?>


        </div>


    </div>


</div>


</body>

</html>