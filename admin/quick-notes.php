<?php
session_start();
include '../includes/db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    $stmt = $pdo->prepare("UPDATE quick_notes SET status=? WHERE id=?");
    echo json_encode([
        'success' => $stmt->execute([$_POST['status'], $_POST['id']])
    ]);
    exit;
}
$stmt = $pdo->query("SELECT * FROM quick_notes WHERE is_deleted = 0 ORDER BY id DESC");
$notes = $stmt->fetchAll();

$pageTitle = "Quick Notes Master - Sogasu";
$activePage = "quick-notes";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; ">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Quick Notes Master</h2>
            <p class="text-muted">Manage pre-defined notes for orders</p>
        </div>
        <button class="btn btn-primary" onclick="window.location.href='add-quick-note.php'"><i class="ri-add-line"></i> Add New Note</button>
    </div>

    <!-- Preview Area -->
    <div style="background: white; border: 1px solid #e2e8f0; padding: 1.5rem; border-radius: 12px; ">
        <h3 style="font-size: 0.9rem; font-weight: 600; color: #64748b; text-transform: uppercase; margin-bottom: 1rem;">Live Preview</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
            <?php foreach ($notes as $note): ?>
                <span style="display: inline-flex; align-items: center; padding: 0.5rem 1.25rem; background: <?= $note['color_bg'] ?>; border: 1px solid <?= $note['color_border'] ?>; color: <?= $note['color_text'] ?>; border-radius: 50px; font-size: 0.85rem; font-weight: 600;">
                    <?= htmlspecialchars($note['note_text']) ?> +
                </span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="table-container" style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; ">
        <table id="quickNotesTable" class="table" style="width:100%;">
                <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <th style="padding: 1rem;">ID</th>
                    <th style="padding: 1rem;">Note Text</th>
                    <th style="padding: 1rem;">Colors (BG / Border / Text)</th>
                    <th style="padding: 1rem;">Status</th>
                    <th style="padding: 1rem; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notes as $note): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 1rem; font-weight: 600; color: #94a3b8;">#<?= $note['id'] ?></td>
                        <td style="padding: 1rem; font-weight: 600; color: #1e293b;"><?= htmlspecialchars($note['note_text']) ?></td>
                        <td style="padding: 1rem;">
                            <div style="display: flex; gap: 4px;">
                                <div style="width: 20px; height: 20px; border: 1px solid #ddd; background: <?= $note['color_bg'] ?>;" title="Background"></div>
                                <div style="width: 20px; height: 20px; border: 1px solid #ddd; background: <?= $note['color_border'] ?>;" title="Border"></div>
                                <div style="width: 20px; height: 20px; border: 1px solid #ddd; background: <?= $note['color_text'] ?>;" title="Text"></div>
                            </div>
                        </td>
                        <td style="padding: 1rem;">
<label class="toggle-switch">
    <input type="checkbox"
        <?= $note['status'] ? 'checked' : '' ?>
        onchange="toggleNoteStatus(this, <?= $note['id']; ?>)">
    <span class="toggle-slider"></span>
</label>
                        </td>
                        <td style="padding: 1rem; text-align: right;">
                            <a href="add-quick-note.php?id=<?= $note['id'] ?>" style="color: #3b82f6; text-decoration: none; margin-right: 10px;"><i class="ri-edit-line"></i></a>
                            <a href="#" onclick="confirmDelete(<?= $note['id'] ?>)" style="color: #ef4444; text-decoration: none;"><i class="ri-delete-bin-line"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This note will be removed!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "delete-quick-note.php?id=" + id;
            }
        });
    }
</script>
<?php include __DIR__ . '/includes/datatable.php'; ?>

<script>

initializeDataTable(
    'quickNotesTable',
    'Quick Notes',
    3
);

</script>
<script>
function toggleNoteStatus(el, id) {
    const status = el.checked ? 1 : 0;

    fetch('quick-notes.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + id + '&status=' + status
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {

            Swal.fire({
                icon: 'success',
                title: status ? 'Activated' : 'Deactivated',
                text: status ? 'Activated successfully' : 'Deactivated successfully',
                showConfirmButton: false,
                timer: 1500,
                customClass: {
                    popup: 'swal-custom-popup',
                    title: 'swal-title',
                    htmlContainer: 'swal-text'
                }
            });

        } else {
            el.checked = !el.checked;
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>
