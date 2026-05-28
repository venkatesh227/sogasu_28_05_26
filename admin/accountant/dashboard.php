<?php
session_start();
$pageTitle = "Accountant Dashboard - Sogasu";
$activePage = "accountant-dashboard";
include '../includes/header.php'; // Adjusted path
?>

<main class="main-content">
    <div style="padding: 1rem;">
        <div class="page-header" style="margin-bottom: 2rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Accountant Panel</h2>
            <p class="text-muted">Financial overview and ledger management</p>
        </div>

        <!-- Accountant Stats -->
        <div class="cards-grid" style="grid-template-columns: repeat(5, 1fr); gap: 1rem;">
            <div class="count-card card-blue" style="padding: 1.25rem;">
                <h3 style="font-size: 0.85rem; color: #475569;">Total Revenue</h3>
                <div class="value" style="font-size: 1.4rem;">₹ 12,45,000</div>
            </div>
            <div class="count-card card-red" style="padding: 1.25rem;">
                <h3 style="font-size: 0.85rem; color: #475569;">Total Expenses</h3>
                <div class="value" style="font-size: 1.4rem;">₹ 4,20,000</div>
            </div>
            <div class="count-card card-green" style="padding: 1.25rem;">
                <h3 style="font-size: 0.85rem; color: #475569;">Total Credit</h3>
                <div class="value" style="font-size: 1.4rem;">₹ 8,50,000</div>
            </div>
            <div class="count-card card-orange" style="padding: 1.25rem;">
                <h3 style="font-size: 0.85rem; color: #475569;">Total Debit</h3>
                <div class="value" style="font-size: 1.4rem;">₹ 1,15,000</div>
            </div>
            <div class="count-card card-purple" style="padding: 1.25rem;">
                <h3 style="font-size: 0.85rem; color: #475569;">Net Balance</h3>
                <div class="value" style="font-size: 1.4rem;">₹ 8,25,000</div>
            </div>
        </div>

        <div style="margin-top: 2rem; display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
            <!-- Recent Ledger -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3 style="font-size: 1.1rem; color: #1e293b;">Recent Transactions</h3>
                    <a href="transactions.php" style="font-size: 0.85rem; color: #4f46e5;">View All</a>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <th style="padding: 0.75rem; text-align: left; color: #64748b; font-size: 0.8rem;">Date</th>
                            <th style="padding: 0.75rem; text-align: left; color: #64748b; font-size: 0.8rem;">Description</th>
                            <th style="padding: 0.75rem; text-align: right; color: #64748b; font-size: 0.8rem;">Credit</th>
                            <th style="padding: 0.75rem; text-align: right; color: #64748b; font-size: 0.8rem;">Debit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 0.75rem; font-size: 0.85rem;">24 Feb 2026</td>
                            <td style="padding: 0.75rem; font-size: 0.85rem;">Client Payment - Mrs. Rekha</td>
                            <td style="padding: 0.75rem; text-align: right; color: #16a34a; font-weight: 600;">₹ 2,500</td>
                            <td style="padding: 0.75rem; text-align: right;">-</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 0.75rem; font-size: 0.85rem;">23 Feb 2026</td>
                            <td style="padding: 0.75rem; font-size: 0.85rem;">Electricity Bill</td>
                            <td style="padding: 0.75rem; text-align: right;">-</td>
                            <td style="padding: 0.75rem; text-align: right; color: #dc2626; font-weight: 600;">₹ 1,200</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Quick Actions -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem;">
                <h3 style="font-size: 1.1rem; margin-bottom: 1.5rem;">Quick Links</h3>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <a href="ledger.php" class="btn" style="background: #f8fafc; color: #1e293b; border: 1px solid #e2e8f0; justify-content: flex-start;">
                        <i class="ri-book-2-line"></i> General Ledger
                    </a>
                    <a href="billing-view.php" class="btn" style="background: #f8fafc; color: #1e293b; border: 1px solid #e2e8f0; justify-content: flex-start;">
                        <i class="ri-file-list-3-line"></i> View All Bills
                    </a>
                    <a href="expenses-view.php" class="btn" style="background: #f8fafc; color: #1e293b; border: 1px solid #e2e8f0; justify-content: flex-start;">
                        <i class="ri-money-rupee-circle-line"></i> View Expenses
                    </a>
                    <a href="add-transaction.php" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="ri-add-line"></i> Add Manual Transaction
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
