<?php

/* =====================================================
   LECTURER HEADER
===================================================== */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/* =====================================================
   LECTURER NAME
===================================================== */

$header_lecturer_name = "Lecturer";

if (isset($_SESSION["user_id"])) {

    $header_user_id = (int) $_SESSION["user_id"];

    $header_stmt = $conn->prepare("
        SELECT full_name
        FROM lecturers
        WHERE user_id = ?
        LIMIT 1
    ");

    if ($header_stmt) {

        $header_stmt->bind_param(
            "i",
            $header_user_id
        );

        $header_stmt->execute();

        $header_result =
            $header_stmt->get_result();

        if (
            $header_result->num_rows === 1
        ) {

            $header_row =
                $header_result->fetch_assoc();

            if (
                !empty(
                    $header_row["full_name"]
                )
            ) {

                $header_lecturer_name =
                    $header_row["full_name"];
            }
        }

        $header_stmt->close();
    }
}

?>


<style>

/* =====================================================
   LECTURER HEADER
===================================================== */

.lecturer-header {

    width: 100%;

    min-height: 88px;

    background: #172554;

    color: #ffffff;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 30px;

    box-shadow:
        0 4px 14px
        rgba(15, 23, 42, 0.16);

    position: relative;

    z-index: 1000;
}


/* =====================================================
   COLLEGE INFORMATION
===================================================== */

.lecturer-college-info {

    display: flex;

    flex-direction: column;

    justify-content: center;

    min-width: 0;
}


.lecturer-college-name {

    margin: 0;

    color: #ffffff;

    font-size: 27px;

    line-height: 1.2;

    font-weight: 700;

    letter-spacing: -0.3px;
}


.lecturer-college-subtitle {

    margin-top: 5px;

    color: #dbeafe;

    font-size: 14px;

    line-height: 1.3;
}


/* =====================================================
   RIGHT SIDE
===================================================== */

.lecturer-header-right {

    display: flex;

    align-items: center;

    gap: 25px;

    flex-shrink: 0;
}


/* =====================================================
   USER AREA
===================================================== */

.lecturer-user {

    display: flex;

    align-items: center;

    gap: 10px;
}


/* =====================================================
   USER ICON
===================================================== */

.lecturer-user-icon {

    width: 40px;

    height: 40px;

    border-radius: 50%;

    background: rgba(255, 255, 255, 0.12);

    display: flex;

    align-items: center;

    justify-content: center;

    color: #ffffff;

    font-size: 19px;

    border:
        1px solid
        rgba(255, 255, 255, 0.12);
}


/* =====================================================
   USER NAME
===================================================== */

.lecturer-user-name {

    color: #ffffff;

    font-size: 14px;

    font-weight: 600;

    white-space: nowrap;
}


/* =====================================================
   HEADER LINKS
===================================================== */

.lecturer-header-link {

    color: #ffffff;

    text-decoration: none;

    font-size: 14px;

    font-weight: 600;

    padding: 8px 2px;

    transition:
        color 0.2s ease,
        opacity 0.2s ease;
}


.lecturer-header-link:hover {

    color: #bfdbfe;

    opacity: 0.95;
}


/* =====================================================
   LOGOUT
===================================================== */

.lecturer-logout {

    color: #ffffff;

    text-decoration: none;

    font-size: 14px;

    font-weight: 600;

    padding: 8px 2px;

    cursor: pointer;

    transition:
        color 0.2s ease;
}


.lecturer-logout:hover {

    color: #bfdbfe;
}


/* =====================================================
   MOBILE
===================================================== */

@media (max-width: 800px) {

    .lecturer-header {

        padding: 15px 20px;

        gap: 15px;

        flex-wrap: wrap;
    }


    .lecturer-college-name {

        font-size: 21px;
    }


    .lecturer-college-subtitle {

        font-size: 12px;
    }


    .lecturer-header-right {

        width: 100%;

        justify-content: flex-end;

        gap: 18px;
    }

}


@media (max-width: 520px) {

    .lecturer-header {

        padding: 15px;
    }


    .lecturer-user-name {

        display: none;
    }


    .lecturer-header-right {

        gap: 15px;
    }

}

</style>


<header class="lecturer-header">


    <!-- =================================================
         COLLEGE INFORMATION
    ================================================= -->

    <div class="lecturer-college-info">

        <h1 class="lecturer-college-name">

            Smt. Sharadamma V. Kotbal

        </h1>


        <div class="lecturer-college-subtitle">

            BBA, BCA &amp; B.Com College,
            Koppal, Karnataka

        </div>

    </div>


    <!-- =================================================
         RIGHT SIDE
    ================================================= -->

    <div class="lecturer-header-right">


        <!-- USER -->

        <div class="lecturer-user">

            <div class="lecturer-user-icon">

                👤

            </div>


            <span class="lecturer-user-name">

                <?php

                echo htmlspecialchars(
                    $header_lecturer_name,
                    ENT_QUOTES,
                    "UTF-8"
                );

                ?>

            </span>

        </div>


        <!-- PROFILE -->

        <a
            href="profile.php"
            class="lecturer-header-link"
        >
            Profile
        </a>


        <!-- LOGOUT -->

        <a
            href="../auth/logout.php"
            class="lecturer-logout"
            onclick="
                return confirm(
                    'Are you sure you want to logout?'
                );
            "
        >
            Logout
        </a>


    </div>

</header>