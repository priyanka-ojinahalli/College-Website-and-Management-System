<?php
session_start();

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'lecturer') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$userId = (int)$_SESSION['user_id'];
$lecturerId = 0;
$stmt = $conn->prepare('SELECT id FROM lecturers WHERE user_id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) $lecturerId = (int)$row['id'];
    $stmt->close();
}
if ($lecturerId <= 0) {
    http_response_code(403);
    exit('Lecturer account not found.');
}

$submissionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$submissionId || $submissionId <= 0) {
    http_response_code(400);
    exit('Invalid submission.');
}

$stmt = $conn->prepare(
    'SELECT subm.file_path, subm.original_filename
     FROM assignment_submissions subm
     INNER JOIN assignments a ON a.id = subm.assignment_id
     WHERE subm.id = ? AND a.lecturer_id = ?
     LIMIT 1'
);
if (!$stmt) {
    http_response_code(500);
    exit('Could not prepare the download request.');
}
$stmt->bind_param('ii', $submissionId, $lecturerId);
$stmt->execute();
$fileRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fileRow) {
    http_response_code(404);
    exit('Submission not found or you do not have permission to access it.');
}

$relativePath = (string)$fileRow['file_path'];
if (strpos($relativePath, 'submissions/') !== 0) {
    http_response_code(404);
    exit('The stored file path is invalid.');
}
$fileName = basename(substr($relativePath, strlen('submissions/')));
if ($fileName === '' || $fileName === '.' || $fileName === '..') {
    http_response_code(404);
    exit('The stored file path is invalid.');
}

$projectRoot = dirname(__DIR__, 2);
$submissionRoot = $projectRoot . '/uploads/learning/submissions';
$realRoot = realpath($submissionRoot);
$realFile = realpath($submissionRoot . '/' . $fileName);
if (!$realRoot || !$realFile || !is_file($realFile) || strpos($realFile, $realRoot . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    exit('The submitted file could not be found.');
}

$originalName = basename(str_replace('\\', '/', (string)$fileRow['original_filename']));
$originalName = preg_replace('/[\r\n\x00-\x1F\x7F]/u', '', $originalName) ?: 'submission';
$asciiName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName) ?: 'submission';
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$mimeTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'txt' => 'text/plain'
];

if (!isset($mimeTypes[$extension])) {
    http_response_code(415);
    exit('This file type is not supported for download.');
}

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mimeTypes[$extension]);
header('Content-Length: ' . (string)filesize($realFile));
header('Content-Disposition: attachment; filename="' . addcslashes($asciiName, '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($originalName));
header('Cache-Control: private, no-store, no-cache, must-revalidate');
readfile($realFile);
exit;
