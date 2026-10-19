<?php

/* =====================================================
   SESSION CHECK
===================================================== */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "student"
) {
    header("Location: ../auth/login.php");
    exit();
}


/* =====================================================
   DATABASE CONNECTION
===================================================== */

require_once __DIR__ . "/../config/database.php";


/* =====================================================
   GET LOGGED-IN STUDENT
===================================================== */

$user_id = (int) $_SESSION["user_id"];


$stmt = $conn->prepare("
    SELECT
        full_name,
        registration_no
    FROM students
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

    die("Student account not found.");

}


$student_header = $result->fetch_assoc();

$stmt->close();

?>


<!-- =====================================================
     STUDENT HEADER
===================================================== -->

<header class="student-header">


    <!-- COLLEGE INFORMATION -->

    <div class="college-header">

        <h1>
            Smt. Sharadamma V. Kotbal
        </h1>

        <p>
            BBA, BCA &amp; B.Com College,
            Koppal, Karnataka
        </p>

    </div>


    <!-- STUDENT USER AREA -->

    <div class="student-user">


        <span class="student-icon">
            👤
        </span>


        <strong>

            <?php

            echo htmlspecialchars(
                $student_header["full_name"]
            );

            ?>

        </strong>

         <a href="/college_website/student/learning/index.php">
                Assignments & Study Materials
        </a>

        <a href="profile.php">
            Profile
        </a>


        <a
            href="logout.php"
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


<!-- =====================================================
     STUDENT HEADER CSS
===================================================== -->

<style>

/* =====================================================
   HEADER
===================================================== */

.student-header {

    width: 100% !important;

    min-height: 78px !important;

    background-color: #172554 !important;

    display: flex !important;

    align-items: center !important;

    justify-content: space-between !important;

    padding: 0 30px !important;

    margin: 0 !important;

    box-sizing: border-box !important;

    color: #ffffff !important;

    box-shadow:
        0 3px 12px
        rgba(0, 0, 0, 0.18) !important;

}


/* =====================================================
   COLLEGE INFORMATION
===================================================== */

.student-header .college-header {

    display: flex !important;

    flex-direction: column !important;

    justify-content: center !important;

    margin: 0 !important;

    padding: 0 !important;

}


.student-header .college-header h1 {

    margin: 0 !important;

    padding: 0 !important;

    font-size: 22px !important;

    line-height: 1.25 !important;

    font-weight: 700 !important;

    color: #ffffff !important;

}


.student-header .college-header p {

    margin: 3px 0 0 !important;

    padding: 0 !important;

    font-size: 13px !important;

    line-height: 1.3 !important;

    color: #dbeafe !important;

}


/* =====================================================
   STUDENT USER AREA
===================================================== */

.student-header .student-user {

    display: flex !important;

    align-items: center !important;

    gap: 12px !important;

    white-space: nowrap !important;

    font-size: 13px !important;

    margin: 0 !important;

    padding: 0 !important;

}


.student-header .student-user strong {

    color: #ffffff !important;

    font-weight: 600 !important;

}


.student-header .student-user a {

    color: #ffffff !important;

    text-decoration: none !important;

    font-weight: 600 !important;

    padding: 7px 10px !important;

    border-radius: 5px !important;

    transition:
        background-color 0.2s ease,
        color 0.2s ease !important;

}


.student-header .student-user a:hover {

    color: #ffffff !important;

    background-color:
        rgba(255, 255, 255, 0.15) !important;

}


/* =====================================================
   USER ICON
===================================================== */

.student-header .student-icon {

    width: 32px !important;

    height: 32px !important;

    display: flex !important;

    align-items: center !important;

    justify-content: center !important;

    border-radius: 50% !important;

    background-color:
        rgba(255, 255, 255, 0.12) !important;

    color: #ffffff !important;

    font-size: 15px !important;

}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 800px) {

    .student-header {

        min-height: 70px !important;

        padding: 10px 15px !important;

        gap: 15px !important;

    }


    .student-header .college-header h1 {

        font-size: 17px !important;

    }


    .student-header .college-header p {

        font-size: 11px !important;

    }


    .student-header .student-user {

        gap: 5px !important;

        font-size: 12px !important;

    }


    .student-header .student-icon {

        width: 28px !important;

        height: 28px !important;

    }

}


@media (max-width: 600px) {

    .student-header {

        flex-direction: column !important;

        align-items: flex-start !important;

        padding: 14px 15px !important;

    }


    .student-header .student-user {

        width: 100% !important;

        justify-content: flex-end !important;

    }

}

</style>