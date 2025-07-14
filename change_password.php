<?php
$new_password = 'admin123';
$hash = password_hash($new_password, PASSWORD_DEFAULT);
echo $hash;
