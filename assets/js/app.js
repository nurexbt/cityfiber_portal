// assets/js/app.js - ISP Front-end Interactions & Theme Control

// 1. Theme Switcher (Default: Light Theme)
function initTheme() {
    const savedTheme = localStorage.getItem('isp_theme') || 'light';
    applyTheme(savedTheme);
}

function applyTheme(theme) {
    const icon = document.getElementById('themeIcon');
    const text = document.getElementById('themeText');

    if (theme === 'dark') {
        document.documentElement.classList.add('dark');
        document.documentElement.classList.remove('light');
        if (icon) {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        }
        if (text) text.textContent = 'Light';
    } else {
        document.documentElement.classList.add('light');
        document.documentElement.classList.remove('dark');
        if (icon) {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
        if (text) text.textContent = 'Dark';
    }
}

function toggleTheme() {
    const isDark = document.documentElement.classList.contains('dark');
    const newTheme = isDark ? 'light' : 'dark';
    localStorage.setItem('isp_theme', newTheme);
    applyTheme(newTheme);

    // Refresh Chart.js charts if present to update axis colors
    if (window.trendChartInstance) {
        const textColor = newTheme === 'dark' ? '#94a3b8' : '#64748b';
        const gridColor = newTheme === 'dark' ? 'rgba(51, 65, 85, 0.3)' : 'rgba(226, 232, 240, 0.6)';
        window.trendChartInstance.options.scales.x.ticks.color = textColor;
        window.trendChartInstance.options.scales.x.grid.color = gridColor;
        window.trendChartInstance.options.scales.y.ticks.color = textColor;
        window.trendChartInstance.options.scales.y.grid.color = gridColor;
        window.trendChartInstance.update();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    initTheme();

    // 2. Live Digital Clock (if element present)
    const clockEl = document.getElementById('nocClock');
    if (clockEl) {
        function updateClock() {
            const now = new Date();
            const dateStr = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            const timeStr = now.toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
            clockEl.textContent = `${dateStr} • ${timeStr}`;
        }
        updateClock();
        setInterval(updateClock, 1000);
    }

    // 3. Responsive Sidebar Collapse & Extend System
    const sidebar = document.getElementById('appSidebar');
    const mainContent = document.getElementById('appMain');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const closeBtn = document.getElementById('sidebarCloseBtn');
    let backdrop = document.getElementById('sidebarBackdrop');

    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id = 'sidebarBackdrop';
        backdrop.className = 'sidebar-backdrop';
        document.body.appendChild(backdrop);
    }

    function isMobile() {
        return window.innerWidth < 992;
    }

    function openMobileSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('mobile-open');
        backdrop.classList.add('active');
        document.body.classList.add('sidebar-open');
    }

    function closeMobileSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('mobile-open');
        backdrop.classList.remove('active');
        document.body.classList.remove('sidebar-open');
    }

    if (sidebar && toggleBtn && mainContent) {
        // Desktop saved collapse preference
        if (!isMobile()) {
            const isCollapsed = localStorage.getItem('noc_sidebar_collapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            }
        }

        // Toggle Button (Mobile: Slide-in Drawer; Desktop: Mini Sidebar Collapse)
        toggleBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (isMobile()) {
                if (sidebar.classList.contains('mobile-open')) {
                    closeMobileSidebar();
                } else {
                    openMobileSidebar();
                }
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                localStorage.setItem('noc_sidebar_collapsed', sidebar.classList.contains('collapsed'));
            }
        });

        // Close button inside mobile sidebar header
        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                closeMobileSidebar();
            });
        }

        // Close when tapping outside on backdrop
        backdrop.addEventListener('click', function () {
            closeMobileSidebar();
        });

        // Close on navigation link click when in mobile
        sidebar.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', function () {
                if (isMobile()) {
                    closeMobileSidebar();
                }
            });
        });

        // Smooth window resize transition
        window.addEventListener('resize', function () {
            if (!isMobile()) {
                closeMobileSidebar();
                const isCollapsed = localStorage.getItem('noc_sidebar_collapsed') === 'true';
                if (isCollapsed) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                }
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
            }
        });
    }

    // 4. Quick Table Search Filter (Client-side instant filter)
    const tableSearchInputs = document.querySelectorAll('[data-noc-search]');
    tableSearchInputs.forEach(input => {
        const targetTableId = input.getAttribute('data-noc-search');
        const targetTable = document.getElementById(targetTableId);
        if (!targetTable) return;

        input.addEventListener('keyup', function () {
            const query = this.value.toLowerCase().trim();
            const rows = targetTable.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    });

    // 5. Initialize Bootstrap Toasts (Bottom-Right Pinned)
    const toastEls = document.querySelectorAll('.toast');
    toastEls.forEach(el => {
        const t = new bootstrap.Toast(el, { autohide: true, delay: 6000 });
        t.show();
    });

    // 6. Global Confirmation Card Box Interceptor (Deletes and Adds)
    initConfirmInterceptors();
});

