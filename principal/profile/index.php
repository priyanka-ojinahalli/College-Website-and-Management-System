<?php

session_start();

require_once "../../config/database.php";

/* Principal access only */
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "principal") {
    header("Location: ../../auth/login.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];

/* Get Principal account details */
$stmt = $conn->prepare("
    SELECT id, username, email, role
    FROM users
    WHERE id = ? AND role = 'principal'
    LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$principal = $result->fetch_assoc();

$stmt->close();

if (!$principal) {
    header("Location: ../dashboard.php");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Principal Profile</title>

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

.profile-card {
    max-width: 800px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 30px;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
}

.profile-top {
    display: flex;
    align-items: center;
    gap: 20px;
    padding-bottom: 25px;
    margin-bottom: 25px;
    border-bottom: 1px solid #e2e8f0;
}

.profile-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: #172554;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
}

.profile-top h2 {
    margin: 0 0 5px;
    color: #172554;
}

.profile-top p {
    margin: 0;
    color: #64748b;
}

.profile-details {
    display: grid;
    grid-template-columns: 180px 1fr;
    row-gap: 18px;
}

.profile-label {
    font-weight: 600;
    color: #475569;
}

.profile-value {
    color: #172554;
}

.role-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    background: #dbeafe;
    color: #1e40af;
    font-size: 13px;
    font-weight: 600;
    text-transform: capitalize;
}

.security-note {
    margin-top: 30px;
    padding: 15px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
    color: #64748b;
    font-size: 13px;
    line-height: 1.6;
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

    .profile-details {
        grid-template-columns: 1fr;
        row-gap: 6px;
    }

    .profile-label {
        margin-top: 12px;
    }

}

</style>

</head>

<body>

<?php include "../includes/principal_sidebar.php"; ?>

<?php include "../includes/principal_header.php"; ?>


<div class="admin-content">

    <div class="page-heading">

        <h1>Principal Profile</h1>

        <p>View your account information.</p>

    </div>


    <div class="profile-card">

        <div class="profile-top">

            <div class="profile-icon">
                👤
            </div>

            <div>

                <h2>
                    <?php echo htmlspecialchars($principal["username"]); ?>
                </h2>

                <p>Principal Account</p>

            </div>

        </div>


        <div class="profile-details">

            <div class="profile-label">
                Username
            </div>

            <div class="profile-value">
                <?php echo htmlspecialchars($principal["username"]); ?>
            </div>


            <div class="profile-label">
                Email
            </div>

            <div class="profile-value">
                <?php echo htmlspecialchars($principal["email"]); ?>
            </div>


            <div class="profile-label">
                Role
            </div>

            <div class="profile-value">

                <span class="role-badge">
                    <?php echo htmlspecialchars($principal["role"]); ?>
                </span>

            </div>

        </div>

    </div>

</div>


</body>

</html>