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


$error = "";


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


/* =====================================================
   UPDATE COURSE
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /* -------------------------------------------------
       GET FORM DATA
    ------------------------------------------------- */

    $course_name = trim(
        $_POST["course_name"] ?? ""
    );

    $course_code = trim(
        $_POST["course_code"] ?? ""
    );


    /* =================================================
       REQUIRED VALIDATION
    ================================================= */

    if (
        $course_name === "" ||
        $course_code === ""
    ) {

        $error =
            "Please fill all required fields.";

    }


    /* =================================================
       COURSE CODE VALIDATION
    ================================================= */

    if ($error === "") {

        if (
            !preg_match(
                "/^[A-Za-z0-9._-]{2,20}$/",
                $course_code
            )
        ) {

            $error =
                "Course code contains invalid characters.";

        }

    }


    /* =================================================
       CHECK DUPLICATE COURSE CODE
    ================================================= */

    if ($error === "") {

        $check = $conn->prepare("
            SELECT id
            FROM courses
            WHERE course_code = ?
              AND id != ?
            LIMIT 1
        ");


        $check->bind_param(
            "si",
            $course_code,
            $course_id
        );


        $check->execute();


        $check_result =
            $check->get_result();


        if ($check_result->num_rows > 0) {

            $error =
                "This course code is already being used.";

        }


        $check->close();

    }


    /* =================================================
       CHECK DUPLICATE COURSE NAME
    ================================================= */

    if ($error === "") {

        $check = $conn->prepare("
            SELECT id
            FROM courses
            WHERE course_name = ?
              AND id != ?
            LIMIT 1
        ");


        $check->bind_param(
            "si",
            $course_name,
            $course_id
        );


        $check->execute();


        $check_result =
            $check->get_result();


        if ($check_result->num_rows > 0) {

            $error =
                "This course name is already being used.";

        }


        $check->close();

    }


    /* =================================================
       UPDATE DATABASE
    ================================================= */

    if ($error === "") {

        $update = $conn->prepare("
            UPDATE courses
            SET
                course_name = ?,
                course_code = ?
            WHERE id = ?
        ");


        if (!$update) {

            $error =
                "Unable to prepare course update.";

        } else {


            $update->bind_param(
                "ssi",
                $course_name,
                $course_code,
                $course_id
            );


            if ($update->execute()) {

                $update->close();


                header(
                    "Location: index.php?updated=1"
                );

                exit();

            } else {

                $error =
                    "Unable to update course: " .
                    $update->error;

                $update->close();

            }

        }

    }


    /* =================================================
       KEEP ENTERED VALUES IF ERROR
    ================================================= */

    $course["course_name"] =
        $course_name;

    $course["course_code"] =
        $course_code;

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
        Edit Course - Admin
    </title>


    <link
        rel="stylesheet"
        href="../../css/admin.css?v=23"
    >


    <style>

        .course-form-card {

            background: #ffffff;

            max-width: 750px;

            padding: 30px;

            margin-top: 25px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

        }


        .form-group {

            margin-bottom: 20px;

        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            font-weight: 600;

            color: #374151;

        }


        .required {

            color: #dc2626;

        }


        .form-group input {

            width: 100%;

            padding: 11px;

            border: 1px solid #d1d5db;

            border-radius: 5px;

            font-family: inherit;

            font-size: 15px;

            box-sizing: border-box;

        }


        .form-group input:focus {

            outline: none;

            border-color: #172554;

            box-shadow:
                0 0 0 2px
                rgba(23, 37, 84, 0.1);

        }


        .help-text {

            font-size: 12px;

            color: #6b7280;

            margin-top: 5px;

        }


        .alert-error {

            background: #fee2e2;

            color: #991b1b;

            padding: 12px 15px;

            border-radius: 6px;

            margin-top: 20px;

            max-width: 750px;

        }


        .info-box {

            background: #eff6ff;

            border-left:
                4px solid #2563eb;

            padding: 15px;

            margin-bottom: 25px;

            border-radius: 5px;

            color: #374151;

        }


        .info-box strong {

            color: #172554;

        }


        .form-actions {

            display: flex;

            gap: 10px;

            margin-top: 30px;

            padding-top: 20px;

            border-top:
                1px solid #e5e7eb;

        }


        .btn-primary {

            border: none;

            background: #172554;

            color: #ffffff;

            padding: 11px 18px;

            border-radius: 5px;

            cursor: pointer;

            font-size: 14px;

            font-weight: 600;

        }


        .btn-primary:hover {

            background: #1e3a8a;

        }


        .btn-secondary {

            display: inline-block;

            background: #e5e7eb;

            color: #374151;

            padding: 11px 18px;

            border-radius: 5px;

            text-decoration: none;

            font-size: 14px;

        }


        .btn-secondary:hover {

            background: #d1d5db;

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


            <h2>
                Edit Course
            </h2>


            <p class="welcome">

                Update the course information.

            </p>


            <?php if ($error !== ""): ?>

                <div class="alert-error">

                    <?php

                    echo htmlspecialchars(
                        $error
                    );

                    ?>

                </div>

            <?php endif; ?>


            <div class="course-form-card">


                <!-- INFORMATION -->

                <div class="info-box">

                    <strong>
                        Course Information
                    </strong>

                    <p style="margin-top: 7px;">

                        Update the official course name
                        or course code below.

                    </p>

                </div>


                <!-- FORM -->

                <form
                    method="POST"
                    action="edit.php?id=<?php
                        echo $course_id;
                    ?>"
                    id="courseForm"
                    novalidate
                >


                    <!-- COURSE CODE -->

                    <div class="form-group">

                        <label>

                            Course Code

                            <span class="required">
                                *
                            </span>

                        </label>


                        <input
                            type="text"
                            name="course_code"
                            id="course_code"
                            required
                            maxlength="20"
                            value="<?php

                                echo htmlspecialchars(
                                    $course[
                                        "course_code"
                                    ]
                                );

                            ?>"
                            placeholder="Example: BCA"
                        >


                        <div class="help-text">

                            Example: BCA, BBA or B.Com

                        </div>

                    </div>


                    <!-- COURSE NAME -->

                    <div class="form-group">

                        <label>

                            Course Name

                            <span class="required">
                                *
                            </span>

                        </label>


                        <input
                            type="text"
                            name="course_name"
                            id="course_name"
                            required
                            maxlength="150"
                            value="<?php

                                echo htmlspecialchars(
                                    $course[
                                        "course_name"
                                    ]
                                );

                            ?>"
                            placeholder="Example: Bachelor of Computer Applications"
                        >


                        <div class="help-text">

                            Enter the full official name
                            of the course.

                        </div>

                    </div>


                    <!-- ACTIONS -->

                    <div class="form-actions">


                        <button
                            type="submit"
                            class="btn-primary"
                        >

                            Save Changes

                        </button>


                        <a
                            href="view.php?id=<?php
                                echo $course_id;
                            ?>"
                            class="btn-secondary"
                        >

                            Cancel

                        </a>


                    </div>


                </form>


            </div>


        </div>


    </div>


</div>


<script>

/* =====================================================
   FORM VALIDATION
===================================================== */

document
    .getElementById("courseForm")
    .addEventListener(
        "submit",
        function(event) {


            const code =
                document
                    .getElementById("course_code")
                    .value
                    .trim();


            const name =
                document
                    .getElementById("course_name")
                    .value
                    .trim();


            if (code === "") {

                event.preventDefault();

                alert(
                    "Please enter the course code."
                );

                document
                    .getElementById("course_code")
                    .focus();

                return;

            }


            if (
                !/^[A-Za-z0-9._-]{2,20}$/
                    .test(code)
            ) {

                event.preventDefault();

                alert(
                    "Please enter a valid course code."
                );

                document
                    .getElementById("course_code")
                    .focus();

                return;

            }


            if (name === "") {

                event.preventDefault();

                alert(
                    "Please enter the course name."
                );

                document
                    .getElementById("course_name")
                    .focus();

                return;

            }

        }
    );

</script>


</body>

</html>