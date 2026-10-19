<?php
session_start();

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: ../../auth/login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$userId = (int)$_SESSION['user_id'];
$student = null;
$errors = [];
$success = (string)($_SESSION['student_learning_flash'] ?? '');
unset($_SESSION['student_learning_flash']);

if (empty($_SESSION['student_learning_csrf'])) {
    $_SESSION['student_learning_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['student_learning_csrf'];

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function safeOriginalName(string $name): string {
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\r\n\x00-\x1F\x7F]/u', '', $name) ?? 'download';
    return mb_substr($name, 0, 255);
}

/* Resolve the logged-in account to its student record. */
$stmt = $conn->prepare(
    'SELECT id, registration_no, full_name, course_id, semester, student_status
     FROM students WHERE user_id = ? LIMIT 1'
);
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc() ?: null;
    $stmt->close();
}

if (!$student) {
    http_response_code(403);
    exit('Your student account is not linked to a student record. Please contact the college administrator.');
}
if (($student['student_status'] ?? '') !== 'Active') {
    http_response_code(403);
    exit('This student account is not currently active. Please contact the college administrator.');
}

$studentId = (int)$student['id'];
$courseId = (int)($student['course_id'] ?? 0);
$semester = (int)($student['semester'] ?? 0);

$projectRoot = dirname(__DIR__, 2);
$storageRoot = $projectRoot . '/uploads/learning';
$submissionStorage = $storageRoot . '/submissions';
$allowedExtensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt'];
$maxFileSize = 15 * 1024 * 1024;

/* Uploaded learning files are served through download.php, not by direct URL. */
if (!is_dir($submissionStorage) && !mkdir($submissionStorage, 0750, true) && !is_dir($submissionStorage)) {
    $errors[] = 'The submission folder could not be created. Check the uploads folder permissions.';
}
$denyFile = $storageRoot . '/.htaccess';
if (is_dir($storageRoot) && !file_exists($denyFile)) {
    if (@file_put_contents($denyFile, "Options -Indexes\nRequire all denied\n") === false) {
        $errors[] = 'File storage could not be protected. Ask the administrator to check the upload-folder permissions.';
    }
}

function validateStudentUpload(array $file, array $allowedExtensions, int $maxFileSize): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Please choose a valid file.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The selected file is too large.',
            UPLOAD_ERR_NO_FILE => 'Please choose a file to submit.',
            default => 'The file could not be uploaded. Please try again.'
        };
        return ['ok' => false, 'error' => $message];
    }
    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        return ['ok' => false, 'error' => 'The uploaded file could not be verified.'];
    }
    if (($file['size'] ?? 0) <= 0 || $file['size'] > $maxFileSize) {
        return ['ok' => false, 'error' => 'The file must be no larger than 15 MB.'];
    }

    $originalName = safeOriginalName((string)($file['name'] ?? 'submission'));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        return ['ok' => false, 'error' => 'Allowed file types are PDF, DOC, DOCX, PPT, PPTX, and TXT.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowedMimes = [
        'pdf' => ['application/pdf', 'application/octet-stream'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
        'txt' => ['text/plain', 'application/octet-stream']
    ];
    if (!in_array($mime, $allowedMimes[$extension] ?? [], true)) {
        return ['ok' => false, 'error' => 'The file content does not match its file extension.'];
    }

    try {
        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'A secure filename could not be generated.'];
    }

    return [
        'ok' => true,
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'extension' => $extension
    ];
}

