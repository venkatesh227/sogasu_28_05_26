<?php
session_start();
include '../includes/db.php';

$pageTitle = "Design Board - Sogasu";
$errors = [];
$activePage = "design-board";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

$title = trim($_POST['title'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if ($title == '') {
    $errors['title'] = "Design Name field is required";
}

if ($notes == '') {
    $errors['notes'] = "Notes field is required";
}

if (empty($_FILES['design_image']['name'])) {
    $errors['design_image'] = "Upload Reference field is required";
}    $created_by = $_SESSION['user_id'] ?? 1;

    $image_path = '';
if (empty($errors)) {
    if (!empty($_FILES['design_image']['name'])) {

        $uploadDir = '../uploads/designs/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . '_' . $_FILES['design_image']['name'];

        move_uploaded_file(
            $_FILES['design_image']['tmp_name'],
            $uploadDir . $fileName
        );

        $image_path = 'uploads/designs/' . $fileName;
    }

   if (!empty($_POST['edit_id'])) {

    $edit_id = $_POST['edit_id'];

    $stmt = $pdo->prepare("SELECT image_path FROM designs WHERE id=?");
    $stmt->execute([$edit_id]);
    $oldData = $stmt->fetch(PDO::FETCH_ASSOC);

    $image_path = $oldData['image_path'];

    if (!empty($_FILES['design_image']['name'])) {

        $uploadDir = '../uploads/designs/';

        $fileName = time() . '_' . $_FILES['design_image']['name'];

        move_uploaded_file(
            $_FILES['design_image']['tmp_name'],
            $uploadDir . $fileName
        );

        $image_path = 'uploads/designs/' . $fileName;
    }

    $stmt = $pdo->prepare("
        UPDATE designs
        SET title=?, notes=?, image_path=?
        WHERE id=?
    ");

    $stmt->execute([
        $title,
        $notes,
        $image_path,
        $edit_id
    ]);

    $_SESSION['success'] = "Design updated successfully";

} else {

    $stmt = $pdo->prepare("
        INSERT INTO designs
        (title, image_path, notes, created_by, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $title,
        $image_path,
        $notes,
        $created_by
    ]);

    $_SESSION['success'] = "Design saved successfully";
}

header("Location: design-board.php");
exit;
    }
}

$stmt = $pdo->query("
    SELECT * FROM designs
    ORDER BY id DESC
");

$designs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<main class="main-content">

    <?php include 'includes/topbar.php'; ?>

    <div style="padding: 1rem;">

        <div class="page-header"
             style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;">

            <div>
                <h2 style="font-size:1.5rem;font-weight:700;color:#1e293b;">
                    Design Board
                </h2>

                <p class="text-muted">
                    Create and manage your designs
                </p>
            </div>

            <button class="btn btn-add" onclick="openNewDesign()">
                <i class="ri-add-line"></i> Add New Design
            </button>

        </div>

        <div class="design-grid"
             style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem;">

            <?php foreach($designs as $design): ?>

            <div class="design-card">

                <div class="design-image"
                     style="height:200px;background:#f8fafc;border-bottom:1px solid #e2e8f0;position:relative;overflow:hidden;">

                    <?php if($design['image_path'] != ''): ?>

<img src="../<?= $design['image_path'] ?>"
     style="
        width:100%;
        height:100%;
        object-fit:contain;
        background:white;
     ">
                    <?php else: ?>

                    <div style="height:100%;display:flex;align-items:center;justify-content:center;color:#94a3b8;">
                        No Image
                    </div>

                    <?php endif; ?>

                    <div class="design-overlay">

                       <button onclick="editDesign(
'<?= $design['id'] ?>',
'<?= htmlspecialchars($design['title'], ENT_QUOTES) ?>',
'<?= htmlspecialchars($design['notes'], ENT_QUOTES) ?>',
'../<?= $design['image_path'] ?>'
)">
    <i class="ri-edit-line"></i> Edit
</button>
                    </div>

                </div>

                <div class="design-info" style="padding:1rem;">

                    <h4 style="margin:0;font-size:1rem;color:#1e293b;">

                        <?= htmlspecialchars($design['title']) ?>

                    </h4>

                    <p style="font-size:0.85rem;color:#64748b;margin:0.5rem 0 0;">

                        <?= date('d M Y h:i A', strtotime($design['created_at'])) ?>

                    </p>

                </div>

            </div>

            <?php endforeach; ?>

        </div>

    </div>

</main>

<div id="designModal" class="modal">

<form id="designForm" method="POST" novalidate
          enctype="multipart/form-data">
          <input type="hidden" name="edit_id" id="edit_id">

        <div class="modal-card" style="width:1150px;max-width:95%;">

            <div class="modal-header"
                 style="background:var(--primary);color:white;padding:1rem;display:flex;justify-content:space-between;align-items:center;">

                <h3 id="modalTitle" style="margin:0;">
                    Create New Design
                </h3>

                <i class="ri-close-line"
                   style="cursor:pointer;font-size:1.5rem;"
                   onclick="closeModal()"></i>

            </div>

            <div class="modal-body"
                 style="padding:1.5rem;display:grid;grid-template-columns:1fr 2fr;gap:1.5rem;">

                <div>

                    <div class="form-group" style="margin-bottom:1rem;">

                        <label class="form-label">
                            Design Name
                        </label>

<input type="text"
       name="title"
       class="form-control"
       placeholder="Enter design name"
       value="<?= $title ?? '' ?>">

<?php if(isset($errors['title'])): ?>

    <small class="validation-error" style="color:red;">
        <?= $errors['title'] ?>
    </small>

<?php endif; ?>                               

                    </div>

                    <div class="form-group" style="margin-bottom:1rem;">

                        <label class="form-label">
                            Notes
                        </label>

                        <textarea name="notes"
                                  class="form-control"
                                  rows="4"></textarea>
                               <?php if(isset($errors['notes'])): ?>

<small class="validation-error" style="color:red;">
                <?= $errors['notes'] ?>
        </small>

    <?php endif; ?>
                    </div>

                    <div class="form-group" style="margin-bottom:1rem;">

                        <label class="form-label">
                            Upload Reference
                        </label>

                        <input type="file"
       name="design_image"
       class="form-control"
       id="design_image"
       accept="image/*">

<div style="
    margin-top:10px;
    display:flex;
    gap:15px;
">

    <!-- OLD IMAGE -->
    <div id="oldPreviewContainer"
         style="display:none;">

        <img id="oldImagePreview"
             src=""
             style="
                width:120px;
                height:120px;
                object-fit:cover;
                border-radius:10px;
                border:2px solid #22c55e;
             ">

    </div>

    <!-- NEW IMAGE -->
    <div id="newPreviewContainer"
         style="display:none;">

        <img id="newImagePreview"
             src=""
             style="
                width:120px;
                height:120px;
                object-fit:cover;
                border-radius:10px;
                border:2px solid #ef4444;
             ">

    </div>

</div>

    <?php if(isset($errors['design_image'])): ?>

<small class="validation-error" style="color:red;">
                <?= $errors['design_image'] ?>
        </small>

    <?php endif; ?>

                    </div>

<button type="button"
                            class="btn btn-primary"
                            style="width:100%;">

                        Save Design

                    </button>

                </div>

<div>

    <label class="form-label">
        Drawing Board
    </label>

    <div id="drawing-board"
         style="width:100%;
                height:500px;
                border:1px solid #cbd5e1;
                border-radius:8px;
                background:white;
                position:relative;
                overflow:hidden;">

        <canvas id="canvas"
                style="width:100%;
                       height:100%;
                       cursor:url(&quot;data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23db2777' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><path d='M12 20h9'/><path d='M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z'/></svg>&quot;) 3 20, crosshair;">
        </canvas>

    </div>

    <div style="margin-top:1rem;
                display:flex;
                gap:0.5rem;
                align-items:center;">

        <button type="button"
                class="btn btn-sm"
                onclick="clearCanvas()"
                style="background:#f1f5f9;
                       border:1px solid #e2e8f0;">

            <i class="ri-eraser-line"></i> Clear

        </button>

        <button type="button"
                class="btn btn-sm"
                onclick="setBrush()"
                style="background:#f1f5f9;
                       border:1px solid #e2e8f0;">

            <i class="ri-brush-line"></i> Brush

        </button>

    <input type="color"
       id="colorPicker"
       value="#000000"
       onchange="changeColor(this.value)"
       style="
            width:30px;
            height:30px;
            padding:0;
            border:1px solid #e2e8f0;
            border-radius:10px;
            background:#f1f5f9;
            cursor:pointer;
            overflow:hidden;
       ">
    </div>

</div>
            </div>

        </div>

    </form>

</div>

<style>

.design-card{
    background:white;
    border:1px solid #e2e8f0;
    border-radius:12px;
    overflow:hidden;
    transition:0.2s;
}

.design-card:hover{
    transform:translateY(-4px);
    box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);
}

.design-overlay{
    position:absolute;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.4);
    display:flex;
    justify-content:center;
    align-items:center;
    opacity:0;
    transition:0.2s;
}

.design-card:hover .design-overlay{
    opacity:1;
}

.design-overlay button{
    background:white;
    border:none;
    padding:0.5rem 1rem;
    border-radius:6px;
    cursor:pointer;
}

.modal{
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.5);
    z-index:1000;
    justify-content:center;
    align-items:center;
}

