<?php
// includes/events_db.php
require_once __DIR__ . '/../config/db.php';

/**
 * Ensures event management tables exist in the database and seeds initial data if missing.
 */
function init_event_tables($pdo) {
    try {
        // Ensure uploads/events directory and fallback banner image exist
        $events_dir = __DIR__ . '/../uploads/events';
        if (!file_exists($events_dir)) {
            @mkdir($events_dir, 0777, true);
        }
        $default_img = $events_dir . '/default_event.jpg';
        if (!file_exists($default_img)) {
            if (function_exists('imagecreatetruecolor')) {
                $im = @imagecreatetruecolor(800, 400);
                if ($im) {
                    $bg = imagecolorallocate($im, 79, 70, 229);
                    imagefilledrectangle($im, 0, 0, 800, 400, $bg);
                    @imagejpeg($im, $default_img, 90);
                    @imagedestroy($im);
                }
            }
        }

        // Create events table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                category VARCHAR(100) NOT NULL,
                description TEXT NOT NULL,
                banner_image VARCHAR(255) DEFAULT 'default_event.jpg',
                event_date DATE NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                venue VARCHAR(255) NOT NULL,
                speaker VARCHAR(150) NOT NULL,
                max_participants INT NOT NULL DEFAULT 50,
                registration_deadline DATETIME NOT NULL,
                status ENUM('Upcoming', 'Ongoing', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Upcoming',
                visibility ENUM('Public', 'Only Selected Students') NOT NULL DEFAULT 'Public',
                created_by INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");


        // Create event_target_students table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS event_target_students (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                student_id INT NOT NULL,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                UNIQUE KEY unique_event_target (event_id, student_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Create event_registrations table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS event_registrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                student_id INT NOT NULL,
                registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                attendance_status ENUM('Pending', 'Present', 'Absent') NOT NULL DEFAULT 'Pending',
                registration_status ENUM('Registered', 'Cancelled') NOT NULL DEFAULT 'Registered',
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                UNIQUE KEY unique_event_student_reg (event_id, student_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Create notifications table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                type VARCHAR(50) DEFAULT 'info',
                is_read TINYINT(1) DEFAULT 0,
                link_url VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Seed initial events if table is empty
        $count = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
        if ($count == 0) {
            $pdo->exec("
                INSERT INTO events (id, title, category, description, banner_image, event_date, start_time, end_time, venue, speaker, max_participants, registration_deadline, status, visibility, created_by) VALUES
                (1, 'Campus Mental Health Awareness Week 2026', 'Mental Health Awareness Week', 'Join us for a week-long series of interactive discussions, expert Q&A panels, and interactive wellness booths designed to break stigma around student mental health.', 'default_event.jpg', DATE_ADD(CURDATE(), INTERVAL 5 DAY), '09:30:00', '12:30:00', 'Main Auditorium & Student Center', 'Dr. Sarah Jenkins & Guest Panelists', 100, DATE_ADD(NOW(), INTERVAL 4 DAY), 'Upcoming', 'Public', 1),
                (2, 'Exams Stress Relief & Time Management Workshop', 'Stress Management Workshop', 'Learn practical cognitive reframing, study scheduling, and anti-burnout techniques to conquer final exam anxiety.', 'default_event.jpg', DATE_ADD(CURDATE(), INTERVAL 8 DAY), '14:00:00', '16:00:00', 'Seminar Hall B, Health Sciences Building', 'Dr. Michael Chen', 40, DATE_ADD(NOW(), INTERVAL 7 DAY), 'Upcoming', 'Public', 1),
                (3, 'Sunset Yoga & Deep Breathing Revival', 'Yoga Session', 'Experience outdoor grounding yoga designed for beginner and intermediate practitioners. Mats provided at venue.', 'default_event.jpg', DATE_ADD(CURDATE(), INTERVAL 12 DAY), '17:00:00', '18:30:00', 'Campus Central Lawn & Pavilion', 'Elena Rostova (Certified Yoga Instructor)', 30, DATE_ADD(NOW(), INTERVAL 11 DAY), 'Upcoming', 'Public', 1),
                (4, 'Mindfulness & Box Breathing Masterclass', 'Mindfulness Training', 'Master box breathing, 4-7-8 breathing, and body scan meditation techniques to reduce physiological stress during exams.', 'default_event.jpg', DATE_SUB(CURDATE(), INTERVAL 3 DAY), '10:00:00', '11:30:00', 'MindCare Conference Room 102', 'Dr. Sarah Jenkins', 25, DATE_SUB(NOW(), INTERVAL 4 DAY), 'Completed', 'Public', 1);
            ");

            // Seed initial registrations if student 1 and 2 exist
            $pdo->exec("
                INSERT IGNORE INTO event_registrations (event_id, student_id, registration_date, attendance_status, registration_status) VALUES
                (1, 1, DATE_SUB(NOW(), INTERVAL 2 DAY), 'Pending', 'Registered'),
                (1, 2, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Pending', 'Registered'),
                (2, 1, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Pending', 'Registered'),
                (4, 1, DATE_SUB(NOW(), INTERVAL 5 DAY), 'Present', 'Registered'),
                (4, 2, DATE_SUB(NOW(), INTERVAL 5 DAY), 'Absent', 'Registered');
            ");
        }
    } catch (\Exception $e) {
        error_log("init_event_tables error: " . $e->getMessage());
    }
}

// Auto-run schema check
init_event_tables($pdo);

/**
 * Returns standard event categories.
 */
function get_event_categories() {
    return [
        'Mental Health Awareness Week',
        'Stress Management Workshop',
        'Yoga Session',
        'Meditation Session',
        'Mindfulness Training',
        'Guest Talk',
        'Career Stress Seminar',
        'Group Counseling Session',
        'Emotional Wellness Workshop',
        'Happiness Challenge'
    ];
}

/**
 * Fetches all events with registration statistics and optional filtering.
 */
function get_all_events($pdo, $filters = [], $student_id = null) {
    $where = ["1=1"];
    $params = [];

    if (!empty($filters['search'])) {
        $where[] = "(e.title LIKE ? OR e.description LIKE ? OR e.speaker LIKE ? OR e.venue LIKE ?)";
        $term = '%' . trim($filters['search']) . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    if (!empty($filters['category'])) {
        $where[] = "e.category = ?";
        $params[] = $filters['category'];
    }

    if (!empty($filters['status'])) {
        $where[] = "e.status = ?";
        $params[] = $filters['status'];
    }

    if (!empty($filters['date_from'])) {
        $where[] = "e.event_date >= ?";
        $params[] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $where[] = "e.event_date <= ?";
        $params[] = $filters['date_to'];
    }

    // Filter by visibility for students
    if ($student_id !== null) {
        $where[] = "(e.visibility = 'Public' OR e.id IN (SELECT event_id FROM event_target_students WHERE student_id = ?))";
        $params[] = $student_id;
    }

    $whereClause = implode(" AND ", $where);

    $sql = "
        SELECT e.*, 
               (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.id AND er.registration_status = 'Registered') AS registered_count,
               (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.id AND er.registration_status = 'Registered' AND er.attendance_status = 'Present') AS present_count
        FROM events e
        WHERE {$whereClause}
        ORDER BY e.event_date ASC, e.start_time ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get details of a single event.
 */
function get_event_by_id($pdo, $event_id) {
    $stmt = $pdo->prepare("
        SELECT e.*, 
               (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.id AND er.registration_status = 'Registered') AS registered_count,
               (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.id AND er.registration_status = 'Registered' AND er.attendance_status = 'Present') AS present_count
        FROM events e
        WHERE e.id = ?
    ");
    $stmt->execute([$event_id]);
    return $stmt->fetch();
}

/**
 * Checks if student is registered for event.
 */
function get_student_event_registration($pdo, $event_id, $student_id) {
    $stmt = $pdo->prepare("SELECT * FROM event_registrations WHERE event_id = ? AND student_id = ?");
    $stmt->execute([$event_id, $student_id]);
    return $stmt->fetch();
}

/**
 * Registers student for an event with validation checks.
 */
function register_student_for_event($pdo, $event_id, $student_id) {
    $event = get_event_by_id($pdo, $event_id);
    if (!$event) {
        return ['success' => false, 'message' => 'Event not found.'];
    }

    if ($event['status'] === 'Cancelled') {
        return ['success' => false, 'message' => 'Registration is closed as this event has been cancelled.'];
    }

    // Check registration deadline
    if (strtotime($event['registration_deadline']) < time()) {
        return ['success' => false, 'message' => 'Registration Closed – Deadline Passed.'];
    }

    // Check available seats
    if ($event['registered_count'] >= $event['max_participants']) {
        return ['success' => false, 'message' => 'Registration Closed – Event Full.'];
    }

    // Check visibility requirement
    if ($event['visibility'] === 'Only Selected Students') {
        $targetStmt = $pdo->prepare("SELECT id FROM event_target_students WHERE event_id = ? AND student_id = ?");
        $targetStmt->execute([$event_id, $student_id]);
        if (!$targetStmt->fetch()) {
            return ['success' => false, 'message' => 'This event is restricted to selected students.'];
        }
    }

    $existing = get_student_event_registration($pdo, $event_id, $student_id);
    if ($existing) {
        if ($existing['registration_status'] === 'Registered') {
            return ['success' => false, 'message' => 'You are already registered for this event.'];
        } else {
            // Re-register
            $updateStmt = $pdo->prepare("
                UPDATE event_registrations 
                SET registration_status = 'Registered', registration_date = NOW(), attendance_status = 'Pending' 
                WHERE id = ?
            ");
            $updateStmt->execute([$existing['id']]);
        }
    } else {
        $insertStmt = $pdo->prepare("
            INSERT INTO event_registrations (event_id, student_id, registration_date, attendance_status, registration_status)
            VALUES (?, ?, NOW(), 'Pending', 'Registered')
        ");
        $insertStmt->execute([$event_id, $student_id]);
    }

    // Send confirmation in-app notification
    create_notification(
        $pdo,
        get_user_id_by_student($pdo, $student_id),
        "Event Registration Confirmed",
        "You have successfully registered for '{$event['title']}'. We look forward to seeing you at {$event['venue']} on " . date('M d, Y', strtotime($event['event_date'])) . ".",
        "success",
        "student/events.php"
    );

    // Send confirmation Email Notification to student's inbox
    try {
        require_once __DIR__ . '/mail.php';
        $studentStmt = $pdo->prepare("SELECT s.full_name, u.email FROM students s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
        $studentStmt->execute([$student_id]);
        $student_user = $studentStmt->fetch();

        if ($student_user && !empty($student_user['email'])) {
            $email_subject = "Registration Confirmed: " . $event['title'];
            $email_body = "
                <div style=\"font-family: 'Segoe UI', Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background-color: #ffffff;\">
                    <div style=\"background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: #ffffff; padding: 25px; text-align: center;\">
                        <h2 style=\"margin: 0; font-size: 24px; font-weight: 700;\">Registration Confirmed!</h2>
                        <p style=\"margin-top: 5px; opacity: 0.9;\">MindCare Hub Campus Wellness Module</p>
                    </div>
                    <div style=\"padding: 25px; color: #1e293b;\">
                        <p>Hello <strong>" . h($student_user['full_name']) . "</strong>,</p>
                        <p>Your registration for the campus mental wellness event has been successfully confirmed. Below are your event details:</p>
                        <div style=\"background-color: #f8fafc; border-left: 4px solid #4f46e5; padding: 15px; border-radius: 6px; margin: 20px 0;\">
                            <h3 style=\"margin-top: 0; color: #4f46e5;\">" . h($event['title']) . "</h3>
                            <p style=\"margin: 5px 0;\"><strong>Category:</strong> " . h($event['category']) . "</p>
                            <p style=\"margin: 5px 0;\"><strong>Date:</strong> " . date('l, F d, Y', strtotime($event['event_date'])) . "</p>
                            <p style=\"margin: 5px 0;\"><strong>Time:</strong> " . date('h:i A', strtotime($event['start_time'])) . " - " . date('h:i A', strtotime($event['end_time'])) . "</p>
                            <p style=\"margin: 5px 0;\"><strong>Venue:</strong> " . h($event['venue']) . "</p>
                            <p style=\"margin: 5px 0;\"><strong>Speaker:</strong> " . h($event['speaker']) . "</p>
                        </div>
                        <p>Log in to your student portal to view event details and manage your registrations.</p>
                    </div>
                    <div style=\"background-color: #f1f5f9; padding: 15px; text-align: center; font-size: 12px; color: #64748b;\">
                        MindCare Hub &bull; Student Mental Wellness System
                    </div>
                </div>
            ";
            send_mail($student_user['email'], $email_subject, $email_body);
        }
    } catch (\Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
    }

    return ['success' => true, 'message' => 'Successfully registered for ' . $event['title'] . '! Confirmation email sent.'];
}

/**
 * Cancels a student's registration before the deadline.
 */
function cancel_student_registration($pdo, $event_id, $student_id) {
    $event = get_event_by_id($pdo, $event_id);
    if (!$event) {
        return ['success' => false, 'message' => 'Event not found.'];
    }

    // Check deadline
    if (strtotime($event['registration_deadline']) < time()) {
        return ['success' => false, 'message' => 'Cancellation deadline has passed for this event.'];
    }

    $reg = get_student_event_registration($pdo, $event_id, $student_id);
    if (!$reg || $reg['registration_status'] !== 'Registered') {
        return ['success' => false, 'message' => 'You are not registered for this event.'];
    }

    $updateStmt = $pdo->prepare("
        UPDATE event_registrations 
        SET registration_status = 'Cancelled' 
        WHERE id = ?
    ");
    $updateStmt->execute([$reg['id']]);

    // Send notification
    create_notification(
        $pdo,
        get_user_id_by_student($pdo, $student_id),
        "Event Registration Cancelled",
        "Your registration for '{$event['title']}' has been cancelled.",
        "warning",
        "student/events.php"
    );

    return ['success' => true, 'message' => 'Your registration for ' . $event['title'] . ' has been cancelled.'];
}

/**
 * Gets user_id from student_id.
 */
function get_user_id_by_student($pdo, $student_id) {
    $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    return $stmt->fetchColumn();
}

/**
 * Gets registered events for a student.
 */
function get_student_registered_events($pdo, $student_id) {
    $stmt = $pdo->prepare("
        SELECT e.*, er.id as registration_id, er.registration_date, er.attendance_status, er.registration_status
        FROM event_registrations er
        JOIN events e ON er.event_id = e.id
        WHERE er.student_id = ? AND er.registration_status = 'Registered'
        ORDER BY e.event_date ASC, e.start_time ASC
    ");
    $stmt->execute([$student_id]);
    return $stmt->fetchAll();
}

/**
 * Gets list of students registered for an event (for Admin attendance/export).
 */
function get_event_registrations($pdo, $event_id) {
    $stmt = $pdo->prepare("
        SELECT er.*, s.id AS student_id, s.full_name, s.student_id_number, s.phone, u.email
        FROM event_registrations er
        JOIN students s ON er.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE er.event_id = ? AND er.registration_status = 'Registered'
        ORDER BY s.full_name ASC
    ");
    $stmt->execute([$event_id]);
    return $stmt->fetchAll();
}

/**
 * Saves batch attendance for an event.
 */
function save_event_attendance($pdo, $event_id, $attendance_map) {
    $event = get_event_by_id($pdo, $event_id);
    if (!$event) {
        return ['success' => false, 'message' => 'Event not found.'];
    }

    $stmt = $pdo->prepare("
        UPDATE event_registrations 
        SET attendance_status = ? 
        WHERE event_id = ? AND student_id = ? AND registration_status = 'Registered'
    ");

    $count = 0;
    foreach ($attendance_map as $student_id => $status) {
        if (in_array($status, ['Present', 'Absent', 'Pending'])) {
            $stmt->execute([$status, $event_id, $student_id]);
            $count++;
        }
    }

    // Auto update status to Completed if not already
    if ($event['status'] === 'Upcoming' || $event['status'] === 'Ongoing') {
        $updateEvent = $pdo->prepare("UPDATE events SET status = 'Completed' WHERE id = ?");
        $updateEvent->execute([$event_id]);
    }

    return ['success' => true, 'message' => "Successfully updated attendance records for {$count} students."];
}

/**
 * Creates in-app notification.
 */
function create_notification($pdo, $user_id, $title, $message, $type = 'info', $link_url = null) {
    if (!$user_id) return;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read, link_url)
            VALUES (?, ?, ?, ?, 0, ?)
        ");
        $stmt->execute([$user_id, $title, $message, $type, $link_url]);
    } catch (\Exception $e) {
        error_log("create_notification error: " . $e->getMessage());
    }
}

/**
 * Sends event notifications to targeted students when an event is created/updated/cancelled.
 */
function notify_students_for_event($pdo, $event_id, $action_type, $custom_msg = '') {
    $event = get_event_by_id($pdo, $event_id);
    if (!$event) return;

    if ($action_type === 'published') {
        // Notify all students or selected target students
        if ($event['visibility'] === 'Only Selected Students') {
            $stmt = $pdo->prepare("
                SELECT u.id AS user_id 
                FROM event_target_students ets 
                JOIN students s ON ets.student_id = s.id 
                JOIN users u ON s.user_id = u.id 
                WHERE ets.event_id = ?
            ");
            $stmt->execute([$event_id]);
        } else {
            $stmt = $pdo->query("SELECT user_id FROM students");
        }
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($users as $uid) {
            create_notification(
                $pdo,
                $uid,
                "New Event Published: " . $event['title'],
                "A new wellness event '{$event['title']}' has been scheduled for " . date('M d, Y', strtotime($event['event_date'])) . ". Register early to secure your seat!",
                "event",
                "student/events.php"
            );
        }
    } elseif ($action_type === 'updated' || $action_type === 'cancelled') {
        // Notify registered students
        $regs = get_event_registrations($pdo, $event_id);
        foreach ($regs as $reg) {
            $uid = get_user_id_by_student($pdo, $reg['student_id']);
            $title = ($action_type === 'cancelled') ? "Event Cancelled: " . $event['title'] : "Event Details Updated: " . $event['title'];
            $msg = ($action_type === 'cancelled') 
                ? "The event '{$event['title']}' scheduled for " . date('M d, Y', strtotime($event['event_date'])) . " has been cancelled. We apologize for any inconvenience." 
                : "The schedule or details for '{$event['title']}' have been updated. Please check the updated event details.";
            if ($custom_msg) $msg = $custom_msg;

            create_notification(
                $pdo,
                $uid,
                $title,
                $msg,
                ($action_type === 'cancelled') ? "warning" : "info",
                "student/events.php"
            );
        }
    }
}

/**
 * Checks and triggers 1-day and 1-hour automated reminders for registered students.
 */
function check_and_trigger_event_reminders($pdo) {
    try {
        // 1-Day Reminder: events happening tomorrow
        $tomorrowEvents = $pdo->query("
            SELECT e.* FROM events e 
            WHERE e.event_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) 
              AND e.status IN ('Upcoming', 'Ongoing')
        ")->fetchAll();

        foreach ($tomorrowEvents as $event) {
            $regs = get_event_registrations($pdo, $event['id']);
            foreach ($regs as $reg) {
                $uid = get_user_id_by_student($pdo, $reg['student_id']);
                // Check if already notified today
                $check = $pdo->prepare("
                    SELECT id FROM notifications 
                    WHERE user_id = ? AND title LIKE ? AND created_at >= CURDATE()
                ");
                $check->execute([$uid, "%Reminder: " . $event['title'] . "%"]);
                if (!$check->fetch()) {
                    create_notification(
                        $pdo,
                        $uid,
                        "1-Day Event Reminder: " . $event['title'],
                        "Reminder! '{$event['title']}' is happening tomorrow at " . date('h:i A', strtotime($event['start_time'])) . " in {$event['venue']}.",
                        "reminder",
                        "student/events.php"
                    );
                }
            }
        }
    } catch (\Exception $e) {
        // silent fallback
    }
}

/**
 * Fetches registered students across all events with multi-criteria filtering for Admin Exports.
 */
function get_filtered_event_registrations($pdo, $filters = []) {
    $where = ["er.registration_status = 'Registered'"];
    $params = [];

    if (!empty($filters['event_id'])) {
        $where[] = "er.event_id = ?";
        $params[] = intval($filters['event_id']);
    }

    if (!empty($filters['category'])) {
        $where[] = "e.category = ?";
        $params[] = $filters['category'];
    }

    if (!empty($filters['attendance_status'])) {
        $where[] = "er.attendance_status = ?";
        $params[] = $filters['attendance_status'];
    }

    if (!empty($filters['search'])) {
        $where[] = "(s.full_name LIKE ? OR s.student_id_number LIKE ? OR u.email LIKE ? OR e.title LIKE ?)";
        $term = '%' . trim($filters['search']) . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    if (!empty($filters['date_from'])) {
        $where[] = "e.event_date >= ?";
        $params[] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $where[] = "e.event_date <= ?";
        $params[] = $filters['date_to'];
    }

    $whereClause = implode(" AND ", $where);

    $sql = "
        SELECT er.*, s.full_name, s.student_id_number, s.phone, u.email,
               e.title AS event_title, e.category AS event_category, e.event_date, e.start_time, e.end_time, e.venue
        FROM event_registrations er
        JOIN students s ON er.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN events e ON er.event_id = e.id
        WHERE {$whereClause}
        ORDER BY e.event_date DESC, s.full_name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
?>

