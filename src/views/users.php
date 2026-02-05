<?php
$msg = '';
$msgType = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $userId = $userManager->create(
            $_POST['username'], 
            $_POST['password'], 
            $_POST['role'], 
            $_POST['email'] ?? '', 
            $_POST['auth_type'] ?? 'password'
        );
        if ($userId) {
            $projectIds = $_POST['project_ids'] ?? [];
            $projectManager->setUserProjects((int)$userId, $projectIds);
            header("Location: index.php?action=users&msg=created");
            exit;
        } else {
            $msg = 'Failed to create user. Username may already exist.';
            $msgType = 'error';
        }
    } elseif (isset($_POST['edit_user'])) {
        $data = [
            'role' => $_POST['role'],
            'email' => $_POST['email'] ?? '',
            'auth_type' => $_POST['auth_type'] ?? 'password'
        ];
        if (!empty($_POST['password'])) {
            $data['password'] = $_POST['password'];
        }
        if ($userManager->update((int)$_POST['id'], $data)) {
            $projectIds = $_POST['project_ids'] ?? [];
            $projectManager->setUserProjects((int)$_POST['id'], $projectIds);
            header("Location: index.php?action=users&msg=updated");
            exit;
        } else {
            $msg = 'Failed to update user.';
            $msgType = 'error';
        }
    } elseif (isset($_POST['delete_user'])) {
        if ($userManager->delete((int)$_POST['id'])) {
            header("Location: index.php?action=users&msg=deleted");
            exit;
        } else {
            $msg = 'Failed to delete user. You cannot delete yourself.';
            $msgType = 'error';
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') {
        $msg = 'User created successfully.';
        $msgType = 'success';
    } elseif ($_GET['msg'] === 'updated') {
        $msg = 'User updated successfully.';
        $msgType = 'success';
    } elseif ($_GET['msg'] === 'deleted') {
        $msg = 'User deleted successfully.';
        $msgType = 'success';
    }
}

$users = $userManager->getAll();
$allProjects = $projectManager->getAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3rem;" class="fade-in">
    <div>
        <h1 style="font-size: 2.5rem; font-weight: 800; letter-spacing: -1px;">User Management</h1>
        <p style="color: var(--text-secondary);">Manage system access and roles</p>
    </div>
    
    <?php if ($msg): ?>
        <div class="glass-panel fade-in" style="position: fixed; top: 2rem; left: 50%; transform: translateX(-50%); padding: 1rem 2rem; background: <?= $msgType === 'error' ? 'rgba(239, 68, 68, 0.2)' : 'rgba(16, 185, 129, 0.2)' ?>; border: 1px solid <?= $msgType === 'error' ? 'rgba(239, 68, 68, 0.3)' : 'rgba(16, 185, 129, 0.3)' ?>; z-index: 2000; color: white;">
            <?= htmlspecialchars($msg) ?>
        </div>
        <script>setTimeout(() => document.querySelector('.glass-panel.fade-in').remove(), 4000);</script>
    <?php endif; ?>

    <button onclick="document.getElementById('add-user-modal').toggleAttribute('hidden')" style="padding: 0.875rem 1.5rem;">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z"></path></svg>
        Add User
    </button>
</div>

<div class="grid fade-in">
    <?php foreach ($users as $user): ?>
        <div class="card glass-panel" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                 <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                    <div style="background: var(--primary-glow); padding: 0.75rem; border-radius: 50%; color: var(--primary); width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.2rem;">
                        <?= strtoupper(substr($user->username, 0, 1)) ?>
                    </div>
                    <span class="badge badge-<?= $user->role ?>"><?= ucfirst($user->role) ?></span>
                </div>
                <h3 style="margin-bottom: 0.5rem;"><?= htmlspecialchars($user->username) ?></h3>
                <p style="color: var(--text-secondary); font-size: 0.8rem; margin-bottom: 0.25rem;">ID: #<?= $user->id ?></p>
                <div style="font-size: 0.75rem; color: var(--text-muted);">
                    <strong>Projects:</strong>
                    <?php 
                    $userProjectIds = $projectManager->getUserProjectIds((int)$user->id);
                    $assignedNames = [];
                    foreach ($allProjects as $p) {
                        if (in_array((int)$p->id, $userProjectIds)) $assignedNames[] = htmlspecialchars($p->name);
                    }
                    echo !empty($assignedNames) ? implode(', ', $assignedNames) : 'None';
                    ?>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem; display: flex; gap: 0.5rem; justify-content: flex-end; position: relative; z-index: 50;" onclick="event.stopPropagation()">
                 <button type="button" onclick="openEditUserModal(<?= htmlspecialchars(json_encode([
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'auth_type' => $user->auth_type,
                    'project_ids' => $userProjectIds
                 ]), ENT_QUOTES, 'UTF-8') ?>)" class="btn-icon" style="padding: 0.5rem; background: rgba(255,255,255,0.05);">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                </button>
                <?php if ($user->id != $_SESSION['user_id']): ?>
                    <form method="POST" onsubmit="return confirm('Delete this user?');" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateCsrfToken() ?>">
                        <input type="hidden" name="id" value="<?= $user->id ?>">
                        <input type="hidden" name="delete_user" value="1">
                        <button type="submit" class="btn-icon" style="padding: 0.5rem; background: rgba(239, 68, 68, 0.1); color: #fca5a5;">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Add User Modal -->
