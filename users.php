<?php
// users.php - Portal User Management & Granular Role Permissions
require_once __DIR__ . '/config/auth.php';
require_login();
require_permission('users', 'view');

$user_data = get_current_user_data();
$all_modules = get_all_modules();

// Fetch all roles
$stmt = $pdo->query("
    SELECT r.*, COUNT(u.id) as user_count 
    FROM roles r 
    LEFT JOIN users u ON u.role_id = r.id 
    GROUP BY r.id 
    ORDER BY r.id ASC
");
$roles = $stmt->fetchAll();

// Determine active role for matrix tab
$active_role_id = isset($_GET['role_id']) ? (int)$_GET['role_id'] : ($roles[0]['id'] ?? 1);
$active_tab = isset($_GET['tab']) ? clean_input($_GET['tab']) : 'users';

// Fetch active role details
$stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
$stmt->execute([$active_role_id]);
$current_role = $stmt->fetch();
if (!$current_role && !empty($roles)) {
    $current_role = $roles[0];
    $active_role_id = $current_role['id'];
}

// ==========================================
// HANDLE ACTIONS
// ==========================================

// 1. ADD USER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    require_permission('users', 'add');

    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id = (int)($_POST['role_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['Active', 'Suspended']) ? $_POST['status'] : 'Active';

    // Validation
    if (empty($username) || empty($full_name) || empty($password) || empty($role_id)) {
        set_flash_message('danger', 'Please fill in all required fields (Username, Full Name, Password, Role).');
    } elseif (strlen($password) < 6) {
        set_flash_message('danger', 'Password must be at least 6 characters long.');
    } else {
        // Check duplicate username
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            set_flash_message('danger', "Username '{$username}' is already taken. Please choose another.");
        } else {
            // Get role name
            $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
            $stmt->execute([$role_id]);
            $role_name = $stmt->fetchColumn() ?: 'Operator';

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, email, phone, role, role_id, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $hashed_password, $full_name, $email, $phone, $role_name, $role_id, $status]);

            set_flash_message('success', "New user account '{$username}' ({$role_name}) created successfully!");
            header('Location: users.php?tab=users');
            exit;
        }
    }
}

