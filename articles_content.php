<?php
// includes/articles_content.php
require_once __DIR__ . '/../config/db.php';

// Fetch articles from the database
$stmt = $pdo->query("SELECT * FROM articles ORDER BY created_at DESC");
$articles = $stmt->fetchAll();
?>

<div class="row mb-4 fade-in-up">
    <div class="col-12">
        <h1 class="fw-bold">Mental Wellness Articles</h1>
        <p class="text-muted">Explore our curated collection of expert guides, therapeutic insights, and wellness tips.</p>
    </div>
</div>

<div class="row g-4 mb-5 fade-in-up" style="animation-delay: 0.1s;">
    <?php if (count($articles) > 0): ?>
        <?php foreach ($articles as $art): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card card-glass article-card border-0 h-100 shadow-sm">
                    <div class="article-img-container">
                        <img src="<?php echo $base_url . h($art['image_path']); ?>" class="article-img" alt="<?php echo h($art['title']); ?>">
                    </div>
                    <div class="card-body d-flex flex-column p-4">
                        <h5 class="fw-bold text-dark mb-2"><?php echo h($art['title']); ?></h5>
                        <p class="text-secondary small flex-grow-1"><?php echo h($art['summary']); ?></p>
                        <button type="button" class="btn btn-primary-custom btn-sm w-100 mt-3" data-bs-toggle="modal" data-bs-target="#articleModal<?php echo $art['id']; ?>">
                            <i class="bi bi-book-half me-1"></i> Read Article
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal for Article -->
            <div class="modal fade" id="articleModal<?php echo $art['id']; ?>" tabindex="-1" aria-labelledby="articleModalLabel<?php echo $art['id']; ?>" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <div class="modal-header border-bottom-0 pt-4 px-4 pb-2">
                            <h5 class="modal-title fw-bold" id="articleModalLabel<?php echo $art['id']; ?>"><?php echo h($art['title']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body px-4 py-3">
                            <img src="<?php echo $base_url . h($art['image_path']); ?>" class="img-fluid rounded-3 mb-3 w-100" alt="<?php echo h($art['title']); ?>" style="height:220px; object-fit:cover;">
                            <p class="text-secondary" style="line-height: 1.6; white-space: pre-line;">
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
    <?php else: ?>
        <div class="col-12 text-center py-5">
            <span class="display-3 text-muted"><i class="bi bi-book"></i></span>
            <p class="text-muted mt-3">No articles are currently available.</p>
        </div>
    <?php endif; ?>
</div>
