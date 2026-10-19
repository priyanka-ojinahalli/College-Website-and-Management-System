<?php

session_start();

require_once "../config/database.php";
require_once "../config/email.php";


/* ==========================================
   CHECK REQUEST
========================================== */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: forgot_password.php");

    exit();

}


/* ==========================================
   GET USERNAME / REGISTRATION NUMBER
========================================== */

$identifier = trim(
    $_POST["identifier"] ?? ""
);


if ($identifier === "") {

    $_SESSION["forgot_error"] =
        "Please enter your username or registration number.";

    header("Location: forgot_password.php");

    exit();

}


/* ==========================================
   FIND USER
   1. Username from users table
   2. Registration number from students table
========================================== */

$stmt = $conn->prepare(

    "SELECT
        u.id,
        u.username,
        u.email,
        u.role
     FROM users u
     LEFT JOIN students s
        ON s.user_id = u.id
     WHERE
        u.username = ?
        OR s.registration_no = ?
     LIMIT 1"

);


if (!$stmt) {

    $_SESSION["forgot_error"] =
        "Database error. Please try again.";

    header("Location: forgot_password.php");

    exit();

}


$stmt->bind_param(
    "ss",
    $identifier,
    $identifier
);

$stmt->execute();

$result = $stmt->get_result();


/* ==========================================
   USER NOT FOUND
========================================== */

if ($result->num_rows !== 1) {

    $_SESSION["forgot_error"] =
        "No account was found with that username or registration number.";

    header("Location: forgot_password.php");

    exit();

}


$user = $result->fetch_assoc();


/* ==========================================
   CHECK EMAIL
========================================== */

if (

    empty($user["email"]) ||

    !filter_var(
        $user["email"],
        FILTER_VALIDATE_EMAIL
    )

) {

    $_SESSION["forgot_error"] =
        "No valid registered email address is available for this account.";

    header("Location: forgot_password.php");

    exit();

}


/* ==========================================
   GENERATE 6-DIGIT OTP
========================================== */

$otp = str_pad(

    (string) random_int(
        0,
        999999
    ),

    6,

    "0",

    STR_PAD_LEFT

);


/* ==========================================
   HASH OTP
========================================== */

$otp_hash = password_hash(
    $otp,
    PASSWORD_DEFAULT
);


/* ==========================================
   OTP EXPIRES IN 5 MINUTES
========================================== */

$expires_at = date(
    "Y-m-d H:i:s",
    time() + 300
);


/* ==========================================
   RESET OTP ATTEMPTS
========================================== */

$otp_attempts = 0;


/* ==========================================
   SAVE OTP
========================================== */

$update = $conn->prepare(

    "UPDATE users
     SET
        otp_code = ?,
        otp_expires_at = ?,
        otp_attempts = ?
     WHERE id = ?"

);


if (!$update) {

    $_SESSION["forgot_error"] =
        "Unable to prepare OTP request.";

    header("Location: forgot_password.php");

    exit();

}


$update->bind_param(

    "ssii",

    $otp_hash,
    $expires_at,
    $otp_attempts,
    $user["id"]

);


if (!$update->execute()) {

    $_SESSION["forgot_error"] =
        "Unable to generate OTP.";

    header("Location: forgot_password.php");

    exit();

}


/* ==========================================
   EMAIL BODY
========================================== */

$email_body = "

<html>

<body style='font-family:Arial,Helvetica,sans-serif;'>

<h2>Password Reset Verification</h2>

<p>
Hello " .
htmlspecialchars($user["username"]) .
",
</p>

<p>
We received a request to reset your college account password.
</p>

<p>
Your One-Time Password (OTP) is:
</p>

<h1 style='letter-spacing:6px;'>
" . $otp . "
</h1>

<p>
This OTP will expire in
<strong>5 minutes</strong>.
</p>

<p>
If you did not request a password reset,
please ignore this email.
</p>

<br>

<p>
<strong>
Smt. Sharadamma V. Kotbal<br>
BBA, BCA &amp; B.Com College<br>
Koppal, Karnataka
</strong>
</p>

</body>

</html>

";


/* ==========================================
   SEND EMAIL
========================================== */

if (

    !sendEmail(

        $user["email"],

        "College Password Reset OTP",

        $email_body

    )

) {

    $_SESSION["forgot_error"] =
        "Unable to send OTP. Please try again later.";

    header("Location: forgot_password.php");

    exit();

}


/* ==========================================
   CREATE PASSWORD RESET SESSION
========================================== */

$_SESSION["pending_reset_user_id"] =
    $user["id"];


/* ==========================================
   SUCCESS MESSAGE
========================================== */

$_SESSION["forgot_success"] =
    "OTP has been sent to your registered email address.";


/* ==========================================
   REDIRECT TO FORGOT PASSWORD PAGE
========================================== */

header(
    "Location: forgot_password.php"
);

exit();

?>