// 2. EDIT USER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    require_permission('users', 'edit');

    $user_id = (int)($_POST['user_id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id = (int)($_POST['role_id'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['Active', 'Suspended']) ? $_POST['status'] : 'Active';

    // Fetch existing user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $target_user = $stmt->fetch();

    if (!$target_user) {
        set_flash_message('danger', 'User record not found.');
    } else {
        // Prevent demoting primary Admin
        if ($target_user['id'] == 1 && $role_id != 1) {
            set_flash_message('danger', 'Primary Super Administrator cannot be changed to another role.');
            header('Location: users.php?tab=users');
            exit;
        }

        // Get role name
        $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
        $stmt->execute([$role_id]);
        $role_name = $stmt->fetchColumn() ?: $target_user['role'];

        if (!empty($password)) {
            if (strlen($password) < 6) {
                set_flash_message('danger', 'New password must be at least 6 characters long.');
                header('Location: users.php?tab=users');
                exit;
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, phone = ?, role = ?, role_id = ?, status = ?, password = ?
                WHERE id = ?
            ");
            $stmt->execute([$full_name, $email, $phone, $role_name, $role_id, $status, $hashed_password, $user_id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, phone = ?, role = ?, role_id = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$full_name, $email, $phone, $role_name, $role_id, $status, $user_id]);
        }

        // If updating currently logged in user, refresh session
        if ($user_id == $_SESSION['user_id']) {
            $_SESSION['full_name'] = $full_name;
            $_SESSION['role'] = $role_name;
            $_SESSION['role_id'] = $role_id;
            load_user_permissions($role_id, $role_name);
        }

        set_flash_message('success', "User account '{$target_user['username']}' updated successfully!");
        header('Location: users.php?tab=users');
        exit;
    }
}

// 3. TOGGLE STATUS
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    require_permission('users', 'edit');

    $id = (int)$_GET['id'];
    if ($id === (int)$_SESSION['user_id']) {
        set_flash_message('warning', 'You cannot suspend your own logged-in account.');
    } elseif ($id === 1) {
        set_flash_message('warning', 'Primary Super Administrator account cannot be suspended.');
    } else {
        $stmt = $pdo->prepare("SELECT status, username FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if ($u) {
            $new_status = $u['status'] === 'Active' ? 'Suspended' : 'Active';
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            set_flash_message('info', "Account '{$u['username']}' status changed to {$new_status}.");
        }
    }
    header('Location: users.php?tab=users');
    exit;
}

// 4. DELETE USER
if (isset($_GET['delete_user_id'])) {
    require_permission('users', 'delete');

    $id = (int)$_GET['delete_user_id'];
    if ($id === (int)$_SESSION['user_id']) {
        set_flash_message('danger', 'Security violation: You cannot delete your own logged-in account.');
    } elseif ($id === 1) {
        set_flash_message('danger', 'Primary Super Administrator account cannot be deleted.');
    } else {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $uname = $stmt->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        set_flash_message('success', "User account '{$uname}' has been permanently deleted.");
    }
    header('Location: users.php?tab=users');
    exit;
}

// 5. CREATE ROLE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_role') {
    require_permission('users', 'add');

    $role_name = trim($_POST['role_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($role_name)) {
        set_flash_message('danger', 'Role name cannot be empty.');
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM roles WHERE name = ?");
        $stmt->execute([$role_name]);
        if ($stmt->fetchColumn() > 0) {
            set_flash_message('danger', "Role '{$role_name}' already exists.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO roles (name, description, is_system) VALUES (?, ?, 0)");
            $stmt->execute([$role_name, $description]);
            $new_role_id = $pdo->lastInsertId();

            // Populate default empty permissions for this new role
            foreach (array_keys($all_modules) as $mod) {
                $pdo->prepare("
                    INSERT INTO role_permissions (role_id, module, can_view, can_add, can_edit, can_delete)
                    VALUES (?, ?, 0, 0, 0, 0)
                ")->execute([$new_role_id, $mod]);
            }

            set_flash_message('success', "Custom role '{$role_name}' created. Configure its page permissions below.");
            header("Location: users.php?tab=matrix&role_id={$new_role_id}");
            exit;
        }
    }
}

// 6. SAVE PERMISSION MATRIX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_permissions') {
    require_permission('users', 'edit');

    $role_id = (int)($_POST['role_id'] ?? 0);
    $submitted_perms = $_POST['perms'] ?? [];

    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$role_id]);
    $role = $stmt->fetch();

    if (!$role) {
        set_flash_message('danger', 'Invalid role selected.');
    } else {
        foreach (array_keys($all_modules) as $mod) {
            $can_view = isset($submitted_perms[$mod]['view']) ? 1 : 0;
            $can_add = isset($submitted_perms[$mod]['add']) ? 1 : 0;
            $can_edit = isset($submitted_perms[$mod]['edit']) ? 1 : 0;
            $can_delete = isset($submitted_perms[$mod]['delete']) ? 1 : 0;

            // If Admin role, force all to 1
            if (strtolower($role['name']) === 'admin') {
                $can_view = $can_add = $can_edit = $can_delete = 1;
            }

            $stmt = $pdo->prepare("
                INSERT INTO role_permissions (role_id, module, can_view, can_add, can_edit, can_delete)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    can_view = VALUES(can_view),
                    can_add = VALUES(can_add),
                    can_edit = VALUES(can_edit),
                    can_delete = VALUES(can_delete)
            ");
            $stmt->execute([$role_id, $mod, $can_view, $can_add, $can_edit, $can_delete]);
        }

        // If the updated role is the logged-in user's role, reload permissions now
        if ($role_id == ($_SESSION['role_id'] ?? 0) || strtolower($role['name']) == strtolower($_SESSION['role'] ?? '')) {
            load_user_permissions($role_id, $role['name']);
        }

        set_flash_message('success', "Permissions matrix for '{$role['name']}' saved successfully!");
        header("Location: users.php?tab=matrix&role_id={$role_id}");
        exit;
    }
}

