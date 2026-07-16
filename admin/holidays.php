<?php
session_start();
include '../includes/db.php';

// Handle AJAX: Add Holiday
if (isset($_POST['action']) && $_POST['action'] === 'add_holiday') {
    header('Content-Type: application/json');
    try {
        $name = $_POST['name'];
        $date = $_POST['holiday_date'];
        $date = date(
            'Y-m-d',
            strtotime($date)
        );
        $type = $_POST['type'];
        $color = $_POST['color'];

        $stmt = $pdo->prepare("
    INSERT INTO holidays
    (name, holiday_date, type, color)
    VALUES (?, ?, ?, ?)
");

        $stmt->execute([
            $name,
            $date,
            $type,
            $color
        ]);

        /*
   |--------------------------------------------------------------------------
   | FIND AFFECTED APPOINTMENTS
   |--------------------------------------------------------------------------
   */

        $affectedStmt = $pdo->prepare("

            SELECT
                id,
                user_id,
                appointment_date,
                appointment_time
            FROM appointments

            WHERE appointment_date = ?
            AND status = 'scheduled'

        ");

        $affectedStmt->execute([
            $date
        ]);

        $affectedAppointments = $affectedStmt->fetchAll();

        $cancelAppointmentStmt = $pdo->prepare("

            UPDATE appointments
            SET status = 'cancelled'
            WHERE id = ?

        ");
        /*
        |--------------------------------------------------------------------------
        | SEND NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

        foreach ($affectedAppointments as $appointment) {
            $cancelAppointmentStmt->execute([
                $appointment['id']
            ]);

            /*
            |--------------------------------------------------------------------------
            | SKIP IF USER NOT FOUND
            |--------------------------------------------------------------------------
            */

            if (empty($appointment['user_id'])) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | INSERT NOTIFICATION
            |--------------------------------------------------------------------------
            */

            $notifyStmt = $pdo->prepare("

            INSERT INTO appointment_notifications
            (
                user_id,
                notification_type,
                title,
                message,
                status,
                created_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                NOW()
            )

    ");

            $notifyStmt->execute([

                $appointment['user_id'],

                'Holiday Cancellation',

                'Appointment Rebooking Required',

                'Your appointment on ' .
                $appointment['appointment_date'] .
                ' at ' .
                date(
                    'h:i A',
                    strtotime($appointment['appointment_time'])
                ) .
                ' was cancelled due to a holiday. Please rebook another slot.',

                'pending'

            ]);


        }

        echo json_encode([
            'success' => true
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle AJAX: Fetch Holidays for FullCalendar
if (isset($_GET['action']) && $_GET['action'] === 'get_holidays') {
    header('Content-Type: application/json');
    $stmt = $pdo->query("SELECT id, name as title, holiday_date as start, color FROM holidays");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

$pageTitle = "Holidays - Sogasu";
$activePage = "holidays";
include 'includes/header.php';
?>

<!-- FullCalendar CSS -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />

<style>
    :root {
        --fc-border-color: #e2e8f0;
        --fc-today-bg-color: #f0f9ff;
        --fc-button-bg-color: #ffffff;
        --fc-button-border-color: #cbd5e1;
        --fc-button-text-color: #475569;
        --fc-button-hover-bg-color: #f8fafc;
        --fc-button-active-bg-color: var(--primary);
        --fc-button-active-text-color: #ffffff;
    }

    .fc .fc-toolbar-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1e293b;
    }

    .fc .fc-button-primary {
        background: var(--fc-button-bg-color);
        border-color: var(--fc-button-border-color);
        color: var(--fc-button-text-color);
        text-transform: capitalize;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s;
    }

    .fc .fc-button-primary:hover {
        background: var(--fc-button-hover-bg-color) !important;
        border-color: #cbd5e1 !important;
        color: #1e293b !important;
    }

    .fc .fc-button-primary:not(:disabled).fc-button-active,
    .fc .fc-button-primary:not(:disabled):active {
        background: var(--fc-button-active-bg-color) !important;
        border-color: var(--fc-button-active-bg-color) !important;
        color: var(--fc-button-active-text-color) !important;
    }

    .fc .fc-col-header-cell-cushion {
        padding: 10px;
        font-weight: 700;
        color: #1e293b;
        font-size: 0.9rem;
    }

    .fc-daygrid-day-number {
        font-size: 0.85rem;
        color: #94a3b8;
        padding: 8px !important;
    }

    .fc-event {
        border: none;
        padding: 2px 5px;
        border-radius: 4px;
        font-weight: 600;
        font-size: 0.75rem;
    }
</style>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="display: flex; flex-direction: column; gap: 1.5rem; width: 100%; max-width: 100%;">

        <!-- Premium Header Area -->
        <div
            style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 0.5rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Holiday Calendar</h2>
                <p style="color: #64748b; margin-top: 0.25rem; margin-bottom: 0;">Configure official public, company,
                    and optional holidays.</p>
            </div>

            <div style="display: flex; gap: 0.75rem; align-items: center;">
                <button onclick="showAddHolidayModal()" class="btn btn-primary"
                    style="padding: 10px 20px; border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <i class="ri-add-line"></i> Add Holiday
                </button>
                <button class="btn btn-secondary"
                    style="background: white; border: 1px solid #cbd5e1; color: #475569; padding: 10px 20px; border-radius: 8px; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <i class="ri-check-double-line"></i> Mark Default
                </button>
            </div>
        </div>

        <!-- Calendar Card -->
        <div class="glass-card"
            style="padding: 1.5rem; border-radius: 16px; border: 1px solid #e2e8f0; background: white; box-shadow: var(--shadow-sm);">
            <div id='calendar'></div>
        </div>

    </div>
</main>

<!-- Premium Add Holiday Modal -->
<div id="holidayModal" class="premium-modal-overlay">
    <div class="glass-card premium-modal-content"
        style="max-width: 440px; border-radius: 16px; border: 1px solid #e2e8f0; padding: 1.5rem; background: white; box-shadow: var(--shadow-lg);">
        <div
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-dark);">Add New Holiday</h3>
            <button onclick="closeAddHolidayModal()"
                style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 4px; border-radius: 50%; transition: all 0.2s;"><i
                    class="ri-close-line"></i></button>
        </div>

        <form id="holidayForm" onsubmit="event.preventDefault(); submitHoliday()">
            <div style="margin-bottom: 1.25rem;">
                <label
                    style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Holiday
                    Name <span style="color: #ef4444;">*</span></label>
                <input type="text" name="name" required placeholder="e.g. Independence Day"
                    style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Roboto', sans-serif; font-size: 0.9rem; outline: none; background: #fafbfc; transition: all 0.2s;"
                    onfocus="this.style.borderColor='var(--primary)'; this.style.background='white';">
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label
                    style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Date
                    <span style="color: #ef4444;">*</span></label>
                <input type="date" name="holiday_date" required
                    style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Roboto', sans-serif; font-size: 0.9rem; outline: none; background: #fafbfc; transition: all 0.2s;"
                    onfocus="this.style.borderColor='var(--primary)'; this.style.background='white';">
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label
                        style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Type</label>
                    <select name="type"
                        style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Roboto', sans-serif; font-size: 0.9rem; outline: none; background: white; transition: all 0.2s;"
                        onfocus="this.style.borderColor='var(--primary)';">
                        <option value="Public">Public Holiday</option>
                        <option value="Company">Company Holiday</option>
                        <option value="Optional">Optional Holiday</option>
                    </select>
                </div>
                <div>
                    <label
                        style="display: block; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">Color</label>
                    <input type="color" name="color" value="#ef4444"
                        style="width: 100%; height: 41px; padding: 2px; border: 1px solid #cbd5e1; border-radius: 8px; cursor: pointer; background: white;">
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; border-top: 1px solid #f1f5f9; padding-top: 1.25rem;">
                <button type="button" onclick="closeAddHolidayModal()" class="btn btn-secondary"
                    style="flex: 1; padding: 10px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; background: #f1f5f9; color: var(--text-muted); border: none;">Cancel</button>
                <button type="submit" class="btn btn-primary"
                    style="flex: 1.5; padding: 10px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; color: white;">Save
                    Holiday</button>
            </div>
        </form>
    </div>
</div>

<style>
    .premium-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(8px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        animation: fadeIn 0.3s ease-out;
    }

    .premium-modal-content {
        width: 100%;
        animation: slideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px) scale(0.95);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
</style>

<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
            },
            events: 'holidays.php?action=get_holidays',
            height: 'auto',
            firstDay: 1, // Monday
            eventClick: function (info) {
                Swal.fire({
                    title: info.event.title,
                    text: 'Date: ' + info.event.start.toLocaleDateString(),
                    icon: 'info'
                });
            }
        });
        calendar.render();
    });

    function showAddHolidayModal() {
        document.getElementById('holidayModal').style.display = 'flex';
    }

    function closeAddHolidayModal() {
        document.getElementById('holidayModal').style.display = 'none';
    }

    function submitHoliday() {
        const form = document.getElementById('holidayForm');
        const formData = new FormData(form);
        formData.append('action', 'add_holiday');

        fetch('holidays.php', {
            method: 'POST',
            body: new URLSearchParams(formData)
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    Swal.fire('Error', data.error, 'error');
                }
            });
    }

    // Close modal on click outside
    window.onclick = function (event) {
        const modal = document.getElementById('holidayModal');
        if (event.target == modal) {
            closeAddHolidayModal();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>