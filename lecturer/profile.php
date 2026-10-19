<?php

session_start();

/* =====================================================
   LECTURER LOGIN CHECK
===================================================== */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "lecturer"
) {
    header("Location: ../auth/login.php");
    exit();
}


/* =====================================================
   DATABASE
===================================================== */

require_once "../config/database.php";


/* =====================================================
   GET LOGGED-IN LECTURER
===================================================== */

$user_id = (int) $_SESSION["user_id"];

$stmt = $conn->prepare("
    SELECT *
    FROM lecturers
    WHERE user_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    die("Lecturer profile not found.");
}

$lecturer = $result->fetch_assoc();

$stmt->close();


/* =====================================================
   HELPER FUNCTION
===================================================== */

function profileValue($value)
{
    if (
        $value === null ||
        trim((string)$value) === ""
    ) {
        return "Not provided";
    }

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =====================================================
   INITIALS
===================================================== */

$full_name = trim(
    $lecturer["full_name"] ?? "Lecturer"
);

$name_parts = preg_split(
    "/\s+/",
    $full_name
);

$initials = "";

if (!empty($name_parts[0])) {

    $initials .= strtoupper(
        substr(
            $name_parts[0],
            0,
            1
        )
    );
}

if (count($name_parts) > 1) {

    $last_name = end($name_parts);

    $initials .= strtoupper(
        substr(
            $last_name,
            0,
            1
        )
    );
}


/* =====================================================
   STATUS
===================================================== */

$status = trim(
    $lecturer["status"] ?? "Active"
);

$status_lower = strtolower($status);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Lecturer Profile</title>


    <style>

        /* =================================================
           RESET
        ================================================= */

        * {
            box-sizing: border-box;
        }


        html {
            scroll-behavior: smooth;
        }


        body {

            margin: 0;

            font-family:
                "Segoe UI",
                Arial,
                sans-serif;

            background: #f5f8fc;

            color: #172033;

            line-height: 1.5;
        }


        /* =================================================
           MAIN PAGE
        ================================================= */

        .profile-page {

            width: 100%;

            max-width: 1200px;

            margin: 0 auto;

            padding:
                32px 35px 60px;
        }


        /* =================================================
           BREADCRUMB
        ================================================= */

        .breadcrumb {

            display: flex;

            align-items: center;

            gap: 9px;

            margin-bottom: 22px;

            font-size: 14px;
        }


        .breadcrumb .current {

            color: #64748b;
        }


        .breadcrumb .main {

            color: #172554;

            font-weight: 700;
        }


        .breadcrumb .slash {

            color: #94a3b8;
        }


        /* =================================================
           PAGE INTRO
        ================================================= */

        .profile-intro {

            background: #dbeafe;

            border:
                1px solid #bfdbfe;

            border-radius: 18px;

            padding:
                28px 32px;

            margin-bottom: 25px;

            box-shadow:
                0 8px 20px
                rgba(30, 64, 175, 0.07);
        }


        .profile-intro h1 {

            margin: 0 0 5px;

            font-size: 27px;

            color: #172554;

            font-weight: 700;
        }


        .profile-intro p {

            margin: 0;

            font-size: 14px;

            color: #475569;
        }


        /* =================================================
           MAIN PROFILE CARD
        ================================================= */

        .profile-card {

            background: #ffffff;

            border:
                1px solid #e2e8f0;

            border-radius: 18px;

            overflow: hidden;

            box-shadow:
                0 8px 25px
                rgba(15, 23, 42, 0.06);
        }


        /* =================================================
           PROFILE IDENTITY
        ================================================= */

        .profile-identity {

            display: flex;

            align-items: center;

            gap: 20px;

            padding:
                27px 32px;

            background:
                linear-gradient(
                    135deg,
                    #eff6ff,
                    #dbeafe
                );

            border-bottom:
                1px solid #bfdbfe;
        }


        /* =================================================
           AVATAR
        ================================================= */

        .profile-avatar {

            width: 72px;

            height: 72px;

            min-width: 72px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #1e3a8a;

            color: #ffffff;

            font-size: 24px;

            font-weight: 700;

            box-shadow:
                0 5px 12px
                rgba(30, 58, 138, 0.18);
        }


        /* =================================================
           IDENTITY TEXT
        ================================================= */

        .identity-text h2 {

            margin: 0 0 3px;

            color: #172554;

            font-size: 23px;

            font-weight: 700;
        }


        .identity-text p {

            margin: 0;

            color: #475569;

            font-size: 14px;
        }


        /* =================================================
           INFORMATION SECTION
        ================================================= */

        .information-section {

            padding:
                27px 32px 30px;
        }


        .section-title {

            margin: 0 0 20px;

            color: #172554;

            font-size: 18px;

            font-weight: 700;

            padding-bottom: 12px;

            border-bottom:
                1px solid #e2e8f0;
        }


        /* =================================================
           INFORMATION GRID
        ================================================= */

        .information-grid {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            column-gap: 55px;

            row-gap: 0;
        }


        /* =================================================
           INFORMATION ITEM
        ================================================= */

        .info-item {

            min-height: 82px;

            padding:
                17px 0;

            border-bottom:
                1px solid #edf1f5;

            display: grid;

            grid-template-columns: 145px minmax(0, 1fr);

            align-items: center;

            column-gap: 15px;
        }


        .info-label {

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 0.45px;

            color: #64748b;

            font-weight: 700;
        }


        .info-value {

            font-size: 14px;

            color: #172033;

            font-weight: 600;

            overflow-wrap: anywhere;
        }


        /* =================================================
           ADDRESS
        ================================================= */

        .address-item {

            grid-column: 1 / -1;

            grid-template-columns:
                145px minmax(0, 1fr);
        }


        /* =================================================
           STATUS
        ================================================= */

        .status {

            display: inline-flex;

            align-items: center;

            width: fit-content;

            padding:
                5px 12px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: 700;

            text-transform: capitalize;
        }


        .status-active {

            background: #dcfce7;

            color: #166534;
        }


        .status-inactive {

            background: #fee2e2;

            color: #991b1b;
        }


        /* =================================================
           ACTION AREA
        ================================================= */

        .profile-actions {

            display: flex;

            align-items: center;

            justify-content: flex-end;

            padding:
                20px 32px;

            background: #f8fafc;

            border-top:
                1px solid #e2e8f0;
        }


        /* =================================================
           CHANGE PASSWORD BUTTON
        ================================================= */

        .password-btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            padding:
                10px 18px;

            border-radius: 8px;

            background: #172554;

            color: #ffffff;

            text-decoration: none;

            font-size: 13px;

            font-weight: 600;

            transition:
                background 0.2s ease,
                transform 0.2s ease;
        }


        .password-btn:hover {

            background: #1e3a8a;

            transform: translateY(-1px);
        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 900px) {

            .profile-page {

                padding:
                    25px 20px 50px;
            }


            .information-grid {

                grid-template-columns: 1fr;

                column-gap: 0;
            }


            .address-item {

                grid-column: auto;
            }

        }


        @media (max-width: 600px) {

            .profile-page {

                padding:
                    20px 14px 40px;
            }


            .profile-intro {

                padding:
                    24px 22px;
            }


            .profile-intro h1 {

                font-size: 23px;
            }


            .profile-identity {

                padding:
                    23px 22px;
            }


            .profile-avatar {

                width: 60px;

                height: 60px;

                min-width: 60px;

                font-size: 21px;
            }


            .identity-text h2 {

                font-size: 20px;
            }


            .information-section {

                padding:
                    22px;
            }


            .info-item {

                grid-template-columns: 1fr;

                gap: 5px;

                padding:
                    15px 0;

                min-height: auto;
            }


            .address-item {

                grid-template-columns: 1fr;
            }


            .profile-actions {

                padding:
                    18px 22px;

                justify-content: stretch;
            }


            .password-btn {

                width: 100%;
            }

        }

    </style>

