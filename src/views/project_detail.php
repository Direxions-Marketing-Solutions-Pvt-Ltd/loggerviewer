<?php
$id = (int)($_GET['id'] ?? 0);
$project = $projectManager->getById($id);

if (!$project) {
    echo "<h1>Project not found</h1>";
    return;
}

$webReader = $project->webserver_path ? new \App\LogReader($project->webserver_path) : null;
$phpReader = $project->php_path ? new \App\LogReader($project->php_path) : null;

$webLogs = $webReader ? $webReader->getLogFiles() : [];
$phpLogs = $phpReader ? $phpReader->getLogFiles() : [];

// Extraction and Grouping logic
function groupLogsByMonth(array $logs) {
    $grouped = [];
    foreach ($logs as $log) {
        $month = date('F Y', $log['mtime']);
        $date = date('Y-m-d', $log['mtime']);
        $grouped[$month][$date][] = $log;
    }
    uksort($grouped, function($a, $b) {
        return strtotime($b) - strtotime($a);
    });
    foreach ($grouped as &$days) {
        krsort($days);
    }
    return $grouped;
}

function renderProjectCalendar($activeDates) {
    $html = '<div class="log-calendar-grid">';
    $days = ['S','M','T','W','T','F','S'];
    foreach ($days as $d) $html .= "<div class='day-header'>$d</div>";
    for ($i = 34; $i >= 0; $i--) {
        $ts = strtotime("-$i days");
        $date = date('Y-m-d', $ts);
        $dayNum = date('j', $ts);
        $isActive = isset($activeDates[$date]);
        $isToday = $date === date('Y-m-d');
        $classes = 'calendar-day' . ($isActive ? ' has-logs' : '') . ($isToday ? ' is-today' : '');
        $onclick = $isActive ? "onclick='filterByDate(\"$date\")'" : "";
        $html .= "<div class='$classes' $onclick title='$date'>$dayNum</div>";
    }
    return $html . '</div>';
}

$webLogsGrouped = groupLogsByMonth($webLogs);
$phpLogsGrouped = groupLogsByMonth($phpLogs);
$activeDates = [];
foreach ($webLogs as $log) $activeDates[date('Y-m-d', $log['mtime'])] = true;
foreach ($phpLogs as $log) $activeDates[date('Y-m-d', $log['mtime'])] = true;
?>

