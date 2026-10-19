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
$errors = [];

$success = (string) (
    $_SESSION['submission_review_flash'] ?? ''
);

unset($_SESSION['submission_review_flash']);

if (empty($_SESSION['submission_review_csrf'])) {
    $_SESSION['submission_review_csrf'] =
        bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['submission_review_csrf'];

/* Escape output safely. */
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

    $lecturer = $stmt->get_result()->fetch_assoc();

    if ($lecturer) {
        $lecturerId = (int) $lecturer['id'];
        $lecturerName = (string) $lecturer['full_name'];
    }

    $stmt->close();
}

/* Ensure the logged-in user has a lecturer record. */
if ($lecturerId <= 0) {
    http_response_code(403);
    exit(
        'Your lecturer account is not linked to a lecturer record. ' .
        'Please contact the administrator.'
    );
}

/* =========================================
   SAVE MARKS AND FEEDBACK
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    if (!hash_equals($csrfToken, $submittedToken)) {
        $errors[] =
            'Your form expired. Refresh the page and try again.';
    } else {

        $submissionId = filter_input(
            INPUT_POST,
            'submission_id',
            FILTER_VALIDATE_INT
        );

        $marksInput = trim(
            (string) ($_POST['marks'] ?? '')
        );

        $feedback = trim(
            (string) ($_POST['feedback'] ?? '')
        );

        if (!$submissionId || $submissionId <= 0) {
            $errors[] = 'Invalid submission selected.';
        }

        if (
            $marksInput === '' ||
            !preg_match(
                '/^\d{1,4}(?:\.\d{1,2})?$/',
                $marksInput
            ) ||
            (float) $marksInput < 0 ||
            (float) $marksInput > 9999.99
        ) {
            $errors[] =
                'Enter a valid non-negative mark with up to two decimal places.';
        }

        if (mb_strlen($feedback) > 5000) {
            $errors[] =
                'Feedback must not exceed 5,000 characters.';
        }

        if (!$errors) {

            /*
             * Verify that the submission belongs to an assignment
             * created by the currently logged-in lecturer.
             */
            $check = $conn->prepare(
                'SELECT
                    subm.id,
                    a.maximum_marks
                 FROM assignment_submissions subm
                 INNER JOIN assignments a
                    ON a.id = subm.assignment_id
                 WHERE subm.id = ?
                   AND a.lecturer_id = ?
                 LIMIT 1'
            );

            $assignmentForSubmission = null;

            if ($check) {
                $check->bind_param(
                    'ii',
                    $submissionId,
                    $lecturerId
                );

                $check->execute();

                $assignmentForSubmission =
                    $check->get_result()->fetch_assoc() ?: null;

                $check->close();
            }

            if (!$assignmentForSubmission) {
                $errors[] =
                    'That submission does not belong to one of your assignments.';
            } else {

                $maximumMarks = (float) (
                    $assignmentForSubmission['maximum_marks']
                );

                if ((float) $marksInput > $maximumMarks) {
                    $errors[] =
                        'Marks cannot exceed this assignment maximum of ' .
                        number_format($maximumMarks, 2) . '.';
                }
            }

            /* Update marks and feedback. */
            if (!$errors) {

                $marks = (float) $marksInput;

                $update = $conn->prepare(
                    'UPDATE assignment_submissions subm
                     INNER JOIN assignments a
                        ON a.id = subm.assignment_id
                     SET
                        subm.marks = ?,
                        subm.feedback = ?,
                        subm.graded_by = ?,
                        subm.graded_at = NOW()
                     WHERE subm.id = ?
                       AND a.lecturer_id = ?'
                );

                if ($update) {

                    $update->bind_param(
                        'dsiii',
                        $marks,
                        $feedback,
                        $lecturerId,
                        $submissionId,
                        $lecturerId
                    );

                    if ($update->execute()) {

                        $_SESSION['submission_review_flash'] =
                            'Marks and feedback saved successfully.';

                        $update->close();

                        header('Location: submissions.php');
                        exit;

                    } else {

                        $errors[] =
                            'Could not save the review. Please try again.';

                        $update->close();
                    }

                } else {
                    $errors[] =
                        'Could not prepare the review update query.';
                }
            }
        }
    }
}

