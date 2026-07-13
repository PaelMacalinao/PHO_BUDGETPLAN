    </main><!-- end page content -->

<?php
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if (basename($base) === 'master') {
    $base = dirname($base);
}
$base = rtrim($base, '/\\');
?>
    <footer class="mt-auto border-t border-gray-200 bg-white">
        <div class="mx-auto w-full max-w-[1400px] px-4 sm:px-6 lg:px-8 py-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-brand-700"><?= e(APP_ORG) ?></h3>
                    <p class="mt-2 text-xs sm:text-sm text-gray-600 leading-5">
                        PHO Budgeting System for planning, reviewing, and monitoring budget proposals across programs and fund sources.
                    </p>
                </div>

                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-brand-700">Contact Information</h3>
                    <div class="mt-2 space-y-1.5 text-xs sm:text-sm text-gray-600">
                        <p><i class="fa-solid fa-location-dot w-4 mr-2 text-brand-600"></i><?= e(APP_ADDRESS) ?></p>
                        <p><i class="fa-solid fa-globe w-4 mr-2 text-brand-600"></i><a href="<?= e(APP_WEBSITE) ?>" target="_blank" rel="noopener noreferrer" class="hover:text-brand-700 transition"><?= e(APP_WEBSITE) ?></a></p>
                        <?php if (APP_CONTACT_EMAIL !== ''): ?>
                        <p><i class="fa-solid fa-envelope w-4 mr-2 text-brand-600"></i><a href="mailto:<?= e(APP_CONTACT_EMAIL) ?>" class="hover:text-brand-700 transition"><?= e(APP_CONTACT_EMAIL) ?></a></p>
                        <?php endif; ?>
                        <?php if (APP_CONTACT_PHONE !== ''): ?>
                        <p><i class="fa-solid fa-phone w-4 mr-2 text-brand-600"></i><a href="tel:<?= e(APP_CONTACT_PHONE) ?>" class="hover:text-brand-700 transition"><?= e(APP_CONTACT_PHONE) ?></a></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-brand-700">Quick Links</h3>
                    <div class="mt-2 space-y-1.5 text-xs sm:text-sm">
                        <p><a href="<?= e($base) ?>/index.php" class="text-gray-600 hover:text-brand-700 transition">Dashboard</a></p>
                        <p><a href="<?= e($base) ?>/admin_dashboard.php" class="text-gray-600 hover:text-brand-700 transition">Budget Overview</a></p>
                        <p><a href="<?= e($base) ?>/login.php" class="text-gray-600 hover:text-brand-700 transition">Login</a></p>
                    </div>
                </div>
            </div>

            <div class="mt-6 pt-3 border-t border-gray-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 text-[11px] text-gray-400">
                <p>&copy; <?= date('Y') ?> <?= e(APP_ORG) ?>. All rights reserved.</p>
                <p>Budget Planning Portal</p>
            </div>
        </div>
    </footer>
</div><!-- end main wrapper -->

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- ═══ Sidebar Navigation Controller (Vanilla JS) ═══ -->
<script>
(function() {
    'use strict';

    var sidebar    = document.getElementById('sidebar');
    var overlay    = document.getElementById('sidebarOverlay');
    var hamburger  = document.getElementById('hamburgerBtn');
    var closeBtn   = document.getElementById('sidebarCloseBtn');
    var body       = document.body;
    var isOpen     = false;
    var BREAKPOINT = 1024;

    function openSidebar() {
        if (isOpen) return;
        isOpen = true;
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('sidebar-visible');
        overlay.classList.add('active');
        hamburger.classList.add('is-active');
        hamburger.setAttribute('aria-expanded', 'true');
        body.classList.add('sidebar-open');
    }

    function closeSidebar() {
        if (!isOpen) return;
        isOpen = false;
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('sidebar-visible');
        overlay.classList.remove('active');
        hamburger.classList.remove('is-active');
        hamburger.setAttribute('aria-expanded', 'false');
        body.classList.remove('sidebar-open');
    }

    function toggleSidebar() {
        isOpen ? closeSidebar() : openSidebar();
    }

    hamburger.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleSidebar();
    });

    overlay.addEventListener('click', function() {
        closeSidebar();
    });

    closeBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        closeSidebar();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isOpen) {
            closeSidebar();
        }
    });

    window.toggleSidebar = toggleSidebar;
})();
</script>

<!-- Version Selector Controller -->
<script>
(function() {
    var selects = [
        document.getElementById('globalVersionSelect'),
        document.getElementById('globalVersionSelectMobile')
    ].filter(Boolean);
    var versionWrap = document.getElementById('budgetVersionWrap');
    if (!selects.length) return;

    if (versionWrap && selects[0]) {
        versionWrap.addEventListener('click', function(e) {
            if (e.target.closest('select')) return;
            if (typeof selects[0].showPicker === 'function') {
                selects[0].showPicker();
            } else {
                selects[0].focus();
                selects[0].click();
            }
        });
    }

    selects.forEach(function(sel) {
        sel.addEventListener('change', function() {
            var url = new URL(window.location.href);
            url.searchParams.set('version', this.value);
            window.location.href = url.toString();
        });
    });
})();
</script>

<script>
(function() {
    var dateLabel = document.getElementById('headerDateLabel');
    var timeLabel = document.getElementById('headerTimeLabel');
    if (!dateLabel || !timeLabel) return;

    function updateHeaderDateTime() {
        var now = new Date();
        dateLabel.textContent = now.toLocaleDateString('en-PH', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
        timeLabel.textContent = now.toLocaleTimeString('en-PH', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }

    updateHeaderDateTime();
    setInterval(updateHeaderDateTime, 1000);
})();
</script>

</body>
</html>
