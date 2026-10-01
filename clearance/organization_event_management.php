                <section class="panel">
                    <h2>QR Event Attendance</h2>
                    <p>Create each required <?= $organization['code'] === 'CBE' ? 'YBC' : ($organization['code'] === 'CTE' ? 'FEO' : 'CCS organization') ?> event. During the event, open a short-lived QR code for students to scan.</p>
                    <form class="attendance-controls" method="post" action="<?= escapeHtml(applicationUrl('/organization/event_action.php')) ?>">
                        <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="create">
                        <label>Event name<input name="name" maxlength="200" required placeholder="Required organization event"></label>
                        <label>Date<input name="event_date" type="date" required value="<?= escapeHtml(date('Y-m-d')) ?>"></label>
                        <button type="submit">Create Required Event</button>
                    </form>
                    <?php if ($events === []): ?><p class="muted">No required events have been created for this semester. Until an event is created, direct organization sign-off remains available.</p><?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Date / Time</th>
                                        <th>Attendance</th>
                                        <th>Attendance time limit</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?><?php $qrOpen = (int) $event['qr_is_open'] === 1; ?><tr>
                                        <td><strong><?= escapeHtml($event['name']) ?></strong></td>
                                        <td><?= escapeHtml($event['event_date']) ?><br><span class="muted"><?= escapeHtml(trim(($event['start_time'] ?? '') . ' - ' . ($event['end_time'] ?? ''), ' -')) ?></span></td>
                                        <td><?= (int) $event['present_count'] ?> / <?= (int) $event['enrolled_count'] ?> present</td>
                                        <td><?php if ($qrOpen): ?><span class="muted">&mdash;</span><?php else: ?><select form="open-organization-qr-<?= (int) $event['id'] ?>" name="minutes" aria-label="Attendance time limit">
                                                            <option value="5">5 minutes</option>
                                                            <option value="10" selected>10 minutes</option>
                                                            <option value="15">15 minutes</option>
                                                            <option value="30">30 minutes</option>
                                                            <option value="60">1 hour</option>
                                                            <option value="120">2 hours</option>
                                                            <option value="240">4 hours</option>
                                                            <option value="480">8 hours</option>
                                                            <option value="720">12 hours</option>
                                                        </select><?php endif; ?></td>
                                        <td><div class="event-actions"><?php if ($qrOpen): ?><?php if ((int) ($qrDisplay['event_id'] ?? 0) === (int) $event['id']): ?><a class="button-link" href="<?= escapeHtml(applicationUrl('/organization/event_qr.php?event=' . (int) $event['id'])) ?>">Display QR</a><?php endif; ?><form class="inline" method="post" action="<?= escapeHtml(applicationUrl('/organization/event_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="close_qr"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><button class="secondary" type="submit">Close QR</button></form>
                                                <?php else: ?><form id="open-organization-qr-<?= (int) $event['id'] ?>" class="inline" method="post" action="<?= escapeHtml(applicationUrl('/organization/event_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="open_qr"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><button type="submit">Open check-in</button></form><?php endif; ?>
                                            <form method="post" action="<?= escapeHtml(applicationUrl('/organization/event_action.php')) ?>" onsubmit="return confirm('Delete this event and all of its attendance records?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><button class="danger" type="submit">Delete</button></form>
                                        </div></td>
                                    </tr><?php endforeach; ?></tbody>
                            </table>
                        </div><?php endif; ?>
                </section>
                