/* =========================================
   GET SUBMISSIONS FOR THIS LECTURER
========================================= */

$submissions = [];

$stmt = $conn->prepare(
    'SELECT
        subm.id AS submission_id,
        subm.assignment_id,
        subm.student_id,
        subm.file_path,
        subm.original_filename,
        subm.submitted_at,
        subm.marks,
        subm.feedback,
        subm.graded_at,

        a.title AS assignment_title,
        a.due_date,
        a.maximum_marks,

        s.subject_name,
        s.subject_code,
        s.semester,

        c.course_name,
        c.course_code,

        st.full_name AS student_name,
        st.registration_no,
        st.roll_no

     FROM assignment_submissions subm

     INNER JOIN assignments a
        ON a.id = subm.assignment_id

     INNER JOIN subjects s
        ON s.id = a.subject_id

     LEFT JOIN courses c
        ON c.id = s.course_id

     INNER JOIN students st
        ON st.id = subm.student_id

     WHERE a.lecturer_id = ?

     ORDER BY
        (subm.graded_at IS NULL) DESC,
        subm.submitted_at DESC

     LIMIT 300'
);

if ($stmt) {

    $stmt->bind_param('i', $lecturerId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $submissions[] = $row;
    }

    $stmt->close();
}

/* Calculate summary counts. */
$totalCount = count($submissions);
$pendingCount = 0;
$reviewedCount = 0;

