                    <section class="panel">
                            <h2>Institutional Event Attendance</h2>
                            <p>Create required institutional activities and open a QR code during each activity. Every enrolled student can check in; attendance is recorded in ClearGo for clearance verification.</p>
                            <form class="controls council-event-form" method="post" action="<?= escapeHtml(applicationUrl('/student-council/event_action.php')) ?>">
                                <input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>">
                                <input type="hidden" name="action" value="create">
                                <label>Event Name<input type="text" name="name" maxlength="200" required placeholder="Required institutional activity"></label>
                                <label>Date <input type="date" name="event_date" required value="<?= escapeHtml(date('Y-m-d')) ?>"></label>
                                <button type="submit">Create Required Event</button>
                            </form>
                            <?php if ($councilEvents === []): ?><p class="muted">No institutional activities have been created for this semester.</p><?php else: ?><div class="table-wrap">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Activity</th>
                                                <th>Date</th>
                                                <th>Attendance</th>
                                                <th>Attendance time limit</th>
                                        <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody><?php foreach ($councilEvents as $event): ?><tr>
                                                    <td><strong><?= escapeHtml($event['name']) ?></strong></td>
                                                    <td><?= escapeHtml($event['event_date']) ?></td>
                                                    <td><?= (int) $event['present_count'] ?> / <?= (int) $event['enrolled_count'] ?> present</td>
                                                    <td><?php if ((int) $event['qr_is_open'] === 1): ?><span class="muted">&mdash;</span><?php else: ?><select form="open-council-qr-<?= (int) $event['id'] ?>" name="minutes" aria-label="Attendance time limit">
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
                                        <td><div class="event-actions"><?php if ((int) $event['qr_is_open'] === 1): ?><a class="button-link" href="<?= escapeHtml(applicationUrl('/student-council/event_qr.php?event=' . (int) $event['id'])) ?>">Display QR</a>
                                                            <form class="inline" method="post" action="<?= escapeHtml(applicationUrl('/student-council/event_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="close_qr"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><button class="secondary" type="submit">Close QR</button></form><?php else: ?><form id="open-council-qr-<?= (int) $event['id'] ?>" class="inline" method="post" action="<?= escapeHtml(applicationUrl('/student-council/event_action.php')) ?>"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="open_qr"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><button type="submit">Open check-in</button></form><?php endif; ?><form method="post" action="<?= escapeHtml(applicationUrl('/student-council/event_action.php')) ?>" onsubmit="return confirm('Delete this event and all of its attendance records?');"><input type="hidden" name="csrf_token" value="<?= escapeHtml($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>"><button class="danger" type="submit">Delete</button></form></div></td>
                                                </tr><?php endforeach; ?></tbody>
                                    </table>
                                </div><?php endif; ?>
                        </section>
