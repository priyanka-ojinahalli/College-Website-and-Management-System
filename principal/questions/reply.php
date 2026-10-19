<?php

session_start();

require_once "../../config/database.php";
require_once "../../config/email.php";

/* Principal access only */
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "principal") {
    header("Location: ../../auth/login.php");
    exit;
}

/* Get question ID */
$id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

/* Get question details */
$stmt = $conn->prepare("
    SELECT id, name, email, phone, subject, question, response, status, created_at
    FROM principal_questions
    WHERE id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$question = $result->fetch_assoc();

$stmt->close();

if (!$question) {
    header("Location: index.php");
    exit;
}

$error = "";
$success = "";

/* Handle response submission */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $response = trim($_POST["response"] ?? "");

    if ($response === "") {

        $error = "Please enter a response.";

    } else {

        /*
         * Save response in database
         */
        $update = $conn->prepare("
            UPDATE principal_questions
            SET response = ?,
                status = 'Answered',
                responded_at = NOW()
            WHERE id = ?
        ");

        $update->bind_param("si", $response, $id);

        if ($update->execute()) {

            $update->close();

            /*
             * Send email to the person who asked the question
             */
            $emailSubject = "Response from Smt. Sharadamma V. Kotbal College";

            $emailBody = "
                <div style='font-family: Arial, sans-serif; line-height: 1.6;'>

                    <h2 style='color:#172554;'>
                        Smt. Sharadamma V. Kotbal College
                    </h2>

                    <p>Dear " . htmlspecialchars($question["name"]) . ",</p>

                    <p>
                        Thank you for contacting our Principal.
                    </p>

                    <p>
                        <strong>Your Question:</strong><br>
                        " . nl2br(htmlspecialchars($question["question"])) . "
                    </p>

                    <p>
                        <strong>Response from the Principal:</strong><br>
                        " . nl2br(htmlspecialchars($response)) . "
                    </p>

                    <p>
                        Regards,<br>
                        <strong>Principal</strong><br>
                        Smt. Sharadamma V. Kotbal BBA, BCA &amp; B.Com College<br>
                        Koppal, Karnataka
                    </p>

                </div>
            ";

            $emailSent = sendEmail(
                $question["email"],
                $emailSubject,
                $emailBody
            );

            if ($emailSent) {
                $success = "Response saved and email sent successfully.";
            } else {
                $success = "Response saved successfully, but the email could not be sent.";
            }

            /*
             * Refresh question data
             */
            $stmt = $conn->prepare("
                SELECT id, name, email, phone, subject, question, response, status, created_at
                FROM principal_questions
                WHERE id = ?
            ");

            $stmt->bind_param("i", $id);
            $stmt->execute();

            $result = $stmt->get_result();
            $question = $result->fetch_assoc();

            $stmt->close();

        } else {

            $update->close();

            $error = "Unable to save the response. Please try again.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Reply to Question</title>

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
}

.page-heading p {
    margin: 0;
    color: #64748b;
}

.question-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 4px 12px rgba(15,23,42,.05);
}

.question-box h2 {
    margin-top: 0;
    color: #172554;
}

.question-details {
    display: grid;
    grid-template-columns: 150px 1fr;
    gap: 12px;
    margin-top: 20px;
}

.question-details strong {
    color: #475569;
}

.question-text {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 15px;
    line-height: 1.6;
    white-space: normal;
}

.form-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 4px 12px rgba(15,23,42,.05);
}

.form-box h2 {
    margin-top: 0;
    color: #172554;
}

textarea {
    width: 100%;
    min-height: 180px;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    resize: vertical;
    font-family: Arial, sans-serif;
    font-size: 14px;
    box-sizing: border-box;
}

textarea:focus {
    outline: none;
    border-color: #172554;
}

.button-row {
    margin-top: 18px;
    display: flex;
    gap: 10px;
}

.btn {
    display: inline-block;
    padding: 10px 18px;
    border-radius: 6px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.btn-primary {
    background: #172554;
    color: #ffffff;
}

.btn-primary:hover {
    background: #1e3a8a;
}

.btn-secondary {
    background: #e2e8f0;
    color: #172554;
}

.alert {
    padding: 12px 15px;
    border-radius: 7px;
    margin-bottom: 20px;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-answered {
    background: #dcfce7;
    color: #166534;
}

@media (max-width: 800px) {

    .admin-content {
        margin-left: 0;
        padding: 20px;
    }

    .admin-header {
        margin-left: 0;
        width: 100%;
    }

    .question-details {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<?php include "../includes/principal_sidebar.php"; ?>

<?php include "../includes/principal_header.php"; ?>

<div class="admin-content">

    <div class="page-heading">

        <h1>Reply to Question</h1>

        <p>Respond to the question submitted to the Principal.</p>

    </div>


    <?php if ($success !== ""): ?>

        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <div class="question-box">

        <h2>
            <?php echo htmlspecialchars($question["subject"]); ?>
        </h2>

        <div class="question-details">

            <strong>Name:</strong>
            <span><?php echo htmlspecialchars($question["name"]); ?></span>

            <strong>Email:</strong>
            <span><?php echo htmlspecialchars($question["email"]); ?></span>

            <strong>Phone:</strong>
            <span>
                <?php
                echo $question["phone"]
                    ? htmlspecialchars($question["phone"])
                    : "Not provided";
                ?>
            </span>

            <strong>Status:</strong>
            <span>

                <?php if ($question["status"] === "Answered"): ?>

                    <span class="status-badge status-answered">
                        Answered
                    </span>

                <?php else: ?>

                    <span class="status-badge status-pending">
                        Pending
                    </span>

                <?php endif; ?>

            </span>

            <strong>Question:</strong>

            <div class="question-text">
                <?php echo nl2br(htmlspecialchars($question["question"])); ?>
            </div>

        </div>

    </div>


    <div class="form-box">

        <h2>
            <?php
            echo $question["status"] === "Answered"
                ? "Update Response"
                : "Write Response";
            ?>
        </h2>

        <form method="POST">

            <textarea
                name="response"
                placeholder="Type your response to the student..."
                required
            ><?php echo htmlspecialchars($question["response"] ?? ""); ?></textarea>

            <div class="button-row">

                <button type="submit" class="btn btn-primary">
                    <?php
                    echo $question["status"] === "Answered"
                        ? "Update Response & Send Email"
                        : "Send Response & Email";
                    ?>
                </button>

                <a href="index.php" class="btn btn-secondary">
                    Back
                </a>

            </div>

        </form>

    </div>

</div>



</body>

</html>