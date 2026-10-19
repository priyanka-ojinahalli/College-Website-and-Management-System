<?php

session_start();

require_once "../config/database.php";


/* ==========================================
   CHECK RESET SESSION
========================================== */

if (!isset($_SESSION["pending_reset_user_id"])) {

    $_SESSION["forgot_error"] =
        "Please request a password reset OTP first.";

    header("Location: forgot_password.php");

    exit();

}


$error = $_SESSION["reset_error"] ?? "";

unset($_SESSION["reset_error"]);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Verify OTP - Reset Password</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #eef2ff,
                    #f8fafc
                );

            min-height: 100vh;

            padding: 40px 20px;

        }


        .wrapper {

            width: 100%;

            max-width: 600px;

            margin: auto;

        }


        .college-header {

            text-align: center;

            margin-bottom: 30px;

        }


        .college-header h1 {

            margin: 0;

            color: #172554;

            font-size: 32px;

        }


        .college-header p {

            margin-top: 8px;

            color: #64748b;

            font-size: 16px;

        }


        .card {

            background: #ffffff;

            border-radius: 16px;

            padding: 40px;

            box-shadow:
                0 15px 40px
                rgba(15, 23, 42, 0.10);

            border:
                1px solid #e2e8f0;

        }


        h2 {

            margin-top: 0;

            color: #172554;

            font-size: 29px;

        }


        .description {

            color: #64748b;

            line-height: 1.6;

            margin-bottom: 25px;

        }


        .otp-info {

            background: #eff6ff;

            border-left:
                4px solid #2563eb;

            padding: 15px;

            border-radius: 7px;

            color: #1e3a8a;

            font-size: 14px;

            line-height: 1.6;

            margin-bottom: 25px;

        }


        .error-message {

            background: #fef2f2;

            color: #b91c1c;

            border:
                1px solid #fecaca;

            padding: 14px;

            border-radius: 8px;

            margin-bottom: 22px;

        }


        .form-group {

            margin-bottom: 20px;

        }


        label {

            display: block;

            margin-bottom: 8px;

            font-weight: 600;

            color: #334155;

        }


        input {

            width: 100%;

            padding: 14px;

            border:
                1px solid #cbd5e1;

            border-radius: 8px;

            font-size: 15px;

            outline: none;

        }


        input:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.10);

        }


        .otp-input {

            text-align: center;

            letter-spacing: 8px;

            font-size: 22px;

            font-weight: bold;

        }


        button {

            width: 100%;

            padding: 14px;

            border: none;

            border-radius: 8px;

            background: #172554;

            color: white;

            font-size: 16px;

            font-weight: 600;

            cursor: pointer;

        }


        button:hover {

            background: #1e3a8a;

        }


        .back-link {

            text-align: center;

            margin-top: 22px;

        }


        .back-link a {

            color: #2563eb;

            text-decoration: none;

            font-weight: 600;

        }


        @media (max-width: 600px) {

            body {

                padding: 25px 15px;

            }


            .card {

                padding: 25px;

            }


            .college-header h1 {

                font-size: 25px;

            }

        }

    </style>

</head>


<body>


<div class="wrapper">


    <!-- COLLEGE HEADER -->

    <div class="college-header">

        <h1>
            Smt. Sharadamma V. Kotbal
        </h1>

        <p>
            BBA, BCA &amp; B.Com College,
            Koppal, Karnataka
        </p>

    </div>


    <!-- CARD -->

    <div class="card">


        <h2>
            Verify OTP
        </h2>


        <p class="description">

            Enter the OTP sent to your
            registered email address and
            create your new password.

        </p>


        <?php if ($error !== ""): ?>

            <div class="error-message">

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <div class="otp-info">

            A 6-digit OTP has been sent to
            your registered email address.

            <br><br>

            The OTP is valid for
            <strong>5 minutes</strong>.

        </div>


        <form
            action="reset_password_process.php"
            method="POST"
        >


            <!-- OTP -->

            <div class="form-group">

                <label for="otp">

                    Enter 6-Digit OTP

                </label>


                <input
                    type="text"
                    id="otp"
                    name="otp"
                    class="otp-input"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    inputmode="numeric"
                    placeholder="000000"
                    autocomplete="one-time-code"
                    required
                >

            </div>


            <!-- NEW PASSWORD -->

            <div class="form-group">

                <label for="password">

                    New Password

                </label>


                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter new password"
                    minlength="6"
                    required
                    autocomplete="new-password"
                >

            </div>


            <!-- CONFIRM PASSWORD -->

            <div class="form-group">

                <label for="confirm_password">

                    Confirm New Password

                </label>


                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Re-enter new password"
                    minlength="6"
                    required
                    autocomplete="new-password"
                >

            </div>


            <button type="submit">

                Verify OTP &amp; Change Password

            </button>


        </form>


        <div class="back-link">

            <a href="login.php">

                ← Back to Login

            </a>

        </div>


    </div>

</div>


</body>

</html>