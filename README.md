NovaBank — Loan Recovery System
A lightweight PHP web application for tracking and managing loan defaulters in a banking environment.
Features

Add / Edit / Delete defaulter records
Auto-calculated status based on payment due date:

Overdue — due date has passed
Critical — due within the next 30 days
Pending — due in more than 30 days


Dashboard stats — total defaulters, total amount due, critical and overdue counts
Live search — filter records instantly by any field
Detail view — full client profile with loan and contact info

Requirements

PHP 7.4+
MySQL 5.7+ / MariaDB
A web server (Apache, Nginx, or PHP built-in server)

Setup

Clone the repository

bash   git clone <repo-url>
   cd loan-recovery

Create the database

sql   CREATE DATABASE bank_db;
   USE bank_db;

   CREATE TABLE loan_defaulters (
     id           INT AUTO_INCREMENT PRIMARY KEY,
     full_name    VARCHAR(150) NOT NULL,
     account_no   VARCHAR(50)  NOT NULL,
     phone        VARCHAR(30),
     email        VARCHAR(100),
     loan_amount  DECIMAL(15,2) DEFAULT 0,
     amount_due   DECIMAL(15,2) DEFAULT 0,
     due_date     DATE,
     loan_type    VARCHAR(60),
     status       VARCHAR(20)  DEFAULT 'Pending',
     notes        TEXT,
     created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   );

Configure the database connection in Db.php:

php   $conn = new mysqli('127.0.0.1', 'root', 'your_password', 'bank_db', 3300);

Serve the app

bash   php -S localhost:8000
Then open http://localhost:8000 in your browser.
File Structure
├── index.php       # Main UI — dashboard, table, modals
├── loan_crud.php   # Create, update, delete logic
├── Db.php          # Database connection
└── README.md
Notes

Currency is displayed in RWF (Rwandan Franc).
Loan status is refreshed automatically on every page load.
All user input is sanitised before being written to the database.

License
See LICENSE for details.