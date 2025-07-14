<?php
$password = "admin123";
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Hash-ul pentru parola '$password' este:<br>";
echo $hash;
