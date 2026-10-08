<?php
// admin/includes/footer.php
// Administrator control panel common footer layout
?>
        <footer class="mt-5 py-4 border-top text-muted small text-center">
            &copy; <?php echo date('Y'); ?> <?php echo e(SITE_NAME); ?> Admin. All Rights Reserved. Crafted with care.
        </footer>
    </div> <!-- Close admin-main -->

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Core Admin script actions -->
    <script src="../assets/js/admin.js"></script>
    <script>
        // Sidebar responsive handlers
        const sidebar = document.getElementById('adminSidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarClose = document.getElementById('sidebarClose');

        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.add('show');
            });
        }
        if (sidebarClose && sidebar) {
            sidebarClose.addEventListener('click', () => {
                sidebar.classList.remove('show');
            });
        }
    </script>
</body>
</html>
