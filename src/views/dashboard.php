<?php
$msg = '';
$msgType = '';

if (\App\Auth::isAdmin()) {
    if (isset($_POST['add_project'])) {
        // Simple validation: at least one path
        if (empty($_POST['webserver_path']) && empty($_POST['php_path'])) {
            $msg = 'Please provide at least one log directory path.';
            $msgType = 'error';
        } else {
            $id = $projectManager->create([
                'name' => $_POST['name'],
                'webserver_path' => $_POST['webserver_path'],
                'php_path' => $_POST['php_path'],
                'webserver_format' => $_POST['webserver_format'],
                'php_format' => $_POST['php_format']
            ]);

            if ($id) {
                header("Location: index.php?msg=created");
                exit;
            } else {
                $msg = 'Failed to create project. Database error.';
                $msgType = 'error';
            }
        }
    } elseif (isset($_POST['edit_project'])) {
        // Edit logic
         if (empty($_POST['webserver_path']) && empty($_POST['php_path'])) {
            $msg = 'Please provide at least one log directory path.';
            $msgType = 'error';
        } else {
            $success = $projectManager->update((int)$_POST['id'], [
                'name' => $_POST['name'],
                'webserver_path' => $_POST['webserver_path'],
                'php_path' => $_POST['php_path'],
                'webserver_format' => $_POST['webserver_format'],
                'php_format' => $_POST['php_format']
            ]);

            if ($success) {
                header("Location: index.php?msg=updated");
                exit;
            } else {
                $msg = 'Failed to update project.';
                $msgType = 'error';
            }
        }
    } elseif (isset($_POST['delete_project'])) {
        // Delete logic
        if ($projectManager->delete((int)$_POST['id'])) {
            header("Location: index.php?msg=deleted");
            exit;
        } else {
            $msg = 'Failed to delete project.';
            $msgType = 'error';
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') {
        $msg = 'Project workspace initialized successfully.';
        $msgType = 'success';
    } elseif ($_GET['msg'] === 'updated') {
        $msg = 'Project updated successfully.';
        $msgType = 'success';
    } elseif ($_GET['msg'] === 'deleted') {
        $msg = 'Project deleted successfully.';
        $msgType = 'success';
    }
}

$projects = \App\Auth::isAdmin() ? $projectManager->getAll() : $projectManager->getUserProjects($_SESSION['user_id']);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3rem;" class="fade-in">
    <div>
        <h1 style="font-size: 2.5rem; font-weight: 800; letter-spacing: -1px;">Project Dashboard</h1>
        <p style="color: var(--text-secondary);">Select a project to analyze system and server logs</p>
    </div>
    
    <?php if ($msg): ?>
        <div class="glass-panel fade-in" style="position: fixed; top: 2rem; left: 50%; transform: translateX(-50%); padding: 1rem 2rem; background: <?= $msgType === 'error' ? 'rgba(239, 68, 68, 0.2)' : 'rgba(16, 185, 129, 0.2)' ?>; border: 1px solid <?= $msgType === 'error' ? 'rgba(239, 68, 68, 0.3)' : 'rgba(16, 185, 129, 0.3)' ?>; z-index: 2000; color: white;">
            <?= htmlspecialchars($msg) ?>
        </div>
        <script>setTimeout(() => document.querySelector('.glass-panel.fade-in').remove(), 4000);</script>
    <?php endif; ?>

    <?php if (\App\Auth::isAdmin()): ?>
        <button onclick="document.getElementById('add-modal').toggleAttribute('hidden')" style="padding: 0.875rem 1.5rem;">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path></svg>
            Add New Project
        </button>
    <?php endif; ?>
</div>

<div class="grid fade-in">
    <?php foreach ($projects as $project): ?>
        <div class="card glass-panel card-clickable" style="padding: 0; display: flex; flex-direction: column;">
            <!-- Clickable Main Body -->
            <div onclick="window.location='index.php?action=project&id=<?= $project->id ?>'" style="padding: 2rem; padding-bottom: 1rem; cursor: pointer; flex: 1;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
                    <div style="background: var(--primary-glow); padding: 0.75rem; border-radius: 1rem; color: var(--primary);">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <?php if ($project->webserver_path): ?><span class="badge" style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9;">Web</span><?php endif; ?>
                        <?php if ($project->php_path): ?><span class="badge" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">PHP</span><?php endif; ?>
                    </div>
                </div>
                
                <h3 style="margin-bottom: 0.5rem;"><?= htmlspecialchars($project->name) ?></h3>
                <div style="margin: 1.5rem 0; font-size: 0.8rem; color: var(--text-secondary); background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 0.75rem; border: 1px solid var(--card-border);">
                    <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <strong style="color: var(--text-primary); min-width: 40px;">WEB:</strong> <?= htmlspecialchars($project->webserver_path ?: '---') ?>
                    </div>
                    <div style="display: flex; gap: 0.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <strong style="color: var(--text-primary); min-width: 40px;">PHP:</strong> <?= htmlspecialchars($project->php_path ?: '---') ?>
                    </div>
                </div>
            </div>
            
            <!-- Footer Actions (Non-clickable container) -->
            <div style="padding: 1rem 2rem; padding-top: 0; display: flex; align-items: center; justify-content: space-between; position: relative; z-index: 50;">
                <a href="index.php?action=project&id=<?= $project->id ?>" class="btn-text" style="color: var(--primary); font-weight: 700; font-size: 0.9rem; text-decoration: none;">Inspect Logs &rarr;</a>
                
               <?php if (\App\Auth::isAdmin()): ?>
                    <div style="display: flex; gap: 0.5rem;" onclick="event.stopPropagation()">
                        <a href="index.php?action=analytics&project_id=<?= $project->id ?>" class="btn-icon" style="padding: 0.4rem; background: rgba(99, 102, 241, 0.1); border-radius: 0.5rem; color: #a5b4fc;" title="Visual Analytics">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        </a>
                        <button type="button" onclick="openEditModal(<?= htmlspecialchars(json_encode($project), ENT_QUOTES, 'UTF-8') ?>)" class="btn-icon" style="padding: 0.4rem; background: rgba(255,255,255,0.1); border-radius: 0.5rem; color: var(--text-secondary);">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </button>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this project?');" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateCsrfToken() ?>">
                            <input type="hidden" name="id" value="<?= $project->id ?>">
                            <input type="hidden" name="delete_project" value="1">
                            <button type="submit" class="btn-icon" style="padding: 0.4rem; background: rgba(239, 68, 68, 0.1); border-radius: 0.5rem; color: #fca5a5;">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <a href="index.php?action=project&id=<?= $project->id ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($projects)): ?>
        <div style="grid-column: 1/-1; text-align: center; padding: 6rem; background: rgba(255,255,255,0.02); border-radius: 1.5rem; border: 2px dashed var(--card-border);" class="fade-in">
            <svg width="48" height="48" style="color: var(--text-secondary); margin-bottom: 1.5rem;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            <h3 style="color: var(--text-primary)">No workspace found</h3>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">Start by adding your first project log directory</p>
            <?php if (\App\Auth::isAdmin()): ?>
                <button onclick="document.getElementById('add-modal').toggleAttribute('hidden')" style="margin-top: 2rem; background: transparent; border: 1px solid var(--primary); color: var(--primary);">Create New Project</button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (\App\Auth::isAdmin()): ?>
<!-- Add Modal -->
<div id="add-modal" hidden style="position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000;" class="fade-in">
    <div class="glass-panel" style="padding: 2.5rem; width: 100%; max-width: 520px; animation: slideUp 0.4s ease-out;">
        <h2 style="font-size: 1.75rem; margin-bottom: 0.5rem;">New Project Configuration</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem; font-size: 0.9rem;">Map server directories to monitor logs</p>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateCsrfToken() ?>">
            <div class="form-group">
                <label>Project Workspace Name</label>
                <input type="text" name="name" required placeholder="e.g. Production API">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Nginx/Apache Logs</label>
                    <input type="text" name="webserver_path" placeholder="/var/log/nginx/">
                </div>
                <div class="form-group">
                    <label>PHP System Logs</label>
                    <input type="text" name="php_path" placeholder="/var/log/php/">
                </div>
            </div>
             <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Web Log Format (Regex)</label>
                    <input type="text" name="webserver_format" placeholder="Optional parser">
                </div>
                <div class="form-group">
                    <label>PHP Log Format (Regex)</label>
                    <input type="text" name="php_format" placeholder="Optional parser">
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" name="add_project" style="flex: 1; justify-content: center;">Initialize Workspace</button>
                <button type="button" onclick="document.getElementById('add-modal').toggleAttribute('hidden')" class="btn-secondary" style="flex: 1; justify-content: center;">Dismiss</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="edit-modal" hidden style="position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000;" class="fade-in">
    <div class="glass-panel" style="padding: 2.5rem; width: 100%; max-width: 520px; animation: slideUp 0.4s ease-out;">
        <h2 style="font-size: 1.75rem; margin-bottom: 0.5rem;">Edit Project</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem; font-size: 0.9rem;">Update workspace configuration</p>
        
        <form method="POST" id="edit-form">
            <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateCsrfToken() ?>">
            <input type="hidden" name="id" id="edit-id">
            <input type="hidden" name="edit_project" value="1">
            
            <div class="form-group">
                <label>Project Workspace Name</label>
                <input type="text" name="name" id="edit-name" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Nginx/Apache Logs</label>
                    <input type="text" name="webserver_path" id="edit-webserver_path">
                </div>
                <div class="form-group">
                    <label>PHP System Logs</label>
                    <input type="text" name="php_path" id="edit-php_path">
                </div>
            </div>
             <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Web Log Format (Regex)</label>
                    <input type="text" name="webserver_format" id="edit-webserver_format">
                </div>
                <div class="form-group">
                    <label>PHP Log Format (Regex)</label>
                    <input type="text" name="php_format" id="edit-php_format">
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" style="flex: 1; justify-content: center;">Save Changes</button>
                <button type="button" onclick="document.getElementById('edit-modal').toggleAttribute('hidden')" class="btn-secondary" style="flex: 1; justify-content: center;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(project) {
        document.getElementById('edit-id').value = project.id;
        document.getElementById('edit-name').value = project.name;
        document.getElementById('edit-webserver_path').value = project.webserver_path || '';
        document.getElementById('edit-php_path').value = project.php_path || '';
        document.getElementById('edit-webserver_format').value = project.webserver_format || '';
        document.getElementById('edit-php_format').value = project.php_format || '';
        
        document.getElementById('edit-modal').removeAttribute('hidden');
    }
</script>
<?php endif; ?>
