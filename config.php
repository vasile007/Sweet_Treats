<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sweet_treats_db');

define('ADMIN_EMAIL', 'admin@sweettreats.com');
define('ADMIN_PASSWORD_HASH', '$2y$10$WvQts.yB8KSnBhM4UXaEvOxEj3U5kFV45AfDKqRMNl0T0GYOXUlOS'); 

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Faill connection to db: " . $conn->connect_error);
}