<div id="add-user-modal" hidden style="position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000;" class="fade-in">
    <div class="glass-panel" style="padding: 2.5rem; width: 100%; max-width: 450px; animation: slideUp 0.4s ease-out;">
        <h2 style="font-size: 1.75rem; margin-bottom: 0.5rem;">Add User</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem; font-size: 0.9rem;">Create a new account</p>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateCsrfToken() ?>">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Email (Required for OTP)</label>
                <input type="email" name="email">
            </div>
             <div class="form-group">
                <label>Password (Required for Password Access)</label>
                <input type="password" name="password">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" style="width: 100%; padding: 0.875rem; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--card-border); border-radius: 0.75rem; color: white;">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Access Type</label>
                    <select name="auth_type" style="width: 100%; padding: 0.875rem; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--card-border); border-radius: 0.75rem; color: white;">
                        <option value="password">Password</option>
                        <option value="otp" selected>Email OTP</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Assigned Projects</label>
                <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--card-border); border-radius: 0.75rem; padding: 1rem; max-height: 150px; overflow-y: auto;">
                    <?php foreach ($allProjects as $project): ?>
                        <label style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="project_ids[]" value="<?= $project->id ?>" style="width: auto;">
                            <span><?= htmlspecialchars($project->name) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" name="add_user" style="flex: 1; justify-content: center;">Create User</button>
                <button type="button" onclick="document.getElementById('add-user-modal').toggleAttribute('hidden')" class="btn-secondary" style="flex: 1; justify-content: center;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="edit-user-modal" hidden style="position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 1000;" class="fade-in">
    <div class="glass-panel" style="padding: 2.5rem; width: 100%; max-width: 450px; animation: slideUp 0.4s ease-out;">
        <h2 style="font-size: 1.75rem; margin-bottom: 0.5rem;">Edit User</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem; font-size: 0.9rem;">Update account details</p>
        
        <form method="POST" id="edit-user-form">
            <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateCsrfToken() ?>">
            <input type="hidden" name="id" id="edit-user-id">
            <input type="hidden" name="edit_user" value="1">
            
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="edit-user-username" disabled style="opacity: 0.5; cursor: not-allowed;">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="edit-user-email">
            </div>
             <div class="form-group">
                <label>New Password (leave blank to keep current)</label>
                <input type="password" name="password" placeholder="New password">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="edit-user-role" style="width: 100%; padding: 0.875rem; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--card-border); border-radius: 0.75rem; color: white;">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Access Type</label>
                    <select name="auth_type" id="edit-user-auth-type" style="width: 100%; padding: 0.875rem; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--card-border); border-radius: 0.75rem; color: white;">
                        <option value="password">Password</option>
                        <option value="otp">Email OTP</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Assigned Projects</label>
                <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid var(--card-border); border-radius: 0.75rem; padding: 1rem; max-height: 150px; overflow-y: auto;">
                    <?php foreach ($allProjects as $project): ?>
                        <label style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; cursor: pointer;">
                            <input type="checkbox" name="project_ids[]" value="<?= $project->id ?>" class="edit-user-project-checkbox" style="width: auto;">
                            <span><?= htmlspecialchars($project->name) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" style="flex: 1; justify-content: center;">Save Changes</button>
                <button type="button" onclick="document.getElementById('edit-user-modal').toggleAttribute('hidden')" class="btn-secondary" style="flex: 1; justify-content: center;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditUserModal(user) {
        document.getElementById('edit-user-id').value = user.id;
        document.getElementById('edit-user-username').value = user.username;
        document.getElementById('edit-user-email').value = user.email || '';
        document.getElementById('edit-user-role').value = user.role;
        document.getElementById('edit-user-auth-type').value = user.auth_type || 'password';
        
        // Reset and Set Project Checkboxes
        const checkboxes = document.querySelectorAll('.edit-user-project-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = user.project_ids.includes(parseInt(cb.value));
        });

        document.getElementById('edit-user-modal').removeAttribute('hidden');
    }
</script>
