<?php
$msg = '';
$msgType = '';

// Strict Admin Check
if (!\App\Auth::isAdmin()) {
    header('Location: index.php');
    exit;
}

$envPath = dirname(dirname(__DIR__)) . '/.env';
$isWritable = is_writable($envPath);

if (!$isWritable) {
    $msg = "Warning: The .env file is not writable. Settings cannot be saved.";
    $msgType = "error";
}

if (isset($_GET['success'])) {
    $msg = 'Settings updated successfully.';
    $msgType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!$isWritable) {
        $msg = "Error: Cannot save settings because .env is read-only.";
        $msgType = "error";
    } else {
        $newSecret = $_POST['auth_secret'] ?? AUTH_SECRET;
        
        $settings = [
            'APP_TITLE' => $_POST['app_title'] ?? 'Logger View',
            'DB_PATH' => $_POST['db_path'] ?? 'data/database.sqlite',
            'AUTH_SECRET' => $newSecret,
            
            'SMTP_HOST' => $_POST['smtp_host'] ?? '',
            'SMTP_PORT' => $_POST['smtp_port'] ?? '587',
            'SMTP_ENCRYPTION' => $_POST['smtp_encryption'] ?? 'tls',
            'SMTP_USER' => $_POST['smtp_user'] ?? '',
            'SMTP_PASS' => \App\Encryption::encrypt($_POST['smtp_pass'] ?? '', $newSecret),
            'SMTP_FROM_EMAIL' => $_POST['smtp_from_email'] ?? '',
            'SMTP_FROM_NAME' => $_POST['smtp_from_name'] ?? 'Logger View',
            
            'AI_ENABLED' => isset($_POST['ai_enabled']) ? 'true' : 'false',
            'AI_API_URL' => $_POST['ai_api_url'] ?? 'https://api.openai.com/v1/chat/completions',
            'AI_API_KEY' => \App\Encryption::encrypt($_POST['ai_api_key'] ?? '', $newSecret),
            'AI_MODEL' => $_POST['ai_model'] ?? 'gpt-4-turbo',
            
            'DEFAULT_AUTH_TYPE' => $_POST['default_auth_type'] ?? 'otp',
            'REDIS_HOST' => $_POST['redis_host'] ?? '127.0.0.1',
            'REDIS_PORT' => $_POST['redis_port'] ?? '6379',
        ];

        if (\App\ConfigManager::updateEnv($settings)) {
            header('Location: index.php?action=settings&success=1');
            exit;
        } else {
            $msg = 'Failed to update settings. Check file permissions for .env';
            $msgType = 'error';
        }
    }
}
?>

<div style="margin-bottom: 3rem;" class="fade-in">
    <h1 style="font-size: 2.5rem; font-weight: 800; letter-spacing: -1px;">System Settings</h1>
    <p style="color: var(--text-secondary);">Global application configurations (Admin Only)</p>
</div>

<?php if ($msg): ?>
    <div class="glass-panel fade-in" style="margin-bottom: 2rem; padding: 1.25rem 2rem; background: <?= $msgType === 'error' ? 'rgba(239, 68, 68, 0.2)' : 'rgba(16, 185, 129, 0.2)' ?>; border: 1px solid <?= $msgType === 'error' ? 'rgba(239, 68, 68, 0.3)' : 'rgba(16, 185, 129, 0.3)' ?>; color: white; border-radius: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <?php if($msgType === 'success'): ?>
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
            <?php else: ?>
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
            <?php endif; ?>
            <span><?= htmlspecialchars($msg) ?></span>
        </div>
    </div>
<?php endif; ?>

