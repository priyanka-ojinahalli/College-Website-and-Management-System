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
   ADD COURSE
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
       REQUIRED FIELD VALIDATION
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
            LIMIT 1
        ");


        $check->bind_param(
            "s",
            $course_code
        );


        $check->execute();


        $result =
            $check->get_result();


        if ($result->num_rows > 0) {

            $error =
                "This course code already exists.";

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
            LIMIT 1
        ");


        $check->bind_param(
            "s",
            $course_name
        );


        $check->execute();


        $result =
            $check->get_result();


        if ($result->num_rows > 0) {

            $error =
                "This course already exists.";

        }


        $check->close();

    }


    /* =================================================
       INSERT COURSE
    ================================================= */

    if ($error === "") {

        $stmt = $conn->prepare("
            INSERT INTO courses
            (
                course_name,
                course_code
            )
            VALUES
            (
                ?,
                ?
            )
        ");


        if (!$stmt) {

            $error =
                "Unable to prepare course.";

        } else {


            $stmt->bind_param(
                "ss",
                $course_name,
                $course_code
            );


            if ($stmt->execute()) {

                $stmt->close();


                header(
                    "Location: index.php?added=1"
                );

                exit();

            } else {

                $error =
                    "Unable to add course: " .
                    $stmt->error;

                $stmt->close();

            }

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
        Add Course - Admin
    </title>


    <link
        rel="stylesheet"
        href="../../css/admin.css?v=21"
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
                Add Course
            </h2>


            <p class="welcome">

                Add a new course offered by the college.

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

                        Enter the official course name
                        and course code.

                    </p>

                    <p style="margin-top: 5px;">

                        Example:

                        <strong>BCA</strong> -
                        Bachelor of Computer Applications

                    </p>

                </div>


                <!-- FORM -->

                <form
                    method="POST"
                    action=""
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
                                    $_POST[
                                        "course_code"
                                    ] ?? ""
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
                                    $_POST[
                                        "course_name"
                                    ] ?? ""
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

                            Add Course

                        </button>


                        <a
                            href="index.php"
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