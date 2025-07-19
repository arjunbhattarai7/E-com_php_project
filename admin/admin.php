<?php

session_start();
require_once '../config/database.php';

// Only allow admin users
if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

// Fetch admin info
$admin = null;
try {
    $stmt = $pdo->prepare("SELECT username, email, last_login FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
} catch (PDOException $e) {
    $admin = null;
}

// Dashboard stats
try {

    $books_count = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
    $orders_count = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $users_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $revenue = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'completed'")->fetchColumn() ?: 0;

    // Recent orders (last 5)
    $recent_orders = $pdo->query("
        SELECT o.id, u.username, o.created_at, o.total_amount, o.status 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ")->fetchAll();

    // Recent books (last 5)
    $recent_books = $pdo->query("
        SELECT id, title, author, price, image 
        FROM books 
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    $books_count = $orders_count = $users_count = $revenue = 0;
    $recent_orders = $recent_books = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard | Book Haven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary: #4e73df;
            --primary-dark: #2e59d9;
            --secondary: #858796;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
.sidebar {
    background: linear-gradient(135deg, #1a365d 0%, #2d4a69 100%);
    min-width: 240px;
    max-width: 240px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    z-index: 1000;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(26,54,93,0.12);
    border-radius: 0 24px 24px 0;
    transition: all 0.3s;
    color: #fff;
}
.sidebar-brand {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    font-weight: 700;
    color: #fff;
    padding: 2rem 1rem 1rem 1rem;
    letter-spacing: 1px;
    text-decoration: none;
}
.sidebar-divider {
    border-top: 1px solid rgba(255,255,255,0.12);
    margin: 0 1.5rem 1.5rem 1.5rem;
}
.sidebar .nav-link {
    color: rgba(255,255,255,0.85);
    font-weight: 500;
    padding: 0.85rem 2rem;
    border-radius: 0 20px 20px 0;
    margin-bottom: 0.25rem;
    transition: background 0.2s, color 0.2s, padding-left 0.2s;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1rem;
}
.sidebar .nav-link.active,
.sidebar .nav-link:hover {
    background: rgba(255,255,255,0.12);
    color: #f59e0b;
    padding-left: 2.5rem;
}
.sidebar .nav-link i {
    font-size: 1.2rem;
}
@media (max-width: 991px) {
    .sidebar {
        min-width: 70px;
        max-width: 70px;
        border-radius: 0 12px 12px 0;
    }
    .sidebar .nav-link span,
    .sidebar-brand span {
        display: none;
    }
    .sidebar .nav-link {
        justify-content: center;
        padding: 0.85rem 0.5rem;
    }
}
.main-content {
    margin-left: 240px;
    width: calc(100% - 240px);
    min-height: 100vh;
    transition: all 0.3s;
}
@media (max-width: 991px) {
    .main-content {
        margin-left: 70px;
        width: calc(100% - 70px);
    }
}
        
        .topbar {
            height: 70px;
            background: #fff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            padding: 0 1.5rem;
        }
        
        .content-container {
            padding: 1.5rem;
        }
        
        .page-title {
            color: var(--dark);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background: #fff;
            border-bottom: 1px solid #e3e6f0;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem 0.5rem 0 0 !important;
            font-weight: 700;
            color: var(--dark);
        }
        
        .stat-card {
            border-left: 4px solid;
        }
        
        .stat-card.primary {
            border-left-color: var(--primary);
        }
        
        .stat-card.success {
            border-left-color: var(--success);
        }
        
        .stat-card.warning {
            border-left-color: var(--warning);
        }
        
        .stat-card.danger {
            border-left-color: var(--danger);
        }
        
        .stat-icon {
            color: #dddfeb;
            font-size: 2rem;
            position: absolute;
            right: 1.5rem;
            top: 1.5rem;
            opacity: 0.4;
        }
        
        .stat-number {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--dark);
        }
        
        .stat-title {
            text-transform: uppercase;
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--secondary);
            letter-spacing: 0.05rem;
        }
        
        .status-badge {
            padding: 0.3rem 0.6rem;
            border-radius: 0.25rem;
            font-size: 0.8rem;
            font-weight: 700;
        }
        
        .status-pending {
            background-color: #f8f5e4;
            color: #856404;
        }
        
        .status-processing {
            background-color: #e4f0f5;
            color: #0c5460;
        }
        
        .status-completed {
            background-color: #e4f5ec;
            color: #155724;
        }
        
        .status-cancelled {
            background-color: #f5e4e4;
            color: #721c24;
        }
        
        .book-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #eaecf4;
            transition: background-color 0.2s;
        }
        
        .book-item:hover {
            background-color: #f8f9fc;
        }
        
        .book-item:last-child {
            border-bottom: none;
        }
        
        .book-cover {
            width: 45px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .book-info {
            flex: 1;
            padding: 0 1rem;
        }
        
        .book-title {
            font-weight: 600;
            margin-bottom: 0.1rem;
            color: var(--dark);
        }
        
        .book-author {
            font-size: 0.85rem;
            color: var(--secondary);
        }
        
        .book-price {
            font-weight: 700;
            color: var(--primary);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .sidebar.active {
                margin-left: 0;
            }
            
            .main-content.active {
                margin-left: 250px;
                width: calc(100% - 250px);
            }
            
            .sidebar-toggler {
                display: block !important;
            }
        }
    </style>
</head>
<body>
<div class="d-flex">
    <!-- Sidebar (matches manage_books.php) -->
    <nav class="sidebar d-flex flex-column p-0">
        <a href="admin.php" class="sidebar-brand text-decoration-none mb-3"><i class="bi bi-book-half me-2"></i> <span>Book Haven Admin</span></a>
        <div class="sidebar-divider"></div>
        <ul class="nav flex-column mb-auto">
            <li><a href="admin.php" class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin.php' ? ' active' : '' ?>"><i class="bi bi-speedometer2"></i> <span>Dashboard</span></a></li>
            <li><a href="manage_books.php" class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'manage_books.php' ? ' active' : '' ?>"><i class="bi bi-journal-bookmark"></i> <span>Manage Books</span></a></li>
            <li><a href="manage_orders.php" class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'manage_orders.php' ? ' active' : '' ?>"><i class="bi bi-bag-check"></i> <span>Manage Orders</span></a></li>
            <li><a href="manage_users.php" class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? ' active' : '' ?>"><i class="bi bi-people"></i> <span>Manage Users</span></a></li>
        </ul>
        <div class="mt-auto p-3">
            <a href="../index.php" class="btn btn-light w-100"><i class="bi bi-house"></i> <span>Back to Site</span></a>
            <a href="logout.php" class="btn btn-danger w-100 mt-2"><i class="bi bi-box-arrow-right"></i> <span>Logout</span></a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Admin Dashboard</h2>
                <div class="text-muted">Welcome, <?= htmlspecialchars($admin['username'] ?? 'Admin') ?></div>
            </div>
            <div>
                <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>

        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="mb-2"><i class="bi bi-book fs-2 text-primary"></i></div>
                        <h5 class="card-title">Books</h5>
                        <p class="card-text fs-4"><?= $books_count ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="mb-2"><i class="bi bi-cart-check fs-2 text-success"></i></div>
                        <h5 class="card-title">Orders</h5>
                        <p class="card-text fs-4"><?= $orders_count ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="mb-2"><i class="bi bi-people fs-2 text-warning"></i></div>
                        <h5 class="card-title">Users</h5>
                        <p class="card-text fs-4"><?= $users_count ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="mb-2"><i class="bi bi-currency-dollar fs-2 text-danger"></i></div>
                        <h5 class="card-title">Revenue</h5>
                        <p class="card-text fs-4">$<?= number_format($revenue, 2) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="row mb-4">
            <div class="col-lg-8 mb-3">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Recent Orders</span>
                        <a href="manage_orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>User</th>
                                        <th>Date</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_orders): ?>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($order['id']) ?></td>
                                                <td><?= htmlspecialchars($order['username']) ?></td>
                                                <td><?= htmlspecialchars($order['created_at']) ?></td>
                                                <td>$<?= number_format($order['total_amount'], 2) ?></td>
                                                <td><?= htmlspecialchars(ucfirst($order['status'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center text-muted">No recent orders.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Books -->
            <div class="col-lg-4 mb-3">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Recent Books</span>
                        <a href="manage_books.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php if ($recent_books): ?>
                            <?php foreach ($recent_books as $book): ?>
                                <li class="list-group-item d-flex align-items-center">
                                    <?php if ($book['image'] && $book['image'] !== 'default.jpg'): ?>
                                        <img src="../assets/images/uploads/books/<?= htmlspecialchars($book['image']) ?>" alt="" width="40" height="55" class="me-2 rounded">
                                    <?php else: ?>
                                        <span class="me-2 text-muted" style="width:40px;display:inline-block;text-align:center;">No Image</span>
                                    <?php endif; ?>
                                    <div>
                                        <div><?= htmlspecialchars($book['title']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($book['author']) ?></small>
                                    </div>
                                    <span class="ms-auto fw-bold">$<?= number_format($book['price'], 2) ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-center text-muted">No recent books.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>