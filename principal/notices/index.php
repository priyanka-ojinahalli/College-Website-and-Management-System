<?php

session_start();

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "principal"
) {
    header("Location: ../../auth/login.php");
    exit();
}

require_once "../../config/database.php";


/* =========================
   PRINCIPAL USER ID
========================= */

$principal_id = (int) $_SESSION["user_id"];


/* =========================
   DELETE NOTICE
   ONLY OWN NOTICE
========================= */

if (isset($_GET["delete"]) && is_numeric($_GET["delete"])) {

    $id = (int) $_GET["delete"];

    $stmt = $conn->prepare(
        "DELETE FROM notices
         WHERE id = ?
         AND created_by = ?
         AND created_role = 'principal'"
    );

    $stmt->bind_param(
        "ii",
        $id,
        $principal_id
    );

    $stmt->execute();

    $stmt->close();

    header("Location: index.php?deleted=1");
    exit();
}


/* =========================
   GET ALL NOTICES
========================= */

$result = $conn->query(
    "SELECT *
     FROM notices
     ORDER BY notice_date DESC, id DESC"
);

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Manage Notices - Principal</title>

    <link
        rel="stylesheet"
        href="../../css/admin.css?v=4"
    >

</head>


<body>


<div class="dashboard">


    <!-- =========================
         SIDEBAR
    ========================= -->

    <?php include "../includes/principal_sidebar.php"; ?>


    <!-- =========================
         MAIN CONTENT
    ========================= -->

    <div class="main-content">

        <!-- CONTENT -->

        <div class="content">


            <div class="page-top">


                <div>

                    <h2>Manage Notices</h2>

                    <p class="welcome">
                        Add, view and manage college notices.
                    </p>

                </div>


                <a
                    href="add.php"
                    class="btn-primary"
                >
                    + Add Notice
                </a>


            </div>


            <br>


            <?php if (isset($_GET["success"])): ?>

                <div class="alert success">

                    Notice added successfully.

                </div>

            <?php endif; ?>


            <?php if (isset($_GET["deleted"])): ?>

                <div class="alert success">

                    Notice deleted successfully.

                </div>

            <?php endif; ?>


            <?php if (isset($_GET["updated"])): ?>

                <div class="alert success">

                    Notice updated successfully.

                </div>

            <?php endif; ?>


            <!-- =========================
                 NOTICE TABLE
            ========================= -->

            <div class="table-card">


                <table class="admin-table">


                    <thead>

                        <tr>

                            <th>ID</th>

                            <th>Title</th>

                            <th>Date</th>

                            <th>Posted By</th>

                            <th>Status</th>

                            <th>Created</th>

                            <th>Actions</th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if ($result && $result->num_rows > 0): ?>


                        <?php while ($notice = $result->fetch_assoc()): ?>


                            <tr>


                                <!-- ID -->

                                <td>

                                    <?php
                                    echo (int) $notice["id"];
                                    ?>

                                </td>


                                <!-- TITLE -->

                                <td>

                                    <strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $notice["title"]
                                        );
                                        ?>

                                    </strong>

                                    <br>

                                    <small>

                                        <?php
                                        echo htmlspecialchars(
                                            substr(
                                                $notice["description"],
                                                0,
                                                80
                                            )
                                        );
                                        ?>...

                                    </small>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?php
                                    echo date(
                                        "d M Y",
                                        strtotime(
                                            $notice["notice_date"]
                                        )
                                    );
                                    ?>

                                </td>


                                <!-- POSTED BY -->

                                <td>

                                    <?php

                                    if (
                                        !empty(
                                            $notice["created_role"]
                                        )
                                    ) {

                                        echo htmlspecialchars(
                                            ucfirst(
                                                $notice["created_role"]
                                            )
                                        );

                                    } else {

                                        echo "Admin";

                                    }

                                    ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <?php if (
                                        $notice["status"] === "active"
                                    ): ?>

                                        <span class="status-active">

                                            Active

                                        </span>

                                    <?php else: ?>

                                        <span class="status-inactive">

                                            Inactive

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- CREATED -->

                                <td>

                                    <?php
                                    echo date(
                                        "d M Y",
                                        strtotime(
                                            $notice["created_at"]
                                        )
                                    );
                                    ?>

                                </td>


                                <!-- ACTIONS -->

                                <td>


                                    <?php

                                    /*
                                     * Check whether this notice
                                     * belongs to the logged-in Principal.
                                     */

                                    $is_owner =
                                        (
                                            !empty(
                                                $notice["created_by"]
                                            )
                                            &&
                                            (int) $notice["created_by"]
                                                === $principal_id
                                            &&
                                            isset(
                                                $notice["created_role"]
                                            )
                                            &&
                                            $notice["created_role"]
                                                === "principal"
                                        );

                                    ?>


                                    <?php if ($is_owner): ?>


                                        <!-- EDIT -->

                                        <a
                                            href="edit.php?id=<?php echo (int) $notice["id"]; ?>"
                                            class="action-edit"
                                        >
                                            Edit
                                        </a>


                                        <!-- DELETE -->

                                        <a
                                            href="index.php?delete=<?php echo (int) $notice["id"]; ?>"
                                            class="action-delete"
                                            onclick="return confirm('Are you sure you want to delete this notice?');"
                                        >
                                            Delete
                                        </a>


                                    <?php else: ?>


                                        <span
                                            style="
                                                color: #777;
                                                font-size: 13px;
                                            "
                                        >
                                            View Only
                                        </span>


                                    <?php endif; ?>


                                </td>


                            </tr>


                        <?php endwhile; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="7"
                                class="no-data"
                            >

                                No notices found.

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