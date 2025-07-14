<?php




session_start(); // Start PHP session for managing login state

// Include database configuration
require_once 'config.php'; // Ensure config.php contains the database connection ($conn)

// Function to safely output HTML entities
function html_safe($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// ADMIN CREDENTIALS (IMPORTANT: CHANGE THESE FOR PRODUCTION!)
$admin_email = 'admin@sweettreats.com';
// Generate this hash once and paste it here. Example: echo password_hash('parola_admin_aici', PASSWORD_DEFAULT);
// DO NOT leave 'parola_mea_secreta_aici' as the actual password!
$admin_password_hash = password_hash('admin123', PASSWORD_DEFAULT); // <-- SCHIMBĂ ACEASTA CU PAROLA TA REALĂ!
// Apoi, generează hash-ul și înlocuiește linia cu hash-ul generat.


// Handle POST request for feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'feedback') {
    // Retrieve form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');


    if (!preg_match("/^[a-zA-Z ]+$/", $name)) {
        $_SESSION['message'] = 'Name must contain only letters and spaces.';
        $_SESSION['message_type'] = 'error';
        header('Location: index.php?page=feedback');
        exit();
    }


    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['message'] = 'Please enter a valid email address.';
        $_SESSION['message_type'] = 'error';
        header('Location: index.php?page=feedback');
        exit();
    }

    if ($name === '' || $message === '') {
        $_SESSION['message'] = 'Name and feedback are required.';
        $_SESSION['message_type'] = 'error';
        header('Location: ?page=feedback');
        exit();
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO customer_feedback (name, email, message) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $message);

    if ($stmt->execute()) {
        $_SESSION['message'] = 'Thank you for your feedback!';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'An error occurred. Please try again.';
        $_SESSION['message_type'] = 'error';
    }

    $stmt->close();

    // Redirect to prevent form resubmission
    header('Location: ?page=feedback');
    exit();
}

