
<?php

require_once "config/database.php";

// =========================
// SELECT CALENDAR MONTH
// =========================

$requested_month = (
    isset($_GET["month"]) &&
    is_string($_GET["month"])
)
    ? $_GET["month"]
    : date("Y-m");

$calendar_month = DateTimeImmutable::createFromFormat(
    "!Y-m-d",
    $requested_month . "-01"
);

// Validate the requested month.
if (
    !$calendar_month ||
    $calendar_month->format("Y-m") !== $requested_month
) {
    $calendar_month = new DateTimeImmutable(
        date("Y-m-01")
    );
}

$start_date = $calendar_month->format("Y-m-01");

$end_date = $calendar_month
    ->modify("first day of next month")
    ->format("Y-m-d");

$month_title = $calendar_month->format("F Y");

$previous_month = $calendar_month
    ->modify("-1 month")
    ->format("Y-m");

$next_month = $calendar_month
    ->modify("+1 month")
    ->format("Y-m");

$days_in_month = (int) $calendar_month->format("t");

// Monday = 0, Tuesday = 1, ..., Sunday = 6.
$starting_day = (int) $calendar_month->format("N") - 1;

$total_cells = (int) (
    ceil(($starting_day + $days_in_month) / 7) * 7
);

$today = date("Y-m-d");


// =========================
// GET EVENTS FOR CALENDAR
// =========================

$calendar_events = [];

$stmt = $conn->prepare(
    "SELECT id, title, event_date, category
    FROM events
    WHERE event_date >= ?
      AND event_date < ?
    ORDER BY event_date ASC, id ASC"
);

if ($stmt) {
    $stmt->bind_param(
        "ss",
        $start_date,
        $end_date
    );

    if ($stmt->execute()) {
        $calendar_result = $stmt->get_result();

        while ($event = $calendar_result->fetch_assoc()) {
            $event_date = substr(
                (string) $event["event_date"],
                0,
                10
            );

            $event_timestamp = strtotime($event_date);

            if ($event_timestamp === false) {
                continue;
            }

            $day = (int) date("j", $event_timestamp);

            $calendar_events[$day][] = $event;
        }
    }

    $stmt->close();
}


// =========================
// GET ALL EVENTS
// =========================

$all_events = $conn->query(
    "SELECT *
     FROM events
     ORDER BY event_date DESC, id DESC"
);


// =========================
// GET UPCOMING EVENTS
// =========================

$upcoming_events = $conn->query(
    "SELECT *
     FROM events
     WHERE event_date >= CURDATE()
     ORDER BY event_date ASC, id ASC"
);

?>

<?php include "includes/public_header.php"; ?>


<style>
/* =========================
   COLLEGE EVENTS CALENDAR
========================= */

.events-calendar-section {
    padding-top: 35px;
}

.events-calendar-wrapper {
    max-width: 1150px;
    margin: 0 auto;
    padding: 24px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 5px 20px rgba(15, 23, 42, 0.06);
}

.events-calendar-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}

.events-calendar-navigation h3 {
    margin: 0;
    color: #172554;
    font-size: 25px;
}

.events-calendar-nav-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.events-calendar-nav-buttons a {
    display: inline-block;
    padding: 9px 13px;
    color: #172554;
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
}

.events-calendar-nav-buttons a:hover {
    color: #ffffff;
    background: #172554;
    border-color: #172554;
}

.events-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    border-top: 1px solid #e2e8f0;
    border-left: 1px solid #e2e8f0;
}

.events-calendar-weekday {
    padding: 12px 4px;
    text-align: center;
    color: #ffffff;
    background: #172554;
    font-size: 13px;
    font-weight: 700;
}

.events-calendar-day {
    min-width: 0;
    min-height: 115px;
    padding: 9px;
    background: #ffffff;
    border-right: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
}

.events-calendar-day.is-outside {
    background: #f8fafc;
}

.events-calendar-day.is-today {
    background: #eff6ff;
    box-shadow: inset 0 0 0 2px #2563eb;
}

