<?php
session_start();
require_once '../includes/db.php';
$selected_date = $_GET['date'] ?? date('Y-m-d');

// validate date format
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $selected_date)) {
    $selected_date = date('Y-m-d');
}

// prevent past date
if (strtotime($selected_date) < strtotime(date('Y-m-d'))) {
    $selected_date = date('Y-m-d');
}

$stmt = $pdo->prepare("
    SELECT * FROM appointments 
    WHERE is_deleted = 0 
    AND appointment_date = ?
    ORDER BY appointment_time ASC
");
$stmt->execute([$selected_date]);
$appointments = $stmt->fetchAll();
$pageTitle = "Appointments - Sogasu";
$activePage = "appointments";
include 'includes/header.php';
?>

<main class="main-content">
    <header class="top-header"
        style="justify-content: space-between; gap: 1rem; flex-wrap: nowrap; align-items: center;">
        <i class="ri-menu-line mobile-toggle" onclick="toggleSidebar()"></i>

        <!-- Left Section: Search & Filters -->
        <div style="display: flex; align-items: center; gap: 1rem; flex: 1;">
            <div class="search-bar" style="width: 220px; min-width: 200px;">
                <i class="ri-search-line"></i>
                <input type="text" placeholder="Search appointments...">
            </div>
        </div>

        <!-- Right Section: User Profile -->
        <div class="user-profile-corner" style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 0;">
            <div style="display: flex; gap: 0.75rem;">
                <button
                    style="border: none; background: transparent; color: #94a3b8; font-size: 1.2rem; cursor: pointer;"><i
                        class="ri-notification-3-line"></i></button>
                <button
                    style="border: none; background: transparent; color: #94a3b8; font-size: 1.2rem; cursor: pointer;"><i
                        class="ri-settings-line"></i></button>
            </div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="profile-info" style="text-align: right;">
                    <div class="profile-name" style="font-weight: 700; color: #0f172a; font-size: 0.9rem;">Sushmita
                    </div>
                    <div class="profile-role" style="font-size: 0.75rem; color: #64748b; text-transform: uppercase;">
                        Admin</div>
                </div>
                <img src="https://ui-avatars.com/api/?name=Sushmita+A&background=random"
                    style="width: 40px; height: 40px; border-radius: 50%;">
            </div>
        </div>
    </header>

    <div style="margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Appointments</h2>
                <p class="text-muted">Manage trials, consultations and measurements</p>
            </div>
            <button class="btn btn-primary" onclick="window.location.href='add-appointment.php'"><i
                    class="ri-calendar-check-line"></i> New Appointment</button>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 1.5rem;">

        <!-- Appointments List -->
        <div style="display: flex; flex-direction: column; gap: 1rem;">

            <!-- Section Header -->
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b;">Upcoming Schedule</h3>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn-filter active">Upcoming</button>
                    <button class="btn-filter">History</button>
                </div>
            </div>
            <?php foreach ($appointments as $row): ?>
                <div class="appointment-card">
                    <div class="appointment-date-box">
                        <div class="month"><?= strtoupper(date('M', strtotime($row['appointment_date']))) ?></div>
                        <div class="day"><?= date('d', strtotime($row['appointment_date'])) ?></div>
                    </div>

                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <h4 class="appointment-title">
                                    <?= ucfirst(str_replace('_', ' ', $row['type'])) ?> -
                                    <?= htmlspecialchars($row['customer_name']) ?>
                                </h4>

                                <div class="appointment-meta">
                                    <span>
                                        <i class="ri-time-line"></i>
                                        <?= date('h:i A', strtotime($row['appointment_time'])) ?> -
                                        <?= date('h:i A', strtotime($row['appointment_time'] . ' +15 minutes')) ?>
                                    </span>

                                    <span>
                                        <i class="ri-user-line"></i>
                                        <?= htmlspecialchars($row['customer_phone'] ?: 'No phone') ?>
                                    </span>
                                </div>
                            </div>

                            <span
                                class="badge 
                                <?= $row['type'] == 'trial' ? 'badge-orange' : ($row['type'] == 'delivery_pickup' ? 'badge-green' : 'badge-blue') ?>">
                                <?= ucfirst(str_replace('_', ' ', $row['type'])) ?>
                            </span>
                        </div>
                    </div>

                    <button class="btn-icon-only action-btn" data-id="<?= $row['id'] ?>">
                        <i class="ri-more-2-fill"></i>
                    </button>
                </div>
            <?php endforeach; ?>
            <?php if (empty($appointments)): ?>
                <p>No appointments found</p>
            <?php endif; ?>
        </div>

        <!-- Sidebar: Calendar & Stats -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">

            <!-- Calendar Widget -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #1e293b;">
                        <?= date('F Y', strtotime($selected_date)) ?>
                    </h3>
                    <div style="display: flex; gap: 0.5rem;">
                        <i class="ri-arrow-left-s-line" style="cursor: pointer;"></i>
                        <i class="ri-arrow-right-s-line" style="cursor: pointer;"></i>
                    </div>
                </div>
                <div class="calendar-grid">
                    <div class="day-name">S</div>
                    <div class="day-name">M</div>
                    <div class="day-name">T</div>
                    <div class="day-name">W</div>
                    <div class="day-name">T</div>
                    <div class="day-name">F</div>
                    <div class="day-name">S</div>
                    <!-- Empty slots -->
                    <div class="day empty"></div>
                    <div class="day empty"></div>
                    <div class="day empty"></div>
                    <?php
                    $daysInMonth = date('t', strtotime($selected_date));
                    $currentMonth = date('Y-m', strtotime($selected_date));

                    // get booked days (ONLY for highlight)
                    $allAppointments = $pdo->query("
                        SELECT appointment_date FROM appointments 
                        WHERE is_deleted = 0
                    ")->fetchAll();

                    $bookedDays = array_map(function ($a) {
                        return $a['appointment_date'];
                    }, $allAppointments);
                    ?>

                    <?php for ($d = 1; $d <= $daysInMonth; $d++):

                        $date = $currentMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                        $isPast = strtotime($date) < strtotime(date('Y-m-d'));
                        ?>

                        <div class="day 
                        <?= ($date == $selected_date) ? 'active' : '' ?> 
                        <?= in_array($date, $bookedDays) ? 'has-booking' : '' ?> 
                        <?= $isPast ? 'empty' : '' ?>" <?= !$isPast ? "onclick=\"window.location.href='?date=$date'\"" : '' ?>>
                            <?= $d ?>
                        </div>

                    <?php endfor; ?>
                </div>
                <div style="margin-top: 1rem; font-size: 0.75rem; color: #64748b;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                        <span style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></span>
                        Booked Slots
                    </div>
                </div>
            </div>

            <!-- Today's Summary -->
            <div
                style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; text-align: center;">
                <h3
                    style="font-size: 0.9rem; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">
                    Today's Count</h3>
                <div style="font-size: 3.5rem; font-weight: 700; color: var(--primary); line-height: 1;"><?= count(array_filter($appointments, function ($a) use ($selected_date) {
                    return $a['appointment_date'] == $selected_date;
                })) ?></div>
                <div style="color: #475569; font-size: 0.9rem; margin-top: 0.5rem;">Appointments Scheduled</div>
            </div>

        </div>

    </div>

</main>

<style>
    .appointment-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1.25rem;
        display: flex;
        gap: 1.25rem;
        align-items: center;
        transition: box-shadow 0.2s;
    }

    .appointment-card:hover {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .appointment-date-box {
        text-align: center;
        padding: 0.75rem 0.5rem;
        background: #f8fafc;
        border-radius: 8px;
        min-width: 70px;
        border: 1px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .appointment-date-box .month {
        font-size: 0.75rem;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .appointment-date-box .day {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
        line-height: 1;
    }

    .appointment-title {
        font-size: 1.05rem;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.35rem;
    }

    .appointment-meta {
        font-size: 0.85rem;
        color: #64748b;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .appointment-meta span {
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    .badge {
        padding: 0.25rem 0.75rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .badge-blue {
        background: #eef2ff;
        color: #4338ca;
    }

    .badge-orange {
        background: #fff7ed;
        color: #c2410c;
    }

    .badge-green {
        background: #f0fdf4;
        color: #15803d;
    }

    .btn-icon-only {
        background: transparent;
        border: none;
        color: #94a3b8;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 50%;
        transition: background 0.2s;
        align-self: flex-start;
    }

    .btn-icon-only:hover {
        background: #f1f5f9;
        color: #475569;
    }

    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 4px;
        text-align: center;
    }

    .day-name {
        font-size: 0.7rem;
        font-weight: 700;
        color: #94a3b8;
        padding-bottom: 0.5rem;
    }

    .day {
        font-size: 0.8rem;
        padding: 0.5rem 0;
        border-radius: 4px;
        color: #475569;
        cursor: pointer;
    }

    .day:hover {
        background: #f1f5f9;
    }

    .day.active {
        background: var(--primary);
        color: white !important;
    }

    .day.has-booking:not(.active) {
        font-weight: 700;
        color: var(--primary);
        text-decoration: underline;
    }

    .day.empty {
        cursor: default;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if (!empty($_SESSION['success'])): ?>
    <script>
        let msg = "Appointment created";

        if ("<?= $_SESSION['success'] ?>" === "updated") msg = "Appointment updated";
        if ("<?= $_SESSION['success'] ?>" === "deleted") msg = "Appointment deleted";

        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: msg
        });
    </script>
    <?php unset($_SESSION['success']); endif; ?>
<script>
    document.querySelectorAll('.action-btn').forEach(btn => {

        btn.addEventListener('click', function (e) {
            e.stopPropagation(); // IMPORTANT

            let id = this.dataset.id;

            Swal.fire({
                title: 'Choose Action',
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: 'Edit',
                denyButtonText: 'Delete',
            }).then((result) => {

                if (result.isConfirmed) {
                    window.location.href = 'add-appointment.php?id=' + id;
                }

                if (result.isDenied) {

                    Swal.fire({
                        title: 'Are you sure?',
                        text: "This appointment will be deleted",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete it!'
                    }).then((res) => {

                        if (res.isConfirmed) {
                            window.location.href = 'delete-appointment.php?id=' + id;
                        }

                    });
                }

            });

        });

    });
</script>

<?php include 'includes/footer.php'; ?>