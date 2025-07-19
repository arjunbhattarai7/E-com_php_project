<?php
session_start();
require_once 'config/database.php';

// Initialize CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle logout request
if (isset($_GET['logout'])) {
    // Verify CSRF token for logout
    if (!isset($_GET['token']) || $_GET['token'] !== $_SESSION['csrf_token']) {
        die('Invalid logout request');
    }
    
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Delete the remember me cookie if it exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // Redirect to index page
    header('Location: index.php');
    exit;
}

// Handle user registration
$register_success = '';
$register_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_modal'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $register_errors[] = "Security error. Please try again.";
    }

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $register_errors[] = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_errors[] = "Invalid email address.";
    } elseif ($password !== $confirm_password) {
        $register_errors[] = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $register_errors[] = "Password must be at least 8 characters.";
    }

    // Check if email exists
    if (empty($register_errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $register_errors[] = "Email is already registered.";
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $register_errors[] = "Registration failed. Please try again.";
        }
    }

    // Register user
    if (empty($register_errors)) {
        try {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $verificationToken = bin2hex(random_bytes(32));
            
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, verification_token, created_at) VALUES (?, ?, ?, ?, NOW())");
            
            if ($stmt->execute([$username, $email, $hashed, $verificationToken])) {
                $userId = $pdo->lastInsertId();
                
                // In a real application, you would send an email here
                // sendVerificationEmail($email, $verificationToken);
                
                $register_success = "Registration successful! Please check your email to verify your account.";
                unset($_POST['username'], $_POST['email'], $_POST['password'], $_POST['confirm_password']);
            } else {
                $register_errors[] = "Registration failed. Please try again.";
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $register_errors[] = "Registration failed. Please try again.";
        }
    }
}

// Handle user login
$login_success = '';
$login_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_modal'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $login_errors[] = "Security error. Please try again.";
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Validate inputs
    if (empty($email) || empty($password)) {
        $login_errors[] = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $login_errors[] = "Invalid email address.";
    }

    // Authenticate user
    if (empty($login_errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password_hash, email_verified FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // Update last login
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + 60 * 60 * 24 * 30, '/', '', true, true);
                    $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?")->execute([$token, $user['id']]);
                }
                
                $login_success = "Login successful! Welcome back.";
            } else {
                $login_errors[] = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $login_errors[] = "Login failed. Please try again.";
        }
    }
}

// Handle remember me functionality
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE remember_token = ?");
        $stmt->execute([$_COOKIE['remember_token']]);
        
        if ($user = $stmt->fetch()) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
        }
    } catch (PDOException $e) {
        error_log("Remember me error: " . $e->getMessage());
    }
}

// Fetch active categories
$categories = [];
try {
    $categories = $pdo->query("SELECT id, name, slug FROM categories ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    error_log("Database error fetching categories: " . $e->getMessage());
}

// Fetch featured books (excluding soft-deleted)
$books = [];
try {
    $books = $pdo->query("SELECT id, title, author, price, image FROM books WHERE status='active' AND deleted_at IS NULL ORDER BY id DESC LIMIT 8")->fetchAll();
} catch (PDOException $e) {
    error_log("Database error fetching books: " . $e->getMessage());
}

// Get cart items
$cart = [];
$total = 0.0;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT c.book_id, c.quantity, b.title, b.author, b.price, b.image 
                              FROM cart c 
                              JOIN books b ON c.book_id = b.id 
                              WHERE c.user_id = ? AND b.deleted_at IS NULL");
        $stmt->execute([$_SESSION['user_id']]);
        $cart = $stmt->fetchAll();
        
        foreach ($cart as $item) {
            $total += $item['price'] * $item['quantity'];
        }
    } catch (PDOException $e) {
        error_log("Database error fetching cart: " . $e->getMessage());
    }
}

