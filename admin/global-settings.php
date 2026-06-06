<?php
ob_start();
session_start();
include '../includes/db.php';
$errors = [];

$from_date = '';
$to_date = '';
$hourly_rate = '';

$timing_errors = [];
$effective_from = '';
$start_time = '';
$end_time = '';

// Handle Add Rate Range
if (isset($_POST['action']) && $_POST['action'] === 'add_rate') {
    $from_date = trim($_POST['from_date'] ?? '');
    $to_date = trim($_POST['to_date'] ?? '');
$ot_percentage = trim($_POST['ot_percentage'] ?? '');
    if (empty($from_date)) {
        $errors['from_date'] = 'From Date is required';
    }

    if (empty($to_date)) {
        $errors['to_date'] = 'To Date is required';
    }

    if (empty($ot_percentage)) {
    $errors['ot_percentage'] = 'OT Percentage is required';
}

    if (empty($errors)) {

        $stmt = $pdo->prepare("INSERT INTO ot_rate_settings
(from_date, to_date, ot_percentage)
VALUES (?, ?, ?)");
$stmt->execute([
    $from_date,
    $to_date,
    $ot_percentage
]);
        header("Location: global-settings.php?success=1");
        exit;
    }
}
/*
|--------------------------------------------------------------------------
| ADD BOUTIQUE TIMINGS
|--------------------------------------------------------------------------
*/

if (isset($_POST['action']) && $_POST['action'] === 'add_timing') {

    $effective_from = trim($_POST['effective_from'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | VALIDATIONS
    |--------------------------------------------------------------------------
    */

    if (empty($effective_from)) {
        $timing_errors['effective_from'] = 'Effective date is required';
    }

    if (empty($start_time)) {
        $timing_errors['start_time'] = 'Start time is required';
    }

    if (empty($end_time)) {
        $timing_errors['end_time'] = 'End time is required';
    }

    /*
    |--------------------------------------------------------------------------
    | START < END VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        !empty($start_time) &&
        !empty($end_time) &&
        strtotime($start_time) >= strtotime($end_time)
    ) {

        $timing_errors['end_time'] =
            'End time must be greater than start time';
    }

    /*
    |--------------------------------------------------------------------------
    | DUPLICATE DATE VALIDATION
    |--------------------------------------------------------------------------
    */

    if (!empty($effective_from)) {

        $checkStmt = $pdo->prepare("
            SELECT id
            FROM boutique_timing_settings
            WHERE effective_from = ?
        ");

        $checkStmt->execute([$effective_from]);

        if ($checkStmt->fetch()) {

            $timing_errors['effective_from'] =
                'Timing already exists for this date';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE
    |--------------------------------------------------------------------------
    */

    if (empty($timing_errors)) {

        $stmt = $pdo->prepare("
            INSERT INTO boutique_timing_settings
            (
                effective_from,
                start_time,
                end_time,
                created_at
            )
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([

            $effective_from,
            $start_time,
            $end_time,
            date('Y-m-d H:i:s')

        ]);

        header("Location: global-settings.php?timing_success=1");
        exit;
    }
}
    
if (isset($_POST['action']) && $_POST['action'] === 'delete_rate') {
            $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM ot_rate_settings WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: global-settings.php?deleted=1");
        exit;
    }
/*
|--------------------------------------------------------------------------
| DELETE BOUTIQUE TIMING
|--------------------------------------------------------------------------
*/

if (
    isset($_POST['action']) &&
    $_POST['action'] === 'delete_timing'
) {

    $id = $_POST['id'];

    $stmt = $pdo->prepare("
        DELETE FROM boutique_timing_settings
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    header("Location: global-settings.php?timing_deleted=1");
    exit;
}


$rates = $pdo->query("SELECT * FROM ot_rate_settings ORDER BY from_date DESC")->fetchAll();
/*
|--------------------------------------------------------------------------
| FETCH BOUTIQUE TIMINGS
|--------------------------------------------------------------------------
*/

$timings = $pdo->query("
    SELECT *
    FROM boutique_timing_settings
    ORDER BY effective_from DESC
")->fetchAll();

$pageTitle = "OT Settings - Sogasu";
$activePage = "global-settings";
include 'includes/header.php';
?>

<main class="main-content" style="overflow-y: auto !important; height: 100vh;">
    <?php include 'includes/topbar.php'; ?>

    <div style="margin-bottom: 2rem;">
        <div style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">OT Settings</h2>
            <p class="text-muted" style="margin: 0;">Define overtime percentage rates for specific date ranges. Payroll calculates OT as a percentage of the employee's salary for the selected period.</p>
        </div>

        <div style="display: grid; grid-template-columns: 350px 1fr; gap: 2rem; align-items: start;">
            <!-- Form -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                <h3 style="font-size: 1rem; font-weight: 700; color: #1e293b; margin-top: 0; margin-bottom: 1.25rem;">Add New Rate Period</h3>
<form method="POST" novalidate>
                        <input type="hidden" name="action" value="add_rate">
                    
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">From Date</label>
                        <input type="date" name="from_date"  style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <?php if(isset($errors['from_date'])): ?>
<div style="color:red;font-size:12px;margin-top:5px;">
    <?= $errors['from_date']; ?>
</div>
<?php endif; ?>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">To Date</label>
                        <input type="date" name="to_date" style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <?php if(isset($errors['to_date'])): ?>
<div style="color:red;font-size:12px;margin-top:5px;">
    <?= $errors['to_date']; ?>
</div>
<?php endif; ?>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">OT Percentage (%)</label>
<input
    type="number"
    name="ot_percentage"
    step="0.01"
    placeholder="e.g. 50%">
               <?php if(isset($errors['ot_percentage'])): ?>
<div style="color:red;font-size:12px;margin-top:5px;">
    <?= $errors['ot_percentage']; ?>
</div>
<?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-weight: 700;">Apply Rate Range</button>
                </form>
            </div>

            <!-- List -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
                <div style="padding: 1rem 1.5rem; border-bottom: 1px solid #f1f5f9; background: #fff;">
                    <h3 style="font-size: 1rem; font-weight: 700; color: #1e293b; margin: 0;">Applied Date Ranges</h3>
                </div>
                <div style="padding: 0;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8fafc; text-align: left; color: #64748b; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                <th style="padding: 1rem 1.5rem;">Period</th>
                                <th style="padding: 1rem 1.5rem;">Rate</th>
                                <th style="padding: 1rem 1.5rem; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rates)): ?>
                                <tr>
                                    <td colspan="3" style="padding: 2rem; text-align: center; color: #94a3b8;">No custom rates defined yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rates as $r): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 1rem 1.5rem;">
                                            <div style="font-weight: 600; color: #1e293b;">
                                                <?= date('d M', strtotime($r['from_date'])) ?> - <?= date('d M, Y', strtotime($r['to_date'])) ?>
                                            </div>
                                            <div style="font-size: 0.7rem; color: #94a3b8;">Active for these dates</div>
                                        </td>
                                        <td style="padding: 1rem 1.5rem;">
                                            <div style="font-size: 1.1rem; font-weight: 800; color: #059669;"><?= number_format($r['ot_percentage'], 2) ?>%</div>
                                            <div style="font-size: 0.7rem; color: #64748b;">salary percentage</div>
                                        </td>
                                        <td style="padding: 1rem 1.5rem; text-align: right;">
<form method="POST" style="display: inline;" class="delete-form">
                                                    <input type="hidden" name="action" value="delete_rate">
                                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                                <button type="submit" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1.2rem;" title="Delete Range">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div style="margin-top:2rem;">

    <div style="margin-bottom: 1.5rem;">

        <h2 style="
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        ">

            Boutique Timing Settings

        </h2>

        <p class="text-muted" style="margin:0;">

            Configure boutique appointment timings.

        </p>

    </div>

    <div style="
        display:grid;
        grid-template-columns:350px 1fr;
        gap:2rem;
        align-items:start;
    ">

        <!-- FORM -->

        <div style="
            background:white;
            border:1px solid #e2e8f0;
            border-radius:12px;
            padding:1.5rem;
        ">

            <h3 style="margin-top:0;margin-bottom:1rem;">

                Set Boutique Timings

            </h3>

            <form method="POST">

                <input
                    type="hidden"
                    name="action"
                    value="add_timing"
                >

                <!-- EFFECTIVE DATE -->

                <div style="margin-bottom:1rem;">

                    <label>

                        Effective From Date

                    </label>

                    <input
                        type="date"
                        name="effective_from"
                        value="<?= htmlspecialchars($effective_from) ?>"
                        style="
                            width:100%;
                            padding:0.7rem;
                            border:1px solid #e2e8f0;
                            border-radius:8px;
                        "
                    >

                    <?php if(isset($timing_errors['effective_from'])): ?>

                        <div style="
                            color:red;
                            font-size:12px;
                            margin-top:5px;
                        ">

                            <?= $timing_errors['effective_from']; ?>

                        </div>

                    <?php endif; ?>

                </div>

                <!-- START TIME -->

                <div style="margin-bottom:1rem;">

                    <label>

                        Start Time

                    </label>

                    <input
                        type="time"
                        name="start_time"
                        value="<?= htmlspecialchars($start_time) ?>"
                        style="
                            width:100%;
                            padding:0.7rem;
                            border:1px solid #e2e8f0;
                            border-radius:8px;
                        "
                    >

                    <?php if(isset($timing_errors['start_time'])): ?>

                        <div style="
                            color:red;
                            font-size:12px;
                            margin-top:5px;
                        ">

                            <?= $timing_errors['start_time']; ?>

                        </div>

                    <?php endif; ?>

                </div>

                <!-- END TIME -->

                <div style="margin-bottom:1rem;">

                    <label>

                        End Time

                    </label>

                    <input
                        type="time"
                        name="end_time"
                        value="<?= htmlspecialchars($end_time) ?>"
                        style="
                            width:100%;
                            padding:0.7rem;
                            border:1px solid #e2e8f0;
                            border-radius:8px;
                        "
                    >

                    <?php if(isset($timing_errors['end_time'])): ?>

                        <div style="
                            color:red;
                            font-size:12px;
                            margin-top:5px;
                        ">

                            <?= $timing_errors['end_time']; ?>

                        </div>

                    <?php endif; ?>

                </div>

                <button
                    type="submit"
                    class="btn btn-primary"
                    style="
                        width:100%;
                        padding:0.75rem;
                        font-weight:700;
                    "
                >

                    Save Timing

                </button>

            </form>

        </div>

        <!-- TABLE -->

        <div style="
            background:white;
            border:1px solid #e2e8f0;
            border-radius:12px;
            overflow:hidden;
        ">

            <div style="
                padding:1rem 1.5rem;
                border-bottom:1px solid #f1f5f9;
            ">

                <h3 style="margin:0;">

                    Applied Boutique Timings

                </h3>

            </div>

            <table style="
                width:100%;
                border-collapse:collapse;
            ">

                <thead>

                    <tr style="background:#f8fafc;">

                        <th style="padding:1rem;">

                            Effective From

                        </th>

                        <th style="padding:1rem;">

                            Start

                        </th>

                        <th style="padding:1rem;">

                            End

                        </th>

                        <th style="padding:1rem;">

                            Action

                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php if(empty($timings)): ?>

                        <tr>

                            <td
                                colspan="4"
                                style="
                                    padding:2rem;
                                    text-align:center;
                                    color:#94a3b8;
                                "
                            >

                                No timings configured.

                            </td>

                        </tr>

                    <?php else: ?>

                        <?php foreach($timings as $t): ?>

                            <tr style="
                                border-bottom:1px solid #f1f5f9;
                            ">

                                <td style="padding:1rem;">

                                    <?= date(
                                        'd M Y',
                                        strtotime($t['effective_from'])
                                    ) ?>

                                </td>

                                <td style="padding:1rem;">

                                    <?= date(
                                        'h:i A',
                                        strtotime($t['start_time'])
                                    ) ?>

                                </td>

                                <td style="padding:1rem;">

                                    <?= date(
                                        'h:i A',
                                        strtotime($t['end_time'])
                                    ) ?>

                                </td>

                                <td style="padding:1rem;">

                                    <form
                                        method="POST"
                                        class="delete-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete_timing"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= $t['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            style="
                                                background:none;
                                                border:none;
                                                color:#ef4444;
                                                cursor:pointer;
                                            "
                                        >

                                            <i class="ri-delete-bin-line"></i>

                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (isset($_GET['success'])): ?>
<script>Swal.fire({ icon: 'success', title: 'Rate Added', text: 'New OT rate period successfully applied.', timer: 1500, showConfirmButton: false });</script>
<?php elseif (isset($_GET['deleted'])): ?>
<?php elseif (isset($_GET['timing_success'])): ?>

<script>
Swal.fire({
    icon: 'success',
    title: 'Timing Saved',
    text: 'Boutique timing configured successfully.',
    timer: 1500,
    showConfirmButton: false
});
</script>

<?php elseif (isset($_GET['timing_deleted'])): ?>

<script>
Swal.fire({
    icon: 'success',
    title: 'Deleted',
    text: 'Boutique timing removed.',
    timer: 1500,
    showConfirmButton: false
});
</script>
<script>Swal.fire({ icon: 'success', title: 'Deleted', text: 'Rate period removed.', timer: 1500, showConfirmButton: false });</script>
<?php endif; ?>
<script>
document.querySelectorAll('.delete-form').forEach(form => {

    form.addEventListener('submit', function(e) {

        e.preventDefault();

        Swal.fire({
            title: 'Are you sure?',
            text: 'This OT rate period will be deleted permanently!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {

            if(result.isConfirmed){
                form.submit();
            }

        });

    });

});
</script>
<?php include 'includes/footer.php'; ?>
