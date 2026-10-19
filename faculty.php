<?php
session_start();
require_once __DIR__ . "/config/database.php";

// Escape output before displaying database values.
function faculty_escape($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Return a usable public image path, or an empty string when no photo is saved.
function faculty_photo_path($photo) {
    $photo = basename(trim((string)($photo ?? "")));
    if ($photo === "") {
        return "";
    }

    $relativePath = "images/faculty/" . $photo;
    $absolutePath = __DIR__ . "/" . $relativePath;
    return is_file($absolutePath) ? $relativePath : "";
}

// Check whether the optional photo column exists, so the page still works
// if the Principal photo column has not been added yet.
function faculty_column_exists($conn, $table, $column) {
    $allowedTables = ["principals", "lecturers"];
    if (!in_array($table, $allowedTables, true)) {
        return false;
    }
    $column = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $result && $result->num_rows > 0;
}

$principal = null;
$principalHasPhoto = faculty_column_exists($conn, "principals", "photo");
$principalPhotoField = $principalHasPhoto ? "photo" : "NULL AS photo";

$principalSql = "SELECT principal_id, full_name, email, phone, designation, {$principalPhotoField}
                 FROM principals
                 ORDER BY created_at DESC
                 LIMIT 1";
$principalResult = $conn->query($principalSql);
if ($principalResult && $principalResult->num_rows > 0) {
    $principal = $principalResult->fetch_assoc();
}

$lecturers = [];
$lecturerHasPhoto = faculty_column_exists($conn, "lecturers", "photo");
$lecturerPhotoField = $lecturerHasPhoto ? "l.photo" : "NULL AS photo";
$lecturerSql = "SELECT l.id, l.lecturer_id, l.full_name, l.email, l.phone,
                       l.designation, {$lecturerPhotoField}, d.department_name
                FROM lecturers AS l
                LEFT JOIN departments AS d ON l.department_id = d.id
                ORDER BY l.full_name ASC";
$lecturerResult = $conn->query($lecturerSql);
if ($lecturerResult) {
    while ($row = $lecturerResult->fetch_assoc()) {
        $lecturers[] = $row;
    }
}

include __DIR__ . "/includes/public_header.php";
?>

<style>
.faculty-page { max-width: 1200px; margin: 0 auto; }
.faculty-intro { text-align: center; margin-bottom: 32px; }
.faculty-section-heading { text-align: center; margin: 36px 0 22px; }
.faculty-section-heading h2 { margin-bottom: 8px; }
.faculty-section-heading p { margin: 0; }
.faculty-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 24px;
    align-items: stretch;
}
.faculty-card {
    background: #fff;
    border-radius: 14px;
    padding: 24px 20px;
    text-align: center;
    box-shadow: 0 5px 20px rgba(15, 23, 42, .08);
    border: 1px solid #e8edf5;
    overflow: hidden;
}
.faculty-card.principal-card { max-width: 420px; margin: 0 auto; }
.faculty-photo, .faculty-placeholder {
    width: 132px;
    height: 132px;
    border-radius: 50%;
    object-fit: cover;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    border: 4px solid #eef2ff;
}
.faculty-placeholder {
    background: linear-gradient(135deg, #dbeafe, #e0e7ff);
    color: #1e3a8a;
    font-size: 38px;
    font-weight: 700;
}
.faculty-card h3 { margin: 8px 0 6px; font-size: 1.15rem; color: #172554; }
.faculty-designation { color: #334155; font-weight: 600; margin: 0 0 8px; }
.faculty-department { color: #64748b; margin: 0 0 12px; }
.faculty-contact { font-size: .92rem; color: #475569; overflow-wrap: anywhere; margin: 5px 0; }
.faculty-tag {
    display: inline-block;
    margin-top: 8px;
    padding: 5px 11px;
    border-radius: 999px;
    background: #eef2ff;
    color: #3730a3;
    font-size: .8rem;
    font-weight: 600;
}
.faculty-empty {
    text-align: center;
    padding: 26px 18px;
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    color: #475569;
    grid-column: 1 / -1;
}

/* Lecturer group-photo slideshow */
.faculty-slideshow {
    position: relative;
    max-width: 1000px;
    margin: 0 auto 42px;
    border-radius: 18px;
    overflow: hidden;
    background: #0f172a;
    box-shadow: 0 10px 28px rgba(15, 23, 42, .18);
}
.faculty-slide { display: none; position: relative; }
.faculty-slide.is-active { display: block; animation: facultyFade .45s ease; }
.faculty-slide img {
    display: block;
    width: 100%;
    height: clamp(240px, 48vw, 520px);
    object-fit: cover;
}
.faculty-slide-caption {
    position: absolute;
    inset: auto 0 0;
    padding: 42px 20px 22px;
    text-align: center;
    color: #fff;
    font-weight: 700;
    font-size: clamp(1rem, 2.5vw, 1.35rem);
    background: linear-gradient(transparent, rgba(15, 23, 42, .78));
}
.faculty-slide-control {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 2;
    width: 44px;
    height: 44px;
    border: 0;
    border-radius: 50%;
    background: rgba(255,255,255,.92);
    color: #172554;
    font-size: 28px;
    line-height: 1;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,.18);
}
.faculty-slide-control:hover { background: #fff; }
.faculty-slide-prev { left: 14px; }
.faculty-slide-next { right: 14px; }
.faculty-slide-dots {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 9px;
    padding: 14px 10px 16px;
    background: #f8fafc;
}
.faculty-slide-dot {
    width: 10px;
    height: 10px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: #cbd5e1;
    cursor: pointer;
}
.faculty-slide-dot[aria-current="true"] { background: #1d4ed8; transform: scale(1.2); }
@keyframes facultyFade { from { opacity: .65; } to { opacity: 1; } }
@media (max-width: 600px) {
    .faculty-slide img { height: 250px; }
    .faculty-slide-control { width: 38px; height: 38px; font-size: 24px; }
    .faculty-slide-prev { left: 9px; }
    .faculty-slide-next { right: 9px; }
}

@media (max-width: 600px) {
    .faculty-grid { grid-template-columns: 1fr; }
    .faculty-card.principal-card { max-width: 100%; }
}
</style>

<section class="section">
    <div class="faculty-page">
        <div class="section-title faculty-intro">
            <h2>Our Faculty</h2>
            <p>Meet the teaching and academic team of our college.</p>
        </div>


        <div class="faculty-section-heading" style="margin-top: 28px;">
            <h2>Our Faculty in Action</h2>
            <p>Moments and memories from our college faculty team.</p>
        </div>

        <div class="faculty-slideshow" id="facultySlideshow" aria-label="Lecturer group photo slideshow">
            <div class="faculty-slide is-active">
                <img src="images/faculty-group/group1.jpg" alt="College lecturer group photo 1" loading="eager">
                <div class="faculty-slide-caption">Our Faculty Team</div>
            </div>
            <div class="faculty-slide">
                <img src="images/faculty-group/group2.jpg" alt="College lecturer group photo 2" loading="lazy">
                <div class="faculty-slide-caption">Together for Student Success</div>
            </div>
            <div class="faculty-slide">
                <img src="images/faculty-group/group3.jpg" alt="College lecturer group photo 3" loading="lazy">
                <div class="faculty-slide-caption">Learning and Growing Together</div>
            </div>
            <div class="faculty-slide">
                <img src="images/faculty-group/group4.jpg" alt="College lecturer group photo 4" loading="lazy">
                <div class="faculty-slide-caption">A Dedicated Academic Team</div>
            </div>
            <div class="faculty-slide">
                <img src="images/faculty-group/group5.jpg" alt="College lecturer group photo 5" loading="lazy">
                <div class="faculty-slide-caption">Supporting Every Student</div>
            </div>

            <button class="faculty-slide-control faculty-slide-prev" type="button" aria-label="Previous photo">&#8249;</button>
            <button class="faculty-slide-control faculty-slide-next" type="button" aria-label="Next photo">&#8250;</button>
            <div class="faculty-slide-dots" aria-label="Choose a photo">
                <button class="faculty-slide-dot" type="button" aria-label="Show photo 1" aria-current="true"></button>
                <button class="faculty-slide-dot" type="button" aria-label="Show photo 2" aria-current="false"></button>
                <button class="faculty-slide-dot" type="button" aria-label="Show photo 3" aria-current="false"></button>
                <button class="faculty-slide-dot" type="button" aria-label="Show photo 4" aria-current="false"></button>
                <button class="faculty-slide-dot" type="button" aria-label="Show photo 5" aria-current="false"></button>
            </div>
        </div>

        <div class="faculty-section-heading">
            <h2>Principal</h2>
            <p>Academic and administrative leadership of the institution.</p>
        </div>

        <?php if ($principal): ?>
            <div class="faculty-card principal-card">
                <?php $principalPhoto = faculty_photo_path($principal['photo'] ?? ''); ?>
                <?php if ($principalPhoto): ?>
                    <img class="faculty-photo" src="<?= faculty_escape($principalPhoto) ?>" alt="Photo of <?= faculty_escape($principal['full_name']) ?>" loading="lazy">
                <?php else: ?>
                    <?php
                    $principalInitials = '';
                    foreach (preg_split('/\s+/', trim((string)$principal['full_name'])) as $part) {
                        if ($part !== '') $principalInitials .= mb_substr($part, 0, 1, 'UTF-8');
                        if (mb_strlen($principalInitials, 'UTF-8') >= 2) break;
                    }
                    ?>
                    <div class="faculty-placeholder" aria-label="No photo available"><?= faculty_escape(mb_strtoupper($principalInitials ?: 'P', 'UTF-8')) ?></div>
                <?php endif; ?>
                <h3><?= faculty_escape($principal['full_name']) ?></h3>
                <p class="faculty-designation"><?= faculty_escape($principal['designation'] ?: 'Principal') ?></p>
                <span class="faculty-tag">College Leadership</span>
                <?php if (!empty($principal['email'])): ?><p class="faculty-contact">Email: <?= faculty_escape($principal['email']) ?></p><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="faculty-empty">Principal details have not been added yet.</div>
        <?php endif; ?>

        <div class="faculty-section-heading" style="margin-top: 58px;">
            <h2>Teaching Faculty</h2>
            <p>Our faculty members support students in their academic and professional development.</p>
        </div>

        <div class="faculty-grid">
            <?php if (count($lecturers) > 0): ?>
                <?php foreach ($lecturers as $lecturer): ?>
                    <?php
                    $lecturerPhoto = faculty_photo_path($lecturer['photo'] ?? '');
                    $initials = '';
                    foreach (preg_split('/\s+/', trim((string)$lecturer['full_name'])) as $part) {
                        if ($part !== '') $initials .= mb_substr($part, 0, 1, 'UTF-8');
                        if (mb_strlen($initials, 'UTF-8') >= 2) break;
                    }
                    ?>
                    <article class="faculty-card">
                        <?php if ($lecturerPhoto): ?>
                            <img class="faculty-photo" src="<?= faculty_escape($lecturerPhoto) ?>" alt="Photo of <?= faculty_escape($lecturer['full_name']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="faculty-placeholder" aria-label="No photo available"><?= faculty_escape(mb_strtoupper($initials ?: 'F', 'UTF-8')) ?></div>
                        <?php endif; ?>
                        <h3><?= faculty_escape($lecturer['full_name']) ?></h3>
                        <p class="faculty-designation"><?= faculty_escape($lecturer['designation'] ?: 'Lecturer') ?></p>
                        <p class="faculty-department"><?= faculty_escape($lecturer['department_name'] ?: 'Department not assigned') ?></p>
                        <?php if (!empty($lecturer['email'])): ?><p class="faculty-contact">Email: <?= faculty_escape($lecturer['email']) ?></p><?php endif; ?>
                        <?php if (!empty($lecturer['lecturer_id'])): ?><span class="faculty-tag">Faculty</span><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="faculty-empty">Teaching faculty details have not been added yet.</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section notice-section">
    <div class="section-title">
        <h2>Faculty &amp; Academic Support</h2>
        <p>Our academic team is committed to supporting student learning, guidance, and development.</p>
    </div>
</section>


<script>
(function () {
    const slideshow = document.getElementById('facultySlideshow');
    if (!slideshow) return;

    const slides = Array.from(slideshow.querySelectorAll('.faculty-slide'));
    const dots = Array.from(slideshow.querySelectorAll('.faculty-slide-dot'));
    const previousButton = slideshow.querySelector('.faculty-slide-prev');
    const nextButton = slideshow.querySelector('.faculty-slide-next');
    let currentIndex = 0;
    let intervalId = null;

    function showSlide(index) {
        currentIndex = (index + slides.length) % slides.length;
        slides.forEach((slide, i) => {
            slide.classList.toggle('is-active', i === currentIndex);
            slide.setAttribute('aria-hidden', i === currentIndex ? 'false' : 'true');
        });
        dots.forEach((dot, i) => {
            dot.setAttribute('aria-current', i === currentIndex ? 'true' : 'false');
        });
    }

    function startAutoplay() {
        stopAutoplay();
        intervalId = window.setInterval(() => showSlide(currentIndex + 1), 5000);
    }

    function stopAutoplay() {
        if (intervalId !== null) {
            window.clearInterval(intervalId);
            intervalId = null;
        }
    }

    previousButton.addEventListener('click', () => { showSlide(currentIndex - 1); startAutoplay(); });
    nextButton.addEventListener('click', () => { showSlide(currentIndex + 1); startAutoplay(); });
    dots.forEach((dot, i) => dot.addEventListener('click', () => { showSlide(i); startAutoplay(); }));
    slideshow.addEventListener('mouseenter', stopAutoplay);
    slideshow.addEventListener('mouseleave', startAutoplay);
    slideshow.addEventListener('focusin', stopAutoplay);
    slideshow.addEventListener('focusout', startAutoplay);

    showSlide(0);
    startAutoplay();
})();
</script>

<?php include __DIR__ . "/includes/public_footer.php"; ?>