// Handle book details request
$book_details = null;
$book_error = '';
if (isset($_GET['book_id'])) {
    $book_id = intval($_GET['book_id']);
    if ($book_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ? AND status='active'");
            $stmt->execute([$book_id]);
            $book_details = $stmt->fetch();
        } catch (PDOException $e) {
            $book_error = "Could not load book details.";
        }
    } else {
        $book_error = "Invalid book ID.";
    }
}

// Handle checkout process
$order_success = '';
$order_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    if (!isset($_SESSION['user_id'])) {
        $order_error = "Please login to complete your order.";
    } else {
        $address = trim($_POST['address'] ?? '');
        $payment_method = $_POST['payment_method'] ?? '';
        $csrf_token = $_POST['csrf_token'] ?? '';
        $phone = trim($_POST['phone'] ?? '');

        if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
            $order_error = "Invalid session. Please try again.";
        } elseif (empty($phone) || empty($address) || empty($payment_method)) {
            $order_error = "All fields are required.";
        } elseif (!$cart) {
            $order_error = "Your cart is empty.";
        } else {
            try {
                $pdo->beginTransaction();

                // Simulate payment transaction
                $payment_id = null;
                if ($payment_method === 'esewa') {
                    $payment_id = 'ESEWA-' . strtoupper(bin2hex(random_bytes(6)));
                } elseif ($payment_method === 'mobile_banking') {
                    $payment_id = 'MBANK-' . strtoupper(bin2hex(random_bytes(6)));
                } elseif ($payment_method === 'cod') {
                    $payment_id = 'COD-' . strtoupper(bin2hex(random_bytes(6)));
                }

                // Create order
                $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, status, payment_method, payment_id, shipping_address, created_at, updated_at, phone) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $total,
                    $payment_method === 'cod' ? 'pending' : 'paid',
                    $payment_method,
                    $payment_id,
                    $address,
                    $phone
                ]);
                $order_id = $pdo->lastInsertId();

                // Add order items
                $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, book_id, quantity, price) VALUES (?, ?, ?, ?)");
                $update_stock_stmt = $pdo->prepare("UPDATE books SET stock = stock - ? WHERE id = ?");
                foreach ($cart as $item) {
                    $item_stmt->execute([$order_id, $item['book_id'], $item['quantity'], $item['price']]);
                    // Decrement book stock
                    $update_stock_stmt->execute([$item['quantity'], $item['book_id']]);
                }

                // Clear cart
                $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$_SESSION['user_id']]);

                $pdo->commit();
                $order_success = "Order placed successfully! Your order ID is #$order_id.";
                $cart = [];
                $total = 0.0;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $order_error = "Order failed. Please try again.";
            }
        }
    }
}

// Fetch user orders
$user_orders = [];
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, created_at, total_amount, status FROM orders WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $user_orders = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error fetching orders: " . $e->getMessage());
    }
}

