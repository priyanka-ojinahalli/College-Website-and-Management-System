<?php

require_once "config/database.php";

/* =========================
   GET LATEST 3 ACTIVE NOTICES
========================= */

$latest_notices = $conn->query(
    "SELECT id, title
     FROM notices
     WHERE status = 'active'
     ORDER BY notice_date DESC, id DESC
     LIMIT 3"
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        Smt. Sharadamma V. Kotbal BBA, BCA & B.Com College
    </title>

    <link rel="stylesheet" href="css/style.css">

    <style>

        /* =========================
           MOVING NOTICE TICKER
        ========================= */

        .notice-ticker {
            display: flex;
            align-items: center;
            width: 100%;
            height: 50px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .ticker-label {
            background: #172554;
            color: white;
            font-weight: bold;
            padding: 15px 20px;
            min-width: 150px;
            text-align: center;
            z-index: 2;
        }

        .ticker-container {
            flex: 1;
            overflow: hidden;
            white-space: nowrap;
        }

        .ticker-content {
            display: inline-block;
            white-space: nowrap;
            animation: noticeScroll 20s linear infinite;
        }

        .ticker-link {
            color: #1d4ed8;
            text-decoration: underline;
            font-weight: 600;
            font-size: 16px;
        }

        .ticker-link:hover {
            color: #dc2626;
        }

        .ticker-separator {
            margin: 0 25px;
            color: #64748b;
            font-weight: bold;
        }

        @keyframes noticeScroll {

            0% {
                transform: translateX(100%);
            }

            100% {
                transform: translateX(-100%);
            }

        }

        /* Pause moving notices when mouse is over them */

        .ticker-content:hover {
            animation-play-state: paused;
        }


        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 600px) {

            .notice-ticker {
                height: 45px;
            }

            .ticker-label {
                min-width: 110px;
                padding: 13px 8px;
                font-size: 13px;
            }

            .ticker-link {
                font-size: 14px;
            }

            .ticker-separator {
                margin: 0 15px;
            }

        }

    </style>

</head>


<body>


<?php include "includes/public_header.php"; ?>


<!-- =========================
     HERO SECTION
========================= -->

<section class="hero">

    <div class="hero-content">

        <h2>
            Smt. Sharadamma V. Kotbal College
        </h2>

        <p>
            BBA, BCA & B.Com College, Koppal, Karnataka
        </p>

        <p>
            Empowering students through knowledge,
            skills and academic excellence.
        </p>

        <div class="hero-buttons">

            <a href="courses.php"
               class="btn btn-primary">
                Explore Courses
            </a>

            <a href="contact.php"
               class="btn btn-secondary">
                Contact Us
            </a>

        </div>

    </div>

</section>



<!-- =========================
     MOVING LATEST NOTICES
========================= -->

<section class="notice-ticker">

    <div class="ticker-label">

        📢 Latest Notices

    </div>


    <div class="ticker-container">

        <div class="ticker-content">

            <?php if ($latest_notices && $latest_notices->num_rows > 0): ?>

                <?php while ($notice = $latest_notices->fetch_assoc()): ?>

                    <a
                        href="notices.php#notice-<?php echo (int)$notice["id"]; ?>"
                        class="ticker-link"
                    >

                        <?php
                        echo htmlspecialchars(
                            $notice["title"]
                        );
                        ?>

                    </a>

                    <span class="ticker-separator">
                        •
                    </span>

                <?php endwhile; ?>

            <?php else: ?>

                <span>
                    No latest notices available.
                </span>

            <?php endif; ?>

        </div>

    </div>

</section>



<!-- =========================
     ABOUT SECTION
========================= -->

<section class="section">

    <div class="about">


        <div class="about-slideshow">

            <img
                src="images/college2.jpg"
                class="about-slide active"
                alt="College Campus"
            >

            <img
                src="images/college3.jpg"
                class="about-slide"
                alt="College Campus"
            >

        </div>


        <div class="about-text">

            <h2>
                About Our College
            </h2>

            <p>
                Smt. Sharadamma V. Kotbal BBA, BCA & B.Com
                College is a private higher education institution
                located in Koppal, Karnataka.
            </p>

            <p>
                The college provides undergraduate education in
                business administration, computer applications
                and commerce.
            </p>

            <a href="about.php"
               class="btn btn-primary">

                Read More

            </a>

        </div>

    </div>

</section>



<!-- =========================
     COURSES SECTION
========================= -->

<section class="section">


    <div class="section-title">

        <h2>
            Our Courses
        </h2>

        <p>
            Undergraduate programmes offered by the college
        </p>

    </div>


    <div class="course-grid">


        <!-- BBA -->

        <div class="course-card">

            <div class="course-icon">
                💼
            </div>

            <h3>
                BBA
            </h3>

            <h4>
                Bachelor of Business Administration
            </h4>

            <p>
                The Bachelor of Business Administration
                programme introduces students to important
                areas of business, management, finance,
                marketing and entrepreneurship.
            </p>

            <a href="courses.php#bba"
               class="btn btn-primary">

                View Details

            </a>

        </div>



        <!-- BCA -->

        <div class="course-card">

            <div class="course-icon">
                💻
            </div>

            <h3>
                BCA
            </h3>

            <h4>
                Bachelor of Computer Applications
            </h4>

            <p>
                The Bachelor of Computer Applications
                programme focuses on computer applications,
                programming, databases, web technologies
                and information technology.
            </p>

            <a href="courses.php#bca"
               class="btn btn-primary">

                View Details

            </a>

        </div>



        <!-- B.COM -->

        <div class="course-card">

            <div class="course-icon">
                📊
            </div>

            <h3>
                B.Com
            </h3>

            <h4>
                Bachelor of Commerce
            </h4>

            <p>
                The Bachelor of Commerce programme provides
                students with knowledge of commerce,
                accounting, finance, business and related
                areas.
            </p>

            <a href="courses.php#bcom"
               class="btn btn-primary">

                View Details

            </a>

        </div>


    </div>

</section>

<!-- =========================
     ADMISSION SECTION
========================= -->

<section class="section admission-section">

    <div class="section-title">

        <h2>
            Admissions
        </h2>

        <p>
            Begin your journey with Smt. Sharadamma V. Kotbal College
        </p>

    </div>


    <div class="admission-content">

        <p>
            Admissions are available for our undergraduate
            programmes in BBA, BCA and B.Com.
        </p>

        <p>
            The college does not provide online admission or
            online courses. Students interested in admission
            can contact the college directly for admission
            procedure, eligibility, required documents,
            fees and other details.
        </p>


        <a href="contact.php"
           class="btn btn-primary">

            Contact Us for Admission & More Details

        </a>

    </div>

</section>

<!-- =========================
     FOOTER
========================= -->

<?php include "includes/public_footer.php"; ?>



<!-- =========================
     ABOUT SLIDESHOW
========================= -->

<script>

let aboutSlides =
    document.querySelectorAll(".about-slide");

let aboutCurrentSlide = 0;


function showAboutSlide() {

    aboutSlides.forEach(function(slide) {

        slide.classList.remove("active");

    });


    aboutSlides[
        aboutCurrentSlide
    ].classList.add("active");


    aboutCurrentSlide++;


    if (
        aboutCurrentSlide >=
        aboutSlides.length
    ) {

        aboutCurrentSlide = 0;

    }

}


setInterval(
    showAboutSlide,
    2000
);

</script>


</body>

</html>