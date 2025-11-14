-- Migration: Create email_settings table for SMTP configuration
CREATE TABLE IF NOT EXISTS email_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transport VARCHAR(20) DEFAULT 'smtp',
    host VARCHAR(191) NULL,
    port INT NULL,
    encryption VARCHAR(20) NULL,
    username VARCHAR(191) NULL,
    password VARCHAR(255) NULL,
    from_name VARCHAR(191) NULL,
    from_email VARCHAR(191) NULL,
    reply_to_email VARCHAR(191) NULL,
    bcc_email VARCHAR(191) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure only one record exists (global settings)
-- This will be enforced by application logic

