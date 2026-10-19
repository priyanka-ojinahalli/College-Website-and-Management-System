<?php

session_start();

require_once "../config/database.php";


/* ==========================================
   CHECK PENDING LOGIN
========================================== */

if (!isset($_SESSION["pending_user_id"])) {

    $_SESSION["auth_error"] =
        "Please login first.";

    header("Location: login.php");

    exit();

}


$user_id = $_SESSION["pending_user_id"];


/* ==========================================
   GET OTP
========================================== */

$otp = trim(
    $_POST["otp"] ?? ""
);


/* ==========================================
   OTP FORMAT
========================================== */

if (!preg_match(
    '/^[0-9]{6}$/',
    $otp
)) {

    $_SESSION["otp_error"] =
        "Please enter a valid 6-digit OTP.";

    header("Location: login.php");

    exit();

}


/* ==========================================
   GET USER
========================================== */

$stmt = $conn->prepare(

    "SELECT
        id,
        username,
        email,
        password,
        role,
        otp_code,
        otp_expires_at,
        otp_attempts
     FROM users
     WHERE id = ?
     LIMIT 1"

);


$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows !== 1) {

    unset(
        $_SESSION["pending_user_id"]
    );

    $_SESSION["auth_error"] =
        "User account not found.";

    header("Location: login.php");

    exit();

}


$user = $result->fetch_assoc();


/* ==========================================
   MAXIMUM 5 ATTEMPTS
========================================== */

if (
    (int)$user["otp_attempts"] >= 5
) {

    unset(
        $_SESSION["pending_user_id"]
    );

    $_SESSION["auth_error"] =
        "Too many incorrect OTP attempts. Please login again.";

    header("Location: login.php");

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
        $_SESSION["pending_user_id"]
    );

    $_SESSION["auth_error"] =
        "OTP has expired. Please login again.";

    header("Location: login.php");

    exit();

}


/* ==========================================
   VERIFY OTP
========================================== */

if (

    empty($user["otp_code"]) ||

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


    $_SESSION["otp_error"] =
        "Incorrect OTP. Please try again.";

    header("Location: login.php");

    exit();

}


/* ==========================================
   OTP CORRECT
========================================== */

session_regenerate_id(true);


/* ==========================================
   CREATE AUTHENTICATED SESSION
========================================== */

$_SESSION["user_id"] =
    $user["id"];

$_SESSION["username"] =
    $user["username"];

$_SESSION["email"] =
    $user["email"];

$_SESSION["role"] =
    $user["role"];


/* ==========================================
   CLEAR OTP
========================================== */

$clear = $conn->prepare(

    "UPDATE users
     SET
        otp_code = NULL,
        otp_expires_at = NULL,
        otp_attempts = 0
     WHERE id = ?"

);


$clear->bind_param(
    "i",
    $user_id
);

$clear->execute();


/* ==========================================
   REMOVE TEMPORARY LOGIN
========================================== */

unset(
    $_SESSION["pending_user_id"]
);


/* ==========================================
   ROLE-BASED REDIRECTION
========================================== */

switch ($user["role"]) {


    case "student":

        header(
            "Location: ../student/dashboard.php"
        );

        break;


    case "lecturer":

        header(
            "Location: ../lecturer/dashboard.php"
        );

        break;


    case "principal":

        header(
            "Location: ../principal/dashboard.php"
        );

        break;


    case "admin":

        header(
            "Location: ../admin/dashboard.php"
        );

        break;


    default:

        session_destroy();

        die(
            "Invalid user role."
        );

}


exit();

?>