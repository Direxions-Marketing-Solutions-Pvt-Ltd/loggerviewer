<?php
$projectId = (int)$_GET['project_id'];
$type = $_GET['type'];
$file = $_GET['file'];

$project = $projectManager->getById($projectId);
if (!$project) die("Project not found");

$title = htmlspecialchars($file) . " (" . ($type === 'webserver' ? 'Web' : 'PHP') . ")";
?>

<div style="margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: center;" class="fade-in">
    <div>
        <a href="index.php?action=project&id=<?= $projectId ?>" style="text-decoration: none; color: var(--text-secondary); font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
            BACK TO PROJECT
        </a>
        <h1 style="font-size: 2rem; font-weight: 800; letter-spacing: -0.5px;"><?= $file ?></h1>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.25rem;">
             <span class="badge" style="background: <?= $type === 'webserver' ? 'rgba(14, 165, 233, 0.1)' : 'rgba(245, 158, 11, 0.1)' ?>; color: <?= $type === 'webserver' ? '#0ea5e9' : '#f59e0b' ?>;">
                <?= $type === 'webserver' ? 'WEBSERVER' : 'PHP' ?>
             </span>
             <span style="color: var(--text-secondary); font-size: 0.85rem;"><?= htmlspecialchars($project->name) ?></span>
        </div>
    </div>
    
    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 1rem;">
        <div class="filters">
            <button class="filter-btn active" data-filter="all">ALL ENTRIES</button>
            <button class="filter-btn" data-filter="error">ERRORS</button>
            <button class="filter-btn" data-filter="warn">WARNINGS</button>
            <button class="filter-btn" data-filter="info">INFORMATION</button>
        </div>
        
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div class="search-box" style="position: relative; width: 300px;">
                <input type="text" id="log-search" placeholder="Search logs..." 
                       style="width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; background: rgba(255,255,255,0.05); border: 1px solid var(--card-border); border-radius: 0.75rem; color: var(--text-primary); outline: none;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" 
                     style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary);">
                    <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            
            <a href="index.php?action=download_raw&project_id=<?= $projectId ?>&file=<?= urlencode($file) ?>" 
               class="btn-secondary" style="padding: 0.6rem 1rem; border-radius: 0.75rem; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                </svg>
                DOWNLOAD RAW
            </a>
        </div>
    </div>
</div>

<div class="viewer-container fade-in shadow-soft">
    <div class="viewer-header">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <span>STATUS: <span style="color: var(--accent-success)">LIVE</span></span>
            <span>ENCODING: UTF-8</span>
        </div>
        <div id="log-status">IDLE</div>
    </div>
    <div id="log-lines"></div>
    <div id="log-loader" style="text-align: center; padding: 4rem; color: var(--text-secondary)">
        <div style="margin-bottom: 1rem;">
             <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin"><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"></path></svg>
        </div>
        Stream initialization...
    </div>
</div>

<div style="margin-top: 2rem; display: flex; justify-content: flex-end; align-items: center; gap: 1rem;" class="fade-in">
    <button id="load-more" class="btn-secondary" style="border-radius: 0.75rem;">
        LOAD PREVIOUS ENTRIES
    </button>
</div>

<style>
    .spin { animation: spin 2s linear infinite; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .shadow-soft { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }

    /* AI Response Modal */
    #ai-modal {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(15, 23, 42, 0.9);
        backdrop-filter: blur(4px);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }
    #ai-modal-content {
        max-width: 800px;
        width: 100%;
        max-height: 80vh;
        overflow-y: auto;
        padding: 2.5rem;
    }
</style>

<!-- AI Modal -->
<div id="ai-modal">
    <div id="ai-modal-content" class="card glass-panel fade-in">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="background: rgba(139, 92, 246, 0.1); padding: 0.5rem; border-radius: 0.5rem; color: #a78bfa;">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <h2 style="font-size: 1.5rem; font-weight: 700;">AI Analysis</h2>
            </div>
            <button onclick="document.getElementById('ai-modal').style.display='none'" class="btn-secondary" style="padding: 0.5rem;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div id="ai-response" style="line-height: 1.8; color: var(--text-primary); font-size: 1.05rem;">
            <!-- AI Response will be injected here -->
        </div>
        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--card-border); display: flex; justify-content: space-between; align-items: center;">
            <div id="ai-status-container" style="display: flex; align-items: center; gap: 0.75rem;">
                <span id="ai-status" class="ai-badge-gradient" style="display: none;"></span>
                <span id="ai-cached-tag" style="font-size: 0.75rem; color: var(--text-muted);"></span>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button id="copy-ai-res" class="btn-secondary ai-action-btn" style="padding: 0.5rem 1rem; font-size: 0.8rem; border: none;" onclick="copyAiResponse()">
                     <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 012-2v-8a2 2 0 01-2-2h-8a2 2 0 01-2 2v8a2 2 0 012 2z"></path></svg>
                     COPY
                </button>
                <button onclick="document.getElementById('ai-modal').style.display='none'" class="btn" style="padding: 0.5rem 1.5rem;">CLOSE</button>
            </div>
        </div>
    </div>
</div>

<script>
    const CONFIG = {
        projectId: <?= json_encode($projectId) ?>,
        type: <?= json_encode($type) ?>,
        file: <?= json_encode($file) ?>,
        aiEnabled: <?= json_encode(AI_ENABLED) ?>,
        csrfToken: <?= json_encode(\App\Auth::generateCsrfToken()) ?>
    };
</script>
<script src="assets/js/viewer.js?v=<?= time() ?>"></script>
