<?php
// includes/common_dashboard.php
// Expected variables:
// - $welcome_name: string (student or counselor full name)
// - $role: string ('student' or 'counselor')
// - $quick_nav_cards: array of array (title, url, icon, desc, badge_class)
// - $extra_panels_html: string (role-specific content to display in the main left column)
// - $sidebar_panels_html: string (role-specific content to display in the right sidebar column)

// Predefined quotes
$quotes = [
    "Your mental health is a priority. Your happiness is an essential. Your self-care is a necessity.",
    "It is okay to not be okay, but it is not okay to stay that way. Reach out, you are not alone.",
    "Deep breathing is like an anchor in the midst of a raging storm.",
    "You don't have to control your thoughts. You just have to stop letting them control you.",
    "Self-care is how you take your power back.",
    "One small crack does not mean that you are broken, it means that you were put to the test and you didn't fall apart.",
    "Tough times never last, but tough people do.",
    "Give yourself the same care and attention you give to others and watch yourself bloom.",
    "You are stronger than you think, braver than you know, and loved more than you can imagine.",
    "Slow down. Take a deep breath. You are doing the best you can.",
    "Wellness is a connection of path, mind, and spirit. Every step you take matters.",
    "Quiet the mind and the soul will speak.",
    "Healing takes time, and asking for help is a courageous step, not a weakness.",
    "Be gentle with yourself. You are doing the best you can with what you have.",
    "Your present circumstances don't determine where you can go; they merely determine where you start.",
    "Almost everything will work again if you unplug it for a few minutes, including you.",
    "The only way to get through is to go through, but you don't have to walk the path alone.",
    "Mindfulness isn't difficult, we just need to remember to do it.",
    "Peace is not the absence of trouble, but the presence of strength.",
    "You are worthy of support, patience, and love. Never forget that."
];
$random_quote = $quotes[array_rand($quotes)];

// Fetch top 3 articles from database
$artStmt = $pdo->query("SELECT * FROM articles ORDER BY created_at DESC LIMIT 3");
$dashboard_articles = $artStmt->fetchAll();
?>

<!-- Welcome greeting -->
<div class="row mb-4 fade-in-up align-items-center">
    <?php if ($role === 'counselor'): ?>
        <?php
        if (!isset($pdo)) {
            require_once __DIR__ . '/../config/db.php';
        }
        $cPhotoStmt = $pdo->prepare("SELECT photo FROM counselors WHERE user_id = ?");
        $cPhotoStmt->execute([$_SESSION['user_id']]);
        $c_photo = $cPhotoStmt->fetchColumn() ?: 'default.png';
        ?>
        <div class="col-auto">
            <img src="../uploads/counselors/<?php echo h($c_photo); ?>" alt="Dr. <?php echo h($welcome_name); ?>" class="avatar-photo avatar-photo-lg shadow-sm">
        </div>
    <?php endif; ?>
    <div class="col">
        <h1 class="fw-bold text-dark">
            <?php echo ($role === 'counselor') ? "Welcome, Dr. " . h($welcome_name) . "!" : "Hello, " . h($welcome_name) . "!"; ?>
        </h1>
        <p class="text-muted mb-0">Welcome to your mental wellness space. Here is your overview for today.</p>
    </div>
</div>

