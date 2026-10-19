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


/* =====================================================
   GET SUBJECTS
===================================================== */

$subjects = [];

$sql = "
    SELECT
        subjects.id,
        subjects.subject_name,
        subjects.subject_code,
        subjects.semester,
        subjects.lecturer_id,

        courses.course_name,
        courses.course_code,

        lecturers.full_name AS lecturer_name

    FROM subjects

    LEFT JOIN courses
        ON subjects.course_id = courses.id

    LEFT JOIN lecturers
        ON subjects.lecturer_id = lecturers.id

    ORDER BY
        courses.course_name ASC,
        subjects.semester ASC,
        subjects.subject_name ASC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
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

    <title>Subject Management - Admin</title>

    <link
        rel="stylesheet"
        href="../../css/admin.css?v=10"
    >

    <style>

        .page-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

            margin-bottom: 20px;

        }


        .page-header h2 {

            margin: 0;

        }


        .page-header p {

            margin: 6px 0 0;

            color: #6b7280;

        }


        .subject-card {

            background: #ffffff;

            border-radius: 10px;

            padding: 20px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

            overflow-x: auto;

        }


        .subject-table {

            width: 100%;

            border-collapse: collapse;

            min-width: 850px;

        }


        .subject-table th {

            background: #172554;

            color: #ffffff;

            padding: 13px;

            text-align: left;

            font-size: 14px;

        }


        .subject-table td {

            padding: 13px;

            border-bottom:
                1px solid #e5e7eb;

            color: #374151;

            font-size: 14px;

        }


        .subject-table tr:hover {

            background: #f8fafc;

        }


        .subject-code {

            font-weight: bold;

            color: #172554;

        }


        .course-badge {

            display: inline-block;

            background: #eff6ff;

            color: #1d4ed8;

            padding: 5px 9px;

            border-radius: 5px;

            font-size: 12px;

            font-weight: bold;

        }


        .semester-badge {

            display: inline-block;

            background: #f3f4f6;

            color: #374151;

            padding: 5px 9px;

            border-radius: 5px;

            font-size: 12px;

        }


        .lecturer-name {

            font-weight: 500;

        }


        .not-assigned {

            color: #dc2626;

            font-size: 13px;

            font-style: italic;

        }


        .action-buttons {

            display: flex;

            gap: 7px;

            flex-wrap: wrap;

        }


        .btn {

            display: inline-block;

            padding: 7px 11px;

            border-radius: 5px;

            text-decoration: none;

            font-size: 13px;

            font-weight: 500;

        }


        .btn-view {

            background: #dbeafe;

            color: #1d4ed8;

        }


        .btn-edit {

            background: #fef3c7;

            color: #92400e;

        }


        .btn-delete {

            background: #fee2e2;

            color: #991b1b;

        }


        .btn-add {

            background: #172554;

            color: #ffffff;

            padding: 10px 16px;

            border-radius: 6px;

            text-decoration: none;

            font-weight: 600;

        }


        .btn-add:hover {

            background: #1e3a8a;

        }


        .empty-state {

            text-align: center;

            padding: 50px 20px;

            color: #6b7280;

        }


        .success-message {

            background: #dcfce7;

            color: #166534;

            padding: 12px 15px;

            border-radius: 6px;

            margin-bottom: 20px;

        }


        @media (max-width: 700px) {

            .page-header {

                flex-direction: column;

                align-items: flex-start;

            }

        }

    </style>

</head>


<body>


