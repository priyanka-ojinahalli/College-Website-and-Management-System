```php
<?php
session_start();

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../../auth/login.php");
    exit();
}

require_once "../../config/database.php";

if (!isset($_GET["id"]) || !ctype_digit($_GET["id"])) {
    header("Location: index.php");
    exit();
}

$id = (int) $_GET["id"];

$categories = [
    "Academic",
    "Cultural",
    "Sports",
    "Examination",
    "Holiday",
    "Other"
];

$stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: index.php");
    exit();
}

$event = $result->fetch_assoc();
$stmt->close();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $event_date = trim($_POST["event_date"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $category = trim($_POST["category"] ?? "");

    // Keep submitted values in the form if validation fails.
    $event["title"] = $title;
    $event["description"] = $description;
    $event["event_date"] = $event_date;
    $event["location"] = $location;
    $event["category"] = $category;

    $validDate = false;

    if ($event_date !== "") {
        $date = DateTimeImmutable::createFromFormat("!Y-m-d", $event_date);
        $validDate = $date && $date->format("Y-m-d") === $event_date;
    }

    if (
        $title === "" ||
        $description === "" ||
        $event_date === "" ||
        $location === "" ||
        $category === ""
    ) {
        $error = "Please fill in all required fields.";
    } elseif (!$validDate) {
        $error = "Please enter a valid event date.";
    } elseif (!in_array($category, $categories, true)) {
        $error = "Please select a valid event category.";
    } else {
        $stmt = $conn->prepare(
            "UPDATE events
             SET title = ?,
                 description = ?,
                 event_date = ?,
                 location = ?,
                 category = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "sssssi",
            $title,
            $description,
            $event_date,
            $location,
            $category,
            $id
        );

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: index.php?updated=1");
            exit();
        } else {
            $error = "Failed to update event. Please try again.";
            $stmt->close();
        }
    }
}

$currentCategory = $event["category"] ?? "Other";

if (!in_array($currentCategory, $categories, true)) {
    $currentCategory = "Other";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - Admin</title>
    <link rel="stylesheet" href="../../css/admin.css?v=4">
</head>

<body>
<div class="dashboard">

    <?php include "../../includes/admin_sidebar.php"; ?>

    <div class="main-content">

        <?php include "../../includes/admin_header.php"; ?>

        <div class="content">
            <h2>Edit Event</h2>
            <p class="welcome">Update the college event details.</p>

            <br>

            <?php if ($error !== ""): ?>
                <div class="alert error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <form method="POST">

                    <div class="form-group">
                        <label for="title">Event Title *</label>
                        <input
                            type="text"
                            id="title"
                            name="title"
                            maxlength="255"
                            value="<?php echo htmlspecialchars($event["title"] ?? ""); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea
                            id="description"
                            name="description"
                            rows="6"
                            required
                        ><?php echo htmlspecialchars($event["description"] ?? ""); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="event_date">Event Date *</label>
                        <input
                            type="date"
                            id="event_date"
                            name="event_date"
                            value="<?php echo htmlspecialchars($event["event_date"] ?? ""); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="location">Location *</label>
                        <input
                            type="text"
                            id="location"
                            name="location"
                            maxlength="255"
                            value="<?php echo htmlspecialchars($event["location"] ?? ""); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="category">Event Category *</label>
                        <select id="category" name="category" required>
                            <?php foreach ($categories as $option): ?>
                                <option
                                    value="<?php echo htmlspecialchars($option); ?>"
                                    <?php echo ($currentCategory === $option) ? "selected" : ""; ?>
                                >
                                    <?php echo htmlspecialchars($option); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">
                            Update Event
                        </button>

                        <a href="index.php" class="btn-secondary">
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
```
