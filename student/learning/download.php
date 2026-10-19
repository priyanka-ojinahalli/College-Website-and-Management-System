<?php
session_start();

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$userId = (int)$_SESSION['user_id'];
$student = null;
$stmt = $conn->prepare('SELECT id, course_id, semester, student_status FROM students WHERE user_id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}
if (!$student || ($student['student_status'] ?? '') !== 'Active') {
    http_response_code(403);
    exit('Access denied.');
}

$courseId = (int)($student['course_id'] ?? 0);
$semester = (int)($student['semester'] ?? 0);
$type = (string)($_GET['type'] ?? '');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($courseId <= 0 || $semester <= 0 || !$id || $id <= 0 || !in_array($type, ['assignment', 'material'], true)) {
    http_response_code(400);
    exit('Invalid download request.');
}

$relativePath = '';
$originalName = '';
if ($type === 'assignment') {
    $stmt = $conn->prepare(
        'SELECT a.attachment_path AS file_path, a.attachment_original_name AS original_name
         FROM assignments a
         INNER JOIN subjects s ON s.id = a.subject_id
         WHERE a.id = ? AND s.course_id = ? AND s.semester = ?
           AND a.attachment_path IS NOT NULL AND a.attachment_path <> \'\'
         LIMIT 1'
    );
} else {
    $stmt = $conn->prepare(
        'SELECT m.file_path, m.original_filename AS original_name
         FROM study_materials m
         INNER JOIN subjects s ON s.id = m.subject_id
         WHERE m.id = ? AND s.course_id = ? AND s.semester = ?
         LIMIT 1'
    );
}
if ($stmt) {
    $stmt->bind_param('iii', $id, $courseId, $semester);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        $relativePath = (string)$row['file_path'];
        $originalName = (string)$row['original_name'];
    }
}
if ($relativePath === '' || $originalName === '') {
    http_response_code(404);
    exit('This file was not found or is not available to your course and semester.');
}

$expectedFolder = $type === 'assignment' ? 'assignments/' : 'materials/';
if (strpos($relativePath, $expectedFolder) !== 0 || basename($relativePath) !== substr($relativePath, strlen($expectedFolder))) {
    http_response_code(403);
    exit('Invalid file path.');
}

$storageRoot = realpath(dirname(__DIR__, 2) . '/uploads/learning');
$filePath = $storageRoot ? realpath($storageRoot . DIRECTORY_SEPARATOR . $relativePath) : false;
if (!$storageRoot || !$filePath || strpos($filePath, $storageRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit('The file is missing or cannot be accessed.');
}

$downloadName = basename(str_replace('\\', '/', $originalName));
$downloadName = preg_replace('/[\r\n"\\\\]/', '_', $downloadName) ?: 'download';
$mime = 'application/octet-stream';
if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detected = $finfo->file($filePath);
    if (is_string($detected) && $detected !== '') $mime = $detected;
}

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($filePath));
header('Content-Disposition: attachment; filename="' . $downloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
readfile($filePath);
exit;
