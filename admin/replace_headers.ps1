$files = Get-ChildItem -Path "c:\Workspace\8.2.1112\htdocs\sogasu_05_05\admin" -Filter "*.php" -File
foreach ($f in $files) {
    $content = [System.IO.File]::ReadAllText($f.FullName)
    $newContent = [regex]::Replace($content, '(?s)<header class="top-header".*?</header>', "<?php include 'includes/topbar.php'; ?>")
    if ($content -ne $newContent) {
        [System.IO.File]::WriteAllText($f.FullName, $newContent)
        Write-Host "Updated $($f.Name)"
    }
}
Write-Host "Done"
