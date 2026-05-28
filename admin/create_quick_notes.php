<?php
include '../includes/db.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS quick_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        note_text VARCHAR(100) NOT NULL,
        color_bg VARCHAR(20) DEFAULT '#f8fafc',
        color_border VARCHAR(20) DEFAULT '#e2e8f0',
        color_text VARCHAR(20) DEFAULT '#475569',
        status TINYINT(1) DEFAULT 1,
        is_deleted TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Seed with initial data from screenshot
    $check = $pdo->query("SELECT COUNT(*) FROM quick_notes")->fetchColumn();
    if ($check == 0) {
        $seeds = [
            ['Side Zipper', '#fee2e2', '#fecaca', '#991b1b'],
            ['Back Zipper', '#f0fdf4', '#dcfce7', '#166534'],
            ['Back Rope', '#ecfeff', '#cffafe', '#155e75'],
            ['Hip Rope', '#fdf2f8', '#fce7f3', '#9d174d']
        ];
        $stmt = $pdo->prepare("INSERT INTO quick_notes (note_text, color_bg, color_border, color_text) VALUES (?, ?, ?, ?)");
        foreach ($seeds as $s) {
            $stmt->execute($s);
        }
    }
    echo "Success: Table created and seeded.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
