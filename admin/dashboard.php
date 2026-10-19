<?php

session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}

require_once "../config/database.php";

$student_count = 0;
$lecturer_count = 0;
$course_count = 0;
$department_count = 0;

/* Count students */
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'student'"
);

if ($result) {
    $student_count = $result->fetch_assoc()["total"];
}

/* Count lecturers */
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'lecturer'"
);

if ($result) {
    $lecturer_count = $result->fetch_assoc()["total"];
}

/* Count courses */
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM courses"
);

if ($result) {
    $course_count = $result->fetch_assoc()["total"];
}

/* Count departments */
$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM departments"
);

if ($result) {
    $department_count = $result->fetch_assoc()["total"];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | Smt. Sharadamma V. Kotbal College</title>

    <link rel="stylesheet" href="../css/admin.css">

</head>

<body>

<div class="dashboard">

    <!-- Sidebar -->
    <?php include "../includes/admin_sidebar.php"; ?>

    <!-- Main Content -->
    <main class="main-content">

        <?php include "../includes/admin_header.php"; ?>

        <section class="content">

            <h2>Dashboard</h2>

            <p class="welcome">
                Welcome, <?php echo htmlspecialchars($_SESSION["username"]); ?>!
            </p>

            <div class="cards">

                <div class="card">
                    <h3>Students</h3>
                    <p><?php echo $student_count; ?></p>
                </div>

                <div class="card">
                    <h3>Lecturers</h3>
                    <p><?php echo $lecturer_count; ?></p>
                </div>

                <div class="card">
                    <h3>Courses</h3>
                    <p><?php echo $course_count; ?></p>
                </div>

                <div class="card">
                    <h3>Departments</h3>
                    <p><?php echo $department_count; ?></p>
                </div>

            </div>

            <div class="dashboard-section">

                <h3>Recent Activities</h3>

                <p>No recent activities.</p>

            </div>

        </section>

    </main>

</div>

</body>

</html>