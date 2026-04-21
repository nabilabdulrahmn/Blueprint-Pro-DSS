<?php
// DeKukis — Shared Database Connection
// Prevent PHP 8.1+ from throwing fatal exceptions (which cause HTTP 500 errors on InfinityFree)
mysqli_report(MYSQLI_REPORT_OFF);

// Use @ to suppress raw PHP warnings and handle them gracefully below
$conn = @mysqli_connect("localhost", "root", "", "dekukis_db");

if (!$conn) {
    $db_error = "Database Connection Failed: " . mysqli_connect_error();
} else {
    $db_error = false;
    mysqli_set_charset($conn, "utf8mb4");
}
