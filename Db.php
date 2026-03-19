<?php
$conn = new mysqli('127.0.0.1', 'root', 'kezasabrine6^', 'bank_db',3300);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
   // die('<p style="font-family:sans-serif;color:#341100;padding:2rem">Connection failed: ' . htmlspecialchars($conn->connect_error) . '</p>');
}
$conn->set_charset('utf8mb4');