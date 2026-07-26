<?php
session_start();
include '../includes/db.php';

$pageTitle = "Reports - Sogasu";
$activePage = "reports";

// ==================== REPORT PERIOD FILTER ====================
$selectedPeriod = $_GET['period'] ?? '1M';
$allowedPeriods = ['1W', '1M', '1Y'];

if (!in_array($selectedPeriod, $allowedPeriods, true)) {
    $selectedPeriod = '1M';
}

$periodConfig = [
    '1W' => ['start' => '-6 days', 'label' => 'Last 7 days'],
    '1M' => ['start' => '-29 days', 'label' => 'Last 30 days'],
    '1Y' => ['start' => '-364 days', 'label' => 'Last 12 months']
];

$filterStartDate = date('Y-m-d', strtotime($periodConfig[$selectedPeriod]['start']));
$filterEndDate = date('Y-m-d');
$selectedPeriodLabel = $periodConfig[$selectedPeriod]['label'];

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

// 3. Order collections for the selected dashboard period.
$collectionTotalStmt = $pdo->prepare("
    SELECT COALESCE(SUM(payment_amount), 0)
    FROM bill_payments
    WHERE is_deleted = 0
      AND DATE(payment_date) BETWEEN ? AND ?
");
$collectionTotalStmt->execute([$filterStartDate, $filterEndDate]);
$periodIncome = $collectionTotalStmt->fetchColumn();

// 4. Day-wise attendance for the selected period. Count each active in-house
// employee once from their check-in, regardless of how many punches they have.
// This remains accurate when the workforce grows beyond the current demo size.
$dayWiseAttendance = [];
$dayLabels = [];
$presentStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT al.employee_id)
    FROM attendance_logs al
    INNER JOIN employees e ON e.id = al.employee_id
    WHERE al.log_date = ?
      AND al.log_type = 'In'
      AND e.is_deleted = 0
      AND e.employee_type = 'inhouse'
");

for ($date = $filterStartDate; $date <= $filterEndDate; $date = date('Y-m-d', strtotime($date . ' +1 day'))) {
    $dayLabel = date('d M', strtotime($date));
    $dayLabels[] = $dayLabel;

    $presentStmt->execute([$date]);
    $presentCount = $presentStmt->fetchColumn();

    // Absent = Total inhouse - Present
    $absentCount = max(0, $inhouseEmployees - $presentCount);

    $dayWiseAttendance[] = [
        'present' => (int) $presentCount,
        'absent' => (int) $absentCount
    ];
}

// 5. Day-wise order collections for the selected period. The report intentionally
// stays day-wise for weekly, monthly, and yearly selections.
$dailyCollectionsData = [];
$dailyLabels = [];

