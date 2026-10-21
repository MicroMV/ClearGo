<?php
declare(strict_types=1);
if (!isset($csrfToken, $stage, $action, $showRecovery)) { http_response_code(403); exit('Forbidden'); }
?>
<dialog id="password-recovery-modal" class="recovery-modal" aria-labelledby="recovery-title" aria-describedby="recovery-intro" data-auto-open="<?= $showRecovery ? 'true' : 'false' ?>">
    <section class="recovery-card">
        <button class="recovery-close" type="button" data-close-recovery aria-label="Close password recovery">&times;</button>
        <p class="recovery-eyebrow">Student account recovery</p>
        <h2 id="recovery-title"><?= ['email' => 'Forgot your password?', 'verify' => 'Check your email', 'reset' => 'Choose a new password'][$stage] ?></h2>
        <?php if ($recoveryMessage !== null): ?><p class="recovery-message" role="status"><?= escapeHtml($recoveryMessage) ?></p><?php endif; ?>
        <?php if ($stage === 'email'): ?>
            <p id="recovery-intro" class="intro">Enter your school email to request a reset code.</p>
            <form method="post" action="<?= $action ?>">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="request">
                <label for="recovery-email">School email</label>
                <input id="recovery-email" name="email" type="email" autocomplete="email" maxlength="254" placeholder="name@thelewiscollege.edu.ph" pattern=".+@thelewiscollege\.edu\.ph" required data-recovery-focus>
                <button type="submit">Send reset code</button>
            </form>
            <p class="form-note">For students only. Office staff should contact the Registrar.</p>
        <?php elseif ($stage === 'verify'): ?>
            <p id="recovery-intro" class="intro">Enter the six-digit code sent to <strong><?= escapeHtml($flow['email']) ?></strong>. Codes expire after 10 minutes. Delivery may take a minute.</p>
            <form method="post" action="<?= $action ?>">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="verify">
                <label for="recovery-code">Email code</label>
                <input class="recovery-code" id="recovery-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required data-recovery-focus>
                <button type="submit">Verify code</button>
            </form>
            <form class="recovery-secondary" method="post" action="<?= $action ?>">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                <button class="secondary" name="action" value="resend">Resend code</button><button class="secondary" name="action" value="start_over">Use another email</button>
            </form>
        <?php else: ?>
            <p id="recovery-intro" class="intro">Use 8 to 72 characters. Your other sign-ins will be signed out.</p>
            <form method="post" action="<?= $action ?>">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="reset">
                <label for="new-password">New password</label><input id="new-password" name="new_password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required data-recovery-focus>
                <label for="confirm-password">Confirm new password</label><input id="confirm-password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required>
                <button type="submit">Reset password</button>
            </form>
        <?php endif; ?>
        <button type="button" class="recovery-back" data-close-recovery>Back to sign in</button>
    </section>
</dialog>
