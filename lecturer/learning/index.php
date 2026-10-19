<?php
session_start();

if (
    !isset($_SESSION['user_id'], $_SESSION['role']) ||
    $_SESSION['role'] !== 'lecturer'
) {
    header('Location: ../../auth/login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$userId = (int) $_SESSION['user_id'];
$lecturerId = 0;
$lecturerName = 'Lecturer';
$subjects = [];
$errors = [];
$success = $_SESSION['learning_flash'] ?? '';
unset($_SESSION['learning_flash']);

if (empty($_SESSION['learning_csrf'])) {
    $_SESSION['learning_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['learning_csrf'];

function h($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/* Get the logged-in lecturer. */
$stmt = $conn->prepare(
    'SELECT id, full_name
     FROM lecturers
     WHERE user_id = ?
     LIMIT 1'
);

if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($lecturer = $result->fetch_assoc()) {
        $lecturerId = (int) $lecturer['id'];
        $lecturerName = $lecturer['full_name'];
    }

    $stmt->close();
}

if ($lecturerId <= 0) {
    http_response_code(403);
    exit(
        'Your lecturer account is not linked to a lecturer record. ' .
        'Please contact the administrator.'
    );
}

/* Get subjects assigned to this lecturer. */
$stmt = $conn->prepare(
    'SELECT
        s.id,
        s.subject_name,
        s.subject_code,
        s.semester,
        c.course_name,
        c.course_code
     FROM subjects s
     LEFT JOIN courses c ON c.id = s.course_id
     WHERE s.lecturer_id = ?
     ORDER BY
        c.course_name,
        s.semester,
        s.subject_name'
);

if ($stmt) {
    $stmt->bind_param('i', $lecturerId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }

    $stmt->close();
}

$allowedExtensions = [
    'pdf',
    'doc',
    'docx',
    'ppt',
    'pptx',
    'txt'
];

$maxFileSize = 15 * 1024 * 1024;

$projectRoot = dirname(__DIR__, 2);
$storageRoot = $projectRoot . '/uploads/learning';

$assignmentStorage = $storageRoot . '/assignments';
$materialStorage = $storageRoot . '/materials';

/* Create upload directories. */
foreach (
    [$storageRoot, $assignmentStorage, $materialStorage]
    as $directory
) {
    if (
        !is_dir($directory) &&
        !mkdir($directory, 0750, true) &&
        !is_dir($directory)
    ) {
        $errors[] =
            'The upload folder could not be created. ' .
            'Check folder permissions.';
        break;
    }
}

/* Prevent direct web access to stored files on Apache. */
$htaccessPath = $storageRoot . '/.htaccess';

if (!file_exists($htaccessPath) && is_dir($storageRoot)) {
    $written = @file_put_contents(
        $htaccessPath,
        "Options -Indexes\nRequire all denied\n"
    );

    if ($written === false) {
        $errors[] =
            'The upload folder could not be protected. ' .
            'Check folder permissions before uploading files.';
    }
}

/* Validate and save an uploaded learning file. */
function saveLearningUpload(
    array $file,
    string $targetDirectory,
    string $folderName,
    array $allowedExtensions,
    int $maxFileSize
): array {
    if (
        !isset($file['error']) ||
        is_array($file['error'])
    ) {
        return [
            'ok' => false,
            'error' => 'Invalid file upload.'
        ];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        if (
            $file['error'] === UPLOAD_ERR_INI_SIZE ||
            $file['error'] === UPLOAD_ERR_FORM_SIZE
        ) {
            $message = 'The selected file is too large.';
        } elseif ($file['error'] === UPLOAD_ERR_NO_FILE) {
            $message = 'Please select a file to upload.';
        } else {
            $message = 'The file could not be uploaded. Try again.';
        }

        return [
            'ok' => false,
            'error' => $message
        ];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return [
            'ok' => false,
            'error' => 'The uploaded file could not be verified.'
        ];
    }

    if (
        $file['size'] <= 0 ||
        $file['size'] > $maxFileSize
    ) {
        return [
            'ok' => false,
            'error' => 'Files must be 15 MB or smaller.'
        ];
    }

    $originalName = basename(
        str_replace('\\', '/', (string) $file['name'])
    );

    $extension = strtolower(
        pathinfo($originalName, PATHINFO_EXTENSION)
    );

    if (!in_array($extension, $allowedExtensions, true)) {
        return [
            'ok' => false,
            'error' =>
                'Allowed file types: PDF, DOC, DOCX, PPT, PPTX, and TXT.'
        ];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $allowedMimes = [
        'pdf' => [
            'application/pdf',
            'application/octet-stream'
        ],
        'doc' => [
            'application/msword',
            'application/x-ole-storage',
            'application/octet-stream'
        ],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/octet-stream'
        ],
        'ppt' => [
            'application/vnd.ms-powerpoint',
            'application/x-ole-storage',
            'application/octet-stream'
        ],
        'pptx' => [
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip',
            'application/octet-stream'
        ],
        'txt' => [
            'text/plain',
            'application/octet-stream'
        ]
    ];

    if (
        !in_array(
            $mime,
            $allowedMimes[$extension] ?? [],
            true
        )
    ) {
        return [
            'ok' => false,
            'error' =>
                'The file content does not match its extension.'
        ];
    }

    try {
        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'error' => 'A secure filename could not be generated.'
        ];
    }

    $destination = $targetDirectory . '/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return [
            'ok' => false,
            'error' => 'The server could not save the uploaded file.'
        ];
    }

    return [
        'ok' => true,
        'relative_path' => $folderName . '/' . $storedName,
        'original_name' => mb_substr($originalName, 0, 255),
        'absolute_path' => $destination
    ];
}

/* Process form submissions. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($csrfToken, $submittedToken)) {
        $errors[] =
            'Your form expired. Refresh the page and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        $subjectId = filter_input(
            INPUT_POST,
            'subject_id',
            FILTER_VALIDATE_INT
        );

        $title = trim((string) ($_POST['title'] ?? ''));

        $description = trim(
            (string) ($_POST['description'] ?? '')
        );

        /* Verify subject ownership. */
        if (!$subjectId || $subjectId <= 0) {
            $errors[] =
                'Please select one of your assigned subjects.';
        } else {
            $stmt = $conn->prepare(
                'SELECT id
                 FROM subjects
                 WHERE id = ? AND lecturer_id = ?
                 LIMIT 1'
            );

            if ($stmt) {
                $stmt->bind_param(
                    'ii',
                    $subjectId,
                    $lecturerId
                );

                $stmt->execute();
                $subjectResult = $stmt->get_result();

                if ($subjectResult->num_rows !== 1) {
                    $errors[] =
                        'The selected subject is not assigned to your account.';
                }

                $stmt->close();
            } else {
                $errors[] =
                    'Could not verify your subject. Please try again.';
            }
        }

        if (
            $title === '' ||
            mb_strlen($title) > 200
        ) {
            $errors[] =
                'Enter a title of 1–200 characters.';
        }

        if (mb_strlen($description) > 10000) {
            $errors[] =
                'The description is too long. Maximum 10,000 characters.';
        }

        /* Create assignment with custom maximum marks. */
        if (!$errors && $action === 'create_assignment') {
            $maximumMarksInput = trim(
                (string) ($_POST['maximum_marks'] ?? '100')
            );

            $maximumMarks = 100.00;

            if (
                $maximumMarksInput === '' ||
                !preg_match(
                    '/^\d{1,4}(?:\.\d{1,2})?$/',
                    $maximumMarksInput
                ) ||
                (float) $maximumMarksInput < 0.01 ||
                (float) $maximumMarksInput > 9999.99
            ) {
                $errors[] =
                    'Maximum marks must be between 0.01 and 9999.99, ' .
                    'with up to two decimal places.';
            } else {
                $maximumMarks = (float) $maximumMarksInput;
            }

            $dueDateInput = trim(
                (string) ($_POST['due_date'] ?? '')
            );

            $dueDate = null;

            if ($dueDateInput !== '') {
                $date = DateTime::createFromFormat(
                    '!Y-m-d',
                    $dueDateInput
                );

                if (
                    !$date ||
                    $date->format('Y-m-d') !== $dueDateInput
                ) {
                    $errors[] = 'Please enter a valid due date.';
                } else {
                    $dueDate = $dueDateInput;
                }
            }

            $upload = null;

            $fileProvided =
                isset($_FILES['attachment']) &&
                $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE;

            if (!$errors && $fileProvided) {
                $upload = saveLearningUpload(
                    $_FILES['attachment'],
                    $assignmentStorage,
                    'assignments',
                    $allowedExtensions,
                    $maxFileSize
                );

                if (!$upload['ok']) {
                    $errors[] = $upload['error'];
                }
            }

            if (!$errors) {
                $attachmentPath = $upload['relative_path'] ?? null;

                $originalFilename = $upload['original_name'] ?? null;

                $stmt = $conn->prepare(
                    'INSERT INTO assignments
                    (
                        subject_id,
                        lecturer_id,
                        title,
                        description,
                        due_date,
                        maximum_marks,
                        attachment_path,
                        attachment_original_name
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );

                if ($stmt) {
                    $stmt->bind_param(
                        'iisssdss',
                        $subjectId,
                        $lecturerId,
                        $title,
                        $description,
                        $dueDate,
                        $maximumMarks,
                        $attachmentPath,
                        $originalFilename
                    );

                    if ($stmt->execute()) {
                        $_SESSION['learning_flash'] =
                            'Assignment created successfully.';

                        $stmt->close();

                        header('Location: index.php');
                        exit;
                    }

                    $stmt->close();

                    if (
                        $upload &&
                        isset($upload['absolute_path']) &&
                        is_file($upload['absolute_path'])
                    ) {
                        @unlink($upload['absolute_path']);
                    }

                    $errors[] =
                        'The assignment could not be saved. ' .
                        'Confirm that assignments has maximum_marks, ' .
                        'attachment_path, and attachment_original_name.';
                } else {
                    if (
                        $upload &&
                        isset($upload['absolute_path']) &&
                        is_file($upload['absolute_path'])
                    ) {
                        @unlink($upload['absolute_path']);
                    }

                    $errors[] =
                        'Could not prepare the assignment database query.';
                }
            }
        }

        /* Upload study material. */
        elseif (!$errors && $action === 'upload_material') {
            $upload = saveLearningUpload(
                $_FILES['material_file'] ?? [],
                $materialStorage,
                'materials',
                $allowedExtensions,
                $maxFileSize
            );

            if (!$upload['ok']) {
                $errors[] = $upload['error'];
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO study_materials
                    (
                        subject_id,
                        lecturer_id,
                        title,
                        description,
                        file_path,
                        original_filename
                    )
                    VALUES (?, ?, ?, ?, ?, ?)'
                );

                if ($stmt) {
                    $path = $upload['relative_path'];

                    $originalFilename = $upload['original_name'];

                    $stmt->bind_param(
                        'iissss',
                        $subjectId,
                        $lecturerId,
                        $title,
                        $description,
                        $path,
                        $originalFilename
                    );

                    if ($stmt->execute()) {
                        $_SESSION['learning_flash'] =
                            'Study material uploaded successfully.';

                        $stmt->close();

                        header('Location: index.php');
                        exit;
                    }

                    $stmt->close();

                    if (is_file($upload['absolute_path'])) {
                        @unlink($upload['absolute_path']);
                    }

                    $errors[] =
                        'The material could not be saved. ' .
                        'Check that study_materials has the expected columns.';
                } else {
                    if (is_file($upload['absolute_path'])) {
                        @unlink($upload['absolute_path']);
                    }

                    $errors[] =
                        'Could not prepare the study-material query.';
                }
            }
        }

        elseif (!$errors) {
            $errors[] =
                'Select whether you are creating an assignment ' .
                'or uploading study material.';
        }
    }
}

