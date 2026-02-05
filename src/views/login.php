<div class="auth-container">
    <div class="auth-card glass-panel">
        <div style="text-align: center; margin-bottom: 2.5rem;">
            <div class="logo" style="font-size: 2.5rem; margin-bottom: 0.5rem;">LOGGER<span style="color: var(--text-primary); font-weight: 300;">VIEW</span></div>
            <p style="color: var(--text-secondary); font-size: 0.9rem;">Sign in to your administration dashboard</p>
        </div>
        
        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.75rem;">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php 
        $mode = $_GET['mode'] ?? DEFAULT_AUTH_TYPE; 
        ?>

        <form method="POST" action="index.php?action=login">
            <input type="hidden" name="csrf_token" value="<?= \App\Auth::generateCsrfToken() ?>">
            <input type="hidden" name="login_mode" value="<?= htmlspecialchars($mode) ?>">
            
            <?php if ($otpPending): ?>
                <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); color: #93c5fd; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 2rem; font-size: 0.9rem; text-align: center;">
                    <p style="margin-bottom: 0.5rem; font-weight: 600;">Verification Required</p>
                    We've sent a 6-digit OTP code to your email.
                </div>
                <div class="form-group">
                    <label for="otp">Enter OTP Code</label>
                    <input type="text" id="otp" name="otp" required autofocus placeholder="000000" maxlength="6" style="text-align: center; letter-spacing: 0.5rem; font-size: 1.5rem; font-weight: 700;">
                </div>
                <button type="submit" style="width: 100%; margin-top: 1rem; justify-content: center;">Verify & Login</button>
            <?php elseif ($mode === 'otp'): ?>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autocomplete="username" placeholder="Enter your username">
                </div>
                <button type="submit" style="width: 100%; margin-top: 1rem; justify-content: center;">Send Login OTP</button>
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="index.php?action=login&mode=password" style="color: var(--text-secondary); font-size: 0.85rem; text-decoration: none; border-bottom: 1px dashed rgba(255,255,255,0.2);">Use Password Login</a>
                </div>
            <?php else: ?>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autocomplete="username" placeholder="Enter your username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••">
                </div>
                <button type="submit" style="width: 100%; margin-top: 1rem; justify-content: center;">Sign In</button>
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="index.php?action=login&mode=otp" style="color: var(--text-secondary); font-size: 0.85rem; text-decoration: none; border-bottom: 1px dashed rgba(255,255,255,0.2);">Use Email OTP Login</a>
                </div>
            <?php endif; ?>

            <?php if ($otpPending): ?>
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="index.php?action=login" style="color: var(--text-secondary); font-size: 0.8rem; text-decoration: none;">← Back to login</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>
