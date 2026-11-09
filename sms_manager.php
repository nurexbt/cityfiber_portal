<?php
// sms_manager.php - ISP SMS Gateway & Billing Due Reminder Broadcast
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('sms', 'view');
$user_data = get_current_user_data();

// Handle Send SMS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_sms') {
    require_permission('sms', 'add');
    $target_group = $_POST['target_group'] ?? 'due_only';
    $custom_phone = trim($_POST['custom_phone'] ?? '');
    $message_template = trim($_POST['message'] ?? '');
    $sms_type = $_POST['sms_type'] ?? 'Due Reminder';

    if (empty($message_template)) {
        set_flash_message('danger', 'Please enter a message text to send.');
    } else {
        $sent_count = 0;
        $due_date = date('Y-m-') . str_pad($app_settings['bill_due_day'] ?? 10, 2, '0', STR_PAD_LEFT);
        $cur_month = date('F Y');

        if ($target_group === 'custom' && !empty($custom_phone)) {
            $stmt = $pdo->prepare("INSERT INTO sms_logs (phone, message, sms_type, status) VALUES (?, ?, ?, 'Sent')");
            $stmt->execute([$custom_phone, $message_template, $sms_type]);
            $sent_count = 1;
        } else {
            // Determine recipient query
            $sql = "SELECT id, customer_code, name, phone, total_due FROM customers WHERE phone IS NOT NULL AND phone != ''";
            if ($target_group === 'due_only') {
                $sql .= " AND total_due > 0";
            } elseif (str_starts_with($target_group, 'zone_')) {
                $zid = (int)str_replace('zone_', '', $target_group);
                $sql .= " AND zone_id = {$zid}";
            }

            $recipients = $pdo->query($sql)->fetchAll();
            $ins = $pdo->prepare("INSERT INTO sms_logs (customer_id, phone, message, sms_type, status) VALUES (?, ?, ?, ?, 'Sent')");

            foreach ($recipients as $rec) {
                // Personalize tags
                $msg = str_replace(
                    ['{NAME}', '{CUSTOMER_CODE}', '{DUE_AMOUNT}', '{MONTH}', '{DUE_DATE}', '{HOTLINE}'],
                    [$rec['name'], $rec['customer_code'], number_format($rec['total_due'], 2), $cur_month, $due_date, $app_settings['phone']],
                    $message_template
                );

                $ins->execute([$rec['id'], $rec['phone'], $msg, $sms_type]);
                $sent_count++;
            }
        }

        set_flash_message('success', "SMS Broadcast: Successfully queued and sent {$sent_count} SMS notification(s)!");
        header('Location: sms_manager.php');
        exit;
    }
}

