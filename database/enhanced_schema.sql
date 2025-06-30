-- ===== ENHANCED EMS DATABASE - PHASE 2 ADDITIONS =====
-- Building on existing schema.sql structure

USE ems;

-- ===== ENHANCE EXISTING USERS TABLE =====
ALTER TABLE users 
ADD COLUMN date_of_birth DATE AFTER phone_number,
ADD COLUMN gender ENUM('male', 'female', 'other') AFTER date_of_birth,
ADD COLUMN bio TEXT AFTER profile_image,
ADD COLUMN last_login DATETIME AFTER verification_token,
ADD COLUMN login_attempts INT DEFAULT 0 AFTER last_login,
ADD COLUMN locked_until DATETIME NULL AFTER login_attempts,
ADD COLUMN reset_token VARCHAR(100) AFTER locked_until,
ADD COLUMN reset_token_expires DATETIME AFTER reset_token,
ADD COLUMN preferences JSON AFTER reset_token_expires,
ADD INDEX idx_email (email),
ADD INDEX idx_role (role),
ADD INDEX idx_email_verified (email_verified);

-- ===== ENHANCE EXISTING EVENTS TABLE =====
ALTER TABLE events 
ADD COLUMN slug VARCHAR(200) UNIQUE AFTER title,
ADD COLUMN short_description VARCHAR(500) AFTER description,
ADD COLUMN registration_deadline DATETIME AFTER end_datetime,
ADD COLUMN current_attendees INT DEFAULT 0 AFTER max_attendees,
ADD COLUMN price DECIMAL(10,2) DEFAULT 0.00 AFTER is_paid,
ADD COLUMN gallery JSON AFTER image,
ADD COLUMN tags JSON AFTER featured,
ADD COLUMN requirements TEXT AFTER tags,
ADD COLUMN contact_info JSON AFTER requirements,
ADD COLUMN social_links JSON AFTER contact_info,
ADD INDEX idx_category (category),
ADD INDEX idx_start_date (start_datetime),
ADD INDEX idx_status (status),
ADD INDEX idx_featured (featured);

