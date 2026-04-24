CREATE DATABASE IF NOT EXISTS campusconnect;
USE campusconnect;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','company','student') NOT NULL,
  status ENUM('pending','approved','rejected','active') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS student_profiles (
  user_id INT PRIMARY KEY,
  branch VARCHAR(80),
  gpa DECIMAL(3,2) DEFAULT 0,
  skills TEXT,
  linkedin_url VARCHAR(255),
  github_url VARCHAR(255),
  resume_path VARCHAR(255),
  cgpa DECIMAL(3,2),
  CONSTRAINT fk_student_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS companies (
  user_id INT PRIMARY KEY,
  company_name VARCHAR(160) NOT NULL,
  logo VARCHAR(255),
  description TEXT,
  website VARCHAR(200),
  hr_name VARCHAR(120),
  hr_email VARCHAR(150),
  status ENUM('pending','approved','rejected','active') DEFAULT 'pending',
  CONSTRAINT fk_company_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  ctc VARCHAR(50) NOT NULL,
  criteria_gpa DECIMAL(3,2) DEFAULT 0,
  criteria_branch VARCHAR(120),
  skills_required TEXT,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  deadline DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_job_company FOREIGN KEY (company_id) REFERENCES companies(user_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  job_id INT NOT NULL,
  status VARCHAR(30) DEFAULT 'applied',
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_student_job (student_id, job_id),
  CONSTRAINT fk_application_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_application_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS interviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  scheduled_at DATETIME NOT NULL,
  mode VARCHAR(50),
  rounds INT DEFAULT 1,
  venue VARCHAR(255),
  notes TEXT,
  CONSTRAINT fk_interview_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS offers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  offer_letter_path VARCHAR(255),
  ctc_offered VARCHAR(50),
  status ENUM('issued','rejected','pending') DEFAULT 'pending',
  issued_at DATETIME,
  CONSTRAINT fk_offer_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
