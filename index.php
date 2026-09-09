<?php
// index.php
$page_title = "Welcome to MindCare Hub";

// 20 predefined wellness quotes
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

require_once 'includes/header.php';
?>

<!-- Hero Section -->
<div class="row align-items-center py-5 hero-section rounded-4 px-4 px-md-5 mb-5 shadow-sm">
    <div class="col-lg-8 mx-auto text-center mb-4 mb-lg-0">
        <span class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill fw-semibold mb-3">Prioritize Your Mental Health</span>
        <h1 class="display-4 fw-bold mb-3" style="color: var(--dark);">Your Safe Space for <br><span style="color: var(--primary);">Mental Wellness</span></h1>
        <p class="lead text-muted mb-4" style="max-width: 600px; margin: 0 auto;">
            MindCare Hub helps you track your daily mood, assess your stress levels standardly, keep a private therapeutic journal, and directly schedule appointments with certified campus counselors.
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?php echo $_SESSION['role']; ?>/dashboard.php" class="btn btn-primary-custom btn-lg shadow-sm">
                    <i class="bi bi-speedometer2 me-2"></i> Go to Dashboard
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary-custom btn-lg shadow-sm">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Sign In to Account
                </a>
                <a href="register.php" class="btn btn-outline-secondary btn-lg" style="border-width: 2px; border-radius: 8px;">
                    <i class="bi bi-person-plus me-2"></i> Register as Student
                </a>
            <?php endif; ?>
        </div>
    </div>

        </div>


<!-- Core Features Section -->
<div class="row text-center mb-5">
    <div class="col-12 mb-4">
        <h2 class="fw-bold">How MindCare Hub Empowers You</h2>
        <p class="text-muted">A comprehensive, secure dashboard designed exclusively for college students and academic advisors.</p>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card card-glass h-100 p-4">
            <div class="text-success fs-1 mb-3"><i class="bi bi-calendar-heart"></i></div>
            <h5 class="fw-bold">Daily Mood Log</h5>
            <p class="text-muted small mb-0">Log your mood status daily to visualize long-term trends and identify emotional triggers.</p>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card card-glass h-100 p-4">
            <div class="text-primary fs-1 mb-3"><i class="bi bi-clipboard2-pulse"></i></div>
            <h5 class="fw-bold">Stress Diagnosis</h5>
            <p class="text-muted small mb-0">Complete a 10-question scientifically accepted questionnaire (PSS-10) to compute stress levels.</p>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card card-glass h-100 p-4">
            <div class="text-secondary fs-1 mb-3"><i class="bi bi-journals"></i></div>
            <h5 class="fw-bold">Private Journals</h5>
            <p class="text-muted small mb-0">Write self-reflective entries to release feelings. Share them securely with your counselor when needed.</p>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card card-glass h-100 p-4">
            <div class="text-danger fs-1 mb-3"><i class="bi bi-people"></i></div>
            <h5 class="fw-bold">Direct Counseling</h5>
            <p class="text-muted small mb-0">Book direct appointments with assigned advisors and review counselor recommendations securely.</p>
        </div>
    </div>
</div>

