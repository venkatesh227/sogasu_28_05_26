<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$pageTitle = "Help & Support - Sogasu";
$headerTitle = "Help & Support";
$activePage = "profile";

$savedData = [];
$stmt = $pdo->prepare("
    SELECT id, data
    FROM customer_profiles 
    WHERE user_id = ? 
    AND section_type = 'support'
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$userId]);
$result = $stmt->fetch();
if ($result) {
    $savedData = json_decode($result['data'], true) ?? [];
    $savedData['id'] = $result['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'issue_type' => $_POST['issue_type'] ?? 'general',
        'subject' => $_POST['subject'] ?? '',
        'message' => $_POST['message'] ?? '',
        'contact_email' => $_POST['contact_email'] ?? '',
        'contact_phone' => $_POST['contact_phone'] ?? '',
        'preferred_contact' => $_POST['preferred_contact'] ?? 'email',
    ];

    $supportId = $_POST['support_id'] ?? '';

    if ($supportId) {

        // UPDATE EXISTING REQUEST

        $stmt = $pdo->prepare("
        UPDATE customer_profiles
        SET data = ?, updated_at = NOW()
        WHERE id = ?
        AND user_id = ?
        AND section_type = 'support'
    ");

        $stmt->execute([
            json_encode($formData),
            $supportId,
            $userId
        ]);

        $responseMessage = 'Support request updated successfully!';
        $responseTitle = 'Request Updated!';

    } else {

        // CREATE NEW REQUEST

        $stmt = $pdo->prepare("
        INSERT INTO customer_profiles
        (user_id, section_type, data)
        VALUES (?, 'support', ?)
    ");

        $stmt->execute([
            $userId,
            json_encode($formData)
        ]);

        $responseMessage = 'Your support request has been submitted successfully!';
        $responseTitle = 'Request Submitted!';
    }
    header('Content-Type: application/json');

    echo json_encode([
        'success' => true,
        'title' => $responseTitle,
        'message' => $responseMessage
    ]);

    exit();
}

include 'includes/header.php';
?>

<div class="container">

    <div class="card" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
        <a href="profile.php"
            style="background: var(--background); border: none; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); text-decoration: none; font-size: 1.2rem;">
            <i class="ri-arrow-left-line"></i>
        </a>
        <div>
            <h2 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main);">Help & Support</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Get help or report an issue</p>
        </div>
    </div>

    <div class="card">
        <form id="supportForm" method="POST">
            <input type="hidden" name="support_id" value="<?php echo $savedData['id'] ?? ''; ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">

                <div>
                    <label class="input-label">Issue Type</label>
                    <select name="issue_type" class="form-input">
                        <option value="general" <?php echo ($savedData['issue_type'] ?? '') === 'general' ? 'selected' : ''; ?>>General Question</option>
                        <option value="order_issue" <?php echo ($savedData['issue_type'] ?? '') === 'order_issue' ? 'selected' : ''; ?>>Order Issue</option>
                        <option value="payment" <?php echo ($savedData['issue_type'] ?? '') === 'payment' ? 'selected' : ''; ?>>Payment Issue</option>
                        <option value="delivery" <?php echo ($savedData['issue_type'] ?? '') === 'delivery' ? 'selected' : ''; ?>>Delivery Issue</option>
                        <option value="product_quality" <?php echo ($savedData['issue_type'] ?? '') === 'product_quality' ? 'selected' : ''; ?>>Product Quality</option>
                        <option value="other" <?php echo ($savedData['issue_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div>
                    <label class="input-label">Preferred Contact</label>
                    <select name="preferred_contact" class="form-input">
                        <option value="email" <?php echo ($savedData['preferred_contact'] ?? '') === 'email' ? 'selected' : ''; ?>>Email</option>
                        <option value="phone" <?php echo ($savedData['preferred_contact'] ?? '') === 'phone' ? 'selected' : ''; ?>>Phone</option>
                        <option value="whatsapp" <?php echo ($savedData['preferred_contact'] ?? '') === 'whatsapp' ? 'selected' : ''; ?>>WhatsApp</option>
                    </select>
                </div>

                <div>
                    <label class="input-label">Email</label>
                    <input type="email" name="contact_email" class="form-input"
                        value="<?php echo htmlspecialchars($savedData['contact_email'] ?? ''); ?>"
                        placeholder="your@email.com" required>
                </div>

                <div>
                    <label class="input-label">Phone (Optional)</label>
                    <input type="tel" name="contact_phone" class="form-input"
                        value="<?php echo htmlspecialchars($savedData['contact_phone'] ?? ''); ?>"
                        placeholder="+91 98765 43210">
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="input-label">Subject</label>
                    <input type="text" name="subject" class="form-input"
                        value="<?php echo htmlspecialchars($savedData['subject'] ?? ''); ?>"
                        placeholder="Brief subject of your issue" required>
                </div>

                <div style="grid-column: 1 / -1;">
                    <label class="input-label">Message</label>
                    <textarea name="message" class="form-input" rows="6"
                        placeholder="Please describe your issue in detail..."
                        required><?php echo htmlspecialchars($savedData['message'] ?? ''); ?></textarea>
                </div>

            </div>

            <button type="submit" class="btn-primary" style="width: 100%; font-size: 1.1rem; padding: 1rem;">
                <i class="ri-send-plane-line"></i>

                <?php echo !empty($savedData['id']) ? 'Update Request' : 'Submit Request'; ?>
            </button>
        </form>
    </div>

</div>

<style>
    .input-label {
        font-size: 0.85rem;
        color: var(--text-muted);
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }

    .form-input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 1rem;
        font-family: inherit;
        background: var(--background);
        outline: none;
    }

    .form-input:focus {
        border-color: var(--primary);
        background: white;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.getElementById('supportForm')?.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('support.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: data.title,
                        text: data.message,
                        confirmButtonColor: '#db2777'
                    }).then(() => {
                        window.location.href = "profile.php?section=support"; // ✅ FIX
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Failed to submit support request',
                    confirmButtonColor: '#db2777'
                });
            });
    });
</script>

<?php include 'includes/bottom-nav.php'; ?>