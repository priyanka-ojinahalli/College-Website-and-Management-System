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
   GET LECTURER ID
===================================================== */

$id = (int)($_GET["id"] ?? 0);

if ($id <= 0) {
    header("Location: index.php");
    exit();
}


/* =====================================================
   GET LECTURER + USER ID
===================================================== */

$stmt = $conn->prepare("
    SELECT
        id,
        user_id,
        full_name,
        lecturer_id
    FROM lecturers
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {

    $stmt->close();

    header("Location: index.php?error=notfound");
    exit();
}

$lecturer = $result->fetch_assoc();

$stmt->close();


/* =====================================================
   DELETE
===================================================== */

$conn->begin_transaction();

try {

    /* -------------------------------------------------
       Delete lecturer record
    ------------------------------------------------- */

    $delete_lecturer = $conn->prepare("
        DELETE FROM lecturers
        WHERE id = ?
    ");

    if (!$delete_lecturer) {
        throw new Exception(
            "Unable to prepare lecturer deletion."
        );
    }

    $delete_lecturer->bind_param(
        "i",
        $id
    );

    if (!$delete_lecturer->execute()) {

        throw new Exception(
            "Unable to delete lecturer: " .
            $delete_lecturer->error
        );
    }

    $delete_lecturer->close();


    /* -------------------------------------------------
       Delete corresponding login account
    ------------------------------------------------- */

    if (!empty($lecturer["user_id"])) {

        $delete_user = $conn->prepare("
            DELETE FROM users
            WHERE id = ?
            AND role = 'lecturer'
        ");

        if (!$delete_user) {
            throw new Exception(
                "Unable to prepare user deletion."
            );
        }

        $delete_user->bind_param(
            "i",
            $lecturer["user_id"]
        );

        if (!$delete_user->execute()) {

            throw new Exception(
                "Unable to delete lecturer login account: " .
                $delete_user->error
            );
        }

        $delete_user->close();
    }


    /* -------------------------------------------------
       Everything successful
    ------------------------------------------------- */

    $conn->commit();

    header(
        "Location: index.php?deleted=1"
    );

    exit();

} catch (Exception $e) {

    $conn->rollback();

    die(
        "Unable to delete lecturer: " .
        htmlspecialchars($e->getMessage())
    );
}