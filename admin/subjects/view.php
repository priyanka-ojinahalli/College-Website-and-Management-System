<?php

session_start();

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../../auth/login.php");
    exit();
}

require_once "../../config/database.php";

$subject_id = (int)($_GET["id"] ?? 0);

if ($subject_id <= 0) {
    header("Location: index.php");
    exit();
}


/* =====================================================
   GET SUBJECT DETAILS
===================================================== */

$stmt = $conn->prepare("
    SELECT
        subjects.id,
        subjects.subject_name,
        subjects.subject_code,
        subjects.semester,
        subjects.lecturer_id,

        courses.course_name,
        courses.course_code,

        lecturers.full_name AS lecturer_name,
        lecturers.lecturer_id AS lecturer_code,
        lecturers.email AS lecturer_email,
        lecturers.phone AS lecturer_phone

    FROM subjects

    LEFT JOIN courses
        ON subjects.course_id = courses.id

    LEFT JOIN lecturers
        ON subjects.lecturer_id = lecturers.id

    WHERE subjects.id = ?

    LIMIT 1
");

$stmt->bind_param(
    "i",
    $subject_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    $stmt->close();

    header("Location: index.php");
    exit();
}

$subject = $result->fetch_assoc();

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
        View Subject - Admin
    </title>


    <link
        rel="stylesheet"
        href="../../css/admin.css?v=10"
    >


    <style>

        .page-header {

            margin-bottom: 25px;

        }


        .page-header h2 {

            margin-bottom: 6px;

        }


        .page-header p {

            color: #6b7280;

            margin: 0;

        }


        .subject-view-card {

            background: #ffffff;

            max-width: 900px;

            border-radius: 10px;

            padding: 30px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

        }


        .subject-title {

            background: #eff6ff;

            border-left:
                5px solid #172554;

            padding: 20px;

            border-radius: 6px;

            margin-bottom: 25px;

        }


        .subject-title h3 {

            margin: 0;

            color: #172554;

            font-size: 24px;

        }


        .subject-title p {

            margin: 7px 0 0;

            color: #6b7280;

        }


        .details-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;

        }


        .detail-box {

            border:
                1px solid #e5e7eb;

            border-radius: 7px;

            padding: 16px;

            background: #fafafa;

        }


        .detail-box.full {

            grid-column: 1 / -1;

        }


        .detail-label {

            font-size: 12px;

            color: #6b7280;

            text-transform: uppercase;

            font-weight: 600;

            margin-bottom: 6px;

        }


        .detail-value {

            font-size: 16px;

            color: #111827;

            font-weight: 500;

        }


        .subject-code {

            color: #172554;

            font-weight: bold;

        }


        .course-badge {

            display: inline-block;

            background: #dbeafe;

            color: #1d4ed8;

            padding: 6px 10px;

            border-radius: 5px;

            font-weight: 600;

            font-size: 13px;

        }


        .semester-badge {

            display: inline-block;

            background: #f3f4f6;

            color: #374151;

            padding: 6px 10px;

            border-radius: 5px;

            font-weight: 600;

            font-size: 13px;

        }


        .lecturer-box {

            background: #f0fdf4;

            border:
                1px solid #bbf7d0;

        }


        .not-assigned {

            color: #dc2626;

            font-style: italic;

        }


        .lecturer-details {

            margin-top: 10px;

            line-height: 1.7;

            color: #4b5563;

            font-size: 14px;

        }


        .action-buttons {

            display: flex;

            gap: 10px;

            margin-top: 30px;

            padding-top: 20px;

            border-top:
                1px solid #e5e7eb;

        }


        .btn {

            display: inline-block;

            padding: 10px 17px;

            border-radius: 6px;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;

        }


        .btn-edit {

            background: #172554;

            color: #ffffff;

        }


        .btn-edit:hover {

            background: #1e3a8a;

        }


        .btn-back {

            background: #e5e7eb;

            color: #374151;

        }


        .btn-back:hover {

            background: #d1d5db;

        }


        @media (max-width: 700px) {

            .details-grid {

                grid-template-columns: 1fr;

            }

            .detail-box.full {

                grid-column: auto;

            }

        }

    </style>

</head>


<body>


