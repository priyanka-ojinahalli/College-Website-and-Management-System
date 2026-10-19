```php
<?php include "includes/public_header.php"; ?>

<section class="section">

    <div class="section-title">
        <h2>About Our College</h2>
        <p>Learn more about our institution</p>
    </div>

    <div class="about">

        <!-- College Photo Slideshow -->
        <div class="about-image">

            <div class="about-slideshow">

                <img src="images/college4.jpeg"
                     alt="College Campus Photo 1"
                     class="about-slide active">

                <img src="images/college5.jpeg"
                     alt="College Campus Photo 2"
                     class="about-slide">

                <button type="button"
                        class="about-prev"
                        onclick="changeAboutSlide(-1)"
                        aria-label="Previous photo">
                    &#10094;
                </button>

                <button type="button"
                        class="about-next"
                        onclick="changeAboutSlide(1)"
                        aria-label="Next photo">
                    &#10095;
                </button>

                <div class="about-dots">
                    <button type="button"
                            class="about-dot active"
                            onclick="showAboutSlide(0)"
                            aria-label="Show photo 1"></button>

                    <button type="button"
                            class="about-dot"
                            onclick="showAboutSlide(1)"
                            aria-label="Show photo 2"></button>
                </div>

            </div>

        </div>

        <div class="about-text">

            <h2>Smt. Sharadamma V. Kotbal College</h2>

            <p>
                Smt. Sharadamma V. Kotbal BBA, BCA &
                B.Com College is a private college located
                in Koppal, Karnataka.
            </p>

            <p>
                The institution provides undergraduate
                programmes in Business Administration,
                Computer Applications and Commerce.
            </p>

            <p>
                The college aims to provide students with
                academic knowledge, practical skills and
                opportunities for overall development.
            </p>

            <h3>Our Programmes</h3>

            <p>• Bachelor of Business Administration (BBA)</p>
            <p>• Bachelor of Computer Applications (BCA)</p>
            <p>• Bachelor of Commerce (B.Com)</p>

        </div>

    </div>

</section>

<style>
.about-slideshow {
    position: relative;
    width: 100%;
    overflow: hidden;
    border-radius: 12px;
    background: #e5e7eb;
}

.about-slide {
    display: none;
    width: 100%;
    height: 380px;
    object-fit: cover;
}

.about-slide.active {
    display: block;
    animation: aboutFade 0.6s ease;
}

.about-prev,
.about-next {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    font-size: 20px;
    cursor: pointer;
}

.about-prev {
    left: 12px;
}

.about-next {
    right: 12px;
}

.about-prev:hover,
.about-next:hover {
    background: rgba(0, 0, 0, 0.85);
}

.about-dots {
    position: absolute;
    bottom: 14px;
    left: 0;
    right: 0;
    display: flex;
    justify-content: center;
    gap: 10px;
}

.about-dot {
    width: 11px;
    height: 11px;
    border: none;
    border-radius: 50%;
    padding: 0;
    background: white;
    opacity: 0.6;
    cursor: pointer;
}

.about-dot.active {
    opacity: 1;
    transform: scale(1.2);
}

@keyframes aboutFade {
    from { opacity: 0.4; }
    to { opacity: 1; }
}

@media (max-width: 600px) {
    .about-slide {
        height: 260px;
    }
}
</style>

<script>
let aboutSlideIndex = 0;
let aboutSlideTimer;

function showAboutSlide(index) {
    const slides = document.querySelectorAll(".about-slide");
    const dots = document.querySelectorAll(".about-dot");

    if (!slides.length) return;

    aboutSlideIndex = (index + slides.length) % slides.length;

    slides.forEach((slide, i) => {
        slide.classList.toggle("active", i === aboutSlideIndex);
    });

    dots.forEach((dot, i) => {
        dot.classList.toggle("active", i === aboutSlideIndex);
    });

    clearInterval(aboutSlideTimer);
    aboutSlideTimer = setInterval(() => {
        showAboutSlide(aboutSlideIndex + 1);
    }, 4000);
}

function changeAboutSlide(direction) {
    showAboutSlide(aboutSlideIndex + direction);
}

showAboutSlide(0);
</script>

<?php include "includes/public_footer.php"; ?>
```