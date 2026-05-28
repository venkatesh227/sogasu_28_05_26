<?php
$pageTitle = "Payments - Sogasu";
$activePage = "payments";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="dashboard-container animate-fade-in" style="padding: 2rem; max-width: 1400px; margin: 0 auto;">
        
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem;">
            <div>
                <h1 style="font-size: 2rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.5rem; letter-spacing: -0.02em;">Payments & Finance</h1>
                <p style="color: var(--text-muted); font-size: 1rem; font-weight: 500;">Track income, expenses and manage pending receivables.</p>
            </div>
            
            <div style="display: flex; gap: 1rem;">
                <button class="btn-premium" style="background: white; color: var(--text-dark); border-color: #e2e8f0;"><i class="ri-download-2-line"></i> Export Report</button>
                <button class="btn-premium"><i class="ri-add-line"></i> Record Expense</button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="premium-stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 2.5rem;">
            <div class="premium-stat-card card-income">
                <div class="stat-header">
                    <div class="stat-icon"><i class="ri-money-rupee-circle-line"></i></div>
                    <div class="stat-trend up"><i class="ri-arrow-up-s-line"></i> 15%</div>
                </div>
                <div class="stat-label">Total Income (Feb)</div>
                <div class="stat-value">₹ 1,24,500</div>
                <div class="stat-footer">vs ₹ 1,08,200 last month</div>
            </div>
            
            <div class="premium-stat-card card-warning">
                <div class="stat-header">
                    <div class="stat-icon"><i class="ri-time-line"></i></div>
                    <div class="stat-trend down"><i class="ri-alert-line"></i> Overdue</div>
                </div>
                <div class="stat-label">Pending Receivables</div>
                <div class="stat-value">₹ 12,450</div>
                <div class="stat-footer">5 outstanding invoices</div>
            </div>
            
            <div class="premium-stat-card card-expense">
                <div class="stat-header">
                    <div class="stat-icon"><i class="ri-hand-coin-line"></i></div>
                    <div class="stat-trend neutral"><i class="ri-subtract-line"></i> On Track</div>
                </div>
                <div class="stat-label">Monthly Expenses</div>
                <div class="stat-value">₹ 35,200</div>
                <div class="stat-footer">Salaries, Rent & Materials</div>
            </div>
        </div>

        <!-- Transaction Table -->
        <div class="glass-card" style="padding: 0; overflow: hidden; border-radius: 24px;">
            <div style="padding: 1.5rem 2rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin: 0;">Recent Transactions</h3>
                <div style="display: flex; gap: 1rem;">
                    <select class="glass-card" style="padding: 0.5rem 1rem; border-radius: 12px; font-size: 0.85rem; font-weight: 600; color: var(--text-dark); outline: none; border-color: #f1f5f9;">
                        <option>All Transactions</option>
                        <option>Income</option>
                        <option>Expense</option>
                        <option>Receivable</option>
                    </select>
                </div>
            </div>
            
            <div style="padding: 1.5rem 2rem;">
                <table id="paymentsTable" class="premium-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Reference</th>
                            <th>Description / Party</th>
                            <th>Type</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="font-weight: 500; color: var(--text-muted);">24 Feb, 10:30 AM</td>
                            <td style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: var(--primary); font-weight: 700;">TXN-8842</td>
                            <td>
                                <div style="font-weight: 700; color: var(--text-dark);">Rashmi K</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Order #ORD-2458</div>
                            </td>
                            <td><span class="status-badge" style="background: #f0fdf4; color: #16a34a; border-color: #dcfce7;">Income</span></td>
                            <td style="font-weight: 600; color: var(--text-muted);">UPI (PhonePe)</td>
                            <td>
                                <span style="display: flex; align-items: center; gap: 0.4rem; color: #16a34a; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #16a34a;"></span> Success
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 900; color: #16a34a; font-size: 1.1rem;">+ ₹ 1,200</td>
                        </tr>
                        <tr>
                            <td style="font-weight: 500; color: var(--text-muted);">23 Feb, 04:15 PM</td>
                            <td style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: var(--primary); font-weight: 700;">TXN-8841</td>
                            <td>
                                <div style="font-weight: 700; color: var(--text-dark);">Gold Threads Purchase</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Inventory Restock</div>
                            </td>
                            <td><span class="status-badge" style="background: #fff1f2; color: #e11d48; border-color: #ffe4e6;">Expense</span></td>
                            <td style="font-weight: 600; color: var(--text-muted);">Cash</td>
                            <td>
                                <span style="display: flex; align-items: center; gap: 0.4rem; color: #16a34a; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #16a34a;"></span> Success
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 900; color: #e11d48; font-size: 1.1rem;">- ₹ 450</td>
                        </tr>
                        <tr>
                            <td style="font-weight: 500; color: var(--text-muted);">23 Feb, 02:00 PM</td>
                            <td style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: var(--primary); font-weight: 700;">TXN-8840</td>
                            <td>
                                <div style="font-weight: 700; color: var(--text-dark);">Sneha J</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Order #ORD-2457</div>
                            </td>
                            <td><span class="status-badge" style="background: #f0fdf4; color: #16a34a; border-color: #dcfce7;">Income</span></td>
                            <td style="font-weight: 600; color: var(--text-muted);">Card (HDFC)</td>
                            <td>
                                <span style="display: flex; align-items: center; gap: 0.4rem; color: #16a34a; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #16a34a;"></span> Success
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 900; color: #16a34a; font-size: 1.1rem;">+ ₹ 4,500</td>
                        </tr>
                        <tr>
                            <td style="font-weight: 500; color: var(--text-muted);">22 Feb, 11:00 AM</td>
                            <td style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: var(--primary); font-weight: 700;">TXN-PENDING</td>
                            <td>
                                <div style="font-weight: 700; color: var(--text-dark);">Mrs. Shanti</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Order #ORD-2440</div>
                            </td>
                            <td><span class="status-badge" style="background: #fffbeb; color: #d97706; border-color: #fef3c7;">Receivable</span></td>
                            <td style="font-weight: 600; color: var(--text-muted);">-</td>
                            <td>
                                <span style="display: flex; align-items: center; gap: 0.4rem; color: #d97706; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #d97706;"></span> Pending
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 900; color: #d97706; font-size: 1.1rem;">₹ 2,800</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<style>
    .card-income { --primary: #10b981; --primary-light: #d1fae5; }
    .card-warning { --primary: #f59e0b; --primary-light: #fef3c7; }
    .card-expense { --primary: #ef4444; --primary-light: #fee2e2; }
</style>

<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>
    $(document).ready(function() {
        initializeDataTable('paymentsTable', 'Payments & Transactions');
    });
</script>

<?php include 'includes/footer.php'; ?>
