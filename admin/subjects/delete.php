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
   GET SUBJECT ID
===================================================== */

$subject_id = (int)($_GET["id"] ?? 0);

if ($subject_id <= 0) {

    header("Location: index.php");
    exit();

}


/* =====================================================
   CHECK SUBJECT EXISTS
===================================================== */

$check = $conn->prepare("
    SELECT id, subject_name
    FROM subjects
    WHERE id = ?
    LIMIT 1
");

$check->bind_param(
    "i",
    $subject_id
);

$check->execute();

$result = $check->get_result();

if ($result->num_rows !== 1) {

    $check->close();

    header("Location: index.php");
    exit();

}

$subject = $result->fetch_assoc();

$check->close();


/* =====================================================
   DELETE SUBJECT
===================================================== */

$delete = $conn->prepare("
    DELETE FROM subjects
    WHERE id = ?
    LIMIT 1
");

$delete->bind_param(
    "i",
    $subject_id
);


if ($delete->execute()) {

    $delete->close();

    header(
        "Location: index.php?deleted=1"
    );

    exit();

}


/* =====================================================
   DELETE FAILED
===================================================== */

$error = $delete->error;

$delete->close();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Delete Subject - Admin</title>


    <link
        rel="stylesheet"
        href="../../css/admin.css?v=10"
    >


    <style>

        .error-card {

            background: #ffffff;

            max-width: 600px;

            margin: 40px auto;

            padding: 30px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

            text-align: center;

        }


        .error-icon {

            font-size: 45px;

            margin-bottom: 15px;

        }


        .error-title {

            color: #991b1b;

            margin-bottom: 10px;

        }


        .error-message {

            color: #6b7280;

            margin-bottom: 25px;

        }


        .btn {

            display: inline-block;

            padding: 10px 18px;

            border-radius: 6px;

            text-decoration: none;

            font-weight: 600;

            margin: 5px;

        }


        .btn-back {

            background: #172554;

            color: #ffffff;

        }


        .btn-back:hover {

            background: #1e3a8a;

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


            <div class="error-card">


                <div class="error-icon">
                    ⚠️
                </div>


                <h2 class="error-title">

                    Unable to Delete Subject

                </h2>


                <p class="error-message">

                    The subject

                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $subject["subject_name"]
                        );

                        ?>

                    </strong>

                    could not be deleted.

                </p>


                <?php if ($error !== ""): ?>

                    <p class="error-message">

                        <?php

                        echo htmlspecialchars($error);

                        ?>

                    </p>

                <?php endif; ?>


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


</body>

</html>