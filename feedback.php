<?php
session_start();
require_once 'config.php';

// Display any message first
if (!empty($_SESSION['message'])) {
    $color = $_SESSION['message_type'] === 'success' ? 'green' : 'red';
    echo "<p style='color: $color; text-align:center; font-weight:bold;'>{$_SESSION['message']}</p>";
    unset($_SESSION['message'], $_SESSION['message_type']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Handle admin delete
    if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true && isset($_POST['delete_id'])) {
        $delete_id = intval($_POST['delete_id']);

        $stmt = $conn->prepare("DELETE FROM customer_feedback WHERE id = ?");
        $stmt->bind_param("i", $delete_id);

        if ($stmt->execute()) {
            $_SESSION['message'] = 'Feedback deleted successfully.';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error deleting feedback: ' . $stmt->error;
            $_SESSION['message_type'] = 'error';
        }

        $stmt->close();
        $conn->close();
        header('Location: index.php?page=feedback');
        exit();
    }

    // 2. Handle normal feedback submission

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Validate name: only letters and spaces allowed
    if (!preg_match("/^[a-zA-Z ]+$/", $name)) {
        $_SESSION['message'] = 'Name must contain only letters and spaces.';
        $_SESSION['message_type'] = 'error';
        header('Location: index.php?page=feedback');
        exit();
    }

    // Validate email if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = 'Please enter a valid email address.';
        $_SESSION['message_type'] = 'error';
        header('Location: index.php?page=feedback');
        exit();
    }

    // Check required fields
    if (empty($name) || empty($message)) {
        $_SESSION['message'] = 'Name and feedback message are required.';
        $_SESSION['message_type'] = 'error';
        header('Location: index.php?page=feedback');
        exit();
    }

    // Prepare and execute insert statement
    $stmt = $conn->prepare("INSERT INTO customer_feedback (name, email, message) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $message);

    if ($stmt->execute()) {
        $_SESSION['message'] = 'Thank you for your feedback! It has been submitted successfully.';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Error submitting feedback: ' . $stmt->error;
        $_SESSION['message_type'] = 'error';
    }
    $stmt->close();
    $conn->close();

    header('Location: index.php?page=feedback');
    exit();
} else {
    $_SESSION['message'] = 'Invalid access to feedback submission.';
    $_SESSION['message_type'] = 'error';
    header('Location: index.php?page=home');
    exit();
}