// 7. DELETE CUSTOM ROLE
if (isset($_GET['delete_role_id'])) {
    require_permission('users', 'delete');

    $del_role_id = (int)$_GET['delete_role_id'];
    $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
    $stmt->execute([$del_role_id]);
    $del_role = $stmt->fetch();

    if (!$del_role) {
        set_flash_message('danger', 'Role not found.');
    } elseif ($del_role['is_system'] == 1 || strtolower($del_role['name']) === 'admin') {
        set_flash_message('danger', 'System protected role cannot be deleted.');
    } else {
        // Check if any users currently have this role
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
        $stmt->execute([$del_role_id]);
        $assigned_count = $stmt->fetchColumn();

        if ($assigned_count > 0) {
            set_flash_message('warning', "Cannot delete role '{$del_role['name']}': {$assigned_count} user(s) are currently assigned. Reassign them first.");
        } else {
            $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$del_role_id]);
            $pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$del_role_id]);
            set_flash_message('success', "Role '{$del_role['name']}' and its permission set were deleted.");
        }
    }
    header("Location: users.php?tab=matrix");
    exit;
}

// Fetch all users with role details
$stmt = $pdo->query("
    SELECT u.*, r.name as role_name, r.description as role_desc 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id 
    ORDER BY u.id ASC
");
$users = $stmt->fetchAll();

// Fetch permissions for active role in matrix
$active_role_permissions = [];
if ($active_role_id) {
    $stmt = $pdo->prepare("SELECT module, can_view, can_add, can_edit, can_delete FROM role_permissions WHERE role_id = ?");
    $stmt->execute([$active_role_id]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $active_role_permissions[$r['module']] = $r;
    }
}

// KPI counters
$total_users = count($users);
$active_users_count = 0;
$suspended_users_count = 0;
foreach ($users as $u) {
    if ($u['status'] === 'Active') $active_users_count++;
    else $suspended_users_count++;
}
$total_roles = count($roles);

$page_title = 'User Management & Permissions';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h4 class="fw-bold text-white mb-0 font-sans">User Management &amp; Role Permissions</h4>
            <span class="badge badge-header-cyan font-mono text-xs">
                <i class="fa-solid fa-shield-halved me-1"></i> RBAC Security
            </span>
        </div>
        <p class="text-slate-400 text-xs mb-0">Create portal operator logins, configure granular page access, and set View, Add, Edit, Delete permissions for every module.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if (has_permission('users', 'add')): ?>
            <button class="btn btn-sm btn-cyan font-mono text-xs" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fa-solid fa-user-plus me-1"></i> + Create User
            </button>
            <button class="btn btn-sm btn-outline-cyan font-mono text-xs" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                <i class="fa-solid fa-layer-group me-1"></i> + New Role
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="card card-noc kpi-card h-100">
            <div class="kpi-title d-flex justify-content-between align-items-center">
                <span>Total Portal Users</span>
                <i class="fa-solid fa-users-gear text-cyan-400"></i>
            </div>
            <div class="kpi-value text-cyan-400"><?= $total_users ?></div>
            <div class="kpi-subtext">Registered portal accounts</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="card card-noc kpi-card h-100">
            <div class="kpi-title d-flex justify-content-between align-items-center">
                <span>Active Operators</span>
                <span class="radar-dot"></span>
            </div>
            <div class="kpi-value text-emerald-400"><?= $active_users_count ?></div>
            <div class="kpi-subtext">Authorized &amp; active lines</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="card card-noc kpi-card h-100">
            <div class="kpi-title d-flex justify-content-between align-items-center">
                <span>Defined Access Roles</span>
                <i class="fa-solid fa-id-card-clip text-purple-400"></i>
            </div>
            <div class="kpi-value text-purple-400"><?= $total_roles ?></div>
            <div class="kpi-subtext">Permission security profiles</div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-xl-3">
        <div class="card card-noc kpi-card h-100">
            <div class="kpi-title d-flex justify-content-between align-items-center">
                <span>Suspended Logins</span>
                <i class="fa-solid fa-user-lock text-rose-400"></i>
            </div>
            <div class="kpi-value <?= $suspended_users_count > 0 ? 'text-rose-400' : 'text-slate-500' ?>"><?= $suspended_users_count ?></div>
            <div class="kpi-subtext">Restricted or disabled</div>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs nav-tabs-noc mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $active_tab !== 'matrix' ? 'active' : '' ?>" href="users.php?tab=users">
            <i class="fa-solid fa-users me-1 text-cyan-400"></i> Portal Users Directory (<?= $total_users ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $active_tab === 'matrix' ? 'active' : '' ?>" href="users.php?tab=matrix&role_id=<?= $active_role_id ?>">
            <i class="fa-solid fa-table-cells me-1 text-purple-400"></i> Role Permission Matrix (<?= count($all_modules) ?> Modules)
        </a>
    </li>
</ul>

<!-- TAB CONTENT 1: PORTAL USERS DIRECTORY -->
<?php if ($active_tab !== 'matrix'): ?>
    <div class="card card-noc">
        <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                    <i class="fa-solid fa-user-shield text-cyan-400 me-2"></i>Operator User Directory
                </span>
                <span class="badge bg-slate-900 border border-slate-700 text-slate-400 font-mono text-xs"><?= count($users) ?> Users</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="max-width: 250px;">
                    <span class="input-group-text bg-slate-900 border-slate-700 text-slate-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" id="userTableSearch" class="form-control form-control-noc" placeholder="Search user, name, role...">
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-noc align-middle" id="usersTable">
                    <thead>
                        <tr>
                            <th>User Profile</th>
                            <th>Username</th>
                            <th>Contact Info</th>
                            <th>Assigned Role</th>
                            <th>Status</th>
                            <th>Last Active</th>
                            <th>Registered</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle bg-slate-900 border border-slate-700 text-cyan-400 fw-bold d-flex align-items-center justify-content-center font-mono" style="width: 32px; height: 32px; font-size: 11px;">
                                            <?= strtoupper(substr($u['full_name'] ?: $u['username'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-slate-200"><?= htmlspecialchars($u['full_name']) ?></div>
                                            <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                                <span class="badge badge-investment-partner font-mono" style="font-size: 9px;"><i class="fa-solid fa-user-check me-1"></i>You (Current Session)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="font-mono text-cyan-400 fw-semibold text-xs">@<?= htmlspecialchars($u['username']) ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($u['phone'])): ?>
                                        <div class="text-slate-300 font-mono text-xs"><i class="fa-solid fa-phone me-1 text-slate-500"></i><?= htmlspecialchars($u['phone']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($u['email'])): ?>
                                        <div class="text-slate-400 text-xs"><i class="fa-regular fa-envelope me-1 text-slate-500"></i><?= htmlspecialchars($u['email']) ?></div>
                                    <?php endif; ?>
                                    <?php if (empty($u['phone']) && empty($u['email'])): ?>
                                        <span class="text-slate-500 text-xs">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $role_badge = 'badge-dept-default';
                                    $role_icon = 'fa-user-tag';
                                    $rname = strtolower($u['role_name'] ?: $u['role']);
                                    if ($rname === 'admin') {
                                        $role_badge = 'badge-dept-noc';
                                        $role_icon = 'fa-crown';
                                    } elseif ($rname === 'manager') {
                                        $role_badge = 'badge-investment-partner';
                                        $role_icon = 'fa-user-tie';
                                    } elseif ($rname === 'operator') {
                                        $role_badge = 'badge-dept-support';
                                        $role_icon = 'fa-headset';
                                    } elseif ($rname === 'accountant') {
                                        $role_badge = 'badge-dept-billing';
                                        $role_icon = 'fa-file-invoice-dollar';
                                    } elseif (strpos($rname, 'collector') !== false || strpos($rname, 'field') !== false) {
                                        $role_badge = 'badge-dept-field';
                                        $role_icon = 'fa-motorcycle';
                                    }
                                    ?>
                                    <a href="users.php?tab=matrix&role_id=<?= $u['role_id'] ?: 1 ?>" class="text-decoration-none">
                                        <span class="badge <?= $role_badge ?> font-mono text-xs">
                                            <i class="fa-solid <?= $role_icon ?> me-1"></i><?= htmlspecialchars($u['role_name'] ?: $u['role']) ?>
                                        </span>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($u['status'] === 'Active'): ?>
                                        <span class="badge badge-active"><i class="fa-solid fa-circle-check me-1"></i> Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-suspended"><i class="fa-solid fa-ban me-1"></i> Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td class="font-mono text-slate-400 text-xs">
                                    <?= !empty($u['last_login']) ? date('d M Y, h:i A', strtotime($u['last_login'])) : '<span class="text-slate-500">Never</span>' ?>
                                </td>
                                <td class="font-mono text-slate-500 text-xs">
                                    <?= date('d M Y', strtotime($u['created_at'])) ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <?php if (has_permission('users', 'edit')): ?>
                                            <button class="btn btn-outline-secondary border-slate-800 p-1 px-2 text-slate-300" onclick='openEditUserModal(<?= json_encode($u) ?>)' title="Edit User">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <?php if ($u['id'] != 1 && $u['id'] != $_SESSION['user_id']): ?>
                                                <a href="users.php?toggle_status=1&id=<?= $u['id'] ?>" class="btn btn-outline-secondary border-slate-800 p-1 px-2 text-slate-300" title="Toggle Active / Suspended">
                                                    <i class="fa-solid fa-rotate"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if (has_permission('users', 'delete') && $u['id'] != 1 && $u['id'] != $_SESSION['user_id']): ?>
                                            <a href="users.php?delete_user_id=<?= $u['id'] ?>" class="btn btn-outline-danger border-slate-800 p-1 px-2" data-confirm-delete="Permanently delete user account @<?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['full_name']) ?>)?" data-item-name="<?= htmlspecialchars($u['full_name']) ?>" data-item-code="@<?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['role_name'] ?: $u['role']) ?>)" title="Delete User">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- TAB CONTENT 2: ROLE PERMISSION MATRIX -->
<?php if ($active_tab === 'matrix'): ?>
    <div class="row g-3">
        <!-- Left: Roles Selector Sidebar -->
        <div class="col-12 col-lg-3">
            <div class="card card-noc mb-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                        <i class="fa-solid fa-id-badge text-cyan-400 me-2"></i>Select Role
                    </span>
                    <?php if (has_permission('users', 'add')): ?>
                        <button class="btn btn-sm btn-outline-cyan p-0 px-2 font-mono text-xs" data-bs-toggle="modal" data-bs-target="#addRoleModal" title="Create New Role">
                            <i class="fa-solid fa-plus"></i> New
                        </button>
                    <?php endif; ?>
                </div>
                <div class="list-group list-group-flush bg-transparent">
                    <?php foreach ($roles as $r): ?>
                        <a href="users.php?tab=matrix&role_id=<?= $r['id'] ?>" class="list-group-item list-group-item-action bg-transparent border-slate-800 d-flex align-items-center justify-content-between p-2.5 <?= $active_role_id == $r['id'] ? 'active-role-item' : '' ?>">
                            <div>
                                <div class="fw-bold text-xs d-flex align-items-center gap-1.5">
                                    <?php if ($r['is_system']): ?>
                                        <i class="fa-solid fa-shield-halved text-cyan-400 font-size-12"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-user-group text-slate-400 font-size-12"></i>
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars($r['name']) ?></span>
                                </div>
                                <span class="text-slate-500 text-xs d-block text-truncate" style="max-width: 170px;">
                                    <?= htmlspecialchars($r['description'] ?: 'Custom role') ?>
                                </span>
                            </div>
                            <span class="badge bg-slate-900 border border-slate-700 text-slate-400 font-mono text-xs"><?= $r['user_count'] ?> Users</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Role Summary Card -->
            <div class="card card-noc p-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-bold text-xs text-uppercase text-slate-300">Active Profile</span>
                    <?php if ($current_role['is_system']): ?>
                        <span class="badge badge-investment-partner font-mono text-xs">System Role</span>
                    <?php else: ?>
                        <span class="badge badge-dept-support font-mono text-xs">Custom Role</span>
                    <?php endif; ?>
                </div>
                <h5 class="fw-bold text-cyan-400 mb-1"><?= htmlspecialchars($current_role['name']) ?></h5>
                <p class="text-slate-400 text-xs mb-3"><?= htmlspecialchars($current_role['description'] ?: 'No description provided.') ?></p>

                <?php if (!$current_role['is_system'] && has_permission('users', 'delete')): ?>
                    <a href="users.php?delete_role_id=<?= $current_role['id'] ?>" class="btn btn-sm btn-outline-danger w-100 font-mono text-xs" data-confirm-delete="Delete role '<?= htmlspecialchars($current_role['name']) ?>'? Any existing permissions will be removed." data-item-name="<?= htmlspecialchars($current_role['name']) ?>" data-item-code="Custom Role (ID: <?= $current_role['id'] ?>)">
                        <i class="fa-solid fa-trash me-1"></i> Delete Role
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Interactive Permission Matrix Table -->
        <div class="col-12 col-lg-9">
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="save_permissions">
                <input type="hidden" name="role_id" value="<?= $active_role_id ?>">

                <div class="card card-noc">
                    <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                        <div>
                            <span class="text-xs text-uppercase fw-semibold tracking-wider text-slate-300">
                                <i class="fa-solid fa-sliders text-cyan-400 me-2"></i>Granular Page &amp; Action Permissions: <span class="text-cyan-400"><?= htmlspecialchars($current_role['name']) ?></span>
                            </span>
                            <div class="text-slate-500 text-xs">Select which portal pages this role can View, Add, Edit, or Delete.</div>
                        </div>
                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary font-mono text-xs" onclick="setMatrixPreset('all')">
                                <i class="fa-solid fa-check-double me-1 text-cyan-400"></i> Select All
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary font-mono text-xs" onclick="setMatrixPreset('view_only')">
                                <i class="fa-solid fa-eye me-1 text-amber-400"></i> View Only
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary font-mono text-xs" onclick="setMatrixPreset('none')">
                                <i class="fa-solid fa-xmark me-1 text-rose-400"></i> Clear All
                            </button>
                            <?php if (has_permission('users', 'edit')): ?>
                                <button type="submit" class="btn btn-sm btn-cyan font-mono text-xs shadow-sm">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Permissions
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (strtolower($current_role['name']) === 'admin'): ?>
                        <div class="alert alert-info bg-cyan-950/40 border-cyan-800 text-cyan-300 text-xs m-3 mb-0 d-flex align-items-center gap-2">
                            <i class="fa-solid fa-circle-info fa-lg"></i>
                            <div>The <strong>Admin (Super Administrator)</strong> role always retains unrestricted master authority (View, Add, Edit, Delete on all 16 modules) to prevent system lockout.</div>
                        </div>
                    <?php endif; ?>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-noc align-middle" id="permissionMatrixTable">
                                <thead>
                                    <tr>
                                        <th style="min-width: 250px;">Portal Page / Module</th>
                                        <th style="min-width: 140px;">Category</th>
                                        <th class="text-center" style="width: 100px;">
                                            <div class="d-flex flex-column align-items-center">
                                                <span>View</span>
                                                <input type="checkbox" class="form-check-input mt-1" id="col_view" onclick="toggleColumn('view', this.checked)" title="Toggle all View">
                                            </div>
                                        </th>
                                        <th class="text-center" style="width: 100px;">
                                            <div class="d-flex flex-column align-items-center">
                                                <span>Add</span>
                                                <input type="checkbox" class="form-check-input mt-1" id="col_add" onclick="toggleColumn('add', this.checked)" title="Toggle all Add">
                                            </div>
                                        </th>
                                        <th class="text-center" style="width: 100px;">
                                            <div class="d-flex flex-column align-items-center">
                                                <span>Edit</span>
                                                <input type="checkbox" class="form-check-input mt-1" id="col_edit" onclick="toggleColumn('edit', this.checked)" title="Toggle all Edit">
                                            </div>
                                        </th>
                                        <th class="text-center" style="width: 100px;">
                                            <div class="d-flex flex-column align-items-center">
                                                <span>Delete</span>
                                                <input type="checkbox" class="form-check-input mt-1" id="col_delete" onclick="toggleColumn('delete', this.checked)" title="Toggle all Delete">
                                            </div>
                                        </th>
                                        <th class="text-center" style="width: 80px;">Row</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $prev_category = '';
                                    foreach ($all_modules as $mod_key => $mod): 
                                        $p = $active_role_permissions[$mod_key] ?? ['can_view' => 0, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0];
                                        $is_admin = strtolower($current_role['name']) === 'admin';
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded bg-slate-900 border border-slate-700 text-cyan-400 p-1.5 text-center" style="width: 30px; height: 30px;">
                                                        <i class="fa-solid <?= $mod['icon'] ?> font-size-12"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-slate-200"><?= htmlspecialchars($mod['name']) ?></div>
                                                        <div class="text-slate-500 font-mono" style="font-size: 10px;"><?= htmlspecialchars($mod['desc']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-subtle font-mono text-xs"><?= htmlspecialchars($mod['category']) ?></span>
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input perm-box perm-view row-<?= $mod_key ?>" name="perms[<?= $mod_key ?>][view]" value="1" <?= ($is_admin || !empty($p['can_view'])) ? 'checked' : '' ?> <?= $is_admin ? 'disabled' : '' ?>>
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input perm-box perm-add row-<?= $mod_key ?>" name="perms[<?= $mod_key ?>][add]" value="1" <?= ($is_admin || !empty($p['can_add'])) ? 'checked' : '' ?> <?= $is_admin ? 'disabled' : '' ?>>
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input perm-box perm-edit row-<?= $mod_key ?>" name="perms[<?= $mod_key ?>][edit]" value="1" <?= ($is_admin || !empty($p['can_edit'])) ? 'checked' : '' ?> <?= $is_admin ? 'disabled' : '' ?>>
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input perm-box perm-delete row-<?= $mod_key ?>" name="perms[<?= $mod_key ?>][delete]" value="1" <?= ($is_admin || !empty($p['can_delete'])) ? 'checked' : '' ?> <?= $is_admin ? 'disabled' : '' ?>>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-outline-secondary border-slate-800 p-0 px-1 text-slate-400" onclick="toggleRow('<?= $mod_key ?>')" <?= $is_admin ? 'disabled' : '' ?> title="Toggle all 4 actions for this row">
                                                    <i class="fa-solid fa-arrows-rotate font-size-10"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if (has_permission('users', 'edit') && strtolower($current_role['name']) !== 'admin'): ?>
                        <div class="card-footer bg-slate-900 border-top border-slate-800 d-flex align-items-center justify-content-between p-3">
                            <span class="text-xs text-slate-400 font-mono">
                                <i class="fa-solid fa-lock-open me-1 text-emerald-400"></i>Changes apply instantly to all users with role '<strong><?= htmlspecialchars($current_role['name']) ?></strong>'.
                            </span>
                            <button type="submit" class="btn btn-sm btn-cyan font-mono text-xs px-3">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- ==========================================
     MODALS
     ========================================== -->

<!-- 1. Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-noc">
            <form method="POST" action="users.php" data-confirm-add="Create new portal user account?">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold text-white font-sans d-flex align-items-center gap-2">
                        <i class="fa-solid fa-user-plus text-cyan-400"></i> Create Portal User Account
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label-noc">Full Name <span class="text-rose-400">*</span></label>
                        <input type="text" name="full_name" class="form-control form-control-noc" required placeholder="e.g. Mahfuzur Rahman">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label-noc">Username <span class="text-rose-400">*</span></label>
                            <input type="text" name="username" class="form-control form-control-noc font-mono" required placeholder="e.g. mahfuz">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label-noc">Password <span class="text-rose-400">*</span></label>
                            <input type="password" name="password" class="form-control form-control-noc font-mono" required placeholder="Min 6 characters">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label-noc">Assigned Role <span class="text-rose-400">*</span></label>
                            <select name="role_id" class="form-select form-select-noc" required>
                                <option value="" disabled selected>Select Role...</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?> <?= $r['is_system'] ? '(System)' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label-noc">Account Status <span class="text-rose-400">*</span></label>
                            <select name="status" class="form-select form-select-noc" required>
                                <option value="Active" selected>Active</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-12 col-sm-6">
                            <label class="form-label-noc">Phone Number</label>
                            <input type="text" name="phone" class="form-control form-control-noc font-mono" placeholder="+880 1700-000000">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label-noc">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-noc" placeholder="operator@cityfiber.bd">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary font-mono text-xs" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-cyan font-mono text-xs">
                        <i class="fa-solid fa-check me-1"></i> Create User Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2. Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-noc">
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold text-white font-sans d-flex align-items-center gap-2">
                        <i class="fa-solid fa-user-pen text-cyan-400"></i> Edit Portal User: <span id="edit_username_title" class="text-cyan-400 font-mono"></span>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label-noc">Full Name <span class="text-rose-400">*</span></label>
                        <input type="text" name="full_name" id="edit_full_name" class="form-control form-control-noc" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label-noc">Assigned Role <span class="text-rose-400">*</span></label>
                            <select name="role_id" id="edit_role_id" class="form-select form-select-noc" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label-noc">Account Status <span class="text-rose-400">*</span></label>
                            <select name="status" id="edit_status" class="form-select form-select-noc" required>
                                <option value="Active">Active</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label-noc">Phone Number</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control form-control-noc font-mono">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label-noc">Email Address</label>
                            <input type="email" name="email" id="edit_email" class="form-control form-control-noc">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label-noc">Reset Password <span class="text-slate-500 font-mono">(leave blank to keep existing password)</span></label>
                        <input type="password" name="password" class="form-control form-control-noc font-mono" placeholder="Enter new password (optional)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary font-mono text-xs" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-cyan font-mono text-xs">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. Add Custom Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-noc">
            <form method="POST" action="users.php" data-confirm-add="Create new custom security role?">
                <input type="hidden" name="action" value="add_role">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold text-white font-sans d-flex align-items-center gap-2">
                        <i class="fa-solid fa-layer-group text-purple-400"></i> Create Custom Access Role
                    </h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label-noc">Role Name <span class="text-rose-400">*</span></label>
                        <input type="text" name="role_name" class="form-control form-control-noc font-mono" required placeholder="e.g. NOC Fiber Splicer">
                    </div>
                    <div class="mb-2">
                        <label class="form-label-noc">Description</label>
                        <textarea name="description" class="form-control form-control-noc" rows="2" placeholder="Brief explanation of this role's responsibilities..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary font-mono text-xs" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-purple font-mono text-xs">
                        <i class="fa-solid fa-plus me-1"></i> Create Role &amp; Open Matrix
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.active-role-item {
    background-color: rgba(8, 145, 178, 0.12) !important;
    border-left: 3px solid #0891b2 !important;
}
html.dark .active-role-item {
    background-color: rgba(6, 182, 212, 0.15) !important;
    border-left: 3px solid #06b6d4 !important;
}
.btn-purple {
    background-color: #7c3aed;
    color: #fff;
    border: 1px solid #6d28d9;
}
.btn-purple:hover {
    background-color: #6d28d9;
    color: #fff;
}
</style>

<script>
// Filter user table rows dynamically
document.getElementById('userTableSearch')?.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll('#usersTable tbody tr');
    rows.forEach(r => {
        const text = r.innerText.toLowerCase();
        r.style.display = text.includes(q) ? '' : 'none';
    });
});

// Edit user modal population
function openEditUserModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username_title').textContent = '@' + user.username;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_phone').value = user.phone || '';
    document.getElementById('edit_email').value = user.email || '';
    document.getElementById('edit_status').value = user.status || 'Active';
    document.getElementById('edit_role_id').value = user.role_id || 1;

    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

// Matrix helper presets
function setMatrixPreset(type) {
    const checkboxes = document.querySelectorAll('.perm-box:not(:disabled)');
    checkboxes.forEach(cb => {
        if (type === 'all') {
            cb.checked = true;
        } else if (type === 'none') {
            cb.checked = false;
        } else if (type === 'view_only') {
            cb.checked = cb.classList.contains('perm-view');
        }
    });
}

// Toggle an entire column (View, Add, Edit, Delete)
function toggleColumn(action, checked) {
    document.querySelectorAll('.perm-' + action + ':not(:disabled)').forEach(cb => {
        cb.checked = checked;
    });
}

// Toggle an entire row (all 4 actions for a module)
function toggleRow(modKey) {
    const rowBoxes = document.querySelectorAll('.row-' + modKey + ':not(:disabled)');
    const anyUnchecked = Array.from(rowBoxes).some(cb => !cb.checked);
    rowBoxes.forEach(cb => cb.checked = anyUnchecked);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