.events-calendar-date {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 8px;
    color: #0f172a;
    font-weight: 700;
    font-size: 14px;
}

.events-calendar-today-label {
    display: inline-block;
    padding: 2px 5px;
    color: #ffffff;
    background: #2563eb;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 600;
}

.events-calendar-items {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.events-calendar-event-link {
    display: block;
    padding: 6px 7px;
    color: #1d4ed8;
    background: #dbeafe;
    border-left: 3px solid #2563eb;
    border-radius: 4px;
    text-decoration: none;
    overflow-wrap: anywhere;
    font-size: 12px;
    line-height: 1.4;
}

.events-calendar-event-link:hover {
    color: #ffffff;
    background: #1d4ed8;
}
/* Event category colours */

.events-calendar-event-link.category-academic {
    color: #1d4ed8;
    background: #dbeafe;
    border-left-color: #2563eb;
}

.events-calendar-event-link.category-cultural {
    color: #7e22ce;
    background: #f3e8ff;
    border-left-color: #9333ea;
}

.events-calendar-event-link.category-sports {
    color: #15803d;
    background: #dcfce7;
    border-left-color: #16a34a;
}

.events-calendar-event-link.category-examination {
    color: #92400e;
    background: #fef3c7;
    border-left-color: #d97706;
}

.events-calendar-event-link.category-holiday {
    color: #be123c;
    background: #ffe4e6;
    border-left-color: #e11d48;
}

.events-calendar-event-link.category-other {
    color: #334155;
    background: #e2e8f0;
    border-left-color: #64748b;
}

.events-calendar-empty {
    color: #cbd5e1;
    font-size: 12px;
}

.events-calendar-help {
    margin: 18px 0 0;
    color: #64748b;
    font-size: 13px;
    text-align: center;
}

.events-calendar-no-events {
    margin-top: 15px;
    padding: 12px;
    color: #475569;
    background: #f8fafc;
    border-radius: 8px;
    text-align: center;
}

/* Category labels on event cards and upcoming events */
.event-category-badge {
    display: inline-block;
    margin: 8px 0 10px;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.3;
}

.event-category-badge.category-academic {
    color: #1d4ed8;
    background: #dbeafe;
}

.event-category-badge.category-cultural {
    color: #7e22ce;
    background: #f3e8ff;
}

.event-category-badge.category-sports {
    color: #15803d;
    background: #dcfce7;
}

.event-category-badge.category-examination {
    color: #92400e;
    background: #fef3c7;
}

.event-category-badge.category-holiday {
    color: #be123c;
    background: #ffe4e6;
}

.event-category-badge.category-other {
    color: #334155;
    background: #e2e8f0;
}

.events-calendar-event-card {
    scroll-margin-top: 100px;
}

/* Highlight an event when its link is clicked. */
.events-calendar-event-card:target {
    outline: 3px solid #2563eb;
    background: #eff6ff;
}

/* =========================
   MOBILE RESPONSIVENESS
========================= */

@media (max-width: 700px) {
    .events-calendar-wrapper {
        padding: 12px;
    }

    .events-calendar-navigation {
        align-items: flex-start;
    }

    .events-calendar-navigation h3 {
        font-size: 21px;
        width: 100%;
    }

    .events-calendar-nav-buttons a {
        padding: 8px 10px;
        font-size: 12px;
    }

    .events-calendar-weekday {
        padding: 9px 1px;
        font-size: 10px;
    }

    .events-calendar-day {
        min-height: 80px;
        padding: 4px;
    }

    .events-calendar-date {
        display: block;
        margin-bottom: 5px;
        font-size: 12px;
    }

    .events-calendar-today-label {
        display: block;
        width: fit-content;
        margin-top: 3px;
    }

    .events-calendar-event-link {
        padding: 4px;
        font-size: 10px;
        border-left-width: 2px;
    }

    .events-calendar-empty {
        font-size: 10px;
    }
}

@media (max-width: 400px) {
    .events-calendar-day {
        min-height: 65px;
        padding: 3px;
    }

    .events-calendar-event-link {
        font-size: 9px;
        padding: 3px;
    }
}
</style>


<!-- =========================
     MONTHLY COLLEGE CALENDAR
========================= -->

<section class="section events-calendar-section"
         id="college-calendar">

    <div class="section-title">
        <h2>College Calendar of Events</h2>

        <p>
            Explore upcoming programmes, activities
            and important events at our college.
        </p>
    </div>


    <div class="events-calendar-wrapper">

        <!-- Month navigation -->
        <div class="events-calendar-navigation">

            <h3>
                📅 <?php echo htmlspecialchars($month_title); ?>
            </h3>

            <div class="events-calendar-nav-buttons">

                <a href="?month=<?php echo htmlspecialchars($previous_month); ?>#college-calendar"
                   aria-label="View previous month">
                    &#8592; Previous
                </a>

                <a href="events.php#college-calendar">
                    Current Month
                </a>

                <a href="?month=<?php echo htmlspecialchars($next_month); ?>#college-calendar"
                   aria-label="View next month">
                    Next &#8594;
                </a>

            </div>
        </div>


        <!-- Calendar grid -->
        <div class="events-calendar-grid">

            <?php
            $weekdays = [
                "Monday",
                "Tuesday",
                "Wednesday",
                "Thursday",
                "Friday",
                "Saturday",
                "Sunday"
            ];

            foreach ($weekdays as $weekday):
            ?>

                <div class="events-calendar-weekday">
                    <?php echo htmlspecialchars($weekday); ?>
                </div>

            <?php endforeach; ?>


            <?php for (
                $cell = 0;
                $cell < $total_cells;
                $cell++
            ): ?>

                <?php
                $day = $cell - $starting_day + 1;
                ?>

                <?php if (
                    $day < 1 ||
                    $day > $days_in_month
                ): ?>

                    <div class="events-calendar-day is-outside"></div>

                <?php else: ?>

                    <?php
                    $date_key = $calendar_month->format("Y-m-")
                        . str_pad(
                            (string) $day,
                            2,
                            "0",
                            STR_PAD_LEFT
                        );

                    $is_today = ($date_key === $today);
                    ?>

                    <div class="events-calendar-day <?php
                        echo $is_today ? "is-today" : "";
                    ?>">

                        <div class="events-calendar-date">

                            <span>
                                <?php echo $day; ?>
                            </span>

                            <?php if ($is_today): ?>
                                <span class="events-calendar-today-label">
                                    Today
                                </span>
                            <?php endif; ?>

                        </div>


                        <div class="events-calendar-items">

                            <?php if (
                                !empty($calendar_events[$day])
                            ): ?>

                                <?php foreach (
                                    $calendar_events[$day] as $calendar_event
                                ): ?>
                                    <?php
$event_category = $calendar_event["category"] ?? "Other";

$category_classes = [
    "Academic" => "category-academic",
    "Cultural" => "category-cultural",
    "Sports" => "category-sports",
    "Examination" => "category-examination",
    "Holiday" => "category-holiday",
    "Other" => "category-other"
];

$category_class =
    $category_classes[$event_category] ?? "category-other";
?>
                                    <a
                                        class="events-calendar-event-link <?php echo htmlspecialchars($category_class); ?>"
                                        href="#event-<?php echo (int) $calendar_event["id"]; ?>"
                                        title="<?php echo htmlspecialchars($calendar_event["title"]); ?>"
                                    >
                                        <?php
                                        echo htmlspecialchars(
                                            $calendar_event["title"]
                                        );
                                        ?>
                                    </a>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <span class="events-calendar-empty">
                                    &mdash;
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endif; ?>

            <?php endfor; ?>

        </div>


        <p class="events-calendar-help">
            💡 Click an event title to view its complete details below.
        </p>


        <?php if (empty($calendar_events)): ?>

            <div class="events-calendar-no-events">
                No events are scheduled for
                <?php echo htmlspecialchars($month_title); ?>.
            </div>

        <?php endif; ?>

    </div>

</section>

<!-- =========================
     UPCOMING EVENTS
========================= -->

<section class="section notice-section">

    <div class="section-title">

        <h2>Upcoming Events</h2>

        <p>
            Stay updated with upcoming college activities
        </p>

    </div>


    <div class="notice-list">

        <?php if (
            $upcoming_events &&
            $upcoming_events->num_rows > 0
        ): ?>

            <?php while (
                $event = $upcoming_events->fetch_assoc()
            ): ?>

                <?php
                $event_category = $event["category"] ?? "Other";
                $category_classes = [
                    "Academic" => "category-academic",
                    "Cultural" => "category-cultural",
                    "Sports" => "category-sports",
                    "Examination" => "category-examination",
                    "Holiday" => "category-holiday",
                    "Other" => "category-other"
                ];
                $category_class = $category_classes[$event_category] ?? "category-other";
                ?>

                <div class="notice">

                    <strong>
                        <?php
                        echo htmlspecialchars($event["title"]);
                        ?>
                    </strong>

                    <div>
                        <span class="event-category-badge <?php echo htmlspecialchars($category_class); ?>">
                            <?php echo htmlspecialchars($event_category); ?>
                        </span>
                    </div>

                    <p>
                        <?php
                        echo nl2br(
                            htmlspecialchars($event["description"])
                        );
                        ?>
                    </p>

                    <p class="notice-date">

                        <strong>Date:</strong>

                        <?php
                        echo date(
                            "d M Y",
                            strtotime($event["event_date"])
                        );
                        ?>

                        &nbsp; | &nbsp;

                        <strong>Location:</strong>

                        <?php
                        echo htmlspecialchars($event["location"]);
                        ?>

                    </p>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="notice">

                <strong>No upcoming events</strong>

                <p class="notice-date">
                    New events will be announced here.
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>


<!-- =========================
     ALL COLLEGE EVENTS
========================= -->

<section class="section">

    <div class="section-title">

        <h2>All College Events</h2>

        <p>
            Activities, programmes and events at our college
        </p>

    </div>


    <div class="course-grid">

        <?php if (
            $all_events &&
            $all_events->num_rows > 0
        ): ?>

            <?php while (
                $event = $all_events->fetch_assoc()
            ): ?>

                <?php
                $event_category = $event["category"] ?? "Other";
                $category_classes = [
                    "Academic" => "category-academic",
                    "Cultural" => "category-cultural",
                    "Sports" => "category-sports",
                    "Examination" => "category-examination",
                    "Holiday" => "category-holiday",
                    "Other" => "category-other"
                ];
                $category_class = $category_classes[$event_category] ?? "category-other";
                ?>

                <div
                    class="course-card events-calendar-event-card"
                    id="event-<?php echo (int) $event["id"]; ?>"
                >

                    <div class="course-icon">
                        📅
                    </div>

                    <h3>
                        <?php
                        echo htmlspecialchars($event["title"]);
                        ?>
                    </h3>

                    <span class="event-category-badge <?php echo htmlspecialchars($category_class); ?>">
                        <?php echo htmlspecialchars($event_category); ?>
                    </span>

                    <p>
                        <?php
                        echo nl2br(
                            htmlspecialchars($event["description"])
                        );
                        ?>
                    </p>

                    <p class="notice-date">

                        <strong>Date:</strong>

                        <?php
                        echo date(
                            "d M Y",
                            strtotime($event["event_date"])
                        );
                        ?>

                    </p>

                    <p class="notice-date">

                        <strong>Location:</strong>

                        <?php
                        echo htmlspecialchars($event["location"]);
                        ?>

                    </p>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="course-card">

                <div class="course-icon">
                    📅
                </div>

                <h3>No Events Available</h3>

                <p>
                    College events will be announced here.
                </p>

            </div>

        <?php endif; ?>

    </div>

</section>



<?php include "includes/public_footer.php"; ?>