.modal-card{
    background:white;
    border-radius:12px;
    overflow:hidden;
}

</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

let canvas;
let ctx;
let painting = false;
let brushColor = '#000000';

function openNewDesign() {

    document.querySelector('#designModal form').reset();
    document.getElementById('oldPreviewContainer').style.display = 'none';

document.getElementById('newPreviewContainer').style.display = 'none';
    document.getElementById('edit_id').value = '';

    document.querySelectorAll('.validation-error').forEach(el => {
        el.remove();
    });

    document.getElementById('designModal').style.display = 'flex';

    setTimeout(() => {
        initCanvas();
    }, 100);

}
function closeModal() {

    document.getElementById('designModal').style.display = 'none';

}

function editDesign(id, title, notes, image) {

    document.getElementById('modalTitle').innerText = 'Edit Design';

    document.querySelector('[name="title"]').value = title;

    document.querySelector('[name="notes"]').value = notes;
    document.getElementById('edit_id').value = id;

document.getElementById('oldImagePreview').src = image;

document.getElementById('oldPreviewContainer').style.display = 'block';

document.getElementById('newPreviewContainer').style.display = 'none';
    document.getElementById('designModal').style.display = 'flex';

    setTimeout(() => {
        initCanvas();
    }, 100);

}
function initCanvas() {

    canvas = document.getElementById('canvas');

    if (!canvas) return;

    ctx = canvas.getContext('2d');

    canvas.width = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;

    canvas.addEventListener('mousedown', startPosition);
    canvas.addEventListener('mouseup', endPosition);
    canvas.addEventListener('mousemove', draw);

}

