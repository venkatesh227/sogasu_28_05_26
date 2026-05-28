<?php
session_start();
include '../includes/db.php';

$stmt = $pdo->query("SELECT * FROM branches WHERE is_deleted = 0 ORDER BY id DESC");

$branches = $stmt->fetchAll();

$pageTitle = "Branches - Sogasu";
$activePage = "branches";
include 'includes/header.php';
?>

<main class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; ">
        <div>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">Branches</h2>
            <p class="text-muted">Manage store locations and details</p>
        </div>
        <button class="btn btn-primary" onclick="window.location.href='add-branch.php'"><i class="ri-store-3-line"></i>
            Add New Branch</button>
    </div>

    <div class="cards-grid"
        style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem;">

        <?php if (!empty($branches)): ?>
            <?php foreach ($branches as $row): ?>

                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">

                    <!-- HEADER COLOR (FROM DB) -->
                    <div style="height: 100px; background: <?= htmlspecialchars($row['color_theme']) ?>; position: relative;">

                        <div
                            style="position: absolute; bottom: -20px; left: 20px; width: 50px; height: 50px; background: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <i class="ri-store-2-fill"
                                style="font-size: 1.5rem; color: <?= htmlspecialchars($row['color_theme']) ?>;"></i>
                        </div>

                        <div style="position: absolute; top: 10px; right: 10px;">
                            <?php if ($row['status'] == 'active'): ?>
                                <span class="badge badge-success">Active</span>
                            <?php elseif ($row['status'] == 'inactive'): ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Maintenance</span>
                            <?php endif; ?>
                        </div>

                    </div>

                    <div style="padding: 2.5rem 1.5rem 1.5rem 1.5rem;">

                        <!-- NAME -->
                        <h3 style="font-size: 1.125rem; font-weight: 600; color: #0f172a; margin-bottom: 0.25rem;">
                            <?= htmlspecialchars($row['branch_name']) ?>
                        </h3>

                        <!-- ADDRESS -->
                        <p style="color: #64748b; font-size: 0.875rem; margin-bottom: 1rem;">
                            <i class="ri-map-pin-line"></i>
                            <?= htmlspecialchars($row['address']) ?>
                        </p>

                        <!-- DETAILS -->
                        <div
                            style="border-top: 1px solid #f1f5f9; padding-top: 1rem; margin-top: 1rem; display: flex; flex-direction: column; gap: 0.75rem;">


                            <div style="display: flex; justify-content: space-between; font-size: 0.875rem;">
                                <span class="text-muted">Contact</span>
                                <span style="font-weight: 500;">
                                    <?= htmlspecialchars($row['phone']) ?>
                                </span>
                            </div>

                        </div>

                        <!-- ACTIONS -->
                        <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem;">

                            <a href="add-branch.php?id=<?= $row['id'] ?>" class="btn"
                                style="flex: 1; border: 1px solid #e2e8f0; background: white;">
                                Edit
                            </a>

                            <a href="#" onclick="confirmDelete(<?= $row['id'] ?>)" class="btn"
                                style="flex: 1; border: 1px solid #e2e8f0; background: white; color: #dc2626;">
                                Close
                            </a>

                        </div>

                    </div>
                </div>

            <?php endforeach; ?>

        <?php else: ?>
            <p>No branches found</p>
        <?php endif; ?>

    </div>

</main>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (!empty($_SESSION['success'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?= $_SESSION['success'] === "updated"
                ? "Branch updated successfully"
                : ($_SESSION['success'] === "deleted"
                    ? "Branch deleted successfully"
                    : "Branch added successfully") ?>'
        });
    </script>
    <?php unset($_SESSION['success']); endif; ?>
<script>
    function confirmDelete(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "This branch will be removed!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "delete-branch.php?id=" + id;
            }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>