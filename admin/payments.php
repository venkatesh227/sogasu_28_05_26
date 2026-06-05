<?php
$pageTitle = "Payments - Sogasu";
$activePage = "payments";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="dashboard-container animate-fade-in" style="padding: 1.25rem;">

        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem;">

            <div>
                <h1 style="font-size:2rem; font-weight:800; color:var(--text-dark); margin-bottom:0.35rem;">
                    Payments & Finance
                </h1>

                <p style="color:var(--text-muted); font-size:1rem;">
                    Track income, expenses and manage pending receivables.
                </p>
            </div>

            <button class="btn-premium">
                <i class="ri-add-line"></i>
                Record Expense
            </button>

        </div>

        <!-- Stats Grid -->
        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:1.5rem;">

            <!-- CARD 1 -->
            <div class="table-container" style="padding:1.25rem;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">

                    <div>
                        <div style="font-size:0.75rem; font-weight:700; color:#64748b; text-transform:uppercase;">
                            TOTAL INCOME (FEB)
                        </div>

                        <div style="font-size:2rem; font-weight:800; color:#0f172a; margin:0.75rem 0 0.35rem;">
                            ₹ 1,24,500
                        </div>

                        <div style="font-size:0.85rem; color:#64748b;">
                            vs ₹ 1,08,200 last month
                        </div>
                    </div>

                    <div style="
                width:48px;
                height:48px;
                border-radius:12px;
                background:rgba(79,70,229,0.1);
                color:#4f46e5;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:1.3rem;
                flex-shrink:0;
            ">
                        <i class="ri-line-chart-line"></i>
                    </div>

                </div>
            </div>

            <!-- CARD 2 -->
            <div class="table-container" style="padding:1.25rem;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">

                    <div>
                        <div style="font-size:0.75rem; font-weight:700; color:#64748b; text-transform:uppercase;">
                            PENDING RECEIVABLES
                        </div>

                        <div style="font-size:2rem; font-weight:800; color:#0f172a; margin:0.75rem 0 0.35rem;">
                            ₹ 12,450
                        </div>

                        <div style="font-size:0.85rem; color:#64748b;">
                            5 outstanding invoices
                        </div>
                    </div>

                    <div style="
                width:48px;
                height:48px;
                border-radius:12px;
                background:rgba(245,158,11,0.1);
                color:#f59e0b;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:1.3rem;
                flex-shrink:0;
            ">
                        <i class="ri-error-warning-line"></i>
                    </div>

                </div>
            </div>

            <!-- CARD 3 -->
            <div class="table-container" style="padding:1.25rem;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">

                    <div>
                        <div style="font-size:0.75rem; font-weight:700; color:#64748b; text-transform:uppercase;">
                            MONTHLY EXPENSES
                        </div>

                        <div style="font-size:2rem; font-weight:800; color:#0f172a; margin:0.75rem 0 0.35rem;">
                            ₹ 35,200
                        </div>

                        <div style="font-size:0.85rem; color:#64748b;">
                            Salaries, Rent & Materials
                        </div>
                    </div>

                    <div style="
                width:48px;
                height:48px;
                border-radius:12px;
                background:rgba(239,68,68,0.1);
                color:#ef4444;
                display:flex;
                align-items:center;
                justify-content:center;
                font-size:1.3rem;
                flex-shrink:0;
            ">
                        <i class="ri-wallet-3-line"></i>
                    </div>

                </div>
            </div>

        </div>

    </div>

    <!-- Transaction Table -->
    <div class="glass-card"
        style="padding:0; overflow:hidden; border-radius:18px; border:1px solid #eef2f7; box-shadow:0 4px 20px rgba(15,23,42,0.04);">
        <div
            style="padding:1rem 1.25rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin: 0;">Recent
                Transactions</h3>
            <div style="display: flex; gap: 1rem;">
                <select class="form-control" style="
                        padding:0.55rem 0.9rem;
                        border-radius:10px;
                        font-size:0.85rem;
                        font-weight:600;
                        border:1px solid #e2e8f0;
                        background:#fff;
                        min-width:180px;
                        ">
                    <option>All Transactions</option>
                    <option>Income</option>
                    <option>Expense</option>
                    <option>Receivable</option>
                </select>
            </div>
        </div>

        <div style="padding:1rem 1.25rem;">
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
                        <td
                            style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: var(--primary); font-weight: 700;">
                            TXN-8842</td>
                        <td>
                            <div style="font-weight: 700; color: var(--text-dark);">Rashmi K</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Order
                                #ORD-2458</div>
                        </td>
                        <td><span class="status-badge"
                                style="background: #f0fdf4; color: #16a34a; border-color: #dcfce7;">Income</span>
                        </td>
                        <td style="font-weight: 600; color: var(--text-muted);">UPI (PhonePe)</td>
                        <td>
                            <span
                                style="display: flex; align-items: center; gap: 0.4rem; color: #16a34a; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">
                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #16a34a;"></span>
                                Success
                            </span>
                        </td>
                        <td style="text-align: right; font-weight: 900; color: #16a34a; font-size: 1.1rem;">+ ₹
                            1,200</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 500; color: var(--text-muted);">23 Feb, 04:15 PM</td>
                        <td
                            style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: var(--primary); font-weight: 700;">
                            TXN-8841</td>
                        <td>
                            <div style="font-weight: 700; color: var(--text-dark);">Gold Threads Purchase</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Inventory
                                Restock</div>
                        </td>
                        <td><span class="status-badge"
                                style="background: #fff1f2; color: #e11d48; border-color: #ffe4e6;">Expense</span>
                        </td>
                        <td style="font-weight: 600; color: var(--text-muted);">Cash</td>
                        <td>
                            <span
                                style="display: flex; align-items: center; gap: 0.4rem; color: #16a34a; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">
                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #16a34a;"></span>
                                Success
                            </span>
                        </td>
                        <td style="text-align: right; font-weight: 900; color: #e11d48; font-size: 1.1rem;">- ₹ 450
                        </td>
                    </tr>
                    <tr>
                        <td style="font-weight: 500; color: var(--text-muted);">23 Feb, 02:00 PM</td>
                        <td
                            style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: var(--primary); font-weight: 700;">
                            TXN-8840</td>
                        <td>
                            <div style="font-weight: 700; color: var(--text-dark);">Sneha J</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Order
                                #ORD-2457</div>
                        </td>
                        <td><span class="status-badge"
                                style="background: #f0fdf4; color: #16a34a; border-color: #dcfce7;">Income</span>
                        </td>
                        <td style="font-weight: 600; color: var(--text-muted);">Card (HDFC)</td>
                        <td>
                            <span
                                style="display: flex; align-items: center; gap: 0.4rem; color: #16a34a; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">
                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #16a34a;"></span>
                                Success
                            </span>
                        </td>
                        <td style="text-align: right; font-weight: 900; color: #16a34a; font-size: 1.1rem;">+ ₹
                            4,500</td>
                    </tr>
                    <tr>
                        <td style="font-weight: 500; color: var(--text-muted);">22 Feb, 11:00 AM</td>
                        <td
                            style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: var(--primary); font-weight: 700;">
                            TXN-PENDING</td>
                        <td>
                            <div style="font-weight: 700; color: var(--text-dark);">Mrs. Shanti</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">Order
                                #ORD-2440</div>
                        </td>
                        <td><span class="status-badge"
                                style="background: #fffbeb; color: #d97706; border-color: #fef3c7;">Receivable</span>
                        </td>
                        <td style="font-weight: 600; color: var(--text-muted);">-</td>
                        <td>
                            <span
                                style="display: flex; align-items: center; gap: 0.4rem; color: #d97706; font-weight: 800; font-size: 0.8rem; text-transform: uppercase;">
                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #d97706;"></span>
                                Pending
                            </span>
                        </td>
                        <td style="text-align: right; font-weight: 900; color: #d97706; font-size: 1.1rem;">₹ 2,800
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    </div>
</main>

<style>
    .card-income {
        --primary: #10b981;
        --primary-light: #d1fae5;
    }

    .card-warning {
        --primary: #f59e0b;
        --primary-light: #fef3c7;
    }

    .card-expense {
        --primary: #ef4444;
        --primary-light: #fee2e2;
    }

    .premium-stat-card {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 18px;
        padding: 1.4rem;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.04);
    }

    .stat-header {
        margin-bottom: 1rem;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
    }

    .stat-label {
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 800;
        margin: 0.35rem 0;
    }

    .stat-footer {
        font-size: 0.85rem;
    }

    .premium-table td {
        padding: 1rem 0.75rem;
    }

    .premium-table th {
        padding: 0.9rem 0.75rem;
        font-size: 0.75rem;
        letter-spacing: .04em;
    }

    .main-content {
        background: #f8fafc;
    }
</style>

<?php include __DIR__ . '/includes/datatable.php'; ?>
<script>
    $(document).ready(function () {
        initializeDataTable('paymentsTable', 'Payments & Transactions');
    });
</script>

<?php include 'includes/footer.php'; ?>