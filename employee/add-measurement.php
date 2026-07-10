<?php
session_start();
require '../includes/db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: login.php");
    exit;
}

$pageTitle = "Add Measurements - Sogasu";
$headerTitle = "Add Measurements";
$activePage = "measurements";

$appointmentId = $_GET['appointment_id'] ?? 0;

if (!$appointmentId) {
    die("Invalid Appointment");
}

/*
|--------------------------------------------------------------------------
| Fetch Appointment
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
SELECT *
FROM appointments
WHERE id=?
LIMIT 1
");

$stmt->execute([$appointmentId]);

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$appointment) {
    die("Appointment not found.");
}

$userId          = $appointment['user_id'];
$categoryId      = $appointment['category_id'];
$subCategoryId   = $appointment['sub_category_id'];
$measurementId   = $appointment['measurement_id'];

/*
|--------------------------------------------------------------------------
| Categories
|--------------------------------------------------------------------------
*/

$categories = $pdo->query("
SELECT id,category_name
FROM categories
WHERE status='active'
AND is_deleted=0
ORDER BY category_name
")->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Sub Categories
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
SELECT id,name
FROM sub_categories
WHERE category_id=?
AND status='active'
AND is_deleted=0
");

$stmt->execute([$categoryId]);

$subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Measurement Fields
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
SELECT
mk.key_name,
mk.key_name AS label,
mk.input_type

FROM measurement_mapping mm

JOIN measurement_keys mk
ON mm.key_id=mk.id

WHERE mm.sub_category_id=?

AND mk.status='active'
AND mk.is_deleted=0

ORDER BY mk.key_name
");

$stmt->execute([$subCategoryId]);

$measurementFields = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Existing Measurements
|--------------------------------------------------------------------------
*/

$savedMeasurements = [];

if (!empty($measurementId)) {

    $stmt = $pdo->prepare("
    SELECT *
    FROM customer_measurements
    WHERE id=?
    ");

    $stmt->execute([$measurementId]);

    $measurementRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($measurementRow) {

        $savedMeasurements =
            json_decode(
                $measurementRow['measurements'],
                true
            ) ?? [];

    }

}

/*
|--------------------------------------------------------------------------
| Save
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    header("Content-Type: application/json");

    try {

        $formData = [];

        foreach ($measurementFields as $field) {

            $fieldName = str_replace(' ','_',$field['key_name']);

            if($field['input_type']=='checkbox'){

                $formData[$field['key_name']] =
                    isset($_POST[$fieldName]) ? 1 : 0;

            }else{

                $formData[$field['key_name']] =
                    $_POST[$fieldName] ?? '';

            }

        }

        $jsonMeasurements = json_encode($formData);
                /*
        |--------------------------------------------------------------------------
        | INSERT / UPDATE Measurements
        |--------------------------------------------------------------------------
        */

        if (!empty($measurementId)) {

            // Update Existing Measurement

            $stmt = $pdo->prepare("
                UPDATE customer_measurements
                SET
                    measurements = ?,
                    updated_by = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $jsonMeasurements,
                $_SESSION['user_id'],
                $measurementId
            ]);

        } else {

            // Create New Measurement

            $stmt = $pdo->prepare("
                INSERT INTO customer_measurements
                (
                    user_id,
                    category_id,
                    sub_category_id,
                    measurements,
                    created_by
                )
                VALUES
                (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $categoryId,
                $subCategoryId,
                $jsonMeasurements,
                $_SESSION['user_id']
            ]);

            $measurementId = $pdo->lastInsertId();

            // Update Appointment

            $stmt = $pdo->prepare("
                UPDATE appointments
                SET measurement_id = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $measurementId,
                $appointmentId
            ]);
        }

        echo json_encode([
            "success" => true,
            "redirect" => "measurements.php"
        ]);

        exit;

    } catch (Exception $e) {

        echo json_encode([
            "success" => false,
            "message" => $e->getMessage()
        ]);

        exit;
    }

}

include 'includes/header.php';
?>

<div class="container">

<div class="card">

<div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">

<a href="measurements.php"
style="text-decoration:none;font-size:20px;">
<i class="ri-arrow-left-line"></i>
</a>

<div>

<h2>Add Measurements</h2>

<p style="color:#64748b;">
Enter customer measurements.
</p>

</div>

</div>

<form id="measurementForm" method="POST">

<input
type="hidden"
name="category_id"
value="<?= $categoryId ?>">

<input
type="hidden"
name="sub_category_id"
value="<?= $subCategoryId ?>">

<div
style="
display:grid;
grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
gap:15px;
">
<?php foreach ($measurementFields as $field): ?>

    <?php
        $fieldName = str_replace(' ','_',$field['key_name']);

        $value = $savedMeasurements[$field['key_name']]
                ?? $savedMeasurements[$fieldName]
                ?? '';
    ?>

    <div>

        <label class="input-label">
            <?= htmlspecialchars($field['label']) ?>
        </label>

        <?php if($field['input_type']=='checkbox'): ?>

            <input
                type="checkbox"
                name="<?= $fieldName ?>"
                value="1"
                <?= !empty($value) ? 'checked' : '' ?>
            >

        <?php elseif($field['input_type']=='select'): ?>

            <select
                name="<?= $fieldName ?>"
                class="form-input">

                <option value="">Select</option>

                <option
                    value="yes"
                    <?= $value=='yes'?'selected':'' ?>>
                    Yes
                </option>

                <option
                    value="no"
                    <?= $value=='no'?'selected':'' ?>>
                    No
                </option>

            </select>

        <?php else: ?>

            <input
                type="number"
                step="0.1"
                class="form-input"
                name="<?= $fieldName ?>"
                value="<?= htmlspecialchars($value) ?>">

        <?php endif; ?>

    </div>

<?php endforeach; ?>

</div>

<div style="margin-top:30px;">

<button
type="button"
id="saveBtn"
class="save-btn">

<i class="ri-save-3-line"></i>

<span>Save Measurements</span>

</button>

</div>

</form>

</div>

</div>

<style>

.input-label{

display:block;

margin-bottom:6px;

font-weight:600;

}

.form-input{

width:100%;

padding:10px;

border:1px solid #ddd;

border-radius:8px;

}
.save-btn{

width:100%;

height:60px;

border:none;

border-radius:15px;

background:linear-gradient(135deg,#7c3aed,#9333ea);

color:#fff;

font-size:18px;

font-weight:700;

cursor:pointer;

display:flex;

justify-content:center;

align-items:center;

gap:10px;

box-shadow:0 10px 25px rgba(124,58,237,.35);

transition:.3s;

margin-top:25px;

}

.save-btn i{

font-size:22px;

}

.save-btn:hover{

transform:translateY(-2px);

box-shadow:0 15px 30px rgba(124,58,237,.45);

}
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

document.getElementById("saveBtn").onclick=function(){

let form=document.getElementById("measurementForm");

let data=new FormData(form);

fetch(window.location.href,{

method:"POST",

body:data

})

.then(r=>r.json())

.then(res=>{

if (res.success) {

    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Measurements Saved Successfully',
        confirmButtonColor: '#7c3aed',
        timer: 1800,
        showConfirmButton: false
    }).then(() => {

        window.location.href = res.redirect;

    });

}else{

Swal.fire({
    icon:'error',
    title:'Error',
    text:res.message
});
}

})

.catch(()=>{

Swal.fire({
    icon:'error',
    title:'Server Error',
    text:'Something went wrong.'
});
});

};

</script>

<?php include 'includes/bottom-nav.php'; ?>