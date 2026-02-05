<?php
date_default_timezone_set('UTC');
$db = new \App\Database(DB_PATH);
$projectManager = new \App\Project($db);
$projectId = (int)($_GET['project_id'] ?? 0);
$since = date('Y-m-d H:i:s', strtotime('-24 hours'));
$where = "timestamp >= ?";
$params = [$since];

if ($projectId > 0) {
    $where .= " AND project_id = ?";
    $params[] = $projectId;
    $projects = [$projectManager->getById($projectId)];
} else {
    $projects = $projectManager->getAll();
}

// Fetch stats for the last 24 hours
$statsRaw = $db->get_results(
    "SELECT project_id, timestamp, error_count, warn_count, info_count, top_errors 
     FROM stats 
     WHERE $where 
     ORDER BY timestamp ASC",
    $params
);

$chartData = [];
$totals = ['errors' => 0, 'warns' => 0, 'info' => 0];
$frequentErrors = [];

foreach ($statsRaw as $row) {
    $ts = date('H:i', strtotime($row->timestamp));
    if (!isset($chartData[$row->project_id])) {
        $chartData[$row->project_id] = [
            'labels' => [],
            'errors' => [],
            'warnings' => [],
            'info' => []
        ];
    }
    $chartData[$row->project_id]['labels'][] = $ts;
    $chartData[$row->project_id]['errors'][] = $row->error_count;
    $chartData[$row->project_id]['warnings'][] = $row->warn_count;
    $chartData[$row->project_id]['info'][] = $row->info_count;

    $totals['errors'] += $row->error_count;
    $totals['warns'] += $row->warn_count;
    $totals['info'] += $row->info_count;

    // Aggregate top errors per project (latest hour's snapshot)
    if (!empty($row->top_errors)) {
        $frequentErrors[$row->project_id] = json_decode($row->top_errors, true);
    }
}

$title = "Visual Analytics";
?>

<div class="fade-in">
    <div style="margin-bottom: 2rem;">
        <a href="<?= $projectId > 0 ? 'index.php?action=project&id='.$projectId : 'index.php?action=dashboard' ?>" style="text-decoration: none; color: var(--text-secondary); font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
            BACK TO <?= $projectId > 0 ? 'PROJECT' : 'DASHBOARD' ?>
        </a>
    </div>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 3rem;">
        <div>
            <h1 style="font-size: 2.5rem; font-weight: 800; letter-spacing: -1px; margin-bottom: 0.5rem;">LOG ANALYTICS</h1>
            <p style="color: var(--text-secondary);">Hour-by-hour trend analysis across your infrastructure (Last 24h).</p>
        </div>
        <div style="display: flex; gap: 1.5rem;">
            <div class="card glass-panel" style="padding: 1rem 2rem; border-left: 4px solid #ef4444;">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Total Errors</div>
                <div style="font-size: 1.5rem; font-weight: 800;"><?= number_format($totals['errors']) ?></div>
            </div>
            <div class="card glass-panel" style="padding: 1rem 2rem; border-left: 4px solid #f59e0b;">
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Total Warnings</div>
                <div style="font-size: 1.5rem; font-weight: 800;"><?= number_format($totals['warns']) ?></div>
            </div>
        </div>
    </div>

    <?php if (empty($chartData)): ?>
        <div class="card glass-panel" style="padding: 4rem; text-align: center;">
            <div style="color: var(--text-secondary); margin-bottom: 1rem;">
                <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            </div>
            <h3>No statistics discovered for the last 24 hours.</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem;">You can wait for the hourly collector or initialize historical data now:</p>
            <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 0.75rem; align-items: center;">
                <div style="background: rgba(0,0,0,0.2); padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid var(--card-border); width: fit-content;">
                    <code style="color: #60a5fa;">php src/scripts/collect_stats.php</code>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-secondary);">OR to generate test data:</div>
                <div style="background: rgba(0,0,0,0.2); padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid var(--card-border); width: fit-content;">
                    <code style="color: #34d399;">php src/scripts/seed_stats.php</code>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 3rem;">
            <?php foreach ($projects as $project): ?>
                <?php if (!isset($chartData[$project->id])) continue; ?>
                <div class="project-analytics-block">
                    <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 2rem; align-items: start;">
                        <!-- Chart Area -->
                        <div class="card glass-panel" style="padding: 1.5rem;">
                            <h3 style="margin-bottom: 1.5rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.75rem;">
                                <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--accent-primary);"></span>
                                <?= htmlspecialchars($project->name) ?>
                            </h3>
                            <div style="height: 300px;">
                                <canvas id="chart-<?= $project->id ?>"></canvas>
                            </div>
                        </div>

                        <!-- Frequent Issues Table -->
                        <div class="card glass-panel" style="padding: 1.5rem;">
                            <h4 style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                Frequent Issues (Hourly)
                            </h4>
                            <?php if (!empty($frequentErrors[$project->id])): ?>
                                <div class="frequent-issues-list">
                                    <?php foreach ($frequentErrors[$project->id] as $msg => $count): ?>
                                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 0; border-bottom: 1px solid rgba(255,255,255,0.03);">
                                            <div style="font-size: 0.8rem; color: #cbd5e1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 80%; line-height: 1.4;" title="<?= htmlspecialchars($msg) ?>">
                                                <?= htmlspecialchars($msg) ?>
                                            </div>
                                            <span class="badge badge-error" style="font-size: 0.7rem; padding: 0.15rem 0.5rem; min-width: 35px; text-align: center;"><?= $count ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p style="font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 2rem 0;">No specific fatal issues aggregated yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const data = <?= json_encode($chartData) ?>;
        
        Object.keys(data).forEach(projectId => {
            const canvas = document.getElementById(`chart-${projectId}`);
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data[projectId].labels,
                    datasets: [
                        {
                            label: 'Errors',
                            data: data[projectId].errors,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.05)',
                            borderWidth: 3,
                            pointRadius: 4,
                            pointBackgroundColor: '#ef4444',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Warnings',
                            data: data[projectId].warnings,
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245, 158, 11, 0.05)',
                            borderWidth: 2,
                            pointRadius: 2,
                            pointBackgroundColor: '#f59e0b',
                            tension: 0.4,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'end',
                            labels: {
                                color: '#94a3b8',
                                boxWidth: 10,
                                padding: 20,
                                font: { family: 'Outfit', size: 11, weight: '600' }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.9)',
                            padding: 12,
                            titleFont: { size: 13, weight: '700' },
                            bodyFont: { size: 12 },
                            borderWidth: 1,
                            borderColor: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255, 255, 255, 0.03)' },
                            ticks: { color: '#64748b', font: { size: 10 } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { 
                                color: '#64748b', 
                                font: { size: 10 },
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 12
                            }
                        }
                    }
                }
            });
        });
    });
</script>