function deleteStoredSubmission(string $relativePath, string $submissionStorage): void {
    $fileName = basename(str_replace('\\', '/', $relativePath));
    $candidate = $submissionStorage . '/' . $fileName;
    $realRoot = realpath($submissionStorage);
    $realFile = realpath($candidate);
    if ($realRoot && $realFile && strpos($realFile, $realRoot . DIRECTORY_SEPARATOR) === 0 && is_file($realFile)) {
        @unlink($realFile);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $submittedToken)) {
        $errors[] = 'Your form has expired. Refresh the page and try again.';
    } elseif ($errors) {
        // Do not accept uploads if the storage protection failed.
    } else {
        $assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
        if (!$assignmentId || $assignmentId <= 0) {
            $errors[] = 'Invalid assignment selected.';
        }

        $assignment = null;
        if (!$errors) {
            $stmt = $conn->prepare(
                'SELECT a.id, a.title, a.due_date
                 FROM assignments a
                 INNER JOIN subjects sub ON sub.id = a.subject_id
                 WHERE a.id = ? AND sub.course_id = ? AND sub.semester = ?
                 LIMIT 1'
            );
            if ($stmt) {
                $stmt->bind_param('iii', $assignmentId, $courseId, $semester);
                $stmt->execute();
                $assignment = $stmt->get_result()->fetch_assoc() ?: null;
                $stmt->close();
            }
            if (!$assignment) {
                $errors[] = 'That assignment is not available for your course and semester.';
            }
        }

        if (!$errors) {
            $validation = validateStudentUpload($_FILES['submission_file'] ?? [], $allowedExtensions, $maxFileSize);
            if (!$validation['ok']) {
                $errors[] = $validation['error'];
            } else {
                $storedName = $validation['stored_name'];
                $absolutePath = $submissionStorage . '/' . $storedName;
                $relativePath = 'submissions/' . $storedName;

                if (!move_uploaded_file($_FILES['submission_file']['tmp_name'], $absolutePath)) {
                    $errors[] = 'The server could not save your submission. Try again.';
                } else {
                    $previousPath = '';
                    $oldStmt = $conn->prepare('SELECT file_path FROM assignment_submissions WHERE assignment_id = ? AND student_id = ? LIMIT 1');
                    if ($oldStmt) {
                        $oldStmt->bind_param('ii', $assignmentId, $studentId);
                        $oldStmt->execute();
                        $oldRow = $oldStmt->get_result()->fetch_assoc();
                        if ($oldRow) $previousPath = (string)$oldRow['file_path'];
                        $oldStmt->close();
                    }

                    $originalName = $validation['original_name'];
                    $stmt = $conn->prepare(
                        'INSERT INTO assignment_submissions
                            (assignment_id, student_id, file_path, original_filename, submitted_at)
                         VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                         ON DUPLICATE KEY UPDATE
                            file_path = VALUES(file_path),
                            original_filename = VALUES(original_filename),
                            submitted_at = CURRENT_TIMESTAMP,
                            marks = NULL,
                            feedback = NULL,
                            graded_by = NULL,
                            graded_at = NULL'
                    );
                    if ($stmt) {
                        $stmt->bind_param('iiss', $assignmentId, $studentId, $relativePath, $originalName);
                        if ($stmt->execute()) {
                            $stmt->close();
                            if ($previousPath !== '' && $previousPath !== $relativePath) {
                                deleteStoredSubmission($previousPath, $submissionStorage);
                            }
                            $_SESSION['student_learning_flash'] = 'Your assignment was submitted successfully.';
                            header('Location: index.php');
                            exit;
                        }
                        $stmt->close();
                    }
                    @unlink($absolutePath);
                    $errors[] = 'Your submission could not be saved. Confirm that the assignment_submissions table has the expected columns and unique assignment/student key.';
                }
            }
        }
    }
}

$assignments = [];
$materials = [];
if ($courseId > 0 && $semester > 0) {
    $stmt = $conn->prepare(
        'SELECT a.id, a.title, a.description, a.due_date, a.created_at,
                a.attachment_original_name,
                sub.subject_name, sub.subject_code,
                subm.id AS submission_id, subm.original_filename AS submitted_filename,
                subm.submitted_at, subm.marks, subm.feedback
         FROM assignments a
         INNER JOIN subjects sub ON sub.id = a.subject_id
         LEFT JOIN assignment_submissions subm
            ON subm.assignment_id = a.id AND subm.student_id = ?
         WHERE sub.course_id = ? AND sub.semester = ?
         ORDER BY (a.due_date IS NULL), a.due_date ASC, a.created_at DESC'
    );
    if ($stmt) {
        $stmt->bind_param('iii', $studentId, $courseId, $semester);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $assignments[] = $row;
        $stmt->close();
    }

    $stmt = $conn->prepare(
        'SELECT m.id, m.title, m.description, m.original_filename, m.uploaded_at,
                sub.subject_name, sub.subject_code
         FROM study_materials m
         INNER JOIN subjects sub ON sub.id = m.subject_id
         WHERE sub.course_id = ? AND sub.semester = ?
         ORDER BY m.uploaded_at DESC'
    );
    if ($stmt) {
        $stmt->bind_param('ii', $courseId, $semester);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) $materials[] = $row;
        $stmt->close();
    }
}