<style>
    .log-calendar-container { margin-bottom: 2.5rem; background: rgba(255,255,255,0.02); padding: 1.5rem; border-radius: 1rem; border: 1px solid var(--card-border); }
    .log-calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.5rem; max-width: 280px; }
    .day-header { font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-align: center; padding-bottom: 0.25rem; }
    .calendar-day { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; border-radius: 0.4rem; color: var(--text-muted); background: rgba(255,255,255,0.01); cursor: default; transition: all 0.2s; }
    .calendar-day.has-logs { background: var(--primary-glow); color: var(--primary); font-weight: 700; cursor: pointer; border: 1px solid rgba(99, 102, 241, 0.2); }
    .calendar-day.has-logs:hover { transform: scale(1.1); background: var(--primary); color: white; }
    .calendar-day.is-today { box-shadow: 0 0 0 2px var(--accent-primary); }
    .calendar-day.is-selected { background: var(--accent-primary) !important; color: white !important; }

    .month-accordion { margin-bottom: 1rem; border-radius: 0.75rem; overflow: hidden; border: 1px solid var(--card-border); background: rgba(255,255,255,0.02); }
    .month-header { padding: 1.1rem 1.5rem; background: rgba(255,255,255,0.03); cursor: pointer; display: flex; justify-content: space-between; align-items: center; user-select: none; transition: background 0.2s; }
    .month-header:hover { background: rgba(255,255,255,0.05); }
    .month-header h3 { font-size: 0.85rem; font-weight: 700; margin: 0; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; }
    .month-content { padding: 1.5rem; display: none; }
    .month-accordion.open .month-content { display: block; }
    .month-accordion.open .month-header { background: rgba(255,255,255,0.05); border-bottom: 1px solid var(--card-border); }
    .month-accordion.open .month-header h3 { color: var(--text-primary); }

    .timeline-date { position: relative; padding-left: 2rem; margin-bottom: 2rem; }
    .timeline-date::before { content: ''; position: absolute; left: 0.35rem; top: 0.5rem; bottom: -2rem; width: 2px; background: rgba(255, 255, 255, 0.05); }
    .timeline-date:last-child::before { display: none; }
    .timeline-dot { position: absolute; left: 0; top: 0.25rem; width: 0.75rem; height: 0.75rem; border-radius: 50%; background: var(--accent-primary); border: 2px solid var(--bg-primary); z-index: 1; }
    .date-label { font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }

    .tab-bar { display: flex; gap: 2rem; margin-bottom: 2.5rem; border-bottom: 1px solid var(--card-border); padding-bottom: 0px; }
    .tab-item { font-size: 0.85rem; font-weight: 700; color: var(--text-muted); cursor: pointer; padding-bottom: 1rem; position: relative; transition: all 0.2s; letter-spacing: 0.05em; }
    .tab-item:hover { color: var(--text-primary); }
    .tab-item.active { color: var(--primary); }
    .tab-item.active::after { content: ''; position: absolute; bottom: -1px; left: 0; right: 0; height: 2px; background: var(--primary); box-shadow: 0 0 10px var(--primary-glow); }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    .quick-card { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 1rem; padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; text-decoration: none; transition: all 0.2s; position: relative; overflow: hidden; }
    .quick-card:hover { border-color: var(--primary); transform: translateY(-4px); background: rgba(255,255,255,0.02); }
    .quick-card::before { content: ''; position: absolute; top: 0; left: 0; width: 3px; height: 100%; background: var(--card-accent, var(--primary)); }
</style>

