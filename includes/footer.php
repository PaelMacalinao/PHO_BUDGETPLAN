    </main><!-- end page content -->
</div><!-- end main wrapper -->

<!-- JS Libraries -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.min.js"></script>

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

</body>
</html>
