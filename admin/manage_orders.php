<?php
session_start();
require_once '../config/database.php';

// Only allow admin users
if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

// Fetch all orders with user info
$stmt = $pdo->prepare("
    SELECT o.*, u.username, u.email, u.phone
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC
");
$stmt->execute();
$orders = $stmt->fetchAll();

// For status update
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE orders SET status=? WHERE id=?");
    $stmt->execute([$status, $order_id]);
    header("Location: manage_orders.php?msg=updated");
    exit;
}

// For delete order
if (isset($_POST['delete_order'])) {
    $order_id = $_POST['delete_order'];
    $stmt = $pdo->prepare("DELETE FROM orders WHERE id=?");
    $stmt->execute([$order_id]);
    header("Location: manage_orders.php?msg=deleted");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Orders | Book Haven Admin</title>
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
    <!-- Sidebar (same as admin.php/manage_books.php) -->
    <nav class="sidebar d-flex flex-column p-0">
        <a href="admin.php" class="sidebar-brand text-decoration-none mb-3"><i class="bi bi-book-half me-2"></i> <span>Book Haven Admin</span></a>
        <div class="sidebar-divider"></div>
        <ul class="nav flex-column mb-auto">
            <li><a href="admin.php" class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin.php' ? ' active' : '' ?>"><i class="bi bi-speedometer2"></i> <span>Dashboard</span></a></li>
            <li><a href="manage_books.php" class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'manage_books.php' ? ' active' : '' ?>"><i class="bi bi-journal-bookmark"></i> <span>Manage Books</span></a></li>
            <li><a href="manage_orders.php" class="nav-link active"><i class="bi bi-bag-check"></i> <span>Manage Orders</span></a></li>
            <li><a href="manage_users.php" class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? ' active' : '' ?>"><i class="bi bi-people"></i> <span>Manage Users</span></a></li>
        </ul>
        <div class="mt-auto p-3">
            <a href="../index.php" class="btn btn-light w-100"><i class="bi bi-house"></i> <span>Back to Site</span></a>
            <a href="logout.php" class="btn btn-danger w-100 mt-2"><i class="bi bi-box-arrow-right"></i> <span>Logout</span></a>
        </div>
    </nav>
    <!-- Main Content -->
    <div class="main-content p-4">
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php
                if ($_GET['msg'] === 'updated') echo "Order status updated!";
                elseif ($_GET['msg'] === 'deleted') echo "Order deleted!";
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Manage Orders</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Date</th>
                        <th>Total ($)</th>
                        <th>Payment Method</th>
                        <th>Shipping Address</th>
                        <th>Status</th>
                        <th style="width:180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($orders): ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= $order['id'] ?></td>
                            <td><?= htmlspecialchars($order['username'] ?? 'Guest') ?></td>
                            <td><?= htmlspecialchars($order['email'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($order['phone'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($order['created_at']) ?></td>
                            <td><?= number_format($order['total_amount'], 2) ?></td>
                            <td><?= isset($payment_labels[$order['payment_method']]) ? $payment_labels[$order['payment_method']] : htmlspecialchars($order['payment_method']) ?></td>
                            <td><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></td>
                            <td>
                                <span class="badge bg-<?= 
                                    $order['status'] === 'completed' ? 'success' : 
                                    ($order['status'] === 'pending' ? 'warning' : 
                                    ($order['status'] === 'cancelled' ? 'danger' : 'secondary')) ?>">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </td>
                            <td>
                                <!-- Status Update Modal Trigger -->
                                <button class="btn btn-sm btn-primary mb-1" data-bs-toggle="modal" data-bs-target="#statusModal<?= $order['id'] ?>">
                                    <i class="bi bi-pencil"></i> Update Status
                                </button>
                                <!-- Delete -->
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this order?');">
                                    <input type="hidden" name="delete_order" value="<?= $order['id'] ?>">
                                    <button class="btn btn-sm btn-danger mb-1"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted">No orders found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Status Update Modals -->
<?php foreach ($orders as $order): ?>
<div class="modal fade" id="statusModal<?= $order['id'] ?>" tabindex="-1" aria-labelledby="statusModalLabel<?= $order['id'] ?>" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content">
      <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
      <div class="modal-header">
        <h5 class="modal-title" id="statusModalLabel<?= $order['id'] ?>">Update Order Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" required>
                <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="update_status" class="btn btn-primary">Update</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
