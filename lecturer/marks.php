<?php

session_start();

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "lecturer"
) {
    header("Location: ../auth/login.php");
    exit();
}

require_once "../config/database.php";


/* =====================================================
   GET LOGGED-IN LECTURER
===================================================== */

$user_id = (int) $_SESSION["user_id"];

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

if ($result->num_rows !== 1) {
    $stmt->close();
    die("Lecturer account not found.");
}

$lecturer = $result->fetch_assoc();

$lecturer_id = (int) $lecturer["id"];
$lecturer_name = $lecturer["full_name"];

$stmt->close();


/* =====================================================
   GET STUDENT AND SUBJECT IDs
===================================================== */

$student_id = isset($_GET["student_id"])
    ? (int) $_GET["student_id"]
    : (int) ($_POST["student_id"] ?? 0);

$subject_id = isset($_GET["subject_id"])
    ? (int) $_GET["subject_id"]
    : (int) ($_POST["subject_id"] ?? 0);


if ($student_id <= 0 || $subject_id <= 0) {
    die("Invalid student or subject.");
}


/* =====================================================
   GET SUBJECT
   MAKE SURE IT BELONGS TO THIS LECTURER
===================================================== */

$stmt = $conn->prepare("
    SELECT
        s.id,
        s.subject_name,
        s.subject_code,
        s.course_id,
        s.semester,
        c.course_name,
        c.course_code
    FROM subjects s
    LEFT JOIN courses c
        ON c.id = s.course_id
    WHERE s.id = ?
      AND s.lecturer_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $subject_id,
    $lecturer_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    die("You are not authorized to enter marks for this subject.");
}

$subject = $result->fetch_assoc();

$stmt->close();


/* =====================================================
   GET STUDENT
   MAKE SURE STUDENT BELONGS TO SUBJECT COURSE/SEMESTER
===================================================== */

