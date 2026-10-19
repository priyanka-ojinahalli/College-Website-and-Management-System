<?php

session_start();

/* =====================================================
   ADMIN LOGIN CHECK
===================================================== */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../../auth/login.php");
    exit();
}

/* =====================================================
   DATABASE
===================================================== */

require_once "../../config/database.php";

/* =====================================================
   GET LECTURER ID
===================================================== */

$id = (int)($_GET["id"] ?? 0);

if ($id <= 0) {
    header("Location: index.php");
    exit();
}

/* =====================================================
   GET LECTURER DETAILS
===================================================== */

$stmt = $conn->prepare("
    SELECT
        l.id,
        l.user_id,
        l.lecturer_id,
        l.full_name,
        l.email,
        l.phone,
        l.designation,
        l.department_id,
        l.photo,
        d.department_name,
        u.username,
        u.role
    FROM lecturers AS l
    LEFT JOIN departments AS d
        ON l.department_id = d.id
    LEFT JOIN users AS u
        ON l.user_id = u.id
    WHERE l.id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Unable to prepare lecturer query.");
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

/* =====================================================
   CHECK LECTURER EXISTS
===================================================== */

if ($result->num_rows !== 1) {
    $stmt->close();
    header("Location: index.php");
    exit();
}

$lecturer = $result->fetch_assoc();
$stmt->close();

/* =====================================================
   SAFE DISPLAY HELPERS
===================================================== */

function displayValue($value, $fallback = "Not provided")
{
    if ($value === null || trim((string)$value) === "") {
        return $fallback;
    }

    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

/* =====================================================
   PHOTO PATH AND FALLBACK INITIALS
===================================================== */

$photoFile = !empty($lecturer["photo"])
    ? basename((string)$lecturer["photo"])
    : "";

$photoPath = "../../images/faculty/" . $photoFile;

$name = trim((string)$lecturer["full_name"]);
$nameParts = preg_split("/\s+/", $name);
$initials = "";

if (count($nameParts) >= 2) {
    $initials = strtoupper(
        substr($nameParts[0], 0, 1) .
        substr($nameParts[count($nameParts) - 1], 0, 1)
    );
} else {
    $initials = strtoupper(substr($name, 0, 2));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>View Lecturer - Admin</title>

    <link rel="stylesheet" href="../../css/admin.css?v=13">

    <style>
        .lecturer-profile-card {
            background: #ffffff;
            max-width: 950px;
            margin-top: 25px;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 25px;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 25px;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #172554;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            flex-shrink: 0;
            overflow: hidden;
        }

        .profile-avatar img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .profile-header h2 {
            margin: 0 0 5px;
            color: #172554;
        }

        .profile-header p {
            margin: 0;
            color: #6b7280;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .info-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            min-width: 0;
        }

        .info-label {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            word-break: break-word;
        }

        .login-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e5e7eb;
        }

        .login-section h3 {
            color: #172554;
            margin-bottom: 18px;
        }

        .login-info {
            background: #eff6ff;
            border-left: 4px solid #172554;
            padding: 15px;
            border-radius: 6px;
        }

        .login-info p {
            margin: 5px 0;
            color: #374151;
            overflow-wrap: anywhere;
        }

        .profile-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .btn-edit,
        .btn-back {
            display: inline-block;
            padding: 10px 18px;
            text-decoration: none;
            border-radius: 5px;
        }

        .btn-edit {
            background: #172554;
            color: #ffffff;
        }

        .btn-edit:hover {
            opacity: 0.9;
        }

        .btn-back {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-back:hover {
            background: #d1d5db;
        }

        @media (max-width: 700px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .profile-header {
                align-items: center;
                flex-wrap: wrap;
            }

            .lecturer-profile-card {
                padding: 20px;
            }
        }

        @media (max-width: 420px) {
            .profile-avatar {
                width: 80px;
                height: 80px;
            }
        }
    </style>
</head>

<body>
<div class="dashboard">

    <!-- ADMIN SIDEBAR -->
    <?php include "../../includes/admin_sidebar.php"; ?>

    <div class="main-content">

        <!-- ADMIN HEADER -->
        <?php include "../../includes/admin_header.php"; ?>

        <div class="content">
            <h2>Lecturer Profile</h2>
            <p class="welcome">View complete lecturer information.</p>

            <div class="lecturer-profile-card">

                <!-- PROFILE HEADER -->
                <div class="profile-header">

                    <div class="profile-avatar">
                        <?php if ($photoFile !== ""): ?>
                            <img
                                src="<?php echo htmlspecialchars($photoPath, ENT_QUOTES, "UTF-8"); ?>"
                                alt="Photo of <?php echo displayValue($lecturer["full_name"], "lecturer"); ?>"
                                onerror="this.style.display='none'; this.parentElement.textContent='<?php echo htmlspecialchars($initials, ENT_QUOTES, "UTF-8"); ?>';"
                            >
                        <?php else: ?>
                            <?php echo displayValue($initials, "?"); ?>
                        <?php endif; ?>
                    </div>

                    <div>
                        <h2><?php echo displayValue($lecturer["full_name"]); ?></h2>
                        <p><?php echo displayValue($lecturer["designation"]); ?></p>
                    </div>

                </div>

                <!-- LECTURER INFORMATION -->
                <div class="info-grid">

                    <div class="info-box">
                        <div class="info-label">Lecturer ID</div>
                        <div class="info-value">
                            <?php echo displayValue($lecturer["lecturer_id"]); ?>
                        </div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">Full Name</div>
                        <div class="info-value">
                            <?php echo displayValue($lecturer["full_name"]); ?>
                        </div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">Email Address</div>
                        <div class="info-value">
                            <?php echo displayValue($lecturer["email"]); ?>
                        </div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value">
                            <?php echo displayValue($lecturer["phone"] ?? null); ?>
                        </div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">Department</div>
                        <div class="info-value">
                            <?php echo displayValue($lecturer["department_name"] ?? null, "Not assigned"); ?>
                        </div>
                    </div>

                    <div class="info-box">
                        <div class="info-label">Designation</div>
                        <div class="info-value">
                            <?php echo displayValue($lecturer["designation"]); ?>
                        </div>
                    </div>

                </div>

                <!-- LOGIN INFORMATION -->
                <div class="login-section">
                    <h3>Login Information</h3>

                    <div class="login-info">
                        <p>
                            <strong>Login Username:</strong>
                            <?php echo displayValue($lecturer["username"] ?? null); ?>
                        </p>
                        <p>
                            <strong>Login Email:</strong>
                            <?php echo displayValue($lecturer["email"]); ?>
                        </p>
                        <p><strong>Account Role:</strong> Lecturer</p>
                        <p><strong>OTP:</strong> Required during login</p>
                        <p><strong>Password:</strong> Hidden for security</p>
                    </div>
                </div>

                <!-- ACTION BUTTONS -->
                <div class="profile-actions">
                    <a
                        href="edit.php?id=<?php echo (int)$lecturer["id"]; ?>"
                        class="btn-edit"
                    >Edit Lecturer</a>

                    <a href="index.php" class="btn-back">Back to Lecturers</a>
                </div>

            </div>
        </div>
    </div>
</div>
</body>
</html>
