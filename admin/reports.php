<?php
session_start();
include '../includes/db.php';

$pageTitle = "Reports - Sogasu";
$activePage = "reports";

// ==================== CALCULATE METRICS ====================

// 1. Separate Employees Count
$inhouseEmpStmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE is_deleted = 0 AND employee_type = 'inhouse'");
$inhouseEmployees = $inhouseEmpStmt->fetchColumn();

$outsourceEmpStmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE is_deleted = 0 AND employee_type = 'outsource'");
$outsourceEmployees = $outsourceEmpStmt->fetchColumn();

$totalEmployees = $inhouseEmployees + $outsourceEmployees;

// 2. Total Customers (active, not deleted)
$totalCustStmt = $pdo->query("SELECT COUNT(*) FROM customers WHERE is_deleted = 0");
$totalCustomers = $totalCustStmt->fetchColumn();

// 3. Orders Collected Today
$todayDate = date('Y-m-d');
$todayIncome = $pdo->query("
    SELECT COALESCE(SUM(payment_amount), 0)
    FROM bill_payments
    WHERE is_deleted = 0 
    AND DATE(payment_date) = '$todayDate'
")->fetchColumn();

// 4. Orders Collected This Month
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-d');
$monthIncome = $pdo->query("
    SELECT COALESCE(SUM(payment_amount), 0)
    FROM bill_payments
    WHERE is_deleted = 0 
    AND DATE(payment_date) BETWEEN '$monthStart' AND '$monthEnd'
")->fetchColumn();

// 5. Total Expenses This Month
$monthExpenses = $pdo->query("
    SELECT COALESCE(SUM(amount), 0)
    FROM expenses
    WHERE DATE(expense_date) BETWEEN '$monthStart' AND '$monthEnd'
")->fetchColumn();

// 6. Net Profit This Month
$netProfit = $monthIncome - $monthExpenses;

// 7. Day-wise Attendance for Current Week (Last 7 days)
$dayWiseAttendance = [];
$dayLabels = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dayLabel = date('D', strtotime($date));
    $dayLabels[] = $dayLabel;

    // Present employees (have check-in logs)
    $presentStmt = $pdo->query("
        SELECT COUNT(DISTINCT employee_id)
        FROM attendance_logs
        WHERE DATE(log_date) = '$date'
        AND employee_id IN (SELECT id FROM employees WHERE is_deleted = 0 AND employee_type = 'inhouse')
    ");
    $presentCount = $presentStmt->fetchColumn();

    // Absent = Total inhouse - Present
    $absentCount = max(0, $inhouseEmployees - $presentCount);

    $dayWiseAttendance[] = [
        'present' => (int) $presentCount,
        'absent' => (int) $absentCount
    ];
}

// 8. Monthly Revenue Data (last 12 months for graph)
$monthlyRevenueData = [];
$monthlyLabels = [];

for ($i = 11; $i >= 0; $i--) {
    $date = date('Y-m-01', strtotime("-$i months"));
    $monthLabel = date('M', strtotime($date));
    $monthStart = date('Y-m-01', strtotime($date));
    $monthEnd = date('Y-m-t', strtotime($date));

    $revenue = $pdo->query("
        SELECT COALESCE(SUM(payment_amount), 0)
        FROM bill_payments
        WHERE is_deleted = 0 
        AND DATE(payment_date) BETWEEN '$monthStart' AND '$monthEnd'
    ")->fetchColumn();

    $expense = $pdo->query("
        SELECT COALESCE(SUM(amount), 0)
        FROM expenses
        WHERE DATE(expense_date) BETWEEN '$monthStart' AND '$monthEnd'
    ")->fetchColumn();

    $monthlyLabels[] = $monthLabel;
    $monthlyRevenueData[] = [
        'revenue' => (float) $revenue,
        'expense' => (float) $expense
    ];
}

// 9. Average Order Value This Month
$totalOrders = $pdo->query("
    SELECT COUNT(DISTINCT order_id)
    FROM bill_payments
    WHERE is_deleted = 0 
    AND DATE(payment_date) BETWEEN '$monthStart' AND '$monthEnd'
")->fetchColumn();

$avgOrderValue = $totalOrders > 0 ? $monthIncome / $totalOrders : 0;

include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="padding: 1.5rem;">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <div>
                <h2
                    style="font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.5rem; letter-spacing: -0.02em;">
                    Business Intelligence</h1>
                    <p style="color: var(--text-muted); font-size: 1rem; font-weight: 500;">Analyze your business
                        performance with advanced visual analytics.</p>
            </div>

            <div style="display:flex; align-items:center; gap:0.75rem;">
                <button style="
background:white;
border:1px solid #e2e8f0;
padding:10px 18px;
border-radius:8px;
font-weight:600;
color:#1e293b;
cursor:pointer;
"><i class="ri-calendar-line"></i> Select Period</button>
                <button style="
background:#4f46e5;
border:none;
padding:10px 18px;
border-radius:8px;
font-weight:600;
color:white;
cursor:pointer;
"></i> Download PDF Report</button>
            </div>
        </div>

        <!-- Premium KPI Widgets -->
        <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:1.25rem; margin-bottom:1.5rem;">

            <!-- Card 1: Inhouse Employees -->
            <div class="table-container" style="padding:1.25rem; border-radius:16px;">

                <div style="display:flex; justify-content:space-between; align-items:center;">

                    <div>
                        <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">
                            Inhouse Employees
                        </div>

                        <div style="font-size:1.8rem; font-weight:800; margin-top:0.5rem; color:#4f46e5;">
                            <?= $inhouseEmployees ?>
                        </div>
                    </div>

                    <div style="
            width:48px;
            height:48px;
            border-radius:12px;
            background:rgba(79,70,229,0.1);
            display:flex;
            align-items:center;
            justify-content:center;
            color:#4f46e5;
            font-size:1.4rem;
            ">
                        <i class="ri-team-line"></i>
                    </div>

                </div>
            </div>

            <!-- Card 2: Outsource Employees -->
            <div class="table-container" style="padding:1.25rem; border-radius:16px;">

                <div style="display:flex; justify-content:space-between; align-items:center;">

                    <div>
                        <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">
                            Outsource Employees
                        </div>

                        <div style="font-size:1.8rem; font-weight:800; margin-top:0.5rem; color:#10b981;">
                            <?= $outsourceEmployees ?>
                        </div>
                    </div>

                    <div style="
            width:48px;
            height:48px;
            border-radius:12px;
            background:rgba(16,185,129,0.1);
            display:flex;
            align-items:center;
            justify-content:center;
            color:#10b981;
            font-size:1.4rem;
            ">
                        <i class="ri-git-branch-line"></i>
                    </div>

                </div>
            </div>

            <!-- Card 3: Total Customers -->
            <div class="table-container" style="padding:1.25rem; border-radius:16px;">

                <div style="display:flex; justify-content:space-between; align-items:center;">

                    <div>
                        <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">
                            Total Customers
                        </div>

                        <div style="font-size:1.8rem; font-weight:800; margin-top:0.5rem; color:#f59e0b;">
                            <?= $totalCustomers ?>
                        </div>
                    </div>

                    <div style="
            width:48px;
            height:48px;
            border-radius:12px;
            background:rgba(245,158,11,0.1);
            display:flex;
            align-items:center;
            justify-content:center;
            color:#f59e0b;
            font-size:1.4rem;
            ">
                        <i class="ri-user-add-line"></i>
                    </div>

                </div>
            </div>

            <!-- Card 4: Orders This Month -->
            <div class="table-container" style="padding:1.25rem; border-radius:16px;">

                <div style="display:flex; justify-content:space-between; align-items:center;">

                    <div>
                        <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">
                            Orders This Month
                        </div>

                        <div style="font-size:1.8rem; font-weight:800; margin-top:0.5rem; color:#ef4444;">
                            ₹ <?= number_format($monthIncome, 0) ?>
                        </div>
                    </div>

                    <div style="
            width:48px;
            height:48px;
            border-radius:12px;
            background:rgba(239,68,68,0.1);
            display:flex;
            align-items:center;
            justify-content:center;
            color:#ef4444;
            font-size:1.4rem;
            ">
                        <i class="ri-shopping-cart-line"></i>
                    </div>

                </div>
            </div>

        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <!-- Orders Revenue Graph -->
            <div class="table-container" style="padding:1.5rem; border-radius:16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <div>
                        <h3
                            style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.25rem;">
                            Orders Revenue</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">Monthly revenue and
                            expenses trend</p>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <div
                            style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.8rem; background: #f0fdf4; border-radius: 8px; font-size: 0.75rem; font-weight: 800; color: #16a34a;">
                            Revenue
                        </div>
                        <div
                            style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.8rem; background: #fff1f2; border-radius: 8px; font-size: 0.75rem; font-weight: 800; color: #e11d48;">
                            Expense
                        </div>
                    </div>
                </div>
                <div style="height: 350px;">
                    <canvas id="revenueGraph"></canvas>
                </div>
            </div>

            <!-- Attendance This Week Graph -->
            <div class="table-container" style="padding:1.5rem; border-radius:16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <div>
                        <h3
                            style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.25rem;">
                            Weekly Attendance</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">Daily present vs
                            absent - Last 7 days</p>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <div
                            style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.8rem; background: #f0fdf4; border-radius: 8px; font-size: 0.75rem; font-weight: 800; color: #16a34a;">
                            <i class="ri-checkbox-circle-fill"></i> Present
                        </div>
                        <div
                            style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.8rem; background: #fff1f2; border-radius: 8px; font-size: 0.75rem; font-weight: 800; color: #e11d48;">
                            <i class="ri-close-circle-fill"></i> Absent
                        </div>
                    </div>
                </div>
                <div style="height: 350px;">
                    <canvas id="attendanceGraph"></canvas>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Shared Chart Options
        const sharedOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1e293b',
                    bodyColor: '#64748b',
                    borderColor: '#f1f5f9',
                    borderWidth: 1,
                    padding: 12,
                    boxPadding: 6,
                    usePointStyle: true,
                    titleFont: { family: 'Outfit', size: 14, weight: 'bold' },
                    bodyFont: { family: 'Outfit', size: 13 }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { family: 'Outfit', size: 12, weight: '600' }, color: '#94a3b8' }
                },
                y: {
                    grid: { color: '#f1f5f9', borderDash: [5, 5] },
                    ticks: { font: { family: 'Outfit', size: 12, weight: '600' }, color: '#94a3b8' }
                }
            }
        };

        // Revenue Graph - Dynamic Data
        const ctxRevenue = document.getElementById('revenueGraph').getContext('2d');
        const revenueGradient = ctxRevenue.createLinearGradient(0, 0, 0, 300);
        revenueGradient.addColorStop(0, 'rgba(16, 185, 129, 0.1)');
        revenueGradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

        // Extract revenue and expense data from PHP
        const monthlyData = <?= json_encode($monthlyRevenueData) ?>;
        const revenueDataPoints = monthlyData.map(item => item.revenue);
        const expenseDataPoints = monthlyData.map(item => item.expense);

        new Chart(ctxRevenue, {
            type: 'line',
            data: {
                labels: <?= json_encode($monthlyLabels) ?>,
                datasets: [
                    {
                        label: 'Revenue',
                        data: revenueDataPoints,
                        borderColor: '#10b981',
                        borderWidth: 4,
                        pointBackgroundColor: 'white',
                        pointBorderColor: '#10b981',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.4,
                        fill: true,
                        backgroundColor: revenueGradient
                    },
                    {
                        label: 'Expenses',
                        data: expenseDataPoints,
                        borderColor: '#ef4444',
                        borderWidth: 3,
                        borderDash: [5, 5],
                        pointRadius: 0,
                        tension: 0.4,
                        fill: false
                    }
                ]
            },
            options: sharedOptions
        });

        // Attendance Graph - Day-wise Attendance
        const ctxAttendance = document.getElementById('attendanceGraph').getContext('2d');
        const dayWiseData = <?= json_encode($dayWiseAttendance) ?>;
        const dayLabels = <?= json_encode($dayLabels) ?>;

        const presentData = dayWiseData.map(item => item.present);
        const absentData = dayWiseData.map(item => item.absent);

        new Chart(ctxAttendance, {
            type: 'bar',
            data: {
                labels: dayLabels,
                datasets: [
                    {
                        label: 'Present',
                        data: presentData,
                        backgroundColor: '#10b981',
                        borderRadius: 8,
                        barThickness: 25
                    },
                    {
                        label: 'Absent',
                        data: absentData,
                        backgroundColor: '#ef4444',
                        borderRadius: 8,
                        barThickness: 25
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.95)',
                        titleColor: '#1e293b',
                        bodyColor: '#64748b',
                        borderColor: '#f1f5f9',
                        borderWidth: 1,
                        padding: 12,
                        boxPadding: 6,
                        usePointStyle: true,
                        titleFont: { family: 'Outfit', size: 14, weight: 'bold' },
                        bodyFont: { family: 'Outfit', size: 13 }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Outfit', size: 12, weight: '600' }, color: '#94a3b8' }
                    },
                    y: {
                        grid: { color: '#f1f5f9', borderDash: [5, 5] },
                        ticks: { font: { family: 'Outfit', size: 12, weight: '600' }, color: '#94a3b8' },
                        beginAtZero: true
                    }
                }
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>