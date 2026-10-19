<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "lecturer"
) {
    header("Location: ../../auth/login.php");
    exit();
}

require_once "../../config/database.php";

$lecturer_id = (int) $_SESSION["user_id"];

$title = "";
$description = "";
$notice_date = "";
$status = "active";

$error = "";


/* =========================
   ADD NOTICE
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $notice_date = trim($_POST["notice_date"] ?? "");
    $status = trim($_POST["status"] ?? "active");


    /* Validation */

    if ($title === "") {

        $error = "Please enter the notice title.";

    } elseif ($description === "") {

        $error = "Please enter the notice description.";

    } elseif ($notice_date === "") {

        $error = "Please select the notice date.";

    } elseif (!in_array($status, ["active", "inactive"])) {

        $error = "Invalid notice status.";

    } else {


        /* =========================
           INSERT NOTICE
        ========================= */

        $created_by = $lecturer_id;
        $created_role = "lecturer";

        $stmt = $conn->prepare(
            "INSERT INTO notices
            (
                title,
                description,
                notice_date,
                status,
                created_by,
                created_role
            )
            VALUES (?, ?, ?, ?, ?, ?)"
        );


        if ($stmt) {

            $stmt->bind_param(
                "ssssis",
                $title,
                $description,
                $notice_date,
                $status,
                $created_by,
                $created_role
            );


            if ($stmt->execute()) {

                $stmt->close();

                header("Location: index.php?success=1");
                exit();

            } else {

                $error = "Failed to add notice. Please try again.";

            }

            $stmt->close();

        } else {

            $error = "Database error. Please try again.";

        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Notice</title>

    <link rel="stylesheet" href="../../css/admin.css?v=4">


    <style>

        /* ===============================
           ADD NOTICE PAGE
        =============================== */

        .notice-form-page {
            padding: 32px;
            background: #f4f6fa;
            min-height: calc(100vh - 70px);
        }


        /* Page Header */

        .notice-form-header {
            margin-bottom: 25px;
        }

        .notice-form-header h2 {
            margin: 0 0 8px;
            font-size: 32px;
            color: #111827;
        }

        .notice-form-header p {
            margin: 0;
            color: #64748b;
            font-size: 18px;
        }


        /* Form Card */

        .notice-form-card {
            max-width: 900px;
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        }


        /* Form Group */

        .form-group {
            margin-bottom: 22px;
        }


        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
        }


        .form-group label span {
            color: #dc2626;
        }


        /* Inputs */

        .form-group input,
        .form-group textarea,
        .form-group select {

            width: 100%;

            padding: 12px 14px;

            border: 1px solid #cbd5e1;

            border-radius: 7px;

            font-size: 15px;

            box-sizing: border-box;

            outline: none;

            transition: 0.2s;

        }


        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {

            border-color: #2563eb;

            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);

        }


        .form-group textarea {

            min-height: 160px;

            resize: vertical;

        }


        /* Error */

        .error-message {

            background: #fee2e2;

            color: #991b1b;

            border: 1px solid #fecaca;

            padding: 12px 16px;

            border-radius: 7px;

            margin-bottom: 20px;

        }


        /* Buttons */

        .form-buttons {

            display: flex;

            gap: 12px;

            margin-top: 25px;

        }


        .save-btn {

            background: #172554;

            color: white;

            border: none;

            padding: 12px 22px;

            border-radius: 7px;

            font-size: 15px;

            font-weight: 600;

            cursor: pointer;

        }


        .save-btn:hover {

            background: #1e3a8a;

        }


        .cancel-btn {

            background: #e2e8f0;

            color: #334155;

            text-decoration: none;

            padding: 12px 22px;

            border-radius: 7px;

            font-size: 15px;

            font-weight: 600;

        }


        .cancel-btn:hover {

            background: #cbd5e1;

        }


        /* Mobile */

        @media (max-width: 768px) {

            .notice-form-page {

                padding: 20px;

            }

            .notice-form-card {

                padding: 20px;

            }

            .notice-form-header h2 {

                font-size: 26px;

            }

        }

    </style>

</head>


<body>


<?php

include "../../includes/lecturer_sidebar.php";


?>


<div class="main-content">


    <div class="notice-form-page">


        <!-- HEADER -->

        <div class="notice-form-header">

            <h2>Add Notice</h2>

            <p>
                Create a new college notice.
            </p>

        </div>


        <!-- ERROR -->

        <?php if ($error !== "") { ?>

            <div class="error-message">

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php } ?>


        <!-- FORM -->

        <div class="notice-form-card">


            <form method="POST" action="">


                <!-- TITLE -->

                <div class="form-group">

                    <label for="title">
                        Notice Title <span>*</span>
                    </label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        value="<?php echo htmlspecialchars($title); ?>"
                        placeholder="Enter notice title"
                        maxlength="200"
                        required
                    >

                </div>


                <!-- DESCRIPTION -->

                <div class="form-group">

                    <label for="description">
                        Description <span>*</span>
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        placeholder="Enter notice details..."
                        required
                    ><?php echo htmlspecialchars($description); ?></textarea>

                </div>


                <!-- DATE -->

                <div class="form-group">

                    <label for="notice_date">
                        Notice Date <span>*</span>
                    </label>

                    <input
                        type="date"
                        id="notice_date"
                        name="notice_date"
                        value="<?php echo htmlspecialchars($notice_date); ?>"
                        required
                    >

                </div>


                <!-- STATUS -->

                <div class="form-group">

                    <label for="status">
                        Status
                    </label>

                    <select
                        id="status"
                        name="status"
                    >

                        <option
                            value="active"
                            <?php
                            echo $status === "active"
                                ? "selected"
                                : "";
                            ?>
                        >
                            Active
                        </option>

                        <option
                            value="inactive"
                            <?php
                            echo $status === "inactive"
                                ? "selected"
                                : "";
                            ?>
                        >
                            Inactive
                        </option>

                    </select>

                </div>


                <!-- BUTTONS -->

                <div class="form-buttons">

                    <button
                        type="submit"
                        class="save-btn"
                    >
                        Publish Notice
                    </button>


                    <a
                        href="index.php"
                        class="cancel-btn"
                    >
                        Cancel
                    </a>

                </div>


            </form>


        </div>


    </div>


</div>


<?php


?>


</body>

</html>