<?php

session_start();

require_once "../config/database.php";
require_once "../config/email.php";


/* ==========================================
   CHECK REQUEST
========================================== */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: login.php");

    exit();

}


/* ==========================================
   GET LOGIN DATA
========================================== */

$username = trim(
    $_POST["username"] ?? ""
);

$password = $_POST["password"] ?? "";


if ($username === "" || $password === "") {

    $_SESSION["auth_error"] =
        "Username and password are required.";

    header("Location: login.php");

    exit();

}


/* ==========================================
   FIND USER
========================================== */

$stmt = $conn->prepare(

    "SELECT
        id,
        username,
        email,
        password,
        role
     FROM users
     WHERE username = ?
     LIMIT 1"

);


if (!$stmt) {

    $_SESSION["auth_error"] =
        "Database error. Please try again.";

    header("Location: login.php");

    exit();

}


$stmt->bind_param(
    "s",
    $username
);

$stmt->execute();

$result = $stmt->get_result();


/* ==========================================
   USER NOT FOUND
========================================== */

if ($result->num_rows !== 1) {

    $_SESSION["auth_error"] =
        "Invalid username or password.";

    header("Location: login.php");

    exit();

}


$user = $result->fetch_assoc();


/* ==========================================
   VERIFY PASSWORD
========================================== */

if (!password_verify(
    $password,
    $user["password"]
)) {

    $_SESSION["auth_error"] =
        "Invalid username or password.";

    header("Location: login.php");

    exit();

}


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

    $_SESSION["auth_error"] =
        "No valid registered email address is available for this account.";

    header("Location: login.php");

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
   RESET ATTEMPTS
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

    $_SESSION["auth_error"] =
        "Unable to prepare OTP request.";

    header("Location: login.php");

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

    $_SESSION["auth_error"] =
        "Unable to generate OTP.";

    header("Location: login.php");

    exit();

}


/* ==========================================
   OTP EMAIL
========================================== */

$email_body = "

<html>

<body>

<h2>College Login Verification</h2>

<p>
Hello " .
htmlspecialchars($user["username"]) .
",
</p>

<p>
Your One-Time Password (OTP) is:
</p>

<h1 style='letter-spacing:5px;'>
" . $otp . "
</h1>

<p>
This OTP will expire in
<strong>5 minutes</strong>.
</p>

<p>
If you did not attempt to log in,
please ignore this email.
</p>

<p>
<strong>
Smt. Sharadamma V. Kotbal
BBA, BCA &amp; B.Com College
</strong>
</p>

</body>

</html>

";


/* ==========================================
   SEND EMAIL
========================================== */

if (!sendEmail(

    $user["email"],

    "College Login OTP",

    $email_body

)) {

    $_SESSION["auth_error"] =
        "Unable to send OTP. Please try again later.";

    header("Location: login.php");

    exit();

}


/* ==========================================
   TEMPORARY LOGIN SESSION
========================================== */

$_SESSION["pending_user_id"] =
    $user["id"];


/* ==========================================
   GO BACK TO LOGIN PAGE
   OTP BOX WILL NOW APPEAR
========================================== */

header(
    "Location: login.php"
);

exit();

?>