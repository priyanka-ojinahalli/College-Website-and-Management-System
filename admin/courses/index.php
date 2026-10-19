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
   FETCH COURSES
===================================================== */

$courses = [];

$result = $conn->query("
    SELECT
        id,
        course_name,
        course_code
    FROM courses
    ORDER BY course_name ASC
");


if ($result) {

    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
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

    <title>
        Course Management - Admin
    </title>


    <link
        rel="stylesheet"
        href="../../css/admin.css?v=20"
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


        .btn-add {

            display: inline-block;

            background: #172554;

            color: white;

            padding: 10px 16px;

            border-radius: 6px;

            text-decoration: none;

            font-weight: 600;

        }


        .btn-add:hover {

            background: #1e3a8a;

        }


        .course-card {

            background: white;

            border-radius: 10px;

            padding: 25px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

            margin-top: 20px;

        }


        .course-table {

            width: 100%;

            border-collapse: collapse;

        }


        .course-table th {

            background: #172554;

            color: white;

            padding: 13px;

            text-align: left;

        }


        .course-table td {

            padding: 13px;

            border-bottom:
                1px solid #e5e7eb;

        }


        .course-table tr:hover {

            background: #f8fafc;

        }


        .course-code {

            font-weight: 600;

            color: #172554;

        }


        .action-buttons {

            display: flex;

            gap: 7px;

            flex-wrap: wrap;

        }


        .btn-view,
        .btn-edit {

            display: inline-block;

            padding: 7px 12px;

            border-radius: 5px;

            text-decoration: none;

            font-size: 13px;

            font-weight: 600;

        }


        .btn-view {

            background: #dbeafe;

            color: #1e40af;

        }


        .btn-edit {

            background: #dcfce7;

            color: #166534;

        }


        .btn-view:hover {

            background: #bfdbfe;

        }


        .btn-edit:hover {

            background: #bbf7d0;

        }


        .empty-message {

            text-align: center;

            padding: 40px;

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


            .course-card {

                overflow-x: auto;

            }


            .course-table {

                min-width: 650px;

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


                <div>

                    <h2>
                        Course Management
                    </h2>

                    <p class="welcome">

                        Manage the courses offered by
                        the college.

                    </p>

                </div>


                <a
                    href="add.php"
                    class="btn-add"
                >

                    + Add Course

                </a>


            </div>


            <!-- =================================================
                 SUCCESS MESSAGE
            ================================================= -->

            <?php if (
                isset($_GET["added"]) &&
                $_GET["added"] === "1"
            ): ?>

                <div class="success-message">

                    Course added successfully.

                </div>

            <?php endif; ?>


            <?php if (
                isset($_GET["updated"]) &&
                $_GET["updated"] === "1"
            ): ?>

                <div class="success-message">

                    Course updated successfully.

                </div>

            <?php endif; ?>


            <?php if (
                isset($_GET["deleted"]) &&
                $_GET["deleted"] === "1"
            ): ?>

                <div class="success-message">

                    Course deleted successfully.

                </div>

            <?php endif; ?>


            <!-- =================================================
                 COURSE TABLE
            ================================================= -->

            <div class="course-card">


                <?php if (count($courses) > 0): ?>


                    <table class="course-table">


                        <thead>

                            <tr>

                                <th>
                                    #
                                </th>

                                <th>
                                    Course Code
                                </th>

                                <th>
                                    Course Name
                                </th>

                                <th>
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php

                        $number = 1;

                        foreach (
                            $courses
                            as $course
                        ):

                        ?>


                            <tr>


                                <td>

                                    <?php
                                    echo $number++;
                                    ?>

                                </td>


                                <td>

                                    <span
                                        class="course-code"
                                    >

                                        <?php

                                        echo htmlspecialchars(
                                            $course[
                                                "course_code"
                                            ]
                                        );

                                        ?>

                                    </span>

                                </td>


                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $course[
                                            "course_name"
                                        ]
                                    );

                                    ?>

                                </td>


                                <td>

                                    <div
                                        class="action-buttons"
                                    >


                                        <a
                                            href="view.php?id=<?php
                                                echo (int)
                                                    $course["id"];
                                            ?>"
                                            class="btn-view"
                                        >

                                            View

                                        </a>


                                        <a
                                            href="edit.php?id=<?php
                                                echo (int)
                                                    $course["id"];
                                            ?>"
                                            class="btn-edit"
                                        >

                                            Edit

                                        </a>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>


                    </table>


                <?php else: ?>


                    <div class="empty-message">

                        <h3>
                            No Courses Found
                        </h3>

                        <p>

                            No courses have been added
                            yet.

                        </p>

                        <br>

                        <a
                            href="add.php"
                            class="btn-add"
                        >

                            + Add First Course

                        </a>

                    </div>


                <?php endif; ?>


            </div>


        </div>


    </div>


</div>


</body>

</html>