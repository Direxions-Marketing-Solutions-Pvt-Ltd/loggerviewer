# 🚀 Logger View

Logger View is a high-performance, secure, and modern web application for scanning and analyzing server logs (Nginx, PHP, Apache). It features a configuration-driven parsing engine, AI-powered error analysis, and advanced security measures.

## ✨ Key Features

- **High-Performance Parser**: Optimized buffered binary backward reader (~2ms retrieval time).
- **Dynamic UI**: Automatically renders log data into structured tables based on detected formats.
- **Log Organization**: Grid-based **Calendar View** and **Monthly Archives** for 60-day retention.
- **Troubleshooting Dashboard**: Tabled UI (Webserver vs PHP) with **Quick Access** cards for the latest error logs.
- **AI Error Analysis**: Integrated with OpenAI/VoidAI to summarize and troubleshoot error logs.
- **Security Hardening**: All passwords are "peppered" with a 32-character `AUTH_SECRET`.
- **Encrypted Credentials**: Sensitive API keys (SMTP/AI) are stored encrypted.
- **CLI Utilities**: Manage passwords, rotate secrets, and seed historical data.
- **Analytics Engine**: Internal data collection synchronized in **UTC** timezone.
- **Flexible Scanning**: Scans `.log`, `.txt`, and other text-based log assets.
- **Interactive Installer**: Easy setup with automatic directory and database initialization.
- **Responsive Design**: Premium, dark-mode focused UI with smooth micro-interactions.

## 🛠 Tech Stack

- **Backend**: Native PHP 8.1+, SQLite (Database), Redis (Caching).
- **Frontend**: Vanilla JS (ES6+), Modern CSS.
- **APIs**: OpenAI Compatible Chat API.

## 🚦 Quick Start

1. **Deploy**: Upload the files to your server.
2. **Setup**: Run the interactive installer from your terminal:
   ```bash
   php install.php
   ```
3. **Configure**: Follow the prompts to set your 32-character `AUTH_SECRET`.
4. **Login**: Access the dashboard and start monitoring your logs!

> For detailed deployment instructions, server configurations, and security tips, see [DEPLOYMENT.MD](DEPLOYMENT.MD).

## 🔧 CLI Tools

- **Update Password**: `php src/scripts/update_password.php <username> <new_password>`
- **Rotate Secret**: `php src/scripts/update_secret.php`
- **Seed Historical Data**: `php src/scripts/seed_stats.php` (Visualizes charts immediately)

---
## ⚖️ License

This project is open-source and available under the [MIT License](LICENSE).

*Built for developers who need speed and security in their log management.*