function startPosition(e) {

    painting = true;

    draw(e);

}

function endPosition() {

    painting = false;

    ctx.beginPath();

}

function draw(e) {

    if (!painting) return;

    const rect = canvas.getBoundingClientRect();

    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    ctx.strokeStyle = brushColor;

    ctx.lineTo(
        e.clientX - rect.left,
        e.clientY - rect.top
    );

    ctx.stroke();

    ctx.beginPath();

    ctx.moveTo(
        e.clientX - rect.left,
        e.clientY - rect.top
    );

}

function clearCanvas() {

    ctx.clearRect(0, 0, canvas.width, canvas.height);

}

function setBrush() {

    canvas.style.cursor = `url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23db2777' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><path d='M12 20h9'/><path d='M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z'/></svg>") 3 20, crosshair`;

}

function changeColor(color) {

    brushColor = color;

}
document.querySelector('#designForm button').addEventListener('click', function (e) {

    let valid = true;

    // remove old errors
    document.querySelectorAll('.validation-error').forEach(el => {
        el.remove();
    });

    // fields
    const title = document.querySelector('[name="title"]');
    const notes = document.querySelector('[name="notes"]');
    const image = document.querySelector('[name="design_image"]');

    // title validation
    if (title.value.trim() === '') {

        valid = false;

        title.insertAdjacentHTML(
            'afterend',
            '<small class="validation-error" style="color:red;">Design Name field is required</small>'
        );
    }

    // notes validation
    if (notes.value.trim() === '') {

        valid = false;

        notes.insertAdjacentHTML(
            'afterend',
            '<small class="validation-error" style="color:red;">Notes field is required</small>'
        );
    }

    // image validation
    if (image.files.length === 0) {

        valid = false;

        image.insertAdjacentHTML(
            'afterend',
            '<small class="validation-error" style="color:red;">Upload Reference field is required</small>'
        );
    }

    // submit if valid
    if (valid) {
        document.getElementById('designForm').submit();
    }

});
</script>
<?php if(!empty($errors)): ?>

<script>

document.addEventListener('DOMContentLoaded', function () {

    document.getElementById('designModal').style.display = 'flex';

    setTimeout(() => {
        initCanvas();
    }, 100);

});

</script>

<?php endif; ?>

<script>

document.getElementById('design_image').addEventListener('change', function(e) {

    const file = e.target.files[0];

    if (file) {

        const reader = new FileReader();

        reader.onload = function(event) {

document.getElementById('newImagePreview').src = event.target.result;

document.getElementById('newPreviewContainer').style.display = 'block';
        };

        reader.readAsDataURL(file);

    }

});

</script>

<?php if (!empty($_SESSION['success'])): ?>

<script>

Swal.fire({
    icon: 'success',
    title: 'Success',
    text: '<?= $_SESSION['success']; ?>',
    confirmButtonColor: '#db2777'
});

</script>

<?php unset($_SESSION['success']); endif; ?>

<?php include 'includes/footer.php'; ?>