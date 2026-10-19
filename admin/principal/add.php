<?php

session_start();

/* =====================================================
   ADMIN LOGIN CHECK
===================================================== */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../../auth/login.php");
    exit();
}


/* =====================================================
   DATABASE
===================================================== */

require_once "../../config/database.php";


$error = "";


/* =====================================================
   CHECK WHETHER PRINCIPAL ALREADY EXISTS
===================================================== */

$existing = $conn->query("
    SELECT id
    FROM principals
    LIMIT 1
");

if ($existing && $existing->num_rows > 0) {

    header("Location: index.php");

    exit();
}


/* =====================================================
   ADD PRINCIPAL
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /* -------------------------------------------------
       GET FORM DATA
    ------------------------------------------------- */

    $principal_id = trim(
        $_POST["principal_id"] ?? ""
    );

    $full_name = trim(
        $_POST["full_name"] ?? ""
    );

    $email = trim(
        $_POST["email"] ?? ""
    );

    $phone = trim(
        $_POST["phone"] ?? ""
    );

    $password = $_POST["password"] ?? "";

    $confirm_password =
        $_POST["confirm_password"] ?? "";


    /* =================================================
       REQUIRED FIELD VALIDATION
    ================================================= */

    if (
        $principal_id === "" ||
        $full_name === "" ||
        $email === "" ||
        $password === "" ||
        $confirm_password === ""
    ) {

        $error =
            "Please fill all required fields.";

    }


    /* =================================================
       EMAIL VALIDATION
    ================================================= */

    if ($error === "") {

        if (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error =
                "Please enter a valid email address.";

        }

    }


    /* =================================================
       PHONE VALIDATION
    ================================================= */

    if (
        $error === "" &&
        $phone !== ""
    ) {

        if (
            !preg_match(
                "/^[0-9]{10}$/",
                $phone
            )
        ) {

            $error =
                "Phone number must contain exactly 10 digits.";

        }

    }


    /* =================================================
       PRINCIPAL ID VALIDATION
    ================================================= */

    if ($error === "") {

        if (
            !preg_match(
                "/^[A-Za-z0-9_-]{3,30}$/",
                $principal_id
            )
        ) {

            $error =
                "Principal ID contains invalid characters.";

        }

    }


    /* =================================================
       PASSWORD VALIDATION
    ================================================= */

    if ($error === "") {

        if (strlen($password) < 8) {

            $error =
                "Password must contain at least 8 characters.";

        }

    }


    /* =================================================
       CONFIRM PASSWORD
    ================================================= */

    if ($error === "") {

        if (
            $password !==
            $confirm_password
        ) {

            $error =
                "Passwords do not match.";

        }

    }


    /* =================================================
       CHECK PRINCIPAL ID
    ================================================= */

    if ($error === "") {

        $check = $conn->prepare("
            SELECT id
            FROM principals
            WHERE principal_id = ?
            LIMIT 1
        ");

        $check->bind_param(
            "s",
            $principal_id
        );

        $check->execute();

        $check_result =
            $check->get_result();

        if (
            $check_result->num_rows > 0
        ) {

            $error =
                "This Principal ID already exists.";

        }

        $check->close();

    }


    /* =================================================
       CHECK EMAIL IN USERS TABLE
    ================================================= */

    if ($error === "") {

        $check = $conn->prepare("
            SELECT id
            FROM users
            WHERE username = ?
               OR email = ?
            LIMIT 1
        ");

        $check->bind_param(
            "ss",
            $email,
            $email
        );

        $check->execute();

        $check_result =
            $check->get_result();

        if (
            $check_result->num_rows > 0
        ) {

            $error =
                "This email is already registered.";

        }

        $check->close();

    }


    /* =================================================
       CREATE PRINCIPAL + LOGIN ACCOUNT
    ================================================= */

    if ($error === "") {

        $conn->begin_transaction();

        try {


            /* =========================================
               CREATE LOGIN ACCOUNT
            ========================================= */

            $hashed_password =
                password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


            $user_stmt = $conn->prepare("

                INSERT INTO users
                (
                    username,
                    email,
                    password,
                    role
                )

                VALUES
                (
                    ?,
                    ?,
                    ?,
                    'principal'
                )

            ");


            if (!$user_stmt) {

                throw new Exception(
                    "Unable to prepare principal login account."
                );

            }


            /*
             * Email is used as username.
             */

            $user_stmt->bind_param(
                "sss",
                $email,
                $email,
                $hashed_password
            );


            if (!$user_stmt->execute()) {

                throw new Exception(
                    "Unable to create principal login account: " .
                    $user_stmt->error
                );

            }


            /* =========================================
               GET USER ID
            ========================================= */

            $user_id =
                $conn->insert_id;


            $user_stmt->close();


            /* =========================================
               CREATE PRINCIPAL RECORD
            ========================================= */

            $principal_stmt =
                $conn->prepare("

                    INSERT INTO principals
                    (
                        user_id,
                        principal_id,
                        full_name,
                        email,
                        phone,
                        designation
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'Principal'
                    )

                ");


            if (!$principal_stmt) {

                throw new Exception(
                    "Unable to prepare principal record."
                );

            }


            $principal_stmt->bind_param(
                "issss",
                $user_id,
                $principal_id,
                $full_name,
                $email,
                $phone
            );


            if (
                !$principal_stmt->execute()
            ) {

                throw new Exception(
                    "Unable to create principal record: " .
                    $principal_stmt->error
                );

            }


            $principal_stmt->close();


            /* =========================================
               SUCCESS
            ========================================= */

            $conn->commit();


            header(
                "Location: index.php?added=1"
            );

            exit();


        } catch (Exception $e) {


            /* =========================================
               ROLLBACK
            ========================================= */

            $conn->rollback();

            $error =
                $e->getMessage();

        }

    }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Add Principal - Admin
    </title>


    <link
        rel="stylesheet"
        href="../../css/admin.css?v=16"
    >


    <style>

        .principal-form-card {

            background: #ffffff;

            max-width: 900px;

            padding: 30px;

            margin-top: 25px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

        }


        .form-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;

        }


        .form-group {

            margin-bottom: 0;

        }


        .form-group.full {

            grid-column: 1 / -1;

        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            font-weight: 600;

            color: #374151;

        }


        .required {

            color: #dc2626;

        }


        .form-group input {

            width: 100%;

            padding: 11px;

            border: 1px solid #d1d5db;

            border-radius: 5px;

            font-size: 15px;

            box-sizing: border-box;

        }


        .form-group input:focus {

            outline: none;

            border-color: #172554;

            box-shadow:
                0 0 0 2px
                rgba(23, 37, 84, 0.1);

        }


        .help-text {

            margin-top: 5px;

            font-size: 12px;

            color: #6b7280;

        }


        .login-info {

            background: #eff6ff;

            border-left:
                4px solid #172554;

            padding: 15px;

            margin-bottom: 25px;

            border-radius: 5px;

            color: #374151;

        }


        .login-info strong {

            color: #172554;

        }


        .alert-error {

            background: #fee2e2;

            color: #991b1b;

            padding: 12px 15px;

            border-radius: 6px;

            margin-top: 20px;

            max-width: 900px;

        }


        .form-actions {

            display: flex;

            gap: 10px;

            margin-top: 30px;

            padding-top: 20px;

            border-top:
                1px solid #e5e7eb;

        }


        .btn-primary {

            border: none;

            background: #172554;

            color: #ffffff;

            padding: 11px 18px;

            border-radius: 5px;

            cursor: pointer;

            font-size: 14px;

            font-weight: 600;

        }


        .btn-secondary {

            display: inline-block;

            background: #e5e7eb;

            color: #374151;

            padding: 11px 18px;

            border-radius: 5px;

            text-decoration: none;

            font-size: 14px;

        }


        .password-wrapper {

            position: relative;

        }


        .password-wrapper input {

            padding-right: 80px;

        }


        .show-password {

            position: absolute;

            right: 10px;

            top: 50%;

            transform: translateY(-50%);

            border: none;

            background: transparent;

            color: #172554;

            cursor: pointer;

            font-weight: 600;

        }


        @media (max-width: 700px) {

            .form-grid {

                grid-template-columns: 1fr;

            }


            .form-group.full {

                grid-column: auto;

            }

        }

    </style>

</head>


<body>


<div class="dashboard">


    <!-- =================================================
         SIDEBAR
    ================================================= -->

    <?php

    include "../../includes/admin_sidebar.php";

    ?>


    <!-- =================================================
         MAIN CONTENT
    ================================================= -->

    <div class="main-content">


        <!-- HEADER -->

        <?php

        include "../../includes/admin_header.php";

        ?>


        <div class="content">


            <h2>
                Add Principal
            </h2>


            <p class="welcome">

                Create the college principal's account.

            </p>


            <?php if ($error !== ""): ?>

                <div class="alert-error">

                    <?php

                    echo htmlspecialchars(
                        $error
                    );

                    ?>

                </div>

            <?php endif; ?>


            <div class="principal-form-card">


                <!-- =================================================
                     LOGIN INFORMATION
                ================================================= -->

                <div class="login-info">

                    <strong>
                        Principal Login
                    </strong>

                    <p>

                        The principal will log in using
                        their <strong>email address</strong>
                        and password.

                    </p>

                    <p>

                        OTP verification will be required
                        after entering the correct password.

                    </p>

                </div>


                <form
                    method="POST"
                    action=""
                    id="principalForm"
                    novalidate
                >


                    <div class="form-grid">


                        <!-- PRINCIPAL ID -->

                        <div class="form-group">

                            <label>

                                Principal ID

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                name="principal_id"
                                required
                                maxlength="30"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "principal_id"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="Example: PRI001"
                            >


                            <div class="help-text">

                                Example: PRI001

                            </div>

                        </div>


                        <!-- FULL NAME -->

                        <div class="form-group">

                            <label>

                                Full Name

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                name="full_name"
                                required
                                maxlength="100"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "full_name"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="Principal full name"
                            >

                        </div>


                        <!-- EMAIL -->

                        <div class="form-group">

                            <label>

                                Email Address

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <input
                                type="email"
                                name="email"
                                required
                                maxlength="150"
                                autocomplete="email"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "email"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="principal@gmail.com"
                            >


                            <div class="help-text">

                                This email will be used
                                for login and OTP verification.

                            </div>

                        </div>


                        <!-- PHONE -->

                        <div class="form-group">

                            <label>
                                Phone Number
                            </label>


                            <input
                                type="tel"
                                name="phone"
                                maxlength="10"
                                pattern="[0-9]{10}"
                                inputmode="numeric"
                                value="<?php

                                    echo htmlspecialchars(
                                        $_POST[
                                            "phone"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="10-digit mobile number"
                            >

                        </div>


                        <!-- PASSWORD -->

                        <div class="form-group">

                            <label>

                                Password

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <div class="password-wrapper">

                                <input
                                    type="password"
                                    name="password"
                                    id="password"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                    placeholder="Minimum 8 characters"
                                >


                                <button
                                    type="button"
                                    class="show-password"
                                    onclick="togglePassword(
                                        'password',
                                        this
                                    )"
                                >
                                    Show
                                </button>

                            </div>


                            <div class="help-text">

                                Minimum 8 characters.

                            </div>

                        </div>


                        <!-- CONFIRM PASSWORD -->

                        <div class="form-group">

                            <label>

                                Confirm Password

                                <span class="required">
                                    *
                                </span>

                            </label>


                            <div class="password-wrapper">

                                <input
                                    type="password"
                                    name="confirm_password"
                                    id="confirm_password"
                                    required
                                    minlength="8"
                                    autocomplete="new-password"
                                    placeholder="Re-enter password"
                                >


                                <button
                                    type="button"
                                    class="show-password"
                                    onclick="togglePassword(
                                        'confirm_password',
                                        this
                                    )"
                                >
                                    Show
                                </button>

                            </div>

                        </div>


                    </div>


                    <!-- ACTIONS -->

                    <div class="form-actions">


                        <button
                            type="submit"
                            class="btn-primary"
                        >

                            Create Principal Account

                        </button>


                        <a
                            href="index.php"
                            class="btn-secondary"
                        >

                            Cancel

                        </a>


                    </div>


                </form>


            </div>


        </div>


    </div>


</div>


<script>

/* =====================================================
   SHOW / HIDE PASSWORD
===================================================== */

function togglePassword(
    fieldId,
    button
) {

    const field =
        document.getElementById(fieldId);


    if (
        field.type === "password"
    ) {

        field.type = "text";

        button.textContent = "Hide";

    } else {

        field.type = "password";

        button.textContent = "Show";

    }

}


/* =====================================================
   FORM VALIDATION
===================================================== */

document
    .getElementById("principalForm")
    .addEventListener(
        "submit",
        function(event) {

            const email =
                document
                    .querySelector(
                        '[name="email"]'
                    );


            if (
                !email.checkValidity()
            ) {

                event.preventDefault();

                email.focus();

                alert(
                    "Please enter a valid email address."
                );

                return;

            }


            const phone =
                document
                    .querySelector(
                        '[name="phone"]'
                    )
                    .value
                    .trim();


            if (
                phone !== "" &&
                !/^[0-9]{10}$/.test(phone)
            ) {

                event.preventDefault();

                alert(
                    "Phone number must contain exactly 10 digits."
                );

                return;

            }


            const password =
                document
                    .getElementById(
                        "password"
                    )
                    .value;


            const confirmPassword =
                document
                    .getElementById(
                        "confirm_password"
                    )
                    .value;


            if (
                password.length < 8
            ) {

                event.preventDefault();

                alert(
                    "Password must contain at least 8 characters."
                );

                return;

            }


            if (
                password !==
                confirmPassword
            ) {

                event.preventDefault();

                alert(
                    "Passwords do not match."
                );

                return;

            }

        }
    );

</script>


</body>

</html>