// Handle Admin Login and Admin Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $_SESSION['message'] = 'Email and password are required for login.';
            $_SESSION['message_type'] = 'error';
            header('Location: ?page=admin_login');
            exit();
        }

        if ($email === $admin_email && password_verify($password, $admin_password_hash)) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['message'] = 'Logged in as administrator.';
            $_SESSION['message_type'] = 'success';
            header('Location: ?page=admin');
            exit();
        } else {
            $_SESSION['message'] = 'Invalid email or password.';
            $_SESSION['message_type'] = 'error';
            header('Location: ?page=admin_login');
            exit();
        }
    }

    // All other admin actions require login
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        $_SESSION['message'] = 'Access denied. Please log in as an administrator.';
        $_SESSION['message_type'] = 'error';
        header('Location: ?page=admin_login');
        exit();
    }

    if ($_POST['action'] === 'add_menu_item' || $_POST['action'] === 'update_menu_item') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $item_id = intval($_POST['id'] ?? 0); // Only set for update

        if (empty($name) || empty($description) || $price <= 0) {
            $_SESSION['message'] = 'Name, description, and a valid price are required.';
            $_SESSION['message_type'] = 'error';
            header('Location: ?page=admin' . ($item_id ? '&action=edit_menu_item&id=' . $item_id : ''));
            exit();
        }

        $image_path = $_POST['current_image_path'] ?? null; // Keep current image if no new one is uploaded

        // Handle image upload
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "uploads/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true); // Create 'uploads' directory if it doesn't exist
            }
            $file_extension = strtolower(pathinfo($_FILES["product_image"]["name"], PATHINFO_EXTENSION));
            $new_file_name = uniqid('img_') . '.' . $file_extension; // Generate unique file name
            $target_file = $target_dir . $new_file_name;

            // Validate image type
            $check = getimagesize($_FILES["product_image"]["tmp_name"]);
            if ($check === false) {
                $_SESSION['message'] = 'File is not an image.';
                $_SESSION['message_type'] = 'error';
                header('Location: ?page=admin' . ($item_id ? '&action=edit_menu_item&id=' . $item_id : ''));
                exit();
            }

            // Check file size (e.g., 5MB max)
            if ($_FILES["product_image"]["size"] > 5000000) { // 5MB limit
                $_SESSION['message'] = 'Sorry, your image file is too large (max 5MB).';
                $_SESSION['message_type'] = 'error';
                header('Location: ?page=admin' . ($item_id ? '&action=edit_menu_item&id=' . $item_id : ''));
                exit();
            }

            // Allow certain file formats
            $allowed_extensions = ["jpg", "png", "jpeg", "gif"];
            if (!in_array($file_extension, $allowed_extensions)) {
                $_SESSION['message'] = 'Sorry, only JPG, JPEG, PNG & GIF files are allowed.';
                $_SESSION['message_type'] = 'error';
                header('Location: ?page=admin' . ($item_id ? '&action=edit_menu_item&id=' . $item_id : ''));
                exit();
            }

            if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
                // Delete old image if updating and a new one is uploaded
                if ($_POST['action'] === 'update_menu_item' && !empty($_POST['current_image_path']) && file_exists($_POST['current_image_path'])) {
                    unlink($_POST['current_image_path']);
                }
                $image_path = $target_file;
            } else {
                $_SESSION['message'] = 'Sorry, there was an error uploading your image file.';
                $_SESSION['message_type'] = 'error';
                header('Location: ?page=admin' . ($item_id ? '&action=edit_menu_item&id=' . $item_id : ''));
                exit();
            }
        }

        if ($_POST['action'] === 'add_menu_item') {
            $stmt = $conn->prepare("INSERT INTO menu_items (name, description, price, image_path) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssds", $name, $description, $price, $image_path);
            if ($stmt->execute()) {
                $_SESSION['message'] = 'Menu item added successfully!';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Error adding menu item: ' . $stmt->error;
                $_SESSION['message_type'] = 'error';
            }
        } elseif ($_POST['action'] === 'update_menu_item' && $item_id > 0) {
            $stmt = $conn->prepare("UPDATE menu_items SET name = ?, description = ?, price = ?, image_path = ? WHERE id = ?");
            $stmt->bind_param("ssdsi", $name, $description, $price, $image_path, $item_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = 'Menu item updated successfully!';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Error updating menu item: ' . $stmt->error;
                $_SESSION['message_type'] = 'error';
            }
        }
        $stmt->close();
        header('Location: ?page=admin');
        exit();
    }

    if ($_POST['action'] === 'delete_menu_item') {
        $item_id = intval($_POST['id'] ?? 0);

        if ($item_id > 0) {
            // Optional: Delete the image file from the server if it exists
            $stmt = $conn->prepare("SELECT image_path FROM menu_items WHERE id = ?");
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if (!empty($row['image_path']) && file_exists($row['image_path'])) {
                    unlink($row['image_path']); // Delete the file
                }
            }
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM menu_items WHERE id = ?");
            $stmt->bind_param("i", $item_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = 'Menu item deleted successfully!';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Error deleting menu item: ' . $stmt->error;
                $_SESSION['message_type'] = 'error';
            }
            $stmt->close();
        } else {
            $_SESSION['message'] = 'Invalid menu item ID.';
            $_SESSION['message_type'] = 'error';
        }
        header('Location: ?page=admin');
        exit();
    }
}


// Check if admin is logged in
$is_admin_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Determine current page based on GET parameter
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sweet Treats Bakery</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet" href="style.css">
</head>

