<?php
declare(strict_types=1);

// Rendered inside the student's Home view; office actions retain their existing locks.
$preparationReminders = studentDashboardPreparationReminders([
    'period' => $period,
    'enrolled' => $isEnrolled,
    'stations' => $stations,
    'statuses' => $displayStationStatuses,
    'medical_status' => $medicalStatus,
    'appointment' => $medicalAppointment,
    'accounting' => $accountingObligations ?? [],
    'books' => $libraryBooks ?? [],
    'facilities' => $facilitiesItems,
    'violations' => $openViolations ?? [],
    'sanctions' => $sanctionGroups ?? [],
    'bookstore' => $bookstoreFees,
]);
?>
<section class="portal-attention" data-clearance-live="reminders" data-reminders-version="<?= hash('sha256', json_encode($preparationReminders, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR)) ?>" <?= $preparationReminders !== [] ? 'aria-labelledby="preparation-title"' : 'hidden' ?>>
    <?php if ($preparationReminders !== []): ?>
        <div class="portal-section-title"><div><span class="portal-card-eyebrow">BEFORE CLEARANCE SIGNING</span><h2 id="preparation-title">A few things to check</h2><p>Prepare ahead for a smoother visit to each office. Complete the items that apply to you.</p></div><span class="portal-count-badge"><?= count($preparationReminders) ?> reminders</span></div>
        <div class="portal-reminder-list">
            <?php foreach ($preparationReminders as $reminder): ?>
                <details class="portal-alert <?= $reminder['attention'] ? 'has-outstanding-items' : 'is-preparation' ?>" data-reminder-key="<?= escapeHtml($reminder['key']) ?>">
                    <summary><?= studentDashboardIcon($reminder['icon']) ?><span><strong><?= escapeHtml($reminder['title']) ?></strong><small><?= escapeHtml($reminder['hint']) ?></small></span><?= studentDashboardIcon('chevron') ?></summary>
                    <div class="portal-reminder-body">
                        <p><?= escapeHtml($reminder['guidance']) ?></p>
                        <?php if ($reminder['items'] !== []): ?><ul><?php foreach ($reminder['items'] as $item): ?><li><?= escapeHtml($item) ?></li><?php endforeach; ?></ul><?php endif; ?>
                        <?php if ($reminder['modal'] !== null): ?><button type="button" class="portal-reminder-action" data-requirements-modal="requirements-modal-<?= (int) $reminder['modal'] ?>">View office requirements <?= studentDashboardIcon('arrow') ?></button><?php elseif ($reminder['attendance']): ?><a class="portal-reminder-action" href="#attendance" data-dashboard-link="attendance">View attendance and sanctions <?= studentDashboardIcon('arrow') ?></a><?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
