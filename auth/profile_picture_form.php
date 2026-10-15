<?php declare(strict_types=1); ?>
<form class="profile-picture-form" method="post" enctype="multipart/form-data" action="<?= escapeHtml(applicationUrl('/auth/profile_picture_action.php')) ?>" data-profile-picture-form>
    <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="2097152">
    <?php if ($profilePictureShowHeading ?? true): ?><h3>Profile picture</h3><?php endif; ?>
    <div class="profile-picture-editor">
        <div class="profile-picture-summary">
        <span class="profile-picture-preview">
            <img data-profile-picture-preview src="<?= escapeHtml($profilePictureUrl !== '' ? $profilePictureUrl : applicationUrl('/assets/the-lewis-college-logo.png')) ?>" alt="Your profile picture preview" <?= $profilePictureUrl === '' ? 'hidden' : '' ?>>
            <span data-profile-picture-initials <?= $profilePictureUrl !== '' ? 'hidden' : '' ?>><?= escapeHtml($profilePictureInitials) ?></span>
        </span>
        <?php if (isset($profilePictureIdentity)): ?>
            <div class="profile-picture-identity">
                <span class="profile-role-badge"><?= escapeHtml($profilePictureIdentity['role']) ?></span>
                <h2 id="profile-identity-title"><?= escapeHtml($profilePictureIdentity['office']) ?></h2>
                <p class="profile-identity-email"><?= escapeHtml($profilePictureIdentity['email']) ?></p>
            </div>
        <?php endif; ?>
        </div>
        <div class="profile-picture-fields">
            <label class="profile-picture-field-label" for="profile-picture">Choose your photo</label>
            <div class="profile-picture-upload">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V3m-5 5 5-5 5 5M4 15v5a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-5"/></svg>
                <strong>Click to choose a photo</strong>
                <small id="profile-picture-help">JPG, PNG or WebP, up to 2 MB</small>
                <input id="profile-picture" name="profile_picture" type="file" accept="image/jpeg,image/png,image/webp" aria-describedby="profile-picture-help profile-picture-selection profile-picture-status" required>
            </div>
            <span class="profile-picture-selection" id="profile-picture-selection" data-profile-picture-selection aria-live="polite">No new picture selected</span>
        </div>
    </div>
    <div class="profile-picture-form-footer">
        <span>Preview your photo before saving.</span>
        <div class="profile-picture-buttons"><button class="profile-picture-cancel" type="button" data-profile-picture-reset hidden>Cancel</button><button type="submit"><?= $profilePictureUrl === '' ? 'Upload picture' : 'Update picture' ?></button></div>
    </div>
    <p class="profile-picture-status" id="profile-picture-status" role="status"></p>
</form>
