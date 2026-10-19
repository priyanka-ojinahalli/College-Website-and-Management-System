<?php

session_start();

require_once "../config/database.php";


/* ==========================================
   CHECK RESET SESSION
========================================== */

if (!isset($_SESSION["pending_reset_user_id"])) {

    $_SESSION["forgot_error"] =
        "Please request a password reset first.";

    header("Location: forgot_password.php");

    exit();

}


$user_id = (int) $_SESSION["pending_reset_user_id"];


/* ==========================================
   CHECK REQUEST METHOD
========================================== */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: reset_password.php");

    exit();

}


/* ==========================================
   GET FORM DATA
========================================== */

$otp = trim(
    $_POST["otp"] ?? ""
);

$password =
    $_POST["password"] ?? "";

$confirm_password =
    $_POST["confirm_password"] ?? "";


/* ==========================================
   CHECK OTP FORMAT
========================================== */

if (!preg_match(
    '/^[0-9]{6}$/',
    $otp
)) {

    $_SESSION["reset_error"] =
        "Please enter a valid 6-digit OTP.";

    header("Location: reset_password.php");

    exit();

}


/* ==========================================
   CHECK PASSWORD
========================================== */

if ($password === "") {

    $_SESSION["reset_error"] =
        "Please enter a new password.";

    header("Location: reset_password.php");

    exit();

}


/* ==========================================
   PASSWORD LENGTH
========================================== */

if (strlen($password) < 8) {

    $_SESSION["reset_error"] =
        "Password must be at least 8 characters long.";

    header("Location: reset_password.php");

    exit();

}


/* ==========================================
   CONFIRM PASSWORD
========================================== */

if ($password !== $confirm_password) {

    $_SESSION["reset_error"] =
        "New password and confirm password do not match.";

    header("Location: reset_password.php");

    exit();

}


/* ==========================================
   GET USER AND OTP
========================================== */

$stmt = $conn->prepare(

    "SELECT
        id,
        username,
        email,
        password,
        otp_code,
        otp_expires_at,
        otp_attempts
     FROM users
     WHERE id = ?
     LIMIT 1"

);


if (!$stmt) {

    $_SESSION["reset_error"] =
        "Database error. Please try again.";

    header("Location: reset_password.php");

    exit();

}


$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();


/* ==========================================
   USER NOT FOUND
========================================== */

if ($result->num_rows !== 1) {

    unset(
        $_SESSION["pending_reset_user_id"]
    );

    $_SESSION["forgot_error"] =
        "User account not found.";

    header("Location: forgot_password.php");

    exit();

}


$user = $result->fetch_assoc();


/* ==========================================
   CHECK OTP ATTEMPTS
========================================== */

if (
    (int)$user["otp_attempts"] >= 5
) {

    unset(
        $_SESSION["pending_reset_user_id"]
    );

    $_SESSION["forgot_error"] =
        "Too many incorrect OTP attempts. Please request a new OTP.";

    header("Location: forgot_password.php");

    exit();

}


/* ==========================================
   CHECK OTP EXISTS
========================================== */

if (
    empty($user["otp_code"])
) {

    unset(
        $_SESSION["pending_reset_user_id"]
    );

    $_SESSION["forgot_error"] =
        "No valid OTP was found. Please request a new OTP.";

    header("Location: forgot_password.php");

    exit();

}


/* ==========================================
   CHECK OTP EXPIRATION
========================================== */

if (

    empty($user["otp_expires_at"]) ||

    strtotime(
        $user["otp_expires_at"]
    ) < time()

) {

    unset(
        $_SESSION["pending_reset_user_id"]
    );

    $_SESSION["forgot_error"] =
        "OTP has expired. Please request a new OTP.";

    header("Location: forgot_password.php");

    exit();

}


/* ==========================================
   VERIFY OTP
========================================== */

if (

    !password_verify(
        $otp,
        $user["otp_code"]
    )

) {

    $new_attempts =
        (int)$user["otp_attempts"] + 1;


    $attempt = $conn->prepare(

        "UPDATE users
         SET otp_attempts = ?
         WHERE id = ?"

    );


    $attempt->bind_param(
        "ii",
        $new_attempts,
        $user_id
    );


    $attempt->execute();


    $_SESSION["reset_error"] =
        "Incorrect OTP. Please try again.";

    header("Location: reset_password.php");

    exit();

}


/* ==========================================
   CREATE NEW PASSWORD HASH
========================================== */

$password_hash = password_hash(
    $password,
    PASSWORD_DEFAULT
);


/* ==========================================
   UPDATE PASSWORD
========================================== */

$update = $conn->prepare(

    "UPDATE users
     SET
        password = ?,
        otp_code = NULL,
        otp_expires_at = NULL,
        otp_attempts = 0
     WHERE id = ?"

);


if (!$update) {

    $_SESSION["reset_error"] =
        "Unable to update password. Please try again.";

    header("Location: reset_password.php");

    exit();

}


$update->bind_param(
    "si",
    $password_hash,
    $user_id
);


/* ==========================================
   EXECUTE UPDATE
========================================== */

if (!$update->execute()) {

    $_SESSION["reset_error"] =
        "Unable to update password. Please try again.";

    header("Location: reset_password.php");

    exit();

}


/* ==========================================
   CLEAR RESET SESSION
========================================== */

unset(
    $_SESSION["pending_reset_user_id"]
);


/* ==========================================
   SUCCESS MESSAGE
========================================== */

$_SESSION["auth_success"] =
    "Password changed successfully. Please login with your new password.";


/* ==========================================
   REDIRECT TO LOGIN
========================================== */

header(
    "Location: login.php"
);

exit();

?>