-- Update existing events with slugs
UPDATE events SET slug = LOWER(REPLACE(REPLACE(title, ' ', '-'), '''', '')) WHERE slug IS NULL;

-- ===== NEW TABLE: USER SESSIONS =====
CREATE TABLE IF NOT EXISTS user_sessions (
    session_id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_active (is_active)
);

-- ===== NEW TABLE: ACTIVITY LOGS =====
CREATE TABLE IF NOT EXISTS activity_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- ===== ENHANCE EXISTING NOTIFICATIONS TABLE =====
ALTER TABLE notifications 
ADD COLUMN category ENUM('event', 'announcement', 'system', 'reminder') DEFAULT 'system' AFTER type,
ADD COLUMN action_url VARCHAR(255) AFTER is_read,
ADD COLUMN data JSON AFTER action_url,
ADD COLUMN read_at TIMESTAMP NULL AFTER data,
ADD INDEX idx_user_id (user_id),
ADD INDEX idx_is_read (is_read),
ADD INDEX idx_created_at (created_at);

-- ===== NEW TABLE: EVENT REGISTRATIONS (Enhanced from tickets) =====
CREATE TABLE IF NOT EXISTS event_registrations (
    registration_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'confirmed', 'cancelled', 'attended') DEFAULT 'registered',
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_reference VARCHAR(100),
    ticket_number VARCHAR(50) UNIQUE,
    qr_code VARCHAR(255),
    additional_info JSON,
    checked_in_at DATETIME NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_event (user_id, event_id),
    INDEX idx_user_id (user_id),
    INDEX idx_event_id (event_id),
    INDEX idx_status (status),
    INDEX idx_ticket_number (ticket_number)
);

-- ===== NEW TABLE: ANNOUNCEMENTS (Enhanced) =====
CREATE TABLE IF NOT EXISTS announcements (
    announcement_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    category ENUM('general', 'academic', 'event', 'urgent', 'maintenance') DEFAULT 'general',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    author_id INT,
    target_audience ENUM('all', 'students', 'staff', 'specific') DEFAULT 'all',
    target_users JSON,
    start_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    end_date DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    is_pinned BOOLEAN DEFAULT FALSE,
    read_count INT DEFAULT 0,
    attachments JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_category (category),
    INDEX idx_priority (priority),
    INDEX idx_active (is_active),
    INDEX idx_start_date (start_date)
);

-- ===== NEW TABLE: USER PREFERENCES =====
CREATE TABLE IF NOT EXISTS user_preferences (
    preference_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    notification_email BOOLEAN DEFAULT TRUE,
    notification_sms BOOLEAN DEFAULT FALSE,
    theme ENUM('light', 'dark', 'auto') DEFAULT 'dark',
    language VARCHAR(10) DEFAULT 'en',
    timezone VARCHAR(50) DEFAULT 'UTC',
    event_reminders BOOLEAN DEFAULT TRUE,
    marketing_emails BOOLEAN DEFAULT FALSE,
    accessibility_settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preference (user_id)
);

-- ===== INSERT ENHANCED SAMPLE DATA =====

-- Update admin user with enhanced fields
UPDATE users SET 
    bio = 'System Administrator for EMS Platform',
    last_login = NOW(),
    preferences = '{"theme": "dark", "notifications": true}'
WHERE username = 'admin';

-- Insert sample students
INSERT INTO users (username, email, password, first_name, last_name, role, phone_number, email_verified, bio) VALUES
('john_doe', 'john@student.ems.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'Doe', 'user', '+265991234567', TRUE, 'Computer Science student passionate about AI and machine learning.'),
('jane_smith', 'jane@student.ems.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane', 'Smith', 'user', '+265991234568', TRUE, 'Business Administration student and event organizer.'),
('mike_wilson', 'mike@student.ems.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike', 'Wilson', 'organizer', '+265991234569', TRUE, 'Event coordinator and sports enthusiast.');

-- Insert enhanced sample events
INSERT INTO events (title, slug, description, short_description, start_datetime, end_datetime, venue, category, event_type, is_paid, price, max_attendees, organizer_id, status, featured) VALUES
('AI Innovation Summit 2025', 'ai-innovation-summit-2025', 'Join us for the most comprehensive AI conference in Malawi. Featuring keynote speakers from Google, Microsoft, and local tech innovators. Learn about machine learning, neural networks, and the future of AI in Africa.', 'Premier AI conference with industry leaders', '2025-01-28 09:00:00', '2025-01-28 17:00:00', 'Tech Hub Auditorium', 'academic', 'seminar', TRUE, 25000.00, 250, 1, 'approved', TRUE),

('Cultural Heritage Festival', 'cultural-heritage-festival', 'Celebrate the rich cultural diversity of Malawi and beyond. Experience traditional dances, music, food, and crafts from various ethnic groups. A perfect opportunity to learn and appreciate our heritage.', 'Multicultural celebration with traditional performances', '2025-02-01 10:00:00', '2025-02-01 22:00:00', 'Campus Main Grounds', 'cultural', 'other', FALSE, 0.00, 1000, 1, 'approved', TRUE),

('Inter-University Sports Championship', 'inter-university-sports-championship', 'The biggest sporting event of the year featuring competitions in football, basketball, volleyball, athletics, and more. Universities from across the region will compete for the championship trophy.', 'Regional university sports competition', '2025-02-05 08:00:00', '2025-02-07 18:00:00', 'University Sports Complex', 'sports', 'other', FALSE, 0.00, 2000, 1, 'approved', TRUE);

-- Insert sample announcements
INSERT INTO announcements (title, content, category, priority, author_id, is_active, is_pinned) VALUES
('🚀 Campus Innovation Fair Next Week', 'Get ready for the biggest innovation showcase of the year! Students will present their groundbreaking projects and compete for amazing prizes worth over MK 500,000. Registration deadline is this Friday.', 'event', 'high', 1, TRUE, TRUE),

('📚 Library Extended Hours During Exams', 'Starting next Monday, the library will extend its operating hours to support students during the examination period. New hours: Monday-Sunday 6:00 AM - 11:00 PM. Additional study spaces have been arranged.', 'academic', 'medium', 1, TRUE, FALSE),

('🏥 Health Center New Services', 'The campus health center is pleased to announce new mental health support services. Free counseling sessions are now available every Tuesday and Thursday from 2:00 PM - 5:00 PM. Book your appointment online.', 'general', 'medium', 1, TRUE, FALSE);

-- Insert sample user preferences
INSERT INTO user_preferences (user_id, theme, notification_email, event_reminders) VALUES
(1, 'dark', TRUE, TRUE),
(2, 'light', TRUE, TRUE),
(3, 'dark', FALSE, TRUE),
(4, 'auto', TRUE, FALSE);

-- Insert sample event registrations
INSERT INTO event_registrations (user_id, event_id, status, ticket_number, payment_status) VALUES
(2, 1, 'confirmed', 'EMS-2025-001', 'completed'),
(3, 1, 'registered', 'EMS-2025-002', 'pending'),
(4, 2, 'confirmed', 'EMS-2025-003', 'completed'),
(2, 3, 'registered', 'EMS-2025-004', 'completed');

COMMIT;