// Payment options
$payment_options = [
    'esewa' => 'eSewa',
    'mobile_banking' => 'Mobile Banking',
    'cod' => 'Cash on Delivery'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Haven | Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #1a365d;
            --secondary-color: #2d4a69;
            --accent-color: #4a6fa5;
            --light-bg: #f8fafc;
            --dark-text: #1a1a1a;
            --light-text: #f8fafc;
        }
        
        body {
            background: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark-text);
        }
        
        .navbar {
            background: var(--primary-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand, .navbar-nav .nav-link {
            color: var(--light-text) !important;
            transition: all 0.3s ease;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .nav-link:hover {
            color: #c2d6f0 !important;
        }
        
        .hero {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--light-text);
            padding: 4rem 0 3rem 0;
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .hero h1 {
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .hero p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto 2rem auto;
        }
        
        .search-box {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .category-card, .book-card {
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
        }
        
        .category-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            padding: 1.5rem;
            text-align: center;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .book-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .book-card img {
            height: 200px;
            object-fit: contain;
            padding: 1rem;
            transition: transform 0.3s ease;
        }
        
        .book-card:hover img {
            transform: scale(1.05);
        }
        
        .book-card-body {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .book-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        
        .book-author {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .book-price {
            color: var(--primary-color);
            font-weight: bold;
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .modal-content {
            border-radius: 15px;
            overflow: hidden;
            border: none;
        }
        
        .modal-header {
            background: var(--primary-color);
            color: white;
            border-bottom: none;
            padding: 1.5rem;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(74, 111, 165, 0.25);
        }
        
        .password-strength {
            height: 5px;
            background: #eee;
            margin-top: 5px;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            background: #dc3545;
            transition: all 0.3s ease;
        }
        
        .footer {
            background: var(--primary-color);
            color: var(--light-text);
            padding: 3rem 0;
            margin-top: 4rem;
        }
        
        .footer a {
            color: #c2d6f0;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .footer a:hover {
            color: white;
            text-decoration: underline;
        }
        
        .cart-img {
            width: 50px;
            height: 50px;
            object-fit: contain;
            border-radius: 5px;
        }
        
        .loading-spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-left: 10px;
        }
        
        /* Book details modal styles */
        .book-details-img {
            width: 100%;
            max-width: 300px;
            height: 400px;
            object-fit: contain;
            border-radius: 12px;
            background: #f3f3f3;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            margin: 0 auto;
            display: block;
        }
        
        /* Orders modal styles */
        .order-status-pending {
            color: #ffc107;
        }
        
        .order-status-paid {
            color: #28a745;
        }
        
        .order-status-shipped {
            color: #17a2b8;
        }
        
        .order-status-cancelled {
            color: #dc3545;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Alerts -->
    <?php if ($register_success): ?>
        <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
            <?= htmlspecialchars($register_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($login_success): ?>
        <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
            <?= htmlspecialchars($login_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['admin_login_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
            <?= htmlspecialchars($_SESSION['admin_login_error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['admin_login_error']); ?>
    <?php endif; ?>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="bi bi-book-half me-2"></i>Book Haven</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#cartModal">
                                <i class="bi bi-cart"></i> Cart
                                <?php if (isset($cart) && count($cart)): ?>
                                    <span class="badge bg-danger cart-badge"><?= count($cart) ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="user/profile.php">
                                <i class="bi bi-person"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item"><a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#ordersModal"><i class="bi bi-receipt"></i> Orders</a></li>
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?logout=1&token=<?= $_SESSION['csrf_token'] ?>">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#signupModal">
                                <i class="bi bi-person-plus"></i> Sign Up
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#loginModal">
                                <i class="bi bi-box-arrow-in-right"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#adminLoginModal">
                                <i class="bi bi-shield-lock"></i> Admin
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero">
        <div class="container">
            <h1>Welcome to Book Haven</h1>
            <p class="lead">Discover your next favorite book. Browse by category or check out our featured picks!</p>
            <form action="products/search.php" method="get" class="search-box">
                <div class="input-group">
                    <input type="text" name="q" class="form-control" placeholder="Search books, authors, categories...">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container my-5">
        <!-- Categories Section -->
        <section class="mb-5">
            <h3 class="mb-4">Browse Categories</h3>
            <div class="row g-3">
                <?php foreach ($categories as $cat): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="#" class="text-decoration-none category-card-link" data-category-slug="<?= htmlspecialchars($cat['slug']) ?>">
                            <div class="category-card">
                                <div class="fw-bold"><?= htmlspecialchars($cat['name']) ?></div>
                                <small class="text-muted">Explore collection</small>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($categories)): ?>
                    <div class="col-12 text-center text-muted">No categories found.</div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Featured Books Section -->
        <section>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Featured Books</h3>
                <a href="products/all.php" class="btn btn-outline-primary">View All</a>
            </div>
            <div class="row g-4">
                <?php foreach ($books as $book): ?>
                    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                        <div class="book-card h-100">
                            <a href="#" data-bs-toggle="modal" data-bs-target="#bookDetailsModal" onclick="loadBookDetails(<?= $book['id'] ?>)">
                                <img src="assets/images/uploads/books/<?= htmlspecialchars($book['image']) ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="w-100">
                            </a>
                            <div class="book-card-body">
                                <div class="book-title"><?= htmlspecialchars($book['title']) ?></div>
                                <div class="book-author">by <?= htmlspecialchars($book['author']) ?></div>
                                <div class="book-price">$<?= number_format($book['price'], 2) ?></div>
                                <div class="d-flex gap-2 mt-auto">
                                    <a href="#" class="btn btn-sm btn-outline-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#bookDetailsModal" onclick="loadBookDetails(<?= $book['id'] ?>)">Details</a>
                                    <form class="add-to-cart-form" data-book-id="<?= $book['id'] ?>" style="display:inline;">
                                        <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <button class="btn btn-sm btn-primary" type="submit">
                                            <i class="bi bi-cart-plus"></i>
                                            <span class="loading-spinner"></span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($books)): ?>
                    <div class="col-12 text-center text-muted">No featured books available.</div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Signup Modal -->
    <div class="modal fade<?= (!empty($register_errors) ? ' show' : '') ?>" id="signupModal" tabindex="-1" aria-labelledby="signupModalLabel" aria-hidden="<?= empty($register_errors) ? 'true' : 'false' ?>" style="<?= !empty($register_errors) ? 'display:block;' : '' ?>">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="" id="registerForm">
                    <input type="hidden" name="register_modal" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="signupModalLabel"><i class="bi bi-person-plus me-2"></i>Create Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (!empty($register_errors)): ?>
                            <div class="alert alert-danger">
                                <?php foreach ($register_errors as $err): ?>
                                    <div><?= htmlspecialchars($err) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input name="username" class="form-control" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input name="email" type="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input name="password" type="password" class="form-control" id="registerPassword" required>
                            <div class="password-strength mt-2">
                                <div class="password-strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input name="confirm_password" type="password" class="form-control" required>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="termsCheck" required>
                            <label class="form-check-label" for="termsCheck">
                                I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary w-100 py-2">Create Account</button>
                        <div class="text-center mt-3">
                            Already have an account? <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal">Login</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div class="modal fade<?= (!empty($login_errors) ? ' show' : '') ?>" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="<?= empty($login_errors) ? 'true' : 'false' ?>" style="<?= !empty($login_errors) ? 'display:block;' : '' ?>">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="">
                    <input type="hidden" name="login_modal" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="loginModalLabel"><i class="bi bi-box-arrow-in-right me-2"></i>Login</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (!empty($login_errors)): ?>
                            <div class="alert alert-danger">
                                <?php foreach ($login_errors as $err): ?>
                                    <div><?= htmlspecialchars($err) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input name="email" type="email" class="form-control" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input name="password" type="password" class="form-control" required>
                            <div class="text-end mt-1">
                                <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal" data-bs-dismiss="modal">Forgot password?</a>
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="remember" id="rememberCheck">
                            <label class="form-check-label" for="rememberCheck">Remember me</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary w-100 py-2">Login</button>
                        <div class="text-center mt-3">
                            Don't have an account? <a href="#" data-bs-toggle="modal" data-bs-target="#signupModal" data-bs-dismiss="modal">Sign up</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Admin Login Modal -->
    <div class="modal fade" id="adminLoginModal" tabindex="-1" aria-labelledby="adminLoginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="admin/adminlogin.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="adminLoginModalLabel"><i class="bi bi-shield-lock me-2"></i>Admin Login</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" name="password" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary w-100 py-2">Sign In</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cart Modal -->
    <div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" id="cart-modal-content">
                <!-- Cart content will be loaded here via AJAX -->
            </div>
        </div>
    </div>

    <!-- Book Details Modal -->
    <div class="modal fade" id="bookDetailsModal" tabindex="-1" aria-labelledby="bookDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bookDetailsModalLabel">Book Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="bookDetailsContent">
                    <?php if ($book_error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($book_error) ?></div>
                    <?php elseif ($book_details): ?>
                        <div class="row">
                            <div class="col-md-5 text-center mb-4 mb-md-0">
                                <img src="assets/images/uploads/books/<?= htmlspecialchars($book_details['image']) ?>" class="book-details-img" alt="<?= htmlspecialchars($book_details['title']) ?>">
                            </div>
                            <div class="col-md-7">
                                <h2 class="mb-2"><?= htmlspecialchars($book_details['title']) ?></h2>
                                <p class="text-muted mb-1">by <?= htmlspecialchars($book_details['author']) ?></p>
                                <div class="mb-3 fs-4 fw-bold text-success">$<?= number_format($book_details['price'], 2) ?></div>
                                <p><?= nl2br(htmlspecialchars($book_details['description'])) ?></p>
                                <form class="add-to-cart-form mt-4" data-book-id="<?= $book_details['id'] ?>">
                                    <input type="hidden" name="book_id" value="<?= $book_details['id'] ?>">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="bi bi-cart-plus"></i> Add to Cart
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">Book not found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Checkout Modal -->
    <div class="modal fade" id="checkoutModal" tabindex="-1" aria-labelledby="checkoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="checkoutModalLabel"><i class="bi bi-credit-card me-2"></i>Checkout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($order_success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($order_success) ?></div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ordersModal" data-bs-dismiss="modal">View My Orders</button>
                    <?php else: ?>
                        <?php if ($order_error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($order_error) ?></div>
                        <?php endif; ?>
                        <form method="post" id="checkoutForm">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="checkout" value="1">
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-control" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Shipping Address</label>
                                <textarea name="address" class="form-control" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="">Select Payment Method</option>
                                    <?php foreach ($payment_options as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= (($_POST['payment_method'] ?? '') === $key) ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <h5 class="mt-4 mb-3">Order Summary</h5>
                            <?php if ($cart): ?>
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Book</th>
                                            <th>Price</th>
                                            <th>Qty</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($cart as $item): ?>
                                        <tr>
                                            <td>
                                                <img src="assets/images/uploads/books/<?= htmlspecialchars($item['image']) ?>" class="cart-img me-2" alt="">
                                                <?= htmlspecialchars($item['title']) ?>
                                            </td>
                                            <td>$<?= number_format($item['price'], 2) ?></td>
                                            <td><?= htmlspecialchars($item['quantity']) ?></td>
                                            <td>$<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="2" class="text-end fw-bold">Total:</td>
                                            <td colspan="2" class="fw-bold">$<?= number_format($total, 2) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                                <button type="submit" class="btn btn-success w-100">Place Order</button>
                            <?php else: ?>
                                <div class="alert alert-info">Your cart is empty.</div>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Orders Modal -->
    <div class="modal fade" id="ordersModal" tabindex="-1" aria-labelledby="ordersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ordersModalLabel"><i class="bi bi-receipt me-2"></i>My Orders</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="alert alert-info">Please <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal">login</a> to view your orders.</div>
                    <?php elseif ($user_orders): ?>
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($user_orders as $order): ?>
                                <tr>
                                    <td><?= htmlspecialchars($order['id']) ?></td>
                                    <td><?= htmlspecialchars($order['created_at']) ?></td>
                                    <td>$<?= number_format($order['total_amount'], 2) ?></td>
                                    <td>
                                        <span class="order-status-<?= strtolower($order['status']) ?>">
                                            <?= htmlspecialchars($order['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-info text-center py-5">
                            <i class="bi bi-cart-x" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">You have no orders yet</h5>
                            <p class="mb-0">Start shopping to place your first order</p>
                            <a href="products/all.php" class="btn btn-primary mt-3">Browse Books</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Category Books Modal -->
    <div class="modal fade" id="categoryBooksModal" tabindex="-1" aria-labelledby="categoryBooksModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content" id="category-books-modal-content">
                <!-- Category books will be loaded here via AJAX -->
            </div>
        </div>
    </div>

    <!-- Search Results Modal -->
    <div class="modal fade" id="searchResultsModal" tabindex="-1" aria-labelledby="searchResultsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="searchResultsModalLabel"><i class="bi bi-search me-2"></i>Search Results</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="searchResultsContent">
                    <!-- Results will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5>Book Haven</h5>
                    <p>Your one-stop shop for all your reading needs. Discover new worlds between the pages.</p>
                </div>
                <div class="col-md-2 mb-4 mb-md-0">
                    <h5>Shop</h5>
                    <ul class="list-unstyled">
                        <li><a href="products/all.php" class="text-light">All Books</a></li>
                        <li><a href="products/categories.php" class="text-light">Categories</a></li>
                        <li><a href="products/new-releases.php" class="text-light">New Releases</a></li>
                    </ul>
                </div>
                <div class="col-md-2 mb-4 mb-md-0">
                    <h5>Help</h5>
                    <ul class="list-unstyled">
                        <li><a href="about.php" class="text-light">About Us</a></li>
                        <li><a href="contact.php" class="text-light">Contact</a></li>
                        <li><a href="faq.php" class="text-light">FAQ</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Connect With Us</h5>
                    <div class="social-links mt-3">
                        <a href="#" class="text-white me-3"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="text-white me-3"><i class="bi bi-twitter"></i></a>
                        <a href="#" class="text-white me-3"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="text-white"><i class="bi bi-envelope"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-4" style="border-color: rgba(255,255,255,0.1);">
            <div class="text-center">
                <div>Book Haven &copy; <?= date('Y') ?>. All rights reserved.</div>
                <div class="mt-2">
                    <a href="privacy.php" class="text-light me-3">Privacy Policy</a>
                    <a href="terms.php" class="text-light me-3">Terms of Service</a>
                    <a href="#" class="text-light me-3" data-bs-toggle="modal" data-bs-target="#ordersModal">My Orders</a>
                    <a href="#" class="text-light" data-bs-toggle="modal" data-bs-target="#cartModal">Cart</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Password strength indicator
    document.getElementById('registerPassword')?.addEventListener('input', function() {
        const password = this.value;
        const strengthBar = document.getElementById('passwordStrengthBar');
        let strength = 0;
        
        if (password.length >= 8) strength += 1;
        if (password.match(/[a-z]/)) strength += 1;
        if (password.match(/[A-Z]/)) strength += 1;
        if (password.match(/[0-9]/)) strength += 1;
        if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
        
        let width = strength * 20;
        let color = '#dc3545'; // red
        
        if (strength >= 4) {
            color = '#28a745'; // green
        } else if (strength >= 2) {
            color = '#ffc107'; // yellow
        }
        
        strengthBar.style.width = width + '%';
        strengthBar.style.backgroundColor = color;
    });

    // Load book details via AJAX
    function loadBookDetails(bookId) {
        fetch(`products/details_ajax.php?id=${bookId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('bookDetailsContent').innerHTML = html;
            })
            .catch(error => {
                console.error('Error loading book details:', error);
                document.getElementById('bookDetailsContent').innerHTML = 
                    '<div class="alert alert-danger">Could not load book details.</div>';
            });
    }

    // Category modal AJAX loader
    document.querySelectorAll('.category-card-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const slug = this.getAttribute('data-category-slug');
            const modal = new bootstrap.Modal(document.getElementById('categoryBooksModal'));
            document.getElementById('category-books-modal-content').innerHTML = '<div class="modal-body text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';
            modal.show();
            fetch('products/category_ajax.php?slug=' + encodeURIComponent(slug))
                .then(res => res.text())
                .then(html => {
                    document.getElementById('category-books-modal-content').innerHTML = html;
                });
        });
    });

    // Cart functionality
    document.addEventListener('submit', function(e) {
        if (e.target.classList.contains('add-to-cart-form')) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const spinner = form.querySelector('.loading-spinner');
            submitBtn.disabled = true;
            if (spinner) spinner.style.display = 'inline-block';
            var formData = new FormData(form);
            fetch('user/cart_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateCartBadge(data.cart_count);
                    fetchCartContent();
                    // Show success toast
                    const toast = document.createElement('div');
                    toast.className = 'position-fixed bottom-0 end-0 p-3';
                    toast.style.zIndex = '11';
                    toast.innerHTML = `
                        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="toast-header bg-success text-white">
                                <strong class="me-auto">Success</strong>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                            </div>
                            <div class="toast-body">
                                Item added to cart!
                            </div>
                        </div>
                    `;
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 3000);
                } else {
                    alert(data.message || 'Could not add to cart.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            })
            .finally(() => {
                submitBtn.disabled = false;
                if (spinner) spinner.style.display = 'none';
            });
        }
    });

    // Update cart quantity
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('update-qty-btn')) {
            e.preventDefault();
            const btn = e.target;
            const bookId = btn.getAttribute('data-book-id');
            const action = btn.getAttribute('data-action');
            
            fetch('user/cart_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `book_id=${bookId}&action=${action}&csrf_token=<?= $_SESSION['csrf_token'] ?>`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateCartBadge(data.cart_count);
                    fetchCartContent();
                } else {
                    alert(data.message || 'Could not update quantity.');
                }
            });
        }
        
        // Remove from cart
        if (e.target.classList.contains('remove-from-cart-btn') || 
            e.target.closest('.remove-from-cart-btn')) {
            e.preventDefault();
            const btn = e.target.classList.contains('remove-from-cart-btn') ? 
                e.target : e.target.closest('.remove-from-cart-btn');
            const bookId = btn.getAttribute('data-book-id');
            
            if (!confirm('Are you sure you want to remove this item from your cart?')) return;
            
            fetch('user/cart_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `remove_id=${bookId}&csrf_token=<?= $_SESSION['csrf_token'] ?>`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateCartBadge(data.cart_count);
                    fetchCartContent();
                } else {
                    alert(data.message || 'Could not remove from cart.');
                }
            });
        }
    });

    function updateCartBadge(count) {
        const badge = document.querySelector('.cart-badge');
        if (count > 0) {
            if (badge) {
                badge.textContent = count;
            } else {
                const newBadge = document.createElement('span');
                newBadge.className = 'badge bg-danger cart-badge';
                newBadge.textContent = count;
                document.querySelector('.nav-link[data-bs-target="#cartModal"]').appendChild(newBadge);
            }
        } else if (badge) {
            badge.remove();
        }
    }

    function fetchCartContent() {
        fetch('user/cart_ajax.php?get_cart=1')
            .then(res => res.text())
            .then(html => {
                document.getElementById('cart-modal-content').innerHTML = html;
            });
    }

    // On cart modal show, always fetch latest cart content
    document.getElementById('cartModal').addEventListener('show.bs.modal', function () {
        fetchCartContent();
    });

    // Show modals if there are errors
    <?php if (!empty($register_errors)): ?>
        var signupModal = new bootstrap.Modal(document.getElementById('signupModal'));
        signupModal.show();
    <?php endif; ?>
    
    <?php if (!empty($login_errors)): ?>
        var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
        loginModal.show();
    <?php endif; ?>
    
    <?php if ($order_success): ?>
        var checkoutModal = new bootstrap.Modal(document.getElementById('checkoutModal'));
        checkoutModal.show();
    <?php endif; ?>

    // Search form AJAX modal
    document.querySelector('.search-box')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const q = this.querySelector('input[name="q"]').value.trim();
        if (!q) return;
        const modal = new bootstrap.Modal(document.getElementById('searchResultsModal'));
        document.getElementById('searchResultsContent').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';
        modal.show();
        fetch('products/search.php?q=' + encodeURIComponent(q))
            .then(res => res.text())
            .then(html => {
                document.getElementById('searchResultsContent').innerHTML = html;
            });
    });
    </script>
</body>
</html>