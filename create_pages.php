<?php
$source = 'about_vision.php';
$files = [
    'doctors.php',
    'about_history.php',
    'about_executives.php',
    'about_strategy.php',
    'gallery.php',
    'downloads.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        copy($source, $file);
        echo "Created $file\n";
    }
}
?>
