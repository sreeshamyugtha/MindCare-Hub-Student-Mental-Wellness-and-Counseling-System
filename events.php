<?php
// student/events.php
$page_title = "Campus Wellness Events";
$active_nav = "events";

require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/events_db.php';

require_role('student');
$student_id = $_SESSION['student_id'];

// Handle POST Registration and Cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $event_id = intval($_POST['event_id'] ?? 0);

    if ($action === 'register') {
        $result = register_student_for_event($pdo, $event_id, $student_id);
        if ($result['success']) {
            set_flash('success', $result['message']);
        } else {
            set_flash('danger', $result['message']);
        }
        redirect('events.php');
    } elseif ($action === 'cancel') {
        $result = cancel_student_registration($pdo, $event_id, $student_id);
        if ($result['success']) {
            set_flash('warning', $result['message']);
        } else {
            set_flash('danger', $result['message']);
        }
        redirect('events.php?tab=my_events');
    }
}

$active_tab = $_GET['tab'] ?? 'browse';

// Filters
$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'category' => trim($_GET['category'] ?? ''),
    'date_from' => trim($_GET['date_from'] ?? ''),
    'date_to' => trim($_GET['date_to'] ?? '')
];

$all_events = get_all_events($pdo, $filters, $student_id);
$my_registered_events = get_student_registered_events($pdo, $student_id);

// Create mapping of student registrations
$registered_event_ids = [];
$attendance_history_map = [];
foreach ($my_registered_events as $mreg) {
    $registered_event_ids[] = $mreg['id'];
    $attendance_history_map[$mreg['id']] = [
        'attendance_status' => $mreg['attendance_status'],
        'registration_date' => $mreg['registration_date']
    ];
}

