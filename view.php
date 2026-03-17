<?php
/**
 * PHO Budgeting System — View Budget Proposal (read-only)
 */
require_once __DIR__ . '/config.php';

$id  = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$row = null;

if ($id) {
    try {
        $pdo  = getConnection();
        $stmt = $pdo->prepare("
            SELECT bp.*, pu.program_name, ac.account_code, ac.account_title, ac.expense_class,
                   fs.fund_name, ind.indicator_description, un.unit_name
            FROM   tbl_budget_proposals bp
            JOIN   tbl_programs_units pu  ON bp.program_id     = pu.id
            JOIN   tbl_account_codes ac   ON bp.account_id     = ac.id
            JOIN   tbl_fund_sources  fs   ON bp.fund_source_id = fs.id
            JOIN   tbl_indicators    ind  ON bp.indicator_id   = ind.id
            JOIN   tbl_units         un   ON bp.unit_id        = un.id
            WHERE  bp.id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('DB Error: ' . $e->getMessage());
    }
}

$pageTitle  = $row ? 'Proposal #' . (int)$row['id'] : 'Not Found';
$activeMenu = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<?php if (!$row): ?>
<div class="bg-white rounded-2xl shadow-lg border border-gray-100 px-6 py-20 text-center">
    <div class="mx-auto w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mb-6">
        <i class="fa-solid fa-triangle-exclamation text-3xl text-red-300"></i>
    </div>
    <h2 class="text-xl font-semibold text-gray-700 mb-2">Proposal Not Found</h2>
    <p class="text-sm text-gray-400 mb-6">The record does not exist or the ID is invalid.</p>
    <a href="index.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition shadow-md">
        <i class="fa-solid fa-arrow-left text-xs"></i> Return to Dashboard
    </a>
</div>
<?php else: ?>

<!-- Page heading -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-bold text-gray-800">Proposal <span class="text-brand-600">#<?= (int)$row['id'] ?></span></h2>
        <p class="text-sm text-gray-400 mt-0.5">Submitted <?= date('F j, Y \a\t g:i A', strtotime($row['created_at'])) ?></p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <span class="inline-flex items-center gap-1.5 bg-brand-50 text-brand-700 text-xs font-medium px-3 py-1.5 rounded-full"><i class="fa-solid fa-sitemap"></i> <?= e($row['program_name']) ?></span>
        <span class="inline-flex items-center gap-1.5 bg-violet-50 text-violet-700 text-xs font-medium px-3 py-1.5 rounded-full"><i class="fa-solid fa-building"></i> <?= e($row['unit_name']) ?></span>
        <span class="inline-flex items-center gap-1.5 bg-amber-50 text-amber-700 text-xs font-medium px-3 py-1.5 rounded-full"><i class="fa-solid fa-tags"></i> <?= e($row['expense_class']) ?></span>
        <span class="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 text-xs font-medium px-3 py-1.5 rounded-full"><i class="fa-solid fa-coins"></i> <?= e($row['fund_name']) ?></span>
    </div>
</div>

<!-- Section 1: Program Details -->
<div class="bg-white rounded-2xl shadow-md border border-gray-100 mb-6 overflow-hidden">
    <div class="bg-brand-50 px-6 py-4 border-b border-brand-100 flex items-center gap-3">
        <div class="bg-brand-600 text-white w-8 h-8 rounded-lg flex items-center justify-center text-xs"><i class="fa-solid fa-folder-open"></i></div>
        <h3 class="text-base font-semibold text-brand-800">Program &amp; Account Details</h3>
    </div>
    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-y-5 gap-x-8">
        <div class="md:col-span-2">
            <span class="block text-xs font-medium text-gray-400 mb-1">PPA Description</span>
            <span class="block text-sm font-semibold text-gray-800 whitespace-pre-line"><?= e($row['ppa_description']) ?></span>
        </div>
        <div>
            <span class="block text-xs font-medium text-gray-400 mb-1">Account Code</span>
            <span class="block text-sm font-semibold text-gray-800"><?= e($row['account_code']) ?></span>
        </div>
        <div>
            <span class="block text-xs font-medium text-gray-400 mb-1">Account Title</span>
            <span class="block text-sm font-semibold text-gray-800"><?= e($row['account_title']) ?></span>
        </div>
        <div class="md:col-span-2">
            <span class="block text-xs font-medium text-gray-400 mb-1">Performance Indicator</span>
            <span class="block text-sm text-gray-700 leading-relaxed"><?= e($row['indicator_description']) ?></span>
        </div>
    </div>
</div>

<!-- Section 2: Quarterly Targets -->
<div class="bg-white rounded-2xl shadow-md border border-gray-100 mb-6 overflow-hidden">
    <div class="bg-emerald-50 px-6 py-4 border-b border-emerald-100 flex items-center gap-3">
        <div class="bg-emerald-600 text-white w-8 h-8 rounded-lg flex items-center justify-center text-xs"><i class="fa-solid fa-bullseye"></i></div>
        <h3 class="text-base font-semibold text-emerald-800">Quarterly Physical Targets</h3>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
            <?php foreach (['q1_target'=>'Q1','q2_target'=>'Q2','q3_target'=>'Q3','q4_target'=>'Q4'] as $col => $lbl): ?>
            <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 text-center">
                <span class="block text-xs font-medium text-gray-400 mb-1"><?= $lbl ?> Target</span>
                <span class="block text-2xl font-bold text-gray-800"><?= number_format((int)$row[$col]) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-5 flex items-center gap-3">
            <div class="bg-emerald-500 text-white w-10 h-10 rounded-full flex items-center justify-center"><i class="fa-solid fa-calculator"></i></div>
            <div>
                <span class="block text-xs text-emerald-600 font-medium">Annual Physical Target</span>
                <span class="block text-2xl font-bold text-emerald-800"><?= number_format((int)$row['target_total']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Section 3: Monthly Allocation -->
<div class="bg-white rounded-2xl shadow-md border border-gray-100 mb-6 overflow-hidden">
    <div class="bg-amber-50 px-6 py-4 border-b border-amber-100 flex items-center gap-3">
        <div class="bg-amber-500 text-white w-8 h-8 rounded-lg flex items-center justify-center text-xs"><i class="fa-solid fa-coins"></i></div>
        <h3 class="text-base font-semibold text-amber-800">Monthly Financial Allocation</h3>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mb-5">
            <?php
            $months = [
                'jan_amt'=>'January','feb_amt'=>'February','mar_amt'=>'March',
                'apr_amt'=>'April','may_amt'=>'May','jun_amt'=>'June',
                'jul_amt'=>'July','aug_amt'=>'August','sep_amt'=>'September',
                'oct_amt'=>'October','nov_amt'=>'November','dec_amt'=>'December',
            ];
            foreach ($months as $col => $lbl): ?>
            <div class="bg-gray-50 rounded-xl border border-gray-200 p-4">
                <span class="block text-xs font-medium text-gray-400 mb-1"><?= $lbl ?></span>
                <span class="block text-sm font-bold text-gray-800"><?= peso((float)$row[$col]) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 flex items-center gap-3">
            <div class="bg-amber-500 text-white w-10 h-10 rounded-full flex items-center justify-center"><i class="fa-solid fa-peso-sign"></i></div>
            <div>
                <span class="block text-xs text-amber-600 font-medium">Total Annual Allocation</span>
                <span class="block text-2xl font-bold text-amber-800"><?= peso((float)$row['total_allocation']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Section 4: Justification -->
<div class="bg-white rounded-2xl shadow-md border border-gray-100 mb-6 overflow-hidden">
    <div class="bg-violet-50 px-6 py-4 border-b border-violet-100 flex items-center gap-3">
        <div class="bg-violet-600 text-white w-8 h-8 rounded-lg flex items-center justify-center text-xs"><i class="fa-solid fa-file-pen"></i></div>
        <h3 class="text-base font-semibold text-violet-800">Justification</h3>
    </div>
    <div class="p-6">
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-sm text-gray-700 leading-relaxed whitespace-pre-line"><?= e($row['justification']) ?></div>
    </div>
</div>

<!-- Bottom nav -->
<div class="flex items-center justify-between flex-wrap gap-3">
    <a href="index.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-600 hover:bg-gray-100 transition shadow-sm">
        <i class="fa-solid fa-arrow-left text-xs"></i> Back to Dashboard
    </a>
    <div class="flex items-center gap-2">
        <a href="edit.php?id=<?= (int)$row['id'] ?>" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-amber-500 text-white text-sm font-semibold hover:bg-amber-600 transition shadow-md">
            <i class="fa-solid fa-pen-to-square text-xs"></i> Edit Proposal
        </a>
        <span class="text-xs text-gray-400">Updated: <?= date('M j, Y g:i A', strtotime($row['updated_at'])) ?></span>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
