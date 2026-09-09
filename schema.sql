-- MindCare Hub - Database Schema and Seed Data
-- Create Database
CREATE DATABASE IF NOT EXISTS mindcare_clgg;
USE mindcare_clgg;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'counselor', 'student') NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Counselors Table
CREATE TABLE IF NOT EXISTS counselors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    specialization VARCHAR(150) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    photo VARCHAR(255) DEFAULT 'default.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Students Table
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    student_id_number VARCHAR(50) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    counselor_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (counselor_id) REFERENCES counselors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Moods Table
CREATE TABLE IF NOT EXISTS moods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    mood_date DATE NOT NULL,
    mood_score ENUM('happy', 'neutral', 'sad', 'stressed') NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_student_mood_date (student_id, mood_date),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Assessments Table (PSS-10 Stress Questionnaire)
CREATE TABLE IF NOT EXISTS assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    score INT NOT NULL,
    stress_level VARCHAR(50) NOT NULL,
    q1 INT NOT NULL,
    q2 INT NOT NULL,
    q3 INT NOT NULL,
    q4 INT NOT NULL,
    q5 INT NOT NULL,
    q6 INT NOT NULL,
    q7 INT NOT NULL,
    q8 INT NOT NULL,
    q9 INT NOT NULL,
    q10 INT NOT NULL,
    taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Journals Table
CREATE TABLE IF NOT EXISTS journals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    journal_date DATE DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    mood VARCHAR(30) DEFAULT NULL,
    gratitude_points TEXT DEFAULT NULL,
    word_count INT NOT NULL DEFAULT 0,
    positive_word_count INT NOT NULL DEFAULT 0,
    favorite TINYINT(1) NOT NULL DEFAULT 0,
    privacy VARCHAR(40) NOT NULL DEFAULT 'private',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_student_journal_date (student_id, journal_date),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Appointments Table
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    counselor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'rescheduled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (counselor_id) REFERENCES counselors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Counseling Notes Table
CREATE TABLE IF NOT EXISTS counseling_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    counselor_id INT NOT NULL,
    appointment_id INT DEFAULT NULL,
    notes TEXT NOT NULL,
    visible_to_student TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (counselor_id) REFERENCES counselors(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Articles Table
CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    summary TEXT NOT NULL,
    content TEXT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================================================================
-- SEED DATA
-- =========================================================================

-- Insert Users
-- passwords are: admin -> admin123, others -> password123
INSERT INTO users (id, username, password, role, email) VALUES
(1, 'admin', '$2y$10$KTqaTdMgo5C7Mart57yVpub3Y05ne8aAYFxg3nV/E7ujWLapnez0S', 'admin', 'admin@mindcare.edu'),
(2, 'drjenkins', '$2y$10$SNDK0FSxwsed2LwsQ/a9HOSeIW5/t/ch8LkrV6DC3icGt1FEmbEJW', 'counselor', 's.jenkins@mindcare.edu'),
(3, 'drchen', '$2y$10$SNDK0FSxwsed2LwsQ/a9HOSeIW5/t/ch8LkrV6DC3icGt1FEmbEJW', 'counselor', 'm.chen@mindcare.edu'),
(4, 'alice', '$2y$10$SNDK0FSxwsed2LwsQ/a9HOSeIW5/t/ch8LkrV6DC3icGt1FEmbEJW', 'student', 'alice.carter@student.edu'),
(5, 'bob', '$2y$10$SNDK0FSxwsed2LwsQ/a9HOSeIW5/t/ch8LkrV6DC3icGt1FEmbEJW', 'student', 'bob.miller@student.edu'),
(6, 'charlie', '$2y$10$SNDK0FSxwsed2LwsQ/a9HOSeIW5/t/ch8LkrV6DC3icGt1FEmbEJW', 'student', 'charlie.davis@student.edu');

-- Insert Counselors
INSERT INTO counselors (id, user_id, full_name, specialization, phone) VALUES
(1, 2, 'Dr. Sarah Jenkins', 'Anxiety & Stress Management', '+1 (555) 019-2834'),
(2, 3, 'Dr. Michael Chen', 'Academic Burnout & Relationships', '+1 (555) 014-9988');

-- Insert Students
-- alice assigned to Dr. Sarah Jenkins (1), bob assigned to Dr. Michael Chen (2), charlie unassigned (NULL)
INSERT INTO students (id, user_id, full_name, student_id_number, phone, counselor_id) VALUES
(1, 4, 'Alice Carter', 'S1001', '+1 (555) 012-3456', 1),
(2, 5, 'Bob Miller', 'S1002', '+1 (555) 017-8910', 2),
(3, 6, 'Charlie Davis', 'S1003', '+1 (555) 011-2233', NULL);

-- Insert Moods (Historical data to make graphs look nice)
-- Curating entries for Alice and Bob
INSERT INTO moods (student_id, mood_date, mood_score, notes) VALUES
(1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'happy', 'Had a great group project meeting.'),
(1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'neutral', 'Regular lectures, nothing special.'),
(1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'stressed', 'Exam preparation is taking a toll.'),
(1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'sad', 'Felt lonely in the evening.'),
(1, CURDATE(), 'happy', 'Resolved my project issues! feeling positive.'),

(2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'neutral', 'Getting used to the new semester schedule.'),
(2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'stressed', 'Too many deadlines coming up.'),
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'stressed', 'Didnt sleep well due to academic worry.'),
(2, CURDATE(), 'neutral', 'Felt better after talking to a friend.');

