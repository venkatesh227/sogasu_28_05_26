<?php
session_start();
include '../includes/db.php';

$pageTitle = "Employee Devices - Sogasu";
$activePage = "employee-devices";

/*
|--------------------------------------------------------------------------
| FETCH EMPLOYEE DEVICE DETAILS
|--------------------------------------------------------------------------
|
| Fetch:
| - Employee Name
| - Device Token
*/
/*
|--------------------------------------------------------------------------
| UPDATE DEVICE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_device'])) {

    $id = (int) $_POST['employee_id'];
    $device_token = trim($_POST['device_token']);

    $stmt = $pdo->prepare("
        UPDATE users
        SET 
            device_token = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $device_token,
        $id
    ]);

    $_SESSION['success_message'] = "Employee device updated successfully.";

    header("Location: employee-devices.php");
    exit;
}

$stmt = $pdo->query("
    SELECT 
        id,
        username,
        device_token
    FROM users
    WHERE role = 'employee'
    ORDER BY id DESC
");

$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<main class="main-content">

    <?php include 'includes/topbar.php'; ?>

    <div>

        <!-- Page Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">

            <div>
                <h2 style="font-size:1.5rem; font-weight:700; color:#1e293b; margin:0;">
                    Employee Devices
                </h2>

                <p style="color:#64748b; margin-top:0.25rem;">
                    View employee login devices and manage device access.
                </p>
            </div>

        </div>

        <!-- Table Container -->
        <div class="table-container" style="padding:1.5rem;">

            <div style="padding-bottom:1rem; border-bottom:1px solid #f1f5f9; margin-bottom:1rem;">
                <h3 style="font-size:1.05rem; font-weight:700; color:#1e293b; margin:0;">
                    Employee Device List
                </h3>
            </div>

            <div style="overflow-x:auto;">

                <table class="table">

                    <thead>
                        <tr>
                            <th>Employee Name</th>
                            <th>Device</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (!empty($employees)): ?>

                            <?php foreach ($employees as $row): ?>

                                <tr>

                                    <!-- Employee Name -->
                                    <td>
                                        <div style="font-weight:700; color:#1e293b;">
                                            <?= htmlspecialchars($row['username']) ?>
                                        </div>
                                    </td>

                                    <!-- Device -->
                                    <td>

                                        <?php if (!empty($row['device_token'])): ?>

                                            <div style="
                                max-width:300px;
                                overflow:hidden;
                                text-overflow:ellipsis;
                                white-space:nowrap;
                                font-size:0.85rem;
                                color:#334155;
                            ">
                                                <?= htmlspecialchars($row['device_token']) ?>
                                            </div>

                                        <?php else: ?>

                                            <span style="color:#94a3b8;">
                                                No Device Found
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <!-- Action -->
                                    <td style="text-align:right;">

                                        <button type="button" class="openEditModal" data-id="<?= $row['id'] ?>"
                                            data-name="<?= htmlspecialchars($row['username']) ?>"
                                            data-device="<?= htmlspecialchars($row['device_token']) ?>" style="
                                                width:36px;
                                                height:36px;
                                                border-radius:8px;
                                                background:rgba(79,70,229,0.1);
                                                color:#4f46e5;
                                                display:inline-flex;
                                                align-items:center;
                                                justify-content:center;
                                                text-decoration:none;
                                                transition:0.2s;
                                                border:none;
                                                cursor:pointer;
                                            ">
                                            <i class="ri-pencil-line"></i>
                                        </button>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="3" style="text-align:center; padding:2rem; color:#64748b;">
                                    No employee devices found.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</main>
<!-- Edit Device Modal -->
<!-- Edit Device Modal -->
<div id="editDeviceModal" style="
    position:fixed;
    inset:0;
    background:rgba(15,23,42,0.55);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:9999;
    padding:20px;
">

    <div style="
        width:100%;
        max-width:440px;
        border-radius:18px;
        overflow:hidden;
        background:#fff;
        box-shadow:0 20px 50px rgba(0,0,0,0.18);
        animation:popupFade 0.2s ease;
    ">

        <!-- Modal Header -->
        <div style="
            background:linear-gradient(135deg,#5b4cf0,#4b3fe0);
            padding:20px 24px;
            display:flex;
            align-items:center;
            justify-content:space-between;
        ">

            <h3 style="
                margin:0;
                color:#fff;
                font-size:1.65rem;
                font-weight:700;
            ">
                Edit Employee Device
            </h3>

            <button
                type="button"
                id="closeModal"
                style="
                    background:none;
                    border:none;
                    color:#fff;
                    font-size:1.7rem;
                    cursor:pointer;
                    line-height:1;
                "
            >
                ×
            </button>

        </div>

        <!-- Modal Body -->
        <div style="padding:24px;">

            <form method="POST">

                <input type="hidden" name="employee_id" id="employee_id">

                <!-- Employee Name -->
                <div style="margin-bottom:20px;">

                    <label style="
                        display:block;
                        margin-bottom:8px;
                        font-size:15px;
                        font-weight:600;
                        color:#1e293b;
                    ">
                        Employee Name
                    </label>

                    <input
                        type="text"
                        id="employee_name"
                        readonly
                        style="
                            width:100%;
                            height:50px;
                            border:1px solid #dbe2ea;
                            border-radius:10px;
                            padding:0 16px;
                            font-size:15px;
                            background:#f8fafc;
                            color:#64748b;
                            outline:none;
                        "
                    >

                </div>

                <!-- Device Name -->
                <div style="margin-bottom:26px;">

                    <label style="
                        display:block;
                        margin-bottom:8px;
                        font-size:15px;
                        font-weight:600;
                        color:#1e293b;
                    ">
                        Device Name
                    </label>

                    <input
                        type="text"
                        name="device_token"
                        id="device_token"
                        style="
                            width:100%;
                            height:50px;
                            border:1px solid #dbe2ea;
                            border-radius:10px;
                            padding:0 16px;
                            font-size:15px;
                            background:#fff;
                            color:#1e293b;
                            outline:none;
                        "
                    >

                </div>

                <!-- Buttons -->
                <div style="
                    display:flex;
                    gap:12px;
                ">

                    <!-- Update -->
                    <button
                        type="submit"
                        name="update_device"
                        style="
                            flex:1;
                            height:50px;
                            border:none;
                            border-radius:10px;
                            background:linear-gradient(135deg,#5b4cf0,#4b3fe0);
                            color:#fff;
                            font-size:15px;
                            font-weight:600;
                            cursor:pointer;
                        "
                    >
                        Update Device
                    </button>

                    <!-- Cancel -->
                    <button
                        type="button"
                        id="cancelModal"
                        style="
                            width:120px;
                            height:50px;
                            border:none;
                            border-radius:10px;
                            background:#eef2f7;
                            color:#475569;
                            font-size:15px;
                            font-weight:600;
                            cursor:pointer;
                        "
                    >
                        Cancel
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>
<script>

    const modal = document.getElementById('editDeviceModal');

    document.querySelectorAll('.openEditModal').forEach(button => {

        button.addEventListener('click', function () {

            document.getElementById('employee_id').value = this.dataset.id;
            document.getElementById('employee_name').value = this.dataset.name;
            document.getElementById('device_token').value = this.dataset.device;

            modal.style.display = 'flex';
        });

    });

    document.getElementById('closeModal').addEventListener('click', function () {
        modal.style.display = 'none';
    });

    document.getElementById('cancelModal').addEventListener('click', function () {
        modal.style.display = 'none';
    });

    window.addEventListener('click', function (e) {

        if (e.target === modal) {
            modal.style.display = 'none';
        }

    });

</script>
<style>
    @keyframes popupFade {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<?php include 'includes/footer.php'; ?>