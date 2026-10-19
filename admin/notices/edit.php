<?php

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../../auth/login.php");
    exit();
}

require_once "../../config/database.php";


/* =========================
   ADMIN USER ID
========================= */

$admin_id = (int) $_SESSION["user_id"];


/* =========================
   GET NOTICE ID
========================= */

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: index.php");
    exit();
}

$id = (int) $_GET["id"];


/* =========================
   GET EXISTING NOTICE
   ONLY IF CREATED BY THIS ADMIN
========================= */

$stmt = $conn->prepare(
    "SELECT *
     FROM notices
     WHERE id = ?
     AND created_by = ?
     AND created_role = 'admin'"
);

$stmt->bind_param(
    "ii",
    $id,
    $admin_id
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {

    $stmt->close();

    header("Location: index.php");
    exit();
}

$notice = $result->fetch_assoc();

$stmt->close();


/* =========================
   UPDATE NOTICE
   ONLY IF OWNED BY THIS ADMIN
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $notice_date = $_POST["notice_date"] ?? "";
    $status = $_POST["status"] ?? "active";


    if (
        empty($title) ||
        empty($description) ||
        empty($notice_date)
    ) {

        $error = "Please fill in all required fields.";

    } elseif (
        $status !== "active" &&
        $status !== "inactive"
    ) {

        $error = "Invalid notice status.";

    } else {

        $stmt = $conn->prepare(
            "UPDATE notices
             SET title = ?,
                 description = ?,
                 notice_date = ?,
                 status = ?
             WHERE id = ?
             AND created_by = ?
             AND created_role = 'admin'"
        );

        $stmt->bind_param(
            "ssssii",
            $title,
            $description,
            $notice_date,
            $status,
            $id,
            $admin_id
        );


        if ($stmt->execute()) {

            $stmt->close();

            header("Location: index.php?updated=1");
            exit();

        } else {

            $error = "Failed to update notice.";

        }

        $stmt->close();
    }
}

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Edit Notice - Admin</title>

    <link rel="stylesheet"
          href="../../css/admin.css?v=2">

</head>


<body>


<div class="dashboard">


    <!-- SIDEBAR -->

    <?php include "../../includes/admin_sidebar.php"; ?>


    <!-- MAIN CONTENT -->

    <div class="main-content">


        <!-- HEADER -->

        <?php include "../../includes/admin_header.php"; ?>


        <!-- CONTENT -->

        <div class="content">


            <h2>Edit Notice</h2>

            <p class="welcome">
                Update the college announcement.
            </p>


            <br>


            <?php if (isset($error)): ?>

                <div class="alert error">

                    <?php echo htmlspecialchars($error); ?>

                </div>

            <?php endif; ?>


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
                            value="<?php echo htmlspecialchars($notice["title"]); ?>"
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
                            required
                        ><?php echo htmlspecialchars($notice["description"]); ?></textarea>

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
                            value="<?php echo htmlspecialchars($notice["notice_date"]); ?>"
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
                                if ($notice["status"] === "active") {
                                    echo "selected";
                                }
                                ?>
                            >
                                Active
                            </option>

                            <option
                                value="inactive"
                                <?php
                                if ($notice["status"] === "inactive") {
                                    echo "selected";
                                }
                                ?>
                            >
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
                            Update Notice
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