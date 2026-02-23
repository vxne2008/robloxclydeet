CREATE DATABASE IF NOT EXISTS cfsiportal_db;
USE cfsiportal_db;

CREATE TABLE IF NOT EXISTS registered_account (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('student','adviser','guidance') NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255),
    lrn VARCHAR(20),
    strand VARCHAR(100),
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for Adviser to Guidance
CREATE TABLE IF NOT EXISTS registration_adviser_guidanceportal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    last_name VARCHAR(255) NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255),
    strand VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for Student to Information
CREATE TABLE IF NOT EXISTS registration_student_guidanceinformation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    last_name VARCHAR(255) NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255),
    lrn VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    contact VARCHAR(20),
    address TEXT,
    birthdate DATE,
    guardian_name VARCHAR(255),
    guardian_contact VARCHAR(20),
    strand VARCHAR(100) NOT NULL,
    section VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for Stallman Attendance
CREATE TABLE IF NOT EXISTS attendance_stallman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(20) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    attendance_date DATE NOT NULL,
    attendance_time TIME,
    status ENUM('Present', 'Absent') DEFAULT 'Absent',
    UNIQUE KEY unique_attendance (lrn, attendance_date)
);

-- Table for Zuckerberg Attendance
CREATE TABLE IF NOT EXISTS attendance_zuckerberg (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(20) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    attendance_date DATE NOT NULL,
    attendance_time TIME,
    status ENUM('Present', 'Absent') DEFAULT 'Absent',
    UNIQUE KEY unique_attendance (lrn, attendance_date)
);

-- Table for Adviser Schedules and Subjects
CREATE TABLE IF NOT EXISTS advisers_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    strand VARCHAR(100) NOT NULL,
    semester VARCHAR(10) NULL,
    subject_name VARCHAR(255) NOT NULL,
    schedule_time VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Deployed Subject Schedules (Stallman)
CREATE TABLE IF NOT EXISTS subject_schedule_stallman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    strand VARCHAR(100) NOT NULL,
    semester VARCHAR(10) NULL,
    subject_name VARCHAR(255) NOT NULL,
    schedule_time VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_subject (semester, subject_name)
);

-- Deployed Subject Schedules (Zuckerberg)
CREATE TABLE IF NOT EXISTS subject_schedule_zuckerberg (
    id INT AUTO_INCREMENT PRIMARY KEY,
    strand VARCHAR(100) NOT NULL,
    semester VARCHAR(10) NULL,
    subject_name VARCHAR(255) NOT NULL,
    schedule_time VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_subject (semester, subject_name)
);

-- Table for Stallman Grades
CREATE TABLE IF NOT EXISTS grades_stallman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(20) NOT NULL,
    semester VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    grade VARCHAR(10) NOT NULL,
    is_deployed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for Zuckerberg Grades
CREATE TABLE IF NOT EXISTS grades_zuckerberg (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(20) NOT NULL,
    semester VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    grade VARCHAR(10) NOT NULL,
    is_deployed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
