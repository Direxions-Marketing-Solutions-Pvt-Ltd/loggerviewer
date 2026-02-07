<?php
$changelogPath = __DIR__ . '/../../CHANGELOG.md';
$content = file_exists($changelogPath) ? file_get_contents($changelogPath) : 'Changelog not found.';

// Simple Markdown parser for highlights
$content = htmlspecialchars($content);
$content = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $content);
$content = preg_replace('/^## (.*)$/m', '<h2 style="margin-top: 2rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">$1</h2>', $content);
$content = preg_replace('/^### (.*)$/m', '<h3 style="color: var(--primary); margin-top: 1rem;">$1</h3>', $content);
$content = preg_replace('/^\- (.*)$/m', '<li style="margin-bottom: 0.5rem;">$1</li>', $content);
$content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
$content = str_replace("\n", '<br>', $content);
// Wrap lists
$content = preg_replace('/(<li>.*<\/li>)/s', '<ul style="margin-left: 1.5rem;">$1</ul>', $content);
?>

<div class="card" style="max-width: 800px; margin: 2rem auto; padding: 3rem;">
    <div style="margin-bottom: 2rem;">
        <a href="index.php?action=dashboard" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">&larr; Back to Dashboard</a>
    </div>
    
    <div class="changelog-body" style="line-height: 1.6;">
        <?= $content ?>
    </div>
</div>
