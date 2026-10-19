<?php
session_start();

if (isset($_GET["confirm"]) && $_GET["confirm"] === "yes") {

    $_SESSION = [];

    session_destroy();

    header("Location: login.php?logout=success");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logout</title>
</head>

<body>

<script>

    const confirmLogout = confirm("Are you sure you want to logout?");

    if (confirmLogout) {

        window.location.href = "logout.php?confirm=yes";

    } else {

        window.history.back();

    }

</script>

</body>
</html>