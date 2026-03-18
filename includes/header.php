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

$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if (basename($base) === 'master') $base = dirname($base);
$base = rtrim($base, '/\\');

$menuItems = [
    ['section' => 'MAIN'],
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-chart-pie',
     'href' => $base . ($currentRole === 'admin' ? '/index.php' : '/staff_dashboard.php')],
];

if ($currentRole === 'admin') {
    $menuItems[] = ['key' => 'admin_dashboard', 'label' => 'Budget Overview', 'icon' => 'fa-layer-group', 'href' => $base . '/admin_dashboard.php'];
}

$menuItems[] = ['section' => 'PROPOSALS'];
$menuItems[] = ['key' => 'create', 'label' => 'New Proposal', 'icon' => 'fa-plus-circle', 'href' => $base . '/create.php'];

if ($currentRole === 'admin') {
    $menuItems = array_merge($menuItems, [
        ['section' => 'MASTER DATA'],
        ['key' => 'account_codes', 'label' => 'Account Codes', 'icon' => 'fa-barcode',    'href' => $base . '/master/account_codes.php'],
        ['key' => 'programs',      'label' => 'Programs (PPA)', 'icon' => 'fa-sitemap',    'href' => $base . '/master/programs.php'],
        ['key' => 'units',         'label' => 'Units',          'icon' => 'fa-building',   'href' => $base . '/master/units.php'],
        ['key' => 'fund_sources',  'label' => 'Fund Sources',   'icon' => 'fa-wallet',     'href' => $base . '/master/fund_sources.php'],
        ['key' => 'indicators',    'label' => 'Indicators',     'icon' => 'fa-gauge-high', 'href' => $base . '/master/indicators.php'],
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= e($base) ?>">
    <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {50:'#f0faf3',100:'#d5f0dc',200:'#a8e0b9',300:'#6ec98a',400:'#3aad5e',500:'#1a7a3a',600:'#0b4d26',700:'#093f1f',800:'#073218',900:'#052611'},
                        accent:{50:'#fef9e7',100:'#fdf0c4',200:'#fbe28a',300:'#f9d24f',400:'#f9ba15',500:'#e5a80e',600:'#bf8b09',700:'#8c6607',800:'#5f4504',900:'#332503'},
                    }
                }
            }
        }
    </script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css" />
    <link rel="stylesheet" href="<?= e($base) ?>/style.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="bg-gray-50 min-h-screen font-sans text-gray-700">

<!-- ═══ SIDEBAR OVERLAY (translucent click-catcher) ═══ -->
<div id="sidebarOverlay"></div>

<!-- ═══ SIDEBAR ═══ -->
<aside id="sidebar" class="fixed top-0 left-0 h-full w-64 bg-white border-r border-gray-200 z-50 -translate-x-full flex flex-col">
    <!-- Sidebar Header -->
    <div class="h-14 flex items-center justify-between px-4 border-b border-gray-100 shrink-0">
        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Navigation</span>
        <button id="sidebarCloseBtn" class="w-8 h-8 flex items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition" aria-label="Close sidebar">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
    </div>

    <!-- Menu -->
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

    <!-- Sidebar Footer -->
    <div class="p-4 border-t border-gray-100 text-[10px] text-gray-400 text-center shrink-0">
        &copy; <?= date('Y') ?> <?= APP_ORG ?>
    </div>
</aside>

<!-- ═══ MAIN WRAPPER ═══ -->
<div id="mainWrapper" class="min-h-screen flex flex-col">

    <!-- Top bar -->
    <header class="sticky top-0 z-30 shadow-md h-16 flex items-center justify-between px-4 sm:px-6 shrink-0" style="background:#0b4d26">
        <div class="flex items-center gap-3">
            <!-- Hamburger toggle -->
            <button id="hamburgerBtn" class="hamburger" aria-label="Toggle navigation" aria-expanded="false">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </button>
            <!-- App branding (always visible) -->
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-sm font-bold shadow-sm" style="background:#f9ba15;color:#0b4d26">
                    <i class="fa-solid fa-building-columns"></i>
                </div>
                <div class="leading-tight">
                    <span class="block text-sm font-bold text-white tracking-tight">PHO Budgeting</span>
                    <span class="block text-[10px] text-green-200">FY 2026</span>
                </div>
            </div>
            <!-- Page title separator (hidden on small screens) -->
            <div class="hidden sm:flex items-center gap-3">
                <span class="text-green-300/50">|</span>
                <h1 class="text-base font-semibold text-white/90 tracking-tight"><?= e($pageTitle) ?></h1>
            </div>
        </div>
        <div class="flex items-center gap-3 text-sm">
            <?php if ($isLoggedIn): ?>
            <span class="hidden sm:inline text-white/80 text-xs">
                <i class="fa-solid fa-user-circle mr-1"></i> <?= e($currentName) ?>
            </span>
            <?php if ($currentRole === 'admin'): ?>
            <span class="bg-white/15 text-white px-3 py-1 rounded-full text-xs font-medium backdrop-blur-sm">
                <i class="fa-solid fa-shield-halved mr-1"></i> Admin
            </span>
            <?php else: ?>
            <span class="bg-white/15 text-white px-3 py-1 rounded-full text-xs font-medium backdrop-blur-sm">
                <i class="fa-solid fa-user mr-1"></i> Staff
            </span>
            <?php endif; ?>
            <a href="<?= $base ?>/logout.php" class="text-white/70 hover:text-white transition text-xs" title="Sign out">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Page content -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8">