require_once '../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2 fade-in-up">
    <div>
        <h1 class="fw-bold text-dark mb-1"><i class="bi bi-calendar2-heart-fill text-primary me-2"></i> Campus Wellness Events</h1>
        <p class="text-muted mb-0">Discover workshops, mindfulness sessions, and mental health awareness seminars.</p>
    </div>
    <div>
        <a href="notifications.php" class="btn btn-outline-primary position-relative">
            <i class="bi bi-bell-fill me-1"></i> Reminders & Notifications
            <?php if ($unread_notifications_count > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    <?php echo $unread_notifications_count; ?>
                </span>
            <?php endif; ?>
        </a>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-pills mb-4 nav-justified bg-light p-1 rounded-3 shadow-sm fade-in-up" role="tablist">
    <li class="nav-item">
        <a class="nav-link fw-semibold <?php echo ($active_tab === 'browse') ? 'active' : ''; ?>" href="events.php?tab=browse">
            <i class="bi bi-grid-fill me-1"></i> Browse Events (<?php echo count($all_events); ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-semibold <?php echo ($active_tab === 'my_events') ? 'active' : ''; ?>" href="events.php?tab=my_events">
            <i class="bi bi-ticket-detailed-fill me-1"></i> My Registered Events (<?php echo count($my_registered_events); ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-semibold <?php echo ($active_tab === 'calendar') ? 'active' : ''; ?>" href="events.php?tab=calendar">
            <i class="bi bi-calendar-week-fill me-1"></i> Calendar View
        </a>
    </li>
</ul>

<?php if ($active_tab === 'browse'): ?>
    <!-- Filter Bar -->
    <div class="card card-glass border-0 shadow-sm mb-4 p-3 fade-in-up">
        <form action="events.php" method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="tab" value="browse">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search event, speaker, venue..." value="<?php echo h($filters['search']); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select">
                    <option value="">-- All Categories --</option>
                    <?php foreach (get_event_categories() as $cat): ?>
                        <option value="<?php echo h($cat); ?>" <?php echo ($filters['category'] === $cat) ? 'selected' : ''; ?>><?php echo h($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="date" name="date_from" class="form-control" value="<?php echo h($filters['date_from']); ?>" placeholder="Filter Date">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Search</button>
                <a href="events.php?tab=browse" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>

    <!-- Events Grid -->
    <div class="row g-4 fade-in-up">
        <?php if (empty($all_events)): ?>
            <div class="col-12 text-center py-5">
                <div class="p-5 bg-white rounded shadow-sm">
                    <i class="bi bi-calendar-x display-3 text-muted mb-3 d-block"></i>
                    <h4 class="fw-bold text-dark">No Events Found</h4>
                    <p class="text-muted">There are currently no events matching your filter parameters.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($all_events as $event): ?>
                <?php
                $is_registered = in_array($event['id'], $registered_event_ids);
                $available_seats = max(0, $event['max_participants'] - $event['registered_count']);
                $is_full = ($available_seats <= 0);
                $deadline_passed = (strtotime($event['registration_deadline']) < time());
                $event_passed = (strtotime($event['event_date']) < strtotime(date('Y-m-d')) || $event['status'] === 'Completed');
                $is_cancelled = ($event['status'] === 'Cancelled');
                $pct = ($event['max_participants'] > 0) ? round(($event['registered_count'] / $event['max_participants']) * 100) : 0;
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="event-card h-100 d-flex flex-column">
                        <div class="event-banner-wrapper">
                            <img src="../uploads/events/<?php echo h($event['banner_image']); ?>" class="event-banner-img" alt="<?php echo h($event['title']); ?>" onerror="this.onerror=null; this.style.display='none';">
                            <span class="event-category-badge"><?php echo h($event['category']); ?></span>
                            <?php if ($is_registered): ?>
                                <span class="event-status-pill badge bg-success"><i class="bi bi-check-circle-fill me-1"></i> Registered</span>
                            <?php elseif ($is_cancelled): ?>
                                <span class="event-status-pill badge bg-danger">Cancelled</span>
                            <?php elseif ($event_passed): ?>
                                <span class="event-status-pill badge bg-secondary">Completed</span>
                            <?php else: ?>
                                <span class="event-status-pill badge bg-primary"><?php echo h($event['status']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="p-3 d-flex flex-column flex-grow-1">
                            <h5 class="fw-bold text-dark mb-2 text-truncate" title="<?php echo h($event['title']); ?>">
                                <a href="event_details.php?id=<?php echo $event['id']; ?>" class="text-decoration-none text-dark"><?php echo h($event['title']); ?></a>
                            </h5>
                            <p class="text-muted small flex-grow-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                <?php echo h($event['description']); ?>
                            </p>

                            <div class="border-top pt-2 mt-2 text-muted small">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="bi bi-calendar3 text-primary"></i>
                                    <span class="fw-semibold text-dark"><?php echo date('D, M d, Y', strtotime($event['event_date'])); ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="bi bi-clock text-primary"></i>
                                    <span><?php echo date('h:i A', strtotime($event['start_time'])); ?> - <?php echo date('h:i A', strtotime($event['end_time'])); ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="bi bi-geo-alt-fill text-danger"></i>
                                    <span class="text-truncate"><?php echo h($event['venue']); ?></span>
                                </div>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <i class="bi bi-person-circle text-secondary"></i>
                                    <span class="text-truncate"><?php echo h($event['speaker']); ?></span>
                                </div>

                                <!-- Seat Indicator -->
                                <div class="mt-2">
                                    <div class="d-flex justify-content-between align-items-center small mb-1">
                                        <span class="fw-semibold">Seats Available</span>
                                        <span class="fw-bold <?php echo ($available_seats > 5) ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $available_seats; ?> / <?php echo $event['max_participants']; ?> seats left
                                        </span>
                                    </div>
                                    <div class="progress seat-progress-bar">
                                        <div class="progress-bar <?php echo ($pct >= 90) ? 'bg-danger' : 'bg-primary'; ?>" role="progressbar" style="width: <?php echo $pct; ?>%;"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Registration Buttons -->
                            <div class="mt-3 pt-2 border-top d-flex gap-2 align-items-center justify-content-between">
                                <a href="event_details.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-info-circle me-1"></i> Details
                                </a>

                                <?php if ($is_registered): ?>
                                    <button class="btn btn-sm btn-success fw-semibold" disabled>
                                        <i class="bi bi-check-circle-fill me-1"></i> Registered
                                    </button>
                                <?php elseif ($is_cancelled): ?>
                                    <button class="btn btn-sm btn-secondary" disabled>Cancelled</button>
                                <?php elseif ($event_passed): ?>
                                    <button class="btn btn-sm btn-secondary" disabled>Event Ended</button>
                                <?php elseif ($deadline_passed): ?>
                                    <button class="btn btn-sm btn-outline-danger disabled" disabled>Registration Closed – Deadline Passed</button>
                                <?php elseif ($is_full): ?>
                                    <button class="btn btn-sm btn-outline-danger disabled" disabled>Registration Closed – Event Full</button>
                                <?php else: ?>
                                    <form action="events.php" method="POST">
                                        <input type="hidden" name="action" value="register">
                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-primary-custom fw-semibold">
                                            <i class="bi bi-check2-circle me-1"></i> One-Click Register
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php elseif ($active_tab === 'my_events'): ?>
    <!-- My Registered Events View -->
    <div class="card card-glass border-0 shadow-sm p-4 fade-in-up">
        <h4 class="fw-bold text-dark mb-3"><i class="bi bi-calendar-check-fill text-primary me-2"></i> My Event Registrations</h4>

        <?php if (empty($my_registered_events)): ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-x display-3 text-muted mb-3 d-block"></i>
                <h5 class="fw-bold text-dark">You have not registered for any events yet.</h5>
                <p class="text-muted">Browse our upcoming campus mental wellness events and register with one click!</p>
                <a href="events.php?tab=browse" class="btn btn-primary-custom mt-2"><i class="bi bi-grid-fill me-1"></i> Browse Upcoming Events</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($my_registered_events as $mreg): ?>
                    <?php
                    $deadline_passed = (strtotime($mreg['registration_deadline']) < time());
                    $att_status = $mreg['attendance_status'];
                    $att_badge = 'bg-secondary';
                    if ($att_status === 'Present') $att_badge = 'bg-success';
                    if ($att_status === 'Absent') $att_badge = 'bg-danger';
                    ?>
                    <div class="col-lg-6">
                        <div class="event-ticket-pass p-4 d-flex flex-column h-100 shadow-sm">
                            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                                <span class="badge bg-primary px-3 py-1"><?php echo h($mreg['category']); ?></span>
                                <span class="small text-muted">Registered on <?php echo date('M d, Y', strtotime($mreg['registration_date'])); ?></span>
                            </div>

                            <div class="row align-items-center flex-grow-1">
                                <div class="col-md-4 text-center mb-3 mb-md-0">
                                    <img src="../uploads/events/<?php echo h($mreg['banner_image']); ?>" class="img-fluid rounded shadow-sm" style="max-height: 110px; object-fit: cover;" onerror="this.onerror=null; this.style.display='none';">
                                </div>
                                <div class="col-md-8">
                                    <h5 class="fw-bold text-dark mb-2"><?php echo h($mreg['title']); ?></h5>
                                    <div class="small text-muted mb-1"><i class="bi bi-calendar-check text-primary me-1"></i> <?php echo date('D, M d, Y', strtotime($mreg['event_date'])); ?></div>
                                    <div class="small text-muted mb-1"><i class="bi bi-clock text-primary me-1"></i> <?php echo date('h:i A', strtotime($mreg['start_time'])); ?> - <?php echo date('h:i A', strtotime($mreg['end_time'])); ?></div>
                                    <div class="small text-muted mb-2"><i class="bi bi-geo-alt-fill text-danger me-1"></i> <?php echo h($mreg['venue']); ?></div>
                                </div>
                            </div>

                            <div class="border-top pt-3 mt-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <div>
                                    <span class="small text-muted d-block">Attendance Status:</span>
                                    <span class="badge <?php echo $att_badge; ?> px-3 py-1"><?php echo h($att_status); ?></span>
                                </div>

                                <div class="d-flex gap-2">
                                    <?php if (!$deadline_passed && $mreg['status'] !== 'Completed' && $mreg['status'] !== 'Cancelled'): ?>
                                        <form action="events.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel your registration for this event?');">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="event_id" value="<?php echo $mreg['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-x-circle me-1"></i> Cancel Registration
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light border text-muted" disabled title="Cancellation deadline passed">Cancel Closed</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($active_tab === 'calendar'): ?>
    <!-- Calendar View -->
    <div class="card card-glass border-0 shadow-sm p-4 fade-in-up">
        <h4 class="fw-bold text-dark mb-3"><i class="bi bi-calendar3 text-primary me-2"></i> Wellness Event Schedule Calendar</h4>

        <?php
        $month = intval(date('n'));
        $year = intval(date('Y'));
        $first_day = mktime(0, 0, 0, $month, 1, $year);
        $title_month = date('F Y', $first_day);
        $days_in_month = date('t', $first_day);
        $day_of_week = date('w', $first_day);
        ?>

        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="fw-bold text-dark mb-0"><?php echo $title_month; ?></h5>
            <span class="badge bg-primary">Current Semester Schedule</span>
        </div>

        <div class="event-calendar-grid mb-2 text-center fw-bold text-muted small">
            <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
        </div>

        <div class="event-calendar-grid">
            <?php for ($i = 0; $i < $day_of_week; $i++): ?>
                <div class="event-calendar-day other-month"></div>
            <?php endfor; ?>

            <?php for ($day = 1; $day <= $days_in_month; $day++): ?>
                <?php
                $current_date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $is_today = ($current_date_str === date('Y-m-d'));
                $day_events = array_filter($all_events, function($e) use ($current_date_str) {
                    return $e['event_date'] === $current_date_str;
                });
                ?>
                <div class="event-calendar-day <?php echo $is_today ? 'border-primary border-2 bg-light' : ''; ?>">
                    <div class="fw-bold small <?php echo $is_today ? 'text-primary' : 'text-dark'; ?>"><?php echo $day; ?></div>
                    <?php foreach ($day_events as $ev): ?>
                        <a href="event_details.php?id=<?php echo $ev['id']; ?>" class="event-calendar-item bg-primary text-white text-decoration-none" title="<?php echo h($ev['title']); ?>">
                            <?php echo h($ev['title']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
