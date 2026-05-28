<?php
session_start();
include '../includes/db.php';

$customer_id = $_GET['id'] ?? null;
if (!$customer_id) {
    header("Location: customers.php");
    exit;
}

// Fetch Customer Info
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    header("Location: customers.php");
    exit;
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'save') {
        $name = trim($_POST['member_name']);
        $relation = trim($_POST['relationship']);
        $age = (int)($_POST['age'] ?? 0);
        $notes = trim($_POST['notes']);
        $mid = $_POST['member_id'] ?? null;

        if ($mid) {
            $stmt = $pdo->prepare("UPDATE customer_family_members SET member_name=?, relationship=?, age=?, notes=? WHERE id=?");
            $stmt->execute([$name, $relation, $age, $notes, $mid]);
            $_SESSION['success'] = "Member updated";
        } else {
            $stmt = $pdo->prepare("INSERT INTO customer_family_members (customer_id, member_name, relationship, age, phone, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$customer_id, $name, $relation, $age, $customer['phone'], $notes]);
            $_SESSION['success'] = "Member added";
        }
    } elseif ($_POST['action'] == 'delete') {
        $mid = $_POST['member_id'];
        $stmt = $pdo->prepare("UPDATE customer_family_members SET is_deleted = 1 WHERE id = ?");
        $stmt->execute([$mid]);
        $_SESSION['success'] = "Member deleted";
    }
    header("Location: customer-family.php?id=$customer_id");
    exit;
}

// Fetch Family Members
$stmt = $pdo->prepare("SELECT * FROM customer_family_members WHERE customer_id = ? AND is_deleted = 0 ORDER BY id ASC");
$stmt->execute([$customer_id]);
$members = $stmt->fetchAll();

$pageTitle = "Family Members - " . $customer['first_name'];
$activePage = "customers";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; ">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Family Members Management</h2>
            <p style="color: #64748b;">Managing family for: <strong><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></strong> (<?= htmlspecialchars($customer['phone']) ?>)</p>
        </div>
        <button class="btn" onclick="window.location.href='customers.php'" style="background: white; border: 1px solid #e2e8f0; color: #64748b;">
            <i class="ri-arrow-left-line"></i> Back to Customers
        </button>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 2rem;">
        <!-- Add/Edit Form -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; height: fit-content;">
            <h3 id="form-title" style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1.5rem;">Add New Family Member</h3>
<form
    action=""
    method="POST"
    id="member-form"
    novalidate
    onsubmit="return validateMemberForm()"
>                <input type="hidden" name="action" value="save">
                <input type="hidden" name="member_id" id="member_id" value="">
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem;">Member Name</label>
                    <input
    type="text"
    name="member_name"
    id="member_name" id="member_name" required class="form-control" placeholder="e.g. Daughter Name">
    <div
    id="member_name_error"
    style="
        color:red;
        font-size:13px;
        margin-top:4px;
    "
></div>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem;">Relationship</label>
                        <select
    name="relationship"
    id="relationship" id="relationship" class="form-select">
                            <option value="Daughter">Daughter</option>
                            <option value="Son">Son</option>
                            <option value="Spouse">Spouse</option>
                            <option value="Mother">Mother</option>
                            <option value="Father">Father</option>
                            <option value="Sister">Sister</option>
                            <option value="Brother">Brother</option>
                            <option value="Other">Other</option>
                        </select>
                        <div
    id="relationship_error"
    style="
        color:red;
        font-size:13px;
        margin-top:4px;
    "
></div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem;">Age</label>
                        <input
    type="number"
    name="age"
    id="age" id="age" class="form-control" placeholder="Years">
    <div
    id="age_error"
    style="
        color:red;
        font-size:13px;
        margin-top:4px;
    "
