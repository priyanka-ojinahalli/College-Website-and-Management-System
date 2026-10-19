
<?php
$page_title = "College Gallery";
require_once "includes/public_header.php";
?>

<style>
.gallery-container {
    max-width: 1200px;
    margin: 35px auto;
    padding: 20px;
}

.gallery-heading {
    text-align: center;
    margin-bottom: 30px;
}

.gallery-heading h1 {
    color: #172554;
    font-size: 34px;
    margin-bottom: 10px;
}

.gallery-heading p {
    color: #64748b;
    font-size: 16px;
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 22px;
}

.gallery-card {
    background: #ffffff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.10);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.gallery-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.15);
}

.gallery-card img {
    display: block;
    width: 100%;
    height: 230px;
    object-fit: cover;
}

.gallery-caption {
    padding: 16px;
}

.gallery-caption h3 {
    color: #172554;
    margin: 0 0 7px;
    font-size: 18px;
}

.gallery-caption p {
    color: #64748b;
    margin: 0;
    font-size: 14px;
    line-height: 1.5;
}

.gallery-empty {
    grid-column: 1 / -1;
    text-align: center;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    padding: 35px 20px;
    color: #64748b;
}

/* Enlarged image viewer */
.gallery-lightbox {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0, 0, 0, 0.92);
    align-items: center;
    justify-content: center;
    padding: 60px 70px;
}

.gallery-lightbox.is-open {
    display: flex;
}

.gallery-lightbox figure {
    margin: 0;
    text-align: center;
    max-width: 100%;
    max-height: 100%;
}

.gallery-lightbox figure img {
    display: block;
    width: auto;
    height: auto;
    max-width: 100%;
    max-height: 75vh;
    object-fit: contain;
    margin: 0 auto;
}

.gallery-lightbox figcaption {
    color: #ffffff;
    margin-top: 14px;
    font-size: 16px;
}