<!-- Daily Motivation Quote -->
<div class="row mb-5 fade-in-up">
    <div class="col-12">
        <div class="card quote-card border-0 p-4 shadow-sm">
            <div class="card-body py-3 text-center">
                <span class="text-primary fs-3 d-block mb-2"><i class="bi bi-quote"></i></span>
                <h5 class="fw-bold mb-2">Daily Motivation</h5>
                <p class="fs-5 text-secondary mb-0" style="font-style: italic; max-width: 800px; margin: 0 auto;">
                    "<?php echo h($random_quote); ?>"
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Carousel Section -->
<div class="row mb-5 fade-in-up">
    <div class="col-12">
        <div class="card card-glass wellness-focus-card border-0 shadow-sm">
            <h3 class="wellness-focus-title"><i class="bi bi-images text-secondary me-2"></i> Our Wellness Focus</h3>
            <div id="wellnessCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel">
                <div class="carousel-indicators">
                    <button type="button" data-bs-target="#wellnessCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                    <button type="button" data-bs-target="#wellnessCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
                    <button type="button" data-bs-target="#wellnessCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
                </div>
                <div class="carousel-inner">
                    <div class="carousel-item active" data-bs-interval="4000">
                        <img src="img/meditation.png" class="wellness-carousel-img" alt="Mindfulness Meditation">
                        <div class="carousel-caption wellness-carousel-caption d-none d-md-block">
                            <h5 class="fw-bold">Mindfulness &amp; Mental Wellness</h5>
                            <p class="mb-0">Take control of your mental wellness. Practice mindfulness, log your moods, and trace emotional triggers.</p>
                        </div>
                    </div>
                    <div class="carousel-item" data-bs-interval="4000">
                        <img src="img/counseling.png" class="wellness-carousel-img" alt="Student Counseling">
                        <div class="carousel-caption wellness-carousel-caption d-none d-md-block">
                            <h5 class="fw-bold">Professional Counseling Support</h5>
                            <p class="mb-0">Connect with certified advisors. Schedule sessions and receive personal guidance without any stigma.</p>
                        </div>
                    </div>
                    <div class="carousel-item" data-bs-interval="4000">
                        <img src="img/journaling.png" class="wellness-carousel-img" alt="Wellness Journaling">
                        <div class="carousel-caption wellness-carousel-caption d-none d-md-block">
                            <h5 class="fw-bold">Self-Reflective Journaling</h5>
                            <p class="mb-0">Write self-reflective entries to release feelings. Share them securely with your counselor when needed.</p>
                        </div>
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#wellnessCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#wellnessCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Mental Wellness Articles -->
<div class="row mb-5 fade-in-up">
    <div class="col-12 mb-4 text-center">
        <h2 class="fw-bold">Mental Wellness Articles</h2>
        <p class="text-muted">Curated articles to help you navigate college life, emotional well-being, and personal growth.</p>
    </div>
    
    <!-- Article 1 -->
    <div class="col-md-4 mb-4">
        <div class="card card-glass article-card border-0 h-100 shadow-sm">
            <div class="article-img-container">
                <img src="img/burnout.png" class="article-img" alt="Academic Burnout">
            </div>
            <div class="card-body d-flex flex-column p-4">
                <h5 class="fw-bold text-dark mb-2">Understanding Academic Burnout</h5>
                <p class="text-secondary small flex-grow-1">Learn how to recognize the early signs of study fatigue and explore practical strategies to restore balance.</p>
                <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-3" data-bs-toggle="modal" data-bs-target="#article1Modal">
                    <i class="bi bi-book me-1"></i> Read More
                </button>
            </div>
        </div>
    </div>

    <!-- Article 2 -->
    <div class="col-md-4 mb-4">
        <div class="card card-glass article-card border-0 h-100 shadow-sm">
            <div class="article-img-container">
                <img src="img/article_journaling.png" class="article-img" alt="Journal Reflection">
            </div>
            <div class="card-body d-flex flex-column p-4">
                <h5 class="fw-bold text-dark mb-2">The Power of Daily Journaling</h5>
                <p class="text-secondary small flex-grow-1">Explore how keeping a private diary helps sort through complex emotions and reduces psychological stress.</p>
                <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-3" data-bs-toggle="modal" data-bs-target="#article2Modal">
                    <i class="bi bi-book me-1"></i> Read More
                </button>
            </div>
        </div>
    </div>

    <!-- Article 3 -->
    <div class="col-md-4 mb-4">
        <div class="card card-glass article-card border-0 h-100 shadow-sm">
            <div class="article-img-container">
                <img src="img/article_mindfulness.png" class="article-img" alt="Mindfulness Nature">
            </div>
            <div class="card-body d-flex flex-column p-4">
                <h5 class="fw-bold text-dark mb-2">Mindfulness &amp; Breathing Exercises</h5>
                <p class="text-secondary small flex-grow-1">Unlock research-backed, simple breathing techniques designed to calm your central nervous system instantly.</p>
                <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-3" data-bs-toggle="modal" data-bs-target="#article3Modal">
                    <i class="bi bi-book me-1"></i> Read More
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Article Modals -->
<!-- Modal 1 -->
<div class="modal fade" id="article1Modal" tabindex="-1" aria-labelledby="article1ModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="article1ModalLabel">Understanding Academic Burnout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <img src="img/burnout.png" class="img-fluid rounded-3 mb-3 w-100" alt="Burnout" style="height:200px; object-fit:cover;">
                <p class="text-secondary" style="line-height: 1.6;">
                    Academic burnout is a state of emotional, physical, and mental exhaustion caused by excessive and prolonged study demands. It occurs when students feel overwhelmed, emotionally drained, and unable to meet constant expectations.
                    <br><br>
                    <strong>Common signs of burnout include:</strong>
                    <br>
                    • Chronic fatigue and lack of energy to attend classes.
                    <br>
                    • Increased mental distance from studies, or feelings of negativism or cynicism.
                    <br>
                    • Reduced academic efficiency and slipping grades.
                    <br><br>
                    <strong>Preventative strategies:</strong>
                    <br>
                    To beat burnout, prioritize time management by establishing clear boundaries between academic work and relaxation. Sleep at least 7-8 hours daily, eat nutritious meals, and exercise regularly. Don't hesitate to reach out to campus counselors for guidance.
                </p>
            </div>
            <div class="modal-footer border-top-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:8px;">Close Article</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2 -->
