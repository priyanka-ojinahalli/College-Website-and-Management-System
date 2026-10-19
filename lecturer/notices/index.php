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


/* =========================
   DELETE NOTICE
========================= */

if (isset($_GET["delete"])) {

    $notice_id = (int) $_GET["delete"];

    $stmt = $conn->prepare(
        "DELETE FROM notices
         WHERE id = ?
         AND created_by = ?
         AND created_role = 'lecturer'"
    );

    $stmt->bind_param("ii", $notice_id, $lecturer_id);

    $stmt->execute();

    $stmt->close();

    header("Location: index.php?deleted=1");
    exit();
}


/* =========================
   FETCH NOTICES
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

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Notices</title>

    <link rel="stylesheet" href="../../css/admin.css?v=4">

    <style>

        /* =================================
           LECTURER NOTICES PAGE
        ================================= */

        .notices-page {
            padding: 32px;
            background: #f4f6fa;
            min-height: calc(100vh - 70px);
        }


        /* Page heading */

        .notices-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .notices-heading h2 {
            margin: 0 0 8px;
            font-size: 32px;
            color: #111827;
        }

        .notices-heading p {
            margin: 0;
            font-size: 18px;
            color: #64748b;
        }


        /* Add button */

        .add-notice-btn {
            display: inline-block;
            background: #172554;
            color: white;
            text-decoration: none;
            padding: 15px 25px;
            border-radius: 7px;
            font-size: 17px;
            font-weight: 600;
            transition: 0.2s;
        }

        .add-notice-btn:hover {
            background: #1e3a8a;
        }


        /* Success message */

        .success-message {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
            padding: 12px 16px;
            border-radius: 7px;
            margin-bottom: 20px;
        }


        /* Main white card */

        .notices-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            overflow-x: auto;
        }


        /* Table */

        .notices-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }


        /* Table heading */

        .notices-table thead th {
            background: #172554;
            color: #ffffff;
            padding: 16px 18px;
            text-align: left;
            font-size: 17px;
            font-weight: 600;
        }


        .notices-table thead th:first-child {
            border-top-left-radius: 2px;
        }

        .notices-table thead th:last-child {
            border-top-right-radius: 2px;
        }


        /* Table rows */

        .notices-table tbody tr {
            border-bottom: 1px solid #e2e8f0;
        }

        .notices-table tbody tr:last-child {
            border-bottom: none;
        }

        .notices-table tbody tr:hover {
            background: #f8fafc;
        }


        /* Table cells */

        .notices-table tbody td {
            padding: 18px;
            font-size: 16px;
            color: #111827;
            vertical-align: middle;
        }


        /* Notice title */

        .notice-title {
            font-weight: 700;
            font-size: 17px;
            color: #111827;
            margin-bottom: 4px;
        }


        /* Notice description */

        .notice-description {
            color: #64748b;
            font-size: 14px;

            max-width: 380px;

            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }


        /* Date */

        .notice-date {
            white-space: nowrap;
        }


        /* Posted by */

        .posted-by {
            font-weight: 500;
        }


        /* Active badge */

        .status-active {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            padding: 7px 13px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }


        /* Inactive badge */

        .status-inactive {
            display: inline-block;
            background: #fee2e2;
            color: #dc2626;
            padding: 7px 13px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }


        /* Action buttons */

        .action-links {
            white-space: nowrap;
        }


        .edit-btn {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            margin-right: 18px;
        }

        .edit-btn:hover {
            text-decoration: underline;
        }


        .delete-btn {
            color: #dc2626;
            text-decoration: none;
            font-weight: 600;
        }

        .delete-btn:hover {
            text-decoration: underline;
        }


        /* View only */

        .view-only {
            color: #64748b;
            font-weight: 500;
        }


        /* Empty table */

        .no-notices {
            text-align: center;
            padding: 35px !important;
            color: #64748b !important;
        }


        /* =================================
           MOBILE
        ================================= */

        @media (max-width: 768px) {

            .notices-page {
                padding: 20px;
            }

            .notices-heading {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .notices-heading h2 {
                font-size: 26px;
            }

            .notices-heading p {
                font-size: 16px;
            }

            .add-notice-btn {
                padding: 12px 18px;
            }

            .notices-card {
                padding: 12px;
            }

        }

    </style>

</head>


<body>


<?php
include "../../includes/lecturer_sidebar.php";

?>


<!-- MAIN CONTENT -->

<div class="main-content">

    <div class="notices-page">


        <!-- PAGE HEADER -->

        <div class="notices-heading">

            <div>

                <h2>Manage Notices</h2>

                <p>
                    Add, view and manage college notices.
                </p>

            </div>


            <a href="add.php" class="add-notice-btn">
                + Add Notice
            </a>

        </div>


        <!-- SUCCESS MESSAGE -->

        <?php if (isset($_GET["deleted"])) { ?>

            <div class="success-message">
                Notice deleted successfully.
            </div>

        <?php } ?>


        <!-- NOTICE CARD -->

        <div class="notices-card">


            <table class="notices-table">


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


                <?php if ($result && $result->num_rows > 0) { ?>


                    <?php while ($notice = $result->fetch_assoc()) { ?>


                        <?php

                        /*
                         * Lecturer can edit/delete
                         * ONLY their own notices.
                         */

                        $is_owner =
                            !empty($notice["created_by"])
                            &&
                            (int)$notice["created_by"] === $lecturer_id
                            &&
                            isset($notice["created_role"])
                            &&
                            $notice["created_role"] === "lecturer";

                        ?>


                        <tr>


                            <!-- ID -->

                            <td>
                                <?php echo (int)$notice["id"]; ?>
                            </td>


                            <!-- TITLE -->

                            <td>

                                <div class="notice-title">

                                    <?php
                                    echo htmlspecialchars(
                                        $notice["title"]
                                    );
                                    ?>

                                </div>


                                <?php if (!empty($notice["description"])) { ?>

                                    <div class="notice-description">

                                        <?php
                                        echo htmlspecialchars(
                                            $notice["description"]
                                        );
                                        ?>

                                    </div>

                                <?php } ?>

                            </td>


                            <!-- DATE -->

                            <td class="notice-date">

                                <?php

                                echo date(
                                    "d M Y",
                                    strtotime($notice["notice_date"])
                                );

                                ?>

                            </td>


                            <!-- POSTED BY -->

                            <td class="posted-by">

                                <?php

                                if (!empty($notice["created_role"])) {

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

                                <?php

                                if (
                                    strtolower(
                                        $notice["status"] ?? ""
                                    ) === "active"
                                ) {

                                    echo '<span class="status-active">
                                            Active
                                          </span>';

                                } else {

                                    echo '<span class="status-inactive">
                                            Inactive
                                          </span>';

                                }

                                ?>

                            </td>


                            <!-- CREATED -->

                            <td>

                                <?php

                                echo date(
                                    "d M Y",
                                    strtotime($notice["created_at"])
                                );

                                ?>

                            </td>


                            <!-- ACTIONS -->

                            <td class="action-links">


                                <?php if ($is_owner) { ?>


                                    <a
                                        href="edit.php?id=<?php echo (int)$notice["id"]; ?>"
                                        class="edit-btn"
                                    >
                                        Edit
                                    </a>


                                    <a
                                        href="index.php?delete=<?php echo (int)$notice["id"]; ?>"
                                        class="delete-btn"
                                        onclick="return confirm('Are you sure you want to delete this notice?');"
                                    >
                                        Delete
                                    </a>


                                <?php } else { ?>


                                    <span class="view-only">
                                        View Only
                                    </span>


                                <?php } ?>


                            </td>


                        </tr>


                    <?php } ?>


                <?php } else { ?>


                    <tr>

                        <td
                            colspan="7"
                            class="no-notices"
                        >
                            No notices found.
                        </td>

                    </tr>


                <?php } ?>


                </tbody>


            </table>


        </div>


    </div>

</div>


<?php

?>


</body>

</html>