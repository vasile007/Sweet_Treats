<?php
session_start();
require_once 'config.php';

// Function to safely output HTML entities
if (!function_exists('html_safe')) {
    function html_safe($string)
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

// Directory for product images. Make sure this exists and is writable!
$upload_dir = 'uploads/product_images/';

// Create the upload directory if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true); // Create recursively and set permissions
}

// Function to handle image upload
function handle_image_upload($file_input_name, $upload_dir, $current_image_path = null)
{
    // Check if a file was actually uploaded
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] === UPLOAD_ERR_NO_FILE) {
        return $current_image_path; // No new file, keep current path or return null if new item
    }

    $file = $_FILES[$file_input_name];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['message'] = 'Error uploading file: Code ' . $file['error'];
        $_SESSION['message_type'] = 'error';
        return null;
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_file_size = 5 * 1024 * 1024; // 5 MB

    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        $_SESSION['message'] = 'Disallowed file type. Please upload an image (JPG, PNG, GIF).';
        $_SESSION['message_type'] = 'error';
        return null;
    }

    // Validate file size
    if ($file['size'] > $max_file_size) {
        $_SESSION['message'] = 'File is too large. Maximum allowed size is 5MB.';
        $_SESSION['message_type'] = 'error';
        return null;
    }

    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_file_name = uniqid('prod_', true) . '.' . $file_extension;
    $destination_path = $upload_dir . $new_file_name;

    if (move_uploaded_file($file['tmp_name'], $destination_path)) {
        // If there was an old image and a new one is uploaded successfully, delete the old one
        if ($current_image_path && file_exists($current_image_path) && $current_image_path !== $destination_path) {
            unlink($current_image_path);
        }
        return $destination_path;
    } else {
        $_SESSION['message'] = 'Error moving the uploaded file.';
        $_SESSION['message_type'] = 'error';
        return null;
    }
}

// Redirect if not logged in for admin actions (except login itself)
$action = $_POST['action'] ?? '';

if ($action !== 'login' && (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true)) {
    $_SESSION['message'] = 'Unauthorized access. Please log in.';
    $_SESSION['message_type'] = 'error';
    header('Location: index.php?page=admin_login');
    exit();
}


switch ($action) {
    case 'login':
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $_SESSION['message'] = 'Email and password are required.';
            $_SESSION['message_type'] = 'error';
            header('Location: index.php?page=admin_login');
            exit();
        }

        $stmt = $conn->prepare("SELECT password_hash FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_email'] = $email;
                $_SESSION['message'] = 'Admin login successful!';
                $_SESSION['message_type'] = 'success';
                header('Location: index.php?page=admin');
            } else {
                $_SESSION['message'] = 'Incorrect password.';
                $_SESSION['message_type'] = 'error';
                header('Location: index.php?page=admin_login');
            }
        } else {
            $_SESSION['message'] = 'Email does not exist.';
            $_SESSION['message_type'] = 'error';
            header('Location: index.php?page=admin_login');
        }
        exit();
        break;


    case 'add_menu_item':
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $price = $_POST['price'] ?? '';

        if (empty($name) || empty($description) || empty($price)) {
            $_SESSION['message'] = 'All menu item fields are required.';
            $_SESSION['message_type'] = 'error';
            header('Location: index.php?page=admin');
            exit();
        }

        // Handle image upload
        $image_path = handle_image_upload('product_image', $upload_dir);

        // Check if there was an upload error during handle_image_upload
        if ($image_path === null && isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            // An error message was already set by handle_image_upload
            header('Location: index.php?page=admin');
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO menu_items (name, description, price, image_path) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $name, $description, $price, $image_path); // s=string, d=double, s=string (for image_path)

        if ($stmt->execute()) {
            $_SESSION['message'] = 'Menu item added successfully!';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error adding menu item: ' . $stmt->error;
            $_SESSION['message_type'] = 'error';
            // If an image was uploaded but DB insert failed, delete the image
            if ($image_path && file_exists($image_path)) {
                unlink($image_path);
            }
        }
        $stmt->close();
        header('Location: index.php?page=admin');
        exit();
        break;

    case 'update_menu_item':
        $id = $_POST['id'] ?? null;
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $price = $_POST['price'] ?? '';
        $current_image_path = $_POST['current_image_path'] ?? null; // Get current image path from hidden field

        if (empty($id) || empty($name) || empty($description) || empty($price)) {
            $_SESSION['message'] = 'All fields are required for updating.';
            $_SESSION['message_type'] = 'error';
            header('Location: index.php?page=admin');
            exit();
        }

        // Handle new image upload or keep old one
        $new_image_path = handle_image_upload('product_image', $upload_dir, $current_image_path);

        // Check if there was an upload error during handle_image_upload
        if ($new_image_path === null && isset($_FILES['product_image']) && $_FILES['product_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            // An error message was already set by handle_image_upload
            header('Location: index.php?page=admin');
            exit();
        }

        // Prepare update statement with image_path
        $stmt = $conn->prepare("UPDATE menu_items SET name = ?, description = ?, price = ?, image_path = ? WHERE id = ?");
        $stmt->bind_param("ssdss", $name, $description, $price, $new_image_path, $id); // s=string, d=double, s=string, i=integer

        if ($stmt->execute()) {
            $_SESSION['message'] = 'Menu item updated successfully!';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error updating menu item: ' . $stmt->error;
            $_SESSION['message_type'] = 'error';
            // If new image was uploaded but DB update failed, delete the new image
            if ($new_image_path && $new_image_path !== $current_image_path && file_exists($new_image_path)) {
                unlink($new_image_path);
            }
        }
        $stmt->close();
        header('Location: index.php?page=admin');
        exit();
        break;

    case 'delete_menu_item':
        $id = $_POST['id'] ?? null;
        $image_path_to_delete = $_POST['image_path'] ?? null; // Get image path from hidden input

        if (empty($id)) {
            $_SESSION['message'] = 'Menu item ID is missing for deletion.';
            $_SESSION['message_type'] = 'error';
            header('Location: index.php?page=admin');
            exit();
        }

        // Delete the record from the database first
        $stmt = $conn->prepare("DELETE FROM menu_items WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $_SESSION['message'] = 'Menu item deleted successfully!';
            $_SESSION['message_type'] = 'success';

            // If a valid image path exists and the file exists, delete the physical file
            if ($image_path_to_delete && file_exists($image_path_to_delete)) {
                unlink($image_path_to_delete);
            }
        } else {
            $_SESSION['message'] = 'Error deleting menu item: ' . $stmt->error;
            $_SESSION['message_type'] = 'error';
        }
        $stmt->close();
        header('Location: index.php?page=admin');
        exit();
        break;

    default:
        $_SESSION['message'] = 'Unknown action.';
        $_SESSION['message_type'] = 'error';
        header('Location: index.php?page=admin');
        exit();
        break;
}

$conn->close();
