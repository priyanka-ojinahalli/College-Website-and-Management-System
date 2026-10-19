<?php

session_start();

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../../auth/login.php");
    exit();
}

require_once "../../config/database.php";


/* =========================
   HANDLE FORM SUBMISSION
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $notice_date = $_POST["notice_date"];
    $status = $_POST["status"];

    /* Logged-in user information */
    $created_by = $_SESSION["user_id"];
    $created_role = $_SESSION["role"];


    if (
        empty($title) ||
        empty($description) ||
        empty($notice_date)
    ) {

        $error = "Please fill in all required fields.";

    } else {

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

            header("Location: index.php?success=1");
            exit();

        } else {

            $error = "Failed to add notice.";

        }

        $stmt->close();
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

    <title>Add Notice - Admin</title>

    <link
        rel="stylesheet"
        href="../../css/admin.css?v=2"
    >

</head>


<body>


<div class="dashboard">


    <!-- =========================
         SIDEBAR
    ========================= -->

    <?php include "../../includes/admin_sidebar.php"; ?>


    <!-- =========================
         MAIN CONTENT
    ========================= -->

    <div class="main-content">


        <!-- HEADER -->

        <?php include "../../includes/admin_header.php"; ?>


        <!-- PAGE CONTENT -->

        <div class="content">


            <h2>Add Notice</h2>

            <p class="welcome">
                Create a new college announcement.
            </p>


            <br>


            <?php if (isset($error)): ?>

                <div class="alert error">

                    <?php echo htmlspecialchars($error); ?>

                </div>

            <?php endif; ?>


            <!-- =========================
                 NOTICE FORM
            ========================= -->

            <div class="form-card">


                <form method="POST">


                    <!-- TITLE -->

                    <div class="form-group">

                        <label for="title">
                            Notice Title *
                        </label>

                        <input
                            type="text"
                            id="title"
                            name="title"
                            placeholder="Enter notice title"
                            required
                        >

                    </div>


                    <!-- DESCRIPTION -->

                    <div class="form-group">

                        <label for="description">
                            Description *
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            rows="6"
                            placeholder="Enter notice details"
                            required
                        ></textarea>

                    </div>


                    <!-- DATE -->

                    <div class="form-group">

                        <label for="notice_date">
                            Notice Date *
                        </label>

                        <input
                            type="date"
                            id="notice_date"
                            name="notice_date"
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

                            <option value="active">
                                Active
                            </option>

                            <option value="inactive">
                                Inactive
                            </option>

                        </select>

                    </div>


                    <!-- BUTTONS -->

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn-primary"
                        >
                            Add Notice
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


</body>

</html>