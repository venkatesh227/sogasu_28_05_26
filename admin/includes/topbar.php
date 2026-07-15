<!-- Top Header & Filter & Profile -->
<header class="top-header" style="justify-content: space-between; gap: 1rem; flex-wrap: nowrap; align-items: center;">

    <i class="ri-menu-line mobile-toggle" onclick="toggleSidebar()"></i>

    <!-- Left Section: Search & Filters -->
    <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
        <!-- Search -->
        <div class="search-bar" style="width: 220px; min-width: 200px;">
            <i class="ri-search-line"></i>
            <input type="text" placeholder="Search...">
        </div>

        <!-- Filter Controls -->
        <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php'): ?>

            <form method="GET" action="dashboard.php" id="dashboardFilterForm" class="filter-bar"
                style="margin-bottom: 0; flex-wrap: nowrap;">

                <!-- Branch Filter -->
                <div style="position: relative;">
                    <i class="ri-store-2-line"
                        style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 0.9rem; pointer-events: none;"></i>

                    <select name="branch" id="branchFilter" class="btn-filter"
                        onchange="document.getElementById('dashboardFilterForm').submit();"
                        style="padding-left: 2rem; padding-right: 2rem; -webkit-appearance: none; appearance: none; height: 100%; border-radius: 0;">

                        <option value="">All Branches</option>

                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= htmlspecialchars($branch['branch_name']) ?>"
                                <?= $selectedBranch === $branch['branch_name'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($branch['branch_name']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                    <i class="ri-arrow-down-s-line"
                        style="position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); color: #64748b; pointer-events: none;"></i>
                </div>

                <!-- Period Filters -->
                <div class="filter-group">

                    <?php foreach (['24H', '1W', '1M', '1Y'] as $period): ?>

                        <button type="submit" name="period" value="<?= $period ?>"
                            class="filter-option <?= (!$isCustomDateRange && $selectedPeriod === $period) ? 'active' : '' ?>"
                            style="border: none; cursor: pointer;">

                            <?= $period ?>

                        </button>

                    <?php endforeach; ?>

                </div>

                <!-- Custom Date Range -->
                <div style="position: relative;">

                    <button type="button" class="btn-filter" id="dateRangeButton"
                        onclick="toggleDashboardDateRange(event);">

                        <i class="ri-calendar-line"></i>

                        <span id="dateRangeLabel">
                            <?php if ($isCustomDateRange): ?>
                                <?= date('M d', strtotime($fromDate)) ?>
                                -
                                <?= date('M d', strtotime($toDate)) ?>
                            <?php else: ?>
                                Select Dates
                            <?php endif; ?>
                        </span>

                    </button>

                    <div id="dashboardDateRangeBox" style="
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 280px;
            padding: 12px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
            z-index: 9999;
        ">

                        <div style="margin-bottom: 10px;">
                            <label
                                style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">
                                FROM DATE
                            </label>

                            <input type="date" id="dashboardDatePickerFrom" value="<?= htmlspecialchars($fromDate) ?>"
                                style="
                    width: 100%;
                    padding: 8px;
                    border: 1px solid #e2e8f0;
                    border-radius: 6px;
                    box-sizing: border-box;
                ">
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label
                                style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px;">
                                TO DATE
                            </label>

                            <input type="date" id="dashboardDatePickerTo" value="<?= htmlspecialchars($toDate) ?>" style="
                                    width: 100%;
                                    padding: 8px;
                                    border: 1px solid #e2e8f0;
                                    border-radius: 6px;
                                    box-sizing: border-box;
                                ">
                        </div>

                        <button type="button" onclick="applyDashboardDateRange();" style="
                                width: 100%;
                                border: none;
                                background: #4f46e5;
                                color: #ffffff;
                                padding: 9px;
                                border-radius: 6px;
                                font-weight: 700;
                                cursor: pointer;
                            ">
                            Apply Dates
                        </button>

                    </div>

                </div>

                <!-- Export -->
                <button type="button" class="btn-filter" onclick="exportDashboard();">

                    <i class="ri-download-2-line"></i>
                    Export

                </button>

                <input type="hidden" name="from_date" id="dashboardFromDate" value="<?= htmlspecialchars($fromDate) ?>">

                <input type="hidden" name="to_date" id="dashboardToDate" value="<?= htmlspecialchars($toDate) ?>">

            </form>

        <?php endif; ?>
    </div>

    <!-- Right Section: User Profile (Moved from Sidebar) -->
    <div class="user-profile-corner" style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 0;">
        <div style="display: flex; gap: 0.75rem;">
            <button
                style="border: none; background: transparent; color: #94a3b8; font-size: 1.2rem; cursor: pointer;"><i
                    class="ri-notification-3-line"></i></button>
            <button
                style="border: none; background: transparent; color: #94a3b8; font-size: 1.2rem; cursor: pointer;"><i
                    class="ri-settings-line"></i></button>
            <a href="../includes/logout.php"
                style="border: none; background: transparent; color: #ef4444; font-size: 1.2rem; cursor: pointer; text-decoration: none;"
                title="Logout">
                <i class="ri-logout-circle-r-line"></i>
            </a>
        </div>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div class="profile-info" style="text-align: right;">
                <div class="profile-name" style="font-weight: 700; color: #0f172a; font-size: 0.9rem;">
                    Sushmita
                </div>
                <div class="profile-role" style="font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Admin
                </div>
            </div>
            <img src="https://ui-avatars.com/api/?name=Sushmita+A&background=8b5cf6&color=fff"
                style="width: 40px; height: 40px; border-radius: 50%;">
        </div>
    </div>

</header>
<?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php'): ?>
<script>
    function toggleDashboardDateRange(event) {
        event.stopPropagation();

        const dateBox = document.getElementById('dashboardDateRangeBox');

        dateBox.style.display =
            dateBox.style.display === 'block'
                ? 'none'
                : 'block';
    }

    function applyDashboardDateRange() {
        const fromDate = document.getElementById(
            'dashboardDatePickerFrom'
        ).value;

        const toDate = document.getElementById(
            'dashboardDatePickerTo'
        ).value;

        if (!fromDate || !toDate) {
            alert('Please select both From Date and To Date.');
            return;
        }

        if (fromDate > toDate) {
            alert('From Date cannot be greater than To Date.');
            return;
        }

        document.getElementById(
            'dashboardFromDate'
        ).value = fromDate;

        document.getElementById(
            'dashboardToDate'
        ).value = toDate;

        document.getElementById(
            'dashboardFilterForm'
        ).submit();
    }

    document.addEventListener('click', function (event) {
        const dateBox = document.getElementById(
            'dashboardDateRangeBox'
        );

        const dateButton = document.getElementById(
            'dateRangeButton'
        );

        if (
            dateBox &&
            dateButton &&
            !dateBox.contains(event.target) &&
            !dateButton.contains(event.target)
        ) {
            dateBox.style.display = 'none';
        }
    });
</script>

<?php endif; ?>