<div class="modal fade" id="article2Modal" tabindex="-1" aria-labelledby="article2ModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="article2ModalLabel">The Power of Daily Journaling</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <img src="img/article_journaling.png" class="img-fluid rounded-3 mb-3 w-100" alt="Journaling" style="height:200px; object-fit:cover;">
                <p class="text-secondary" style="line-height: 1.6;">
                    Journaling is one of the most effective and affordable ways to improve mental clarity. Writing down your feelings helps you process emotions in a safe, non-judgmental space.
                    <br><br>
                    <strong>How journaling heals the mind:</strong>
                    <br>
                    • Clarifies thoughts and feelings, helping you identify what causes anxiety or depression.
                    <br>
                    • Tracks day-to-day triggers, enabling you to recognize patterns in your mood and behaviors.
                    <br>
                    • Promotes positive self-talk and helps reframe negative narratives.
                    <br><br>
                    <strong>Getting started:</strong>
                    <br>
                    Start with just 5-10 minutes a day. Write about your feelings, describe a significant conversation, or write down list items you're grateful for. You can choose to keep this private or share extracts with your counselor.
                </p>
            </div>
            <div class="modal-footer border-top-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:8px;">Close Article</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 3 -->
<div class="modal fade" id="article3Modal" tabindex="-1" aria-labelledby="article3ModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="article3ModalLabel">Mindfulness &amp; Breathing Exercises</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <img src="img/article_mindfulness.png" class="img-fluid rounded-3 mb-3 w-100" alt="Mindfulness" style="height:200px; object-fit:cover;">
                <p class="text-secondary" style="line-height: 1.6;">
                    Mindfulness is the practice of drawing your full attention to the present moment without judgment. Combined with deep breathing, it acts as a physiological brake on stress.
                    <br><br>
                    <strong>Simple techniques you can practice anywhere:</strong>
                    <br>
                    • <strong>Box Breathing (4-4-4-4):</strong> Inhale for 4 seconds, hold for 4 seconds, exhale for 4 seconds, and hold empty for 4 seconds. Repeat 4 times.
                    <br>
                    • <strong>4-7-8 Technique:</strong> Inhale for 4 seconds, hold for 7 seconds, exhale slowly for 8 seconds. This acts as a natural tranquilizer for the nervous system.
                    <br>
                    • <strong>5-4-3-2-1 Grounding:</strong> Identify 5 things you can see, 4 you can touch, 3 you can hear, 2 you can smell, and 1 you can taste.
                    <br><br>
                    Regular practice lowers heart rate, controls anxiety, and increases focus before major exams.
                </p>
            </div>
            <div class="modal-footer border-top-0 pb-4 px-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius:8px;">Close Article</button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
