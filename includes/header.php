<?php
/**
 * Shared header: HTML head, sidebar, top bar.
 * Set $pageTitle, $activeMenu before including this file.
 */
$pageTitle  = $pageTitle  ?? 'Dashboard';
$activeMenu = $activeMenu ?? 'dashboard';

initSession();
$currentRole = getUserRole();
$currentName = currentFullname();
$isLoggedIn  = !empty($_SESSION['user_id']);
$hiddenHeaderNames = ['System Administrator', 'Staff User'];
$headerDisplayName = in_array(trim($currentName), $hiddenHeaderNames, true) ? '' : $currentName;

$headerRoleLabel = 'STAFF';
if ($currentRole === 'admin') {
    $headerRoleLabel = 'ADMIN';
} elseif ($currentRole === 'viewer') {
    $headerRoleLabel = 'VIEWER';
}
$headerUserTitle = $headerDisplayName !== ''
    ? $headerDisplayName . ' | ' . $headerRoleLabel
    : $headerRoleLabel;

$_allVersions     = $isLoggedIn ? getAllVersions() : [];
$_selectedVerId   = $isLoggedIn ? getSelectedVersionId() : 0;
$_selectedVerName = '';
foreach ($_allVersions as $_v) {
    if ((int)$_v['id'] === $_selectedVerId) {
        $_selectedVerName = $_v['year_name'];
        break;
    }
}

$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if (basename($base) === 'master') {
    $base = dirname($base);
}
$base = rtrim($base, '/\\');
$homeHref = $base . (canViewAllData() ? '/index.php' : '/staff_dashboard.php');
$headerLogoSrc = '/PHO_HFDP_CONSO/assets/images/pho-logo.png';

$menuItems = [
    ['section' => 'MAIN'],
    [
        'key' => 'dashboard',
        'label' => canViewAllData() ? 'Dashboard' : 'My Submissions',
        'icon' => 'fa-chart-pie',
        'href' => $base . (canViewAllData() ? '/index.php' : '/staff_dashboard.php')
    ],
];

if (canViewAllData()) {
    $menuItems[] = [
        'key' => 'admin_dashboard',
        'label' => 'Budget Overview',
        'icon' => 'fa-layer-group',
        'href' => $base . '/admin_dashboard.php'
    ];
}

if (canSubmitProposals()) {
    $menuItems[] = ['section' => 'PROPOSALS'];
    $menuItems[] = [
        'key' => 'create',
        'label' => 'New Proposal',
        'icon' => 'fa-plus-circle',
        'href' => $base . '/create.php'
    ];
}

if ($currentRole === 'admin') {
    $menuItems = array_merge($menuItems, [
        ['section' => 'MASTER DATA'],
        ['key' => 'account_codes', 'label' => 'Account Codes', 'icon' => 'fa-barcode', 'href' => $base . '/master/account_codes.php'],
        ['key' => 'units', 'label' => 'Units', 'icon' => 'fa-building', 'href' => $base . '/master/units.php'],
        ['key' => 'fund_sources', 'label' => 'Fund Sources', 'icon' => 'fa-wallet', 'href' => $base . '/master/fund_sources.php'],
        ['section' => 'SETTINGS'],
        ['key' => 'user_management', 'label' => 'User Management', 'icon' => 'fa-users-gear', 'href' => $base . '/user_management.php'],
        ['key' => 'manage_versions', 'label' => 'Budget Versions', 'icon' => 'fa-calendar-days', 'href' => $base . '/manage_versions.php'],
        ['key' => 'data_reset', 'label' => 'Data Reset', 'icon' => 'fa-trash-can', 'href' => $base . '/data_reset.php'],
    ]);
}

