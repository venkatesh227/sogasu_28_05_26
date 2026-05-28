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
        <a href="attendance.php" class="nav-item <?php echo ($activePage == 'attendance') ? 'active' : ''; ?>">
            <i class="ri-checkbox-circle-line"></i>
            <span>Clock</span>
        </a>
        <a href="leaves.php" class="nav-item <?php echo ($activePage == 'leaves') ? 'active' : ''; ?>">
            <i class="ri-umbrella-line"></i>
            <span>Leaves</span>
        </a>
        <a href="tasks.php" class="nav-item <?php echo ($activePage == 'tasks') ? 'active' : ''; ?>">
            <i class="ri-task-line"></i>
            <span>Tasks</span>
        </a>
        <a href="profile.php" class="nav-item <?php echo ($activePage == 'profile') ? 'active' : ''; ?>">
            <i class="ri-user-3-line"></i>
            <span>Profile</span>
        </a>
    </nav>

</body>
</html>
