<?php
session_start();
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

// Check if the employee is a supervisor
$user_id = $_SESSION['user_id'];
$stmtEmp = $pdo->prepare("SELECT job_role FROM employees WHERE user_id = ? AND is_deleted = 0");
$stmtEmp->execute([$user_id]);
$emp = $stmtEmp->fetch();
$is_supervisor = ($emp && $emp['job_role'] === 'Supervisor');

$order_id = $_GET['id'] ?? null;
if (!$order_id) {
    header("Location: dashboard.php");
    exit();
}
// Fetch order details
$stmt = $pdo->prepare(" 
        SELECT 
            o.id,
            o.order_code,
            o.customer_id,
            o.family_member_id,
            o.category_id,
            o.sub_category_id,
            o.fabric_details,
            o.notes,
            o.order_status,
            o.payment_status,
            o.razorpay_payment_link_id,
            o.payment_link,
            o.razorpay_payment_id,
            o.paid_at,
            o.payment_response,
            o.supervisor_id,
            o.assigned_employee_id,
            o.employee_taken_at,
            o.rack_id,
            o.base_price,
            o.extra_charges,
            o.total_amount,
            o.advance_amount,
            o.paid_amount,
            o.advance_payment_mode,
            o.transaction_reference,
            o.due_date,
            o.measurement_unit,
            o.is_deleted,
            o.created_at,
            c.first_name as cust_first,
            c.last_name as cust_last,
            c.phone as cust_phone,
            sc.name as garment,
            r.rack_name,
            r.description as rack_desc,
            e.first_name as emp_first,
            e.last_name as emp_last

        FROM orders o

        LEFT JOIN customers c 
            ON o.customer_id = c.id

        LEFT JOIN sub_categories sc 
            ON o.sub_category_id = sc.id

        LEFT JOIN racks r 
            ON o.rack_id = r.id

        LEFT JOIN employees e 
            ON o.assigned_employee_id = e.id

        WHERE o.id = ?
        AND o.is_deleted = 0

        UNION ALL

        SELECT 
            co.id,
            co.order_code,
            co.user_id as customer_id,
            NULL as family_member_id,
            co.category_id,
            co.sub_category_id,
            NULL as fabric_details,
            co.additional_notes as notes,
            co.status as order_status,
            NULL as payment_status,
            NULL as razorpay_payment_link_id,
            NULL as payment_link,
            NULL as razorpay_payment_id,
            NULL as paid_at,
            NULL as payment_response,
            co.supervisor_id,
            co.assigned_employee_id,
            NULL as employee_taken_at,
            co.rack_id,
            co.base_price,
            co.extra_charges,
            co.total_amount,
            0 as advance_amount,
            0 as paid_amount,
            NULL as advance_payment_mode,
            NULL as transaction_reference,
            co.appointment_date as due_date,
            'CMS' as measurement_unit,
            co.is_deleted,
            co.created_at,
            cu.first_name as cust_first,
            cu.last_name as cust_last,
            cu.phone as cust_phone,
            sc.name as garment,
            r.rack_name,
            r.description as rack_desc,
            e.first_name as emp_first,
            e.last_name as emp_last

        FROM customer_orders co

        LEFT JOIN customers cu 
            ON co.user_id = cu.user_id

        LEFT JOIN sub_categories sc 
            ON co.sub_category_id = sc.id

        LEFT JOIN racks r 
            ON co.rack_id = r.id

        LEFT JOIN employees e 
            ON co.assigned_employee_id = e.id

        WHERE co.id = ?
        AND co.is_deleted = 0
");
$stmt->execute([
    $order_id,
    $order_id
]);
$order = $stmt->fetch();
// Fetch measurements dynamically
$stmt = $pdo->prepare("
    SELECT key_name, measurement_value
    FROM order_measurements
    WHERE order_id = ?
");
$stmt->execute([$order_id]);
$measurements = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

if (!$order) {
    echo "Order not found.";
    exit();
}

// Fetch order images
$stmt = $pdo->prepare("SELECT * FROM order_images WHERE order_id = ?");
$stmt->execute([$order_id]);
$images = $stmt->fetchAll();

$design_refs = array_filter($images, function ($img) {
    return $img['image_type'] === 'sample'; });
$fabric_refs = array_filter($images, function ($img) {
    return $img['image_type'] === 'fabric'; });

$pageTitle = "Task Details - Sogasu Staff";
$headerTitle = "Order #" . htmlspecialchars($order['order_code']);
include 'includes/header.php';
?>

<div
    style="background: var(--surface); padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); position: sticky; top: 60px; z-index: 40;">
    <div style="display: flex; justify-content: space-between; align-items: start;">
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 700;"><?= htmlspecialchars($order['garment'] ?: 'Product') ?>
            </h2>
            <div style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">Due by
                <?= date('d M, Y', strtotime($order['due_date'])) ?></div>
        </div>
        <span class="badge <?= strtolower($order['order_status']) ?>"><?= ucfirst($order['order_status']) ?></span>
    </div>
</div>

<div class="container">

    <!-- Customer Info -->
    <div class="card">
        <div class="section-title">Customer</div>
        <div style="display: flex; align-items: center; gap: 1rem;">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($order['cust_first']) ?>&background=random"
                style="width: 48px; height: 48px; border-radius: 50%;">
            <div>
                <div style="font-weight: 600; font-size: 1rem;">
                    <?= htmlspecialchars($order['cust_first'] . ' ' . $order['cust_last']) ?></div>
                <?php if ($is_supervisor): ?>
                    <div style="color: var(--text-muted); font-size: 0.9rem;"><?= htmlspecialchars($order['cust_phone']) ?>
                    </div>
                <?php else: ?>
                    <div style="color: var(--text-muted); font-size: 0.9rem;">+91 &bull;&bull;&bull;&bull;&bull;
                        &bull;&bull;&bull;&bull;</div>
                <?php endif; ?>
            </div>
            <?php if ($is_supervisor): ?>
                <a href="tel:<?= htmlspecialchars($order['cust_phone']) ?>"
                    style="margin-left: auto; width: 40px; height: 40px; background: var(--primary-light); color: var(--primary); display: flex; align-items: center; justify-content: center; border-radius: 50%; text-decoration: none;">
                    <i class="ri-phone-fill"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Assigned Staff Info -->
    <div class="card">
        <div class="section-title">Assigned Staff</div>
        <div style="display: flex; align-items: center; gap: 1rem;">
            <?php if ($order['assigned_employee_id']): ?>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($order['emp_first']) ?>&background=eef2ff&color=4338ca"
                    style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">
                <div>
                    <div style="font-weight: 600; font-size: 1rem;">
                        <?= htmlspecialchars($order['emp_first'] . ' ' . $order['emp_last']) ?></div>
                    <div style="color: var(--text-muted); font-size: 0.9rem;">Tailoring / Finishing Artist</div>
                </div>
            <?php else: ?>
                <div
                    style="width: 48px; height: 48px; background: #fff1f2; color: #e11d48; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <i class="ri-user-unfollow-line"></i>
                </div>
                <div>
                    <div style="font-weight: 600; font-size: 1rem; color: #e11d48;">Unassigned</div>
                    <div style="color: var(--text-muted); font-size: 0.9rem;">No employee has been assigned yet.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rack Information -->
    <div class="card" style="background: #fffbeb; border-color: #fde68a;">
        <div class="section-title" style="color: #92400e;">
            <span><i class="ri-archive-line"></i> Rack Location</span>
        </div>
        <?php if ($order['rack_id']): ?>
            <div style="font-size: 1.1rem; font-weight: 700; color: #78350f;"><?= htmlspecialchars($order['rack_name']) ?>
            </div>
            <div style="font-size: 0.85rem; color: #92400e; margin-top: 0.25rem;">
                <?= htmlspecialchars($order['rack_desc'] ?: 'Collect materials from this rack.') ?></div>
        <?php else: ?>
            <div style="font-size: 0.9rem; color: #b45309; font-style: italic;">No rack allocated yet. Please check with
                supervisor.</div>
        <?php endif; ?>
    </div>

    <!-- Job Description -->
    <div class="card">
        <div class="section-title">Design Notes</div>
        <p style="color: var(--text-main); line-height: 1.5; font-size: 0.95rem;">
            <?= nl2br(htmlspecialchars($order['notes'] ?? 'No special notes provided.')) ?>
        </p>
        <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
            <?php if (!empty($design_refs)): ?>
                <div onclick="showImages(<?= htmlspecialchars(json_encode(array_column($design_refs, 'image_path'))) ?>)"
                    style="background: #fdf2f8; border: 1px solid #fbcfe8; padding: 0.75rem; border-radius: 12px; flex: 1; text-align: center; cursor: pointer;">
                    <i class="ri-image-line"
                        style="display: block; font-size: 1.5rem; color: #db2777; margin-bottom: 0.25rem;"></i>
                    <span style="font-size: 0.75rem; font-weight: 600; color: #db2777;">Design Ref
                        (<?= count($design_refs) ?>)</span>
                </div>
            <?php else: ?>
                <div
                    style="background: #f1f5f9; padding: 0.75rem; border-radius: 12px; flex: 1; text-align: center; opacity: 0.6;">
                    <i class="ri-image-line"
                        style="display: block; font-size: 1.5rem; color: #64748b; margin-bottom: 0.25rem;"></i>
                    <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">No Design Ref</span>
                </div>
            <?php endif; ?>

            <?php if (!empty($fabric_refs)): ?>
                <div onclick="showImages(<?= htmlspecialchars(json_encode(array_column($fabric_refs, 'image_path'))) ?>)"
                    style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 0.75rem; border-radius: 12px; flex: 1; text-align: center; cursor: pointer;">
                    <i class="ri-t-shirt-line"
                        style="display: block; font-size: 1.5rem; color: #2563eb; margin-bottom: 0.25rem;"></i>
                    <span style="font-size: 0.75rem; font-weight: 600; color: #2563eb;">Fabric Img
                        (<?= count($fabric_refs) ?>)</span>
                </div>
            <?php else: ?>
                <div
                    style="background: #f1f5f9; padding: 0.75rem; border-radius: 12px; flex: 1; text-align: center; opacity: 0.6;">
                    <i class="ri-t-shirt-line"
                        style="display: block; font-size: 1.5rem; color: #64748b; margin-bottom: 0.25rem;"></i>
                    <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">No Fabric Img</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Measurements -->
    <div class="card">
        <div class="section-title">Measurements</div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem;">

            <?php if (!empty($measurements)): ?>
                <?php foreach ($measurements as $key => $value): ?>
                    <div style="background: var(--background); padding: 0.75rem; border-radius: 8px; text-align: center;">
                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                            <?= htmlspecialchars($key) ?>
                        </div>
                        <div style="font-weight: 600; font-size: 1.1rem;">
                            <?= htmlspecialchars($value) ?>"
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: span 3; text-align:center; color: gray;">
                    No measurements available
                </div>
            <?php endif; ?>

        </div>
    </div>
    <!-- Work Status Update -->
    <div class="card">
        <div class="section-title">Update Work Status</div>
        <div
            style="background: var(--background); padding: 0.5rem 1rem; border-radius: 12px; border: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
            <div style="flex: 1;">
                <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 2px;">Current
                    Stage</label>
                <select id="taskStatus" <?= $order['order_status'] === 'delivered' ? 'disabled' : '' ?>
                    style="width: 100%; border: none; background: transparent; font-size: 1rem; font-weight: 600; color: var(--text-main); outline: none; padding: 0.25rem 0; appearance: none;">

                    <option value="pending" <?= $order['order_status'] == 'pending' ? 'selected' : '' ?>>Pending</option>

                    <option value="processing" <?= $order['order_status'] == 'processing' ? 'selected' : '' ?>>Processing
                    </option>

                    <option value="pattern_making" <?= $order['order_status'] == 'pattern_making' ? 'selected' : '' ?>>Pattern
                        Making</option>

                    <option value="cutting" <?= $order['order_status'] == 'cutting' ? 'selected' : '' ?>>Cutting</option>

                    <option value="embroidery" <?= $order['order_status'] == 'embroidery' ? 'selected' : '' ?>>Embroidery /
                        Maggam</option>

                    <option value="stitching" <?= $order['order_status'] == 'stitching' ? 'selected' : '' ?>>Stitching</option>

                    <option value="finishing" <?= $order['order_status'] == 'finishing' ? 'selected' : '' ?>>Finishing /
                        Ironing</option>

                    <option value="ready" <?= $order['order_status'] == 'ready' ? 'selected' : '' ?>>Ready</option>

                    <option value="completed" <?= $order['order_status'] == 'completed' ? 'selected' : '' ?>>Completed</option>

                    <option value="delivered" <?= $order['order_status'] == 'delivered' ? 'selected' : '' ?>>Delivered</option>

                </select>
            </div>
            <i class="ri-arrow-down-s-line" style="color: var(--text-muted); font-size: 1.5rem;"></i>
        </div>
    </div>

    <!-- Actions -->
    <div style="display: flex; gap: 1rem; margin-top: 1rem; margin-bottom: 2rem;">
        <?php if ($order['order_status'] === 'delivered'): ?>
            <button disabled
                style="flex: 1; background: var(--surface); color: #94a3b8; border: 1px solid #cbd5e1; padding: 1rem; border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: not-allowed; opacity: 0.6;">
                Report Issue
            </button>
            <button disabled
                style="flex: 2; background: #cbd5e1; color: #64748b; border: none; padding: 1rem; border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: not-allowed; opacity: 0.8;">
                Update & Save
            </button>
        <?php else: ?>
            <button onclick="document.getElementById('issueModal').style.display = 'flex';"
                style="flex: 1; background: var(--surface); color: var(--danger); border: 1px solid var(--danger); padding: 1rem; border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: pointer;">
                Report Issue
            </button>
            <button onclick="updateTaskStatus(<?= $order_id ?>)"
                style="flex: 2; background: var(--primary); color: white; border: none; padding: 1rem; border-radius: 12px; font-weight: 600; font-size: 1rem; box-shadow: 0 4px 10px rgba(219, 39, 119, 0.3); cursor: pointer;">
                Update & Save
            </button>
        <?php endif; ?>
    </div>

</div>

<!-- Report Issue Modal -->
<div id="issueModal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; align-items:flex-end;">
    <div
        style="background: white; width:100%; border-radius: 24px 24px 0 0; padding: 1.5rem; animation: slideUp 0.3s ease-out;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 800; color: #1e293b;">Report an Issue</h3>
            <button onclick="document.getElementById('issueModal').style.display = 'none';"
                style="border: none; background: #f1f5f9; width: 32px; height: 32px; border-radius: 50%; color: #64748b;">&times;</button>
        </div>
        <form id="reportIssueForm">
            <input type="hidden" name="order_id" value="<?= $order_id ?>">
            <div style="margin-bottom: 1rem;">
                <label
                    style="display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 0.5rem;">Issue
                    Category</label>
                <select name="issue_type"
                    style="width: 100%; padding: 0.85rem; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; appearance: none; background: #f8fafc;">
                    <option value="measurement_mismatch">Measurement Mismatch</option>
                    <option value="fabric_damaged">Fabric Damaged</option>
                    <option value="material_missing">Material Missing (Buttons/Zipper)</option>
                    <option value="design_unclear">Design Unclear</option>
                    <option value="wrong_assignment">Assigned to Wrong Person</option>
                    <option value="other">Other Issue</option>
                </select>
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label
                    style="display: block; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 0.5rem;">Describe
                    the Problem</label>
                <textarea name="description" placeholder="Explain the issue for the supervisor..." rows="4"
                    style="width: 100%; padding: 0.85rem; border: 1px solid #e2e8f0; border-radius: 12px; outline: none; resize: none;"></textarea>
                <div id="descriptionError" style="color:red; font-size:0.8rem; margin-top:5px;"></div>
            </div>
            <button type="submit"
                style="width: 100%; background: #e11d48; color: white; border: none; padding: 1rem; border-radius: 12px; font-weight: 700; font-size: 1rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                <i class="ri-error-warning-line"></i> Submit Report
            </button>
        </form>
    </div>
</div>

<a href="tasks.php"
    style="position: fixed; bottom: 90px; left: 1.25rem; width: 48px; height: 48px; background: var(--text-main); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px rgba(0,0,0,0.2); text-decoration: none; z-index: 100;">
    <i class="ri-arrow-left-line" style="font-size: 1.5rem;"></i>
</a>

<!-- Image Viewer Modal -->
<div id="imageModal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.95); z-index:2000; align-items:center; justify-content:center; padding:1rem;">
    <button onclick="closeImageModal()"
        style="position:absolute; top:1.5rem; right:1.5rem; border:none; background:white; width:40px; height:40px; border-radius:50%; font-size:1.5rem; display:flex; align-items:center; justify-content:center; cursor:pointer; box-shadow: 0 4px 10px rgba(0,0,0,0.5); z-index: 2001;">&times;</button>
    <div id="modalImagesContainer"
        style="width:100%; height:85%; display:flex; flex-direction:column; gap:1.5rem; overflow-y:auto; padding:1rem; scroll-behavior: smooth;">
        <!-- Images injected here -->
    </div>
</div>

<script>
    function showImages(paths) {
        const container = document.getElementById('modalImagesContainer');
        container.innerHTML = '';

        paths.forEach(path => {
            const img = document.createElement('img');
            // Correct path: DB stores 'uploads/orders/...'
            // From 'employee/', we need '../'
            img.src = '../' + path;
            img.style.width = '100%';
            img.style.borderRadius = '16px';
            img.style.marginBottom = '0.5rem';
            img.style.boxShadow = '0 10px 20px rgba(0,0,0,0.4)';
            container.appendChild(img);
        });

        document.getElementById('imageModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeImageModal() {
        document.getElementById('imageModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Report Issue AJAX
    document.getElementById('reportIssueForm').addEventListener('submit', function (e) {

        e.preventDefault();

        let description = document.querySelector('textarea[name="description"]').value.trim();

        let error = document.getElementById('descriptionError');

        if (description == '') {

            error.innerHTML = "Describe the Problem field is required";

            return false;

        } else {

            error.innerHTML = "";
        }

        const formData = new FormData(this);

        fetch('report-issue.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {

                if (data.status === 'success') {

                    document.getElementById('issueModal').style.display = 'none';

                    Swal.fire({
                        icon: 'success',
                        title: 'Issue Reported',
                        text: 'The supervisor has been notified.',
                        confirmButtonColor: '#e11d48'
                    });

                    this.reset();

                } else {

                    Swal.fire('Error', data.message || 'Something went wrong', 'error');
                }

            })
            .catch(err => {

                console.error(err);

                Swal.fire('Error', 'Connection failed', 'error');

            });

    });

    // Update Task Status
    function updateTaskStatus(orderId) {
        const status = document.getElementById('taskStatus').value;

        fetch('update-task-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `order_id=${orderId}&status=${status}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Status Updated',
                        text: 'The work progress has been saved.',
                        confirmButtonColor: '#db2777'
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message || 'Update failed', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Connection failed', 'error');
            });
    }
</script>

<?php include 'includes/bottom-nav.php'; ?>