<form method="POST" class="fade-in" id="settings-form">
    <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateCsrfToken() ?>">
    
    <div style="display: flex; flex-direction: column; gap: 2.5rem;">
        
        <!-- General & Database -->
        <div class="card glass-panel" style="padding: 2rem;">
            <div style="position: relative; z-index: 10;">
                 <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem;">
                    <div style="background: rgba(59, 130, 246, 0.1); padding: 0.5rem; border-radius: 0.5rem; color: #60a5fa;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <h2 style="font-size: 1.5rem; font-weight: 700;">General Configuration</h2>
                </div>
                
                <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div>
                        <div class="form-group">
                            <label>Application Title</label>
                            <input type="text" name="app_title" value="<?= htmlspecialchars(APP_TITLE) ?>">
                        </div>
                        <div class="form-group">
                            <label>Auth Secret (Used for Password Pepper & Encryption)</label>
                            <input type="password" name="auth_secret" value="<?= htmlspecialchars(AUTH_SECRET) ?>">
                            <p style="font-size: 0.75rem; color: #f87171; margin-top: 0.5rem;">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:inline; vertical-align:middle;"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                WARNING: Changing this will invalidate ALL existing passwords and encrypted API keys.
                            </p>
                        </div>
                    </div>
                    <div>
                        <div class="form-group">
                            <label>SQLite Database Path</label>
                            <input type="text" name="db_path" value="<?= htmlspecialchars(getenv('DB_PATH') ?: 'data/database.sqlite') ?>">
                        </div>
                        <div class="form-group">
                            <label>Default Auth Mode</label>
                            <select name="default_auth_type" style="width: 100%; padding: 0.875rem; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--card-border); border-radius: 0.75rem; color: white;">
                                <option value="password" <?= DEFAULT_AUTH_TYPE === 'password' ? 'selected' : '' ?>>Password Only</option>
                                <option value="otp" <?= DEFAULT_AUTH_TYPE === 'otp' ? 'selected' : '' ?>>Email OTP</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 2.5rem;">
            <!-- SMTP Settings -->
            <div class="card glass-panel" style="padding: 2rem;">
                <div style="position: relative; z-index: 10;">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem;">
                        <div style="background: rgba(16, 185, 129, 0.1); padding: 0.5rem; border-radius: 0.5rem; color: #34d399;">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </div>
                        <h2 style="font-size: 1.25rem; font-weight: 700;">SMTP (Mailing)</h2>
                    </div>

                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="smtp_host" value="<?= htmlspecialchars(SMTP_HOST) ?>" placeholder="e.g. smtp.gmail.com">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>Port</label>
                            <input type="number" name="smtp_port" value="<?= htmlspecialchars(SMTP_PORT) ?>">
                        </div>
                        <div class="form-group">
                            <label>Encryption</label>
                            <select name="smtp_encryption" style="width: 100%; padding: 0.875rem; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--card-border); border-radius: 0.75rem; color: white;">
                                <option value="tls" <?= SMTP_ENCRYPTION === 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= SMTP_ENCRYPTION === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                <option value="none" <?= SMTP_ENCRYPTION === 'none' ? 'selected' : '' ?>>None</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="smtp_user" value="<?= htmlspecialchars(SMTP_USER) ?>">
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="smtp_pass" value="<?= htmlspecialchars(\App\Encryption::decrypt(SMTP_PASS)) ?>">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label>From Email</label>
                            <input type="email" name="smtp_from_email" value="<?= htmlspecialchars(SMTP_FROM_EMAIL) ?>">
                        </div>
                        <div class="form-group">
                            <label>From Name</label>
                            <input type="text" name="smtp_from_name" value="<?= htmlspecialchars(SMTP_FROM_NAME) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- AI & Redis -->
            <div style="display: flex; flex-direction: column; gap: 2.5rem;">
                <div class="card glass-panel" style="padding: 2rem;">
                    <div style="position: relative; z-index: 10;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem;">
                            <div style="background: rgba(139, 92, 246, 0.1); padding: 0.5rem; border-radius: 0.5rem; color: #a78bfa;">
                                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <h2 style="font-size: 1.25rem; font-weight: 700;">AI Analysis</h2>
                        </div>

                        <div class="form-group" style="display: flex; align-items: center; gap: 1rem; background: rgba(139, 92, 246, 0.05); padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem; border: 1px solid rgba(139, 92, 246, 0.1);">
                            <div style="flex: 1;">
                                <label style="margin-bottom: 0; color: white; font-weight: 600;">Enable AI Assistant</label>
                                <p style="font-size: 0.75rem; color: var(--text-muted); margin: 0;">Log scanning & summarization</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="ai_enabled" <?= AI_ENABLED ? 'checked' : '' ?>>
                                <span class="slider round"></span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label>API URL</label>
                            <input type="text" name="ai_api_url" value="<?= htmlspecialchars(AI_API_URL) ?>">
                        </div>

                        <div class="form-group">
                            <label>API Key</label>
                            <input type="password" name="ai_api_key" value="<?= htmlspecialchars(\App\Encryption::decrypt(AI_API_KEY)) ?>">
                        </div>

                        <div class="form-group">
                            <label>Model</label>
                            <input type="text" name="ai_model" value="<?= htmlspecialchars(AI_MODEL) ?>">
                        </div>
                    </div>
                </div>

                <div class="card glass-panel" style="padding: 2rem;">
                    <div style="position: relative; z-index: 10;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;">
                            <div style="background: rgba(239, 68, 68, 0.1); padding: 0.5rem; border-radius: 0.5rem; color: #f87171;">
                                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 7v10c0 1.1.9 2 2 2h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2z"></path><path d="M7 12h10M7 16h10"></path></svg>
                            </div>
                            <h2 style="font-size: 1.25rem; font-weight: 700;">Caching (Redis)</h2>
                        </div>
                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Host</label>
                                <input type="text" name="redis_host" value="<?= htmlspecialchars(REDIS_HOST) ?>">
                            </div>
                            <div class="form-group">
                                <label>Port</label>
                                <input type="number" name="redis_port" value="<?= htmlspecialchars(REDIS_PORT) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="position: sticky; bottom: 2rem; margin-top: 4rem; display: flex; justify-content: flex-end; z-index: 50;">
        <button type="submit" name="save_settings" style="padding: 1rem 4rem; font-size: 1.1rem; box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.4);" <?= !$isWritable ? 'disabled' : '' ?>>
            <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
            Save All Configurations
        </button>
    </div>
</form>

<style>
/* Verification: Ensure inputs are NOT readonly unless disabled globally */
input:not([disabled]) {
    cursor: text !important;
    pointer-events: auto !important;
}

/* Toggle Switch Style */
.switch {
  position: relative;
  display: inline-block;
  width: 50px;
  height: 24px;
}

.switch input { 
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(255,255,255,0.1);
  transition: .4s;
  border: 1px solid var(--card-border);
}

.slider:before {
  position: absolute;
  content: "";
  height: 16px;
  width: 16px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: .4s;
}

input:checked + .slider {
  background-color: var(--success);
}

input:focus + .slider {
  box-shadow: 0 0 1px var(--success);
}

input:checked + .slider:before {
  transform: translateX(26px);
}

.slider.round {
  border-radius: 34px;
}

.slider.round:before {
  border-radius: 50%;
}

button[disabled] {
    opacity: 0.5;
    cursor: not-allowed;
    filter: grayscale(1);
}
</style>
