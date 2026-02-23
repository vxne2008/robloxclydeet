-- Registered accounts (login credentials)
CREATE TABLE IF NOT EXISTS registered_account (
    id SERIAL PRIMARY KEY,
    role VARCHAR(20) NOT NULL CHECK (role IN ('student','adviser','guidance')),
    last_name VARCHAR(255) NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255),
    lrn VARCHAR(20),
    strand VARCHAR(100),
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Adviser / Guidance registration
CREATE TABLE IF NOT EXISTS registration_adviser_guidanceportal (
    id SERIAL PRIMARY KEY,
    last_name VARCHAR(255) NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    middle_name VARCHAR(255),
    strand VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Student guidance information
CREATE TABLE IF NOT EXISTS registration_student_guidanceinformation (
    id SERIAL PRIMARY KEY,
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

-- Attendance tables per strand
CREATE TABLE IF NOT EXISTS attendance_stallman (
    id SERIAL PRIMARY KEY,
    lrn VARCHAR(20) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    attendance_date DATE NOT NULL,
    attendance_time TIME,
    status VARCHAR(10) DEFAULT 'Absent' CHECK (status IN ('Present','Absent')),
    semester VARCHAR(10),
    UNIQUE (lrn, attendance_date)
);

CREATE TABLE IF NOT EXISTS attendance_zuckerberg (
    id SERIAL PRIMARY KEY,
    lrn VARCHAR(20) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    attendance_date DATE NOT NULL,
    attendance_time TIME,
    status VARCHAR(10) DEFAULT 'Absent' CHECK (status IN ('Present','Absent')),
    semester VARCHAR(10),
    UNIQUE (lrn, attendance_date)
);

CREATE TABLE IF NOT EXISTS attendance_maslow (
    id SERIAL PRIMARY KEY,
    lrn VARCHAR(20) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    attendance_date DATE NOT NULL,
    attendance_time TIME,
    status VARCHAR(10) DEFAULT 'Absent' CHECK (status IN ('Present','Absent')),
    semester VARCHAR(10),
    UNIQUE (lrn, attendance_date)
);

CREATE TABLE IF NOT EXISTS attendance_voltaire (
    id SERIAL PRIMARY KEY,
    lrn VARCHAR(20) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    attendance_date DATE NOT NULL,
    attendance_time TIME,
    status VARCHAR(10) DEFAULT 'Absent' CHECK (status IN ('Present','Absent')),
    semester VARCHAR(10),
    UNIQUE (lrn, attendance_date)
);

-- Subject schedule tables per strand
CREATE TABLE IF NOT EXISTS subject_schedule_stallman (
    id SERIAL PRIMARY KEY,
    strand VARCHAR(100) NOT NULL,
    semester VARCHAR(10),
    subject_name VARCHAR(255) NOT NULL,
    schedule_time VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (semester, subject_name)
);

CREATE TABLE IF NOT EXISTS subject_schedule_zuckerberg (
    id SERIAL PRIMARY KEY,
    strand VARCHAR(100) NOT NULL,
    semester VARCHAR(10),
    subject_name VARCHAR(255) NOT NULL,
    schedule_time VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (semester, subject_name)
);

CREATE TABLE IF NOT EXISTS subject_schedule_maslow (
    id SERIAL PRIMARY KEY,
    strand VARCHAR(100) NOT NULL,
    semester VARCHAR(10),
    subject_name VARCHAR(255) NOT NULL,
    schedule_time VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (semester, subject_name)
);

CREATE TABLE IF NOT EXISTS subject_schedule_voltaire (
    id SERIAL PRIMARY KEY,
    strand VARCHAR(100) NOT NULL,
    semester VARCHAR(10),
    subject_name VARCHAR(255) NOT NULL,
    schedule_time VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (semester, subject_name)
);

-- Grades tables per strand
CREATE TABLE IF NOT EXISTS grades_stallman (
    id SERIAL PRIMARY KEY,
    lrn VARCHAR(20) NOT NULL,
    semester VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    grade VARCHAR(10) NOT NULL,
    is_deployed SMALLINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS grades_zuckerberg (
    id SERIAL PRIMARY KEY,
    lrn VARCHAR(20) NOT NULL,
    semester VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    grade VARCHAR(10) NOT NULL,
    is_deployed SMALLINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS grades_maslow (
    id SERIAL PRIMARY KEY,
    lrn VARCHAR(20) NOT NULL,
    semester VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    grade VARCHAR(10) NOT NULL,
    is_deployed SMALLINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS grades_voltaire (
    id SERIAL PRIMARY KEY,
    lrn VARCHAR(20) NOT NULL,
    semester VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    grade VARCHAR(10) NOT NULL,
    is_deployed SMALLINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Advisers schedule
CREATE TABLE IF NOT EXISTS advisers_schedule (
    id SERIAL PRIMARY KEY,
    strand VARCHAR(100) NOT NULL,
    semester VARCHAR(10),
    subject_name VARCHAR(255) NOT NULL,
    schedule_time VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