<body class="font-sans text-gray-900 flex flex-col">

    <header class="bg-gradient-to-r from-red-600 via-red-500 to-red-600 shadow-lg">
        <div class="container mx-auto flex justify-between items-center py-5 px-6">
            <h1 class="text-4xl font-bold tracking-tight text-white drop-shadow-md">🍰 Sweet Treats</h1>
            <nav>
                <ul class="flex space-x-8 text-white font-medium text-lg">
                    <li><a href="?page=home" class="hover:underline hover:tracking-wider transition duration-300">Home</a></li>
                    <li><a href="?page=menu" class="hover:underline hover:tracking-wider transition duration-300">Menu</a></li>
                    <li><a href="?page=feedback" class="hover:underline hover:tracking-wider transition duration-300">Feedback</a></li>
                    <li><a href="?page=about" class="hover:underline hover:tracking-wider transition duration-300">About</a></li>

                    <?php if ($is_admin_logged_in): ?>
                        <li><a href="?page=admin" class="hover:underline hover:tracking-wider transition duration-300">Admin Panel</a></li>
                        <li><a href="?page=logout" class="hover:underline hover:tracking-wider transition duration-300">Logout</a></li>
                    <?php else: ?>
                        <li><a href="?page=admin_login" class="hover:underline hover:tracking-wider transition duration-300">Admin Login</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>


    <main id="app-container" class="container mx-auto my-8 p-4 flex-grow">
        <?php
        // Display status messages if any
        if (isset($_SESSION['message'])) {
            echo '<div class="p-3 mb-4 rounded-lg text-center ' . ($_SESSION['message_type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700') . '">' . html_safe($_SESSION['message']) . '</div>';
            unset($_SESSION['message']); // Clear message after display
            unset($_SESSION['message_type']);
        }
        ///////
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            if ($is_admin_logged_in) {
                if ($_POST['action'] === 'delete_feedback' && isset($_POST['feedback_id'])) {
                    $feedback_id = intval($_POST['feedback_id']);
                    $stmt = $conn->prepare("DELETE FROM customer_feedback WHERE id = ?");
                    $stmt->bind_param("i", $feedback_id);
                    if ($stmt->execute()) {
                        $_SESSION['message'] = 'Feedback deleted successfully.';
                        $_SESSION['message_type'] = 'success';
                    } else {
                        $_SESSION['message'] = 'Failed to delete feedback.';
                        $_SESSION['message_type'] = 'error';
                    }
                    $stmt->close();
                    header('Location: ?page=admin');
                    exit();
                }
                //////
            } else {
                $_SESSION['message'] = 'Unauthorized action.';
                $_SESSION['message_type'] = 'error';
                header('Location: ?page=admin_login');
                exit();
            }
        }



        switch ($page) {
            case 'home':
        ?>
                <div class="content-wrapper">
                    <h2 class="text-4xl md:text-5xl font-serif font-semibold text-white mb-6 tracking-wide">
                        Welcome to Sweet Treats Bakery!
                    </h2>
                    <p class="max-w-xl text-white text-lg md:text-xl mb-10 leading-relaxed">
                        Your daily dose of delicious pastries and cakes. Check out our menu and tell us what you think!
                    </p>
                    <a href="?page=menu" class="bg-red-500 hover:bg-red-600 text-white font-bold py-3 px-6 rounded-full shadow-md transition duration-300">
                        View Today's Menu
                    </a>
                </div>
            <?php
                break;

            case 'menu':
            ?>
                <section class="bg-white min-h-screen py-12 px-12 max-w-7xl mx-auto">
                    <h2 class="text-4xl font-extrabold text-red-600 text-center mb-12 tracking-wide">
                        Today's Menu
                    </h2>

                    <div class="max-w-6xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                        <?php
                        // Query the database to get all menu items, including image_path
                        $stmt = $conn->prepare("SELECT name, description, price, image_path FROM menu_items ORDER BY created_at DESC");
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            while ($produs = $result->fetch_assoc()) {
                                // Use the image from the database or a placeholder if none exists
                                $image_src = $produs['image_path'] ? html_safe($produs['image_path']) : "https://placehold.co/150x150/FEE2E2/DC2626?text=" . urlencode($produs['name']);
                        ?>
                                <article class="bg-red-50 rounded-xl shadow-lg p-6 text-center transition duration-300 ease-in-out hover:shadow-2xl hover:-translate-y-1">
                                    <img src="<?php echo $image_src; ?>" alt="<?php echo html_safe($produs['name']); ?>" class="mx-auto mb-4 rounded-lg border border-red-200 shadow-sm w-full h-40 object-cover" />
                                    <h3 class="text-xl font-semibold text-red-700 mb-2"><?php echo html_safe($produs['name']); ?></h3>
                                    <p class="text-red-600 mb-3"><?php echo html_safe($produs['description']); ?></p>
                                    <span class="text-red-800 font-extrabold"><?php echo '£' . html_safe(number_format($produs['price'], 2)); ?></span>

                                </article>
                        <?php
                            }
                        } else {
                            echo '<p class="col-span-full text-center text-gray-600 text-lg">The menu is empty for now. Check back later!</p>';
                        }
                        $stmt->close();
                        ?>
                    </div>
                </section>

            <?php
                break;


            case 'feedback':
            ?>
                <div class="p-6 max-w-4xl mx-auto">
                    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Send Us Your Feedback!</h2>

                    <form action="index.php" method="POST" class="bg-white p-6 rounded-lg shadow-lg mb-8">
                        <input type="hidden" name="form_type" value="feedback">
                        <div class="mb-4">
                            <label for="feedback-name" class="block text-gray-700 text-sm font-bold mb-2">Your Name:</label>
                            <input type="text" id="feedback-name" name="name" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-red-400" required />
                        </div>
                        <div class="mb-4">
                            <label for="feedback-email" class="block text-gray-700 text-sm font-bold mb-2">Your Email (Optional):</label>
                            <input type="email" id="feedback-email" name="email" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-red-400" />
                        </div>
                        <div class="mb-6">
                            <label for="feedback-message" class="block text-gray-700 text-sm font-bold mb-2">Your Feedback:</label>
                            <textarea id="feedback-message" name="message" rows="5" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-red-400 resize-y" required></textarea>
                        </div>
                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-bold py-3 px-6 rounded-lg shadow-md transition duration-300 w-full">
                            Submit Feedback
                        </button>
                    </form>

                    <h3 class="text-2xl font-bold text-gray-800 mb-4 text-center">Latest Customer Feedback</h3>
                    <?php
                    $stmt = $conn->prepare("SELECT name, email, message, submitted_at FROM customer_feedback ORDER BY submitted_at DESC");
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        echo '<div class="space-y-4">';
                        while ($row = $result->fetch_assoc()) {
                            echo '<div class="bg-white p-5 rounded-lg shadow-md">';
                            echo '<p class="font-semibold text-gray-800">From: ' . html_safe($row['name']) . '</p>';
                            // Email is hidden from public view
                            if (!empty($row['email'])) {
                                echo '<span class="text-gray-500 ml-2 text-sm italic">(Email Hidden)</span>';
                            }
                            echo '<p class="text-gray-700 mt-2">' . html_safe($row['message']) . '</p>';
                            echo '<p class="text-gray-500 text-sm mt-2">' . html_safe(date('d.m.Y H:i', strtotime($row['submitted_at']))) . '</p>';
                            echo '</div>';
                        }
                        echo '</div>';
                    } else {
                        echo '<p class="text-center text-gray-600 text-lg">No feedback submitted yet. Be the first!</p>';
                    }
                    $stmt->close();
                    ?>
                </div>
            <?php
                break;
            case 'about':
            ?>
                <section class="bg-white min-h-screen py-16 px-16 max-w-5xl mx-auto">
                    <h2 class="text-4xl font-extrabold text-red-600 text-center mb-12 tracking-wide">
                        About Us
                    </h2>

                    <div class="flex flex-col md:flex-row items-center gap-12">

                        <img src="uploads/product_images/about.jpg" alt="About the Bakery" class="about-small-image" />

                        <div class="md:w-1/2 text-gray-700 text-lg leading-relaxed">
                            <p>
                                At "Sweet Treats Bakery", we are dedicated to bringing you delicious, fresh, and lovingly made pastries and cakes every day. We use only natural ingredients and traditional recipes to offer you the authentic taste you deserve.
                            </p>
                            <p class="mt-6">
                                Our team of passionate bakers works with care and attention for each product, so that every bite is a sweet and memorable experience. Thank you for choosing to be a part of our story!
                            </p>
                        </div>
                    </div>
                </section>
            <?php
                break;


            case 'admin_login': // This case now matches the navigation link
                if ($is_admin_logged_in) {
                    header('Location: ?page=admin');
                    exit();
                }
            ?>
                <div class="p-6 max-w-md mx-auto bg-white rounded-lg shadow-lg mt-10">
                    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Administrator Login</h2>
                    <form action="index.php" method="POST">
                        <input type="hidden" name="action" value="login">
                        <div class="mb-4">
                            <label for="adminEmail" class="block text-gray-700 text-sm font-bold mb-2">Email:</label>
                            <input type="email" id="adminEmail" name="email" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-red-400" required />
                        </div>
                        <div class="mb-6">
                            <label for="adminPassword" class="block text-gray-700 text-sm font-bold mb-2">Password:</label>
                            <input type="password" id="adminPassword" name="password" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-red-400" required />
                        </div>
                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-bold py-3 px-6 rounded-lg shadow-md transition duration-300 w-full">
                            Login
                        </button>
                    </form>
                </div>
            <?php
                break;

            case 'admin':
                if (!$is_admin_logged_in) {
                    $_SESSION['message'] = 'Access denied. Please log in as administrator.';
                    $_SESSION['message_type'] = 'error';
                    header('Location: ?page=admin_login');
                    exit();
                }

                // === Process formular POST ===
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
                    if ($_POST['action'] === 'delete_feedback' && isset($_POST['feedback_id'])) {
                        $feedback_id = intval($_POST['feedback_id']);
                        $stmt = $conn->prepare("DELETE FROM customer_feedback WHERE id = ?");
                        $stmt->bind_param("i", $feedback_id);
                        $stmt->execute();
                        $stmt->close();
                        $_SESSION['message'] = 'Feedback deleted successfully.';
                        $_SESSION['message_type'] = 'success';
                        header('Location: ?page=admin');
                        exit();
                    }

                    if ($_POST['action'] === 'delete_menu_item' && isset($_POST['id'])) {
                        $menu_id = intval($_POST['id']);
                        $stmt = $conn->prepare("DELETE FROM menu_items WHERE id = ?");
                        $stmt->bind_param("i", $menu_id);
                        $stmt->execute();
                        $stmt->close();
                        $_SESSION['message'] = 'Menu item deleted successfully.';
                        $_SESSION['message_type'] = 'success';
                        header('Location: ?page=admin');
                        exit();
                    }
                }


            ?>

                <div class="p-6">
                    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Admin Panel</h2>

                    <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
                        <h3 class="text-2xl font-bold text-gray-800 mb-4">Manage Daily Menu</h3>

                        <?php

                        $edit_item = null;
                        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit_menu_item') {
                            $item_id = intval($_GET['id']);
                            $stmt = $conn->prepare("SELECT id, name, description, price, image_path FROM menu_items WHERE id = ?");
                            $stmt->bind_param("i", $item_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result->num_rows === 1) {
                                $edit_item = $result->fetch_assoc();
                            }
                            $stmt->close();
                        }
                        ?>

                        <form action="index.php?page=admin" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="<?php echo $edit_item ? 'update_menu_item' : 'add_menu_item'; ?>">
                            <?php if ($edit_item): ?>
                                <input type="hidden" name="id" value="<?php echo html_safe($edit_item['id']); ?>">
                            <?php endif; ?>

                            <div class="mb-4">
                                <label for="item-name" class="block text-gray-700 text-sm font-bold mb-2">Product Name:</label>
                                <input type="text" id="item-name" name="name" value="<?php echo $edit_item ? html_safe($edit_item['name']) : ''; ?>" required class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-red-400" />
                            </div>

                            <div class="mb-4">
                                <label for="item-description" class="block text-gray-700 text-sm font-bold mb-2">Description:</label>
                                <textarea id="item-description" name="description" rows="3" required class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-red-400 resize-y"><?php echo $edit_item ? html_safe($edit_item['description']) : ''; ?></textarea>
                            </div>

                            <div class="mb-6">
                                <label for="item-price" class="block text-gray-700 text-sm font-bold mb-2">Price (£):</label>
                                <input type="number" id="item-price" name="price" step="0.01" min="0" value="<?php echo $edit_item ? html_safe($edit_item['price']) : ''; ?>" required class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-red-400" />
                            </div>

                            <div class="mb-6">
                                <label for="item-image" class="block text-gray-700 text-sm font-bold mb-2">Product Image (Optional):</label>
                                <input type="file" id="item-image" name="product_image" accept="image/*" class="shadow appearance-none border rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-red-400" />
                                <?php if ($edit_item && $edit_item['image_path']): ?>
                                    <p class="text-sm text-gray-600 mt-2">Current Image:
                                        <img src="<?php echo html_safe($edit_item['image_path']); ?>" alt="Current Image" class="w-20 h-20 object-cover inline-block ml-2 rounded-lg" />
                                    </p>
                                    <input type="hidden" name="current_image_path" value="<?php echo html_safe($edit_item['image_path']); ?>">
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-6 rounded-lg shadow-md w-full">
                                <?php echo $edit_item ? 'Update Product' : 'Add New Product'; ?>
                            </button>

                            <?php if ($edit_item): ?>
                                <a href="?page=admin" class="mt-2 block text-center bg-gray-400 hover:bg-gray-500 text-white font-bold py-3 px-6 rounded-lg shadow-md w-full">Cancel Edit</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
                        <h3 class="text-2xl font-bold text-gray-800 mb-4">Current Menu Items</h3>
                        <?php
                        $stmt = $conn->prepare("SELECT id, name, description, price, image_path FROM menu_items ORDER BY created_at DESC");
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            echo '<div class="overflow-x-auto">';
                            echo '<table class="min-w-full bg-white rounded-lg overflow-hidden">';
                            echo '<thead class="bg-gray-200">';
                            echo '<tr>';
                            echo '<th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Name</th>';
                            echo '<th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Description</th>';
                            echo '<th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Price</th>';
                            echo '<th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Image</th>';
                            echo '<th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Actions</th>';
                            echo '</tr>';
                            echo '</thead>';
                            echo '<tbody>';
                            while ($row = $result->fetch_assoc()) {
                                $display_image = $row['image_path'] ? html_safe($row['image_path']) : "https://placehold.co/50x50/FEE2E2/DC2626?text=N/A";
                                echo '<tr class="border-b border-gray-200 hover:bg-gray-50">';
                                echo '<td class="py-3 px-4 text-gray-700">' . html_safe($row['name']) . '</td>';
                                echo '<td class="py-3 px-4 text-gray-700">' . html_safe($row['description']) . '</td>';
                                echo '<td class="py-3 px-4 text-gray-700">$' . html_safe(number_format($row['price'], 2)) . '</td>';
                                echo '<td class="py-3 px-4"><img src="' . $display_image . '" class="w-12 h-12 object-cover rounded-lg" alt="Product"></td>';
                                echo '<td class="py-3 px-4">';
                                echo '<a href="?page=admin&action=edit_menu_item&id=' . html_safe($row['id']) . '" class="bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded-lg text-sm mr-2">Edit</a>';
                                echo '<form action="index.php?page=admin" method="POST" style="display:inline-block;">';
                                echo '<input type="hidden" name="action" value="delete_menu_item">';
                                echo '<input type="hidden" name="id" value="' . html_safe($row['id']) . '">';
                                echo '<button type="submit" class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-lg text-sm" onclick="return confirm(\'Are you sure you want to delete this item?\');">Delete</button>';
                                echo '</form>';
                                echo '</td>';
                                echo '</tr>';
                            }
                            echo '</tbody>';
                            echo '</table>';
                            echo '</div>';
                        } else {
                            echo '<p class="text-center text-gray-600 text-lg">No menu items have been added yet.</p>';
                        }
                        $stmt->close();
                        ?>
                    </div>

                    <div class="bg-white p-6 rounded-lg shadow-lg">
                        <h3 class="text-2xl font-bold text-gray-800 mb-4">Customer Feedback</h3>
                        <?php
                        $stmt = $conn->prepare("SELECT id, name, email, message, submitted_at FROM customer_feedback ORDER BY submitted_at DESC");
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            echo '<div class="space-y-4">';
                            while ($row = $result->fetch_assoc()) {
                                echo '<div class="bg-gray-50 p-5 rounded-lg shadow-sm flex justify-between items-start">';
                                echo '<div>';
                                echo '<p class="font-semibold text-gray-800">From: ' . html_safe($row['name']) . '</p>';
                                echo '<p class="text-gray-600 text-sm">Email: ' . html_safe($row['email'] ?: 'N/A') . '</p>';
                                echo '<p class="text-gray-700 mt-2">' . nl2br(html_safe($row['message'])) . '</p>';
                                echo '<p class="text-gray-500 text-xs mt-2">' . html_safe(date('d.m.Y H:i', strtotime($row['submitted_at']))) . '</p>';
                                echo '</div>';

                                echo '<form action="index.php?page=admin" method="POST" onsubmit="return confirm(\'Are you sure you want to delete this feedback?\');">';
                                echo '<input type="hidden" name="action" value="delete_feedback">';
                                echo '<input type="hidden" name="feedback_id" value="' . html_safe($row['id']) . '">';
                                echo '<button type="submit" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded-lg">Delete Feedback</button>';
                                echo '</form>';

                                echo '</div>';
                            }
                            echo '</div>';
                        } else {
                            echo '<p class="text-center text-gray-600 text-lg">No customer feedback available.</p>';
                        }
                        $stmt->close();
                        ?>
                    </div>
                </div>

                <?php
                break;

                ?>
                </div>
                </div>
        <?php
                break;

            case 'logout':
                session_unset();
                session_destroy();
                $_SESSION['message'] = 'You have been successfully logged out!';
                $_SESSION['message_type'] = 'success';
                header('Location: ?page=home');
                exit();
                break;

            default:
                // Fallback to home page if an invalid page is requested
                header('Location: ?page=home');
                exit();
                break;
        }
        $conn->close(); // Close database connection at the end of the script
        ?>
    </main>

    <footer class="bg-gray-800 text-white p-4 text-center mt-auto">
        <p>&copy; <span id="current-year"></span> Sweet Treats Bakery. All rights reserved.</p>
    </footer>

    <script>
        document.getElementById('current-year').textContent = new Date().getFullYear();
    </script>
</body>

</html>