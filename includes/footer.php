<?php
// includes/footer.php
global $pdo, $app_settings, $user_data, $global_flash;
$mysql_port = '3306';
if ($pdo instanceof PDO) {
    try {
        $mysql_port = $pdo->query("SELECT @@port")->fetchColumn() ?: '3306';
    } catch (Exception $e) {}
}
?>
    </main>

    <!-- Footer Status Strip (Zabbix NOC style) -->
    <footer class="app-footer d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="text-slate-400 fw-semibold"><?= htmlspecialchars($app_settings['company_name'] ?? 'City Fiber') ?> NOC</span>
            <span>•</span>
            <span class="text-slate-400">Node: 103.166.230.229</span>
            <span>•</span>
            <span class="text-slate-500">DB: MySQL Port <?= htmlspecialchars($mysql_port) ?></span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="text-emerald-400 fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Radius/SNMP Active</span>
            <span>•</span>
            <span class="text-slate-400">Operator: <?= htmlspecialchars($user_data['username'] ?? 'Admin') ?> (<?= htmlspecialchars($user_data['role'] ?? 'Admin') ?>)</span>
        </div>
    </footer>
</div><!-- /app-main -->

</div><!-- /app-wrapper -->

<!-- Toast Notification Container: Fixed Bottom-Right -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;" id="nocToastContainer">
    <?php if (!empty($global_flash)): ?>
        <?php
            $f_type = $global_flash['type'] ?? 'info';
            $icon = 'fa-circle-info text-cyan-400';
            $border_class = 'border-cyan-500';
            $title = 'System Notice';
            
            if ($f_type === 'success') {
                $icon = 'fa-circle-check text-emerald-400';
                $border_class = 'border-emerald-500';
                $title = 'Success Notification';
            } elseif ($f_type === 'danger' || $f_type === 'error') {
                $icon = 'fa-triangle-exclamation text-rose-400';
                $border_class = 'border-rose-500';
                $title = 'Action Alert';
            } elseif ($f_type === 'warning') {
                $icon = 'fa-circle-exclamation text-amber-400';
                $border_class = 'border-amber-500';
                $title = 'Warning';
            }
        ?>
        <div class="toast toast-noc show align-items-center shadow-lg border-start border-4 <?= $border_class ?>" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="6000">
            <div class="toast-header d-flex align-items-center justify-content-between py-2 px-3 border-bottom border-slate-700/50">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid <?= $icon ?> fs-6"></i>
                    <strong class="font-sans text-xs fw-bold text-dark-force"><?= htmlspecialchars($title) ?></strong>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <small class="text-slate-400 font-mono" style="font-size:10px;">Just now</small>
                    <button type="button" class="btn-close text-xs" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <div class="toast-body py-2.5 px-3 text-xs">
                <?= $global_flash['message'] ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Reusable Card Box Notification Modal for Confirmations (Add / Delete / Actions) -->
<div class="modal fade" id="confirmCardModal" tabindex="-1" aria-labelledby="confirmCardModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content modal-content-noc confirm-card-box shadow-xl border-0 overflow-hidden">
            <!-- Top Accent Color Bar -->
            <div id="confirmCardAccent" class="confirm-accent-bar bg-danger" style="height: 4px;"></div>
            
            <div class="modal-body p-4 text-center">
                <!-- Icon Badge with Glow Effect -->
                <div class="confirm-icon-wrapper mb-3 mx-auto">
                    <div id="confirmCardIconBox" class="confirm-icon-box confirm-icon-delete">
                        <i id="confirmCardIcon" class="fa-solid fa-trash-can fa-2x text-rose-500"></i>
                    </div>
                </div>

                <!-- Title & Prompt -->
                <h5 class="fw-bold mb-1 font-sans text-dark-force" id="confirmCardTitle">Confirm Deletion</h5>
                <p class="text-slate-500 text-xs mb-3" id="confirmCardMessage">Are you sure you want to delete this record? This action cannot be reversed.</p>

                <!-- Context Detail Card Box -->
                <div id="confirmCardDetailsBox" class="confirm-details-card text-start p-2.5 rounded mb-4 font-mono text-xs d-none">
                    <div class="d-flex align-items-center justify-content-between mb-1" id="confirmDetailsRow1">
                        <span class="text-slate-400" id="confirmDetailsLabel">Item:</span>
                        <span class="fw-bold text-slate-200" id="confirmDetailsValue">—</span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between text-slate-400" id="confirmDetailsRow2">
                        <span id="confirmDetailsSubLabel">Details:</span>
                        <span class="font-mono text-cyan-400 fw-semibold" id="confirmDetailsSubValue">—</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex align-items-center justify-content-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary font-mono px-3 py-1.5" data-bs-dismiss="modal" id="confirmCardCancelBtn">
                        <i class="fa-solid fa-xmark me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-sm btn-danger font-mono px-3 py-1.5 shadow-sm fw-semibold" id="confirmCardActionBtn">
                        <i class="fa-solid fa-trash me-1" id="confirmCardActionIcon"></i> 
                        <span id="confirmCardActionText">Yes, Delete</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5.3.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom App Script -->
<script src="assets/js/app.js"></script>
</body>
</html>

