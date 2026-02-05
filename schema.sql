-- Database Schema for Logger View
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user',
    email TEXT,
    auth_type TEXT NOT NULL DEFAULT 'password',
    otp_code TEXT,
    otp_expires_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    webserver_path TEXT,
    php_path TEXT,
    webserver_format TEXT,
    php_format TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS project_access (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    project_id INTEGER NOT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(project_id) REFERENCES projects(id)
);

CREATE TABLE IF NOT EXISTS stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    project_id INTEGER NOT NULL,
    timestamp DATETIME NOT NULL,
    error_count INTEGER DEFAULT 0,
    warn_count INTEGER DEFAULT 0,
    info_count INTEGER DEFAULT 0,
    UNIQUE(project_id, timestamp),
    FOREIGN KEY(project_id) REFERENCES projects(id)
);

-- Initial Administrator
-- Default credentials: admin / admin123
INSERT OR IGNORE INTO users (username, password, role, email, auth_type) 
VALUES ('admin', '$2y$12$QPuVy87A4TTukVzXAxXK.u4MH.yf1QCQbVN0xush92QR8inDfXz9W', 'admin', 'admin@example.com', 'password');