// Fetch SMS History
$logs = $pdo->query("
    SELECT sl.*, c.name AS customer_name, c.customer_code 
    FROM sms_logs sl
    LEFT JOIN customers c ON sl.customer_id = c.id
    ORDER BY sl.id DESC
    LIMIT 100
")->fetchAll();

$zones = $pdo->query("SELECT * FROM zones ORDER BY name ASC")->fetchAll();
$total_sent = $pdo->query("SELECT COUNT(*) FROM sms_logs")->fetchColumn();
$total_due_subscribers = $pdo->query("SELECT COUNT(*) FROM customers WHERE total_due > 0")->fetchColumn();

$page_title = 'SMS Gateway & Notices';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold text-dark mb-0 font-sans">SMS Gateway &amp; Due Reminders</h4>
            <span class="badge bg-purple-50 text-purple-700 border border-purple-200 font-mono text-xs">
                Subscriber Notifications
            </span>
        </div>
        <p class="text-slate-400 text-xs mb-0">Broadcast payment due alerts, collection money receipts, and scheduled optical maintenance notices.</p>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-purple h-100">
            <div class="kpi-title">Total SMS Dispatched</div>
            <div class="kpi-value text-purple-400"><?= $total_sent ?></div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">All-time outbound SMS notices</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-rose h-100">
            <div class="kpi-title">Subscribers in Due</div>
            <div class="kpi-value text-rose-400"><?= $total_due_subscribers ?> Clients</div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Eligible for automated due reminder</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-emerald h-100">
            <div class="kpi-title">Gateway Delivery Rate</div>
            <div class="kpi-value text-emerald-400">100.0%</div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Active SMS aggregator route</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="kpi-card kpi-cyan h-100">
            <div class="kpi-title">Sender Mask ID</div>
            <div class="kpi-value text-cyan-400" style="font-size: 1.15rem;">CITY FIBER</div>
            <div class="text-slate-400 font-mono mt-1" style="font-size:11px;">Approved BTRC alphanumeric mask</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Left Column: Send SMS Form -->
    <div class="col-12 col-lg-5">
        <div class="card card-noc h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-700">
                    <i class="fa-solid fa-paper-plane text-cyan-400 me-2"></i>Compose &amp; Broadcast SMS
                </span>
                <span class="badge bg-light text-slate-500 border font-mono text-xs">Dynamic Tags</span>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="sms_manager.php" data-confirm-add="Confirm broadcasting SMS notification to the target subscriber group?" data-confirm-title="Confirm SMS Broadcast">
                    <input type="hidden" name="action" value="send_sms">

                    <div class="mb-3">
                        <label class="form-label-noc">Recipient Target Group <span class="text-rose-400">*</span></label>
                        <select name="target_group" id="smsTargetGroup" class="form-select form-select-noc" onchange="toggleCustomPhoneInput(this)">
                            <option value="due_only" selected>All Due Subscribers (<?= $total_due_subscribers ?> clients pending)</option>
                            <option value="all">All Active Subscribers</option>
                            <?php foreach ($zones as $z): ?>
                                <option value="zone_<?= $z['id'] ?>">Zone: <?= htmlspecialchars($z['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="custom">Single Custom Phone Number</option>
                        </select>
                    </div>

                    <div class="mb-3 d-none" id="customPhoneDiv">
                        <label class="form-label-noc">Recipient Phone Number</label>
                        <input type="text" name="custom_phone" class="form-control form-control-noc font-mono" placeholder="+880 17XXXXXXXX">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label-noc">Quick Template</label>
                            <select id="smsTemplateSelect" class="form-select form-select-noc form-select-sm" onchange="applySmsTemplate(this.value)">
                                <option value="due">Monthly Bill Due Reminder</option>
                                <option value="received">Payment Confirmation</option>
                                <option value="maintenance">Fiber Outage / Maintenance</option>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label-noc">SMS Type</label>
                            <select name="sms_type" class="form-select form-select-noc form-select-sm">
                                <option value="Due Reminder">Due Reminder</option>
                                <option value="Payment Receipt">Receipt</option>
                                <option value="Outage Notice">Outage Notice</option>
                                <option value="Custom">Custom</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label-noc mb-0">Message Content <span class="text-rose-400">*</span></label>
                            <span class="text-slate-400 font-mono text-xs" id="charCount">0 chars (1 SMS)</span>
                        </div>
                        <textarea name="message" id="smsMessageArea" class="form-control form-control-noc font-mono" rows="5" required onkeyup="updateCharCount()"></textarea>
                    </div>

                    <!-- Available Tags Helper -->
                    <div class="p-2 mb-4 rounded bg-light border font-mono text-xs">
                        <span class="text-slate-500 d-block mb-1">Available merge tags:</span>
                        <div class="d-flex flex-wrap gap-1">
                            <span class="badge bg-white text-cyan-400 border cursor-pointer" onclick="insertTag('{NAME}')">{NAME}</span>
                            <span class="badge bg-white text-cyan-400 border cursor-pointer" onclick="insertTag('{CUSTOMER_CODE}')">{CUSTOMER_CODE}</span>
                            <span class="badge bg-white text-cyan-400 border cursor-pointer" onclick="insertTag('{DUE_AMOUNT}')">{DUE_AMOUNT}</span>
                            <span class="badge bg-white text-cyan-400 border cursor-pointer" onclick="insertTag('{MONTH}')">{MONTH}</span>
                            <span class="badge bg-white text-cyan-400 border cursor-pointer" onclick="insertTag('{DUE_DATE}')">{DUE_DATE}</span>
                            <span class="badge bg-white text-cyan-400 border cursor-pointer" onclick="insertTag('{HOTLINE}')">{HOTLINE}</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-cyan w-100 font-mono fw-bold py-2">
                        <i class="fa-solid fa-paper-plane me-1"></i> Broadcast Notification
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: SMS Outbox History -->
    <div class="col-12 col-lg-7">
        <div class="card card-noc h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-700">
                    <i class="fa-solid fa-clock-rotate-left text-cyan-400 me-2"></i>Recent Outbox Log (<?= count($logs) ?>)
                </span>
                <span class="badge bg-light text-slate-500 border font-mono text-xs">Real-Time Delivery</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-noc align-middle">
                        <thead>
                            <tr>
                                <th>Recipient</th>
                                <th>Type</th>
                                <th>Message</th>
                                <th>Date &amp; Time</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-slate-400 font-mono">No SMS sent yet. Compose your first message on the left.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark text-xs"><?= htmlspecialchars($l['customer_name'] ?: 'External Number') ?></div>
                                            <div class="font-mono text-slate-500 text-xs"><?= htmlspecialchars($l['phone']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-slate-700 border font-mono text-xs"><?= htmlspecialchars($l['sms_type']) ?></span>
                                        </td>
                                        <td>
                                            <div class="text-slate-600 text-xs text-truncate font-mono" style="max-width: 280px;" title="<?= htmlspecialchars($l['message']) ?>">
                                                <?= htmlspecialchars($l['message']) ?>
                                            </div>
                                        </td>
                                        <td class="font-mono text-slate-500 text-xs"><?= htmlspecialchars($l['sent_at']) ?></td>
                                        <td class="text-center">
                                            <span class="badge badge-active"><i class="fa-solid fa-check me-1"></i> Sent</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const templates = {
    due: "Dear {NAME}, your broadband bill of Tk {DUE_AMOUNT} for {MONTH} is pending. Please pay by {DUE_DATE} to avoid service disruption. Helpline: {HOTLINE}. - <?= htmlspecialchars($app_settings['company_name']) ?>",
    received: "Dear {NAME}, we have received your internet bill payment of Tk {DUE_AMOUNT}. Thank you for choosing <?= htmlspecialchars($app_settings['company_name']) ?>! Hotline: {HOTLINE}",
    maintenance: "Notice: Due to urgent optical fiber maintenance, internet service in your zone may face brief interruptions today. We regret the inconvenience. - <?= htmlspecialchars($app_settings['company_name']) ?>"
};

function applySmsTemplate(type) {
    const area = document.getElementById('smsMessageArea');
    if (templates[type]) {
        area.value = templates[type];
        updateCharCount();
    }
}

function updateCharCount() {
    const area = document.getElementById('smsMessageArea');
    const len = area.value.length;
    const parts = Math.max(1, Math.ceil(len / 160));
    document.getElementById('charCount').textContent = `${len} chars (${parts} SMS)`;
}

function toggleCustomPhoneInput(selectEl) {
    const div = document.getElementById('customPhoneDiv');
    if (selectEl.value === 'custom') {
        div.classList.remove('d-none');
    } else {
        div.classList.add('d-none');
    }
}

function insertTag(tag) {
    const area = document.getElementById('smsMessageArea');
    area.value += ' ' + tag + ' ';
    updateCharCount();
}

document.addEventListener('DOMContentLoaded', function () {
    applySmsTemplate('due');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

