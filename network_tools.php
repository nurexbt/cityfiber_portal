<?php
// network_tools.php - ISP Field Technician & NOC Troubleshooting Guide
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('network_tools', 'view');

$page_title = 'NOC Troubleshooting & Field Configuration Guide';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold text-dark-force mb-0 font-sans">NOC Troubleshooting &amp; Field Configuration Guide</h4>
            <span class="badge badge-header-cyan font-mono text-xs">
                <i class="fa-solid fa-screwdriver-wrench me-1"></i> Field SOP v2.4
            </span>
        </div>
        <p class="text-slate-500 text-xs mb-0">Standard operating procedures for Wi-Fi router setup, optical ONU laser standards, and mobile wireless diagnostics.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary font-mono text-xs d-flex align-items-center gap-1.5" onclick="window.print()">
            <i class="fa-solid fa-print text-slate-400"></i> Print / Save SOP
        </button>
    </div>
</div>

<!-- Primary Navigation Tabs -->
<ul class="nav nav-pills mb-4 gap-2 border-bottom border-slate-700/20 pb-3" id="troubleshootTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active font-mono text-xs d-flex align-items-center gap-2 px-3 py-2 rounded-2" id="tab-router-btn" data-bs-toggle="pill" data-bs-target="#tab-router" type="button" role="tab" aria-selected="true">
            <i class="fa-solid fa-wifi text-cyan-400"></i>
            <span class="fw-semibold">1. Wi-Fi Router Setup &amp; NOC Mandates</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link font-mono text-xs d-flex align-items-center gap-2 px-3 py-2 rounded-2" id="tab-onu-btn" data-bs-toggle="pill" data-bs-target="#tab-onu" type="button" role="tab" aria-selected="false">
            <i class="fa-solid fa-wave-square text-emerald-400"></i>
            <span class="fw-semibold">2. Optical ONU Laser Standards</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link font-mono text-xs d-flex align-items-center gap-2 px-3 py-2 rounded-2" id="tab-wifi-btn" data-bs-toggle="pill" data-bs-target="#tab-wifi" type="button" role="tab" aria-selected="false">
            <i class="fa-solid fa-mobile-screen-button text-purple-400"></i>
            <span class="fw-semibold">3. Wi-Fi Signal &amp; Phone Ping System</span>
        </button>
    </li>
</ul>