function subjectTitle(array $row): string {
    $title = (string)($row['subject_name'] ?? 'Subject');
    if (!empty($row['subject_code'])) $title .= ' (' . $row['subject_code'] . ')';
    return $title;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assignments & Study Materials</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f1f5f9;color:#0f172a;font-family:Arial,Helvetica,sans-serif}.topbar{background:#fff;padding:20px clamp(16px,4vw,38px);box-shadow:0 2px 10px rgba(15,23,42,.08);display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}.topbar h1{margin:0;color:#172554;font-size:clamp(20px,3vw,28px)}.topbar p{margin:6px 0 0;color:#64748b}.btn{display:inline-block;background:#172554;color:#fff;text-decoration:none;border:0;border-radius:7px;padding:10px 14px;font-weight:700;cursor:pointer}.btn:hover{background:#1e40af}.page{max-width:1250px;margin:0 auto;padding:26px 18px 45px}.intro{margin-bottom:20px}.intro h2{margin:0 0 6px}.muted{color:#64748b;font-size:13px;line-height:1.5}.notice{border-radius:8px;padding:13px 15px;margin-bottom:16px}.success{background:#dcfce7;color:#166534}.error{background:#fee2e2;color:#991b1b}.columns{display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start}.card{background:#fff;border-radius:12px;padding:21px;box-shadow:0 3px 14px rgba(15,23,42,.06);margin-bottom:20px;min-width:0}.card h3{color:#172554;margin:0 0 15px}.resource{border:1px solid #e2e8f0;border-radius:9px;padding:15px;margin:12px 0}.resource h4{margin:0 0 8px;font-size:16px}.resource p{margin:7px 0;line-height:1.55;white-space:normal;overflow-wrap:anywhere}.resource-actions{display:flex;gap:9px;flex-wrap:wrap;margin-top:12px}.download{font-size:13px}.field{margin-top:12px}.field label{display:block;font-size:13px;font-weight:700;margin-bottom:6px}.field input[type=file]{display:block;width:100%;max-width:100%;font-size:13px}.empty{background:#f8fafc;border-radius:8px;text-align:center;padding:22px;color:#64748b}.tag{display:inline-block;border-radius:99px;padding:4px 8px;font-size:12px;font-weight:700;background:#e2e8f0;color:#334155}.tag.ok{background:#dcfce7;color:#166534}.tag.late{background:#ffedd5;color:#9a3412}.tag.wait{background:#dbeafe;color:#1d4ed8}.feedback{background:#f8fafc;border-left:3px solid #94a3b8;padding:10px;margin-top:10px;white-space:pre-wrap;overflow-wrap:anywhere}.due{font-weight:700}.safe{overflow-wrap:anywhere}@media(max-width:850px){.columns{grid-template-columns:1fr}.page{padding:18px 12px}.card{padding:16px}}
</style>
</head>
<body>
<header class="topbar">
  <div>
    <h1>Assignments & Study Materials</h1>
    <p>Welcome, <?= h($student['full_name']) ?> · Registration No: <?= h($student['registration_no']) ?></p>
    <p class="muted">Course and semester resources for your enrolled class.</p>
  </div>
  <a class="btn" href="../dashboard.php">Back to Student Dashboard</a>
</header>
<main class="page">
  <div class="intro">
    <h2>Learning Resource Centre</h2>
    <p class="muted"><?= $courseId > 0 && $semester > 0 ? 'Showing resources for your enrolled course and semester.' : 'Your course or semester has not been set on your student record. Please contact the administrator.' ?></p>
  </div>

  <?php if ($success !== ''): ?><div class="notice success" role="status"><?= h($success) ?></div><?php endif; ?>
  <?php foreach ($errors as $error): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endforeach; ?>

  <?php if ($courseId <= 0 || $semester <= 0): ?>
    <div class="card"><div class="empty">Your course and semester are not available yet. Ask the administrator to update your student profile.</div></div>
  <?php else: ?>
  <div class="columns">
    <section class="card">
      <h3>📝 Assignments</h3>
      <?php if (!$assignments): ?>
        <div class="empty">No assignments have been published for your course and semester yet.</div>
      <?php else: ?>
        <?php foreach ($assignments as $item): ?>
          <?php
            $isSubmitted = !empty($item['submission_id']);
            $isLate = $isSubmitted && !empty($item['due_date']) && substr((string)$item['submitted_at'], 0, 10) > $item['due_date'];
            $isPastDue = !$isSubmitted && !empty($item['due_date']) && date('Y-m-d') > $item['due_date'];
          ?>
          <article class="resource">
            <h4><?= h($item['title']) ?></h4>
            <p class="muted"><?= h(subjectTitle($item)) ?></p>
            <?php if (!empty($item['description'])): ?><p><?= nl2br(h($item['description'])) ?></p><?php endif; ?>
            <p class="muted">Due date: <span class="due"><?= !empty($item['due_date']) ? h(date('d M Y', strtotime($item['due_date']))) : 'Not specified' ?></span></p>
            <?php if (!empty($item['attachment_original_name'])): ?>
              <p class="muted safe">Question file: <?= h($item['attachment_original_name']) ?></p>
              <div class="resource-actions"><a class="btn download" href="download.php?type=assignment&amp;id=<?= (int)$item['id'] ?>">Download question file</a></div>
            <?php endif; ?>

            <div style="margin-top:14px">
              <?php if ($isSubmitted): ?>
                <span class="tag <?= $isLate ? 'late' : 'ok' ?>"><?= $isLate ? 'Submitted late' : 'Submitted' ?></span>
                <p class="muted safe" style="margin-top:8px">Your file: <?= h($item['submitted_filename']) ?></p>
                <p class="muted">Submitted: <?= h(date('d M Y, h:i A', strtotime($item['submitted_at']))) ?></p>
                <?php if ($item['marks'] !== null): ?><p><strong>Marks:</strong> <?= h($item['marks']) ?></p><?php else: ?><span class="tag wait">Awaiting marks</span><?php endif; ?>
                <?php if (!empty($item['feedback'])): ?><div class="feedback"><strong>Lecturer feedback</strong><br><?= nl2br(h($item['feedback'])) ?></div><?php endif; ?>
                <p class="muted">You may upload a replacement submission below. A replacement clears the previous marks and feedback until it is reviewed again.</p>
              <?php elseif ($isPastDue): ?>
                <span class="tag late">Past due · not submitted</span>
              <?php else: ?>
                <span class="tag wait">Not submitted</span>
              <?php endif; ?>
            </div>

            <form method="post" enctype="multipart/form-data" style="border-top:1px solid #e2e8f0;margin-top:14px;padding-top:12px">
              <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
              <input type="hidden" name="assignment_id" value="<?= (int)$item['id'] ?>">
              <div class="field">
                <label for="submission_<?= (int)$item['id'] ?>"><?= $isSubmitted ? 'Replace your submission' : 'Submit your work' ?></label>
                <input type="file" id="submission_<?= (int)$item['id'] ?>" name="submission_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt" required>
                <span class="muted">PDF, DOC, DOCX, PPT, PPTX, or TXT. Max 15 MB. Submissions after the deadline are accepted but marked late.</span>
              </div>
              <div class="resource-actions"><button type="submit" class="btn">Submit Assignment</button></div>
            </form>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <section class="card">
      <h3>📚 Study Materials</h3>
      <?php if (!$materials): ?>
        <div class="empty">No study materials have been uploaded for your course and semester yet.</div>
      <?php else: ?>
        <?php foreach ($materials as $material): ?>
          <article class="resource">
            <h4><?= h($material['title']) ?></h4>
            <p class="muted"><?= h(subjectTitle($material)) ?></p>
            <?php if (!empty($material['description'])): ?><p><?= nl2br(h($material['description'])) ?></p><?php endif; ?>
            <p class="muted safe">File: <?= h($material['original_filename']) ?></p>
            <p class="muted">Uploaded: <?= h(date('d M Y', strtotime($material['uploaded_at']))) ?></p>
            <div class="resource-actions"><a class="btn download" href="download.php?type=material&amp;id=<?= (int)$material['id'] ?>">Download Material</a></div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </div>
  <?php endif; ?>
  <p class="muted" style="text-align:center;margin-top:20px">Only resources assigned to your course and semester are shown. Keep your uploaded work and account credentials private.</p>
</main>
</body>
</html>
