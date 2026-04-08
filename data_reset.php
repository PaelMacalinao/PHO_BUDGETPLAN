<?php
require_once __DIR__ . '/config.php';
requireAdmin();

$pdo = getConnection();
$message = null;
$messageType = 'success';

if (empty($_SESSION['data_reset_token'])) {
    $_SESSION['data_reset_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['_token'] ?? '');
    $action = (string)($_POST['reset_action'] ?? '');

    if (!hash_equals($_SESSION['data_reset_token'] ?? '', $token)) {
        $message = 'Invalid or expired security token. Please refresh and try again.';
        $messageType = 'error';
    } else {
        try {
            if ($action === 'clear_all_proposals') {
                $stmt = $pdo->prepare("DELETE FROM tbl_budget_proposals");
                $stmt->execute();
                $deleted = $stmt->rowCount();
                $message = "Cleared {$deleted} proposal record(s). User accounts were not affected.";
            } elseif (in_array($action, ['clear_general_fund', 'clear_special_project'], true)) {
                $fundName = $action === 'clear_general_fund' ? 'General Fund' : 'Special Project';
                $stmt = $pdo->prepare("
                    DELETE bp
                    FROM tbl_budget_proposals bp
                    INNER JOIN tbl_fund_sources fs ON fs.id = bp.fund_source_id
                    WHERE fs.fund_name = :fund_name
                ");
                $stmt->execute([':fund_name' => $fundName]);
                $deleted = $stmt->rowCount();
                $message = "Cleared {$deleted} proposal record(s) under {$fundName}.";
            } else {
                throw new RuntimeException('Unknown reset action.');
            }

            $_SESSION['data_reset_token'] = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            error_log('Data reset error: ' . $e->getMessage());
            $message = 'A database error occurred while clearing the data.';
            $messageType = 'error';
        }
    }
}

$summaryStmt = $pdo->query("
    SELECT
        COUNT(*) AS total_proposals,
        COALESCE(SUM(CASE WHEN fs.fund_name = 'General Fund' THEN 1 ELSE 0 END), 0) AS general_fund_count,
        COALESCE(SUM(CASE WHEN fs.fund_name = 'Special Project' THEN 1 ELSE 0 END), 0) AS special_project_count
    FROM tbl_budget_proposals bp
    LEFT JOIN tbl_fund_sources fs ON fs.id = bp.fund_source_id
");
$summary = $summaryStmt->fetch() ?: [
    'total_proposals' => 0,
    'general_fund_count' => 0,
    'special_project_count' => 0,
];

$pageTitle = 'Data Reset';
$activeMenu = 'data_reset';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-red-500">Admin Reset Tools</p>
                <h1 class="text-2xl font-bold text-gray-900 mt-2">Clear Budget Data Safely</h1>
                <p class="text-sm text-gray-500 mt-2 max-w-2xl">
                    This tool clears proposal data only. User accounts, roles, and User Management records stay untouched.
                </p>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 max-w-md">
                <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                These actions are permanent. Only use them when you really want to reset submitted budget records.
            </div>
        </div>
    </div>

    <?php if ($message !== null): ?>
    <div class="rounded-2xl border px-5 py-4 text-sm <?= $messageType === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' ?>">
        <i class="fa-solid <?= $messageType === 'error' ? 'fa-circle-xmark' : 'fa-circle-check' ?> mr-2"></i><?= e($message) ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400">All Proposals</p>
            <p class="text-3xl font-bold text-gray-900 mt-2"><?= number_format((int)$summary['total_proposals']) ?></p>
            <p class="text-sm text-gray-500 mt-1">Current submitted records in the system.</p>
        </div>
        <div class="bg-white rounded-2xl border border-emerald-100 shadow-sm p-5">
            <p class="text-xs font-bold uppercase tracking-wider text-emerald-500">General Fund</p>
            <p class="text-3xl font-bold text-emerald-700 mt-2"><?= number_format((int)$summary['general_fund_count']) ?></p>
            <p class="text-sm text-gray-500 mt-1">Proposal records under General Fund.</p>
        </div>
        <div class="bg-white rounded-2xl border border-amber-100 shadow-sm p-5">
            <p class="text-xs font-bold uppercase tracking-wider text-amber-500">Special Project</p>
            <p class="text-3xl font-bold text-amber-700 mt-2"><?= number_format((int)$summary['special_project_count']) ?></p>
            <p class="text-sm text-gray-500 mt-1">Proposal records under Special Project.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <form method="post" class="bg-white rounded-2xl border border-red-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-red-100 bg-red-50">
                <h2 class="text-lg font-bold text-red-700">Reset All Proposal Data</h2>
                <p class="text-sm text-red-600 mt-1">Deletes all submitted proposals from all fund sources and budget versions.</p>
            </div>
            <div class="p-5 space-y-4">
                <p class="text-sm text-gray-600">User accounts, roles, account codes, units, fund sources, and budget versions will remain.</p>
                <ul class="text-sm text-gray-500 space-y-2">
                    <li><i class="fa-solid fa-check mr-2 text-emerald-500"></i>User Management stays intact</li>
                    <li><i class="fa-solid fa-check mr-2 text-emerald-500"></i>Fund source master data stays intact</li>
                    <li><i class="fa-solid fa-xmark mr-2 text-red-500"></i>All proposal entries will be removed</li>
                </ul>
                <input type="hidden" name="_token" value="<?= e($_SESSION['data_reset_token']) ?>">
                <input type="hidden" name="reset_action" value="clear_all_proposals">
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-3 text-sm font-semibold text-white hover:bg-red-700 transition"
                        onclick="return confirm('Delete ALL proposal data? User accounts will remain.')">
                    <i class="fa-solid fa-trash-can"></i> Clear All Proposal Data
                </button>
            </div>
        </form>

        <form method="post" class="bg-white rounded-2xl border border-emerald-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-emerald-100 bg-emerald-50">
                <h2 class="text-lg font-bold text-emerald-700">Delete General Fund Data</h2>
                <p class="text-sm text-emerald-600 mt-1">Clears only proposal records assigned to General Fund.</p>
            </div>
            <div class="p-5 space-y-4">
                <p class="text-sm text-gray-600">General Fund proposal data will be removed, while Special Project and user accounts stay untouched.</p>
                <div class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3">
                    <span class="block text-xs uppercase tracking-wider text-gray-400">Current Records</span>
                    <span class="block text-2xl font-bold text-emerald-700 mt-1"><?= number_format((int)$summary['general_fund_count']) ?></span>
                </div>
                <input type="hidden" name="_token" value="<?= e($_SESSION['data_reset_token']) ?>">
                <input type="hidden" name="reset_action" value="clear_general_fund">
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 transition"
                        onclick="return confirm('Delete all General Fund proposal data?')">
                    <i class="fa-solid fa-landmark"></i> Delete General Fund
                </button>
            </div>
        </form>

        <form method="post" class="bg-white rounded-2xl border border-amber-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-amber-100 bg-amber-50">
                <h2 class="text-lg font-bold text-amber-700">Delete Special Project Data</h2>
                <p class="text-sm text-amber-600 mt-1">Clears only proposal records assigned to Special Project.</p>
            </div>
            <div class="p-5 space-y-4">
                <p class="text-sm text-gray-600">Special Project proposal data will be removed, while General Fund and user accounts stay untouched.</p>
                <div class="rounded-xl bg-gray-50 border border-gray-100 px-4 py-3">
                    <span class="block text-xs uppercase tracking-wider text-gray-400">Current Records</span>
                    <span class="block text-2xl font-bold text-amber-700 mt-1"><?= number_format((int)$summary['special_project_count']) ?></span>
                </div>
                <input type="hidden" name="_token" value="<?= e($_SESSION['data_reset_token']) ?>">
                <input type="hidden" name="reset_action" value="clear_special_project">
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-amber-600 px-4 py-3 text-sm font-semibold text-white hover:bg-amber-700 transition"
                        onclick="return confirm('Delete all Special Project proposal data?')">
                    <i class="fa-solid fa-diagram-project"></i> Delete Special Project
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
