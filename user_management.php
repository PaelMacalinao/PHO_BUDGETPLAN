<?php
/**
 * PHO Budgeting System - User Management (Admin Only)
 * Create user accounts and assign RBAC roles.
 */
require_once __DIR__ . '/config.php';
requireLogin();

if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$pdo = getConnection();
$message = '';
$msgType = '';
$formData = [
    'fullname' => '',
    'username' => '',
    'role' => 'staff',
];
$validRoles = ['admin', 'staff', 'viewer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'create_user') {
        $formData['fullname'] = trim($_POST['fullname'] ?? '');
        $formData['username'] = trim($_POST['username'] ?? '');
        $formData['role'] = strtolower(trim($_POST['role'] ?? 'staff'));
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($formData['fullname'] === '' || $formData['username'] === '' || $password === '' || $confirmPassword === '') {
            $message = 'Please complete all required account fields.';
            $msgType = 'error';
        } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $formData['username'])) {
            $message = 'Username must be 3-100 characters and may only contain letters, numbers, dots, dashes, or underscores.';
            $msgType = 'error';
        } elseif (!in_array($formData['role'], $validRoles, true)) {
            $message = 'Please select a valid role.';
            $msgType = 'error';
        } elseif (strlen($password) < 8) {
            $message = 'Password must be at least 8 characters long.';
            $msgType = 'error';
        } elseif ($password !== $confirmPassword) {
            $message = 'Password confirmation does not match.';
            $msgType = 'error';
        } else {
            try {
                // Database query for account creation with RBAC assignment.
                $stmt = $pdo->prepare("
                    INSERT INTO tbl_users (fullname, username, password, role)
                    VALUES (:fullname, :username, :password, :role)
                ");
                $stmt->execute([
                    ':fullname' => $formData['fullname'],
                    ':username' => $formData['username'],
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':role' => $formData['role'],
                ]);

                $message = 'User account created successfully.';
                $msgType = 'success';
                $formData = [
                    'fullname' => '',
                    'username' => '',
                    'role' => 'staff',
                ];
            } catch (PDOException $e) {
                if ((string)$e->getCode() === '23000') {
                    $message = 'That username is already in use.';
                } elseif ($formData['role'] === 'viewer' && stripos($e->getMessage(), 'Data truncated') !== false) {
                    $message = 'The database is not yet updated for the Viewer role. Run the viewer-role migration first.';
                } else {
                    error_log('User Management Error: ' . $e->getMessage());
                    $message = 'A database error occurred while creating the account.';
                }
                $msgType = 'error';
            }
        }
    }

    if ($action === 'reset_password') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmNewPassword = (string)($_POST['confirm_new_password'] ?? '');

        if ($targetUserId < 1) {
            $message = 'Invalid user account selected for password reset.';
            $msgType = 'error';
        } elseif ($newPassword === '' || $confirmNewPassword === '') {
            $message = 'Please enter and confirm the new password.';
            $msgType = 'error';
        } elseif (strlen($newPassword) < 8) {
            $message = 'New password must be at least 8 characters long.';
            $msgType = 'error';
        } elseif ($newPassword !== $confirmNewPassword) {
            $message = 'New password confirmation does not match.';
            $msgType = 'error';
        } else {
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM tbl_users WHERE id = :id LIMIT 1");
                $checkStmt->execute([':id' => $targetUserId]);
                if (!$checkStmt->fetch()) {
                    $message = 'User account not found.';
                    $msgType = 'error';
                } else {
                    $updateStmt = $pdo->prepare("
                        UPDATE tbl_users
                        SET password = :password
                        WHERE id = :id
                        LIMIT 1
                    ");
                    $updateStmt->execute([
                        ':password' => password_hash($newPassword, PASSWORD_DEFAULT),
                        ':id' => $targetUserId,
                    ]);
                    $message = 'Password updated successfully.';
                    $msgType = 'success';
                }
            } catch (PDOException $e) {
                error_log('User Password Reset Error: ' . $e->getMessage());
                $message = 'A database error occurred while resetting the password.';
                $msgType = 'error';
            }
        }
    }

    if ($action === 'delete_user') {
        $deleteUserId = (int)($_POST['user_id'] ?? 0);
        $currentUserId = currentUserId();

        if ($deleteUserId < 1) {
            $message = 'Invalid user account selected.';
            $msgType = 'error';
        } elseif ($currentUserId !== null && $deleteUserId === $currentUserId) {
            $message = 'You cannot delete the account you are currently signed in with.';
            $msgType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, role, fullname FROM tbl_users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $deleteUserId]);
                $targetUser = $stmt->fetch();

                if (!$targetUser) {
                    $message = 'User account not found.';
                    $msgType = 'error';
                } elseif (($targetUser['role'] ?? '') === 'admin') {
                    $adminTotal = (int)$pdo->query("SELECT COUNT(*) FROM tbl_users WHERE role = 'admin'")->fetchColumn();
                    if ($adminTotal <= 1) {
                        $message = 'You cannot delete the last remaining admin account.';
                        $msgType = 'error';
                    } else {
                        $deleteStmt = $pdo->prepare("DELETE FROM tbl_users WHERE id = :id");
                        $deleteStmt->execute([':id' => $deleteUserId]);
                        $message = 'User account deleted successfully.';
                        $msgType = 'success';
                    }
                } else {
                    $deleteStmt = $pdo->prepare("DELETE FROM tbl_users WHERE id = :id");
                    $deleteStmt->execute([':id' => $deleteUserId]);
                    $message = 'User account deleted successfully.';
                    $msgType = 'success';
                }
            } catch (PDOException $e) {
                error_log('User Delete Error: ' . $e->getMessage());
                $message = 'A database error occurred while deleting the account.';
                $msgType = 'error';
            }
        }
    }
}

