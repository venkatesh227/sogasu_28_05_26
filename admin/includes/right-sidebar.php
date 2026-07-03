<!-- Right Sidebar Panel -->
<aside class="right-panel">
    <!-- Quick Actions Grid (Right Panel) -->
    <div style="margin-top: 0;">
        <div class="section-header" style="margin-bottom: 0.5rem; border-bottom: none;">
            <h3 class="section-title" style="font-size: 0.85rem;">Quick Actions</h3>
        </div>
        <div class="quick-actions-grid">
            <div class="quick-action-btn" onclick="window.location.href='add-order.php'">
                <i class="ri-add-circle-line"></i>
                <span>Add Order</span>
            </div>
            <div class="quick-action-btn" onclick="window.location.href='add-customer.php'">
                <i class="ri-user-add-line" style="color: #059669;"></i>
                <span>Add Customer</span>
            </div>
            <div class="quick-action-btn" onclick="window.location.href='inventory.php'">
                <i class="ri-archive-line" style="color: #2563eb;"></i>
                <span>Inventory</span>
            </div>
            <div class="quick-action-btn" onclick="window.location.href='orders.php'">
                <i class="ri-file-list-3-line" style="color: #d97706;"></i>
                <span>View Orders</span>
            </div>
            <div class="quick-action-btn" onclick="window.location.href='payroll.php'">
                <i class="ri-wallet-3-line" style="color: #7c3aed;"></i>
                <span>Payroll</span>
            </div>
            <div class="quick-action-btn" onclick="window.location.href='customers.php'">
                <i class="ri-group-line" style="color: #db2777;"></i>
                <span>Customers</span>
            </div>
        </div>
    </div>

    <!-- Ready For Delivery Section (Scrollable Area) -->
    <div style="flex: 1; display: flex; flex-direction: column; min-height: 0; margin-top: 1.5rem;">
        <div class="section-header" style="margin-bottom: 0.5rem; border-bottom: none;">
            <h3 class="section-title" style="font-size: 0.85rem;">Ready for Delivery</h3>
            <a href="customers.php"
                style="font-size: 0.7rem; color: var(--primary); font-weight: 600; text-transform: uppercase;">View
                All</a>
        </div>

        <div class="delivery-scroll-area">
            <?php if (!empty($readyDelivery)): ?>
                <?php foreach ($readyDelivery as $item): ?>
                    <?php
                    $name = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
                    $initials = strtoupper(substr($item['first_name'] ?? 'X', 0, 1) . substr($item['last_name'] ?? '', 0, 1));
                    $phone = preg_replace('/\D/', '', $item['phone'] ?? '');
                    ?>

                    <div class="delivery-item">
                        <div class="avatar-circle" style="background:#e0e7ff;color:#4338ca;">
                            <?= $initials ?>
                        </div>

                        <div class="content">
                            <h4><?= htmlspecialchars($name) ?></h4>
                            <p><?= htmlspecialchars($item['sub_category'] ?? 'Order') ?></p>
                        </div>

                        <?php if ($phone): ?>
                            <div class="action-btn" onclick="window.open('https://wa.me/91<?= $phone ?>','_blank')">
                                <i class="ri-whatsapp-line"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php endforeach; ?>
            <?php else: ?>
                <p>No ready deliveries</p>
            <?php endif; ?>
        </div>
    </div>
</aside>