<div class="tab-content" id="troubleshootTabsContent">

    <!-- ============================================================== -->
    <!-- TAB 1: WI-FI ROUTER CONFIGURATION & MANDATORY SETTINGS         -->
    <!-- ============================================================== -->
    <div class="tab-pane fade show active" id="tab-router" role="tabpanel" aria-labelledby="tab-router-btn">
        
        <!-- Mandatory NOC Policies Banner -->
        <div class="card card-noc mb-4 border-cyan-500/30 bg-cyan-950/10">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-cyan-500 text-slate-950 font-mono fw-bold text-xs uppercase">Mandatory Rule</span>
                    <h5 class="fw-bold text-dark-force mb-0 font-sans">NOC Mandatory Router Deployment Standards</h5>
                </div>
                <p class="text-slate-500 text-xs mb-3">Whenever provisioning a new subscriber or replacing a customer router, field technicians <strong>MUST</strong> enable these two critical parameters before leaving customer premises:</p>
                
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="p-3 rounded border border-cyan-500/30 bg-cyan-500/5 h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="rounded-circle bg-cyan-500/20 text-cyan-400 d-flex align-items-center justify-content-center" style="width:30px; height:30px;">
                                    <i class="fa-solid fa-globe"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark-force mb-0 font-sans text-xs">1. Enable Remote Web Management</h6>
                                    <span class="text-cyan-400 font-mono" style="font-size:11px;">Port: 8080 | Remote IP: 0.0.0.0 (All)</span>
                                </div>
                            </div>
                            <ul class="text-slate-500 text-xs mb-0 ps-3">
                                <li>Enables NOC operators to access router remotely from office desk.</li>
                                <li>Eliminates customer site visits for Wi-Fi password changes or rebooting.</li>
                                <li>Allows checking PPPoE connection status, WAN IP, and wireless channel diagnostics.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="p-3 rounded border border-emerald-500/30 bg-emerald-500/5 h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="rounded-circle bg-emerald-500/20 text-emerald-400 d-flex align-items-center justify-content-center" style="width:30px; height:30px;">
                                    <i class="fa-solid fa-satellite-dish"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark-force mb-0 font-sans text-xs">2. Enable WAN Ping Response (ICMP)</h6>
                                    <span class="text-emerald-400 font-mono" style="font-size:11px;">Respond to Ping from WAN = Enabled</span>
                                </div>
                            </div>
                            <ul class="text-slate-500 text-xs mb-0 ps-3">
                                <li>Enables NOC automated monitoring (MikroTik Netwatch, Zabbix) to ping customer IP.</li>
                                <li>Detects subscriber optical cable cuts and power blackouts in real-time.</li>
                                <li>Allows line testing without disturbing customer during service maintenance.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step-by-Step Brand Guides -->
        <div class="card card-noc mb-4">
            <div class="card-header d-flex justify-content-between align-items-center py-2.5">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-sliders text-cyan-400 me-2"></i>Router Configuration Steps by Brand
                </span>
                <span class="badge bg-slate-900 border border-slate-700 text-slate-400 font-mono text-xs">Step-by-Step SOP</span>
            </div>
            <div class="card-body p-4">
                
                <!-- Inner Router Brand Pills -->
                <ul class="nav nav-tabs mb-4 border-bottom border-slate-700/30 gap-1" id="routerBrandTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active font-mono text-xs fw-semibold px-3 py-2" id="brand-tplink-tab" data-bs-toggle="tab" data-bs-target="#brand-tplink" type="button" role="tab">TP-Link</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link font-mono text-xs fw-semibold px-3 py-2" id="brand-tenda-tab" data-bs-toggle="tab" data-bs-target="#brand-tenda" type="button" role="tab">Tenda</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link font-mono text-xs fw-semibold px-3 py-2" id="brand-netis-tab" data-bs-toggle="tab" data-bs-target="#brand-netis" type="button" role="tab">Netis</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link font-mono text-xs fw-semibold px-3 py-2" id="brand-mercusys-tab" data-bs-toggle="tab" data-bs-target="#brand-mercusys" type="button" role="tab">Mercusys</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link font-mono text-xs fw-semibold px-3 py-2" id="brand-dlink-tab" data-bs-toggle="tab" data-bs-target="#brand-dlink" type="button" role="tab">D-Link / Xiaomi</button>
                    </li>
                </ul>

                <div class="tab-content" id="routerBrandTabsContent">
                    
                    <!-- BRAND 1: TP-LINK -->
                    <div class="tab-pane fade show active" id="brand-tplink" role="tabpanel">
                        <div class="d-flex align-items-center justify-content-between p-2.5 rounded bg-slate-900 border border-slate-800 mb-3 text-xs font-mono">
                            <div><span class="text-slate-400">Default IP:</span> <strong class="text-cyan-400">192.168.0.1</strong> or <strong class="text-cyan-400">tplinkwifi.net</strong></div>
                            <div><span class="text-slate-400">Default Credentials:</span> <strong class="text-emerald-400">admin / admin</strong> (or custom on first boot)</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="border border-slate-700/50 rounded p-3 h-100 bg-surface-subtle">
                                    <div class="fw-bold text-dark-force text-xs mb-2 d-flex align-items-center gap-2">
                                        <span class="badge bg-cyan-950 text-cyan-400 border border-cyan-800">Step A</span>
                                        Enable Remote Web Management
                                    </div>
                                    <ol class="text-slate-500 text-xs ps-3 mb-0" style="line-height: 1.7;">
                                        <li>Log into TP-Link admin panel (<code>192.168.0.1</code>).</li>
                                        <li>Navigate to <strong>System Tools</strong> &gt; <strong>Manage Control</strong> (or <strong>Web Management / Administration</strong>).</li>
                                        <li>Under <em>Remote Management</em>, set <strong>Web Management Port</strong> to <code class="text-cyan-400 fw-bold">8080</code> (or <code>8888</code>).</li>
                                        <li>Set <strong>Remote Management IP Address</strong> to <code class="text-cyan-400 fw-bold">255.255.255.255</code> (allows any ISP NOC IP) or enter the ISP Subnet.</li>
                                        <li>Click <strong>Save</strong>.</li>
                                    </ol>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="border border-slate-700/50 rounded p-3 h-100 bg-surface-subtle">
                                    <div class="fw-bold text-dark-force text-xs mb-2 d-flex align-items-center gap-2">
                                        <span class="badge bg-emerald-950 text-emerald-400 border border-emerald-800">Step B</span>
                                        Enable WAN Ping Response
                                    </div>
                                    <ol class="text-slate-500 text-xs ps-3 mb-0" style="line-height: 1.7;">
                                        <li>In TP-Link menu, click <strong>Security</strong> &gt; <strong>Advanced Security</strong>.</li>
                                        <li>Locate <strong>"Ignore Ping Packet from WAN Port"</strong>.</li>
                                        <li><strong class="text-rose-500">UNCHECK</strong> the box (ensure it is <strong>DISABLED</strong>) so the router responds to external ICMP ping.</li>
                                        <li>On newer TP-Link Archer / Agile GUI: Go to <strong>Security</strong> &gt; <strong>Access Control</strong> &gt; Set <em>"Forbid WAN Ping"</em> to <strong>OFF</strong>.</li>
                                        <li>Click <strong>Save</strong>.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BRAND 2: TENDA -->
                    <div class="tab-pane fade" id="brand-tenda" role="tabpanel">
                        <div class="d-flex align-items-center justify-content-between p-2.5 rounded bg-slate-900 border border-slate-800 mb-3 text-xs font-mono">
                            <div><span class="text-slate-400">Default IP:</span> <strong class="text-cyan-400">192.168.0.1</strong> or <strong class="text-cyan-400">tendawifi.com</strong></div>
                            <div><span class="text-slate-400">Default Credentials:</span> <strong class="text-emerald-400">admin / admin</strong> (or blank login)</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="border border-slate-700/50 rounded p-3 h-100 bg-surface-subtle">
                                    <div class="fw-bold text-dark-force text-xs mb-2 d-flex align-items-center gap-2">
                                        <span class="badge bg-cyan-950 text-cyan-400 border border-cyan-800">Step A</span>
                                        Enable Remote Web Management
                                    </div>
                                    <ol class="text-slate-500 text-xs ps-3 mb-0" style="line-height: 1.7;">
                                        <li>Log into Tenda dashboard (<code>192.168.0.1</code>).</li>
                                        <li>Click <strong>Administration</strong> on the left navigation sidebar.</li>
                                        <li>Scroll down to the <strong>Remote Web Management</strong> section.</li>
                                        <li>Toggle switch to <strong class="text-emerald-400">ENABLE</strong>.</li>
                                        <li>Set <strong>Port</strong> to <code class="text-cyan-400 fw-bold">8080</code>.</li>
                                        <li>Set <strong>Allowed IP</strong> to <code class="text-cyan-400 fw-bold">All (0.0.0.0)</code>.</li>
                                        <li>Scroll down and click <strong>OK / Save</strong>.</li>
                                    </ol>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="border border-slate-700/50 rounded p-3 h-100 bg-surface-subtle">
                                    <div class="fw-bold text-dark-force text-xs mb-2 d-flex align-items-center gap-2">
                                        <span class="badge bg-emerald-950 text-emerald-400 border border-emerald-800">Step B</span>
                                        Enable WAN Ping Response
                                    </div>
                                    <ol class="text-slate-500 text-xs ps-3 mb-0" style="line-height: 1.7;">
                                        <li>In the same <strong>Administration</strong> tab, scroll to <strong>WAN Parameters</strong>.</li>
                                        <li>Locate the checkbox for <strong>"Respond to Ping from WAN"</strong> (or <em>"Allow WAN Ping"</em>).</li>
                                        <li><strong class="text-emerald-400">CHECK</strong> the box to Enable WAN Ping.</li>
                                        <li>Ensure <strong>MTU</strong> is set to <code class="text-cyan-400">1480</code> or <code class="text-cyan-400">1492</code>.</li>
                                        <li>Click <strong>OK</strong> to apply changes.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BRAND 3: NETIS -->
                    <div class="tab-pane fade" id="brand-netis" role="tabpanel">
                        <div class="d-flex align-items-center justify-content-between p-2.5 rounded bg-slate-900 border border-slate-800 mb-3 text-xs font-mono">
                            <div><span class="text-slate-400">Default IP:</span> <strong class="text-cyan-400">192.168.1.1</strong> or <strong class="text-cyan-400">netis.cc</strong></div>
                            <div><span class="text-slate-400">Default Credentials:</span> <strong class="text-emerald-400">guest / guest@XXXX</strong> or <strong class="text-emerald-400">admin / password</strong></div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="border border-slate-700/50 rounded p-3 h-100 bg-surface-subtle">
                                    <div class="fw-bold text-dark-force text-xs mb-2 d-flex align-items-center gap-2">
                                        <span class="badge bg-cyan-950 text-cyan-400 border border-cyan-800">Step A</span>
                                        Enable Remote Web Management
                                    </div>
                                    <ol class="text-slate-500 text-xs ps-3 mb-0" style="line-height: 1.7;">
                                        <li>Open <code>192.168.1.1</code> and click <strong>Advanced</strong> (top right).</li>
                                        <li>In left sidebar, expand <strong>Management</strong> &gt; <strong>Access Control</strong>.</li>
                                        <li>Under <em>Remote Access</em>, select <strong class="text-emerald-400">Enable</strong>.</li>
                                        <li>Set <strong>Port</strong> to <code class="text-cyan-400 fw-bold">8080</code>.</li>
                                        <li>Set <strong>IP Address</strong> to <code class="text-cyan-400 fw-bold">0.0.0.0</code>.</li>
                                        <li>Click <strong>Save</strong>.</li>
                                    </ol>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="border border-slate-700/50 rounded p-3 h-100 bg-surface-subtle">
                                    <div class="fw-bold text-dark-force text-xs mb-2 d-flex align-items-center gap-2">
                                        <span class="badge bg-emerald-950 text-emerald-400 border border-emerald-800">Step B</span>
                                        Enable WAN Ping Response
                                    </div>
                                    <ol class="text-slate-500 text-xs ps-3 mb-0" style="line-height: 1.7;">
                                        <li>Under <strong>Management</strong> &gt; <strong>Access Control</strong> (or <strong>Firewall</strong>).</li>
                                        <li>Find the <strong>WAN Ping</strong> option.</li>
                                        <li>Choose <strong class="text-emerald-400">Enable</strong>.</li>
                                        <li>Click <strong>Save</strong> to restart management daemon.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BRAND 4: MERCUSYS -->
                    <div class="tab-pane fade" id="brand-mercusys" role="tabpanel">
                        <div class="d-flex align-items-center justify-content-between p-2.5 rounded bg-slate-900 border border-slate-800 mb-3 text-xs font-mono">
                            <div><span class="text-slate-400">Default IP:</span> <strong class="text-cyan-400">192.168.1.1</strong> or <strong class="text-cyan-400">mwlogin.net</strong></div>
                            <div><span class="text-slate-400">Default Credentials:</span> <strong class="text-emerald-400">Set password on initial configuration</strong></div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="border border-slate-700/50 rounded p-3 h-100 bg-surface-subtle">
                                    <div class="fw-bold text-dark-force text-xs mb-2 d-flex align-items-center gap-2">
                                        <span class="badge bg-cyan-950 text-cyan-400 border border-cyan-800">Step A</span>
                                        Remote Management Setup
                                    </div>
                                    <ol class="text-slate-500 text-xs ps-3 mb-0" style="line-height: 1.7;">
                                        <li>Click <strong>Advanced</strong> on the top right banner.</li>
                                        <li>Go to <strong>System Tools</strong> &gt; <strong>Web Management</strong>.</li>
                                        <li>Enable <strong>Remote Management</strong>.</li>
                                        <li>Port: <code class="text-cyan-400 fw-bold">8080</code> | Remote IP: <code class="text-cyan-400 fw-bold">255.255.255.255</code>.</li>
                                        <li>Click <strong>Save</strong>.</li>
                                    </ol>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="border border-slate-700/50 rounded p-3 h-100 bg-surface-subtle">
                                    <div class="fw-bold text-dark-force text-xs mb-2 d-flex align-items-center gap-2">
                                        <span class="badge bg-emerald-950 text-emerald-400 border border-emerald-800">Step B</span>
                                        WAN Ping Enable
                                    </div>
                                    <ol class="text-slate-500 text-xs ps-3 mb-0" style="line-height: 1.7;">
                                        <li>Go to <strong>Advanced</strong> &gt; <strong>Network</strong> &gt; <strong>WAN</strong> (or <strong>Security</strong>).</li>
                                        <li>Check <strong>"Respond to Ping from WAN"</strong>.</li>
                                        <li>Save settings.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BRAND 5: D-LINK / XIAOMI -->
                    <div class="tab-pane fade" id="brand-dlink" role="tabpanel">
                        <div class="d-flex align-items-center justify-content-between p-2.5 rounded bg-slate-900 border border-slate-800 mb-3 text-xs font-mono">
                            <div><span class="text-slate-400">D-Link Gateway:</span> <strong class="text-cyan-400">192.168.0.1</strong> / <strong>dlinkrouter.local</strong></div>
                            <div><span class="text-slate-400">Xiaomi Gateway:</span> <strong class="text-purple-400">192.168.31.1</strong> / <strong>miwifi.com</strong></div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <div class="border border-slate-700/50 rounded p-3 h-100 bg-surface-subtle">
                                    <div class="fw-bold text-dark-force text-xs mb-2 text-cyan-400">D-Link Remote &amp; Ping Guide</div>
                                    <ul class="text-slate-500 text-xs ps-3 mb-0" style="line-height: 1.7;">
                                        <li><strong>Remote Admin:</strong> Go to <code>Management</code> &gt; <code>System Admin</code> &gt; <code>Admin</code> &gt; Enable <em>"Enable Remote Management"</em> with Port <code>8080</code>.</li>
                                        <li><strong>WAN Ping:</strong> Go to <code>Features</code> &gt; <code>Firewall</code> &gt; <code>IPv4 Rules</code> &gt; Check <em>"Enable Inbound ICMP (Ping)"</em>.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="border border-slate-700/50 rounded p-3 h-100 bg-surface-subtle">
                                    <div class="fw-bold text-dark-force text-xs mb-2 text-purple-400">Xiaomi Mi Router Guide</div>
                                    <ul class="text-slate-500 text-xs ps-3 mb-0" style="line-height: 1.7;">
                                        <li><strong>Mi WiFi App:</strong> In Mi WiFi app &gt; <code>Toolbox</code> &gt; Enable Remote Web Access.</li>
                                        <li><strong>Inbound Ping:</strong> In web GUI (<code>192.168.31.1</code>) &gt; <code>Settings</code> &gt; <code>Security</code> &gt; Disable <em>"Prevent WAN ICMP Ping"</em>.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <!-- PPPoE & WAN Best Practices Checklist -->
        <div class="card card-noc">
            <div class="card-header py-2.5">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-list-check text-emerald-400 me-2"></i>Field Provisioning Best Practice Checklist
                </span>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row g-3 text-xs">
                    <div class="col-12 col-md-4">
                        <div class="p-2.5 rounded border border-slate-700/40 bg-surface-subtle">
                            <strong class="text-dark-force d-block mb-1">1. PPPoE MTU Sizing</strong>
                            <span class="text-slate-500 d-block">Set WAN MTU to <code class="text-cyan-400 fw-bold">1480</code> or <code class="text-cyan-400 fw-bold">1492</code>. Avoid default 1500 to prevent packet fragmentation, VPN stalls, and slow page loading.</span>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="p-2.5 rounded border border-slate-700/40 bg-surface-subtle">
                            <strong class="text-dark-force d-block mb-1">2. Primary &amp; Secondary DNS</strong>
                            <span class="text-slate-500 d-block">Assign Primary DNS <code class="text-emerald-400 fw-bold">8.8.8.8</code> and Secondary DNS <code class="text-emerald-400 fw-bold">1.1.1.1</code> (or ISP upstream BDIX caching DNS) in WAN settings.</span>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="p-2.5 rounded border border-slate-700/40 bg-surface-subtle">
                            <strong class="text-dark-force d-block mb-1">3. Wi-Fi 2.4GHz Channel</strong>
                            <span class="text-slate-500 d-block">Never leave on "Auto". Always fix to non-overlapping channels: <code class="text-amber-400 fw-bold">Channel 1, 6, or 11</code> with <code class="text-amber-400 fw-bold">20 MHz</code> bandwidth.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ============================================================== -->
    <!-- TAB 2: OPTICAL ONU LASER STANDARD LEVELS & HEALTH METER        -->
    <!-- ============================================================== -->
    <div class="tab-pane fade" id="tab-onu" role="tabpanel" aria-labelledby="tab-onu-btn">
        
        <!-- Live Interactive Optical Power Analyzer -->
        <div class="card card-noc mb-4 border-emerald-500/30">
            <div class="card-header d-flex justify-content-between align-items-center py-2.5">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-calculator text-emerald-400 me-2"></i>Interactive Optical ONU Laser Evaluator
                </span>
                <span class="badge bg-slate-900 border border-slate-700 text-slate-400 font-mono text-xs">Rx Optical Power (dBm)</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-4 align-items-center">
                    <div class="col-12 col-lg-5">
                        <label class="form-label-noc fw-semibold mb-1">Enter Measured ONU Rx Optical Power (dBm):</label>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text bg-slate-900 border-slate-700 text-slate-400 font-mono">
                                <i class="fa-solid fa-gauge"></i>
                            </span>
                            <input type="number" step="0.1" id="onuDbmInput" class="form-control form-control-noc font-mono fw-bold text-center fs-6" value="-21.5" min="-40" max="0">
                            <span class="input-group-text bg-slate-900 border-slate-700 text-slate-400 font-mono">dBm</span>
                        </div>
                        <input type="range" class="form-range" id="onuDbmSlider" min="-38" max="-5" step="0.5" value="-21.5">
                        <div class="d-flex justify-content-between font-mono text-slate-500" style="font-size: 10px;">
                            <span>-38 dBm (Loss)</span>
                            <span>-22 dBm (Target)</span>
                            <span>-5 dBm (Overload)</span>
                        </div>
                    </div>

                    <div class="col-12 col-lg-7">
                        <div class="p-3 rounded border border-slate-800 bg-slate-950 font-mono">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-slate-400 text-xs">Diagnostic Assessment:</span>
                                <span class="badge fs-6 px-2.5 py-1" id="onuHealthBadge">EXCELLENT SIGNAL</span>
                            </div>
                            <div class="progress bg-slate-900 mb-2" style="height: 10px;">
                                <div id="onuHealthBar" class="progress-bar bg-emerald-500" role="progressbar" style="width: 85%;"></div>
                            </div>
                            <div class="text-xs text-slate-300 mb-2" id="onuHealthDescription">
                                Perfect optical power. Zero bit errors, maximum packet throughput, and long ONU laser lifespan.
                            </div>
                            <div class="p-2 rounded bg-slate-900 border border-slate-800 text-xs" id="onuHealthAction">
                                <strong class="text-cyan-400"><i class="fa-solid fa-circle-check me-1"></i> Field Action:</strong> Ready for service activation. No splicing or cleaning needed.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Optical Standards Reference Table -->
        <div class="card card-noc mb-4">
            <div class="card-header py-2.5">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-table-list text-cyan-400 me-2"></i>EPON / GPON Optical Power Standard Reference Scale
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-noc align-middle mb-0 text-xs">
                        <thead>
                            <tr>
                                <th>Laser Rx Range (dBm)</th>
                                <th>Health Status</th>
                                <th>Bit Error Rate (BER)</th>
                                <th>Optical Line Condition</th>
                                <th>Technician Field Recommendation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-row-hover">
                                <td class="font-mono fw-bold text-emerald-400">-15.00 to -22.00 dBm</td>
                                <td><span class="badge badge-dept-noc">🟢 IDEAL / EXCELLENT</span></td>
                                <td class="font-mono text-slate-300">0% (Zero loss)</td>
                                <td class="text-slate-300">Target ISP Standard. Optimum laser sensitivity.</td>
                                <td class="text-slate-400">Excellent line. Ready for activation.</td>
                            </tr>
                            <tr class="table-row-hover">
                                <td class="font-mono fw-bold text-cyan-400">-22.01 to -25.00 dBm</td>
                                <td><span class="badge badge-header-cyan">🔵 NORMAL / GOOD</span></td>
                                <td class="font-mono text-slate-300">&lt; 10⁻¹²</td>
                                <td class="text-slate-300">Standard operating range in city distribution lines.</td>
                                <td class="text-slate-400">Stable for normal browsing and IPTV.</td>
                            </tr>
                            <tr class="table-row-hover">
                                <td class="font-mono fw-bold text-amber-400">-25.01 to -27.00 dBm</td>
                                <td><span class="badge bg-amber-500/20 text-amber-400 border border-amber-500/30">🟡 WARNING / MARGINAL</span></td>
                                <td class="font-mono text-amber-400">10⁻⁹ to 10⁻⁷</td>
                                <td class="text-slate-300">Borderline threshold. May drop packets during rain or temperature shifts.</td>
                                <td class="text-amber-300">Clean SC-UPC connector with alcohol wipe. Inspect drop wire for tight bends (macro-bends).</td>
                            </tr>
                            <tr class="table-row-hover">
                                <td class="font-mono fw-bold text-rose-500">-27.01 to -35.00 dBm</td>
                                <td><span class="badge bg-rose-500/20 text-rose-400 border border-rose-500/30">🔴 CRITICAL / LOS RISK</span></td>
                                <td class="font-mono text-rose-400">Severe (15%-60%)</td>
                                <td class="text-slate-300">Frequent PPPoE disconnection, high packet jitter, red LOS LED blinking on ONU.</td>
                                <td class="text-rose-400 fw-semibold">Immediate re-splice needed at TJ box or splitter output. Check for physical fiber core break.</td>
                            </tr>
                            <tr class="table-row-hover">
                                <td class="font-mono fw-bold text-purple-400">&gt; -8.00 dBm (-3 to -8 dBm)</td>
                                <td><span class="badge bg-purple-500/20 text-purple-300 border border-purple-500/30">🟣 OPTICAL OVERLOAD</span></td>
                                <td class="font-mono text-purple-400">Diode Saturation</td>
                                <td class="text-slate-300">Optical light is too strong! Can permanently burn the ONU photo-detector diode.</td>
                                <td class="text-purple-300 fw-semibold">Install an Optical Attenuator (5 dB or 10 dB) between patch cord and ONU immediately!</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Optical Loss Budget & Splitter Cheatsheet -->
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <div class="card card-noc h-100">
                    <div class="card-header py-2">
                        <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                            <i class="fa-solid fa-code-fork text-cyan-400 me-2"></i>PLC Splitter Insertion Loss Budget
                        </span>
                    </div>
                    <div class="card-body p-3">
                        <div class="table-responsive">
                            <table class="table table-sm table-noc font-mono text-xs mb-0">
                                <thead>
                                    <tr>
                                        <th>Splitter Ratio</th>
                                        <th>Average Loss (dB)</th>
                                        <th>Standard Application</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>1 : 2 Splitter</td>
                                        <td class="text-cyan-400">~ 3.5 dB</td>
                                        <td class="text-slate-400">Primary feeder tap</td>
                                    </tr>
                                    <tr>
                                        <td>1 : 4 Splitter</td>
                                        <td class="text-cyan-400">~ 7.2 dB</td>
                                        <td class="text-slate-400">Secondary branch</td>
                                    </tr>
                                    <tr>
                                        <td>1 : 8 Splitter</td>
                                        <td class="text-emerald-400">~ 10.5 dB</td>
                                        <td class="text-slate-400">Building / pole distribution box</td>
                                    </tr>
                                    <tr>
                                        <td>1 : 16 Splitter</td>
                                        <td class="text-amber-400">~ 14.0 dB</td>
                                        <td class="text-slate-400">Dense residential block</td>
                                    </tr>
                                    <tr>
                                        <td>1 : 32 Splitter</td>
                                        <td class="text-rose-400">~ 17.5 dB</td>
                                        <td class="text-slate-400">High-capacity GPON PON port</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="card card-noc h-100">
                    <div class="card-header py-2">
                        <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                            <i class="fa-solid fa-lightbulb text-amber-400 me-2"></i>ONU Laser Troubleshooting Checklist
                        </span>
                    </div>
                    <div class="card-body p-3 text-xs text-slate-400">
                        <ul class="mb-0 ps-3" style="line-height: 1.8;">
                            <li><strong>ONU Transmit (Tx) Power Standard:</strong> Normal Tx is <code class="text-emerald-400 font-mono">+1.5 dBm to +3.5 dBm</code>. If Tx drops below +1.0 dBm, the laser is degraded.</li>
                            <li><strong>Connector Inspection:</strong> Blue connector = <strong>SC-UPC</strong> (flat polish); Green connector = <strong>SC-APC</strong> (8° angle polish). Never mix UPC with APC connectors directly (causes 4dB+ loss and damage).</li>
                            <li><strong>Cleaning:</strong> Clean connector ferrule with fiber cleaning pen or 99% isopropyl alcohol wipe. Do not use dry shirt or tissue!</li>
                            <li><strong>Macro-bending Check:</strong> Avoid tight cable loops (minimum bend radius must be &gt; 30mm for standard G.652D fiber).</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ============================================================== -->
    <!-- TAB 3: WI-FI SIGNAL & PHONE PING DIAGNOSTIC SYSTEM            -->
    <!-- ============================================================== -->
    <div class="tab-pane fade" id="tab-wifi" role="tabpanel" aria-labelledby="tab-wifi-btn">
        
        <!-- Explanation Banner -->
        <div class="card card-noc mb-4 border-purple-500/30 bg-purple-950/10">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-purple-500 text-slate-950 font-mono fw-bold text-xs uppercase">Field SOP</span>
                    <h5 class="fw-bold text-dark-force mb-0 font-sans">Why Phone-to-Router Ping is the #1 Diagnostic Technique</h5>
                </div>
                <p class="text-slate-500 text-xs mb-0">
                    Over <strong>80% of customer complaints</strong> ("internet is slow", "video buffering", "disconnecting") are caused by local Wi-Fi channel interference, wall attenuation, or router CPU overload—<strong>NOT the ISP fiber line</strong>! 
                    By running a continuous ping directly from a smartphone to the router's local gateway (<code class="text-purple-400 font-mono">192.168.0.1</code>), technicians instantly prove whether the bottleneck is local wireless or upstream ISP.
                </p>
            </div>
        </div>

        <!-- 4 Step Field SOP Guide -->
        <div class="card card-noc mb-4">
            <div class="card-header py-2.5">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-mobile-screen text-purple-400 me-2"></i>Step-by-Step Smartphone Ping Diagnostic Protocol
                </span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <div class="p-3 rounded border border-slate-700/50 bg-surface-subtle h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-purple-950 text-purple-400 border border-purple-800 font-mono">STEP 1</span>
                                <strong class="text-dark-force text-xs font-sans">Install Ping App</strong>
                            </div>
                            <p class="text-slate-500 text-xs mb-2">Install a free network utility on the field technician's or subscriber's phone:</p>
                            <ul class="text-slate-500 text-xs ps-3 mb-0">
                                <li><strong>Android:</strong> <em>PingTools Network Utilities</em> or <em>HE.NET Network Tools</em>.</li>
                                <li><strong>iOS (iPhone):</strong> <em>Network Analyzer Lite</em> or <em>HE.NET</em>.</li>
                            </ul>
                        </div>
                    </div>

                    <div class="col-12 col-md-3">
                        <div class="p-3 rounded border border-slate-700/50 bg-surface-subtle h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-purple-950 text-purple-400 border border-purple-800 font-mono">STEP 2</span>
                                <strong class="text-dark-force text-xs font-sans">Connect to Wi-Fi</strong>
                            </div>
                            <p class="text-slate-500 text-xs mb-2">Connect smartphone to customer's Wi-Fi network.</p>
                            <div class="alert alert-warning py-1.5 px-2 font-mono text-xs mb-0 bg-amber-500/10 border-amber-500/30 text-amber-300">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i> Turn <strong>OFF Mobile Data</strong> (4G/5G) so traffic routes strictly via Wi-Fi.
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-md-3">
                        <div class="p-3 rounded border border-slate-700/50 bg-surface-subtle h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-purple-950 text-purple-400 border border-purple-800 font-mono">STEP 3</span>
                                <strong class="text-dark-force text-xs font-sans">Ping Router Gateway</strong>
                            </div>
                            <p class="text-slate-500 text-xs mb-2">Open the app and run continuous ping to the Router's Local Gateway IP:</p>
                            <div class="p-1.5 rounded bg-slate-900 border border-slate-800 font-mono text-cyan-400 text-xs text-center mb-1">
                                ping 192.168.0.1
                            </div>
                            <span class="text-slate-500" style="font-size: 11px;">(or <code>192.168.1.1</code> for Netis/Mercusys)</span>
                        </div>
                    </div>

                    <div class="col-12 col-md-3">
                        <div class="p-3 rounded border border-slate-700/50 bg-surface-subtle h-100">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-purple-950 text-purple-400 border border-purple-800 font-mono">STEP 4</span>
                                <strong class="text-dark-force text-xs font-sans">Read Latency &amp; Loss</strong>
                            </div>
                            <p class="text-slate-500 text-xs mb-2">Observe 30 to 50 packets:</p>
                            <ul class="text-slate-500 text-xs ps-3 mb-0">
                                <li><strong class="text-emerald-400">1 - 4 ms:</strong> Perfect Wi-Fi link.</li>
                                <li><strong class="text-amber-400">50 - 200 ms:</strong> Channel interference.</li>
                                <li><strong class="text-rose-500">Timeouts:</strong> Wall block or bad adapter.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Diagnostic Decision Matrix -->
        <div class="card card-noc mb-4">
            <div class="card-header py-2.5">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-network-wired text-cyan-400 me-2"></i>Diagnostic Decision Matrix: Interpreting Phone Ping Results
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-noc align-middle mb-0 text-xs">
                        <thead>
                            <tr>
                                <th>Phone to Router Ping</th>
                                <th>Packet Loss %</th>
                                <th>Root Cause Verdict</th>
                                <th>Actionable Field Resolution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="table-row-hover">
                                <td class="font-mono fw-bold text-emerald-400">1 ms - 4 ms (Stable)</td>
                                <td class="font-mono text-emerald-400">0% Loss</td>
                                <td>
                                    <span class="badge badge-dept-noc">🟢 LOCAL WI-FI IS 100% HEALTHY</span>
                                    <div class="text-slate-400 mt-1">Router wireless link, antennas, and device CPU are working perfectly.</div>
                                </td>
                                <td class="text-slate-300">
                                    Now ping the ISP Gateway (<code class="text-cyan-400">103.166.230.229</code>) or <code class="text-cyan-400">8.8.8.8</code>. If external ping drops, check optical laser Rx dBm or core bandwidth contention.
                                </td>
                            </tr>
                            <tr class="table-row-hover">
                                <td class="font-mono fw-bold text-amber-400">50 ms - 300 ms (Jitter / Spikes)</td>
                                <td class="font-mono text-amber-400">0% - 5% Loss</td>
                                <td>
                                    <span class="badge bg-amber-500/20 text-amber-400 border border-amber-500/30">🟡 2.4GHz WIRELESS CONGESTION</span>
                                    <div class="text-slate-400 mt-1">Neighboring Wi-Fi routers broadcasting on the exact same wireless channel.</div>
                                </td>
                                <td class="text-slate-300">
                                    Log into router settings. Change Wi-Fi channel from "Auto" to <strong>Channel 1, 6, or 11</strong>. Switch channel width to <strong>20 MHz</strong> (reduces overlap). Migrate user to 5GHz AC/AX band.
                                </td>
                            </tr>
                            <tr class="table-row-hover">
                                <td class="font-mono fw-bold text-rose-500">Request Timed Out / Fluctuating</td>
                                <td class="font-mono text-rose-400">&gt; 10% - 50% Loss</td>
                                <td>
                                    <span class="badge bg-rose-500/20 text-rose-400 border border-rose-500/30">🔴 PHYSICAL SIGNAL DEGRADATION OR FAULTY ADAPTER</span>
                                    <div class="text-slate-400 mt-1">Wall penetration loss, router CPU thermal throttling, or noisy 12V power supply.</div>
                                </td>
                                <td class="text-slate-300">
                                    1. Swap the router 12V 1A power adapter (bad adapters create high ripple and cause packet loss).<br>
                                    2. Relocate router to central elevated height (4-6 feet above floor).<br>
                                    3. Recommend a secondary Access Point (AP) or mesh node if through 2+ brick walls.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Wi-Fi Signal RSSI Scale & Interactive Simulator -->
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="card card-noc h-100">
                    <div class="card-header py-2">
                        <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                            <i class="fa-solid fa-signal text-cyan-400 me-2"></i>Wi-Fi Signal Strength (RSSI dBm) Standards
                        </span>
                    </div>
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between p-2 mb-2 rounded bg-surface-subtle border border-slate-700/40">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-wifi text-emerald-400 fs-5"></i>
                                <div>
                                    <strong class="text-dark-force text-xs d-block">-30 dBm to -50 dBm</strong>
                                    <span class="text-slate-500" style="font-size:11px;">Right next to router (Same room)</span>
                                </div>
                            </div>
                            <span class="badge badge-dept-noc">EXCELLENT (100% Speed)</span>
                        </div>

                        <div class="d-flex align-items-center justify-content-between p-2 mb-2 rounded bg-surface-subtle border border-slate-700/40">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-wifi text-cyan-400 fs-5"></i>
                                <div>
                                    <strong class="text-dark-force text-xs d-block">-50 dBm to -65 dBm</strong>
                                    <span class="text-slate-500" style="font-size:11px;">Normal room through 1 brick wall</span>
                                </div>
                            </div>
                            <span class="badge badge-header-cyan">GOOD (Stable 4K / Gaming)</span>
                        </div>

                        <div class="d-flex align-items-center justify-content-between p-2 mb-2 rounded bg-surface-subtle border border-slate-700/40">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-wifi text-amber-400 fs-5"></i>
                                <div>
                                    <strong class="text-dark-force text-xs d-block">-65 dBm to -75 dBm</strong>
                                    <span class="text-slate-500" style="font-size:11px;">Boundary coverage (Through 2 walls)</span>
                                </div>
                            </div>
                            <span class="badge bg-amber-500/20 text-amber-400 border border-amber-500/30">FAIR (Speed drops 50%)</span>
                        </div>

                        <div class="d-flex align-items-center justify-content-between p-2 rounded bg-surface-subtle border border-slate-700/40">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fa-solid fa-wifi text-rose-500 fs-5"></i>
                                <div>
                                    <strong class="text-dark-force text-xs d-block">-75 dBm to -90 dBm</strong>
                                    <span class="text-slate-500" style="font-size:11px;">Dead zone / Outer balcony</span>
                                </div>
                            </div>
                            <span class="badge bg-rose-500/20 text-rose-400 border border-rose-500/30">POOR (Dropouts &amp; Lag)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Interactive Fault Isolator Tool -->
            <div class="col-12 col-lg-6">
                <div class="card card-noc h-100 border-purple-500/30">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                            <i class="fa-solid fa-wand-magic-sparkles text-purple-400 me-2"></i>Quick Field Problem Isolator
                        </span>
                        <span class="badge bg-purple-950 text-purple-400 border border-purple-800 font-mono text-xs">Diagnostic Assistant</span>
                    </div>
                    <div class="card-body p-3">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label-noc">Phone to Router Ping:</label>
                                <select id="simPhonePing" class="form-select form-select-noc form-select-sm" onchange="runFaultSimulator()">
                                    <option value="good">1 - 4 ms (Stable, 0% Loss)</option>
                                    <option value="jitter">50 - 200 ms (Jitter / Spikes)</option>
                                    <option value="loss">Timeouts / 20%+ Loss</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label-noc">Optical ONU Rx dBm:</label>
                                <select id="simOnuLaser" class="form-select form-select-noc form-select-sm" onchange="runFaultSimulator()">
                                    <option value="good">-18 to -23 dBm (Good)</option>
                                    <option value="marginal">-26 to -27 dBm (Marginal)</option>
                                    <option value="critical">-29 to -35 dBm (Critical/LOS)</option>
                                    <option value="overload">-5 dBm (Overload)</option>
                                </select>
                            </div>
                        </div>

                        <div class="p-3 rounded bg-slate-950 border border-slate-800" id="simResultBox">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="text-slate-400 text-xs">Diagnosed Issue:</span>
                                <span class="badge badge-dept-noc font-mono" id="simBadge">ALL SYSTEMS NORMAL</span>
                            </div>
                            <div class="fw-bold text-emerald-400 text-xs mb-1 font-sans" id="simTitle">Home Wi-Fi &amp; Optical Fiber Both Healthy</div>
                            <p class="text-slate-400 text-xs mb-2" id="simDesc">If customer complains about slow speed, verify upstream bandwidth profile, router PPPoE negotiation, or customer device background download (torrent/cloud sync).</p>
                            <div class="p-2 rounded bg-slate-900 border border-slate-800 text-xs text-slate-300" id="simAction">
                                <strong class="text-cyan-400">Action:</strong> Run speedtest at fast.com / bdixtest via 5GHz or LAN cable.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Interactive Scripts for Laser Calculator and Fault Isolator -->
