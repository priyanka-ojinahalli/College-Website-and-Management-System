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
$principal = null;


/* =====================================================
   GET PRINCIPAL ID
===================================================== */

$principal_id = (int)($_GET["id"] ?? 0);


/* =====================================================
   FETCH PRINCIPAL
===================================================== */

if ($principal_id <= 0) {

    header("Location: index.php");
    exit();

}


$stmt = $conn->prepare("
    SELECT
        p.id,
        p.user_id,
        p.principal_id,
        p.full_name,
        p.email,
        p.phone,
        p.designation
    FROM principals p
    WHERE p.id = ?
    LIMIT 1
");


$stmt->bind_param(
    "i",
    $principal_id
);


$stmt->execute();


$result = $stmt->get_result();


if ($result->num_rows !== 1) {

    $stmt->close();

    header("Location: index.php");
    exit();

}


$principal = $result->fetch_assoc();


$stmt->close();


/* =====================================================
   UPDATE PRINCIPAL
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    /* -------------------------------------------------
       GET FORM DATA
    ------------------------------------------------- */

    $principal_code = trim(
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

    $designation = trim(
        $_POST["designation"] ?? ""
    );


    /* =================================================
       REQUIRED FIELD VALIDATION
    ================================================= */

    if (
        $principal_code === "" ||
        $full_name === "" ||
        $email === ""
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
                $principal_code
            )
        ) {

            $error =
                "Principal ID contains invalid characters.";

        }

    }


    /* =================================================
       CHECK DUPLICATE PRINCIPAL ID
    ================================================= */

    if ($error === "") {

        $check = $conn->prepare("
            SELECT id
            FROM principals
            WHERE principal_id = ?
              AND id != ?
            LIMIT 1
        ");


        $check->bind_param(
            "si",
            $principal_code,
            $principal_id
        );


        $check->execute();


        $check_result =
            $check->get_result();


        if ($check_result->num_rows > 0) {

            $error =
                "This Principal ID is already being used.";

        }


        $check->close();

    }


    /* =================================================
       CHECK DUPLICATE EMAIL
       IN USERS TABLE
    ================================================= */

    if ($error === "") {

        $check = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = ?
              AND id != ?
            LIMIT 1
        ");


        $check->bind_param(
            "si",
            $email,
            $principal["user_id"]
        );


        $check->execute();


        $check_result =
            $check->get_result();


        if ($check_result->num_rows > 0) {

            $error =
                "This email address is already being used.";

        }


        $check->close();

    }


    /* =================================================
       UPDATE
    ================================================= */

    if ($error === "") {

        $conn->begin_transaction();


        try {


            /* =========================================
               UPDATE USERS TABLE
            ========================================= */

            $user_stmt = $conn->prepare("
                UPDATE users
                SET
                    username = ?,
                    email = ?
                WHERE id = ?
            ");


            if (!$user_stmt) {

                throw new Exception(
                    "Unable to prepare user account update."
                );

            }


            $user_stmt->bind_param(
                "ssi",
                $email,
                $email,
                $principal["user_id"]
            );


            if (
                !$user_stmt->execute()
            ) {

                throw new Exception(
                    "Unable to update principal login account: " .
                    $user_stmt->error
                );

            }


            $user_stmt->close();


            /* =========================================
               UPDATE PRINCIPAL TABLE
            ========================================= */

            $principal_stmt =
                $conn->prepare("
                    UPDATE principals
                    SET
                        principal_id = ?,
                        full_name = ?,
                        email = ?,
                        phone = ?,
                        designation = ?
                    WHERE id = ?
                ");


            if (!$principal_stmt) {

                throw new Exception(
                    "Unable to prepare principal update."
                );

            }


            $principal_stmt->bind_param(
                "sssssi",
                $principal_code,
                $full_name,
                $email,
                $phone,
                $designation,
                $principal_id
            );


            if (
                !$principal_stmt->execute()
            ) {

                throw new Exception(
                    "Unable to update principal details: " .
                    $principal_stmt->error
                );

            }


            $principal_stmt->close();


            /* =========================================
               SUCCESS
            ========================================= */

            $conn->commit();


            header(
                "Location: index.php?updated=1"
            );

            exit();


        } catch (Exception $e) {

            $conn->rollback();

            $error =
                $e->getMessage();

        }

    }


    /* =================================================
       KEEP ENTERED VALUES AFTER ERROR
    ================================================= */

    $principal["principal_id"] =
        $principal_code;

    $principal["full_name"] =
        $full_name;

    $principal["email"] =
        $email;

    $principal["phone"] =
        $phone;

    $principal["designation"] =
        $designation;

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
        Edit Principal - Admin
    </title>


    <link
        rel="stylesheet"
        href="../../css/admin.css?v=18"
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

            font-size: 12px;

            color: #6b7280;

            margin-top: 5px;

        }


        .login-info {

            background: #eff6ff;

            border-left:
                4px solid #2563eb;

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


        .btn-primary:hover {

            background: #1e3a8a;

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


        .btn-secondary:hover {

            background: #d1d5db;

        }


        @media (max-width: 700px) {

            .form-grid {

                grid-template-columns: 1fr;

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
                Edit Principal
            </h2>


            <p class="welcome">

                Update the college principal's details.

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


                <!-- LOGIN INFORMATION -->

                <div class="login-info">

                    <strong>
                        Principal Login Information
                    </strong>

                    <p>

                        The principal logs in using the
                        registered email address.

                    </p>

                    <p>

                        Changing the email here will also
                        change the principal's login email.

                    </p>

                    <p>

                        The existing password will not be
                        changed from this page.

                    </p>

                </div>


                <form
                    method="POST"
                    action="edit.php?id=<?php
                        echo $principal_id;
                    ?>"
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
                                        $principal[
                                            "principal_id"
                                        ]
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
                                        $principal[
                                            "full_name"
                                        ]
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
                                        $principal[
                                            "email"
                                        ]
                                    );

                                ?>"
                                placeholder="principal@gmail.com"
                            >


                            <div class="help-text">

                                This is also the principal's
                                login email.

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
                                        $principal[
                                            "phone"
                                        ] ?? ""
                                    );

                                ?>"
                                placeholder="10-digit mobile number"
                            >

                        </div>


                        <!-- DESIGNATION -->

                        <div class="form-group">

                            <label>
                                Designation
                            </label>


                            <input
                                type="text"
                                name="designation"
                                maxlength="100"
                                value="<?php

                                    echo htmlspecialchars(
                                        $principal[
                                            "designation"
                                        ] ??
                                        "Principal"
                                    );

                                ?>"
                                placeholder="Principal"
                            >

                        </div>


                    </div>


                    <!-- ACTIONS -->

                    <div class="form-actions">


                        <button
                            type="submit"
                            class="btn-primary"
                        >

                            Save Changes

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
   FORM VALIDATION
===================================================== */

document
    .getElementById("principalForm")
    .addEventListener(
        "submit",
        function(event) {


            const email =
                document.querySelector(
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
                document.querySelector(
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

        }
    );

</script>


</body>

</html>