$menuItems[] = ['section' => 'ACCOUNT'];
$menuItems[] = [
    'key' => $isLoggedIn ? 'logout' : 'login',
    'label' => $isLoggedIn ? 'Logout' : 'Login',
    'icon' => $isLoggedIn ? 'fa-right-from-bracket' : 'fa-right-to-bracket',
    'href' => $base . ($isLoggedIn ? '/logout.php' : '/login.php')
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= e($base) ?>">
    <title><?= e($pageTitle) ?> - <?= APP_NAME ?></title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css" />
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/tailwind.output.css" />
    <link rel="stylesheet" href="<?= e($base) ?>/style.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-50 min-h-screen font-sans text-gray-700">

<div id="sidebarOverlay"></div>

<aside id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-white border-r border-gray-200 z-50 -translate-x-full flex flex-col">
    <div class="h-14 flex items-center justify-between px-4 border-b border-gray-100 shrink-0">
        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Navigation</span>
        <button id="sidebarCloseBtn" class="w-8 h-8 flex items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition" aria-label="Close sidebar">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
    </div>

    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        <?php foreach ($menuItems as $item): ?>
            <?php if (isset($item['section'])): ?>
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider px-3 pt-4 pb-1"><?= $item['section'] ?></p>
            <?php else: ?>
                <a href="<?= $item['href'] ?>"
                   class="sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-gray-600 <?= $activeMenu === $item['key'] ? 'active' : '' ?>">
                    <i class="fa-solid <?= $item['icon'] ?> w-5 text-center text-gray-400"></i>
                    <?= $item['label'] ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="p-4 border-t border-gray-100 text-[10px] text-gray-400 text-center shrink-0">
        &copy; <?= date('Y') ?> <?= APP_ORG ?>
    </div>
</aside>

<div id="mainWrapper" class="min-h-screen flex flex-col">
    <header class="sticky top-0 z-30 shadow-md h-16 flex items-center justify-between px-4 sm:px-6 shrink-0" style="background:#0b4d26">
        <div class="flex items-center gap-3">
            <button id="hamburgerBtn" class="hamburger" aria-label="Toggle navigation" aria-expanded="false">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </button>

            <a href="<?= e($homeHref) ?>" class="flex items-center gap-2.5 rounded-lg px-1 py-1 transition hover:bg-white/10" title="Go to dashboard home">
                <img src="<?= e($headerLogoSrc) ?>" alt="PHO Logo" class="w-10 h-10 rounded-full object-cover bg-white border border-white/20 shadow-sm">
                <div class="leading-tight">
                    <span class="block text-sm font-bold text-white tracking-tight">PHO Budgeting</span>
                    <span class="block text-[10px] text-green-200"><?= e($_selectedVerName ?: 'FY 2026') ?></span>
                </div>
            </a>

            <div class="hidden sm:flex items-center gap-3">
                <span class="text-green-300/50">|</span>
                <h1 class="text-base font-semibold text-white/90 tracking-tight"><?= e($pageTitle) ?></h1>
            </div>
        </div>

        <div class="flex items-center gap-2 sm:gap-3 text-sm">
            <?php if ($isLoggedIn && count($_allVersions) > 0): ?>
            <label id="budgetVersionWrap" class="budget-version-wrap hidden sm:flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-2.5 py-1.5 backdrop-blur-sm shadow-sm">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-white/12 text-white">
                    <i class="fa-solid fa-calendar-days text-xs"></i>
                </span>
                <span class="leading-tight min-w-[118px]">
                    <span class="block text-[9px] uppercase tracking-[0.14em] text-green-100/80">Budget Version</span>
                    <select id="globalVersionSelect" class="budget-version-select min-w-[125px] bg-transparent text-xs font-semibold text-white cursor-pointer focus:outline-none appearance-none" title="Switch Budget Year">
                        <?php foreach ($_allVersions as $_v): ?>
                        <option value="<?= (int)$_v['id'] ?>" <?= (int)$_v['id'] === $_selectedVerId ? 'selected' : '' ?> style="color:#1f2937;background:#fff">
                            <?= e($_v['year_name']) ?><?= $_v['is_active'] ? ' *' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </span>
                <i class="fa-solid fa-chevron-down text-[10px] text-white/70 budget-version-chevron"></i>
            </label>
            <select id="globalVersionSelectMobile" class="budget-version-select-mobile sm:hidden bg-white/15 text-white text-xs font-medium rounded-lg px-3 py-1.5 border border-white/20 backdrop-blur-sm cursor-pointer focus:outline-none focus:ring-2 focus:ring-white/30 transition" title="Switch Budget Year">
                <?php foreach ($_allVersions as $_v): ?>
                <option value="<?= (int)$_v['id'] ?>" <?= (int)$_v['id'] === $_selectedVerId ? 'selected' : '' ?> style="color:#333;background:#fff">
                    <?= e($_v['year_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <div id="headerDateTime" class="header-datetime hidden lg:flex items-center gap-3 rounded-xl border border-white/15 bg-white/10 px-3 py-2 text-white/90 backdrop-blur-sm shadow-sm">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white/12 text-white">
                    <i class="fa-solid fa-clock text-xs"></i>
                </span>
                <span class="leading-tight">
                    <span id="headerDateLabel" class="block text-[10px] uppercase tracking-[0.16em] text-green-100/80">Loading Date</span>
                    <span id="headerTimeLabel" class="block text-sm font-semibold text-white">--:--:--</span>
                </span>
            </div>

            <?php if ($isLoggedIn): ?>
            <span class="max-w-[min(58vw,16rem)] shrink truncate text-right text-[10px] tracking-wide text-white sm:max-w-none sm:overflow-visible sm:whitespace-normal sm:text-xs"
                  title="<?= e($headerUserTitle) ?>">
                <i class="fa-solid fa-user mr-1 text-[9px] text-white/85"></i>
                <?php if ($headerDisplayName !== ''): ?>
                    <span class="font-normal uppercase"><?= e($headerDisplayName) ?></span>
                    <span class="mx-1 text-white/70">|</span>
                <?php endif; ?>
                <span class="font-bold uppercase"><?= e($headerRoleLabel) ?></span>
            </span>
            <?php endif; ?>
        </div>
    </header>

    <main class="flex-1 p-4 sm:p-6 lg:p-8">
