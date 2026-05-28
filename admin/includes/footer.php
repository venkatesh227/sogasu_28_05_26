    </div> <!-- End layout-wrapper -->

    <script>
        // Toggle Sidebar Menu
        function toggleMenu(menuId) {
            const menu = document.getElementById(menuId);
            const parent = menu.previousElementSibling;
            if (menu.classList.contains('open')) {
                menu.classList.remove('open');
                parent.classList.remove('open');
            } else {
                menu.classList.add('open');
                parent.classList.add('open');
            }
        }

        // Toggle Sidebar for Mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        // Initialize DataTables
        $(document).ready(function() {
            if ($('.datatable').length > 0) {
                $('.datatable').DataTable({
                    responsive: true,
                    pageLength: 25,
                    order: [], // Disable initial sort so PHP order is kept
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search..."
                    }
                });
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if (!empty($_SESSION['success'])): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: '<?= htmlspecialchars($_SESSION['success']) ?>',
                timer: 2500,
                showConfirmButton: false
            });
        </script>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error'])): ?>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= htmlspecialchars($_SESSION['error']) ?>'
            });
        </script>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
</body>
</html>
