<?php
session_start();
include '../includes/db.php';

$id = $_GET['id'] ?? null;
$note = null;
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM quick_notes WHERE id = ?");
    $stmt->execute([$id]);
    $note = $stmt->fetch();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $text = trim($_POST['note_text'] ?? '');
    $bg = $_POST['color_bg'] ?? '';
    $border = $_POST['color_border'] ?? '';
    $text_color = $_POST['color_text'] ?? '';
    $status = $_POST['status'] ?? '';

    if ($text === '') {
        $errors['note_text'] = "Note Text is required";
    }

    if ($status === '') {
        $errors['status'] = "Status is required";
    }

    if (empty($errors)) {
        if ($id) {
            $stmt = $pdo->prepare("UPDATE quick_notes SET note_text=?, color_bg=?, color_border=?, color_text=?, status=? WHERE id=?");
            $stmt->execute([$text, $bg, $border, $text_color, $status, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO quick_notes (note_text, color_bg, color_border, color_text, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$text, $bg, $border, $text_color, $status]);
        }

        header("Location: quick-notes.php");
        exit;
    }
}

$pageTitle = ($id ? "Edit" : "Add") . " Quick Note - Sogasu";
$activePage = "quick-notes";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="max-width: 600px; margin: 0 auto; padding: 2rem;">
        <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;"><?= $id ? 'Edit' : 'Add New' ?> Quick Note</h2>
            <button class="btn" onclick="history.back()" style="background: white; border: 1px solid #e2e8f0; color: #64748b;"><i class="ri-arrow-left-line"></i> Back</button>
        </div>

        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);">
<form action="" method="POST" novalidate>
                    <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.9rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem;">Note Text</label>
<input type="text" name="note_text"
value="<?= htmlspecialchars($text ?? $note['note_text'] ?? '') ?>" required placeholder="e.g. Side Zipper"
style="width: 100%; padding: 0.75rem; border: 1px solid <?= !empty($errors['note_text']) ? '#dc2626' : '#e2e8f0' ?>; border-radius: 8px;">

<?php if (!empty($errors['note_text'])): ?>
    <p style="color:#dc2626; font-size:13px; margin-top:5px;">
        <?= $errors['note_text'] ?>
    </p>
<?php endif; ?>                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem;">BG Color</label>
                        <input type="color" name="color_bg" value="<?= $note['color_bg'] ?? '#f8fafc' ?>" style="width: 100%; height: 40px; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem;">Border Color</label>
                        <input type="color" name="color_border" value="<?= $note['color_border'] ?? '#e2e8f0' ?>" style="width: 100%; height: 40px; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem;">Text Color</label>
                        <input type="color" name="color_text" value="<?= $note['color_text'] ?? '#475569' ?>" style="width: 100%; height: 40px; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer;">
                    </div>
                </div>

                <div style="margin-bottom: 2rem;">
                    <label style="display: block; font-size: 0.9rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem;">Status</label>
<select name="status"
style="width: 100%; padding: 0.75rem; border: 1px solid <?= !empty($errors['status']) ? '#dc2626' : '#e2e8f0' ?>; border-radius: 8px; background: #f8fafc;">
    <option value="1" <?= ($status ?? $note['status'] ?? 1) == 1 ? 'selected' : '' ?>>Active</option>
    <option value="0" <?= ($status ?? $note['status'] ?? 1) == 0 ? 'selected' : '' ?>>Inactive</option>
</select>

<?php if (!empty($errors['status'])): ?>
    <p style="color:#dc2626; font-size:13px; margin-top:5px;">
        <?= $errors['status'] ?>
    </p>
<?php endif; ?>                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; border-radius: 8px; font-weight: 600; font-size: 1.1rem;">
                    <i class="ri-save-line"></i> <?= $id ? 'Update' : 'Save' ?> Note
                </button>
            </form>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
