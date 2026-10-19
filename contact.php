<?php

require_once "config/database.php";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        Contact Us - Smt. Sharadamma V. Kotbal College
    </title>

    <link rel="stylesheet"
          href="css/style.css">


    <style>

        /* =========================
           CONTACT PAGE
        ========================= */

        .contact-page {
            background: #f8fafc;
            padding-bottom: 70px;
        }


        /* =========================
           PAGE HERO
        ========================= */

        .contact-hero {

            background:
                linear-gradient(
                    rgba(23, 37, 84, 0.90),
                    rgba(23, 37, 84, 0.90)
                ),
                url("images/college.jpg");

            background-size: cover;
            background-position: center;

            color: white;

            text-align: center;

            padding: 80px 20px;

        }


        .contact-hero h2 {

            font-size: 42px;

            margin-bottom: 12px;

        }


        .contact-hero p {

            font-size: 18px;

            color: #e2e8f0;

        }


        /* =========================
           CONTACT CONTAINER
        ========================= */

        .contact-container {

            width: 88%;

            max-width: 1200px;

            margin: 60px auto 0;

        }


        /* =========================
           CONTACT CARDS
        ========================= */

        .contact-info-grid {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

            margin-bottom: 50px;

        }


        .contact-info-card {

            background: white;

            padding: 25px 20px;

            border-radius: 12px;

            text-align: center;

            border:
                1px solid #e2e8f0;

            box-shadow:
                0 8px 25px
                rgba(15, 23, 42, 0.07);

            transition:
                transform 0.3s ease,
                box-shadow 0.3s ease;

        }


        .contact-info-card:hover {

            transform: translateY(-5px);

            box-shadow:
                0 12px 30px
                rgba(15, 23, 42, 0.12);

        }


        .contact-icon {

            width: 55px;

            height: 55px;

            margin:
                0 auto 15px;

            border-radius: 50%;

            background: #eff6ff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 25px;

        }


        .contact-info-card h3 {

            color: #172554;

            margin-bottom: 8px;

        }


        .contact-info-card p {

            color: #64748b;

            font-size: 14px;

            line-height: 1.6;

        }


        /* =========================
           MAIN CONTACT AREA
        ========================= */

        .contact-main {

            display: grid;

            grid-template-columns:
                1fr 1.2fr;

            gap: 35px;

            align-items: start;

        }


        /* =========================
           COLLEGE INFORMATION
        ========================= */

        .contact-details {

            background: white;

            padding: 35px;

            border-radius: 14px;

            border:
                1px solid #e2e8f0;

            box-shadow:
                0 8px 25px
                rgba(15, 23, 42, 0.07);

        }


        .contact-details h2 {

            color: #172554;

            margin-bottom: 15px;

        }


        .contact-details > p {

            color: #64748b;

            line-height: 1.7;

            margin-bottom: 25px;

        }


        .detail-row {

            display: flex;

            gap: 15px;

            margin-bottom: 20px;

            padding-bottom: 20px;

            border-bottom:
                1px solid #e2e8f0;

        }


        .detail-row:last-child {

            border-bottom: none;

        }


        .detail-icon {

            font-size: 22px;

            width: 35px;

        }


        .detail-content strong {

            display: block;

            color: #172554;

            margin-bottom: 4px;

        }


        .detail-content span {

            color: #64748b;

            font-size: 14px;

            line-height: 1.5;

        }


        /* =========================
           ASK PRINCIPAL
        ========================= */

        .question-card {

            background: white;

            padding: 35px;

            border-radius: 14px;

            border:
                1px solid #e2e8f0;

            box-shadow:
                0 8px 25px
                rgba(15, 23, 42, 0.07);

        }


        .question-card h2 {

            color: #172554;

            margin-bottom: 8px;

        }


        .question-card .intro {

            color: #64748b;

            font-size: 14px;

            line-height: 1.6;

            margin-bottom: 25px;

        }


        .form-row {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 15px;

        }


        .form-group {

            margin-bottom: 18px;

        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            font-weight: 600;

            color: #334155;

            font-size: 14px;

        }


        .form-group input,
        .form-group textarea {

            width: 100%;

            padding: 12px 14px;

            border:
                1px solid #cbd5e1;

            border-radius: 7px;

            font-family: inherit;

            font-size: 14px;

            outline: none;

            transition: 0.2s;

        }


        .form-group input:focus,
        .form-group textarea:focus {

            border-color: #172554;

            box-shadow:
                0 0 0 3px
                rgba(23, 37, 84, 0.08);

        }


        .form-group textarea {

            min-height: 140px;

            resize: vertical;

        }


        .question-button {

            width: 100%;

            padding: 13px;

            border: none;

            border-radius: 7px;

            background: #172554;

            color: white;

            font-size: 15px;

            font-weight: 600;

            cursor: pointer;

            transition: 0.3s;

        }


        .question-button:hover {

            background: #1e3a8a;

            transform: translateY(-2px);

        }


        .privacy-note {

            margin-top: 12px;

            text-align: center;

            color: #94a3b8;

            font-size: 12px;

        }


        /* =========================
           MAP
        ========================= */

        .map-section {

            margin-top: 50px;

        }


        .map-section h2 {

            text-align: center;

            color: #172554;

            margin-bottom: 20px;

        }


        .map-box {

            width: 100%;

            height: 350px;

            border-radius: 14px;

            overflow: hidden;

            box-shadow:
                0 8px 25px
                rgba(15, 23, 42, 0.08);

        }


        .map-box iframe {

            width: 100%;

            height: 100%;

            border: 0;

        }


        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 1000px) {

            .contact-info-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        @media (max-width: 800px) {

            .contact-main {

                grid-template-columns: 1fr;

            }

        }


        @media (max-width: 600px) {

            .contact-container {

                width: 92%;

            }

            .contact-hero {

                padding: 60px 20px;

            }

            .contact-hero h2 {

                font-size: 32px;

            }

            .contact-info-grid {

                grid-template-columns: 1fr;

            }

            .form-row {

                grid-template-columns: 1fr;

                gap: 0;

            }

            .contact-details,
            .question-card {

                padding: 25px;

            }

        }

    </style>

</head>


<body>


<!-- =========================
     HEADER
========================= -->

<?php include "includes/public_header.php"; ?>


<!-- =========================
     CONTACT HERO
========================= -->

<section class="contact-hero">

    <h2>Contact Us</h2>

    <p>
        We are here to help and answer your questions.
    </p>

</section>


<main class="contact-page">


<div class="contact-container">


<!-- =========================
     CONTACT INFO CARDS
========================= -->

<div class="contact-info-grid">


    <div class="contact-info-card">

        <div class="contact-icon">
            📍
        </div>

        <h3>Address</h3>

        <p>
            Koppal, Karnataka, India
        </p>

    </div>


    <div class="contact-info-card">

        <div class="contact-icon">
            📞
        </div>

        <h3>Phone</h3>

        <p>
            College Office<br>
            Contact number
        </p>

    </div>


    <div class="contact-info-card">

        <div class="contact-icon">
            ✉️
        </div>

        <h3>Email</h3>

        <p>
            College official email
        </p>

    </div>


    <div class="contact-info-card">

        <div class="contact-icon">
            🕐
        </div>

        <h3>Working Hours</h3>

        <p>
            Monday - Saturday<br>
            9:30 AM - 5:00 PM
        </p>

    </div>


</div>



<!-- =========================
     CONTACT + ASK PRINCIPAL
========================= -->

<div class="contact-main">


    <!-- COLLEGE DETAILS -->

    <div class="contact-details">

        <h2>
            College Contact Information
        </h2>

        <p>
            For admissions, academic information,
            student support or general enquiries,
            please contact the college office.
        </p>


        <div class="detail-row">

            <div class="detail-icon">
                📍
            </div>

            <div class="detail-content">

                <strong>College Address</strong>

                <span>
                    Smt. Sharadamma V. Kotbal
                    BBA, BCA &amp; B.Com College,
                    Koppal, Karnataka, India.
                </span>

            </div>

        </div>


        <div class="detail-row">

            <div class="detail-icon">
                📞
            </div>

            <div class="detail-content">

                <strong>College Office</strong>

                <span>
                    Contact the college office
                    for official enquiries.
                </span>

            </div>

        </div>


        <div class="detail-row">

            <div class="detail-icon">
                ✉️
            </div>

            <div class="detail-content">

                <strong>Email</strong>

                <span>
                    Contact the college through
                    the official email address.
                </span>

            </div>

        </div>


        <div class="detail-row">

            <div class="detail-icon">
                🕐
            </div>

            <div class="detail-content">

                <strong>Office Hours</strong>

                <span>
                    Monday to Saturday<br>
                    9:30 AM to 5:00 PM
                </span>

            </div>

        </div>


    </div>



    <!-- ASK PRINCIPAL -->

    <div class="question-card">

        <h2>
            Ask the Principal
        </h2>

        <p class="intro">

            Have a question or suggestion for the
            Principal? Send your message through the
            form below. The Principal will review
            your question and provide a response.

        </p>


        <form
            action="submit_question.php"
            method="POST"
        >


            <div class="form-row">


                <div class="form-group">

                    <label for="name">
                        Your Name *
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Enter your name"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="email">
                        Email *
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email"
                        required
                    >

                </div>


            </div>



            <div class="form-row">


                <div class="form-group">

                    <label for="phone">
                        Phone Number
                    </label>

                    <input
                        type="text"
                        id="phone"
                        name="phone"
                        placeholder="Optional"
                        maxlength="10"
                    >

                </div>


                <div class="form-group">

                    <label for="subject">
                        Subject *
                    </label>

                    <input
                        type="text"
                        id="subject"
                        name="subject"
                        placeholder="Question subject"
                        required
                    >

                </div>


            </div>



            <div class="form-group">

                <label for="question">
                    Your Question *
                </label>

                <textarea
                    id="question"
                    name="question"
                    placeholder="Write your question here..."
                    required
                ></textarea>

            </div>



            <button
                type="submit"
                class="question-button"
            >

                Send Question to Principal

            </button>


            <p class="privacy-note">

                Your question will be reviewed by
                the college Principal.

            </p>


        </form>

    </div>


</div>



<!-- =========================
     MAP
========================= -->

<div class="map-section">

    <h2>
        Find Us
    </h2>


    <div class="map-box">

        <iframe
            src="https://www.google.com/maps?q=Koppal,Karnataka,India&output=embed"
            loading="lazy"
            allowfullscreen>
        </iframe>

    </div>

</div>


</div>

</main>


<!-- =========================
     FOOTER
========================= -->

<?php include "includes/public_footer.php"; ?>


</body>

</html>