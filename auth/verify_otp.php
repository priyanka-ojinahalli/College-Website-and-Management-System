<?php

session_start();

if (!isset($_SESSION["pending_user_id"])) {
    header("Location: login.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Verify OTP</title>

</head>

<body>

    <h1>OTP Verification</h1>

    <p>
        A 6-digit OTP has been sent to your registered email address.
    </p>

    <form action="verify_otp_process.php" method="POST">

        <label for="otp">Enter OTP:</label>

        <input
            type="text"
            id="otp"
            name="otp"
            maxlength="6"
            pattern="[0-9]{6}"
            required
        >

        <br><br>

        <button type="submit">
            Verify OTP
        </button>

    </form>

</body>

</html>