</head>


<body>


<?php

/* =====================================================
   LECTURER HEADER
===================================================== */

include "../includes/lecturer_header.php";

?>


<main class="profile-page">


    <!-- =================================================
         BREADCRUMB
    ================================================= -->

    <div class="breadcrumb">

        <span class="main">
            Lecturer
        </span>

        <span class="slash">
            /
        </span>

        <span class="current">
            Profile
        </span>

    </div>


    <!-- =================================================
         PAGE INTRO
    ================================================= -->

    <section class="profile-intro">

        <h1>
            My Profile
        </h1>

        <p>
            View your personal and professional information.
        </p>

    </section>


    <!-- =================================================
         PROFILE CARD
    ================================================= -->

    <section class="profile-card">


        <!-- =================================================
             IDENTITY
        ================================================= -->

        <div class="profile-identity">


            <div class="profile-avatar">

                <?php

                echo htmlspecialchars(
                    $initials,
                    ENT_QUOTES,
                    "UTF-8"
                );

                ?>

            </div>


            <div class="identity-text">

                <h2>

                    <?php

                    echo profileValue(
                        $lecturer["full_name"] ?? ""
                    );

                    ?>

                </h2>


                <p>

                    <?php

                    echo profileValue(
                        $lecturer["designation"]
                        ?? "Lecturer"
                    );

                    ?>

                </p>

            </div>


        </div>


        <!-- =================================================
             INFORMATION
        ================================================= -->

        <div class="information-section">


            <h3 class="section-title">
                Personal & Professional Information
            </h3>


            <div class="information-grid">


                <!-- STAFF ID -->

                <div class="info-item">

                    <div class="info-label">
                        Staff ID
                    </div>

                    <div class="info-value">

                        <?php

                        echo profileValue(
                            $lecturer["staff_id"] ?? ""
                        );

                        ?>

                    </div>

                </div>


                <!-- FULL NAME -->

                <div class="info-item">

                    <div class="info-label">
                        Full Name
                    </div>

                    <div class="info-value">

                        <?php

                        echo profileValue(
                            $lecturer["full_name"] ?? ""
                        );

                        ?>

                    </div>

                </div>


                <!-- EMAIL -->

                <div class="info-item">

                    <div class="info-label">
                        Email
                    </div>

                    <div class="info-value">

                        <?php

                        echo profileValue(
                            $lecturer["email"] ?? ""
                        );

                        ?>

                    </div>

                </div>


                <!-- PHONE -->

                <div class="info-item">

                    <div class="info-label">
                        Phone
                    </div>

                    <div class="info-value">

                        <?php

                        echo profileValue(
                            $lecturer["phone"] ?? ""
                        );

                        ?>

                    </div>

                </div>


                <!-- GENDER -->

                <div class="info-item">

                    <div class="info-label">
                        Gender
                    </div>

                    <div class="info-value">

                        <?php

                        echo profileValue(
                            $lecturer["gender"] ?? ""
                        );

                        ?>

                    </div>

                </div>


                <!-- DATE OF BIRTH -->

                <div class="info-item">

                    <div class="info-label">
                        Date of Birth
                    </div>

                    <div class="info-value">

                        <?php

                        if (
                            !empty(
                                $lecturer["date_of_birth"]
                                ?? ""
                            )
                        ) {

                            echo htmlspecialchars(
                                date(
                                    "d M Y",
                                    strtotime(
                                        $lecturer[
                                            "date_of_birth"
                                        ]
                                    )
                                ),
                                ENT_QUOTES,
                                "UTF-8"
                            );

                        } else {

                            echo "Not provided";

                        }

                        ?>

                    </div>

                </div>


                <!-- DEPARTMENT -->

                <div class="info-item">

                    <div class="info-label">
                        Department
                    </div>

                    <div class="info-value">

                        <?php

                        echo profileValue(
                            $lecturer["department"] ?? ""
                        );

                        ?>

                    </div>

                </div>


                <!-- DESIGNATION -->

                <div class="info-item">

                    <div class="info-label">
                        Designation
                    </div>

                    <div class="info-value">

                        <?php

                        echo profileValue(
                            $lecturer["designation"] ?? ""
                        );

                        ?>

                    </div>

                </div>


                <!-- QUALIFICATION -->

                <div class="info-item">

                    <div class="info-label">
                        Qualification
                    </div>

                    <div class="info-value">

                        <?php

                        echo profileValue(
                            $lecturer["qualification"] ?? ""
                        );

                        ?>

                    </div>

                </div>


                <!-- JOINING DATE -->

                <div class="info-item">

                    <div class="info-label">
                        Joining Date
                    </div>

                    <div class="info-value">

                        <?php

                        if (
                            !empty(
                                $lecturer["joining_date"]
                                ?? ""
                            )
                        ) {

                            echo htmlspecialchars(
                                date(
                                    "d M Y",
                                    strtotime(
                                        $lecturer[
                                            "joining_date"
                                        ]
                                    )
                                ),
                                ENT_QUOTES,
                                "UTF-8"
                            );

                        } else {

                            echo "Not provided";

                        }

                        ?>

                    </div>

                </div>


                <!-- ADDRESS -->

                <div class="info-item address-item">

                    <div class="info-label">
                        Address
                    </div>

                    <div class="info-value">

                        <?php

                        if (
                            !empty(
                                $lecturer["address"]
                                ?? ""
                            )
                        ) {

                            echo nl2br(
                                htmlspecialchars(
                                    $lecturer["address"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                )
                            );

                        } else {

                            echo "Not provided";

                        }

                        ?>

                    </div>

                </div>


                <!-- STATUS -->

                <div class="info-item">

                    <div class="info-label">
                        Status
                    </div>

                    <div class="info-value">


                        <?php

                        if (
                            $status_lower === "active"
                        ) {

                            ?>

                            <span
                                class="status status-active"
                            >
                                Active
                            </span>

                            <?php

                        } else {

                            ?>

                            <span
                                class="status status-inactive"
                            >

                                <?php

                                echo htmlspecialchars(
                                    $status,
                                    ENT_QUOTES,
                                    "UTF-8"
                                );

                                ?>

                            </span>

                            <?php

                        }

                        ?>


                    </div>

                </div>


            </div>

        </div>


    </section>


</main>


</body>

</html>