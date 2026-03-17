<?php
/**
 * Shared header: HTML head, sidebar, top bar.
 * Set $pageTitle, $activeMenu before including this file.
 */
$pageTitle  = $pageTitle  ?? 'Dashboard';
$activeMenu = $activeMenu ?? 'dashboard';

initSession();
$currentRole = getUserRole();

$menuItems = [
    ['section' => 'MAIN'],
    ['key' => 'dashboard',       'label' => 'Dashboard',        'icon' => 'fa-chart-pie',    'href' => '/PHO_BUDGETING/index.php'],
    ['key' => 'admin_dashboard', 'label' => 'Budget Overview',  'icon' => 'fa-layer-group',  'href' => '/PHO_BUDGETING/admin_dashboard.php'],
    ['section' => 'PROPOSALS'],
    ['key' => 'create',          'label' => 'New Proposal',     'icon' => 'fa-plus-circle',  'href' => '/PHO_BUDGETING/create.php'],
    ['section' => 'MASTER DATA'],
    ['key' => 'account_codes', 'label' => 'Account Codes',   'icon' => 'fa-barcode',      'href' => '/PHO_BUDGETING/master/account_codes.php'],
    ['key' => 'programs',      'label' => 'Programs (PPA)',    'icon' => 'fa-sitemap',     'href' => '/PHO_BUDGETING/master/programs.php'],
    ['key' => 'units',         'label' => 'Units',             'icon' => 'fa-building',    'href' => '/PHO_BUDGETING/master/units.php'],
    ['key' => 'fund_sources',  'label' => 'Fund Sources',     'icon' => 'fa-wallet',      'href' => '/PHO_BUDGETING/master/fund_sources.php'],
    ['key' => 'indicators',    'label' => 'Indicators',       'icon' => 'fa-gauge-high',  'href' => '/PHO_BUDGETING/master/indicators.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {50:'#eef2ff',100:'#e0e7ff',200:'#c7d2fe',300:'#a5b4fc',400:'#818cf8',500:'#6366f1',600:'#4f46e5',700:'#4338ca',800:'#3730a3',900:'#312e81'},
                    }
                }
            }
        }
    </script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        input[type="number"]{-moz-appearance:textfield}
        input::-webkit-outer-spin-button,input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
        ::-webkit-scrollbar{width:6px;height:6px}
        ::-webkit-scrollbar-thumb{background:#c7d2fe;border-radius:4px}
        table.dataTable thead th{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280}
        table.dataTable tbody td{font-size:.875rem;vertical-align:middle}
        table.dataTable tbody tr:hover{background:#f5f3ff !important}
        .dataTables_wrapper .pagination .page-item.active .page-link{background-color:#4f46e5!important;border-color:#4f46e5!important;color:#fff!important}
        .dataTables_wrapper .pagination .page-link{color:#4f46e5;border-radius:.375rem;margin:0 2px;font-size:.85rem}
        .dataTables_wrapper .dataTables_info{font-size:.8rem;color:#9ca3af}

        /* ── Sidebar & Overlay Transitions ───────────── */
        #sidebar {
            transition: transform 0.3s cubic-bezier(.4,0,.2,1);
            will-change: transform;
        }
        #sidebarOverlay {
            position: fixed;
            inset: 0;
            z-index: 40;
            background: rgba(0,0,0,0);
            pointer-events: none;
            visibility: hidden;
            transition: background 0.3s ease-in-out, visibility 0.3s ease-in-out;
        }
        #sidebarOverlay.active {
            background: rgba(0,0,0,.55);
            pointer-events: auto;
            visibility: visible;
        }
        body.sidebar-open { overflow: hidden; }

        /* ── Hamburger Icon Animation ────────────────── */
        .hamburger {
            width: 22px;
            height: 18px;
            position: relative;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 0;
            border: none;
            background: none;
            -webkit-tap-highlight-color: transparent;
        }
        .hamburger .bar {
            display: block;
            width: 100%;
            height: 2.5px;
            border-radius: 2px;
            background: #6b7280;
            transition: transform 0.3s ease-in-out, opacity 0.25s ease-in-out, background 0.3s ease;
            transform-origin: center;
        }
        .hamburger:hover .bar { background: #4f46e5; }
        .hamburger.is-active .bar:nth-child(1) { transform: translateY(7.75px) rotate(45deg); }
        .hamburger.is-active .bar:nth-child(2) { opacity: 0; transform: scaleX(0); }
        .hamburger.is-active .bar:nth-child(3) { transform: translateY(-7.75px) rotate(-45deg); }

        /* ── Sidebar link refinements ────────────────── */
        .sidebar-link {
            transition: all .2s ease;
            position: relative;
        }
        .sidebar-link:hover { background: rgba(79,70,229,.06); color: #4338ca; }
        .sidebar-link.active {
            background: rgba(79,70,229,.1);
            color: #4338ca;
            font-weight: 600;
        }
        .sidebar-link.active::before {
            content: '';
            position: absolute;
            right: 0;
            top: 6px;
            bottom: 6px;
            width: 3px;
            border-radius: 3px 0 0 3px;
            background: #4f46e5;
        }

        #sidebar.sidebar-visible { box-shadow: 4px 0 24px rgba(0,0,0,.12); }
    </style>
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
    <header class="sticky top-0 z-30 bg-white/95 backdrop-blur-sm border-b border-gray-200 shadow-sm h-16 flex items-center justify-between px-4 sm:px-6 shrink-0">
        <div class="flex items-center gap-3">
            <!-- Hamburger toggle -->
            <button id="hamburgerBtn" class="hamburger" aria-label="Toggle navigation" aria-expanded="false">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </button>
            <!-- App branding (always visible) -->
            <div class="flex items-center gap-2.5">
                <div class="bg-brand-600 text-white w-9 h-9 rounded-lg flex items-center justify-center text-sm font-bold shadow-sm">
                    <i class="fa-solid fa-building-columns"></i>
                </div>
                <div class="leading-tight">
                    <span class="block text-sm font-bold text-gray-800 tracking-tight">PHO Budgeting</span>
                    <span class="block text-[10px] text-gray-400">FY 2026</span>
                </div>
            </div>
            <!-- Page title separator (hidden on small screens) -->
            <div class="hidden sm:flex items-center gap-3">
                <span class="text-gray-300">|</span>
                <h1 class="text-base font-semibold text-gray-600 tracking-tight"><?= e($pageTitle) ?></h1>
            </div>
        </div>
        <div class="flex items-center gap-3 text-sm text-gray-500">
            <?php if ($currentRole === 'admin'): ?>
            <span class="bg-red-50 text-red-700 px-3 py-1 rounded-full text-xs font-medium">
                <i class="fa-solid fa-shield-halved mr-1"></i> Admin
            </span>
            <?php else: ?>
            <span class="bg-brand-50 text-brand-700 px-3 py-1 rounded-full text-xs font-medium">
                <i class="fa-solid fa-user mr-1"></i> Staff
            </span>
            <?php endif; ?>
        </div>
    </header>

    <!-- Page content -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8">