$stmt = $conn->prepare("
    SELECT
        id,
        registration_no,
        roll_no,
        student_id,
        full_name,
        email,
        course_id,
        semester,
        student_status
    FROM students
    WHERE id = ?
      AND course_id = ?
      AND semester = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$course_id = (int) $subject["course_id"];
$semester = (int) $subject["semester"];

$stmt->bind_param(
    "iii",
    $student_id,
    $course_id,
    $semester
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    die("This student does not belong to this subject.");
}

$student = $result->fetch_assoc();

$stmt->close();


/* =====================================================
   DEFAULT VALUES
===================================================== */

$internal_marks = "";
$theory_marks = "";
$practical_marks = "";

$success = "";
$error = "";


/* =====================================================
   CHECK EXISTING MARKS
===================================================== */

$stmt = $conn->prepare("
    SELECT
        internal_marks,
        theory_marks,
        practical_marks
    FROM marks
    WHERE student_id = ?
      AND subject_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $student_id,
    $subject_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $existing_marks = $result->fetch_assoc();

    $internal_marks =
        $existing_marks["internal_marks"];

    $theory_marks =
        $existing_marks["theory_marks"];

    $practical_marks =
        $existing_marks["practical_marks"];
}

$stmt->close();


/* =====================================================
   SAVE / UPDATE MARKS
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $internal_marks =
        trim($_POST["internal_marks"] ?? "");

    $theory_marks =
        trim($_POST["theory_marks"] ?? "");

    $practical_marks =
        trim($_POST["practical_marks"] ?? "");


    /* -------------------------------------------------
       VALIDATION
    ------------------------------------------------- */

    if (
        $internal_marks === "" ||
        $theory_marks === "" ||
        $practical_marks === ""
    ) {

        $error = "Please enter all marks.";

    } elseif (
        !is_numeric($internal_marks) ||
        !is_numeric($theory_marks) ||
        !is_numeric($practical_marks)
    ) {

        $error = "Marks must contain valid numbers.";

    } elseif (
        $internal_marks < 0 ||
        $internal_marks > 100 ||
        $theory_marks < 0 ||
        $theory_marks > 100 ||
        $practical_marks < 0 ||
        $practical_marks > 100
    ) {

        $error = "Each mark must be between 0 and 100.";

    } else {

        $internal_marks =
            (float) $internal_marks;

        $theory_marks =
            (float) $theory_marks;

        $practical_marks =
            (float) $practical_marks;


        /* -------------------------------------------------
           CALCULATE TOTAL
        ------------------------------------------------- */

        $total_marks =
            $internal_marks
            + $theory_marks
            + $practical_marks;


        /* -------------------------------------------------
           CALCULATE GRADE
           Based on percentage of 300
        ------------------------------------------------- */

        $percentage =
            ($total_marks / 300) * 100;


        if ($percentage >= 90) {

            $grade = "A+";

        } elseif ($percentage >= 80) {

            $grade = "A";

        } elseif ($percentage >= 70) {

            $grade = "B+";

        } elseif ($percentage >= 60) {

            $grade = "B";

        } elseif ($percentage >= 50) {

            $grade = "C";

        } elseif ($percentage >= 40) {

            $grade = "D";

        } else {

            $grade = "F";
        }


        /* -------------------------------------------------
           INSERT OR UPDATE
        ------------------------------------------------- */

        $stmt = $conn->prepare("
            INSERT INTO marks (
                student_id,
                subject_id,
                lecturer_id,
                internal_marks,
                theory_marks,
                practical_marks,
                total_marks,
                grade
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)

            ON DUPLICATE KEY UPDATE

                lecturer_id = VALUES(lecturer_id),

                internal_marks =
                    VALUES(internal_marks),

                theory_marks =
                    VALUES(theory_marks),

                practical_marks =
                    VALUES(practical_marks),

                total_marks =
                    VALUES(total_marks),

                grade =
                    VALUES(grade)
        ");

        if (!$stmt) {

            $error =
                "Database error: "
                . $conn->error;

        } else {

            $stmt->bind_param(
                "iiidddds",
                $student_id,
                $subject_id,
                $lecturer_id,
                $internal_marks,
                $theory_marks,
                $practical_marks,
                $total_marks,
                $grade
            );


            if ($stmt->execute()) {

                $success =
                    "Marks saved successfully.";

            } else {

                $error =
                    "Unable to save marks: "
                    . $stmt->error;
            }

            $stmt->close();
        }
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
        Enter Marks -
        <?php
        echo htmlspecialchars(
            $student["full_name"]
        );
        ?>
    </title>


    <link
        rel="stylesheet"
        href="../css/admin.css?v=10"
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
            font-size: 28px;
            line-height: 1.2;
            font-weight: 700;
            color: #000000;
        }


        .college-header p {
            margin: 2px 0 0;
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


        /* =================================================
           CONTENT
        ================================================= */

        .page-content {
            padding: 25px;
            max-width: 1000px;
        }


        .back-link {
            display: inline-block;
            margin-bottom: 18px;
            color: #172554;
            text-decoration: none;
            font-weight: 600;
        }


        .back-link:hover {
            text-decoration: underline;
        }


        /* =================================================
           CARDS
        ================================================= */

        .info-card,
        .marks-card {
            background: #ffffff;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);
        }


        .info-card h2 {
            margin: 0 0 15px;
            color: #172554;
        }


        .student-info {
            display: grid;
            grid-template-columns:
                repeat(2, 1fr);
            gap: 10px 30px;
            color: #374151;
            font-size: 14px;
        }


        .student-info strong {
            color: #111827;
        }


        /* =================================================
           ALERTS
        ================================================= */

        .success-message {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }


        .error-message {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }


        /* =================================================
           MARKS FORM
        ================================================= */

        .marks-card h3 {
            margin-top: 0;
            color: #172554;
        }


        .form-grid {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);
            gap: 20px;
        }


        .form-group {
            display: flex;
            flex-direction: column;
        }


        .form-group label {
            margin-bottom: 7px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }


        .form-group input {
            padding: 11px 12px;
            border: 1px solid #d1d5db;
            border-radius: 5px;
            font-size: 15px;
            box-sizing: border-box;
            width: 100%;
        }


        .form-group input:focus {
            outline: none;
            border-color: #172554;
            box-shadow:
                0 0 0 2px
                rgba(23, 37, 84, 0.1);
        }


        .form-note {
            margin-top: 7px;
            color: #6b7280;
            font-size: 12px;
        }


        /* =================================================
           BUTTON
        ================================================= */

        .form-actions {
            margin-top: 25px;
        }


        .btn-save {
            border: none;
            background: #172554;
            color: #ffffff;
            padding: 11px 22px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }


        .btn-save:hover {
            background: #1e3a8a;
        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 700px) {

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

            .form-grid {
                grid-template-columns: 1fr;
            }

            .student-info {
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
    include "../includes/lecturer_sidebar.php";
    ?>


    <!-- =================================================
         MAIN CONTENT
    ================================================= -->

    <div class="main-content">


        <!-- HEADER -->

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

                <span>
                    👤
                </span>

                <strong>
                    Lecturer
                </strong>

            </div>

        </header>


        <!-- CONTENT -->

        <div class="page-content">


            <a
                href="subjects/students.php?subject_id=<?php echo $subject_id; ?>"
                class="back-link"
            >
                ← Back to Students
            </a>


            <?php if ($success !== ""): ?>

                <div class="success-message">

                    <?php
                    echo htmlspecialchars(
                        $success
                    );
                    ?>

                </div>

            <?php endif; ?>


            <?php if ($error !== ""): ?>

                <div class="error-message">

                    <?php
                    echo htmlspecialchars(
                        $error
                    );
                    ?>

                </div>

            <?php endif; ?>


            <!-- STUDENT INFORMATION -->

            <div class="info-card">


                <h2>
                    Student Marks
                </h2>


                <div class="student-info">


                    <div>

                        <strong>
                            Student Name:
                        </strong>

                        <?php

                        echo htmlspecialchars(
                            $student["full_name"]
                        );

                        ?>

                    </div>


                    <div>

                        <strong>
                            Registration No:
                        </strong>

                        <?php

                        echo htmlspecialchars(
                            $student["registration_no"]
                        );

                        ?>

                    </div>


                    <div>

                        <strong>
                            Roll No:
                        </strong>

                        <?php

                        echo htmlspecialchars(
                            $student["roll_no"]
                            ?: "-"
                        );

                        ?>

                    </div>


                    <div>

                        <strong>
                            Student ID:
                        </strong>

                        <?php

                        echo htmlspecialchars(
                            $student["student_id"]
                        );

                        ?>

                    </div>


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


                    <div>

                        <strong>
                            Subject:
                        </strong>

                        <?php

                        echo htmlspecialchars(
                            $subject["subject_name"]
                        );

                        ?>

                    </div>


                    <div>

                        <strong>
                            Subject Code:
                        </strong>

                        <?php

                        echo htmlspecialchars(
                            $subject["subject_code"]
                            ?: "-"
                        );

                        ?>

                    </div>


                </div>


            </div>


            <!-- MARKS FORM -->

            <div class="marks-card">


                <h3>
                    Enter / Update Marks
                </h3>


                <form
                    method="POST"
                    action=""
                >


                    <input
                        type="hidden"
                        name="student_id"
                        value="<?php
                        echo $student_id;
                        ?>"
                    >


                    <input
                        type="hidden"
                        name="subject_id"
                        value="<?php
                        echo $subject_id;
                        ?>"
                    >


                    <div class="form-grid">


                        <!-- INTERNAL -->

                        <div class="form-group">

                            <label
                                for="internal_marks"
                            >
                                Internal Marks
                            </label>

                            <input
                                type="number"
                                id="internal_marks"
                                name="internal_marks"
                                min="0"
                                max="100"
                                step="0.01"
                                value="<?php
                                echo htmlspecialchars(
                                    (string)
                                    $internal_marks
                                );
                                ?>"
                                required
                            >

                            <div class="form-note">
                                Enter a value from 0 to 100.
                            </div>

                        </div>


                        <!-- THEORY -->

                        <div class="form-group">

                            <label
                                for="theory_marks"
                            >
                                Theory Marks
                            </label>

                            <input
                                type="number"
                                id="theory_marks"
                                name="theory_marks"
                                min="0"
                                max="100"
                                step="0.01"
                                value="<?php
                                echo htmlspecialchars(
                                    (string)
                                    $theory_marks
                                );
                                ?>"
                                required
                            >

                            <div class="form-note">
                                Enter a value from 0 to 100.
                            </div>

                        </div>


                        <!-- PRACTICAL -->

                        <div class="form-group">

                            <label
                                for="practical_marks"
                            >
                                Practical Marks
                            </label>

                            <input
                                type="number"
                                id="practical_marks"
                                name="practical_marks"
                                min="0"
                                max="100"
                                step="0.01"
                                value="<?php
                                echo htmlspecialchars(
                                    (string)
                                    $practical_marks
                                );
                                ?>"
                                required
                            >

                            <div class="form-note">
                                Enter a value from 0 to 100.
                            </div>

                        </div>


                    </div>


                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn-save"
                        >
                            Save Marks
                        </button>

                    </div>


                </form>


            </div>


        </div>


    </div>


</div>


</body>

</html>