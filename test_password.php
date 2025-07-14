<?php
$input_password = 'admin123';
$hash = '$2y$10$kD5KQi8UqTRhAx1uBgtceOHL.H7bdEJTeJCSfV20vV5TR6Xcc0P5a';

if (password_verify($input_password, $hash)) {
    echo "Parola este corectă!";
} else {
    echo "Parola este greșită!";
}
