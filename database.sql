CREATE DATABASE IF NOT EXISTS event_ticket_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE event_ticket_db;

DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS admin;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    image VARCHAR(255) NOT NULL DEFAULT '',
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    venue VARCHAR(150) NOT NULL,
    regular_price DECIMAL(10,2) NOT NULL,
    vip_price DECIMAL(10,2) NOT NULL,
    vvip_price DECIMAL(10,2) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_ref VARCHAR(20) NULL UNIQUE,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    ticket_type ENUM('regular','vip','vvip') NOT NULL,
    quantity INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (event_id) REFERENCES events(id)
) ENGINE=InnoDB;

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    paystack_reference VARCHAR(100) NOT NULL,
    status ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
    paid_at DATETIME NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id)
) ENGINE=InnoDB;

INSERT INTO admin (username, password) VALUES ('admin', '$2y$10$OEYi5/GrIaccrwTnw0lI0Oh41uXSbbP.qyXM1meFQmSQ0M7Atxgnq');

INSERT INTO events (name, description, image, event_date, event_time, venue, regular_price, vip_price, vvip_price) VALUES
('Design Week Lagos 2026', 'Africa''s biggest celebration of design — talks, exhibitions, and workshops from leading designers across the continent.', 'dwl2026.svg', '2026-10-21', '10:00:00', 'Federal Palace Hotel, Victoria Island, Lagos', 5000.00, 15000.00, 30000.00),
('Lagos Tech Fest 2026', 'A full-day festival of technology, startups, and innovation with keynotes, demos, and networking.', 'techfest.svg', '2026-11-14', '09:00:00', 'Landmark Centre, Oniru, Lagos', 3000.00, 10000.00, 20000.00),
('Afrobeat Live Concert', 'An unforgettable night of live Afrobeat performances from top Nigerian artists.', 'afrobeat.svg', '2026-12-18', '19:00:00', 'Eko Hotel & Suites, Victoria Island, Lagos', 8000.00, 20000.00, 50000.00);