foreach ($submissions as $item) {

    if ($item['graded_at'] === null) {
        $pendingCount++;
    } else {
        $reviewedCount++;
    }
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

    <title>Review Assignment Submissions</title>

    <link
        rel="stylesheet"
        href="../../css/admin.css?v=10"
    >

    <style>
        .review-content {
            padding: 25px;
            max-width: 1450px;
            margin: 0 auto;
            color: #111827;
        }

        .review-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .review-top h2 {
            margin: 0;
            color: #172554;
        }

        .review-top p {
            margin: 6px 0 0;
            color: #64748b;
        }

        .review-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .review-btn {
            display: inline-block;
            padding: 10px 14px;
            border: 0;
            border-radius: 7px;
            background: #172554;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
        }

        .review-btn:hover {
            background: #1e3a8a;
        }

        .review-btn.secondary {
            background: #e2e8f0;
            color: #172554;
        }

        .review-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 22px;
        }

        .review-stat {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 19px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
        }

        .review-stat span {
            font-size: 13px;
            color: #64748b;
        }

        .review-stat strong {
            display: block;
            margin-top: 6px;
            font-size: 28px;
            color: #172554;
        }

        .review-alert {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .review-alert.success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .review-alert.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .review-panel {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 2px 9px rgba(15, 23, 42, .05);
            overflow: hidden;
        }

        .review-panel-head {
            padding: 20px 22px;
            border-bottom: 1px solid #e5e7eb;
        }

        .review-panel-head h3 {
            margin: 0;
            color: #172554;
        }

        .review-panel-head p {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 13px;
        }

        .submission-list {
            display: grid;
            gap: 0;
        }

        .submission-card {
            padding: 22px;
            border-bottom: 1px solid #e5e7eb;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(300px, .8fr);
            gap: 24px;
        }

        .submission-card:last-child {
            border-bottom: 0;
        }

        .submission-title {
            margin: 0 0 8px;
            font-size: 18px;
            color: #172554;
            overflow-wrap: anywhere;
        }

        .submission-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin: 10px 0;
        }

        .review-chip {
            display: inline-block;
            background: #f1f5f9;
            color: #334155;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 12px;
        }

        .submission-details {
            color: #475569;
            font-size: 14px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .submission-file {
            display: inline-block;
            margin-top: 10px;
            color: #1d4ed8;
            font-weight: 600;
            text-decoration: none;
            overflow-wrap: anywhere;
        }

        .submission-file:hover {
            text-decoration: underline;
        }

        .review-form {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
        }

        .review-form label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            margin: 0 0 6px;
        }

        .review-form input,
        .review-form textarea {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            padding: 10px 11px;
            font: inherit;
            margin-bottom: 13px;
            background: #fff;
        }

        .review-form textarea {
            min-height: 88px;
            resize: vertical;
        }

        .review-form .review-btn {
            width: 100%;
        }

        .muted {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }

        .empty-review {
            padding: 45px 20px;
            text-align: center;
            color: #64748b;
        }

        .empty-review strong {
            display: block;
            color: #172554;
            font-size: 17px;
            margin-bottom: 7px;
        }

        @media (max-width: 900px) {
            .submission-card {
                grid-template-columns: 1fr;
            }

            .review-content {
                padding: 16px;
            }

            .review-stats {
                gap: 10px;
            }

            .review-stat {
                padding: 15px;
            }
        }

        @media (max-width: 560px) {
            .review-stats {
                grid-template-columns: 1fr;
            }

            .submission-card {
                padding: 16px;
            }

            .review-top h2 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

<div class="dashboard">

    <?php
    include __DIR__ . '/../../includes/lecturer_sidebar.php';
    ?>

    <main class="main-content">

        <header
            class="admin-header"
            style="display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap;padding:20px 25px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.06)"
        >

            <div>
                <h1 style="margin:0;font-size:22px;color:#172554">
                    Submission Review
                </h1>

                <p style="margin:5px 0 0;color:#64748b">
                    Welcome, <?= h($lecturerName) ?>
                </p>
            </div>

            <div class="review-actions">
                <a
                    class="review-btn secondary"
                    href="index.php"
                >
                    Assignments & Materials
                </a>

                <a
                    class="review-btn secondary"
                    href="../dashboard.php"
                >
                    Dashboard
                </a>
            </div>

        </header>

        <section class="review-content">

            <div class="review-top">
                <div>
                    <h2>Student Submissions</h2>

                    <p>
                        Review submitted work, then record marks
                        and feedback.
                    </p>
                </div>
            </div>

            <?php if ($success !== ''): ?>

                <div class="review-alert success" role="status">
                    <?= h($success) ?>
                </div>

            <?php endif; ?>

            <?php if ($errors): ?>

                <div class="review-alert error" role="alert">

                    <strong>Could not save the review:</strong>

                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>

                </div>

            <?php endif; ?>

            <!-- SUMMARY CARDS -->

            <div class="review-stats">

                <div class="review-stat">
                    <span>Submissions shown</span>
                    <strong><?= $totalCount ?></strong>
                </div>

                <div class="review-stat">
                    <span>Awaiting review</span>
                    <strong><?= $pendingCount ?></strong>
                </div>

                <div class="review-stat">
                    <span>Reviewed</span>
                    <strong><?= $reviewedCount ?></strong>
                </div>

            </div>

            <!-- SUBMISSION LIST -->

            <section class="review-panel">

                <div class="review-panel-head">
                    <h3>Submitted Work</h3>

                    <p>
                        The 300 most recent submissions are shown.
                        Each submission is graded against its own
                        assignment's maximum marks.
                    </p>
                </div>

                <?php if (!$submissions): ?>

                    <div class="empty-review">
                        <strong>No submissions yet</strong>

                        When students submit work for your assignments,
                        their files will appear here.
                    </div>

                <?php else: ?>

                    <div class="submission-list">

                        <?php foreach ($submissions as $item): ?>

                            <article class="submission-card">

                                <!-- STUDENT AND ASSIGNMENT DETAILS -->

                                <div>

                                    <h4 class="submission-title">
                                        <?= h($item['assignment_title']) ?>
                                    </h4>

                                    <div class="submission-meta">

                                        <span class="review-chip">
                                            <?= h($item['subject_name']) ?>

                                            <?php if (!empty($item['subject_code'])): ?>
                                                · <?= h($item['subject_code']) ?>
                                            <?php endif; ?>
                                        </span>

                                        <span class="review-chip">
                                            <?= h(
                                                $item['course_code']
                                                ?: $item['course_name']
                                                ?: 'Course'
                                            ) ?>

                                            · Semester
                                            <?= (int) $item['semester'] ?>
                                        </span>

                                        <?php if ($item['graded_at'] === null): ?>

                                            <span class="review-chip">
                                                Awaiting review
                                            </span>

                                        <?php else: ?>

                                            <span class="review-chip">
                                                Reviewed
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                    <div class="submission-details">

                                        <strong>Student:</strong>
                                        <?= h($item['student_name']) ?>
                                        <br>

                                        <strong>Registration no.:</strong>
                                        <?= h($item['registration_no']) ?>

                                        <?php if (!empty($item['roll_no'])): ?>
                                            <br>

                                            <strong>Roll no.:</strong>
                                            <?= h($item['roll_no']) ?>
                                        <?php endif; ?>

                                        <br>

                                        <strong>Submitted:</strong>
                                        <?= h(
                                            date(
                                                'd M Y, h:i A',
                                                strtotime($item['submitted_at'])
                                            )
                                        ) ?>
                                        <br>

                                        <strong>Due date:</strong>

                                        <?php if (!empty($item['due_date'])): ?>

                                            <?= h(
                                                date(
                                                    'd M Y',
                                                    strtotime($item['due_date'])
                                                )
                                            ) ?>

                                        <?php else: ?>

                                            Not set

                                        <?php endif; ?>

                                        <br>

                                        <strong>Maximum marks:</strong>
                                        <?= h(
                                            number_format(
                                                (float) $item['maximum_marks'],
                                                2
                                            )
                                        ) ?>
                                        <br>

                                        <strong>File:</strong>
                                        <?= h($item['original_filename']) ?>

                                    </div>

                                    <a
                                        class="submission-file"
                                        href="submission_download.php?id=<?= (int) $item['submission_id'] ?>"
                                    >
                                        ⬇ Download Submitted File
                                    </a>

                                </div>

                                <!-- MARKS AND FEEDBACK FORM -->

                                <form
                                    class="review-form"
                                    method="post"
                                    action="submissions.php"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= h($csrfToken) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="submission_id"
                                        value="<?= (int) $item['submission_id'] ?>"
                                    >

                                    <label
                                        for="marks_<?= (int) $item['submission_id'] ?>"
                                    >
                                        Marks (out of
                                        <?= h(
                                            number_format(
                                                (float) $item['maximum_marks'],
                                                2
                                            )
                                        ) ?>) *
                                    </label>

                                    <input
                                        id="marks_<?= (int) $item['submission_id'] ?>"
                                        name="marks"
                                        type="number"
                                        min="0"
                                        max="<?= h(
                                            (string) $item['maximum_marks']
                                        ) ?>"
                                        step="0.01"
                                        required
                                        value="<?= $item['marks'] !== null
                                            ? h($item['marks'])
                                            : '' ?>"
                                        placeholder="Enter marks"
                                    >

                                    <label
                                        for="feedback_<?= (int) $item['submission_id'] ?>"
                                    >
                                        Feedback for Student
                                    </label>

                                    <textarea
                                        id="feedback_<?= (int) $item['submission_id'] ?>"
                                        name="feedback"
                                        maxlength="5000"
                                        placeholder="Write constructive feedback..."
                                    ><?= h($item['feedback'] ?? '') ?></textarea>

                                    <?php if ($item['graded_at'] !== null): ?>

                                        <p class="muted">
                                            Last reviewed:

                                            <?= h(
                                                date(
                                                    'd M Y, h:i A',
                                                    strtotime($item['graded_at'])
                                                )
                                            ) ?>
                                        </p>

                                    <?php endif; ?>

                                    <button
                                        class="review-btn"
                                        type="submit"
                                    >
                                        Save Marks & Feedback
                                    </button>

                                </form>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

        </section>

    </main>

