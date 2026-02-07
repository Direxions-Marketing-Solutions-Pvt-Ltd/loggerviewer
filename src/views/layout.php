<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_TITLE ?> - <?= ucfirst($action) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>
    <?php if (\App\Auth::isLoggedIn()): ?>
    <header>
        <div class="container header-content">
            <a href="index.php" class="logo">LOGGER<span style="color: var(--text-primary); font-weight: 300;">VIEW</span></a>
            <nav style="display: flex; align-items: center; gap: 1.5rem;">
                <div style="text-align: right; margin-right: 0.5rem;">
                    <div style="font-size: 0.9rem; font-weight: 600;"><?= $_SESSION['username'] ?></div>
                    <span class="badge badge-<?= $_SESSION['role'] ?>"><?= ucfirst($_SESSION['role']) ?></span>
                </div>
                <a href="index.php?action=dashboard" class="btn btn-secondary" style="padding: 0.5rem 1rem; border: none; background: transparent; color: var(--text-secondary);">Projects</a>
                <a href="index.php?action=changelog" class="btn btn-secondary" style="padding: 0.5rem 1rem; border: none; background: transparent; color: var(--text-secondary);">What's New</a>
                 <?php if (\App\Auth::isAdmin()): ?>
                     <a href="index.php?action=users" class="btn btn-secondary" style="padding: 0.5rem 1rem; border: none; background: transparent; color: var(--text-secondary);">Users</a>
                    <a href="index.php?action=settings" class="btn btn-secondary" style="padding: 0.5rem 1rem; border: none; background: transparent; color: var(--text-secondary);">Settings</a>
                <?php endif; ?>
                <a href="index.php?action=logout" class="btn btn-secondary" style="padding: 0.5rem 1rem;">Logout</a>
            </nav>
        </div>
    </header>
    <?php endif; ?>

    <main class="container">
        <?= $content ?>
    </main>

    <footer style="margin-top: 4rem; padding: 2rem 0; border-top: 1px solid var(--border); text-align: center; color: var(--text-muted)">
        <p>&copy; <?= date('Y') ?> Logger View - Standalone PHP application</p>
    </footer>
</body>
</html>
