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
   GET SUBJECT
===================================================== */

$subject_id = isset($_GET["subject_id"])
    ? (int) $_GET["subject_id"]
    : (int) ($_POST["subject_id"] ?? 0);

if ($subject_id <= 0) {
    header("Location: index.php");
    exit();
}


/* =====================================================
   VERIFY SUBJECT BELONGS TO LECTURER
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
    die("You are not authorized to access this subject.");
}

$subject = $result->fetch_assoc();

$stmt->close();


/* =====================================================
   GET EXAMINATIONS
===================================================== */

$examinations = [];

$result = $conn->query("
    SELECT id, exam_name
    FROM examinations
    ORDER BY id ASC
");

if (!$result) {
    die("Unable to load examinations: " . $conn->error);
}

while ($row = $result->fetch_assoc()) {
    $examinations[] = $row;
}


/* =====================================================
   SELECT EXAMINATION
===================================================== */

$examination_id = isset($_GET["examination_id"])
    ? (int) $_GET["examination_id"]
    : (int) ($_POST["examination_id"] ?? 0);


/*
   If no examination was selected,
   automatically select the first one.
*/

if (
    $examination_id <= 0 &&
    count($examinations) > 0
) {
    $examination_id =
        (int) $examinations[0]["id"];
}


/* =====================================================
   VERIFY EXAMINATION EXISTS
===================================================== */

$selected_exam_name = "";

foreach ($examinations as $exam) {

    if (
        (int) $exam["id"] ===
        $examination_id
    ) {

        $selected_exam_name =
            $exam["exam_name"];

        break;
    }
}


/* =====================================================
   GET STUDENTS
===================================================== */

$students = [];

$course_id =
    (int) $subject["course_id"];

$semester =
    (int) $subject["semester"];


$stmt = $conn->prepare("
    SELECT
        id,
        registration_no,
        roll_no,
        student_id,
        full_name,
        email,
        student_status
    FROM students
    WHERE course_id = ?
      AND semester = ?
    ORDER BY
        roll_no ASC,
        full_name ASC
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "ii",
    $course_id,
    $semester
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

$stmt->close();


/* =====================================================
   SAVE ALL MARKS
===================================================== */

$success = "";
$error = "";


if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["save_marks"])
) {

    $posted_exam_id =
        (int) ($_POST["examination_id"] ?? 0);


    if ($posted_exam_id <= 0) {

        $error =
            "Please select an examination.";

    } else {

        /*
           Verify selected examination.
        */

        $exam_exists = false;

        foreach ($examinations as $exam) {

            if (
                (int) $exam["id"] ===
                $posted_exam_id
            ) {

                $exam_exists = true;

                break;
            }
        }


        if (!$exam_exists) {

            $error =
                "Invalid examination selected.";

        } else {

            /*
               Begin transaction so that all
               student marks are saved together.
            */

            $conn->begin_transaction();


            try {

                /*
                   Prepare INSERT / UPDATE statement.
                */

                $stmt = $conn->prepare("
                    INSERT INTO marks (
                        student_id,
                        subject_id,
                        lecturer_id,
                        examination_id,
                        marks
                    )
                    VALUES (?, ?, ?, ?, ?)

                    ON DUPLICATE KEY UPDATE

                        lecturer_id =
                            VALUES(lecturer_id),

                        marks =
                            VALUES(marks),

                        updated_at =
                            CURRENT_TIMESTAMP
                ");

                if (!$stmt) {
                    throw new Exception(
                        "Database error: "
                        . $conn->error
                    );
                }


                /*
                   Marks are submitted as:

                   marks[student_id]
                */

                $posted_marks =
                    $_POST["marks"] ?? [];


                foreach ($students as $student) {

                    $student_id =
                        (int) $student["id"];


                    /*
                       If no value was entered,
                       save 0.
                    */

                    $mark_value = 0;

                    if (
                        isset(
                            $posted_marks[
                                $student_id
                            ]
                        )
                    ) {

                        $raw_mark =
                            trim(
                                $posted_marks[
                                    $student_id
                                ]
                            );


                        if ($raw_mark !== "") {

                            if (
                                !is_numeric(
                                    $raw_mark
                                )
                            ) {

                                throw new Exception(
                                    "Invalid marks entered for "
                                    . $student["full_name"]
                                    . "."
                                );
                            }


                            $mark_value =
                                (float) $raw_mark;


                            /*
                               Currently marks are
                               allowed from 0 to 100.
                            */

                            if (
                                $mark_value < 0 ||
                                $mark_value > 100
                            ) {

                                throw new Exception(
                                    "Marks for "
                                    . $student["full_name"]
                                    . " must be between 0 and 100."
                                );
                            }

                        }

                    }


                    $stmt->bind_param(
                        "iiiid",
                        $student_id,
                        $subject_id,
                        $lecturer_id,
                        $posted_exam_id,
                        $mark_value
                    );


                    if (!$stmt->execute()) {

                        throw new Exception(
                            "Unable to save marks for "
                            . $student["full_name"]
                            . "."
                        );
                    }

                }


                $stmt->close();


                /*
                   Everything successful.
                */

                $conn->commit();


                $success =
                    "Marks saved successfully for "
                    . $selected_exam_name
                    . ".";


                /*
                   Keep selected examination.
                */

                $examination_id =
                    $posted_exam_id;

            } catch (Exception $e) {

                $conn->rollback();

                $error =
                    $e->getMessage();
            }

        }

    }

}


/* =====================================================
   LOAD EXISTING MARKS
===================================================== */

$existing_marks = [];


if ($examination_id > 0) {

    $stmt = $conn->prepare("
        SELECT
            student_id,
            marks
        FROM marks
        WHERE subject_id = ?
          AND examination_id = ?
    ");

    if (!$stmt) {
        die("Database error: " . $conn->error);
    }

    $stmt->bind_param(
        "ii",
        $subject_id,
        $examination_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $existing_marks[
            (int) $row["student_id"]
        ] = $row["marks"];
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

    <title>
        <?php
        echo htmlspecialchars(
            $subject["subject_name"]
        );
        ?>
        - Marks
    </title>


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
           SUBJECT CARD
        ================================================= */

        .subject-card {

            background: #ffffff;

            padding: 22px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

            margin-bottom: 20px;

        }


        .subject-card h2 {

            margin: 0 0 8px;

            color: #172554;

            font-size: 23px;

        }


        .subject-code {

            display: inline-block;

            background: #eff6ff;

            color: #1e3a8a;

            padding: 4px 9px;

            border-radius: 4px;

            font-size: 12px;

            font-weight: 600;

            margin-bottom: 10px;

        }


        .subject-details {

            color: #4b5563;

            font-size: 14px;

        }


        /* =================================================
           EXAMINATION SELECT
        ================================================= */

        .exam-card {

            background: #ffffff;

            padding: 20px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

            margin-bottom: 20px;

        }


        .exam-card label {

            display: block;

            margin-bottom: 8px;

            font-weight: 600;

            color: #374151;

        }


        .exam-card select {

            width: 300px;

            max-width: 100%;

            padding: 10px 12px;

            border: 1px solid #d1d5db;

            border-radius: 5px;

            background: #ffffff;

            font-size: 14px;

        }


        .exam-card select:focus {

            outline: none;

            border-color: #172554;

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
           MARKS TABLE
        ================================================= */

        .marks-card {

            background: #ffffff;

            padding: 25px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

        }


        .marks-card h3 {

            margin: 0 0 18px;

            color: #172554;

        }


        .table-wrapper {

            width: 100%;

            overflow-x: auto;

        }


        .marks-table {

            width: 100%;

            border-collapse: collapse;

            min-width: 850px;

        }


        .marks-table th {

            background: #172554;

            color: #ffffff;

            padding: 12px;

            text-align: left;

            font-size: 13px;

        }


        .marks-table td {

            padding: 10px 12px;

            border-bottom:
                1px solid #e5e7eb;

            color: #374151;

            font-size: 13px;

        }


        .marks-table tr:hover {

            background: #f8fafc;

        }


        /* =================================================
           MARK INPUT
        ================================================= */

        .mark-input {

            width: 90px;

            padding: 8px 9px;

            border: 1px solid #cbd5e1;

            border-radius: 5px;

            font-size: 14px;

            box-sizing: border-box;

        }


        .mark-input:focus {

            outline: none;

            border-color: #172554;

            box-shadow:
                0 0 0 2px
                rgba(23, 37, 84, 0.1);

        }


        /* =================================================
           SAVE BUTTON
        ================================================= */

        .save-area {

            margin-top: 20px;

            display: flex;

            justify-content: flex-end;

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
           NO STUDENTS
        ================================================= */

        .no-students {

            text-align: center;

            padding: 35px;

            color: #6b7280;

            background: #f8fafc;

            border-radius: 6px;

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
                href="index.php"
                class="back-link"
            >
                ← Back to My Subjects
            </a>


            <!-- SUBJECT -->

            <div class="subject-card">


                <h2>

                    <?php

                    echo htmlspecialchars(
                        $subject["subject_name"]
                    );

                    ?>

                </h2>


                <?php if (
                    !empty(
                        $subject["subject_code"]
                    )
                ): ?>

                    <span class="subject-code">

                        <?php

                        echo htmlspecialchars(
                            $subject["subject_code"]
                        );

                        ?>

                    </span>

                <?php endif; ?>


                <div class="subject-details">

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

                    &nbsp;&nbsp; | &nbsp;&nbsp;

                    <strong>
                        Semester:
                    </strong>

                    <?php

                    echo (int)
                        $subject["semester"];

                    ?>

                </div>


            </div>


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


            <!-- EXAMINATION -->

            <div class="exam-card">


                <form
                    method="GET"
                    action=""
                >


                    <input
                        type="hidden"
                        name="subject_id"
                        value="<?php
                        echo $subject_id;
                        ?>"
                    >


                    <label for="examination_id">

                        Select Examination

                    </label>


                    <select
                        name="examination_id"
                        id="examination_id"
                        onchange="this.form.submit()"
                    >


                        <?php foreach (
                            $examinations
                            as $exam
                        ): ?>


                            <option
                                value="<?php
                                echo (int)
                                    $exam["id"];
                                ?>"
                                <?php

                                if (
                                    (int)
                                    $exam["id"]
                                    ===
                                    $examination_id
                                ) {

                                    echo "selected";
                                }

                                ?>
                            >

                                <?php

                                echo htmlspecialchars(
                                    $exam["exam_name"]
                                );

                                ?>

                            </option>


                        <?php endforeach; ?>


                    </select>


                </form>


            </div>


            <!-- MARKS TABLE -->

            <div class="marks-card">


                <h3>

                    <?php

                    echo htmlspecialchars(
                        $selected_exam_name
                    );

                    ?>

                    Marks

                </h3>


                <?php if (
                    count($students) > 0
                ): ?>


                    <form
                        method="POST"
                        action=""
                    >


                        <input
                            type="hidden"
                            name="subject_id"
                            value="<?php
                            echo $subject_id;
                            ?>"
                        >


                        <input
                            type="hidden"
                            name="examination_id"
                            value="<?php
                            echo $examination_id;
                            ?>"
                        >


                        <div class="table-wrapper">


                            <table class="marks-table">


                                <thead>

                                    <tr>

                                        <th>
                                            #
                                        </th>

                                        <th>
                                            Roll No.
                                        </th>

                                        <th>
                                            Registration No.
                                        </th>

                                        <th>
                                            Student Name
                                        </th>

                                        <th>
                                            Marks
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>


                                    <?php

                                    $serial = 1;

                                    foreach (
                                        $students
                                        as $student
                                    ):

                                        $student_id =
                                            (int)
                                            $student["id"];


                                        $student_mark =
                                            $existing_marks[
                                                $student_id
                                            ]
                                            ?? "";

                                    ?>


                                        <tr>


                                            <td>

                                                <?php

                                                echo $serial++;

                                                ?>

                                            </td>


                                            <td>

                                                <?php

                                                echo htmlspecialchars(
                                                    $student["roll_no"]
                                                    ?: "-"
                                                );

                                                ?>

                                            </td>


                                            <td>

                                                <?php

                                                echo htmlspecialchars(
                                                    $student[
                                                        "registration_no"
                                                    ]
                                                );

                                                ?>

                                            </td>


                                            <td>

                                                <strong>

                                                    <?php

                                                    echo htmlspecialchars(
                                                        $student[
                                                            "full_name"
                                                        ]
                                                    );

                                                    ?>

                                                </strong>

                                            </td>


                                            <td>

                                                <input
                                                    type="number"
                                                    name="marks[<?php
                                                    echo $student_id;
                                                    ?>]"
                                                    class="mark-input"
                                                    min="0"
                                                    max="100"
                                                    step="0.01"
                                                    value="<?php
                                                    echo htmlspecialchars(
                                                        (string)
                                                        $student_mark
                                                    );
                                                    ?>"
                                                    placeholder="Marks"
                                                >

                                            </td>


                                        </tr>


                                    <?php endforeach; ?>


                                </tbody>


                            </table>


                        </div>


                        <div class="save-area">


                            <button
                                type="submit"
                                name="save_marks"
                                class="btn-save"
                            >
                                Save All Marks
                            </button>


                        </div>


                    </form>


                <?php else: ?>


                    <div class="no-students">

                        No students found for this
                        course and semester.

                    </div>


                <?php endif; ?>


            </div>


        </div>


    </div>


</div>


</body>

</html>