<div class="dashboard">


    <!-- SIDEBAR -->

    <?php include "../../includes/admin_sidebar.php"; ?>


    <!-- MAIN CONTENT -->

    <div class="main-content">


        <!-- HEADER -->

        <?php include "../../includes/admin_header.php"; ?>


        <!-- CONTENT -->

        <div class="content">


            <div class="page-header">

                <div>

                    <h2>Subject Management</h2>

                    <p>
                        Manage subjects, semesters and lecturers.
                    </p>

                </div>


                <a
                    href="add.php"
                    class="btn-add"
                >
                    + Add Subject
                </a>

            </div>


            <!-- SUCCESS MESSAGE -->

            <?php if (isset($_GET["added"])): ?>

                <div class="success-message">

                    Subject added successfully.

                </div>

            <?php endif; ?>


            <?php if (isset($_GET["updated"])): ?>

                <div class="success-message">

                    Subject updated successfully.

                </div>

            <?php endif; ?>


            <?php if (isset($_GET["deleted"])): ?>

                <div class="success-message">

                    Subject deleted successfully.

                </div>

            <?php endif; ?>


            <!-- SUBJECT TABLE -->

            <div class="subject-card">


                <?php if (count($subjects) > 0): ?>


                    <table class="subject-table">


                        <thead>

                            <tr>

                                <th>#</th>

                                <th>Subject</th>

                                <th>Subject Code</th>

                                <th>Course</th>

                                <th>Semester</th>

                                <th>Lecturer</th>

                                <th>Actions</th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php

                        $count = 1;

                        foreach ($subjects as $subject):

                        ?>


                            <tr>


                                <!-- NUMBER -->

                                <td>

                                    <?php echo $count++; ?>

                                </td>


                                <!-- SUBJECT -->

                                <td>

                                    <strong>

                                        <?php

                                        echo htmlspecialchars(
                                            $subject["subject_name"]
                                        );

                                        ?>

                                    </strong>

                                </td>


                                <!-- SUBJECT CODE -->

                                <td>

                                    <span class="subject-code">

                                        <?php

                                        echo htmlspecialchars(
                                            $subject["subject_code"]
                                                ?: "-"
                                        );

                                        ?>

                                    </span>

                                </td>


                                <!-- COURSE -->

                                <td>

                                    <span class="course-badge">

                                        <?php

                                        echo htmlspecialchars(

                                            ($subject["course_code"]
                                                ? $subject["course_code"]
                                                . " - "
                                                : "")

                                            .

                                            $subject["course_name"]

                                        );

                                        ?>

                                    </span>

                                </td>


                                <!-- SEMESTER -->

                                <td>

                                    <span class="semester-badge">

                                        Semester

                                        <?php

                                        echo (int)
                                            $subject["semester"];

                                        ?>

                                    </span>

                                </td>


                                <!-- LECTURER -->

                                <td>


                                    <?php

                                    if (
                                        !empty(
                                            $subject["lecturer_name"]
                                        )
                                    ):

                                    ?>

                                        <span
                                            class="lecturer-name"
                                        >

                                            <?php

                                            echo htmlspecialchars(
                                                $subject[
                                                    "lecturer_name"
                                                ]
                                            );

                                            ?>

                                        </span>


                                    <?php else: ?>


                                        <span
                                            class="not-assigned"
                                        >

                                            Not Assigned

                                        </span>


                                    <?php endif; ?>


                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div
                                        class="action-buttons"
                                    >


                                        <a
                                            href="view.php?id=<?php
                                                echo (int)
                                                    $subject["id"];
                                            ?>"
                                            class="btn btn-view"
                                        >
                                            View
                                        </a>


                                        <a
                                            href="edit.php?id=<?php
                                                echo (int)
                                                    $subject["id"];
                                            ?>"
                                            class="btn btn-edit"
                                        >
                                            Edit
                                        </a>


                                        <a
                                            href="delete.php?id=<?php
                                                echo (int)
                                                    $subject["id"];
                                            ?>"
                                            class="btn btn-delete"
                                            onclick="
                                                return confirm(
                                                    'Are you sure you want to delete this subject?'
                                                );
                                            "
                                        >
                                            Delete
                                        </a>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>


                    </table>


                <?php else: ?>


                    <div class="empty-state">

                        <h3>No Subjects Found</h3>

                        <p>
                            No subjects have been added yet.
                        </p>

                        <br>

                        <a
                            href="add.php"
                            class="btn-add"
                        >
                            + Add First Subject
                        </a>

                    </div>


                <?php endif; ?>


            </div>


        </div>


    </div>


</div>


</body>

</html>