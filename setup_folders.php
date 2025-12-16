<?php
// setup_folders.php
// Run this once to create the directory structure for uploads

$baseDir = __DIR__ . '/uploads';

$folders = [
    '/users',
    '/reviews',
    '/reports',
    '/businesses',
    '/products',
    '/pricelists',
    '/admin'
];

echo "<h2>Setting up Upload Directories...</h2>";

if (!file_exists($baseDir)) {
    if (mkdir($baseDir, 0777, true)) {
        echo "<p style='color:green'>Created base directory: <strong>/uploads</strong></p>";
    } else {
        echo "<p style='color:red'>Failed to create base directory. Check permissions.</p>";
    }
} else {
    echo "<p style='color:orange'>Base directory <strong>/uploads</strong> already exists.</p>";
}

foreach ($folders as $folder) {
    $path = $baseDir . $folder;
    if (!file_exists($path)) {
        if (mkdir($path, 0777, true)) {
            echo "<p style='color:green'>Created: <strong>/uploads$folder</strong></p>";
        } else {
            echo "<p style='color:red'>Failed to create: <strong>/uploads$folder</strong></p>";
        }
    } else {
        echo "<p style='color:blue'>Exists: <strong>/uploads$folder</strong></p>";
    }
}

echo "<h3>Done! You can now delete this file.</h3>";
?>