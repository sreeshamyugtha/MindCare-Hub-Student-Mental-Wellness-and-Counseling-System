<?php
// student/event_details.php
$page_title = "Event Details";
$active_nav = "events";

require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/events_db.php';

require_role('student');
$student_id = $_SESSION['student_id'];

$event_id = intval($_GET['id'] ?? 0);
$event = get_event_by_id($pdo, $event_id);

if (!$event) {
    set_flash('danger', 'Event not found.');
    redirect('events.php');
}

// Check registration status
$my_reg = get_student_event_registration($pdo, $event_id, $student_id);
$is_registered = ($my_reg && $my_reg['registration_status'] === 'Registered');

$available_seats = max(0, $event['max_participants'] - $event['registered_count']);
$is_full = ($available_seats <= 0);
$deadline_passed = (strtotime($event['registration_deadline']) < time());
$event_passed = (strtotime($event['event_date']) < strtotime(date('Y-m-d')) || $event['status'] === 'Completed');
$is_cancelled = ($event['status'] === 'Cancelled');
$pct = ($event['max_participants'] > 0) ? round(($event['registered_count'] / $event['max_participants']) * 100) : 0;

require_once '../includes/header.php';
?>

<div class="mb-4 fade-in-up">
    <a href="events.php" class="btn btn-outline-secondary btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i> Back to All Events</a>
    <div class="card card-glass border-0 shadow-lg overflow-hidden">
        <div class="position-relative" style="height: 320px; overflow: hidden; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);">
            <img src="../uploads/events/<?php echo h($event['banner_image']); ?>" class="w-100 h-100" style="object-fit: cover;" alt="<?php echo h($event['title']); ?>" onerror="this.onerror=null; this.style.display='none';">
            <div class="position-absolute bottom-0 start-0 w-100 p-4" style="background: linear-gradient(0deg, rgba(15,23,42,0.9) 0%, rgba(15,23,42,0) 100%); text-shadow: 0 2px 4px rgba(0,0,0,0.5);">
                <span class="badge bg-primary px-3 py-2 mb-2 fs-6"><?php echo h($event['category']); ?></span>
                <h2 class="fw-bold text-white mb-1"><?php echo h($event['title']); ?></h2>
                <div class="text-white-50"><i class="bi bi-person-badge me-1"></i> Speaker/Trainer: <span class="text-white fw-semibold"><?php echo h($event['speaker']); ?></span></div>
            </div>
        </div>

        <div class="card-body p-4 p-md-5">
            <div class="row g-4">
                <div class="col-lg-8">
                    <h4 class="fw-bold text-dark mb-3">About This Event</h4>
                    <p class="text-muted leading-relaxed" style="white-space: pre-line; font-size: 1.05rem;">
                        <?php echo h($event['description']); ?>
                    </p>

                    <hr class="my-4">

                    <h5 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle text-primary me-2"></i> Event Highlights & Schedule</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded d-flex align-items-center gap-3">
                                <div class="fs-2 text-primary"><i class="bi bi-calendar-event"></i></div>
                                <div>
                                    <span class="text-muted small d-block">Date</span>
                                    <span class="fw-bold text-dark"><?php echo date('F d, Y (l)', strtotime($event['event_date'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded d-flex align-items-center gap-3">
                                <div class="fs-2 text-primary"><i class="bi bi-clock-history"></i></div>
                                <div>
                                    <span class="text-muted small d-block">Time</span>
                                    <span class="fw-bold text-dark"><?php echo date('h:i A', strtotime($event['start_time'])); ?> - <?php echo date('h:i A', strtotime($event['end_time'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded d-flex align-items-center gap-3">
                                <div class="fs-2 text-danger"><i class="bi bi-geo-alt-fill"></i></div>
                                <div>
                                    <span class="text-muted small d-block">Venue Location</span>
                                    <span class="fw-bold text-dark"><?php echo h($event['venue']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded d-flex align-items-center gap-3">
                                <div class="fs-2 text-warning"><i class="bi bi-hourglass-split"></i></div>
                                <div>
                                    <span class="text-muted small d-block">Registration Deadline</span>
                                    <span class="fw-bold text-dark"><?php echo date('M d, Y h:i A', strtotime($event['registration_deadline'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Registration Card Sidebar -->
                <div class="col-lg-4">
                    <div class="card border-0 bg-light p-4 rounded-3 shadow-sm">
                        <h5 class="fw-bold text-dark mb-3 border-bottom pb-2">Seat Availability</h5>

                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted">Registered Students</span>
                            <span class="fw-bold text-dark fs-5"><?php echo $event['registered_count']; ?> / <?php echo $event['max_participants']; ?></span>
                        </div>

                        <div class="progress seat-progress-bar mb-3" style="height: 10px;">
                            <div class="progress-bar <?php echo ($pct >= 90) ? 'bg-danger' : 'bg-primary'; ?>" role="progressbar" style="width: <?php echo $pct; ?>%;"></div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-4 small">
                            <span class="text-muted">Seats Remaining:</span>
                            <span class="fw-bold <?php echo ($available_seats > 5) ? 'text-success' : 'text-danger'; ?> fs-6">
                                <?php echo $available_seats; ?> Seats
                            </span>
                        </div>

                        <!-- Status Action Box -->
                        <?php if ($is_registered): ?>
                            <div class="alert alert-success text-center border-0 p-3 mb-3">
                                <i class="bi bi-check-circle-fill fs-3 d-block mb-1"></i>
                                <span class="fw-bold d-block">You Are Registered!</span>
                                <span class="small">Seat reserved for this session.</span>
                            </div>

                            <?php if (!$deadline_passed && !$event_passed && !$is_cancelled): ?>
                                <form action="events.php" method="POST" onsubmit="return confirm('Cancel registration for this event?');">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                    <button type="submit" class="btn btn-outline-danger w-100 py-2">
                                        <i class="bi bi-x-circle me-1"></i> Cancel Registration
                                    </button>
                                </form>
                            <?php endif; ?>

                        <?php elseif ($is_cancelled): ?>
                            <div class="alert alert-danger text-center border-0 p-3">
                                <i class="bi bi-exclamation-triangle-fill fs-3 d-block mb-1"></i>
                                <span class="fw-bold">Event Cancelled</span>
                            </div>
                        <?php elseif ($event_passed): ?>
                            <div class="alert alert-secondary text-center border-0 p-3">
                                <i class="bi bi-clock-history fs-3 d-block mb-1"></i>
                                <span class="fw-bold">Event Has Concluded</span>
                            </div>
                        <?php elseif ($deadline_passed): ?>
                            <div class="alert alert-warning text-center border-0 p-3">
                                <i class="bi bi-calendar-x fs-3 d-block mb-1"></i>
                                <span class="fw-bold d-block">Registration Closed</span>
                                <span class="small">Deadline Passed</span>
                            </div>
                        <?php elseif ($is_full): ?>
                            <div class="alert alert-danger text-center border-0 p-3">
                                <i class="bi bi-dash-circle-fill fs-3 d-block mb-1"></i>
                                <span class="fw-bold d-block">Registration Closed</span>
                                <span class="small">Event Full</span>
                            </div>
                        <?php else: ?>
                            <form action="events.php" method="POST">
                                <input type="hidden" name="action" value="register">
                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                <button type="submit" class="btn btn-primary-custom w-100 py-3 fw-bold fs-6 shadow-sm">
                                    <i class="bi bi-check2-circle me-1"></i> One-Click Register Now
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
