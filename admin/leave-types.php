<?php
ob_start();
session_start();
include '../includes/db.php';

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $name = $_POST['name'];
    $allowance = $_POST['default_allowance'];
    $color = $_POST['color'];

    if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO leave_types (name, default_allowance, color) VALUES (?, ?, ?)");
        $stmt->execute([$name, $allowance, $color]);
    } elseif ($_POST['action'] === 'edit') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("UPDATE leave_types SET name = ?, default_allowance = ?, color = ? WHERE id = ?");
        $stmt->execute([$name, $allowance, $color, $id]);
    }
    header("Location: leave-types.php?success=1");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM leave_types WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: leave-types.php?success=1");
    exit;
}

$types = $pdo->query("SELECT * FROM leave_types ORDER BY name ASC")->fetchAll();

$pageTitle = "Leave Types Configuration - Sogasu";
$activePage = "leave-types";
include 'includes/header.php';
?>

<main class="main-content" style="overflow-y: auto !important; height: 100vh;">
    <?php include 'includes/topbar.php'; ?>

    <div style="margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem;">
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0;">Leave Settings</h2>
                <p class="text-muted" style="margin: 0;">Configure yearly leave allowances and types.</p>
            </div>
            <button onclick="openModal('add')" class="btn btn-primary">
                <i class="ri-add-line"></i> Add Leave Type
            </button>
        </div>

        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
            <div style="padding: 1.5rem; overflow-x: auto;">
                <table class="display" style="width: 100%;">
                    <thead>
                        <tr style="background: #f8fafc; text-align: left; color: #64748b; font-size: 0.85rem; font-weight: 600;">
                            <th>Color</th>
                            <th>Leave Name</th>
                            <th>Yearly Allowance</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($types as $t): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 1rem 0;">
                                    <div style="width: 24px; height: 24px; border-radius: 4px; background: <?= $t['color'] ?>;"></div>
                                </td>
                                <td style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($t['name']) ?></td>
                                <td style="font-weight: 700; color: #4338ca;"><?= $t['default_allowance'] ?> days</td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                        <button onclick='openModal("edit", <?= json_encode($t) ?>)' class="btn-icon" title="Edit" style="color: #4338ca;">
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        <a href="?delete=<?= $t['id'] ?>" onclick="return confirm('Delete this leave type?')" class="btn-icon" title="Delete" style="color: #ef4444;">
                                            <i class="ri-delete-bin-line"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Modal -->
<div id="typeModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: white; width: 400px; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #1e293b;"><span id="modalTitle"></span> Leave Type</h3>
            <button onclick="closeModal()" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.25rem;"><i class="ri-close-line"></i></button>
        </div>
        <form method="POST" style="padding: 1.5rem;">
            <input type="hidden" id="modalAction" name="action">
            <input type="hidden" id="modalId" name="id">
            
            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.85rem; color: #64748b; margin-bottom: 0.5rem;">Leave Name</label>
                <input type="text" id="modalName" name="name" required placeholder="e.g. Sick Leave" style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 6px;">
            </div>
            
            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.85rem; color: #64748b; margin-bottom: 0.5rem;">Yearly Allowance (Days)</label>
                <input type="number" id="modalAllowance" name="default_allowance" required min="0" max="365" style="width: 100%; padding: 0.6rem; border: 1px solid #e2e8f0; border-radius: 6px;">
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.85rem; color: #64748b; margin-bottom: 0.5rem;">Theme Color</label>
                <input type="color" id="modalColor" name="color" value="#4338ca" style="width: 100%; height: 40px; padding: 2px; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer;">
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="closeModal()" class="btn" style="background: #f1f5f9; color: #475569; border: none; padding: 0.6rem 1.5rem; border-radius: 6px; cursor: pointer;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.5rem; border-radius: 6px;">Save Type</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(action, data = null) {
        document.getElementById('modalAction').value = action;
        document.getElementById('modalTitle').innerText = action === 'add' ? 'Add' : 'Edit';
        
        if (data) {
            document.getElementById('modalId').value = data.id;
            document.getElementById('modalName').value = data.name;
            document.getElementById('modalAllowance').value = data.default_allowance;
            document.getElementById('modalColor').value = data.color;
        } else {
            document.getElementById('modalId').value = '';
            document.getElementById('modalName').value = '';
            document.getElementById('modalAllowance').value = '0';
            document.getElementById('modalColor').value = '#4338ca';
        }
        
        document.getElementById('typeModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('typeModal').style.display = 'none';
    }
</script>

<?php include 'includes/footer.php'; ?>