<?php if ($role === 'student'): ?>
    <?php
    // Fetch student's upcoming approved or rescheduled appointments
    $student_id = $_SESSION['student_id'];
    $notifStmt = $pdo->prepare("
        SELECT a.*, c.full_name AS counselor_name 
        FROM appointments a
        JOIN counselors c ON a.counselor_id = c.id
        WHERE a.student_id = ? AND a.appointment_date >= CURDATE() AND a.status IN ('approved', 'rescheduled')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $notifStmt->execute([$student_id]);
    $notifications = $notifStmt->fetchAll();
    ?>
    <?php if (!empty($notifications)): ?>
        <div class="row mb-3 fade-in-up">
            <div class="col-12">
                <?php foreach ($notifications as $notif): ?>
                    <?php
                    $is_today = ($notif['appointment_date'] === date('Y-m-d'));
                    $is_rescheduled = ($notif['status'] === 'rescheduled');
                    
                    $alert_class = 'alert-info';
                    $icon = 'bi-calendar-check-fill';
                    $title = 'Upcoming Appointment';
                    $border_class = 'border-info';
                    
                    if ($is_today) {
                        $alert_class = 'alert-warning';
                        $icon = 'bi-exclamation-triangle-fill';
                        $title = 'Appointment Scheduled for Today';
                        $border_class = 'border-warning';
                    } elseif ($is_rescheduled) {
                        $alert_class = 'alert-info';
                        $icon = 'bi-calendar2-range-fill';
                        $title = 'Appointment Rescheduled';
                        $border_class = 'border-primary';
                    }
                    ?>
                    <div class="alert <?php echo $alert_class; ?> alert-custom alert-dismissible fade show border-start border-4 <?php echo $border_class; ?> mb-2 shadow-sm" role="alert" style="border-radius: 12px; padding: 1.25rem 1.5rem;">
                        <div class="d-flex align-items-center">
                            <span class="fs-4 me-3 text-<?php echo ($is_today ? 'warning' : ($is_rescheduled ? 'primary' : 'info')); ?>"><i class="bi <?php echo $icon; ?>"></i></span>
                            <div>
                                <h6 class="alert-heading fw-bold mb-1" style="font-size: 0.95rem;"><?php echo $title; ?></h6>
                                <span class="small">
                                    Your counseling consultation session with <strong>Dr. <?php echo h($notif['counselor_name']); ?></strong> is confirmed for 
                                    <strong><?php echo date('F d, Y', strtotime($notif['appointment_date'])); ?></strong> at 
                                    <strong><?php echo date('h:i A', strtotime($notif['appointment_time'])); ?></strong>.
                                    <?php if ($is_rescheduled): ?>
                                        <span class="badge bg-primary text-white ms-1">Updated by Counselor</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="row g-4">
    <!-- Main Left Content Area -->
    <div class="col-lg-8">
        
        <?php if ($role === 'student'): ?>
            <!-- Image Carousel (Fixed height, object-fit contain, light blue wellness style) -->
            <div class="card card-glass border-0 p-3 mb-4 shadow-sm fade-in-up">
                <div id="wellnessDashboardCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" style="border-radius: 12px; overflow: hidden;">
                    <div class="carousel-indicators">
                        <button type="button" data-bs-target="#wellnessDashboardCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                        <button type="button" data-bs-target="#wellnessDashboardCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                        <button type="button" data-bs-target="#wellnessDashboardCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                    </div>
                    <div class="carousel-inner">
                        <div class="carousel-item active" data-bs-interval="4000">
                            <img src="../img/meditation.png" class="d-block w-100" alt="Mindfulness Meditation">
                            <div class="carousel-caption d-none d-md-block p-3 rounded" style="background: rgba(44, 62, 80, 0.75); backdrop-filter: blur(4px);">
                                <h6 class="fw-bold text-white mb-1">Mindfulness &amp; Wellness</h6>
                                <p class="small text-white mb-0">Take charge of your emotions, practice deep breathing, and build stress resilience.</p>
                            </div>
                        </div>
                        <div class="carousel-item" data-bs-interval="4000">
                            <img src="../img/counseling.png" class="d-block w-100" alt="Counseling Session">
                            <div class="carousel-caption d-none d-md-block p-3 rounded" style="background: rgba(44, 62, 80, 0.75); backdrop-filter: blur(4px);">
                                <h6 class="fw-bold text-white mb-1">Professional Counseling Support</h6>
                                <p class="small text-white mb-0">Consult with certified advisors, schedule sessions, and explore personalized advice.</p>
                            </div>
                        </div>
                        <div class="carousel-item" data-bs-interval="4000">
                            <img src="../img/journaling.png" class="d-block w-100" alt="Journal Reflections">
                            <div class="carousel-caption d-none d-md-block p-3 rounded" style="background: rgba(44, 62, 80, 0.75); backdrop-filter: blur(4px);">
                                <h6 class="fw-bold text-white mb-1">Self-Reflection Journals</h6>
                                <p class="small text-white mb-0">Maintain a safe, secure therapeutic diary of your thoughts and feelings.</p>
                            </div>
                        </div>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#wellnessDashboardCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#wellnessDashboardCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($role === 'student'): ?>
            <!-- Daily Motivation Quote -->
            <div class="card quote-card border-0 p-4 mb-4 shadow-sm fade-in-up">
                <div class="card-body py-2 text-center">
                    <span class="text-secondary fs-4 d-block mb-1"><i class="bi bi-quote"></i></span>
                    <h6 class="fw-bold mb-1">Daily Motivation</h6>
                    <p class="fs-6 text-secondary mb-0" style="font-style: italic; max-width: 600px; margin: 0 auto;">
                        "<?php echo h($random_quote); ?>"
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Extra main panels (Mood log, counselor tables, session reports) -->
        <?php if (!empty($extra_panels_html)): ?>
            <div class="mb-4">
                <?php echo $extra_panels_html; ?>
            </div>
        <?php endif; ?>

        <!-- Quick Navigation Cards -->
        <div class="card card-glass border-0 p-4 mb-4 shadow-sm fade-in-up">
            <h5 class="fw-bold mb-3"><i class="bi bi-compass text-secondary me-2"></i> Quick Actions Navigation</h5>
            <div class="row g-3">
                <?php foreach ($quick_nav_cards as $card): ?>
                    <div class="col-sm-6 col-md-6">
                        <a href="<?php echo $card['url']; ?>" class="text-decoration-none">
                            <div class="card h-100 p-3 border-0 bg-light rounded-3 shadow-sm border-start border-3 <?php echo $card['badge_class']; ?>" style="transition: all 0.2s;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="fs-3 text-secondary"><i class="bi <?php echo $card['icon']; ?>"></i></div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-1"><?php echo $card['title']; ?></h6>
                                        <p class="text-muted small mb-0" style="font-size:0.75rem;"><?php echo $card['desc']; ?></p>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($role === 'student'): ?>
        <!-- Mental Wellness Articles section -->
        <div class="card card-glass border-0 p-4 mb-4 shadow-sm fade-in-up">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-journals text-primary me-2"></i> Mental Wellness Articles</h5>
                <a href="articles.php" class="btn btn-outline-secondary btn-sm" style="border-radius:6px;">View All</a>
            </div>
            <div class="row g-3">
                <?php foreach ($dashboard_articles as $art): ?>
                    <div class="col-md-4">
                        <div class="card h-100 border-0 bg-light rounded-3 shadow-sm d-flex flex-column" style="overflow:hidden;">
                            <img src="../<?php echo h($art['image_path']); ?>" alt="Article Image" style="height:120px; object-fit:cover;">
                            <div class="p-3 d-flex flex-column flex-grow-1">
                                <h6 class="fw-bold text-dark mb-1 text-truncate" title="<?php echo h($art['title']); ?>"><?php echo h($art['title']); ?></h6>
                                <p class="text-muted small flex-grow-1 mb-2" style="font-size: 0.7rem; line-height:1.4;">
                                    <?php echo h(substr($art['summary'], 0, 75)) . '...'; ?>
                                </p>
                                <button type="button" class="btn btn-outline-primary btn-xs py-0.5 px-2 w-100" style="font-size: 0.75rem; border-radius: 6px;" data-bs-toggle="modal" data-bs-target="#dashArtModal<?php echo $art['id']; ?>">
                                    Read More
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Modal -->
                    <div class="modal fade" id="dashArtModal<?php echo $art['id']; ?>" tabindex="-1" aria-labelledby="dashArtLabel<?php echo $art['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content border-0 shadow-lg rounded-4">
                                <div class="modal-header border-bottom-0 pt-4 px-4">
                                    <h5 class="modal-title fw-bold" id="dashArtLabel<?php echo $art['id']; ?>"><?php echo h($art['title']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body px-4 py-3">
                                    <img src="../<?php echo h($art['image_path']); ?>" class="img-fluid rounded-3 mb-3 w-100" alt="Article" style="height:180px; object-fit:cover;">
                                    <p class="text-secondary small" style="line-height: 1.6; white-space: pre-line;">
                                        <?php echo h($art['content']); ?>
                                    </p>
                                </div>
                                <div class="modal-footer border-top-0 pb-4 px-4">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:8px;">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'student'): ?>
        <!-- Mental Wellness Tips -->
        <div class="card card-glass border-0 p-4 shadow-sm fade-in-up">
            <h5 class="fw-bold mb-3"><i class="bi bi-lightbulb text-warning me-2"></i> Healthy Minds Tips</h5>
            <div class="accordion" id="tipsAccordion">
                <div class="accordion-item border-0 bg-light rounded-3 mb-2 overflow-hidden">
                    <h2 class="accordion-header" id="tipHeading1">
                        <button class="accordion-button collapsed bg-white text-dark small" type="button" data-bs-toggle="collapse" data-bs-target="#tipCollapse1" aria-expanded="false" aria-controls="tipCollapse1">
                            <span class="me-2 text-primary"><i class="bi bi-moon-stars-fill"></i></span> Prioritize Quality Sleep
                        </button>
                    </h2>
                    <div id="tipCollapse1" class="accordion-collapse collapse" aria-labelledby="tipHeading1" data-bs-parent="#tipsAccordion">
                        <div class="accordion-body bg-white text-secondary small">
                            Aim for 7-9 hours of consistent sleep. Sleep is vital for cognitive function, stress reduction, and emotional regulation. Setting a screen-free winding down routine 30 minutes before bed helps release sleep hormones naturally.
                        </div>
                    </div>
                </div>

                <div class="accordion-item border-0 bg-light rounded-3 mb-2 overflow-hidden">
                    <h2 class="accordion-header" id="tipHeading2">
                        <button class="accordion-button collapsed bg-white text-dark small" type="button" data-bs-toggle="collapse" data-bs-target="#tipCollapse2" aria-expanded="false" aria-controls="tipCollapse2">
                            <span class="me-2 text-info"><i class="bi bi-wind"></i></span> Practice Box Breathing
                        </button>
                    </h2>
                    <div id="tipCollapse2" class="accordion-collapse collapse" aria-labelledby="tipHeading2" data-bs-parent="#tipsAccordion">
                        <div class="accordion-body bg-white text-secondary small">
                            Box Breathing lowers nervous stress. Inhale through your nose for 4 seconds, hold your breath for 4, exhale slowly for 4, and hold empty for 4. Repeating this 4 times alerts your nervous system to stay calm.
                        </div>
                    </div>
                </div>

                <div class="accordion-item border-0 bg-light rounded-3 mb-2 overflow-hidden">
                    <h2 class="accordion-header" id="tipHeading3">
                        <button class="accordion-button collapsed bg-white text-dark small" type="button" data-bs-toggle="collapse" data-bs-target="#tipCollapse3" aria-expanded="false" aria-controls="tipCollapse3">
                            <span class="me-2 text-danger"><i class="bi bi-phone-mute-fill"></i></span> Take a Digital Detox Break
                        </button>
                    </h2>
                    <div id="tipCollapse3" class="accordion-collapse collapse" aria-labelledby="tipHeading3" data-bs-parent="#tipsAccordion">
                        <div class="accordion-body bg-white text-secondary small">
                            Constant digital notifications trigger stress and comparison cycles. Dedicate at least 30 minutes a day to step away from all screens and do something physical, like walking outside, drinking water, or writing in your journal.
                        </div>
                    </div>
                </div>

                <div class="accordion-item border-0 bg-light rounded-3 overflow-hidden">
                    <h2 class="accordion-header" id="tipHeading4">
                        <button class="accordion-button collapsed bg-white text-dark small" type="button" data-bs-toggle="collapse" data-bs-target="#tipCollapse4" aria-expanded="false" aria-controls="tipCollapse4">
                            <span class="me-2 text-success"><i class="bi bi-droplet-fill"></i></span> Hydration &amp; Healthy Foods
                        </button>
                    </h2>
                    <div id="tipCollapse4" class="accordion-collapse collapse" aria-labelledby="tipHeading4" data-bs-parent="#tipsAccordion">
                        <div class="accordion-body bg-white text-secondary small">
                            Mild dehydration can lead to brain fog, fatigue, and mood swings. Keep a water flask near you during study sessions, and prefer whole grains, fruits, and omega-rich nuts to fuel your neurochemical wellness.
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Right Sidebar Column (Extra summaries) -->
    <div class="col-lg-4">
        <?php if (!empty($sidebar_panels_html)): ?>
            <?php echo $sidebar_panels_html; ?>
        <?php endif; ?>
    </div>
</div>
