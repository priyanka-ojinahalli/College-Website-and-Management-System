<?php

session_start();

$error = $_SESSION["auth_error"] ?? "";
$otp_error = $_SESSION["otp_error"] ?? "";

unset($_SESSION["auth_error"]);
unset($_SESSION["otp_error"]);
$success = $_SESSION["auth_success"] ?? "";

unset($_SESSION["auth_success"]);

$otp_pending = isset($_SESSION["pending_user_id"]);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>College Login</title>


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


        .login-wrapper {

            width: 100%;

            max-width: 480px;

            margin: 0 auto;

        }


        /* =========================
           COLLEGE HEADER
        ========================= */

        .college-header {

            text-align: center;

            margin-bottom: 25px;

        }


        .college-header h1 {

            margin: 0;

            color: #172554;

            font-size: 28px;

        }


        .college-header p {

            margin-top: 8px;

            color: #64748b;

            font-size: 15px;

        }


        /* =========================
           CARD
        ========================= */

        .login-card {

            background: #ffffff;

            border-radius: 14px;

            padding: 30px;

            margin-bottom: 20px;

            box-shadow:
                0 10px 30px
                rgba(15, 23, 42, 0.10);

            border:
                1px solid #e2e8f0;

        }


        .card-title {

            margin-top: 0;

            margin-bottom: 7px;

            color: #172554;

            font-size: 23px;

        }


        .card-description {

            color: #64748b;

            margin-top: 0;

            margin-bottom: 25px;

            font-size: 14px;

        }


        /* =========================
           FORM
        ========================= */

        .form-group {

            margin-bottom: 18px;

        }


        label {

            display: block;

            margin-bottom: 7px;

            font-weight: 600;

            color: #334155;

        }


        input {

            width: 100%;

            padding: 13px 14px;

            border:
                1px solid #cbd5e1;

            border-radius: 7px;

            font-size: 15px;

            outline: none;

            transition: 0.2s;

        }


        input:focus {

            border-color: #172554;

            box-shadow:
                0 0 0 3px
                rgba(23, 37, 84, 0.10);

        }


        button {

            width: 100%;

            padding: 13px;

            border: none;

            border-radius: 7px;

            background: #172554;

            color: white;

            font-size: 16px;

            font-weight: 600;

            cursor: pointer;

            transition: 0.2s;

        }


        button:hover {

            background: #1e3a8a;

        }


        /* =========================
           OTP
        ========================= */

        .otp-card {

            border-top:
                4px solid #172554;

        }


        .otp-input {

            text-align: center;

            letter-spacing: 8px;

            font-size: 22px;

            font-weight: bold;

        }


        .otp-note {

            background: #eff6ff;

            border-left:
                4px solid #2563eb;

            padding: 12px;

            margin-bottom: 20px;

            color: #1e3a8a;

            font-size: 14px;

            border-radius: 5px;

        }


        /* =========================
           ERROR
        ========================= */

        .error-message {

            background: #fef2f2;

            color: #b91c1c;

            border:
                1px solid #fecaca;

            padding: 12px;

            border-radius: 6px;

            margin-bottom: 18px;

            font-size: 14px;

        }


        /* =========================
           LOGIN HELP
        ========================= */

        .help-text {

            text-align: center;

            margin-top: 20px;

            color: #64748b;

            font-size: 13px;

        }


        .help-text strong {

            color: #334155;

        }


        /* =========================
           FOOTER
        ========================= */

        .footer {

            text-align: center;

            color: #94a3b8;

            font-size: 12px;

            margin-top: 25px;

        }


        @media (max-width: 500px) {

            body {

                padding:
                    25px 15px;

            }


            .login-card {

                padding: 22px;

            }


            .college-header h1 {

                font-size: 23px;

            }

        }
        .forgot-password {
    text-align: right;
    margin-top: -8px;
    margin-bottom: 20px;
}

.forgot-password a {
    color: #2563eb;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
}

.forgot-password a:hover {
    text-decoration: underline;
}

    </style>

</head>


<body>


<div class="login-wrapper">


    <!-- =========================
         COLLEGE HEADER
    ========================= -->

    <div class="college-header">

        <h1>
            Smt. Sharadamma V. Kotbal
        </h1>

        <p>
            BBA, BCA &amp; B.Com College,
            Koppal, Karnataka
        </p>

    </div>



    <!-- =========================
         LOGIN CARD
    ========================= -->

    <div class="login-card">

        <h2 class="card-title">
            College Login
        </h2>

        <p class="card-description">
            Login using your account credentials.
        </p>


        <?php if ($error !== ""): ?>

            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endif; ?>

<?php if ($success !== ""): ?>

    <div class="success-message">
        <?php echo htmlspecialchars($success); ?>
    </div>

<?php endif; ?>
        <form
            action="authenticate.php"
            method="POST"
        >


            <div class="form-group">

                <label for="username">

                    Username /
                    Registration Number

                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter username or registration number"
                    required
                    autocomplete="username"
                >

            </div>


            <div class="form-group">

                <label for="password">

                    Password

                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                >

            </div>
            <div class="forgot-password">
                <a href="forgot_password.php?start=1">
                   Forgot Password?
                </a>
            </div>


            <button type="submit">

                Login

            </button>


        </form>


        <div class="help-text">

            Students can login using their
            <strong>Registration Number</strong>.

        </div>

    </div>



    <!-- =========================
         OTP CARD
    ========================= -->

    <?php if ($otp_pending): ?>

        <div class="login-card otp-card">

            <h2 class="card-title">

                OTP Verification

            </h2>


            <p class="card-description">

                Verify your identity to complete login.

            </p>


            <?php if ($otp_error !== ""): ?>

                <div class="error-message">

                    <?php
                    echo htmlspecialchars($otp_error);
                    ?>

                </div>

            <?php endif; ?>


            <div class="otp-note">

                A 6-digit OTP has been sent to your
                registered email address.

                <br><br>

                The OTP is valid for
                <strong>5 minutes</strong>.

            </div>


            <form
                action="verify_otp_process.php"
                method="POST"
            >


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


                <button type="submit">

                    Verify OTP

                </button>


            </form>

        </div>

    <?php endif; ?>


    <div class="footer">

        © <?php echo date("Y"); ?>
        Smt. Sharadamma V. Kotbal BBA, BCA &amp; B.Com College

    </div>


</div>


</body>

</html>