<div class="dashboard">


    <!-- SIDEBAR -->

    <?php

    include "../../includes/admin_sidebar.php";

    ?>


    <!-- MAIN CONTENT -->

    <div class="main-content">


        <!-- HEADER -->

        <?php

        include "../../includes/admin_header.php";

        ?>


        <div class="content">


            <div class="page-header">

                <h2>
                    Subject Details
                </h2>

                <p>
                    View complete information about this subject.
                </p>

            </div>


            <div class="subject-view-card">


                <!-- SUBJECT TITLE -->

                <div class="subject-title">

                    <h3>

                        <?php

                        echo htmlspecialchars(
                            $subject["subject_name"]
                        );

                        ?>

                    </h3>


                    <?php if (
                        !empty($subject["subject_code"])
                    ): ?>

                        <p>

                            Subject Code:

                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $subject["subject_code"]
                                );

                                ?>

                            </strong>

                        </p>

                    <?php endif; ?>


                </div>


                <!-- DETAILS -->

                <div class="details-grid">


                    <!-- SUBJECT CODE -->

                    <div class="detail-box">

                        <div class="detail-label">

                            Subject Code

                        </div>

                        <div class="detail-value subject-code">

                            <?php

                            echo htmlspecialchars(

                                $subject[
                                    "subject_code"
                                ] ?: "Not Provided"

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

                            <span class="course-badge">

                                <?php

                                echo htmlspecialchars(

                                    (
                                        $subject[
                                            "course_code"
                                        ]
                                        ? $subject[
                                            "course_code"
                                        ] . " - "
                                        : ""
                                    )

                                    .

                                    $subject[
                                        "course_name"
                                    ]

                                );

                                ?>

                            </span>

                        </div>

                    </div>


                    <!-- SEMESTER -->

                    <div class="detail-box">

                        <div class="detail-label">

                            Semester

                        </div>

                        <div class="detail-value">

                            <span class="semester-badge">

                                Semester

                                <?php

                                echo (int)
                                    $subject[
                                        "semester"
                                    ];

                                ?>

                            </span>

                        </div>

                    </div>


                    <!-- LECTURER -->

                    <div
                        class="detail-box lecturer-box"
                    >

                        <div class="detail-label">

                            Assigned Lecturer

                        </div>


                        <?php if (
                            !empty(
                                $subject[
                                    "lecturer_name"
                                ]
                            )
                        ): ?>


                            <div class="detail-value">

                                <?php

                                echo htmlspecialchars(
                                    $subject[
                                        "lecturer_name"
                                    ]
                                );

                                ?>

                            </div>


                            <div class="lecturer-details">


                                <?php if (
                                    !empty(
                                        $subject[
                                            "lecturer_code"
                                        ]
                                    )
                                ): ?>

                                    <strong>
                                        Lecturer ID:
                                    </strong>

                                    <?php

                                    echo htmlspecialchars(
                                        $subject[
                                            "lecturer_code"
                                        ]
                                    );

                                    ?>

                                    <br>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $subject[
                                            "lecturer_email"
                                        ]
                                    )
                                ): ?>

                                    <strong>
                                        Email:
                                    </strong>

                                    <?php

                                    echo htmlspecialchars(
                                        $subject[
                                            "lecturer_email"
                                        ]
                                    );

                                    ?>

                                    <br>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $subject[
                                            "lecturer_phone"
                                        ]
                                    )
                                ): ?>

                                    <strong>
                                        Phone:
                                    </strong>

                                    <?php

                                    echo htmlspecialchars(
                                        $subject[
                                            "lecturer_phone"
                                        ]
                                    );

                                    ?>

                                <?php endif; ?>


                            </div>


                        <?php else: ?>


                            <div class="detail-value not-assigned">

                                No lecturer assigned

                            </div>


                            <div class="lecturer-details">

                                You can assign a lecturer
                                by editing this subject.

                            </div>


                        <?php endif; ?>


                    </div>


                </div>


                <!-- ACTION BUTTONS -->

                <div class="action-buttons">


                    <a
                        href="edit.php?id=<?php
                            echo $subject_id;
                        ?>"
                        class="btn btn-edit"
                    >

                        Edit Subject

                    </a>


                    <a
                        href="index.php"
                        class="btn btn-back"
                    >

                        Back to Subjects

                    </a>


                </div>


            </div>


        </div>


    </div>


</div>


</body>

</html>