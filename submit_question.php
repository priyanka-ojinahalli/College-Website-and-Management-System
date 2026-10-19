<?php

require_once "config/database.php";

// Only allow POST requests
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: contact.php");
    exit;
}

// Get form data
$name     = trim($_POST["name"] ?? "");
$email    = trim($_POST["email"] ?? "");
$phone    = trim($_POST["phone"] ?? "");
$subject  = trim($_POST["subject"] ?? "");
$question = trim($_POST["question"] ?? "");

// Validate required fields
if ($name === "" || $email === "" || $subject === "" || $question === "") {
    header("Location: contact.php?error=Please fill all required fields.");
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: contact.php?error=Please enter a valid email address.");
    exit;
}

// Validate phone if provided
if ($phone !== "" && !preg_match("/^[0-9]{10}$/", $phone)) {
    header("Location: contact.php?error=Please enter a valid 10-digit phone number.");
    exit;
}

// Insert question using prepared statement
$sql = "INSERT INTO principal_questions 
        (name, email, phone, subject, question)
        VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    header("Location: contact.php?error=Something went wrong. Please try again.");
    exit;
}

$stmt->bind_param(
    "sssss",
    $name,
    $email,
    $phone,
    $subject,
    $question
);

if ($stmt->execute()) {

    $stmt->close();

    // Successfully submitted
    header("Location: contact.php?success=Your question has been sent to the Principal successfully.");
    exit;

} else {

    $stmt->close();

    header("Location: contact.php?error=Unable to send your question. Please try again.");
    exit;
}
?>