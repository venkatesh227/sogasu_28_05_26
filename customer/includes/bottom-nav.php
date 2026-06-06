    <style>
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: var(--surface);
            display: flex;
            justify-content: space-around;
            padding: 0.75rem 0;
            box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.05);
            border-top: 1px solid var(--border);
            z-index: 100;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-item i {
            font-size: 1.5rem;
        }

        .nav-item.active {
            color: var(--primary);
        }
    </style>

    <nav class="bottom-nav">
        <a href="dashboard.php" class="nav-item <?php echo ($activePage == 'dashboard') ? 'active' : ''; ?>">
            <i class="ri-home-4-line"></i>
            <span>Home</span>
        </a>
        <a href="my-orders.php" class="nav-item <?php echo ($activePage == 'orders') ? 'active' : ''; ?>">
            <i class="ri-file-list-3-line"></i>
            <span>My Orders</span>
        </a>
        <a href="new-order.php" class="nav-item <?php echo ($activePage == 'new-order') ? 'active' : ''; ?>">
            <div style="width: 48px; height: 48px; background: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-top: -24px; box-shadow: 0 4px 6px rgba(219, 39, 119, 0.4); border: 4px solid var(--surface);">
                <i class="ri-add-line" style="color: white; font-size: 1.5rem;"></i>
            </div>
        </a>
        <!-- <a href="gallery.php" class="nav-item <?php echo ($activePage == 'gallery') ? 'active' : ''; ?>">
            <i class="ri-image-line"></i>
            <span>Gallery</span>
        </a> -->
        <a href="profile.php" class="nav-item <?php echo ($activePage == 'profile') ? 'active' : ''; ?>">
            <i class="ri-user-3-line"></i>
            <span>Profile</span>
        </a>
    </nav>

</body>
</html>