</div>

</body>
</html><?php
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
$errors = [];

$success = (string) (
    $_SESSION['submission_review_flash'] ?? ''
);

unset($_SESSION['submission_review_flash']);

if (empty($_SESSION['submission_review_csrf'])) {
    $_SESSION['submission_review_csrf'] =
        bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['submission_review_csrf'];

/* Escape output safely. */
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

    $lecturer = $stmt->get_result()->fetch_assoc();

    if ($lecturer) {
        $lecturerId = (int) $lecturer['id'];
        $lecturerName = (string) $lecturer['full_name'];
    }

    $stmt->close();
}

/* Ensure the logged-in user has a lecturer record. */
if ($lecturerId <= 0) {
    http_response_code(403);
    exit(
        'Your lecturer account is not linked to a lecturer record. ' .
        'Please contact the administrator.'
    );
}

/* =========================================
   SAVE MARKS AND FEEDBACK
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submittedToken = (string) (
        $_POST['csrf_token'] ?? ''
    );

    if (!hash_equals($csrfToken, $submittedToken)) {
        $errors[] =
            'Your form expired. Refresh the page and try again.';
    } else {

        $submissionId = filter_input(
            INPUT_POST,
            'submission_id',
            FILTER_VALIDATE_INT
        );

        $marksInput = trim(
            (string) ($_POST['marks'] ?? '')
        );

        $feedback = trim(
            (string) ($_POST['feedback'] ?? '')
        );

        if (!$submissionId || $submissionId <= 0) {
            $errors[] = 'Invalid submission selected.';
        }

        if (
            $marksInput === '' ||
            !preg_match(
                '/^\d{1,4}(?:\.\d{1,2})?$/',
                $marksInput
            ) ||
            (float) $marksInput < 0 ||
            (float) $marksInput > 9999.99
        ) {
            $errors[] =
                'Enter a valid non-negative mark with up to two decimal places.';
        }

        if (mb_strlen($feedback) > 5000) {
            $errors[] =
                'Feedback must not exceed 5,000 characters.';
        }

        if (!$errors) {

            /*
             * Verify that the submission belongs to an assignment
             * created by the currently logged-in lecturer.
             */
            $check = $conn->prepare(
                'SELECT
                    subm.id,
                    a.maximum_marks
                 FROM assignment_submissions subm
                 INNER JOIN assignments a
                    ON a.id = subm.assignment_id
                 WHERE subm.id = ?
                   AND a.lecturer_id = ?
                 LIMIT 1'
            );

            $assignmentForSubmission = null;

            if ($check) {
                $check->bind_param(
                    'ii',
                    $submissionId,
                    $lecturerId
                );

                $check->execute();

                $assignmentForSubmission =
                    $check->get_result()->fetch_assoc() ?: null;

                $check->close();
            }

            if (!$assignmentForSubmission) {
                $errors[] =
                    'That submission does not belong to one of your assignments.';
            } else {

                $maximumMarks = (float) (
                    $assignmentForSubmission['maximum_marks']
                );

                if ((float) $marksInput > $maximumMarks) {
                    $errors[] =
                        'Marks cannot exceed this assignment maximum of ' .
                        number_format($maximumMarks, 2) . '.';
                }
            }

            /* Update marks and feedback. */
            if (!$errors) {

                $marks = (float) $marksInput;

                $update = $conn->prepare(
                    'UPDATE assignment_submissions subm
                     INNER JOIN assignments a
                        ON a.id = subm.assignment_id
                     SET
                        subm.marks = ?,
                        subm.feedback = ?,
                        subm.graded_by = ?,
                        subm.graded_at = NOW()
                     WHERE subm.id = ?
                       AND a.lecturer_id = ?'
                );

                if ($update) {

                    $update->bind_param(
                        'dsiii',
                        $marks,
                        $feedback,
                        $lecturerId,
                        $submissionId,
                        $lecturerId
                    );

                    if ($update->execute()) {

                        $_SESSION['submission_review_flash'] =
                            'Marks and feedback saved successfully.';

                        $update->close();

                        header('Location: submissions.php');
                        exit;

                    } else {

                        $errors[] =
                            'Could not save the review. Please try again.';

                        $update->close();
                    }

                } else {
                    $errors[] =
                        'Could not prepare the review update query.';
                }
            }
        }
    }
}

