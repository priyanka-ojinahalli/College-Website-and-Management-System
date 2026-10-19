<?php

session_start();

require_once "../../config/database.php";

/* Principal access only */
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "principal") {
    header("Location: ../../auth/login.php");
    exit;
}


/* Get student ID */
$studentId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($studentId <= 0) {
    header("Location: index.php");
    exit;
}


/* Get complete student details */
$sql = "
    SELECT
        s.*,
        c.course_name,
        c.course_code
    FROM students s
    LEFT JOIN courses c
        ON s.course_id = c.id
    WHERE s.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error.");
}

$stmt->bind_param("i", $studentId);

$stmt->execute();

$result = $stmt->get_result();

$student = $result->fetch_assoc();

$stmt->close();


/* Student not found */
if (!$student) {
    header("Location: index.php");
    exit;
}


/* Determine academic year */

$semester = (int)($student["semester"] ?? 0);

if (in_array($semester, [1, 2], true)) {

    $academicYear = "1st Year";

} elseif (in_array($semester, [3, 4], true)) {

    $academicYear = "2nd Year";

} elseif (in_array($semester, [5, 6], true)) {

    $academicYear = "3rd Year";

} else {

    $academicYear = "-";
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
        Student Profile - Principal
    </title>

    <link
        rel="stylesheet"
        href="../../css/admin.css"
    >

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

            display: flex;

            justify-content: space-between;

            align-items: center;

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


        .back-button {

            display: inline-block;

            padding: 10px 18px;

            background: #ffffff;

            border: 1px solid #cbd5e1;

            border-radius: 6px;

            color: #334155;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;
        }


        .back-button:hover {

            background: #f1f5f9;
        }


        .profile-box {

            background: #ffffff;

            border: 1px solid #e2e8f0;

            border-radius: 10px;

            padding: 25px;

            margin-bottom: 25px;

            box-shadow:
                0 4px 12px
                rgba(15, 23, 42, 0.05);
        }


        .profile-title {

            color: #172554;

            font-size: 20px;

            font-weight: 700;

            margin-bottom: 20px;

            padding-bottom: 12px;

            border-bottom: 1px solid #e2e8f0;
        }


        .student-name {

            font-size: 24px;

            font-weight: 700;

            color: #172554;

            margin-bottom: 5px;
        }


        .student-course {

            color: #64748b;

            font-size: 14px;

            margin-bottom: 20px;
        }


        .details-grid {

            display: grid;

            grid-template-columns: repeat(2, 1fr);

            gap: 18px 25px;
        }


        .detail-item {

            padding: 13px;

            background: #f8fafc;

            border: 1px solid #e2e8f0;

            border-radius: 7px;
        }


        .detail-label {

            display: block;

            color: #64748b;

            font-size: 12px;

            margin-bottom: 5px;
        }


        .detail-value {

            color: #172554;

            font-size: 14px;

            font-weight: 600;

            word-break: break-word;
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

            .details-grid {

                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>


<?php include "../includes/principal_sidebar.php"; ?>


<?php include "../includes/principal_header.php"; ?>


<div class="admin-content">


    <!-- PAGE HEADING -->

    <div class="page-heading">

        <div>

            <h1>Student Profile</h1>

            <p>
                Complete student information
            </p>

        </div>


        <a
            href="index.php"
            class="back-button"
        >
            ← Back to Students
        </a>

    </div>


    <!-- BASIC INFORMATION -->

    <div class="profile-box">

        <div class="student-name">

            <?php

            echo htmlspecialchars(
                $student["full_name"] ?? "-"
            );

            ?>

        </div>


        <div class="student-course">

            <?php

            echo htmlspecialchars(
                $student["course_name"] ?? "-"
            );

            ?>


            <?php if (!empty($student["course_code"])): ?>

                (
                <?php

                echo htmlspecialchars(
                    $student["course_code"]
                );

                ?>
                )

            <?php endif; ?>


        </div>


        <div class="profile-title">

            Personal Information

        </div>


        <div class="details-grid">


            <div class="detail-item">

                <span class="detail-label">
                    Registration Number
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["registration_no"] ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Student ID
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["student_id"] ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Roll Number
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["roll_no"] ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Gender
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["gender"] ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Date of Birth
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["date_of_birth"] ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Email
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["email"] ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Phone
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["phone"] ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Academic Year
                </span>

                <span class="detail-value">

                    <?php echo $academicYear; ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Semester
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["semester"] ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Admission Year
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["admission_year"] ?? "-"
                    );

                    ?>

                </span>

            </div>


        </div>

    </div>


    <!-- FAMILY INFORMATION -->

    <div class="profile-box">

        <div class="profile-title">

            Family / Guardian Information

        </div>


        <div class="details-grid">


            <div class="detail-item">

                <span class="detail-label">
                    Father's Name
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["father_name"] ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Mother's Name
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["mother_name"] ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Guardian Phone
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["guardian_phone"] ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    ABC ID
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["abc_id"] ?? "-"
                    );

                    ?>

                </span>

            </div>


        </div>

    </div>


    <!-- ADDRESS -->

    <div class="profile-box">

        <div class="profile-title">

            Address

        </div>


        <div class="detail-item">

            <span class="detail-label">
                Permanent Address
            </span>

            <span class="detail-value">

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


    <!-- ACADEMIC INFORMATION -->

    <div class="profile-box">

        <div class="profile-title">

            Academic Information

        </div>


        <div class="details-grid">


            <div class="detail-item">

                <span class="detail-label">
                    Course
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["course_name"] ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Course Code
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["course_code"] ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Academic Year
                </span>

                <span class="detail-value">

                    <?php echo $academicYear; ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Semester
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["semester"] ?? "-"
                    );

                    ?>

                </span>

            </div>


            <div class="detail-item">

                <span class="detail-label">
                    Student Status
                </span>

                <span class="detail-value">

                    <?php

                    echo htmlspecialchars(
                        $student["student_status"] ?? "-"
                    );

                    ?>

                </span>

            </div>


        </div>

    </div>


</div>




</body>

</html>