></div>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem;">Notes (Optional)</label>
                    <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Specific measurements or preferences..."></textarea>
                </div>

                <div style="display: flex; gap: 0.75rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="ri-save-line"></i> <span id="submit-text">Save Member</span>
                    </button>
                    <button type="button" id="cancel-edit" class="btn" style="display: none; background: #f1f5f9;" onclick="resetForm()">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Members List -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 600; color: #1e293b; margin-bottom: 1.5rem;">Existing Members</h3>
            
            <?php if (empty($members)): ?>
                <div style="text-align: center; padding: 3rem 1rem; color: #94a3b8;">
                    <i class="ri-group-line" style="font-size: 3rem; display: block; margin-bottom: 1rem; opacity: 0.2;"></i>
                    <p>No family members added yet.</p>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php foreach ($members as $m): ?>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; padding: 1rem; border: 1px solid #f1f5f9; border-radius: 8px; background: #fcfcfc;">
                            <div>
                                <div style="font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 0.5rem;">
                                    <?= htmlspecialchars($m['member_name']) ?>
                                    <span class="badge" style="background: #eef2ff; color: #4338ca; border: none; font-size: 0.65rem;"><?= htmlspecialchars($m['relationship']) ?></span>
                                    <?php if ($m['age']): ?>
                                        <span class="badge" style="background: #f1f5f9; color: #64748b; border: none; font-size: 0.65rem;"><?= $m['age'] ?> Yrs</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.8rem; color: #64748b; margin-top: 4px;">
                                    Phone: <?= htmlspecialchars($m['phone']) ?>
                                </div>
                                <?php if ($m['notes']): ?>
                                    <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 8px; font-style: italic;">
                                        "<?= htmlspecialchars($m['notes']) ?>"
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <button onclick="editMember(<?= htmlspecialchars(json_encode($m)) ?>)" style="border: none; background: #eff6ff; color: #2563eb; width: 30px; height: 30px; border-radius: 6px; cursor: pointer;"><i class="ri-pencil-line"></i></button>
                                <button onclick="deleteMember(<?= $m['id'] ?>)" style="border: none; background: #fef2f2; color: #dc2626; width: 30px; height: 30px; border-radius: 6px; cursor: pointer;"><i class="ri-delete-bin-line"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Delete Hidden Form -->
<form id="delete-form" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="member_id" id="delete_member_id">
</form>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function editMember(data) {
        document.getElementById('form-title').innerText = "Edit Family Member";
        document.getElementById('submit-text').innerText = "Update Member";
        document.getElementById('member_id').value = data.id;
        document.getElementById('member_name').value = data.member_name;
        document.getElementById('relationship').value = data.relationship;
        document.getElementById('age').value = data.age || '';
        document.getElementById('notes').value = data.notes;
        document.getElementById('cancel-edit').style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function resetForm() {
        document.getElementById('form-title').innerText = "Add New Family Member";
        document.getElementById('submit-text').innerText = "Save Member";
        document.getElementById('member_id').value = "";
        document.getElementById('member-form').reset();
        document.getElementById('cancel-edit').style.display = 'none';
    }

    function deleteMember(id) {
        Swal.fire({
            title: 'Delete Member?',
            text: "This family member will be removed.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Yes, delete'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete_member_id').value = id;
                document.getElementById('delete-form').submit();
            }
        });
    }
    function validateMemberForm(){

    let valid = true;

    document.getElementById(
        'member_name_error'
    ).innerHTML = '';

    document.getElementById(
        'relationship_error'
    ).innerHTML = '';

    document.getElementById(
        'age_error'
    ).innerHTML = '';

    let memberName =
        document.getElementById(
            'member_name'
        ).value;

    let relationship =
        document.getElementById(
            'relationship'
        ).value;

    let age =
        document.getElementById(
            'age'
        ).value;

    if(memberName.trim() == ''){

        document.getElementById(
            'member_name_error'
        ).innerHTML =
        'Member Name field is required';

        valid = false;

    }

    if(relationship.trim() == ''){

        document.getElementById(
            'relationship_error'
        ).innerHTML =
        'Relationship field is required';

        valid = false;

    }

    if(age.trim() == ''){

        document.getElementById(
            'age_error'
        ).innerHTML =
        'Age field is required';

        valid = false;

    }

    return valid;

}
</script>

<style>
    .form-control, .form-select {
        width: 100%;
        padding: 0.6rem;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        outline: none;
    }
    .form-control:focus { border-color: #B98060; }
</style>

<?php include 'includes/footer.php'; ?>
