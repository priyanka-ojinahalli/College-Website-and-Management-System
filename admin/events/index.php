<?php

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../../auth/login.php");
    exit();
}

require_once "../../config/database.php";


/* =========================
   DELETE EVENT
========================= */

if (isset($_GET["delete"])) {

    $id = intval($_GET["delete"]);

    $stmt = $conn->prepare(
        "DELETE FROM events WHERE id = ?"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: index.php?deleted=1");
    exit();
}


/* =========================
   GET EVENTS
========================= */

$result = $conn->query(
    "SELECT *
     FROM events
     ORDER BY event_date DESC, id DESC"
);

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Manage Events - Admin</title>

    <link rel="stylesheet"
          href="../../css/admin.css?v=3">

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


            <div class="page-top">

                <div>

                    <h2>Manage Events</h2>

                    <p class="welcome">
                        Add, view and manage college events.
                    </p>

                </div>


                <a
                    href="add.php"
                    class="btn-primary"
                >
                    + Add Event
                </a>

            </div>


            <br>


            <?php if (isset($_GET["deleted"])): ?>

                <div class="alert success">
                    Event deleted successfully.
                </div>

            <?php endif; ?>


            <?php if (isset($_GET["updated"])): ?>

                <div class="alert success">
                    Event updated successfully.
                </div>

            <?php endif; ?>


            <?php if (isset($_GET["success"])): ?>

                <div class="alert success">
                    Event added successfully.
                </div>

            <?php endif; ?>


            <!-- EVENT TABLE -->

            <div class="table-card">

                <table class="admin-table">

                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Event</th>

                            <th>Date</th>

                            <th>Location</th>

                            <th>Created</th>

                            <th>Actions</th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if ($result->num_rows > 0): ?>


                        <?php while ($event = $result->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?php echo $event["id"]; ?>
                                </td>


                                <td>

                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            $event["title"]
                                        );
                                        ?>
                                    </strong>

                                    <br>

                                    <small>

                                        <?php
                                        echo htmlspecialchars(
                                            substr(
                                                $event["description"],
                                                0,
                                                80
                                            )
                                        );
                                        ?>

                                        <?php
                                        if (strlen($event["description"]) > 80) {
                                            echo "...";
                                        }
                                        ?>

                                    </small>

                                </td>


                                <td>

                                    <?php
                                    echo date(
                                        "d M Y",
                                        strtotime(
                                            $event["event_date"]
                                        )
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo htmlspecialchars(
                                        $event["location"]
                                    );
                                    ?>

                                </td>


                                <td>

                                    <?php
                                    echo date(
                                        "d M Y",
                                        strtotime(
                                            $event["created_at"]
                                        )
                                    );
                                    ?>

                                </td>


                                <td>

                                    <a
                                        href="edit.php?id=<?php echo $event["id"]; ?>"
                                        class="action-edit"
                                    >
                                        Edit
                                    </a>


                                    <a
                                        href="index.php?delete=<?php echo $event["id"]; ?>"
                                        class="action-delete"
                                        onclick="return confirm('Are you sure you want to delete this event?');"
                                    >
                                        Delete
                                    </a>

                                </td>

                            </tr>

                        <?php endwhile; ?>


                    <?php else: ?>

                        <tr>

                            <td
                                colspan="6"
                                class="no-data"
                            >
                                No events found.

                            </td>

                        </tr>

                    <?php endif; ?>


                    </tbody>

                </table>

            </div>


        </div>


    </div>


</div>


</body>

</html>