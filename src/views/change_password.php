<div class="auth-container">
    <div class="auth-card glass-panel">
        <div style="text-align: center; margin-bottom: 2.5rem;">
            <div class="logo" style="font-size: 2.5rem; margin-bottom: 0.5rem;">LOGGER<span style="color: var(--text-primary); font-weight: 300;">VIEW</span></div>
            <p style="color: var(--text-secondary); font-size: 0.9rem;">You must change your default password before continuing</p>
        </div>

        <?php if (!empty($cpError)): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem; font-size: 0.85rem;">
                <?= htmlspecialchars($cpError, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php?action=change_password">
            <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateCsrfToken() ?>">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required autocomplete="new-password" placeholder="••••••••">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password" placeholder="••••••••">
            </div>
            <button type="submit" style="width: 100%; margin-top: 1rem; justify-content: center;">Update Password</button>
        </form>
    </div>
</div>
