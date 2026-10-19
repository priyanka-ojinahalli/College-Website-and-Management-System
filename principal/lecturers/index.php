<?php

session_start();

require_once "../../config/database.php";

/* Principal access only */
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "principal") {
    header("Location: ../../auth/login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Lecturer Details + Allotted Subjects
|--------------------------------------------------------------------------
| subjects.lecturer_id is linked with lecturers.id
| subjects.course_id is linked with courses.id
*/

$sql = "
    SELECT 
        l.id,
        l.lecturer_id,
        l.full_name,
        l.email,
        l.phone,
        l.designation,

        GROUP_CONCAT(
            DISTINCT CONCAT(
                s.subject_name,
                ' (',
                s.subject_code,
                ')'
            )
            ORDER BY s.subject_name
            SEPARATOR '||'
        ) AS subjects,

        GROUP_CONCAT(
            DISTINCT c.course_name
            ORDER BY c.course_name
            SEPARATOR '||'
        ) AS courses

    FROM lecturers l

    LEFT JOIN subjects s
        ON s.lecturer_id = l.id

    LEFT JOIN courses c
        ON c.id = s.course_id

    GROUP BY
        l.id,
        l.lecturer_id,
        l.full_name,
        l.email,
        l.phone,
        l.designation

    ORDER BY l.full_name ASC
";

$result = $conn->query($sql);

if (!$result) {
    die("Database Error: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Lecturers - Principal</title>

    <!-- Correct path from principal/lecturers/index.php -->
    <link rel="stylesheet" href="../../css/admin.css">

    <style>

        .admin-content {
            margin-left: 250px;
            padding: 30px;
            min-height: calc(100vh - 95px);
            background: #f8fafc;
            box-sizing: border-box;
        }

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

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h1 {
            margin: 0 0 5px;
            color: #172554;
            font-size: 28px;
        }

        .page-heading p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        .table-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;

            padding: 25px;

            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);

            overflow-x: auto;
        }

        .lecturer-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        .lecturer-table th {
            background: #172554;
            color: #ffffff;

            padding: 13px 12px;

            text-align: left;
            font-size: 14px;
        }

        .lecturer-table td {
            padding: 14px 12px;

            border-bottom: 1px solid #e2e8f0;

            color: #334155;

            font-size: 14px;

            vertical-align: top;
        }

        .lecturer-table tbody tr:hover {
            background: #f8fafc;
        }

        .lecturer-name {
            font-weight: 600;
            color: #172554;
        }

        .lecturer-id {
            font-weight: 600;
            color: #475569;
        }

        .designation {
            display: inline-block;

            padding: 5px 9px;

            background: #eef2ff;
            color: #3730a3;

            border-radius: 5px;

            font-size: 12px;
        }

        .subject-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .subject-item {
            background: #f8fafc;

            border: 1px solid #e2e8f0;

            border-radius: 5px;

            padding: 7px 9px;

            color: #334155;

            font-size: 13px;
        }

        .course-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .course-item {
            background: #ecfdf5;
            color: #047857;

            border: 1px solid #a7f3d0;

            border-radius: 5px;

            padding: 5px 8px;

            font-size: 12px;
        }

        .not-allotted {
            color: #94a3b8;
            font-style: italic;
        }

        .empty-message {
            text-align: center;

            padding: 40px;

            color: #64748b;
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

    </style>

</head>

<body>

<?php
/*
|--------------------------------------------------------------------------
| Principal Includes
|--------------------------------------------------------------------------
| Current file:
| principal/lecturers/index.php
|
| Therefore:
| principal/includes/ = ../includes/
|--------------------------------------------------------------------------
*/

include "../includes/principal_sidebar.php";

include "../includes/principal_header.php";

?>

<div class="admin-content">

    <div class="page-heading">

        <h1>Lecturers</h1>

        <p>
            View lecturer details and their allotted subjects.
        </p>

    </div>


    <div class="table-box">

        <?php if ($result->num_rows > 0): ?>

            <table class="lecturer-table">

                <thead>

                    <tr>

                        <th>Sl. No.</th>

                        <th>Lecturer ID</th>

                        <th>Lecturer Name</th>

                        <th>Email</th>

                        <th>Phone</th>

                        <th>Designation</th>

                        <th>Allotted Subjects</th>

                        <th>Course</th>

                    </tr>

                </thead>


                <tbody>

                <?php

                $sl = 1;

                while ($row = $result->fetch_assoc()):

                ?>

                    <tr>

                        <!-- Sl. No. -->
                        <td>

                            <?php echo $sl++; ?>

                        </td>


                        <!-- Lecturer ID -->
                        <td>

                            <span class="lecturer-id">

                                <?php

                                echo htmlspecialchars(
                                    $row["lecturer_id"]
                                );

                                ?>

                            </span>

                        </td>


                        <!-- Lecturer Name -->
                        <td>

                            <span class="lecturer-name">

                                <?php

                                echo htmlspecialchars(
                                    $row["full_name"]
                                );

                                ?>

                            </span>

                        </td>


                        <!-- Email -->
                        <td>

                            <?php

                            if (!empty($row["email"])) {

                                echo htmlspecialchars(
                                    $row["email"]
                                );

                            } else {

                                echo '<span class="not-allotted">
                                        Not provided
                                      </span>';

                            }

                            ?>

                        </td>


                        <!-- Phone -->
                        <td>

                            <?php

                            if (!empty($row["phone"])) {

                                echo htmlspecialchars(
                                    $row["phone"]
                                );

                            } else {

                                echo '<span class="not-allotted">
                                        Not provided
                                      </span>';

                            }

                            ?>

                        </td>


                        <!-- Designation -->
                        <td>

                            <?php

                            if (!empty($row["designation"])) {

                                echo '<span class="designation">'
                                    . htmlspecialchars(
                                        $row["designation"]
                                    )
                                    . '</span>';

                            } else {

                                echo '<span class="not-allotted">
                                        Not specified
                                      </span>';

                            }

                            ?>

                        </td>


                        <!-- Allotted Subjects -->
                        <td>

                            <?php

                            if (!empty($row["subjects"])):

                                $subjectList = explode(
                                    "||",
                                    $row["subjects"]
                                );

                            ?>

                                <div class="subject-list">

                                    <?php foreach ($subjectList as $subject): ?>

                                        <div class="subject-item">

                                            📚

                                            <?php

                                            echo htmlspecialchars(
                                                $subject
                                            );

                                            ?>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php else: ?>

                                <span class="not-allotted">

                                    No subjects allotted

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Course -->
                        <td>

                            <?php

                            if (!empty($row["courses"])):

                                $courseList = explode(
                                    "||",
                                    $row["courses"]
                                );

                            ?>

                                <div class="course-list">

                                    <?php foreach ($courseList as $course): ?>

                                        <span class="course-item">

                                            <?php

                                            echo htmlspecialchars(
                                                $course
                                            );

                                            ?>

                                        </span>

                                    <?php endforeach; ?>

                                </div>

                            <?php else: ?>

                                <span class="not-allotted">

                                    Not allotted

                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="empty-message">

                <h3>No lecturers found</h3>

                <p>
                    There are currently no lecturers available
                    in the system.
                </p>

            </div>

        <?php endif; ?>

    </div>

</div>


<?php

include "../includes/principal_footer.php";

?>

</body>

</html>