$collectionsStmt = $pdo->prepare("
    SELECT DATE(payment_date) AS collection_date,
           COALESCE(SUM(payment_amount), 0) AS total_collection
    FROM bill_payments
    WHERE is_deleted = 0
      AND DATE(payment_date) BETWEEN ? AND ?
    GROUP BY DATE(payment_date)
    ORDER BY collection_date ASC
");
$collectionsStmt->execute([$filterStartDate, $filterEndDate]);
$collectionsByDay = $collectionsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Do not manufacture zero-value months. The chart's X and Y values should
// represent only real payments recorded from order collections.
foreach ($collectionsByDay as $dayKey => $collectionAmount) {
    $dailyLabels[] = date('d M', strtotime($dayKey));
    $dailyCollectionsData[] = (float) $collectionAmount;
}
$hasOrderCollections = !empty($dailyCollectionsData) && array_sum($dailyCollectionsData) > 0;

include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="padding: 1.5rem;">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <div>
                <h2
                    style="font-size: 1.5rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.5rem; letter-spacing: -0.02em;">
                    Business Intelligence</h2>
                <p style="color: var(--text-muted); font-size: 1rem; font-weight: 500;">Analyze your business
                    performance with advanced visual analytics.</p>
                <form method="get" action="reports.php"
                    style="display:flex; align-items:end; gap:0.65rem; flex-wrap:wrap; margin-top:1rem;">
                    <div>
                        <label for="period"
                            style="display:block; margin-bottom:0.35rem; font-size:0.75rem; color:#64748b; font-weight:700;">REPORT
                            PERIOD</label>
                        <select id="period" name="period"
                            style="background:white; border:1px solid #e2e8f0; padding:10px 12px; border-radius:8px; font-weight:600; color:#1e293b; cursor:pointer;">
                            <option value="1W" <?= $selectedPeriod === '1W' ? 'selected' : '' ?>>Weekly (Last 7 days)
                            </option>
                            <option value="1M" <?= $selectedPeriod === '1M' ? 'selected' : '' ?>>Monthly (Last 30 days)
                            </option>
                            <option value="1Y" <?= $selectedPeriod === '1Y' ? 'selected' : '' ?>>Yearly (Last 12 months)
                            </option>
                        </select>
                    </div>
                    <button type="submit"
                        style="background:#4f46e5; border:none; padding:10px 16px; border-radius:8px; font-weight:600; color:white; cursor:pointer;"><i
                            class="ri-filter-3-line"></i> Apply Filter</button>
                    <a href="reports.php"
                        style="background:white; border:1px solid #e2e8f0; padding:10px 16px; border-radius:8px; font-weight:600; color:#1e293b; text-decoration:none;"><i
                            class="ri-refresh-line"></i> Reset</a>
                    <span style="font-size:0.8rem; color:#64748b; padding-bottom:0.6rem;">Showing: <strong
                            style="color:#334155;"><?= htmlspecialchars($selectedPeriodLabel) ?></strong></span>
                </form>
            </div>

            <div style="display:flex; align-items:center; gap:0.75rem;">
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

            <!-- Card 4: Order Collections for Selected Period -->
            <div class="table-container" style="padding:1.25rem; border-radius:16px;">

                <div style="display:flex; justify-content:space-between; align-items:center;">

                    <div>
                        <div style="font-size:0.75rem; color:#64748b; font-weight:700; text-transform:uppercase;">
                            Order Collections
                        </div>

                        <div style="font-size:1.8rem; font-weight:800; margin-top:0.5rem; color:#ef4444;">
                            ₹ <?= number_format($periodIncome, 0) ?>
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
            <!-- Order Collections Graph -->
            <div class="table-container" style="padding:1.5rem; border-radius:16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <div>
                        <h3
                            style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.25rem;">
                            Order Collections</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">Day-wise payments
                            collected from orders &mdash; <?= htmlspecialchars($selectedPeriodLabel) ?></p>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <div
                            style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.8rem; background: #f0fdf4; border-radius: 8px; font-size: 0.75rem; font-weight: 800; color: #16a34a;">
                            Order Collections
                        </div>
                    </div>
                </div>
                <div style="height: 350px;">
                    <?php if ($hasOrderCollections): ?>
                        <canvas id="orderCollectionsGraph"></canvas>
                    <?php else: ?>
                        <div
                            style="height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:0.65rem; color:#64748b; text-align:center;">
                            <i class="ri-file-list-3-line" style="font-size:2rem; color:#94a3b8;"></i>
                            <strong style="color:#475569;">No order collections recorded</strong>
                            <span style="font-size:0.85rem;">Payments collected for orders will appear here
                                automatically.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Attendance for Selected Period Graph -->
            <div class="table-container" style="padding:1.5rem; border-radius:16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <div>
                        <h3
                            style="font-size: 1.25rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.25rem;">
                            Attendance</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">Daily present vs
                            absent &mdash; <?= htmlspecialchars($selectedPeriodLabel) ?></p>
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

        // Only render a chart when the Payments source has real collections.
        const collectionsCanvas = document.getElementById('orderCollectionsGraph');
        if (collectionsCanvas) {
            const ctxCollections = collectionsCanvas.getContext('2d');
            const collectionsGradient = ctxCollections.createLinearGradient(0, 0, 0, 300);
            collectionsGradient.addColorStop(0, 'rgba(16, 185, 129, 0.18)');
            collectionsGradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

            new Chart(ctxCollections, {
                type: 'line',
                data: {
                    labels: <?= json_encode($dailyLabels) ?>,
                    datasets: [{
                        label: 'Order Collections',
                        data: <?= json_encode($dailyCollectionsData) ?>,
                        borderColor: '#10b981',
                        borderWidth: 4,
                        pointBackgroundColor: 'white',
                        pointBorderColor: '#10b981',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.4,
                        fill: true,
                        backgroundColor: collectionsGradient
                    }]
                },
                options: sharedOptions
            });
        }

        // Attendance Graph - Day-wise Attendance
        const ctxAttendance = document.getElementById('attendanceGraph').getContext('2d');
        const dayWiseData = <?= json_encode($dayWiseAttendance) ?>;
        const dayLabels = <?= json_encode($dayLabels) ?>;

        const presentData = dayWiseData.map(item => item.present);
        const absentData = dayWiseData.map(item => item.absent);
        const activeInhouseEmployees = <?= (int) $inhouseEmployees ?>;

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
                        maxBarThickness: 36
                    },
                    {
                        label: 'Absent',
                        data: absentData,
                        backgroundColor: '#ef4444',
                        borderRadius: 8,
                        maxBarThickness: 36
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
                        ticks: {
                            font: { family: 'Outfit', size: 12, weight: '600' },
                            color: '#94a3b8',
                            precision: 0
                        },
                        beginAtZero: true,
                        max: activeInhouseEmployees || undefined
                    }
                }
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>