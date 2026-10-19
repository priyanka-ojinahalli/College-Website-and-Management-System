
<?php

session_start();

// Admin-only access
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../../auth/login.php");
    exit();
}

require_once "../../config/database.php";

$error = "";

$title = "";
$description = "";
$event_date = "";
$location = "";
$category = "Other";

// Available event categories
$categories = [
    "Academic",
    "Cultural",
    "Sports",
    "Examination",
    "Holiday",
    "Other"
];

// =========================
// HANDLE FORM SUBMISSION
// =========================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $event_date = trim($_POST["event_date"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $category = trim($_POST["category"] ?? "");

    // Validate required fields
    if (
        $title === "" ||
        $description === "" ||
        $event_date === "" ||
        $location === "" ||
        $category === ""
    ) {
        $error = "Please fill in all required fields.";
    } elseif (!in_array($category, $categories, true)) {
        $error = "Please select a valid event category.";
    } else {

        // Validate date format
        $date_object = DateTimeImmutable::createFromFormat(
            "!Y-m-d",
            $event_date
        );

        if (
            !$date_object ||
            $date_object->format("Y-m-d") !== $event_date
        ) {
            $error = "Please select a valid event date.";
        } else {

            // Insert event with its category
            $stmt = $conn->prepare(
                "INSERT INTO events
                (title, description, event_date, location, category)
                VALUES (?, ?, ?, ?, ?)"
            );

            if ($stmt) {

                $stmt->bind_param(
                    "sssss",
                    $title,
                    $description,
                    $event_date,
                    $location,
                    $category
                );

                if ($stmt->execute()) {

                    $stmt->close();

                    header("Location: index.php?success=1");
                    exit();

                } else {
                    $error = "Failed to add event. Please try again.";
                    $stmt->close();
                }

            } else {
                $error = "Unable to prepare the event form.";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Add Event - Admin</title>

    <link rel="stylesheet"
          href="../../css/admin.css?v=3">

</head>

<body>

<div class="dashboard">

    <!-- SIDEBAR -->
    <?php include "../../includes/admin_sidebar.php"; ?>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- HEADER -->
        <?php include "../../includes/admin_header.php"; ?>

        <!-- CONTENT -->
        <div class="content">

            <h2>Add Event</h2>

            <p class="welcome">
                Create a new college event.
            </p>

            <br>

            <?php if ($error !== ""): ?>

                <div class="alert error">
                    <?php echo htmlspecialchars($error); ?>
                </div>

            <?php endif; ?>

            <div class="form-card">

                <form method="POST">

                    <!-- EVENT TITLE -->
                    <div class="form-group">

                        <label for="title">
                            Event Title *
                        </label>

                        <input
                            type="text"
                            id="title"
                            name="title"
                            maxlength="200"
                            placeholder="Enter event title"
                            value="<?php echo htmlspecialchars($title); ?>"
                            required
                        >

                    </div>

                    <!-- DESCRIPTION -->
                    <div class="form-group">

                        <label for="description">
                            Description *
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            rows="6"
                            placeholder="Enter event details"
                            required
                        ><?php echo htmlspecialchars($description); ?></textarea>

                    </div>

                    <!-- EVENT DATE -->
                    <div class="form-group">

                        <label for="event_date">
                            Event Date *
                        </label>

                        <input
                            type="date"
                            id="event_date"
                            name="event_date"
                            value="<?php echo htmlspecialchars($event_date); ?>"
                            required
                        >

                    </div>

                    <!-- EVENT LOCATION -->
                    <div class="form-group">

                        <label for="location">
                            Location *
                        </label>

                        <input
                            type="text"
                            id="location"
                            name="location"
                            maxlength="200"
                            placeholder="Example: College Auditorium"
                            value="<?php echo htmlspecialchars($location); ?>"
                            required
                        >

                    </div>

                    <!-- EVENT CATEGORY -->
                    <div class="form-group">

                        <label for="category">
                            Event Category *
                        </label>

                        <select
                            id="category"
                            name="category"
                            required
                        >

                            <option value="">
                                -- Select Category --
                            </option>

                            <?php foreach ($categories as $option): ?>

                                <option
                                    value="<?php echo htmlspecialchars($option); ?>"
                                    <?php echo $category === $option ? "selected" : ""; ?>
                                >
                                    <?php echo htmlspecialchars($option); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- BUTTONS -->
                    <div class="form-actions">

                        <button
                            type="submit"
                            class="btn-primary"
                        >
                            Add Event
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

</body>
</html>