/* Get recent assignments created by this lecturer. */
$recentAssignments = [];

$stmt = $conn->prepare(
    'SELECT
        a.id,
        a.title,
        a.due_date,
        a.maximum_marks,
        a.created_at,
        a.attachment_original_name,
        s.subject_name,
        s.subject_code
     FROM assignments a
     INNER JOIN subjects s ON s.id = a.subject_id
     WHERE a.lecturer_id = ?
     ORDER BY a.created_at DESC
     LIMIT 8'
);

if ($stmt) {
    $stmt->bind_param('i', $lecturerId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $recentAssignments[] = $row;
    }

    $stmt->close();
}

/* Get recent study materials uploaded by this lecturer. */
$recentMaterials = [];

$stmt = $conn->prepare(
    'SELECT
        m.id,
        m.title,
        m.original_filename,
        m.uploaded_at,
        s.subject_name,
        s.subject_code
     FROM study_materials m
     INNER JOIN subjects s ON s.id = m.subject_id
     WHERE m.lecturer_id = ?
     ORDER BY m.uploaded_at DESC
     LIMIT 8'
);

if ($stmt) {
    $stmt->bind_param('i', $lecturerId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $recentMaterials[] = $row;
    }

    $stmt->close();
}

function subjectLabel(array $subject): string
{
    $label = trim(
        (string) ($subject['subject_name'] ?? 'Subject')
    );

    if (!empty($subject['subject_code'])) {
        $label .= ' (' . $subject['subject_code'] . ')';
    }

    if (!empty($subject['course_code'])) {
        $label .= ' — ' . $subject['course_code'];
    }

    if (!empty($subject['semester'])) {
        $label .= ', Semester ' . (int) $subject['semester'];
    }

    return $label;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Assignments & Study Materials</title>

    <link
        rel="stylesheet"
        href="../../css/admin.css?v=10"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        .learning-content {
            padding: 25px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-heading {
            margin-bottom: 22px;
        }

        .page-heading h2 {
            margin: 0 0 7px;
            color: #172554;
        }

        .page-heading p {
            margin: 0;
            color: #64748b;
        }

        .learning-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
            align-items: start;
        }

        .learning-card {
            background: #fff;
            border-radius: 12px;
            padding: 23px;
            box-shadow: 0 3px 14px rgba(15, 23, 42, .08);
            min-width: 0;
        }

        .learning-card h3 {
            color: #172554;
            margin: 0 0 18px;
        }

        .field {
            margin-bottom: 15px;
        }

        .field label {
            display: block;
            font-weight: 600;
            color: #334155;
            margin-bottom: 7px;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            padding: 11px 12px;
            font: inherit;
            background: #fff;
        }

        .field textarea {
            min-height: 100px;
            resize: vertical;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            outline: 2px solid #93c5fd;
            border-color: #2563eb;
        }

        .help {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-top: 6px;
            line-height: 1.5;
        }

        .btn {
            border: 0;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 16px;
            background: #172554;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }

        .btn:hover {
            background: #1e40af;
        }

        .notice {
            padding: 13px 15px;
            border-radius: 8px;
            margin-bottom: 18px;
        }

        .notice.success {
            background: #dcfce7;
            color: #166534;
        }

        .notice.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .list-section {
            margin-top: 24px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .learning-table {
            border-collapse: collapse;
            width: 100%;
            min-width: 600px;
        }

        .learning-table th,
        .learning-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            vertical-align: top;
        }

        .learning-table th {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .03em;
            background: #f8fafc;
            color: #475569;
        }

        .learning-table td {
            color: #334155;
            font-size: 14px;
        }

        .muted {
            color: #64748b;
            font-size: 13px;
        }

        .empty-state {
            padding: 18px;
            text-align: center;
            color: #64748b;
            background: #f8fafc;
            border-radius: 8px;
        }

        .file-name {
            overflow-wrap: anywhere;
        }

        @media (max-width: 900px) {
            .learning-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 600px) {
            .learning-content {
                padding: 15px;
            }

            .learning-card {
                padding: 17px;
            }
        }
    </style>
</head>

<body>

<div class="dashboard">

    <?php include __DIR__ . '/../../includes/lecturer_sidebar.php'; ?>

    <main class="main-content">

        <header
            class="admin-header"
            style="display:flex;align-items:center;justify-content:space-between;gap:15px;padding:20px 25px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.06);"
        >
            <div>
                <h1 style="margin:0;font-size:22px;color:#172554;">
                    Assignments & Study Materials
                </h1>

                <p style="margin:5px 0 0;color:#64748b;">
                    Welcome, <?= h($lecturerName) ?>
                </p>
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <a class="btn" href="submissions.php">
                    Review Submissions
                </a>

                <a class="btn" href="../dashboard.php">
                    Back to Dashboard
                </a>
            </div>
        </header>

        <section class="learning-content">

            <div class="page-heading">
                <h2>Learning Resource Centre</h2>

                <p>
                    Create assignments or upload learning resources
                    for subjects assigned to you.
                </p>
            </div>

            <?php if ($success !== ''): ?>
                <div class="notice success" role="status">
                    <?= h($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($errors): ?>
                <div class="notice error" role="alert">
                    <strong>Please correct the following:</strong>

                    <ul style="margin:8px 0 0;padding-left:20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!$subjects): ?>

                <div class="notice error">
                    No subjects have been assigned to your lecturer
                    account yet. Please contact the administrator.
                </div>

            <?php else: ?>

            <div class="learning-grid">

                <!-- CREATE ASSIGNMENT -->

                <section class="learning-card">

                    <h3>📝 Create Assignment</h3>

                    <form method="post" enctype="multipart/form-data">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= h($csrfToken) ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="create_assignment"
                        >

                        <div class="field">
                            <label for="assignment_subject">
                                Subject *
                            </label>

                            <select
                                id="assignment_subject"
                                name="subject_id"
                                required
                            >
                                <option value="">
                                    Choose an assigned subject
                                </option>

                                <?php foreach ($subjects as $subject): ?>
                                    <option
                                        value="<?= (int) $subject['id'] ?>"
                                        <?= (string) ($_POST['subject_id'] ?? '') ===
                                            (string) $subject['id'] ? 'selected' : '' ?>
                                    >
                                        <?= h(subjectLabel($subject)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="assignment_title">
                                Assignment title *
                            </label>

                            <input
                                type="text"
                                id="assignment_title"
                                name="title"
                                maxlength="200"
                                required
                                value="<?= h($_POST['title'] ?? '') ?>"
                                placeholder="e.g. Unit 2: Database Normalization"
                            >
                        </div>

                        <div class="field">
                            <label for="assignment_description">
                                Instructions / description
                            </label>

                            <textarea
                                id="assignment_description"
                                name="description"
                                maxlength="10000"
                                placeholder="Explain the task and submission requirements"
                            ><?= h($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <div class="field">
                            <label for="maximum_marks">
                                Maximum marks *
                            </label>

                            <input
                                type="number"
                                id="maximum_marks"
                                name="maximum_marks"
                                min="0.01"
                                max="9999.99"
                                step="0.01"
                                required
                                value="<?= h($_POST['maximum_marks'] ?? '100') ?>"
                            >

                            <span class="help">
                                Set the highest mark, such as 10, 25, or 50.
                            </span>
                        </div>

                        <div class="field">
                            <label for="due_date">
                                Due date
                            </label>

                            <input
                                type="date"
                                id="due_date"
                                name="due_date"
                                value="<?= h($_POST['due_date'] ?? '') ?>"
                            >

                            <span class="help">
                                Leave blank if there is no fixed deadline.
                            </span>
                        </div>

                        <div class="field">
                            <label for="assignment_attachment">
                                Question / instruction file (optional)
                            </label>

                            <input
                                type="file"
                                id="assignment_attachment"
                                name="attachment"
                                accept=".pdf,.doc,.docx,.ppt,.pptx,.txt"
                            >

                            <span class="help">
                                PDF, DOC, DOCX, PPT, PPTX, or TXT.
                                Maximum 15 MB.
                            </span>
                        </div>

                        <button class="btn" type="submit">
                            Create Assignment
                        </button>

                    </form>

                </section>

                <!-- UPLOAD STUDY MATERIAL -->

                <section class="learning-card">

                    <h3>📚 Upload Study Material</h3>

                    <form method="post" enctype="multipart/form-data">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= h($csrfToken) ?>"
                        >

                        <input
                            type="hidden"
                            name="action"
                            value="upload_material"
                        >

                        <div class="field">
                            <label for="material_subject">
                                Subject *
                            </label>

                            <select
                                id="material_subject"
                                name="subject_id"
                                required
                            >
                                <option value="">
                                    Choose an assigned subject
                                </option>

                                <?php foreach ($subjects as $subject): ?>
                                    <option
                                        value="<?= (int) $subject['id'] ?>"
                                    >
                                        <?= h(subjectLabel($subject)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="field">
                            <label for="material_title">
                                Material title *
                            </label>

                            <input
                                type="text"
                                id="material_title"
                                name="title"
                                maxlength="200"
                                required
                                placeholder="e.g. Unit 1 Notes"
                            >
                        </div>

                        <div class="field">
                            <label for="material_description">
                                Description
                            </label>

                            <textarea
                                id="material_description"
                                name="description"
                                maxlength="10000"
                                placeholder="Briefly describe this resource"
                            ></textarea>
                        </div>

                        <div class="field">
                            <label for="material_file">
                                Choose a file *
                            </label>

                            <input
                                type="file"
                                id="material_file"
                                name="material_file"
                                accept=".pdf,.doc,.docx,.ppt,.pptx,.txt"
                                required
                            >

                            <span class="help">
                                PDF, DOC, DOCX, PPT, PPTX, or TXT.
                                Maximum 15 MB.
                            </span>
                        </div>

                        <button class="btn" type="submit">
                            Upload Study Material
                        </button>

                    </form>

                </section>

            </div>

            <?php endif; ?>

            <!-- RECENT ASSIGNMENTS -->

            <section class="learning-card list-section">

                <h3>Recent Assignments</h3>

                <?php if (!$recentAssignments): ?>

                    <div class="empty-state">
                        You have not created any assignments yet.
                    </div>

                <?php else: ?>

                    <div class="table-wrap">

                        <table class="learning-table">

                            <thead>
                                <tr>
                                    <th>Assignment</th>
                                    <th>Subject</th>
                                    <th>Maximum marks</th>
                                    <th>Due date</th>
                                    <th>Attachment</th>
                                    <th>Created</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php foreach ($recentAssignments as $item): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?= h($item['title']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= h($item['subject_name']) ?>

                                        <?php if (!empty($item['subject_code'])): ?>
                                            <div class="muted">
                                                <?= h($item['subject_code']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?= h(
                                            number_format(
                                                (float) $item['maximum_marks'],
                                                2
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <?php if (!empty($item['due_date'])): ?>
                                            <?= h(
                                                date(
                                                    'd M Y',
                                                    strtotime($item['due_date'])
                                                )
                                            ) ?>
                                        <?php else: ?>
                                            <span class="muted">Not set</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="file-name">
                                        <?php if (!empty($item['attachment_original_name'])): ?>
                                            <?= h($item['attachment_original_name']) ?>
                                        <?php else: ?>
                                            <span class="muted">No attachment</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?= h(
                                            date(
                                                'd M Y',
                                                strtotime($item['created_at'])
                                            )
                                        ) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </section>

            <!-- RECENT STUDY MATERIALS -->

            <section class="learning-card list-section">

                <h3>Recent Study Materials</h3>

                <?php if (!$recentMaterials): ?>

                    <div class="empty-state">
                        You have not uploaded any study materials yet.
                    </div>

                <?php else: ?>

                    <div class="table-wrap">

                        <table class="learning-table">

                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th>Subject</th>
                                    <th>File</th>
                                    <th>Uploaded</th>
                                </tr>
                            </thead>

                            <tbody>

                            <?php foreach ($recentMaterials as $item): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?= h($item['title']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= h($item['subject_name']) ?>

                                        <?php if (!empty($item['subject_code'])): ?>
                                            <div class="muted">
                                                <?= h($item['subject_code']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="file-name">
                                        <?= h($item['original_filename']) ?>
                                    </td>

                                    <td>
                                        <?= h(
                                            date(
                                                'd M Y',
                                                strtotime($item['uploaded_at'])
                                            )
                                        ) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

                <p class="help">
                    Files are stored in a protected folder.
                    Authorized download access is handled separately.
                </p>

            </section>

        </section>

    </main>

</div>

</body>
</html>