<script>
// Tab persistence
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash;
    if (hash === '#tab-onu') {
        const triggerEl = document.querySelector('#tab-onu-btn');
        if (triggerEl) bootstrap.Tab.getOrCreateInstance(triggerEl).show();
    } else if (hash === '#tab-wifi') {
        const triggerEl = document.querySelector('#tab-wifi-btn');
        if (triggerEl) bootstrap.Tab.getOrCreateInstance(triggerEl).show();
    }

    // Bind Laser Evaluator
    const numInput = document.getElementById('onuDbmInput');
    const sliderInput = document.getElementById('onuDbmSlider');

    function updateLaser(val) {
        val = parseFloat(val);
        if (isNaN(val)) val = -21.5;
        numInput.value = val.toFixed(1);
        sliderInput.value = val;

        const badge = document.getElementById('onuHealthBadge');
        const bar = document.getElementById('onuHealthBar');
        const desc = document.getElementById('onuHealthDescription');
        const action = document.getElementById('onuHealthAction');

        if (val > -8.0) {
            badge.className = 'badge bg-purple-500 text-slate-950 fs-6 px-2.5 py-1';
            badge.innerText = 'OPTICAL OVERLOAD (< -8 dBm)';
            bar.className = 'progress-bar bg-purple-500';
            bar.style.width = '100%';
            desc.innerHTML = '<span class="text-purple-400 fw-bold">DANGER:</span> Optical light is blinding the receiver diode. Will cause packet corruption and permanent hardware damage!';
            action.innerHTML = '<strong class="text-purple-400"><i class="fa-solid fa-triangle-exclamation me-1"></i> Field Action:</strong> Install a 5dB or 10dB Optical Attenuator at the ONU fiber port immediately.';
        } else if (val >= -22.0 && val <= -15.0) {
            badge.className = 'badge bg-emerald-500 text-slate-950 fs-6 px-2.5 py-1';
            badge.innerText = 'IDEAL / EXCELLENT (-15 to -22 dBm)';
            bar.className = 'progress-bar bg-emerald-500';
            bar.style.width = '90%';
            desc.innerText = 'Perfect optical power. Zero bit errors, maximum packet throughput, and long ONU laser lifespan.';
            action.innerHTML = '<strong class="text-emerald-400"><i class="fa-solid fa-circle-check me-1"></i> Field Action:</strong> Ready for service activation. No splicing or cleaning required.';
        } else if (val < -22.0 && val >= -25.0) {
            badge.className = 'badge bg-cyan-500 text-slate-950 fs-6 px-2.5 py-1';
            badge.innerText = 'GOOD / ACCEPTABLE (-22 to -25 dBm)';
            bar.className = 'progress-bar bg-cyan-500';
            bar.style.width = '70%';
            desc.innerText = 'Normal operating range in city distribution lines. Fully stable for normal internet and IPTV streaming.';
            action.innerHTML = '<strong class="text-cyan-400"><i class="fa-solid fa-circle-check me-1"></i> Field Action:</strong> Line is stable. Proceed with normal service.';
        } else if (val < -25.0 && val >= -27.0) {
            badge.className = 'badge bg-amber-500 text-slate-950 fs-6 px-2.5 py-1';
            badge.innerText = 'WARNING / MARGINAL (-25 to -27 dBm)';
            bar.className = 'progress-bar bg-amber-500';
            bar.style.width = '45%';
            desc.innerText = 'Borderline threshold. May experience packet drops during evening peak, heavy rain, or temperature drops.';
            action.innerHTML = '<strong class="text-amber-400"><i class="fa-solid fa-wrench me-1"></i> Field Action:</strong> Clean SC-UPC connector with alcohol wipe. Check drop cable for tight bends (macro-bends).';
        } else {
            badge.className = 'badge bg-rose-500 text-white fs-6 px-2.5 py-1';
            badge.innerText = 'CRITICAL / LOS RISK (< -27 dBm)';
            bar.className = 'progress-bar bg-rose-500';
            bar.style.width = '20%';
            desc.innerText = 'Critical optical attenuation! Causes frequent PPPoE disconnection, packet drops, and blinking red LOS LED.';
            action.innerHTML = '<strong class="text-rose-400"><i class="fa-solid fa-circle-xmark me-1"></i> Field Action:</strong> Urgent re-splice needed at distribution box. Inspect fiber core with VFL red laser pen.';
        }
    }

    numInput.addEventListener('input', function() { updateLaser(this.value); });
    sliderInput.addEventListener('input', function() { updateLaser(this.value); });
    updateLaser(-21.5);
});