try {
    // Database query for listing all users and their assigned RBAC roles.
    $users = $pdo->query("
        SELECT id, fullname, username, role, created_at
        FROM tbl_users
        ORDER BY created_at DESC, id DESC
    ")->fetchAll();
} catch (PDOException $e) {
    error_log('User List Error: ' . $e->getMessage());
    $users = [];
}

$adminCount = 0;
$staffCount = 0;
$viewerCount = 0;
foreach ($users as $user) {
    if (($user['role'] ?? '') === 'admin') {
        $adminCount++;
    } elseif (($user['role'] ?? '') === 'staff') {
        $staffCount++;
    } elseif (($user['role'] ?? '') === 'viewer') {
        $viewerCount++;
    }
}

$pageTitle = 'User Management';
$activeMenu = 'user_management';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-800">User Management</h2>
            <p class="text-sm text-gray-500">Create system accounts and assign access through role-based permissions.</p>
        </div>
        <div class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-brand-50 text-brand-700 text-sm font-semibold border border-brand-100">
            <i class="fa-solid fa-shield-halved"></i> Administrator Access
        </div>
    </div>

    <?php if ($message): ?>
    <div class="<?= $msgType === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700' ?> border rounded-xl px-5 py-3.5 mb-6 flex items-center gap-2 text-sm">
        <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <?= e($message) ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1.05fr)_minmax(340px,.95fr)] gap-6 mb-6">
        <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-brand-50 to-white">
                <h3 class="text-sm font-bold text-gray-600 uppercase tracking-wider">
                    <i class="fa-solid fa-user-plus text-brand-500 mr-1.5"></i> Create New Account
                </h3>
            </div>
            <form method="POST" class="px-5 sm:px-6 py-5 space-y-5">
                <input type="hidden" name="_action" value="create_user">

                <div>
                    <label for="fullname" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" id="fullname" name="fullname" required maxlength="255"
                           value="<?= e($formData['fullname']) ?>"
                           placeholder="Enter full name"
                           class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="username" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Username <span class="text-red-500">*</span></label>
                        <input type="text" id="username" name="username" required maxlength="100"
                               value="<?= e($formData['username']) ?>"
                               placeholder="e.g. jdelacruz"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition">
                        <p class="text-[11px] text-gray-400 mt-1">Allowed: letters, numbers, dots, dashes, and underscores.</p>
                    </div>
                    <div>
                        <label for="role" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Role-Based Access <span class="text-red-500">*</span></label>
                        <select id="role" name="role" required
                                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition">
                            <option value="staff" <?= $formData['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
                            <option value="admin" <?= $formData['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="viewer" <?= $formData['role'] === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Password <span class="text-red-500">*</span></label>
                        <input type="password" id="password" name="password" required minlength="8"
                               placeholder="Minimum 8 characters"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition">
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Confirm Password <span class="text-red-500">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                               placeholder="Re-enter password"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition">
                    </div>
                </div>

                <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                    <p class="font-semibold mb-1">RBAC Roles</p>
                    <p><strong>Admin</strong> can manage users, settings, and records. <strong>Staff</strong> can create and review only their own submissions. <strong>Viewer</strong> can review all submitted data across the system in read-only mode.</p>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-xs text-gray-400">Passwords are securely stored using PHP password hashing.</p>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition shadow-md">
                        <i class="fa-solid fa-floppy-disk text-xs"></i> Create Account
                    </button>
                </div>
            </form>
        </section>

        <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 sm:px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-600 uppercase tracking-wider">
                    <i class="fa-solid fa-chart-simple text-brand-500 mr-1.5"></i> Access Summary
                </h3>
            </div>
            <div class="px-5 sm:px-6 py-5">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                    <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-3">
                        <span class="block text-[10px] uppercase tracking-wider text-gray-400 font-bold">Total Users</span>
                        <span class="block text-xl font-bold text-gray-800 mt-1"><?= count($users) ?></span>
                    </div>
                    <div class="rounded-xl border border-red-100 bg-red-50 px-3 py-3">
                        <span class="block text-[10px] uppercase tracking-wider text-red-400 font-bold">Admins</span>
                        <span class="block text-xl font-bold text-red-600 mt-1"><?= $adminCount ?></span>
                    </div>
                    <div class="rounded-xl border border-brand-100 bg-brand-50 px-3 py-3">
                        <span class="block text-[10px] uppercase tracking-wider text-brand-500 font-bold">Staff</span>
                        <span class="block text-xl font-bold text-brand-700 mt-1"><?= $staffCount ?></span>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                        <span class="block text-[10px] uppercase tracking-wider text-slate-500 font-bold">Viewers</span>
                        <span class="block text-xl font-bold text-slate-700 mt-1"><?= $viewerCount ?></span>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-gray-600 uppercase tracking-wider">
                    <i class="fa-solid fa-users text-brand-500 mr-1.5"></i> Existing User Accounts
                </h3>
                <p class="text-sm text-gray-500 mt-1">All registered users and their assigned roles.</p>
            </div>
        </div>

        <?php if (empty($users)): ?>
        <div class="px-6 py-12 text-center">
            <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                <i class="fa-solid fa-user-slash text-2xl text-gray-300"></i>
            </div>
            <p class="text-sm text-gray-400">No user accounts found. Create one above.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr class="text-left text-[10px] uppercase tracking-wider text-gray-400">
                        <th class="px-5 sm:px-6 py-3 font-bold">Full Name</th>
                        <th class="px-5 py-3 font-bold">Username</th>
                        <th class="px-5 py-3 font-bold">Role</th>
                        <th class="px-5 py-3 font-bold">Created At</th>
                        <th class="px-5 sm:px-6 py-3 font-bold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($users as $user): ?>
                    <tr class="hover:bg-gray-50/80">
                        <td class="px-5 sm:px-6 py-4">
                            <span class="font-semibold text-gray-800"><?= e($user['fullname']) ?></span>
                        </td>
                        <td class="px-5 py-4 text-gray-500">@<?= e($user['username']) ?></td>
                        <td class="px-5 py-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?= $user['role'] === 'admin' ? 'bg-red-100 text-red-700' : ($user['role'] === 'viewer' ? 'bg-slate-100 text-slate-700' : 'bg-brand-100 text-brand-700') ?>">
                                <?= e($user['role']) ?>
                            </span>
                        </td>
                        <td class="px-5 py-4 text-gray-500 whitespace-nowrap"><?= date('M j, Y g:i A', strtotime($user['created_at'])) ?></td>
                        <td class="px-5 sm:px-6 py-4 text-right">
                            <?php
                            $isCurrentUser = currentUserId() !== null && (int)$user['id'] === currentUserId();
                            $isLastAdmin = $user['role'] === 'admin' && $adminCount <= 1;
                            $deleteDisabled = $isCurrentUser || $isLastAdmin;
                            $deleteTitle = $isCurrentUser
                                ? 'You cannot delete the account you are signed in with.'
                                : ($isLastAdmin ? 'You cannot delete the last remaining admin account.' : 'Delete this account');
                            ?>
                            <button type="button"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-100 transition mr-1"
                                    onclick="openResetPasswordPrompt(<?= (int)$user['id'] ?>, '<?= e(addslashes((string)$user['fullname'])) ?>')"
                                    title="Reset this user's password">
                                <i class="fa-solid fa-key"></i> Reset Password
                            </button>
                            <form method="POST" class="inline">
                                <input type="hidden" name="_action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold transition <?= $deleteDisabled ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-red-50 text-red-700 hover:bg-red-100 border border-red-100' ?>"
                                        <?= $deleteDisabled ? 'disabled' : 'onclick="return confirm(\'Delete this user account?\');"' ?>
                                        title="<?= e($deleteTitle) ?>">
                                    <i class="fa-solid fa-trash-can"></i> Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>

<form id="resetPasswordForm" method="POST" class="hidden">
    <input type="hidden" name="_action" value="reset_password">
    <input type="hidden" name="user_id" id="resetUserId" value="">
    <input type="hidden" name="new_password" id="resetNewPassword" value="">
    <input type="hidden" name="confirm_new_password" id="resetConfirmPassword" value="">
</form>

<script>
function openResetPasswordPrompt(userId, fullName) {
    var label = (fullName || '').trim() || 'this user';
    var newPassword = window.prompt('Enter new password for ' + label + ' (minimum 8 characters):', '');
    if (newPassword === null) return;

    var confirmPassword = window.prompt('Confirm new password:', '');
    if (confirmPassword === null) return;

    if (newPassword.length < 8) {
        window.alert('Password must be at least 8 characters long.');
        return;
    }
    if (newPassword !== confirmPassword) {
        window.alert('Password confirmation does not match.');
        return;
    }
    if (!window.confirm('Reset password for ' + label + '?')) {
        return;
    }

    document.getElementById('resetUserId').value = String(userId);
    document.getElementById('resetNewPassword').value = newPassword;
    document.getElementById('resetConfirmPassword').value = confirmPassword;
    document.getElementById('resetPasswordForm').submit();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
