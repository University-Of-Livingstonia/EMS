-- Ekwendeni Mighty Campus Event Management System Database Schema

-- Drop tables if they exist to avoid conflicts
DROP TABLE IF EXISTS feedback_comment;
DROP TABLE IF EXISTS feedback_rating;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS tickets;
DROP TABLE IF EXISTS guest_rsvps;
DROP TABLE IF EXISTS guest_subscribers;
DROP TABLE IF EXISTS event_proposals;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS users;

-- Users table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('admin', 'organizer', 'user', 'guest') NOT NULL DEFAULT 'user',
    department VARCHAR(100),
    phone_number VARCHAR(20),
    profile_image VARCHAR(255),
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Events table
CREATE TABLE events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    venue VARCHAR(255) NOT NULL,
    venue_details TEXT,
    category ENUM('academic', 'social', 'sports', 'cultural', 'other') NOT NULL,
    event_type ENUM('workshop', 'seminar', 'concert', 'meeting', 'conference', 'other') NOT NULL,
    is_paid BOOLEAN DEFAULT FALSE,
    is_public BOOLEAN DEFAULT TRUE,
    max_attendees INT,
    organizer_id INT,
    status ENUM('draft', 'pending', 'approved', 'rejected', 'cancelled', 'completed') NOT NULL DEFAULT 'draft',
    featured BOOLEAN DEFAULT FALSE,
    image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organizer_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Event Proposals table
CREATE TABLE event_proposals (
    proposal_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    proposed_date DATE NOT NULL,
    proposed_start_time TIME NOT NULL,
    proposed_end_time TIME NOT NULL,
    venue VARCHAR(255) NOT NULL,
    venue_requirements TEXT,
    estimated_attendees INT,
    target_audience VARCHAR(255),
    is_paid BOOLEAN DEFAULT FALSE,
    category ENUM('academic', 'social', 'sports', 'cultural', 'other') NOT NULL,
    event_type ENUM('workshop', 'seminar', 'concert', 'meeting', 'conference', 'other') NOT NULL,
    additional_notes TEXT,
    status ENUM('pending', 'approved', 'rejected', 'revisions_requested') NOT NULL DEFAULT 'pending',
    admin_comments TEXT,
    supporting_documents VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Guest Subscribers table
CREATE TABLE guest_subscribers (
    subscriber_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    subscription_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    unsubscribe_token VARCHAR(255)
);

-- Guest RSVPs table
CREATE TABLE guest_rsvps (
    rsvp_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20),
    attendance_status ENUM('confirmed', 'maybe', 'declined') NOT NULL DEFAULT 'confirmed',
    additional_guests INT DEFAULT 0,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE
);

-- Tickets table
CREATE TABLE tickets (
    ticket_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    ticket_type VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) DEFAULT 0.00,
    payment_status ENUM('pending', 'completed', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending',
    payment_method VARCHAR(50),
    payment_reference VARCHAR(100),
    qr_code VARCHAR(255),
    is_used BOOLEAN DEFAULT FALSE,
    used_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Payments table
CREATE TABLE payments (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('mpamba', 'airtel_money', 'credit_card', 'bank_transfer', 'cash') NOT NULL,
    transaction_reference VARCHAR(100),
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    refund_amount DECIMAL(10, 2),
    refund_date DATETIME,
    refund_reason TEXT,
    FOREIGN KEY (ticket_id) REFERENCES tickets(ticket_id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Notifications table
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('event_confirmation', 'event_reminder', 'event_update', 'event_cancellation', 'proposal_status', 'system') NOT NULL,
    related_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Feedback Rating table
CREATE TABLE feedback_rating (
    rating_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT,
    guest_email VARCHAR(100),
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Feedback Comment table
CREATE TABLE feedback_comment (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT,
    guest_email VARCHAR(100),
    comment TEXT NOT NULL,
    is_anonymous BOOLEAN DEFAULT FALSE,
    is_approved BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- Create an admin user (password: admin123)
INSERT INTO users (username, email, password, first_name, last_name, role, email_verified)
VALUES ('admin', 'admin@unilia.ac.mw', '$2y$10$8tPjdlv.7XDmvW93bFPeAO6FZFVlsJ5XYh5hxQUC9HbSVZlhXu3Uu', 'System', 'Administrator', 'admin', TRUE);
