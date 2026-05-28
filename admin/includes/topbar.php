            <!-- Top Header & Filter & Profile -->
            <header class="top-header"
                style="justify-content: space-between; gap: 1rem; flex-wrap: nowrap; align-items: center;">

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
                    <div class="filter-bar" style="margin-bottom: 0; flex-wrap: nowrap;">

                        <!-- Branch Filter -->
                        <div style="position: relative;">
                            <i class="ri-store-2-line"
                                style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 0.9rem; pointer-events: none;"></i>
                            <select class="btn-filter"
                                style="padding-left: 2rem; padding-right: 2rem; -webkit-appearance: none; appearance: none; height: 100%; border-radius: 0;">
                                <option>All Branches</option>
                                <option>Jayanagar</option>
                                <option>Indiranagar</option>
                            </select>
                            <i class="ri-arrow-down-s-line"
                                style="position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); color: #64748b; pointer-events: none;"></i>
                        </div>

                        <div class="filter-group">
                            <div class="filter-option">24H</div>
                            <div class="filter-option">1W</div>
                            <div class="filter-option">1M</div>
                            <div class="filter-option active">1Y</div>
                        </div>

                        <button class="btn-filter">
                            <i class="ri-calendar-line"></i> Mar 26 - Aug 26
                        </button>

                        <button class="btn-filter">
                            <i class="ri-download-2-line"></i> Export
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Right Section: User Profile (Moved from Sidebar) -->
                <div class="user-profile-corner"
                    style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 0;">
                    <div style="display: flex; gap: 0.75rem;">
                        <button
                            style="border: none; background: transparent; color: #94a3b8; font-size: 1.2rem; cursor: pointer;"><i
                                class="ri-notification-3-line"></i></button>
                        <button
                            style="border: none; background: transparent; color: #94a3b8; font-size: 1.2rem; cursor: pointer;"><i
                                class="ri-settings-line"></i></button>
                        <a href="../includes/logout.php"
                            style="border: none; background: transparent; color: #ef4444; font-size: 1.2rem; cursor: pointer; text-decoration: none;" title="Logout">
                            <i class="ri-logout-circle-r-line"></i>
                        </a>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div class="profile-info" style="text-align: right;">
                            <div class="profile-name" style="font-weight: 700; color: #0f172a; font-size: 0.9rem;">
                                Sushmita
                            </div>
                            <div class="profile-role"
                                style="font-size: 0.75rem; color: #64748b; text-transform: uppercase;">Admin</div>
                        </div>
                        <img src="https://ui-avatars.com/api/?name=Sushmita+A&background=8b5cf6&color=fff"
                            style="width: 40px; height: 40px; border-radius: 50%;">
                    </div>
                </div>

            </header>
