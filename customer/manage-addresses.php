<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location:index.php");
    exit();
}

$pageTitle = "My Addresses";
$headerTitle = "My Addresses";
$activePage = "profile";

$userId = $_SESSION['user_id'];
if (isset($_GET['default']) && is_numeric($_GET['default'])) {

    $pdo->prepare("
        UPDATE customer_addresses
        SET is_default = 0,
            updated_at = ?
        WHERE user_id = ?
    ")->execute([
                date('Y-m-d H:i:s'),
                $userId
            ]);

    $pdo->prepare("
        UPDATE customer_addresses
        SET is_default = 1,
            updated_at = ?
        WHERE id = ?
        AND user_id = ?
    ")->execute([
                date('Y-m-d H:i:s'),
                $_GET['default'],
                $userId
            ]);

    $_SESSION['success_message'] = "Default address updated.";

    header("Location:manage-addresses.php");
    exit();
}

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {

    $stmt = $pdo->prepare("
        UPDATE customer_addresses
        SET
            is_deleted = 1,
            updated_at = ?
        WHERE id = ?
        AND user_id = ?
    ");

    $stmt->execute([
        date('Y-m-d H:i:s'),
        $_GET['delete'],
        $userId
    ]);

    $_SESSION['success_message'] = "Address deleted successfully.";

    header("Location:manage-addresses.php");
    exit();

}

$stmt = $pdo->prepare("
SELECT *
FROM customer_addresses
WHERE user_id=?
AND is_deleted=0
ORDER BY
is_default DESC,
id DESC
");

$stmt->execute([$userId]);

$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container">

    <div style="display:flex;justify-content:flex-end;margin-bottom:15px;">

        <a href="add-address.php" class="btn-primary">
            <i class="ri-add-line"></i>
            &nbsp;
            Add Address
        </a>

    </div>

    <?php if (empty($addresses)) { ?>

        <div class="card" style="text-align:center;padding:40px 20px;">

            <i class="ri-map-pin-line" style="font-size:50px;color:#ccc;"></i>

            <h3>No Address Found</h3>

            <p style="margin-top:10px;color:#777;">
                Add your first address.
            </p>

        </div>

    <?php } else { ?>

        <?php foreach ($addresses as $row) { ?>

            <div class="card">

                <div style="display:flex;justify-content:space-between;align-items:center;">

                    <div>

                        <strong>

                            <?php

                            if ($row['address_type'] == 'Home') {

                                echo "🏠 Home";

                            } elseif ($row['address_type'] == 'Work') {

                                echo "🏢 Work";

                            } else {

                                echo "📍 Other";

                            }

                            ?>

                        </strong>

                        <?php if ($row['is_default']) { ?>

                            <span class="badge completed">
                                Default
                            </span>

                        <?php } else { ?>

                            <a href="?default=<?= $row['id'] ?>" style="
                            margin-left:10px;
                            font-size:13px;
                            text-decoration:none;
                            color:var(--primary);
                            font-weight:600;
                            ">

                                Make Default

                            </a>


                        <?php } ?>

                    </div>

                    <div>

                        <a href="add-address.php?id=<?= $row['id'] ?>" style="text-decoration:none;">

                            <i class="ri-edit-line" style="font-size:20px;"></i>

                        </a>

                        &nbsp;&nbsp;

                        <a onclick="return confirm('Delete address?')" href="?delete=<?= $row['id'] ?>"
                            style="color:red;text-decoration:none;">

                            <i class="ri-delete-bin-line" style="font-size:20px;"></i>

                        </a>

                    </div>

                </div>

                <div style="margin-top:15px;line-height:1.8;">

                    <b><?= htmlspecialchars($row['full_name']) ?></b>

                    <br>

                    <?= htmlspecialchars($row['phone']) ?>

                    <br><br>

                    <?= htmlspecialchars($row['house_no']) ?>

                    <?php if ($row['apartment']) { ?>

                        ,
                        <?= htmlspecialchars($row['apartment']) ?>

                    <?php } ?>

                    <br>

                    <?= htmlspecialchars($row['landmark']) ?>

                    <br>

                    <?= htmlspecialchars($row['area']) ?>

                    <br>

                    <?= htmlspecialchars($row['city']) ?>

                    -
                    <?= htmlspecialchars($row['pincode']) ?>

                    <br>

                    <?= htmlspecialchars($row['state']) ?>

                </div>

            </div>

        <?php } ?>
    <?php } ?>

</div>

<?php include 'includes/bottom-nav.php'; ?>