/* =========================================
   GET SUBMISSIONS FOR THIS LECTURER
========================================= */

$submissions = [];

$stmt = $conn->prepare(
    'SELECT
        subm.id AS submission_id,
        subm.assignment_id,
        subm.student_id,
        subm.file_path,
        subm.original_filename,
        subm.submitted_at,
        subm.marks,
        subm.feedback,
        subm.graded_at,

        a.title AS assignment_title,
        a.due_date,
        a.maximum_marks,

        s.subject_name,
        s.subject_code,
        s.semester,

        c.course_name,
        c.course_code,

        st.full_name AS student_name,
        st.registration_no,
        st.roll_no

     FROM assignment_submissions subm

     INNER JOIN assignments a
        ON a.id = subm.assignment_id

     INNER JOIN subjects s
        ON s.id = a.subject_id

     LEFT JOIN courses c
        ON c.id = s.course_id

     INNER JOIN students st
        ON st.id = subm.student_id

     WHERE a.lecturer_id = ?

     ORDER BY
        (subm.graded_at IS NULL) DESC,
        subm.submitted_at DESC

     LIMIT 300'
);

if ($stmt) {

    $stmt->bind_param('i', $lecturerId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $submissions[] = $row;
    }

    $stmt->close();
}

/* Calculate summary counts. */
$totalCount = count($submissions);
$pendingCount = 0;
$reviewedCount = 0;

