<?php
require 'db.php';
$sql = file_get_contents('migrate_v3.sql');
if (mysqli_query($conn, $sql)) {
    echo "Migration v3 successful.\n";
} else {
    echo "Error: " . mysqli_error($conn) . "\n";
}
?>