-- Insert Stress Assessments
INSERT INTO assessments (student_id, score, stress_level, q1, q2, q3, q4, q5, q6, q7, q8, q9, q10, taken_at) VALUES
(1, 28, 'High Stress', 3, 4, 3, 2, 4, 3, 2, 3, 2, 2, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(1, 15, 'Moderate Stress', 2, 1, 2, 1, 2, 2, 1, 2, 1, 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 25, 'Moderate Stress', 3, 3, 2, 2, 3, 2, 3, 2, 3, 2, DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Insert Journals
INSERT INTO journals (student_id, journal_date, title, content, mood, gratitude_points, word_count, positive_word_count, privacy, created_at) VALUES
(1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'Reflections on Group Dynamics', 'Gratitude:\nI am grateful for my project team, a helpful classmate, and a peaceful evening.\n\nAchievement:\nI managed to speak up and moderate our group meeting.\n\nChallenge:\nSome voices were being ignored, but I tried to keep the discussion fair.', 'happy', 'My project team\nA helpful classmate\nA peaceful evening', 45, 4, 'private', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Late Night Worry', 'Gratitude:\nI am grateful for having time tomorrow to make a plan.\n\nChallenge:\nFinals are approaching and I feel behind in my web technology assignments.\n\nTomorrow''s Goal:\nCreate a study schedule and start with one small task.', 'stressed', 'Time tomorrow to make a plan', 41, 2, 'private', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Finding Balance', 'Gratitude:\nI am grateful for swimming practice, meditation, and a structured morning.\n\nPositive Moment:\nMeditation helped me feel calm and focused.\n\nSelf Reflection:\nBalance is easier when I plan my day early.', 'neutral', 'Swimming practice\nMeditation\nA structured morning', 36, 5, 'share_counselor', DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Insert Appointments
INSERT INTO appointments (id, student_id, counselor_id, appointment_date, appointment_time, reason, status) VALUES
(1, 1, 1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '10:00:00', 'Experiencing high anxiety about upcoming midterm exams.', 'approved'),
(2, 1, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:30:00', 'Follow-up on study plans and breathing exercises.', 'pending'),
(3, 2, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '11:00:00', 'Dealing with burnout and planning routine management.', 'approved');

-- Insert Counseling Notes
INSERT INTO counseling_notes (student_id, counselor_id, appointment_id, notes, visible_to_student) VALUES
(1, 1, 1, 'Alice attended the session expressing severe test anxiety. We discussed progressive muscle relaxation (PMR) and created a revision timetable. Alice agreed to log her mood daily. She is showing high levels of motivation to improve.', 1);

-- Insert Articles
INSERT INTO articles (id, title, summary, content, image_path) VALUES
(1, 'Understanding Academic Burnout', 'Learn how to recognize the early signs of study fatigue and explore practical strategies to restore balance.', 'Academic burnout is a state of emotional, physical, and mental exhaustion caused by excessive and prolonged study demands. It occurs when students feel overwhelmed, emotionally drained, and unable to meet constant expectations.\n\n**Common signs of burnout include:**\n• Chronic fatigue and lack of energy to attend classes.\n• Increased mental distance from studies, or feelings of negativism or cynicism.\n• Reduced academic efficiency and slipping grades.\n\n**Preventative strategies:**\nTo beat burnout, prioritize time management by establishing clear boundaries between academic work and relaxation. Sleep at least 7-8 hours daily, eat nutritious meals, and exercise regularly. Don\'t hesitate to reach out to campus counselors for guidance.', 'img/burnout.png'),
(2, 'The Power of Daily Journaling', 'Explore how keeping a private diary helps sort through complex emotions and reduces psychological stress.', 'Journaling is one of the most effective and affordable ways to improve mental clarity. Writing down your feelings helps you process emotions in a safe, non-judgmental space.\n\n**How journaling heals the mind:**\n• Clarifies thoughts and feelings, helping you identify what causes anxiety or depression.\n• Tracks day-to-day triggers, enabling you to recognize patterns in your mood and behaviors.\n• Promotes positive self-talk and helps reframe negative narratives.\n\n**Getting started:**\nStart with just 5-10 minutes a day. Write about your feelings, describe a significant conversation, or write down list items you\'re grateful for. You can choose to keep this private or share extracts with your counselor.', 'img/article_journaling.png'),
(3, 'Mindfulness & Breathing Exercises', 'Unlock research-backed, simple breathing techniques designed to calm your central nervous system instantly.', 'Mindfulness is the practice of drawing your full attention to the present moment without judgment. Combined with deep breathing, it acts as a physiological brake on stress.\n\n**Simple techniques you can practice anywhere:**\n• **Box Breathing (4-4-4-4):** Inhale for 4 seconds, hold for 4 seconds, exhale for 4 seconds, and hold empty for 4 seconds. Repeat 4 times.\n• **4-7-8 Technique:** Inhale for 4 seconds, hold for 7 seconds, exhale slowly for 8 seconds. This acts as a natural tranquilizer for the nervous system.\n• **5-4-3-2-1 Grounding:** Identify 5 things you can see, 4 you can touch, 3 you can hear, 2 you can smell, and 1 you can taste.\n\nRegular practice lowers heart rate, controls anxiety, and increases focus before major exams.', 'img/article_mindfulness.png');

-- 10. Events Table
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

-- 11. Event Target Students Table (for Selected Students visibility)
CREATE TABLE IF NOT EXISTS event_target_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    student_id INT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_event_target (event_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Event Registrations Table
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

-- 13. Notifications Table
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

-- Insert Seed Events
INSERT INTO events (id, title, category, description, banner_image, event_date, start_time, end_time, venue, speaker, max_participants, registration_deadline, status, visibility, created_by) VALUES
(1, 'Campus Mental Health Awareness Week 2026', 'Mental Health Awareness Week', 'Join us for a week-long series of interactive discussions, expert Q&A panels, and interactive wellness booths designed to break stigma around student mental health.', 'default_event.jpg', DATE_ADD(CURDATE(), INTERVAL 5 DAY), '09:30:00', '12:30:00', 'Main Auditorium & Student Center', 'Dr. Sarah Jenkins & Guest Panelists', 100, DATE_ADD(NOW(), INTERVAL 4 DAY), 'Upcoming', 'Public', 1),
(2, 'Exams Stress Relief & Time Management Workshop', 'Stress Management Workshop', 'Learn practical cognitive reframing, study scheduling, and anti-burnout techniques to conquer final exam anxiety.', 'default_event.jpg', DATE_ADD(CURDATE(), INTERVAL 8 DAY), '14:00:00', '16:00:00', 'Seminar Hall B, Health Sciences Building', 'Dr. Michael Chen', 40, DATE_ADD(NOW(), INTERVAL 7 DAY), 'Upcoming', 'Public', 1),
(3, 'Sunset Yoga & Deep Breathing Revival', 'Yoga Session', 'Experience outdoor grounding yoga designed for beginner and intermediate practitioners. Mats provided at venue.', 'default_event.jpg', DATE_ADD(CURDATE(), INTERVAL 12 DAY), '17:00:00', '18:30:00', 'Campus Central Lawn & Pavilion', 'Elena Rostova (Certified Yoga Instructor)', 30, DATE_ADD(NOW(), INTERVAL 11 DAY), 'Upcoming', 'Public', 1),
(4, 'Mindfulness & Box Breathing Masterclass', 'Mindfulness Training', 'Master box breathing, 4-7-8 breathing, and body scan meditation techniques to reduce physiological stress during exams.', 'default_event.jpg', DATE_SUB(CURDATE(), INTERVAL 3 DAY), '10:00:00', '11:30:00', 'MindCare Conference Room 102', 'Dr. Sarah Jenkins', 25, DATE_SUB(NOW(), INTERVAL 4 DAY), 'Completed', 'Public', 1);

-- Insert Seed Event Registrations
INSERT INTO event_registrations (event_id, student_id, registration_date, attendance_status, registration_status) VALUES
(1, 1, DATE_SUB(NOW(), INTERVAL 2 DAY), 'Pending', 'Registered'),
(1, 2, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Pending', 'Registered'),
(2, 1, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Pending', 'Registered'),
(4, 1, DATE_SUB(NOW(), INTERVAL 5 DAY), 'Present', 'Registered'),
(4, 2, DATE_SUB(NOW(), INTERVAL 5 DAY), 'Absent', 'Registered');

-- Insert Seed Notifications
INSERT INTO notifications (user_id, title, message, type, is_read, link_url) VALUES
(4, 'New Event Published!', 'Mental Health Awareness Week 2026 is now open for registration.', 'event', 0, 'student/events.php'),
(4, 'Event Registration Confirmed', 'You are successfully registered for Campus Mental Health Awareness Week 2026.', 'success', 1, 'student/events.php'),
(5, 'Event Reminder', 'Don\'t forget! Campus Mental Health Awareness Week starts in 5 days.', 'reminder', 0, 'student/events.php');