.gallery-lightbox button {
    position: absolute;
    color: #ffffff;
    background: rgba(255, 255, 255, 0.15);
    border: none;
    border-radius: 50%;
    cursor: pointer;
    width: 44px;
    height: 44px;
    font-size: 27px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.gallery-lightbox button:hover {
    background: rgba(255, 255, 255, 0.3);
}

.gallery-lightbox-close {
    top: 20px;
    right: 24px;
}

.gallery-lightbox-prev {
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
}

.gallery-lightbox-next {
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
}

.gallery-card img {
    cursor: zoom-in;
}

@media (max-width: 550px) {
    .gallery-lightbox {
        padding: 60px 48px;
    }

    .gallery-lightbox figure img {
        max-height: 65vh;
    }

    .gallery-lightbox-close {
        top: 12px;
        right: 12px;
    }

    .gallery-lightbox-prev {
        left: 4px;
    }

    .gallery-lightbox-next {
        right: 4px;
    }
}

@media (max-width: 850px) {
    .gallery-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 550px) {
    .gallery-container {
        padding: 14px;
    }

    .gallery-heading h1 {
        font-size: 27px;
    }

    .gallery-grid {
        grid-template-columns: 1fr;
    }

    .gallery-card img {
        height: 220px;
    }
}
</style>

<main class="gallery-container">

    <section class="gallery-heading">
        <h1>College Gallery</h1>
        <p>
            Explore our campus, classrooms, college events,
            cultural activities and student achievements.
        </p>
    </section>

    <section class="gallery-grid">

        <article class="gallery-card">
            <img
                src="images/gallery/college_campus.jpeg"
                alt="College campus"
                loading="lazy"
            >
            <div class="gallery-caption">
                <h3>College Campus</h3>
                <p>A glimpse of our college campus.</p>
            </div>
        </article>

        <article class="gallery-card">
            <img
                src="images/gallery/classrooms.jpeg"
                alt="College classrooms"
                loading="lazy"
            >
            <div class="gallery-caption">
                <h3>Classrooms & Labs</h3>
                <p>Our learning spaces for students.</p>
            </div>
        </article>

        <article class="gallery-card">
            <img
                src="images/gallery/cultural_events.jpeg"
                alt="Cultural activities"
                loading="lazy"
            >
            <div class="gallery-caption">
                <h3>Cultural Activities</h3>
                <p>Celebrating creativity and talent.</p>
            </div>
        </article>

        <article class="gallery-card">
            <img
                src="images/gallery/sports.jpeg"
                alt="College sports activities"
                loading="lazy"
            >
            <div class="gallery-caption">
                <h3>Sports Activities</h3>
                <p>Encouraging teamwork and participation.</p>
            </div>
        </article>

        <article class="gallery-card">
            <img
                src="images/gallery/college_events.jpeg"
                alt="College events"
                loading="lazy"
            >
            <div class="gallery-caption">
                <h3>College Events</h3>
                <p>Memorable moments from college programmes.</p>
            </div>
        </article>

        <article class="gallery-card">
            <img
                src="https://placehold.co/600x400/fee2e2/991b1b?text=Student+Activities"
                alt="Student activities"
                loading="lazy"
            >
            <div class="gallery-caption">
                <h3>Student Activities</h3>
                <p>Learning, participating and growing together.</p>
            </div>
        </article>

    </section>
    
<div id="galleryLightbox" class="gallery-lightbox" aria-hidden="true">
    <button
        type="button"
        class="gallery-lightbox-close"
        id="galleryLightboxClose"
        aria-label="Close enlarged image"
    >&times;</button>

    <button
        type="button"
        class="gallery-lightbox-prev"
        id="galleryLightboxPrev"
        aria-label="Previous image"
    >&#10094;</button>

    <figure>
        <img id="galleryLightboxImage" src="" alt="">
        <figcaption id="galleryLightboxCaption"></figcaption>
    </figure>

    <button
        type="button"
        class="gallery-lightbox-next"
        id="galleryLightboxNext"
        aria-label="Next image"
    >&#10095;</button>
</div>

</main>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const lightbox = document.getElementById("galleryLightbox");
    const lightboxImage = document.getElementById("galleryLightboxImage");
    const lightboxCaption = document.getElementById("galleryLightboxCaption");

    const closeButton = document.getElementById("galleryLightboxClose");
    const prevButton = document.getElementById("galleryLightboxPrev");
    const nextButton = document.getElementById("galleryLightboxNext");

    const images = Array.from(
        document.querySelectorAll(".gallery-card img")
    );

    let currentIndex = 0;

    function showImage(index) {
        if (images.length === 0) return;

        currentIndex = (index + images.length) % images.length;

        const image = images[currentIndex];
        const card = image.closest(".gallery-card");
        const heading = card.querySelector("h3");

        lightboxImage.src = image.src;
        lightboxImage.alt = image.alt;
        lightboxCaption.textContent = heading
            ? heading.textContent
            : image.alt;

        lightbox.classList.add("is-open");
        lightbox.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
        closeButton.focus();
    }

    function closeLightbox() {
        lightbox.classList.remove("is-open");
        lightbox.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
    }

    images.forEach(function (image, index) {
        image.addEventListener("click", function () {
            showImage(index);
        });
    });

    closeButton.addEventListener("click", closeLightbox);

    prevButton.addEventListener("click", function () {
        showImage(currentIndex - 1);
    });

    nextButton.addEventListener("click", function () {
        showImage(currentIndex + 1);
    });

    lightbox.addEventListener("click", function (event) {
        if (event.target === lightbox) {
            closeLightbox();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (!lightbox.classList.contains("is-open")) return;

        if (event.key === "Escape") {
            closeLightbox();
        } else if (event.key === "ArrowLeft") {
            showImage(currentIndex - 1);
        } else if (event.key === "ArrowRight") {
            showImage(currentIndex + 1);
        }
    });
});
</script>
<?php require_once "includes/public_footer.php"; ?>