<?php
/**
 * PHO Budgeting System — Factory Reset
 * Truncates the tbl_budget_proposals table.
 */
require_once __DIR__ . '/config.php';

initSession();

$success = false;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? '';
    if (!hash_equals($_SESSION['reset_token'] ?? '', $token)) {
        $error = 'Invalid or expired security token. Please go back and try again.';
    } else {
        try {
            $pdo = getConnection();
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $pdo->exec('TRUNCATE TABLE tbl_budget_proposals');
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            $success = true;
        } catch (PDOException $e) {
            error_log('Reset DB Error: ' . $e->getMessage());
            $error = 'A database error occurred. Please check your configuration.';
        }
    }
    unset($_SESSION['reset_token']);
} else {
    $_SESSION['reset_token'] = bin2hex(random_bytes(32));
}

$pageTitle  = 'Factory Reset';
$activeMenu = '';
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-lg mx-auto">
    <?php if ($success): ?>
    <div class="bg-white rounded-2xl shadow-lg border border-emerald-200 text-center py-12 px-6">
        <div class="mx-auto w-20 h-20 bg-emerald-50 rounded-full flex items-center justify-center mb-6">
            <i class="fa-solid fa-check text-3xl text-emerald-500"></i>
        </div>
        <h2 class="text-xl font-bold text-emerald-700 mb-2">Reset Complete</h2>
        <p class="text-sm text-gray-500 mb-6">All budget proposals have been deleted. The system is ready.</p>
        <a href="index.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition shadow-md">
            <i class="fa-solid fa-arrow-left text-xs"></i> Back to Dashboard
        </a>
    </div>

    <?php elseif ($error): ?>
    <div class="bg-white rounded-2xl shadow-lg border border-red-200 text-center py-12 px-6">
        <div class="mx-auto w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mb-6">
            <i class="fa-solid fa-xmark text-3xl text-red-500"></i>
        </div>
        <h2 class="text-xl font-bold text-red-600 mb-2">Reset Failed</h2>
        <p class="text-sm text-gray-500 mb-6"><?= e($error) ?></p>
        <a href="reset.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-red-500 text-white text-sm font-semibold hover:bg-red-600 transition shadow-md mr-2">
            <i class="fa-solid fa-rotate-left text-xs"></i> Try Again
        </a>
        <a href="index.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-600 hover:bg-gray-100 transition">Dashboard</a>
    </div>

    <?php else: ?>
    <div class="bg-white rounded-2xl shadow-lg border border-red-200 overflow-hidden">
        <div class="bg-red-500 text-white text-center py-4 px-6">
            <h2 class="text-lg font-bold"><i class="fa-solid fa-triangle-exclamation mr-2"></i>Factory Reset Database</h2>
        </div>
        <div class="text-center py-12 px-6">
            <div class="mx-auto w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mb-6">
                <i class="fa-solid fa-database text-3xl text-red-400"></i>
            </div>
            <p class="text-gray-700 font-semibold mb-1">Are you sure you want to delete <span class="text-red-600">ALL</span> budget proposals?</p>
            <p class="text-sm text-gray-400 mb-6">This action <strong>cannot be undone</strong>. Master data (account codes, programs, etc.) will be preserved.</p>
            <form method="POST" action="reset.php" class="space-y-3">
                <input type="hidden" name="_token" value="<?= e($_SESSION['reset_token']) ?>">
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg bg-red-500 text-white text-sm font-bold hover:bg-red-600 transition shadow-md">
                    <i class="fa-solid fa-trash"></i> Yes, Delete All Proposals
                </button>
                <a href="index.php" class="w-full inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-600 hover:bg-gray-100 transition">
                    <i class="fa-solid fa-arrow-left text-xs"></i> Cancel
                </a>
            </form>
        </div>
        <div class="bg-gray-50 text-center py-3 border-t">
            <small class="text-gray-400"><i class="fa-solid fa-shield-halved mr-1"></i> Protected by CSRF token</small>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
