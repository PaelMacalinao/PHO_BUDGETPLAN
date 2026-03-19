<?php
/**
 * PHO Budgeting System — Budget Version Management (Admin Only)
 * Create new budget years and toggle active status.
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

// ── Handle POST actions ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    // Add new version
    if ($action === 'add') {
        $yearName = trim($_POST['year_name'] ?? '');
        if ($yearName === '') {
            $message = 'Version name is required.';
            $msgType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO tbl_budget_versions (year_name, is_active) VALUES (:name, 0)");
                $stmt->execute([':name' => $yearName]);
                $message = "Budget version \"{$yearName}\" created successfully.";
                $msgType = 'success';
            } catch (PDOException $e) {
                if ((int)$e->getCode() === 23000) {
                    $message = "A version named \"{$yearName}\" already exists.";
                } else {
                    $message = 'Database error: ' . $e->getMessage();
                }
                $msgType = 'error';
            }
        }
    }

    // Set active version
    if ($action === 'set_active') {
        $verId = (int)($_POST['version_id'] ?? 0);
        if ($verId > 0) {
            try {
                $pdo->beginTransaction();
                $pdo->exec("UPDATE tbl_budget_versions SET is_active = 0");
                $stmt = $pdo->prepare("UPDATE tbl_budget_versions SET is_active = 1 WHERE id = :id");
                $stmt->execute([':id' => $verId]);
                $pdo->commit();
                $message = 'Active version updated.';
                $msgType = 'success';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = 'Failed to update active version.';
                $msgType = 'error';
            }
        }
    }

    // Delete version (only if it has 0 proposals)
    if ($action === 'delete') {
        $verId = (int)($_POST['version_id'] ?? 0);
        if ($verId > 0) {
            try {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_budget_proposals WHERE version_id = :id");
                $countStmt->execute([':id' => $verId]);
                $count = (int)$countStmt->fetchColumn();

                if ($count > 0) {
                    $message = "Cannot delete: this version has {$count} proposal(s) linked to it.";
                    $msgType = 'error';
                } else {
                    $activeCheck = $pdo->prepare("SELECT is_active FROM tbl_budget_versions WHERE id = :id");
                    $activeCheck->execute([':id' => $verId]);
                    $ver = $activeCheck->fetch();
                    if ($ver && (int)$ver['is_active'] === 1) {
                        $message = 'Cannot delete the currently active version.';
                        $msgType = 'error';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM tbl_budget_versions WHERE id = :id");
                        $stmt->execute([':id' => $verId]);
                        $message = 'Version deleted.';
                        $msgType = 'success';
                    }
                }
            } catch (PDOException $e) {
                $message = 'Failed to delete version.';
                $msgType = 'error';
            }
        }
    }
}

// ── Fetch versions with proposal counts ──────────
try {
    $versions = $pdo->query("
        SELECT bv.*,
               COUNT(bp.id) AS proposal_count
        FROM   tbl_budget_versions bv
        LEFT JOIN tbl_budget_proposals bp ON bp.version_id = bv.id
        GROUP BY bv.id
        ORDER BY bv.id DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $versions = [];
}

$pageTitle  = 'Budget Versions';
$activeMenu = 'manage_versions';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-800">Yearly Budget Versions</h2>
            <p class="text-sm text-gray-500">Create new fiscal years and manage which version is active.</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="<?= $msgType === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700' ?> border rounded-xl px-5 py-3.5 mb-6 flex items-center gap-2 text-sm">
        <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <?= e($message) ?>
    </div>
    <?php endif; ?>

    <!-- Add New Version Card -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 sm:p-6 mb-6">
        <h3 class="text-sm font-bold text-gray-600 uppercase tracking-wider mb-4">
            <i class="fa-solid fa-plus-circle text-brand-500 mr-1.5"></i> Add New Budget Year
        </h3>
        <form method="POST" class="flex flex-col sm:flex-row items-stretch sm:items-end gap-3">
            <input type="hidden" name="_action" value="add">
            <div class="flex-1">
                <label class="block text-xs font-medium text-gray-500 mb-1">Version Name</label>
                <input type="text" name="year_name" required placeholder="e.g. FY 2027"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 transition">
            </div>
            <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition shadow-md whitespace-nowrap">
                <i class="fa-solid fa-plus text-xs"></i> Create Version
            </button>
        </form>
    </div>

    <!-- Versions List -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-600 uppercase tracking-wider">
                <i class="fa-solid fa-layer-group text-brand-500 mr-1.5"></i> All Versions
            </h3>
        </div>

        <?php if (empty($versions)): ?>
        <div class="px-6 py-12 text-center">
            <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                <i class="fa-solid fa-calendar-xmark text-2xl text-gray-300"></i>
            </div>
            <p class="text-sm text-gray-400">No budget versions found. Create one above.</p>
        </div>
        <?php else: ?>
        <div class="divide-y divide-gray-100">
            <?php foreach ($versions as $v):
                $isActive = (int)$v['is_active'] === 1;
                $count    = (int)$v['proposal_count'];
            ?>
            <div class="px-5 sm:px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 <?= $isActive ? 'bg-brand-50/50' : '' ?>">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 <?= $isActive ? 'bg-brand-100 text-brand-600' : 'bg-gray-100 text-gray-400' ?>">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold text-gray-800"><?= e($v['year_name']) ?></span>
                            <?php if ($isActive): ?>
                            <span class="inline-flex items-center gap-1 bg-brand-100 text-brand-700 text-[10px] font-bold px-2 py-0.5 rounded-full">
                                <i class="fa-solid fa-circle text-[6px]"></i> ACTIVE
                            </span>
                            <?php endif; ?>
                        </div>
                        <span class="block text-xs text-gray-400">
                            <?= number_format($count) ?> proposal<?= $count !== 1 ? 's' : '' ?>
                            · Created <?= date('M j, Y', strtotime($v['created_at'])) ?>
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <?php if (!$isActive): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="_action" value="set_active">
                        <input type="hidden" name="version_id" value="<?= (int)$v['id'] ?>">
                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-brand-600 text-white text-xs font-semibold hover:bg-brand-700 transition shadow-sm"
                                onclick="return confirm('Set \'<?= e($v['year_name']) ?>\' as the active version?')">
                            <i class="fa-solid fa-check"></i> Set Active
                        </button>
                    </form>
                    <?php if ($count === 0): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="_action" value="delete">
                        <input type="hidden" name="version_id" value="<?= (int)$v['id'] ?>">
                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-500 text-white text-xs font-semibold hover:bg-red-600 transition shadow-sm"
                                onclick="return confirm('Delete \'<?= e($v['year_name']) ?>\'? This cannot be undone.')">
                            <i class="fa-solid fa-trash-can"></i> Delete
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="text-xs text-brand-600 font-medium italic">Currently Active</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Info Card -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-5 text-sm text-blue-700">
        <div class="flex items-start gap-3">
            <i class="fa-solid fa-circle-info text-blue-400 mt-0.5 shrink-0"></i>
            <div>
                <p class="font-semibold mb-1">How Version Control Works</p>
                <ul class="space-y-1 text-blue-600">
                    <li>The <strong>Active</strong> version is the default view for all users upon login.</li>
                    <li>Users can switch between versions using the dropdown in the top navigation bar.</li>
                    <li>Creating a new version starts a clean slate — previous proposals remain untouched under their original version.</li>
                    <li>Versions with linked proposals cannot be deleted.</li>
                </ul>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
