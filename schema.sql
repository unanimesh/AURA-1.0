CREATE DATABASE IF NOT EXISTS smart_agri CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'iotuser'@'localhost' IDENTIFIED BY 'iotpass';
GRANT ALL PRIVILEGES ON smart_agri.* TO 'iotuser'@'localhost';
FLUSH PRIVILEGES;

USE smart_agri;

CREATE TABLE IF NOT EXISTS sensor_data (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    temperature DECIMAL(5,2) NULL,
    humidity DECIMAL(5,2) NULL,
    moisture_pct DECIMAL(5,2) NULL,
    fire_detected TINYINT(1) NOT NULL DEFAULT 0,
    ultrasonic1_cm DECIMAL(8,2) NULL,
    ultrasonic2_cm DECIMAL(8,2) NULL,
    capacity_pct DECIMAL(5,2) NULL,
    motor_on TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sensor_created (created_at),
    INDEX idx_sensor_id (id)
);

CREATE TABLE IF NOT EXISTS motor_actions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action ENUM('ON','OFF') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_motor_created (created_at)
);

CREATE TABLE IF NOT EXISTS crop_data (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    crop VARCHAR(50) NOT NULL,
    sowing_date DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pest_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pest_name VARCHAR(150) NOT NULL,
    confidence DECIMAL(5,2) NULL,
    symptoms TEXT,
    organic_treatment TEXT,
    chemical_treatment TEXT,
    prevention TEXT,
    image_path TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
