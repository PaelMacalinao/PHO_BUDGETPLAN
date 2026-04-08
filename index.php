<?php
/**
 * PHO Budgeting System — Dashboard (High-Level Financial Overview)
 *
 * LEVEL 1: Fund Source tabs (General Fund | Special Project / Locally Funded)
 * LEVEL 2:
 *  - General Fund: aggregated totals by Expense Class (PS, MOOE, CO)
 *  - Special Project: grouped by Project (Unit), then by Expense Class
 */
require_once __DIR__ . '/config.php';
requireLogin();

if (!canViewAllData()) {
    header('Location: staff_dashboard.php');
    exit;
}

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';

$versionId = getSelectedVersionId();

try {
    $pdo = getConnection();

    $stmtOverall = $pdo->prepare("
        SELECT COUNT(id)                          AS total_proposals,
               COALESCE(SUM(total_allocation), 0) AS grand_total
        FROM   tbl_budget_proposals
        WHERE  version_id = :vid
    ");
    $stmtOverall->execute([':vid' => $versionId]);
    $overallRow = $stmtOverall->fetch();

    $stmtFund = $pdo->prepare("
        SELECT fs.fund_name,
               COUNT(bp.id)                          AS proposal_count,
               COALESCE(SUM(bp.total_allocation), 0) AS fund_total
        FROM   tbl_fund_sources fs
        LEFT JOIN tbl_budget_proposals bp ON bp.fund_source_id = fs.id AND bp.version_id = :vid
        GROUP BY fs.id, fs.fund_name
        ORDER BY fs.id
    ");
    $stmtFund->execute([':vid' => $versionId]);
    $fundTotals = $stmtFund->fetchAll();

    $stmtGf = $pdo->prepare("
        SELECT ac.expense_class,
               COUNT(bp.id)                          AS proposal_count,
               COALESCE(SUM(bp.total_allocation), 0) AS class_total
        FROM   tbl_budget_proposals bp
        JOIN   tbl_account_codes ac ON bp.account_id     = ac.id
        JOIN   tbl_fund_sources  fs ON bp.fund_source_id = fs.id
        WHERE  fs.fund_name = 'General Fund'
          AND  bp.version_id = :vid
        GROUP BY ac.expense_class
        ORDER BY FIELD(ac.expense_class, 'PS', 'MOOE', 'CO')
    ");
    $stmtGf->execute([':vid' => $versionId]);
    $gfByClass = $stmtGf->fetchAll();

    $stmtSp = $pdo->prepare("
        SELECT un.unit_name AS project_name,
               ac.expense_class,
               COUNT(bp.id)                          AS proposal_count,
               COALESCE(SUM(bp.total_allocation), 0) AS class_total
        FROM   tbl_budget_proposals bp
        JOIN   tbl_units         un ON bp.unit_id        = un.id
        JOIN   tbl_account_codes ac ON bp.account_id     = ac.id
        JOIN   tbl_fund_sources  fs ON bp.fund_source_id = fs.id
        WHERE  fs.fund_name = 'Special Project'
          AND  bp.version_id = :vid
        GROUP BY un.unit_name, ac.expense_class
        ORDER BY un.unit_name, FIELD(ac.expense_class, 'MOOE', 'CO', 'PS')
    ");
    $stmtSp->execute([':vid' => $versionId]);
    $spByProject = $stmtSp->fetchAll();

} catch (PDOException $e) {
    error_log('Dashboard DB Error: ' . $e->getMessage());
    $overallRow  = ['total_proposals' => 0, 'grand_total' => 0];
    $fundTotals  = [];
    $gfByClass   = [];
    $spByProject = [];
    $dbError = true;
}

$grandTotal     = (float)($overallRow['grand_total'] ?? 0);
$totalProposals = (int)($overallRow['total_proposals'] ?? 0);

$gfTotal = 0; $spTotal = 0; $gfCount = 0; $spCount = 0;
foreach ($fundTotals as $ft) {
    if (($ft['fund_name'] ?? '') === 'General Fund') {
        $gfTotal = (float)$ft['fund_total'];
        $gfCount = (int)$ft['proposal_count'];
    }
    if (($ft['fund_name'] ?? '') === 'Special Project') {
        $spTotal = (float)$ft['fund_total'];
        $spCount = (int)$ft['proposal_count'];
    }
}

$gfClasses = [
    'PS'   => ['total' => 0, 'count' => 0],
    'MOOE' => ['total' => 0, 'count' => 0],
    'CO'   => ['total' => 0, 'count' => 0],
];
foreach ($gfByClass as $r) {
    $cls = $r['expense_class'] ?? '';
    if (!isset($gfClasses[$cls])) continue;
    $gfClasses[$cls] = [
        'total' => (float)$r['class_total'],
        'count' => (int)$r['proposal_count'],
    ];
}

$spClasses = [
    'PS'   => ['total' => 0, 'count' => 0],
    'MOOE' => ['total' => 0, 'count' => 0],
    'CO'   => ['total' => 0, 'count' => 0],
];
foreach ($spByProject as $r) {
    $cls = (string)($r['expense_class'] ?? '');
    if (!isset($spClasses[$cls])) continue;
    $spClasses[$cls]['total'] += (float)($r['class_total'] ?? 0);
    $spClasses[$cls]['count'] += (int)($r['proposal_count'] ?? 0);
}

$classInfo = [
    'PS'   => ['label' => 'Personal Services',                     'icon' => 'fa-users',    'iconBg' => 'bg-purple-100', 'iconText' => 'text-purple-600', 'barBg' => 'bg-purple-500', 'border' => 'border-l-purple-500', 'text' => 'text-purple-700', 'bg' => 'bg-purple-50'],
    'MOOE' => ['label' => 'Maintenance & Other Operating Expenses', 'icon' => 'fa-wrench',   'iconBg' => 'bg-blue-100',   'iconText' => 'text-blue-600',   'barBg' => 'bg-blue-500',   'border' => 'border-l-blue-500',   'text' => 'text-blue-700',   'bg' => 'bg-blue-50'],
    'CO'   => ['label' => 'Capital Outlay',                         'icon' => 'fa-building', 'iconBg' => 'bg-orange-100', 'iconText' => 'text-orange-600', 'barBg' => 'bg-orange-500', 'border' => 'border-l-orange-500', 'text' => 'text-orange-700', 'bg' => 'bg-orange-50'],
];

require_once __DIR__ . '/includes/header.php';
?>

<style>
.dashboard-shell{width:min(100%,1400px);margin:0 auto;overflow-x:clip}
.dashboard-fund-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem}
.dashboard-class-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.25rem}
.fund-tab{position:relative;background:#fff;border:2px solid #e5e7eb;border-radius:1rem;padding:1.25rem 1.5rem;cursor:pointer;transition:all .3s ease;text-align:left;width:100%;outline:none}
.fund-tab:hover{box-shadow:0 4px 12px rgba(0,0,0,.06)}
.fund-tab.active::after{content:'';position:absolute;top:-2px;left:-2px;right:-2px;height:4px;border-radius:1rem 1rem 0 0}
.fund-tab-gf:hover{border-color:#86d19f}
.fund-tab-gf.active{border-color:#0b4d26;box-shadow:0 4px 20px rgba(11,77,38,.12);background:linear-gradient(135deg,#edf8f0 0%,#fff 100%)}
.fund-tab-gf.active::after{background:#0b4d26}
.fund-tab-sp:hover{border-color:#e4c462}
.fund-tab-sp.active{border-color:#c69214;box-shadow:0 4px 20px rgba(198,146,20,.16);background:linear-gradient(135deg,#fff7e1 0%,#fff 100%)}
.fund-tab-sp.active::after{background:#c69214}
.fund-view{animation:viewFadeIn .35s ease}
@keyframes viewFadeIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.exp-card{transition:transform .2s ease,box-shadow .2s ease}
.exp-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.08)}
.fund-title-gf{color:#0b4d26}
.fund-title-sp{color:#b7791f}
.fund-total-gf{background:#ecf8f0;border-color:#bfe3c7;color:#0b4d26}
.fund-total-sp{background:#fff7e3;border-color:#efd797;color:#a16207}
@media (max-width: 640px){
    .fund-tab{padding:1rem}
}
</style>

<div class="dashboard-shell">
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
    <div><p class="text-sm text-gray-500">High-Level Financial Overview — <?= e(getSelectedVersionName()) ?></p></div>
    <?php if (canSubmitProposals()): ?>
    <a href="create.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition shadow-md whitespace-nowrap">
        <i class="fa-solid fa-plus text-xs"></i> New Proposal
    </a>
    <?php endif; ?>
</div>

<?php if (isset($dbError)): ?>
<div class="bg-white rounded-2xl shadow-lg border border-gray-100 px-6 py-16 text-center">
    <div class="mx-auto w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mb-5"><i class="fa-solid fa-triangle-exclamation text-3xl text-red-300"></i></div>
    <h2 class="text-lg font-semibold text-gray-600 mb-2">Database Connection Error</h2>
    <p class="text-sm text-gray-400">Could not retrieve budget data. Please check your database configuration.</p>
</div>
<?php elseif ($totalProposals === 0): ?>
<div class="bg-white rounded-2xl shadow-lg border border-gray-100 px-6 py-16 text-center">
    <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-6"><i class="fa-solid fa-folder-open text-4xl text-gray-300"></i></div>
    <h2 class="text-xl font-semibold text-gray-600 mb-2">No Budget Proposals Yet</h2>
    <p class="text-sm text-gray-400 mb-6 max-w-md mx-auto"><?= canSubmitProposals() ? 'Create your first proposal to see the financial overview.' : 'No proposals have been submitted for this budget version yet.' ?></p>
    <?php if (canSubmitProposals()): ?>
    <a href="create.php" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition shadow-md">
        <i class="fa-solid fa-plus text-xs"></i> Create First Proposal
    </a>
    <?php endif; ?>
</div>
<?php else: ?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-5">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-xl flex items-center justify-center shrink-0" style="background:#d5f0dc;color:#0b4d26">
                <i class="fa-solid fa-chart-pie text-2xl"></i>
            </div>
            <div>
                <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider">Consolidated Budget Total</span>
                <span class="peso-amount text-3xl sm:text-4xl font-bold text-gray-800"><span class="peso-sign">₱</span> <?= number_format($grandTotal, 2) ?></span>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-6 sm:gap-8">
            <div class="text-center">
                <span class="block text-2xl font-bold text-gray-800"><?= number_format($totalProposals) ?></span>
                <span class="block text-xs text-gray-400">Total Proposals</span>
            </div>
            <div class="text-center">
                <span class="block text-2xl font-bold text-gray-800">2</span>
                <span class="block text-xs text-gray-400">Fund Sources</span>
            </div>
        </div>
    </div>
</div>

<div class="dashboard-fund-grid mb-8">
    <button type="button" id="tab-gf" class="fund-tab fund-tab-gf active" onclick="switchFund('gf')">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-landmark text-xl"></i>
            </div>
            <div class="min-w-0">
                <span class="block text-base font-bold text-gray-800">General Fund</span>
                <span class="peso-amount text-xl font-bold text-emerald-700"><span class="peso-sign">₱</span> <?= number_format($gfTotal, 2) ?></span>
                <span class="block text-xs text-gray-400 mt-0.5"><?= number_format($gfCount) ?> proposal<?= $gfCount !== 1 ? 's' : '' ?></span>
            </div>
        </div>
    </button>

    <button type="button" id="tab-sp" class="fund-tab fund-tab-sp" onclick="switchFund('sp')">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-diagram-project text-xl"></i>
            </div>
            <div class="min-w-0">
                <span class="block text-base font-bold text-gray-800">Special Project / Locally Funded</span>
                <span class="peso-amount text-xl font-bold text-amber-700"><span class="peso-sign">₱</span> <?= number_format($spTotal, 2) ?></span>
                <span class="block text-xs text-gray-400 mt-0.5"><?= number_format($spCount) ?> proposal<?= $spCount !== 1 ? 's' : '' ?></span>
            </div>
        </div>
    </button>
</div>

<div id="view-gf" class="fund-view">
    <h3 class="text-sm font-bold uppercase tracking-wider mb-4 fund-title-gf">
        <i class="fa-solid fa-landmark mr-1.5 text-emerald-500"></i> General Fund — Expense Class Breakdown
    </h3>

    <div class="dashboard-class-grid">
        <?php foreach (['PS', 'MOOE', 'CO'] as $cls):
            $ci    = $classInfo[$cls];
            $total = $gfClasses[$cls]['total'];
            $count = $gfClasses[$cls]['count'];
        ?>
        <a href="admin_dashboard.php?expense=<?= urlencode($cls) ?>&fund=<?= urlencode('General Fund') ?>" class="exp-card bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden block group">
            <div class="border-l-4 <?= $ci['border'] ?> p-5 sm:p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="<?= $ci['iconBg'] ?> <?= $ci['iconText'] ?> w-11 h-11 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fa-solid <?= $ci['icon'] ?> text-lg"></i>
                    </div>
                    <div>
                        <span class="block text-sm font-bold text-gray-800"><?= $cls ?></span>
                        <span class="block text-[11px] text-gray-400 leading-tight"><?= e($ci['label']) ?></span>
                    </div>
                </div>
                <div class="mb-4">
                    <span class="peso-amount text-2xl sm:text-3xl font-bold <?= $ci['text'] ?>"><span class="peso-sign">₱</span> <?= number_format($total, 2) ?></span>
                    <span class="block text-xs text-gray-400 mt-1"><?= number_format($count) ?> proposal<?= $count !== 1 ? 's' : '' ?></span>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between">
                    <span class="text-xs text-gray-400 group-hover:text-brand-600 transition">View in Budget Overview</span>
                    <i class="fa-solid fa-arrow-right text-xs text-gray-300 group-hover:text-brand-600 group-hover:translate-x-1 transition-all"></i>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    
</div>

<div id="view-sp" class="fund-view" style="display:none">
    <h3 class="text-sm font-bold uppercase tracking-wider mb-4 fund-title-sp">
        <i class="fa-solid fa-diagram-project mr-1.5 text-amber-500"></i> Special Project / Locally Funded - Expense Class Breakdown
    </h3>

    <?php if ($spCount === 0): ?>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-10 text-center">
            <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4"><i class="fa-solid fa-inbox text-2xl text-gray-300"></i></div>
            <p class="text-sm text-gray-400">No Special Project proposals found.</p>
        </div>
    <?php else: ?>
        <div class="dashboard-class-grid">
            <?php foreach (['PS', 'MOOE', 'CO'] as $cls):
                $ci    = $classInfo[$cls];
                $total = $spClasses[$cls]['total'];
                $count = $spClasses[$cls]['count'];
            ?>
            <a href="admin_dashboard.php?expense=<?= urlencode($cls) ?>&fund=<?= urlencode('Special Project') ?>" class="exp-card bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden block group">
                <div class="border-l-4 <?= $ci['border'] ?> p-5 sm:p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="<?= $ci['iconBg'] ?> <?= $ci['iconText'] ?> w-11 h-11 rounded-lg flex items-center justify-center shrink-0">
                            <i class="fa-solid <?= $ci['icon'] ?> text-lg"></i>
                        </div>
                        <div>
                            <span class="block text-sm font-bold text-gray-800"><?= $cls ?></span>
                            <span class="block text-[11px] text-gray-400 leading-tight"><?= e($ci['label']) ?></span>
                        </div>
                    </div>
                    <div class="mb-4">
                        <span class="peso-amount text-2xl sm:text-3xl font-bold <?= $ci['text'] ?>"><span class="peso-sign">&#8369;</span> <?= number_format($total, 2) ?></span>
                        <span class="block text-xs text-gray-400 mt-1"><?= number_format($count) ?> proposal<?= $count !== 1 ? 's' : '' ?></span>
                    </div>
                    <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between">
                        <span class="text-xs text-gray-400 group-hover:text-brand-600 transition">View in Budget Overview</span>
                        <i class="fa-solid fa-arrow-right text-xs text-gray-300 group-hover:text-brand-600 group-hover:translate-x-1 transition-all"></i>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        
    <?php endif; ?>
</div>

<?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
(function() {
    'use strict';

    var activeFund = 'gf';
    var views = { gf: document.getElementById('view-gf'), sp: document.getElementById('view-sp') };
    var tabs  = { gf: document.getElementById('tab-gf'),  sp: document.getElementById('tab-sp') };
    if (!views.gf || !views.sp || !tabs.gf || !tabs.sp) return;

    window.switchFund = function(fund) {
        if (fund === activeFund) return;
        activeFund = fund;

        Object.keys(tabs).forEach(function(k) {
            tabs[k].classList.toggle('active', k === fund);
        });

        Object.keys(views).forEach(function(k) {
            var el = views[k];
            if (k === fund) {
                el.style.display = '';
                el.style.opacity = '0';
                el.style.transform = 'translateY(12px)';
                el.offsetHeight;
                el.style.transition = 'opacity .35s ease, transform .35s ease';
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            } else {
                el.style.display = 'none';
                el.style.transition = '';
                el.style.opacity = '';
                el.style.transform = '';
            }
        });
    };
})();
</script>