// ==========================================================================
// Toast Notification Engine (Bottom-Right)
// ==========================================================================
function showToast(type, message, title = '', delay = 5000) {
    let container = document.getElementById('nocToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'nocToastContainer';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '1100';
        document.body.appendChild(container);
    }

    // Default icon & border styling based on type
    let iconClass = 'fa-circle-info text-cyan-400';
    let borderClass = 'border-cyan-500';
    let defaultTitle = 'System Notice';

    if (type === 'success') {
        iconClass = 'fa-circle-check text-emerald-400';
        borderClass = 'border-emerald-500';
        defaultTitle = 'Success Notification';
    } else if (type === 'danger' || type === 'error') {
        iconClass = 'fa-triangle-exclamation text-rose-400';
        borderClass = 'border-rose-500';
        defaultTitle = 'Action Alert';
    } else if (type === 'warning') {
        iconClass = 'fa-circle-exclamation text-amber-400';
        borderClass = 'border-amber-500';
        defaultTitle = 'Warning Notice';
    }

    const toastTitle = title || defaultTitle;
    const toastId = 'toast_' + Math.random().toString(36).substring(2, 9);

    const toastHtml = `
        <div id="${toastId}" class="toast toast-noc align-items-center shadow-lg border-start border-4 ${borderClass}" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header d-flex align-items-center justify-content-between py-2 px-3 border-bottom border-slate-700/50">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid ${iconClass} fs-6"></i>
                    <strong class="font-sans text-xs fw-bold text-dark-force">${toastTitle}</strong>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <small class="text-slate-400 font-mono" style="font-size:10px;">Just now</small>
                    <button type="button" class="btn-close text-xs" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <div class="toast-body py-2.5 px-3 text-xs">
                ${message}
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', toastHtml);
    const newToastEl = document.getElementById(toastId);
    if (newToastEl) {
        const bsToast = new bootstrap.Toast(newToastEl, { autohide: true, delay: delay });
        newToastEl.addEventListener('hidden.bs.toast', () => {
            newToastEl.remove();
        });
        bsToast.show();
    }
}

// ==========================================================================
// Card Box Confirmation Modal Engine
// ==========================================================================
let currentConfirmAction = null;

function openConfirmModal(config) {
    const modalEl = document.getElementById('confirmCardModal');
    if (!modalEl) return;

    const accentEl = document.getElementById('confirmCardAccent');
    const iconBoxEl = document.getElementById('confirmCardIconBox');
    const iconEl = document.getElementById('confirmCardIcon');
    const titleEl = document.getElementById('confirmCardTitle');
    const msgEl = document.getElementById('confirmCardMessage');
    const detailsBox = document.getElementById('confirmCardDetailsBox');
    const detailsVal = document.getElementById('confirmDetailsValue');
    const detailsLabel = document.getElementById('confirmDetailsLabel');
    const detailsSubVal = document.getElementById('confirmDetailsSubValue');
    const detailsSubRow = document.getElementById('confirmDetailsRow2');
    const actionBtn = document.getElementById('confirmCardActionBtn');
    const actionText = document.getElementById('confirmCardActionText');
    const actionIcon = document.getElementById('confirmCardActionIcon');

    const type = config.type || 'delete'; // 'delete' or 'add' or 'warning'

    if (type === 'add') {
        accentEl.className = 'confirm-accent-bar bg-info';
        iconBoxEl.className = 'confirm-icon-box confirm-icon-add';
        iconEl.className = 'fa-solid fa-circle-plus fa-2x text-cyan-400';
        actionBtn.className = 'btn btn-sm btn-cyan font-mono px-3 py-1.5 shadow-sm fw-semibold';
        actionIcon.className = 'fa-solid fa-check me-1';
        actionText.textContent = config.actionText || 'Yes, Save & Add';
    } else if (type === 'warning') {
        accentEl.className = 'confirm-accent-bar bg-warning';
        iconBoxEl.className = 'confirm-icon-box confirm-icon-warning';
        iconEl.className = 'fa-solid fa-triangle-exclamation fa-2x text-amber-500';
        actionBtn.className = 'btn btn-sm btn-warning font-mono px-3 py-1.5 shadow-sm fw-semibold text-dark';
        actionIcon.className = 'fa-solid fa-bolt me-1';
        actionText.textContent = config.actionText || 'Yes, Proceed';
    } else {
        // Delete
        accentEl.className = 'confirm-accent-bar bg-danger';
        iconBoxEl.className = 'confirm-icon-box confirm-icon-delete';
        iconEl.className = 'fa-solid fa-trash-can fa-2x text-rose-500';
        actionBtn.className = 'btn btn-sm btn-danger font-mono px-3 py-1.5 shadow-sm fw-semibold';
        actionIcon.className = 'fa-solid fa-trash me-1';
        actionText.textContent = config.actionText || 'Yes, Delete';
    }

    titleEl.textContent = config.title || (type === 'add' ? 'Confirm New Entry' : 'Confirm Deletion');
    msgEl.textContent = config.message || (type === 'add' ? 'Are you sure you want to add this record to the system?' : 'Are you sure you want to delete this record? This action cannot be reversed.');

    if (config.itemValue) {
        detailsBox.classList.remove('d-none');
        detailsLabel.textContent = config.itemLabel || (type === 'add' ? 'Target Entry:' : 'Item:');
        detailsVal.textContent = config.itemValue;

        if (config.subValue) {
            detailsSubRow.classList.remove('d-none');
            detailsSubVal.textContent = config.subValue;
        } else {
            detailsSubRow.classList.add('d-none');
        }
    } else {
        detailsBox.classList.add('d-none');
    }

    // Set callback
    currentConfirmAction = config.onConfirm || null;

    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
    bsModal.show();
}

// Bind Action button inside Modal
document.addEventListener('DOMContentLoaded', function () {
    const actionBtn = document.getElementById('confirmCardActionBtn');
    if (actionBtn) {
        actionBtn.addEventListener('click', function () {
            const modalEl = document.getElementById('confirmCardModal');
            const bsModal = bootstrap.Modal.getInstance(modalEl);
            if (bsModal) bsModal.hide();

            if (typeof currentConfirmAction === 'function') {
                currentConfirmAction();
            } else if (typeof currentConfirmAction === 'string' && currentConfirmAction) {
                window.location.href = currentConfirmAction;
            }
            currentConfirmAction = null;
        });
    }
});

// Helper to automatically intercept clicks & submits across the portal
function initConfirmInterceptors() {
    // 1. Intercept DELETE actions
    document.addEventListener('click', function (e) {
        const deleteLink = e.target.closest('a[data-confirm-delete], a[onclick*="confirm"], a[href*="delete_"]');
        if (!deleteLink) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        const url = deleteLink.getAttribute('href');
        const customMsg = deleteLink.getAttribute('data-confirm-delete');
        const itemName = deleteLink.getAttribute('data-item-name') || '';
        const itemCode = deleteLink.getAttribute('data-item-code') || '';

        // Try extracting row info if available
        let rowDetails = itemName;
        const row = deleteLink.closest('tr');
        if (!rowDetails && row) {
            const firstTd = row.querySelector('td:nth-child(2)') || row.querySelector('td:first-child');
            if (firstTd) rowDetails = firstTd.innerText.trim().split('\n')[0];
        }

        openConfirmModal({
            type: 'delete',
            title: 'Confirm Deletion',
            message: customMsg || 'Are you sure you want to delete this record? This action cannot be reversed.',
            itemLabel: 'Record:',
            itemValue: rowDetails || 'Selected Entry',
            subValue: itemCode || (url ? url.split('?')[1] : ''),
            actionText: 'Yes, Delete Record',
            onConfirm: () => {
                window.location.href = url;
            }
        });
    }, true);

    // 2. Intercept ADD / CREATE forms
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form || !form.matches) return;

        // Check if form should have add confirmation
        const isAddForm = form.hasAttribute('data-confirm-add') || 
                          form.classList.contains('confirm-add-form') ||
                          form.querySelector('input[name="action"][value*="add"], input[name="action"][value*="save"], input[name="action"][value*="receive"], input[name="action"][value*="generate"]');

        if (!isAddForm) return;

        // If already confirmed by user in the card box modal, allow natural submission
        if (form.dataset.confirmed === 'true') {
            return true;
        }

        // Validate form first
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        e.preventDefault();

        // Extract context details
        const customTitle = form.getAttribute('data-confirm-title') || 'Confirm New Entry';
        const customPrompt = form.getAttribute('data-confirm-add') || 'Are you sure you want to submit and save this entry?';

        // Extract intelligent details preview from common input fields
        let previewItem = '';
        let previewSub = '';

        const nameInput = form.querySelector('input[name="name"], input[name="investor_name"], select[name="customer_id"] option:checked, select[name="employee_id"] option:checked, select[name="category"]');
        const amtInput = form.querySelector('input[name="amount"], input[name="monthly_bill"], input[name="net_paid"], input[name="price"]');

        if (nameInput) {
            previewItem = nameInput.tagName === 'SELECT' ? nameInput.textContent.trim() : nameInput.value.trim();
        }
        if (amtInput && amtInput.value) {
            previewSub = 'Amount: BDT ' + parseFloat(amtInput.value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        openConfirmModal({
            type: 'add',
            title: customTitle,
            message: customPrompt,
            itemLabel: 'Target Entry:',
            itemValue: previewItem || 'New Entry Record',
            subValue: previewSub || 'Will be recorded in database immediately',
            actionText: 'Yes, Confirm & Save',
            onConfirm: () => {
                form.dataset.confirmed = 'true';
                form.submit();
            }
        });
    });
}

// Modal Calculation Helpers
function calculateBillTotals() {
    const pkgAmt = parseFloat(document.getElementById('bill_package_amount')?.value) || 0;
    const extra = parseFloat(document.getElementById('bill_extra_charge')?.value) || 0;
    const prevDue = parseFloat(document.getElementById('bill_previous_due')?.value) || 0;
    const discount = parseFloat(document.getElementById('bill_discount')?.value) || 0;

    const total = Math.max(0, (pkgAmt + extra + prevDue) - discount);
    const totalEl = document.getElementById('bill_total_payable');
    if (totalEl) totalEl.value = total.toFixed(2);
}

function calculateSalaryTotals() {
    const basic = parseFloat(document.getElementById('sal_basic_amount')?.value) || 0;
    const allowance = parseFloat(document.getElementById('sal_allowance')?.value) || 0;
    const deduction = parseFloat(document.getElementById('sal_deduction')?.value) || 0;

    const net = Math.max(0, (basic + allowance) - deduction);
    const netEl = document.getElementById('sal_net_paid');
    if (netEl) netEl.value = net.toFixed(2);
}
