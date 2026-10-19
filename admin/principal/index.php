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


/* =====================================================
   GET PRINCIPAL
===================================================== */

$principal = null;

$stmt = $conn->prepare("
    SELECT
        p.id,
        p.user_id,
        p.principal_id,
        p.full_name,
        p.email,
        p.phone,
        p.designation,
        u.username
    FROM principals p
    LEFT JOIN users u
        ON p.user_id = u.id
    ORDER BY p.id ASC
    LIMIT 1
");


if ($stmt) {

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $principal =
            $result->fetch_assoc();

    }

    $stmt->close();

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
        Principal Management - Admin
    </title>


    <!-- ADMIN CSS -->

    <link
        rel="stylesheet"
        href="../../css/admin.css?v=17"
    >


    <style>

        /* =================================================
           PAGE
        ================================================= */

        .principal-page {

            max-width: 1100px;

        }


        /* =================================================
           SUCCESS MESSAGE
        ================================================= */

        .success-message {

            background: #dcfce7;

            color: #166534;

            border-left:
                4px solid #16a34a;

            padding: 13px 16px;

            border-radius: 6px;

            margin-top: 20px;

            margin-bottom: 20px;

            font-size: 14px;

        }


        /* =================================================
           EMPTY CARD
        ================================================= */

        .empty-card {

            background: #ffffff;

            padding: 45px 30px;

            margin-top: 25px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

            text-align: center;

        }


        .empty-card h3 {

            margin-top: 0;

            margin-bottom: 10px;

            color: #172554;

            font-size: 22px;

        }


        .empty-card p {

            color: #6b7280;

            margin-bottom: 20px;

        }


        /* =================================================
           ADD BUTTON
        ================================================= */

        .btn-add {

            display: inline-block;

            background: #172554;

            color: #ffffff;

            text-decoration: none;

            padding: 11px 18px;

            border-radius: 6px;

            font-size: 14px;

            font-weight: 600;

            transition: 0.2s;

        }


        .btn-add:hover {

            background: #1e3a8a;

        }


        /* =================================================
           PRINCIPAL CARD
        ================================================= */

        .principal-card {

            background: #ffffff;

            margin-top: 25px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px
                rgba(0, 0, 0, 0.08);

            overflow: hidden;

        }


        /* =================================================
           CARD HEADER
        ================================================= */

        .principal-card-header {

            background: #172554;

            color: #ffffff;

            padding: 20px 25px;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;

        }


        .principal-card-header h3 {

            margin: 0;

            font-size: 20px;

        }


        .principal-card-header p {

            margin: 5px 0 0;

            font-size: 13px;

            opacity: 0.85;

        }


        /* =================================================
           ACTION BUTTONS
        ================================================= */

        .principal-actions {

            display: flex;

            gap: 8px;

            flex-wrap: wrap;

        }


        .btn-view,
        .btn-edit {

            display: inline-block;

            padding: 9px 15px;

            border-radius: 5px;

            text-decoration: none;

            font-size: 13px;

            font-weight: 600;

        }


        .btn-view {

            background: #ffffff;

            color: #172554;

        }


        .btn-view:hover {

            background: #e5e7eb;

        }


        .btn-edit {

            background: #2563eb;

            color: #ffffff;

        }


        .btn-edit:hover {

            background: #1d4ed8;

        }


        /* =================================================
           DETAILS
        ================================================= */

        .principal-details {

            padding: 25px;

        }


        .details-grid {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 20px;

        }


        .detail-item {

            padding: 15px;

            background: #f8fafc;

            border: 1px solid #e5e7eb;

            border-radius: 7px;

        }


        .detail-label {

            font-size: 12px;

            color: #6b7280;

            margin-bottom: 5px;

            font-weight: 600;

            text-transform: uppercase;

        }


        .detail-value {

            color: #111827;

            font-size: 15px;

            font-weight: 500;

            word-break: break-word;

        }


        /* =================================================
           LOGIN INFORMATION
        ================================================= */

        .login-info {

            margin-top: 25px;

            background: #eff6ff;

            border-left:
                4px solid #2563eb;

            padding: 15px;

            border-radius: 5px;

            color: #374151;

            font-size: 14px;

        }


        .login-info strong {

            color: #172554;

        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 700px) {

            .details-grid {

                grid-template-columns: 1fr;

            }


            .principal-card-header {

                flex-direction: column;

                align-items: flex-start;

            }


            .principal-actions {

                width: 100%;

            }


            .btn-view,
            .btn-edit {

                text-align: center;

            }

        }

    </style>

</head>


<body>


<div class="dashboard">


    <!-- =================================================
         ADMIN SIDEBAR
    ================================================= -->

    <?php

    include "../../includes/admin_sidebar.php";

    ?>


    <!-- =================================================
         MAIN CONTENT
    ================================================= -->

    <div class="main-content">


        <!-- =================================================
             ADMIN HEADER
        ================================================= -->

        <?php

        include "../../includes/admin_header.php";

        ?>


        <!-- =================================================
             PAGE CONTENT
        ================================================= -->

        <div class="content principal-page">


            <h2>
                Principal Management
            </h2>


            <p class="welcome">

                Manage the college principal account.

            </p>


            <!-- =================================================
                 SUCCESS MESSAGE
            ================================================= -->

            <?php if (
                isset($_GET["added"]) &&
                $_GET["added"] == "1"
            ): ?>

                <div class="success-message">

                    Principal account created successfully.

                </div>

            <?php endif; ?>


            <?php if (
                isset($_GET["updated"]) &&
                $_GET["updated"] == "1"
            ): ?>

                <div class="success-message">

                    Principal details updated successfully.

                </div>

            <?php endif; ?>


            <!-- =================================================
                 NO PRINCIPAL
            ================================================= -->

            <?php if ($principal === null): ?>


                <div class="empty-card">


                    <h3>
                        No Principal Configured
                    </h3>


                    <p>

                        A principal account has not been
                        configured for the college yet.

                    </p>


                    <a
                        href="add.php"
                        class="btn-add"
                    >

                        + Add Principal

                    </a>


                </div>


            <?php else: ?>


                <!-- =================================================
                     PRINCIPAL EXISTS
                ================================================= -->

                <div class="principal-card">


                    <!-- CARD HEADER -->

                    <div class="principal-card-header">


                        <div>

                            <h3>

                                <?php

                                echo htmlspecialchars(
                                    $principal["full_name"]
                                );

                                ?>

                            </h3>


                            <p>

                                <?php

                                echo htmlspecialchars(
                                    $principal["designation"]
                                    ?: "Principal"
                                );

                                ?>

                            </p>

                        </div>


                        <!-- ACTIONS -->

                        <div class="principal-actions">


                    

                            <a
                                href="edit.php?id=<?php
                                    echo (int)
                                        $principal["id"];
                                ?>"
                                class="btn-edit"
                            >

                                Edit Principal

                            </a>


                        </div>


                    </div>


                    <!-- =================================================
                         PRINCIPAL DETAILS
                    ================================================= -->

                    <div class="principal-details">


                        <div class="details-grid">


                            <!-- PRINCIPAL ID -->

                            <div class="detail-item">

                                <div class="detail-label">

                                    Principal ID

                                </div>


                                <div class="detail-value">

                                    <?php

                                    echo htmlspecialchars(
                                        $principal[
                                            "principal_id"
                                        ]
                                    );

                                    ?>

                                </div>

                            </div>


                            <!-- FULL NAME -->

                            <div class="detail-item">

                                <div class="detail-label">

                                    Full Name

                                </div>


                                <div class="detail-value">

                                    <?php

                                    echo htmlspecialchars(
                                        $principal[
                                            "full_name"
                                        ]
                                    );

                                    ?>

                                </div>

                            </div>


                            <!-- EMAIL -->

                            <div class="detail-item">

                                <div class="detail-label">

                                    Email

                                </div>


                                <div class="detail-value">

                                    <?php

                                    echo htmlspecialchars(
                                        $principal[
                                            "email"
                                        ]
                                    );

                                    ?>

                                </div>

                            </div>


                            <!-- PHONE -->

                            <div class="detail-item">

                                <div class="detail-label">

                                    Phone

                                </div>


                                <div class="detail-value">

                                    <?php

                                    if (
                                        !empty(
                                            $principal[
                                                "phone"
                                            ]
                                        )
                                    ) {

                                        echo htmlspecialchars(
                                            $principal[
                                                "phone"
                                            ]
                                        );

                                    } else {

                                        echo "Not provided";

                                    }

                                    ?>

                                </div>

                            </div>


                            <!-- DESIGNATION -->

                            <div class="detail-item">

                                <div class="detail-label">

                                    Designation

                                </div>


                                <div class="detail-value">

                                    <?php

                                    echo htmlspecialchars(
                                        $principal[
                                            "designation"
                                        ]
                                        ?: "Principal"
                                    );

                                    ?>

                                </div>

                            </div>


                            <!-- LOGIN EMAIL -->

                            <div class="detail-item">

                                <div class="detail-label">

                                    Login Email

                                </div>


                                <div class="detail-value">

                                    <?php

                                    echo htmlspecialchars(
                                        $principal[
                                            "username"
                                        ]
                                    );

                                    ?>

                                </div>

                            </div>


                        </div>


                        <!-- =================================================
                             LOGIN INFORMATION
                        ================================================= -->

                        <div class="login-info">

                            <strong>
                                Principal Login:
                            </strong>

                            The principal uses the registered
                            email address and password to log in.
                            OTP verification is required after
                            successful password verification.

                        </div>


                    </div>


                </div>


            <?php endif; ?>


        </div>


    </div>


</div>


</body>

</html>