// Interactive Fault Simulator
function runFaultSimulator() {
    const phonePing = document.getElementById('simPhonePing').value;
    const onuLaser = document.getElementById('simOnuLaser').value;

    const badge = document.getElementById('simBadge');
    const title = document.getElementById('simTitle');
    const desc = document.getElementById('simDesc');
    const action = document.getElementById('simAction');

    if (onuLaser === 'critical') {
        badge.className = 'badge bg-rose-500 text-white font-mono';
        badge.innerText = 'OPTICAL FIBER FAULT';
        title.className = 'fw-bold text-rose-400 text-xs mb-1 font-sans';
        title.innerText = 'Optical Signal Lost or Attenuation Critical (< -28 dBm)';
        desc.innerText = 'The physical optical line from distribution splitter to customer ONU is degraded or broken. Red LOS light will be blinking.';
        action.innerHTML = '<strong class="text-rose-400">Resolution:</strong> Dispatch line technician with fusion splicer and optical power meter to inspect drop cable and re-cleave fiber.';
        return;
    }

    if (phonePing === 'jitter') {
        badge.className = 'badge bg-amber-500 text-slate-950 font-mono';
        badge.innerText = 'LOCAL WI-FI CONGESTION';
        title.className = 'fw-bold text-amber-400 text-xs mb-1 font-sans';
        title.innerText = '2.4GHz Wireless Interference / Channel Overlap';
        desc.innerText = 'High ping latency between smartphone and router proves the problem is local Wi-Fi noise, NOT the ISP optical fiber!';
        action.innerHTML = '<strong class="text-amber-400">Resolution:</strong> Change router Wi-Fi channel from "Auto" to fixed Channel 1, 6, or 11. Set Bandwidth to 20MHz. Connect phone to 5GHz Wi-Fi.';
        return;
    }

    if (phonePing === 'loss') {
        badge.className = 'badge bg-rose-500 text-white font-mono';
        badge.innerText = 'ROUTER HARDWARE / WALL OBSTACLE';
        title.className = 'fw-bold text-rose-400 text-xs mb-1 font-sans';
        title.innerText = 'Local Wireless Packet Loss to Router';
        desc.innerText = 'Smartphone cannot communicate reliably with the router. Likely caused by a faulty 12V power supply (voltage ripple) or multiple concrete walls.';
        action.innerHTML = '<strong class="text-rose-400">Resolution:</strong> Replace 12V 1A power adapter, reposition router to an open elevated area (4-6 ft), or add a secondary Access Point.';
        return;
    }

    if (onuLaser === 'marginal') {
        badge.className = 'badge bg-amber-500 text-slate-950 font-mono';
        badge.innerText = 'BORDERLINE OPTICAL POWER';
        title.className = 'fw-bold text-amber-400 text-xs mb-1 font-sans';
        title.innerText = 'Local Wi-Fi Healthy, But Fiber Laser Marginal (-26 dBm)';
        desc.innerText = 'Phone-to-router is healthy (1-4ms), but the optical power is near the -27 dBm limit. May cause occasional latency during heavy rain or night temperature drops.';
        action.innerHTML = '<strong class="text-amber-400">Resolution:</strong> Clean optical SC-UPC connector with 99% alcohol and ensure drop cable has no tight bends.';
        return;
    }

    if (onuLaser === 'overload') {
        badge.className = 'badge bg-purple-500 text-slate-950 font-mono';
        badge.innerText = 'OPTICAL SATURATION';
        title.className = 'fw-bold text-purple-400 text-xs mb-1 font-sans';
        title.innerText = 'Optical Light Overload (> -8 dBm)';
        desc.innerText = 'Light is too strong and blinding the ONU photo-receiver diode.';
        action.innerHTML = '<strong class="text-purple-400">Resolution:</strong> Install a 5dB or 10dB attenuator at the ONU fiber port immediately.';
        return;
    }

    // Default clean
    badge.className = 'badge badge-dept-noc font-mono';
    badge.innerText = 'ALL SYSTEMS HEALTHY';
    title.className = 'fw-bold text-emerald-400 text-xs mb-1 font-sans';
    title.innerText = 'Home Wi-Fi & Optical Fiber Both Healthy';
    desc.innerText = 'Phone-to-router ping is 1-4ms and ONU laser is optimal. Both local wireless and optical link are in ideal condition.';
    action.innerHTML = '<strong class="text-cyan-400">Resolution:</strong> Test upstream speed via LAN cable or 5GHz Wi-Fi. Check if background download or PPPoE profile limit is active.';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
