<?php

session_start();

require_once "../../config/database.php";

/* Principal access only */
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "principal") {
    header("Location: ../../auth/login.php");
    exit;
}

/* Get all questions */
$sql = "
    SELECT
        id,
        name,
        email,
        phone,
        subject,
        question,
        response,
        status,
        created_at,
        responded_at
    FROM principal_questions
    ORDER BY created_at DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Database Error: " . $conn->error);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Ask the Principal - Principal</title>

    <link rel="stylesheet" href="../../css/admin.css">

    <style>

        .admin-content {
            margin-left: 250px;
            padding: 30px;
            min-height: calc(100vh - 95px);
            background: #f8fafc;
            box-sizing: border-box;
        }

        .admin-header {
            margin-left: 250px;
            width: calc(100% - 250px);
            height: 95px;
            box-sizing: border-box;

            display: flex;
            justify-content: space-between;
            align-items: center;

            padding: 15px 30px;

            background: #ffffff;
        }

        .header-college h1 {
            margin: 0;
            font-size: 28px;
            color: #172554;
        }

        .header-college p {
            margin: 5px 0 0;
            color: #475569;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 8px;

            font-size: 16px;
            color: #172554;

            white-space: nowrap;
        }

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h1 {
            margin: 0 0 5px;
            color: #172554;
            font-size: 28px;
        }

        .page-heading p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        /* Main box */

        .questions-box {
            background: #ffffff;

            border: 1px solid #e2e8f0;

            border-radius: 10px;

            padding: 25px;

            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);

            overflow-x: auto;
        }

        /* Table */

        .questions-table {
            width: 100%;

            border-collapse: collapse;

            min-width: 1100px;
        }

        .questions-table th {
            background: #172554;

            color: #ffffff;

            padding: 13px 12px;

            text-align: left;

            font-size: 14px;
        }

        .questions-table td {
            padding: 14px 12px;

            border-bottom: 1px solid #e2e8f0;

            color: #334155;

            font-size: 14px;

            vertical-align: top;
        }

        .questions-table tbody tr:hover {
            background: #f8fafc;
        }

        /* Name */

        .person-name {
            font-weight: 600;

            color: #172554;
        }

        /* Subject */

        .question-subject {
            font-weight: 600;

            color: #334155;
        }

        /* Question */

        .question-text {
            max-width: 300px;

            line-height: 1.5;

            white-space: pre-wrap;
        }

        /* Response */

        .response-text {
            max-width: 300px;

            line-height: 1.5;

            white-space: pre-wrap;
        }

        .no-response {
            color: #94a3b8;

            font-style: italic;
        }

        /* Status */

        .status {
            display: inline-block;

            padding: 5px 10px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: 600;
        }

        .status-pending {
            background: #fff7ed;

            color: #c2410c;

            border: 1px solid #fed7aa;
        }

        .status-answered {
            background: #ecfdf5;

            color: #047857;

            border: 1px solid #a7f3d0;
        }

        /* Empty */

        .empty-message {
            text-align: center;

            padding: 50px 20px;

            color: #64748b;
        }

        .empty-message h3 {
            margin-bottom: 8px;

            color: #172554;
        }
        .btn-reply {
    display: inline-block;
    padding: 7px 12px;
    background: #172554;
    color: #ffffff;
    text-decoration: none;
    border-radius: 5px;
    font-size: 13px;
}

.btn-reply:hover {
    background: #1e3a8a;
}

        /* Mobile */

        @media (max-width: 800px) {

            .admin-content {
                margin-left: 0;

                padding: 20px;
            }

            .admin-header {
                margin-left: 0;

                width: 100%;
            }

        }

    </style>

</head>


<body>


<?php include "../includes/principal_sidebar.php"; ?>

<?php include "../includes/principal_header.php"; ?>


<div class="admin-content">


    <!-- Page Heading -->

    <div class="page-heading">

        <h1>Ask the Principal</h1>

        <p>
            View questions and messages submitted through the college website.
        </p>

    </div>


    <!-- Questions -->

    <div class="questions-box">


        <?php if ($result->num_rows > 0): ?>


            <table class="questions-table">


                <thead>

                    <tr>

                        <th>Sl. No.</th>

                        <th>Name</th>

                        <th>Email</th>

                        <th>Phone</th>

                        <th>Subject</th>

                        <th>Question</th>

                        <th>Response</th>

                        <th>Status</th>
                        <th>Action</th>

                        <th>Submitted On</th>

                    </tr>

                </thead>


                <tbody>


                <?php

                $sl = 1;

                while ($row = $result->fetch_assoc()):

                ?>


                    <tr>


                        <!-- Serial Number -->

                        <td>

                            <?php echo $sl++; ?>

                        </td>


                        <!-- Name -->

                        <td>

                            <span class="person-name">

                                <?php

                                echo htmlspecialchars(
                                    $row["name"]
                                );

                                ?>

                            </span>

                        </td>


                        <!-- Email -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $row["email"]
                            );

                            ?>

                        </td>


                        <!-- Phone -->

                        <td>

                            <?php

                            if (!empty($row["phone"])) {

                                echo htmlspecialchars(
                                    $row["phone"]
                                );

                            } else {

                                echo '<span class="no-response">
                                        Not provided
                                      </span>';

                            }

                            ?>

                        </td>


                        <!-- Subject -->

                        <td>

                            <span class="question-subject">

                                <?php

                                echo htmlspecialchars(
                                    $row["subject"]
                                );

                                ?>

                            </span>

                        </td>


                        <!-- Question -->

                        <td>

                            <div class="question-text">

                                <?php

                                echo nl2br(
                                    htmlspecialchars(
                                        $row["question"]
                                    )
                                );

                                ?>

                            </div>

                        </td>


                        <!-- Response -->

                        <td>

                            <?php

                            if (!empty($row["response"])):

                            ?>

                                <div class="response-text">

                                    <?php

                                    echo nl2br(
                                        htmlspecialchars(
                                            $row["response"]
                                        )
                                    );

                                    ?>

                                </div>

                            <?php else: ?>

                                <span class="no-response">

                                    No response yet

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Status -->

                        <td>

                            <?php

                            if ($row["status"] === "Answered") {

                                echo '<span class="status status-answered">
                                        Answered
                                      </span>';

                            } else {

                                echo '<span class="status status-pending">
                                        Pending
                                      </span>';

                            }

                            ?>

                        </td>
                        <td>
    <a href="reply.php?id=<?php echo (int)$row["id"]; ?>"
       class="btn-reply">
        <?php echo $row["status"] === "Answered" ? "View / Reply" : "Reply"; ?>
    </a>
</td>


                        <!-- Submitted Date -->

                        <td>

                            <?php

                            echo date(
                                "d M Y, h:i A",
                                strtotime($row["created_at"])
                            );

                            ?>

                        </td>


                    </tr>


                <?php endwhile; ?>


                </tbody>


            </table>


        <?php else: ?>


            <div class="empty-message">

                <h3>No Questions Found</h3>

                <p>
                    No questions have been submitted to the Principal yet.
                </p>

            </div>


        <?php endif; ?>


    </div>


</div>

</body>

</html>