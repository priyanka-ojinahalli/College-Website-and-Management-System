<?php

session_start();

if (isset($_GET["start"])) {

    unset($_SESSION["pending_reset_user_id"]);

}
$error = $_SESSION["forgot_error"] ?? "";
$success = $_SESSION["forgot_success"] ?? "";

unset($_SESSION["forgot_error"]);
unset($_SESSION["forgot_success"]);

$otp_pending = isset($_SESSION["pending_reset_user_id"]);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Forgot Password - College</title>


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


        /* =========================
           COLLEGE HEADER
        ========================= */

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


        /* =========================
           CARD
        ========================= */

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


        .card h2 {

            margin-top: 0;

            margin-bottom: 12px;

            color: #172554;

            font-size: 30px;

        }


        .description {

            color: #64748b;

            font-size: 15px;

            line-height: 1.6;

            margin-bottom: 25px;

        }


        /* =========================
           LABEL
        ========================= */

        label {

            display: block;

            margin-bottom: 8px;

            font-weight: 600;

            color: #334155;

        }


        /* =========================
           INPUT
        ========================= */

        input {

            width: 100%;

            padding: 14px 15px;

            border:
                1px solid #cbd5e1;

            border-radius: 8px;

            font-size: 15px;

            outline: none;

            transition: 0.2s;

        }


        input:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, 0.10);

        }


        /* =========================
           BUTTON
        ========================= */

        .main-button {

            width: 100%;

            padding: 14px;

            border: none;

            border-radius: 8px;

            background: #172554;

            color: #ffffff;

            font-size: 16px;

            font-weight: 600;

            cursor: pointer;

            transition: 0.2s;

        }


        .main-button:hover {

            background: #1e3a8a;

        }


        /* =========================
           SUCCESS
        ========================= */

        .success-message {

            background: #f0fdf4;

            color: #047857;

            border:
                1px solid #bbf7d0;

            padding: 14px;

            border-radius: 8px;

            margin-bottom: 22px;

            font-size: 14px;

        }


        /* =========================
           ERROR
        ========================= */

        .error-message {

            background: #fef2f2;

            color: #b91c1c;

            border:
                1px solid #fecaca;

            padding: 14px;

            border-radius: 8px;

            margin-bottom: 22px;

            font-size: 14px;

        }


        /* =========================
           OTP INFORMATION
        ========================= */

        .otp-info {

            background: #eff6ff;

            border-left:
                4px solid #2563eb;

            padding: 15px;

            border-radius: 7px;

            color: #1e3a8a;

            font-size: 14px;

            line-height: 1.6;

            margin-bottom: 22px;

        }


        /* =========================
           BACK LINK
        ========================= */

        .back-link {

            text-align: center;

            margin-top: 22px;

        }


        .back-link a {

            color: #2563eb;

            text-decoration: none;

            font-size: 14px;

            font-weight: 600;

        }


        .back-link a:hover {

            text-decoration: underline;

        }


        /* =========================
           MOBILE
        ========================= */

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


            .card h2 {

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


    <!-- MAIN CARD -->

    <div class="card">


        <h2>
            Forgot Password?
        </h2>


        <p class="description">

            Enter your username or student
            registration number. An OTP will be
            sent to your registered email address.

        </p>


        <!-- ERROR -->

        <?php if ($error !== ""): ?>

            <div class="error-message">

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <!-- SUCCESS -->

        <?php if ($success !== ""): ?>

            <div class="success-message">

                <?php
                echo htmlspecialchars($success);
                ?>

            </div>

        <?php endif; ?>


        <?php if (!$otp_pending): ?>


            <!-- IDENTIFIER FORM -->

            <form
                action="forgot_password_send.php"
                method="POST"
            >

                <label for="identifier">

                    Username /
                    Registration Number

                </label>


                <input
                    type="text"
                    id="identifier"
                    name="identifier"
                    placeholder="Enter username or registration number"
                    required
                    autocomplete="username"
                >


                <br><br>


                <button
                    type="submit"
                    class="main-button"
                >

                    Send OTP

                </button>

            </form>


        <?php else: ?>

    <!-- OTP SENT MESSAGE -->

    <div class="success-message">

        OTP has been sent to your
        registered email address.

    </div>


    <div class="otp-info">

        Your OTP is valid for
        <strong>5 minutes</strong>.

        <br>

        Click the button below to verify
        your OTP and create a new password.

    </div>


    <!-- CONTINUE BUTTON -->

    <a
        href="reset_password.php"
        class="main-button"
        style="
            display:block;
            text-align:center;
            text-decoration:none;
        "
    >

        Continue to OTP Verification

    </a>


<?php endif; ?>


        <!-- BACK TO LOGIN -->

        <div class="back-link">

            <a href="login.php">

                ← Back to Login

            </a>

        </div>


    </div>


</div>


</body>

</html>