foreach ($submissions as $item) {

    if ($item['graded_at'] === null) {
        $pendingCount++;
    } else {
        $reviewedCount++;
    }
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

    <title>Review Assignment Submissions</title>

    <link
        rel="stylesheet"
        href="../../css/admin.css?v=10"
    >

    <style>
        .review-content {
            padding: 25px;
            max-width: 1450px;
            margin: 0 auto;
            color: #111827;
        }

        .review-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .review-top h2 {
            margin: 0;
            color: #172554;
        }

        .review-top p {
            margin: 6px 0 0;
            color: #64748b;
        }

        .review-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .review-btn {
            display: inline-block;
            padding: 10px 14px;
            border: 0;
            border-radius: 7px;
            background: #172554;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
        }

        .review-btn:hover {
            background: #1e3a8a;
        }

        .review-btn.secondary {
            background: #e2e8f0;
            color: #172554;
        }

        .review-stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 15px;
            margin-bottom: 22px;
        }

        .review-stat {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 19px;
            box-shadow: 0 2px 8px rgba(15, 23, 42, .04);
        }

        .review-stat span {
            font-size: 13px;
            color: #64748b;
        }

        .review-stat strong {
            display: block;
            margin-top: 6px;
            font-size: 28px;
            color: #172554;
        }

        .review-alert {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .review-alert.success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .review-alert.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .review-panel {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 2px 9px rgba(15, 23, 42, .05);
            overflow: hidden;
        }

        .review-panel-head {
            padding: 20px 22px;
            border-bottom: 1px solid #e5e7eb;
        }

        .review-panel-head h3 {
            margin: 0;
            color: #172554;
        }

        .review-panel-head p {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 13px;
        }

        .submission-list {
            display: grid;
            gap: 0;
        }

        .submission-card {
            padding: 22px;
            border-bottom: 1px solid #e5e7eb;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(300px, .8fr);
            gap: 24px;
        }

        .submission-card:last-child {
            border-bottom: 0;
        }

        .submission-title {
            margin: 0 0 8px;
            font-size: 18px;
            color: #172554;
            overflow-wrap: anywhere;
        }

        .submission-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin: 10px 0;
        }

        .review-chip {
            display: inline-block;
            background: #f1f5f9;
            color: #334155;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 12px;
        }

        .submission-details {
            color: #475569;
            font-size: 14px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .submission-file {
            display: inline-block;
            margin-top: 10px;
            color: #1d4ed8;
            font-weight: 600;
            text-decoration: none;
            overflow-wrap: anywhere;
        }

        .submission-file:hover {
            text-decoration: underline;
        }

        .review-form {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
        }

        .review-form label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            margin: 0 0 6px;
        }

        .review-form input,
        .review-form textarea {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            padding: 10px 11px;
            font: inherit;
            margin-bottom: 13px;
            background: #fff;
        }

        .review-form textarea {
            min-height: 88px;
            resize: vertical;
        }

        .review-form .review-btn {
            width: 100%;
        }

        .muted {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }

        .empty-review {
            padding: 45px 20px;
            text-align: center;
            color: #64748b;
        }

        .empty-review strong {
            display: block;
            color: #172554;
            font-size: 17px;
            margin-bottom: 7px;
        }

        @media (max-width: 900px) {
            .submission-card {
                grid-template-columns: 1fr;
            }

            .review-content {
                padding: 16px;
            }

            .review-stats {
                gap: 10px;
            }

            .review-stat {
                padding: 15px;
            }
        }

        @media (max-width: 560px) {
            .review-stats {
                grid-template-columns: 1fr;
            }

            .submission-card {
                padding: 16px;
            }

            .review-top h2 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

<div class="dashboard">

    <?php
    include __DIR__ . '/../../includes/lecturer_sidebar.php';
    ?>

    <main class="main-content">

        <header
            class="admin-header"
            style="display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap;padding:20px 25px;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.06)"
        >

            <div>
                <h1 style="margin:0;font-size:22px;color:#172554">
                    Submission Review
                </h1>

                <p style="margin:5px 0 0;color:#64748b">
                    Welcome, <?= h($lecturerName) ?>
                </p>
            </div>

            <div class="review-actions">
                <a
                    class="review-btn secondary"
                    href="index.php"
                >
                    Assignments & Materials
                </a>

                <a
                    class="review-btn secondary"
                    href="../dashboard.php"
                >
                    Dashboard
                </a>
            </div>

        </header>

        <section class="review-content">

            <div class="review-top">
                <div>
                    <h2>Student Submissions</h2>

                    <p>
                        Review submitted work, then record marks
                        and feedback.
                    </p>
                </div>
            </div>

            <?php if ($success !== ''): ?>

                <div class="review-alert success" role="status">
                    <?= h($success) ?>
                </div>

            <?php endif; ?>

            <?php if ($errors): ?>

                <div class="review-alert error" role="alert">

                    <strong>Could not save the review:</strong>

                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>

                </div>

            <?php endif; ?>

            <!-- SUMMARY CARDS -->

            <div class="review-stats">

                <div class="review-stat">
                    <span>Submissions shown</span>
                    <strong><?= $totalCount ?></strong>
                </div>

                <div class="review-stat">
                    <span>Awaiting review</span>
                    <strong><?= $pendingCount ?></strong>
                </div>

                <div class="review-stat">
                    <span>Reviewed</span>
                    <strong><?= $reviewedCount ?></strong>
                </div>

            </div>

            <!-- SUBMISSION LIST -->

            <section class="review-panel">

                <div class="review-panel-head">
                    <h3>Submitted Work</h3>

                    <p>
                        The 300 most recent submissions are shown.
                        Each submission is graded against its own
                        assignment's maximum marks.
                    </p>
                </div>

                <?php if (!$submissions): ?>

                    <div class="empty-review">
                        <strong>No submissions yet</strong>

                        When students submit work for your assignments,
                        their files will appear here.
                    </div>

                <?php else: ?>

                    <div class="submission-list">

                        <?php foreach ($submissions as $item): ?>

                            <article class="submission-card">

                                <!-- STUDENT AND ASSIGNMENT DETAILS -->

                                <div>

                                    <h4 class="submission-title">
                                        <?= h($item['assignment_title']) ?>
                                    </h4>

                                    <div class="submission-meta">

                                        <span class="review-chip">
                                            <?= h($item['subject_name']) ?>

                                            <?php if (!empty($item['subject_code'])): ?>
                                                · <?= h($item['subject_code']) ?>
                                            <?php endif; ?>
                                        </span>

                                        <span class="review-chip">
                                            <?= h(
                                                $item['course_code']
                                                ?: $item['course_name']
                                                ?: 'Course'
                                            ) ?>

                                            · Semester
                                            <?= (int) $item['semester'] ?>
                                        </span>

                                        <?php if ($item['graded_at'] === null): ?>

                                            <span class="review-chip">
                                                Awaiting review
                                            </span>

                                        <?php else: ?>

                                            <span class="review-chip">
                                                Reviewed
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                    <div class="submission-details">

                                        <strong>Student:</strong>
                                        <?= h($item['student_name']) ?>
                                        <br>

                                        <strong>Registration no.:</strong>
                                        <?= h($item['registration_no']) ?>

                                        <?php if (!empty($item['roll_no'])): ?>
                                            <br>

                                            <strong>Roll no.:</strong>
                                            <?= h($item['roll_no']) ?>
                                        <?php endif; ?>

                                        <br>

                                        <strong>Submitted:</strong>
                                        <?= h(
                                            date(
                                                'd M Y, h:i A',
                                                strtotime($item['submitted_at'])
                                            )
                                        ) ?>
                                        <br>

                                        <strong>Due date:</strong>

                                        <?php if (!empty($item['due_date'])): ?>

                                            <?= h(
                                                date(
                                                    'd M Y',
                                                    strtotime($item['due_date'])
                                                )
                                            ) ?>

                                        <?php else: ?>

                                            Not set

                                        <?php endif; ?>

                                        <br>

                                        <strong>Maximum marks:</strong>
                                        <?= h(
                                            number_format(
                                                (float) $item['maximum_marks'],
                                                2
                                            )
                                        ) ?>
                                        <br>

                                        <strong>File:</strong>
                                        <?= h($item['original_filename']) ?>

                                    </div>

                                    <a
                                        class="submission-file"
                                        href="submission_download.php?id=<?= (int) $item['submission_id'] ?>"
                                    >
                                        ⬇ Download Submitted File
                                    </a>

                                </div>

                                <!-- MARKS AND FEEDBACK FORM -->

                                <form
                                    class="review-form"
                                    method="post"
                                    action="submissions.php"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= h($csrfToken) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="submission_id"
                                        value="<?= (int) $item['submission_id'] ?>"
                                    >

                                    <label
                                        for="marks_<?= (int) $item['submission_id'] ?>"
                                    >
                                        Marks (out of
                                        <?= h(
                                            number_format(
                                                (float) $item['maximum_marks'],
                                                2
                                            )
                                        ) ?>) *
                                    </label>

                                    <input
                                        id="marks_<?= (int) $item['submission_id'] ?>"
                                        name="marks"
                                        type="number"
                                        min="0"
                                        max="<?= h(
                                            (string) $item['maximum_marks']
                                        ) ?>"
                                        step="0.01"
                                        required
                                        value="<?= $item['marks'] !== null
                                            ? h($item['marks'])
                                            : '' ?>"
                                        placeholder="Enter marks"
                                    >

                                    <label
                                        for="feedback_<?= (int) $item['submission_id'] ?>"
                                    >
                                        Feedback for Student
                                    </label>

                                    <textarea
                                        id="feedback_<?= (int) $item['submission_id'] ?>"
                                        name="feedback"
                                        maxlength="5000"
                                        placeholder="Write constructive feedback..."
                                    ><?= h($item['feedback'] ?? '') ?></textarea>

                                    <?php if ($item['graded_at'] !== null): ?>

                                        <p class="muted">
                                            Last reviewed:

                                            <?= h(
                                                date(
                                                    'd M Y, h:i A',
                                                    strtotime($item['graded_at'])
                                                )
                                            ) ?>
                                        </p>

                                    <?php endif; ?>

                                    <button
                                        class="review-btn"
                                        type="submit"
                                    >
                                        Save Marks & Feedback
                                    </button>

                                </form>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

        </section>

    </main>

</div>

</body>
</html>