<div style="margin-bottom: 3rem;" class="fade-in">
    <a href="index.php" style="text-decoration: none; color: var(--text-secondary); font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
        ALL PROJECTS
    </a>
    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-size: 2.5rem; font-weight: 800; letter-spacing: -1px;"><?= htmlspecialchars($project->name) ?></h1>
            <p style="color: var(--text-secondary); margin-top: 0.25rem;">Inspect and analyze project logs in real-time</p>
        </div>
        <a href="index.php?action=analytics&project_id=<?= $id ?>" class="btn" style="padding: 0.75rem 1.5rem; background: var(--primary-glow); color: var(--primary); border: 1px solid var(--primary); font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            ANALYTICS
        </a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 320px 1fr; gap: 2.5rem;" class="fade-in">
    <!-- Sidebar -->
    <aside>
        <div class="log-calendar-container">
            <h4 style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1rem; letter-spacing: 0.05em;">Availability (35d)</h4>
            <?= renderProjectCalendar($activeDates) ?>
            <div style="margin-top: 1.5rem; font-size: 0.7rem; color: var(--text-muted); line-height: 1.5;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--primary);"></span> Days with logs
                </div>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; border: 1px solid var(--accent-primary);"></span> Today
                </div>
            </div>
            <button onclick="resetFilters()" id="reset-btn" hidden style="margin-top: 1.5rem; width: 100%; padding: 0.6rem; font-size: 0.7rem; background: rgba(255,255,255,0.05); border: 1px solid var(--card-border); border-radius: 0.5rem; color: var(--text-primary); cursor: pointer;">Show All Files</button>
        </div>
        <div class="glass-panel" style="padding: 1.5rem; background: transparent; border-style: dashed;">
            <h4 style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.75rem; letter-spacing: 0.05em;">Retention</h4>
            <p style="font-size: 0.75rem; color: var(--text-secondary); line-height: 1.6;">Logs are retained for up to 60 days on the server.</p>
        </div>
    </aside>

    <!-- Main Content -->
    <div style="display: flex; flex-direction: column; gap: 3.5rem;">
        <!-- Quick Access -->
        <section>
            <h4 style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); margin-bottom: 1.5rem; letter-spacing: 0.05em; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                Latest Assets
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem;">
                <?php 
                    $latestWeb = null;
                    foreach ($webLogs as $log) {
                        if (stripos($log['name'], 'error') !== false) {
                            $latestWeb = $log;
                            break;
                        }
                    }
                    if (!$latestWeb && !empty($webLogs)) $latestWeb = reset($webLogs);

                    $latestPhp = null;
                    foreach ($phpLogs as $log) {
                        if (stripos($log['name'], 'error') !== false) {
                            $latestPhp = $log;
                            break;
                        }
                    }
                    if (!$latestPhp && !empty($phpLogs)) $latestPhp = reset($phpLogs);
                ?>
                <?php if ($latestWeb): ?>
                <a href="index.php?action=view_log&project_id=<?= $id ?>&type=webserver&file=<?= urlencode($latestWeb['name']) ?>" class="quick-card" style="--card-accent: #0ea5e9;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <span style="font-size: 0.65rem; font-weight: 800; color: #0ea5e9; text-transform: uppercase;">Webserver Log</span>
                        <span style="font-size: 0.65rem; color: var(--text-muted);"><?= date('M j, H:i', $latestWeb['mtime']) ?></span>
                    </div>
                    <h3 style="font-size: 1rem; color: var(--text-primary); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($latestWeb['name']) ?></h3>
                    <div style="font-size: 0.7rem; color: var(--text-secondary);"><?= round((time() - $latestWeb['mtime'])/60, 0) ?>m since last write</div>
                </a>
                <?php endif; ?>
                <?php if ($latestPhp): ?>
                <a href="index.php?action=view_log&project_id=<?= $id ?>&type=php&file=<?= urlencode($latestPhp['name']) ?>" class="quick-card" style="--card-accent: #f59e0b;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <span style="font-size: 0.65rem; font-weight: 800; color: #f59e0b; text-transform: uppercase;">PHP Runtime Log</span>
                        <span style="font-size: 0.65rem; color: var(--text-muted);"><?= date('M j, H:i', $latestPhp['mtime']) ?></span>
                    </div>
                    <h3 style="font-size: 1rem; color: var(--text-primary); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($latestPhp['name']) ?></h3>
                    <div style="font-size: 0.7rem; color: var(--text-secondary);"><?= round((time() - $latestPhp['mtime'])/60, 0) ?>m since last write</div>
                </a>
                <?php endif; ?>
            </div>
        </section>

        <!-- Tabs & Archives -->
        <section>
            <div class="tab-bar">
                <div class="tab-item active" onclick="switchTab('webserver')">WEBSERVER SOURCE</div>
                <div class="tab-item" onclick="switchTab('php')">PHP RUNTIME</div>
            </div>

            <div id="tab-webserver" class="tab-content active">
                <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 2rem;">Source: <?= $project->webserver_path ?: 'Not Configured' ?></p>
                <?php if (!empty($webLogsGrouped)): ?>
                    <?php $i = 0; foreach ($webLogsGrouped as $month => $days): ?>
                        <div class="month-accordion <?= $i === 0 ? 'open' : '' ?>">
                            <div class="month-header" onclick="this.parentElement.classList.toggle('open')">
                                <h3><?= $month ?></h3>
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                            <div class="month-content">
                                <?php foreach ($days as $date => $logs): ?>
                                    <div class="timeline-date log-date-group" data-date="<?= $date ?>">
                                        <div class="timeline-dot"></div>
                                        <div class="date-label"><?= date('M j, Y', strtotime($date)) ?></div>
                                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                            <?php foreach ($logs as $log): ?>
                                                <a href="index.php?action=view_log&project_id=<?= $id ?>&type=webserver&file=<?= urlencode($log['name']) ?>" class="log-file-item" style="text-decoration: none; padding: 0.85rem 1rem; background: rgba(255,255,255,0.02); border: 1px solid var(--card-border); border-radius: 0.75rem; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s;">
                                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                        <svg width="14" height="14" style="color: var(--text-secondary)" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                                        <div>
                                                            <div style="color: var(--text-primary); font-weight: 600; font-size: 0.85rem;"><?= htmlspecialchars($log['name']) ?></div>
                                                            <div style="font-size: 0.65rem; color: var(--text-secondary)"><?= date('H:i', $log['mtime']) ?> • <?= round($log['size'] / 1024, 1) ?> KB</div>
                                                        </div>
                                                    </div>
                                                    <svg width="12" height="12" style="color: var(--text-muted)" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"></path></svg>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php $i++; endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; color: var(--text-secondary); padding: 3rem; background: rgba(0,0,0,0.1); border-radius: 0.75rem;">No webserver logs found</div>
                <?php endif; ?>
            </div>

            <div id="tab-php" class="tab-content">
                <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 2rem;">Source: <?= $project->php_path ?: 'Not Configured' ?></p>
                <?php if (!empty($phpLogsGrouped)): ?>
                    <?php $i = 0; foreach ($phpLogsGrouped as $month => $days): ?>
                        <div class="month-accordion <?= $i === 0 ? 'open' : '' ?>">
                            <div class="month-header" onclick="this.parentElement.classList.toggle('open')">
                                <h3><?= $month ?></h3>
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                            <div class="month-content">
                                <?php foreach ($days as $date => $logs): ?>
                                    <div class="timeline-date log-date-group" data-date="<?= $date ?>">
                                        <div class="timeline-dot" style="background: #f59e0b;"></div>
                                        <div class="date-label"><?= date('M j, Y', strtotime($date)) ?></div>
                                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                            <?php foreach ($logs as $log): ?>
                                                <a href="index.php?action=view_log&project_id=<?= $id ?>&type=php&file=<?= urlencode($log['name']) ?>" class="log-file-item" style="text-decoration: none; padding: 0.85rem 1rem; background: rgba(255,255,255,0.02); border: 1px solid var(--card-border); border-radius: 0.75rem; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s;">
                                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                        <svg width="14" height="14" style="color: var(--text-secondary)" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                                                        <div>
                                                            <div style="color: var(--text-primary); font-weight: 600; font-size: 0.85rem;"><?= htmlspecialchars($log['name']) ?></div>
                                                            <div style="font-size: 0.65rem; color: var(--text-secondary)"><?= date('H:i', $log['mtime']) ?> • <?= round($log['size'] / 1024, 1) ?> KB</div>
                                                        </div>
                                                    </div>
                                                    <svg width="12" height="12" style="color: var(--text-muted)" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"></path></svg>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php $i++; endforeach; ?>
                <?php else: ?>
                    <div style="text-align: center; color: var(--text-secondary); padding: 3rem; background: rgba(0,0,0,0.1); border-radius: 0.75rem;">No PHP logs found</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script>
    function switchTab(type) {
        document.querySelectorAll('.tab-item').forEach(item => {
            item.classList.remove('active');
            if (item.innerText.toLowerCase().includes(type)) item.classList.add('active');
        });
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        document.getElementById(`tab-${type}`).classList.add('active');
    }

    function filterByDate(date) {
        document.querySelectorAll('.log-date-group').forEach(group => {
            if (group.dataset.date === date) {
                group.style.display = 'block';
                group.closest('.month-accordion').classList.add('open');
            } else {
                group.style.display = 'none';
            }
        });
        document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('is-selected'));
        const selectedDay = document.querySelector(`.calendar-day[title="${date}"]`);
        if (selectedDay) selectedDay.classList.add('is-selected');
        document.getElementById('reset-btn').hidden = false;
    }

    function resetFilters() {
        document.querySelectorAll('.log-date-group').forEach(group => group.style.display = 'block');
        document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('is-selected'));
        document.getElementById('reset-btn').hidden = true;
    }

    window.addEventListener('DOMContentLoaded', () => {
        const phpMtime = <?= !empty($phpLogs) ? reset($phpLogs)['mtime'] : 0 ?>;
        const webMtime = <?= !empty($webLogs) ? reset($webLogs)['mtime'] : 0 ?>;
        if (phpMtime > webMtime) switchTab('php');
    });
</script>
