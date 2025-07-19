<?php
session_start();
require_once '../config/database.php';

// Only allow admin users
if (empty($_SESSION['admin_logged_in']) || empty($_SESSION['admin_id'])) {
    header('Location: adminlogin.php');
    exit;
}

// Ensure upload folder exists
$upload_dir = realpath(__DIR__ . '/../assets/images/uploads/books');
if (!$upload_dir) {
    $upload_dir = __DIR__ . '/../assets/images/uploads/books';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
}

// Fetch categories for dropdown
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// Handle Add Book
if (isset($_POST['add_book'])) {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $category_id = $_POST['category_id'] ?? null;
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $status = $_POST['status'];
    $description = trim($_POST['description'] ?? '');

    // Generate SKU and ISBN
    $sku = 'BK' . strtoupper(uniqid());
    $isbn = rand(1000000000000, 9999999999999);

    // Handle image upload
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $image = uniqid('book_', true) . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . "/" . $image);
        }
    }
    if (!$image) {
        $image = 'default.jpg';
    }

    $stmt = $pdo->prepare("INSERT INTO books (title, author, description, category_id, price, stock, status, image, sku, isbn, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->execute([$title, $author, $description, $category_id, $price, $stock, $status, $image, $sku, $isbn]);
    header("Location: manage_books.php?msg=added");
    exit;
}

// Handle Edit Book
if (isset($_POST['edit_book'])) {
    $id = $_POST['book_id'];
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $category_id = $_POST['category_id'] ?? null;
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    $status = $_POST['status'];
    $description = trim($_POST['description'] ?? '');

    // Get current image
    $stmt = $pdo->prepare("SELECT image FROM books WHERE id=?");
    $stmt->execute([$id]);
    $current = $stmt->fetch();
    $current_image = $current ? $current['image'] : 'default.jpg';

    // Handle image upload
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $image = uniqid('book_', true) . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . "/" . $image);
        }
    }
    if (!$image) {
        $image = $current_image ?: 'default.jpg';
    }

    $stmt = $pdo->prepare("UPDATE books SET title=?, author=?, description=?, category_id=?, price=?, stock=?, status=?, image=?, updated_at=NOW() WHERE id=?");
    $stmt->execute([$title, $author, $description, $category_id, $price, $stock, $status, $image, $id]);
    header("Location: manage_books.php?msg=updated");
    exit;
}

// Handle Delete Book
if (isset($_POST['delete_book'])) {
    $id = $_POST['delete_book'];
    $stmt = $pdo->prepare("DELETE FROM books WHERE id=?");
    $stmt->execute([$id]);
    header("Location: manage_books.php?msg=deleted");
    exit;
}

// Fetch all books
$stmt = $pdo->prepare("SELECT books.*, categories.name AS category_name FROM books LEFT JOIN categories ON books.category_id = categories.id ORDER BY books.id DESC");
$stmt->execute();
$books = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Books | Book Haven Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
       :root {
            --primary: #1a365d;
            --primary-light: #2d4a69;
            --secondary: #f59e0b;
            --success: #10b981;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #111827;
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
            padding: 2.5rem 2rem 2rem 2rem;
        }
        @media (max-width: 991px) {
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
        }
        .table thead th {
            background: var(--primary);
            color: #fff;
            border: none;
        }
        .table tbody tr {
            background: #fff;
            transition: background 0.2s;
        }
        .table tbody tr:hover {
            background: #f3f4f6;
        }
        .btn-success {
            background: var(--success);
            border: none;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-primary {
            background: var(--primary);
            border: none;
        }
        .btn-primary:hover {
            background: var(--primary-light);
        }
        .btn-danger {
            background: var(--danger);
            border: none;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .badge-success {
            background: var(--success);
        }
        .badge-warning {
            background: var(--warning);
            color: #856404;
        }
        .badge-secondary {
            background: #6c757d;
        }
        .book-thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; }
    </style>
</head>
<body>
<div class="d-flex">
    <!-- Sidebar -->
    <nav class="sidebar d-flex flex-column p-0">
        <a href="admin.php" class="sidebar-brand text-decoration-none mb-3"><i class="bi bi-book-half me-2"></i> <span>Book Haven Admin</span></a>
        <div class="sidebar-divider"></div>
        <ul class="nav flex-column mb-auto">
            <li><a href="admin.php" class="nav-link"><i class="bi bi-speedometer2"></i> <span>Dashboard</span></a></li>
            <li><a href="manage_books.php" class="nav-link active"><i class="bi bi-journal-bookmark"></i> <span>Manage Books</span></a></li>
            <li><a href="manage_orders.php" class="nav-link"><i class="bi bi-bag-check"></i> <span>Manage Orders</span></a></li>
            <li><a href="manage_users.php" class="nav-link"><i class="bi bi-people"></i> <span>Manage Users</span></a></li>
        </ul>
        <div class="mt-auto p-3">
            <a href="../index.php" class="btn btn-light w-100"><i class="bi bi-house"></i> <span>Back to Site</span></a>
            <a href="logout.php" class="btn btn-danger w-100 mt-2"><i class="bi bi-box-arrow-right"></i> <span>Logout</span></a>
        </div>
    </nav>
    <!-- Main Content -->
    <div class="main-content">
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php
                if ($_GET['msg'] === 'added') echo "Book added successfully!";
                elseif ($_GET['msg'] === 'updated') echo "Book updated successfully!";
                elseif ($_GET['msg'] === 'deleted') echo "Book deleted successfully!";
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Manage Books</h2>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBookModal">
                <i class="bi bi-plus-circle me-1"></i> Add New Book
            </button>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Photo</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Price ($)</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th style="width:140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($books): ?>
                    <?php foreach ($books as $book): ?>
                        <tr>
                            <td><?= $book['id'] ?></td>
                            <td>
                                <?php if ($book['image'] && $book['image'] !== 'default.jpg'): ?>
                                    <img src="../assets/images/uploads/books/<?= htmlspecialchars($book['image']) ?>" class="book-thumb" alt="">
                                <?php else: ?>
                                    <span class="text-muted">No Image</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($book['title']) ?></td>
                            <td><?= htmlspecialchars($book['author']) ?></td>
                            <td><?= htmlspecialchars($book['category_name'] ?? 'Uncategorized') ?></td>
                            <td><?= number_format($book['price'], 2) ?></td>
                            <td><?= $book['stock'] ?></td>
                            <td>
                                <span class="badge bg-<?= $book['status'] === 'active' ? 'success' : ($book['status'] === 'out_of_stock' ? 'warning' : 'secondary') ?>">
                                    <?= ucfirst($book['status']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary mb-1" data-bs-toggle="modal" data-bs-target="#editBookModal<?= $book['id'] ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this book?');">
                                    <input type="hidden" name="delete_book" value="<?= $book['id'] ?>">
                                    <button class="btn btn-sm btn-danger mb-1"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted">No books found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Book Modal -->
<div class="modal fade" id="addBookModal" tabindex="-1" aria-labelledby="addBookModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title" id="addBookModalLabel">Add New Book</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input name="title" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Author</label>
            <input name="author" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Price ($)</label>
            <input name="price" type="number" step="0.01" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Stock</label>
            <input name="stock" type="number" class="form-control" required>
        </div>
        
<div class="mb-3">
    <label class="form-label">Description</label>
    <textarea name="description" class="form-control" required><?= isset($book) ? htmlspecialchars($book['description']) : '' ?></textarea>
</div>
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" required>
                <option value="active">Active</option>
                <option value="out_of_stock">Out of Stock</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Book Image</label>
            <input type="file" name="image" class="form-control">
            <small class="text-muted">Allowed: jpg, jpeg, png, gif, webp</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="add_book" class="btn btn-success">Add Book</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Book Modals (all after the table for best practice) -->
<?php foreach ($books as $book): ?>
<div class="modal fade" id="editBookModal<?= $book['id'] ?>" tabindex="-1" aria-labelledby="editBookModalLabel<?= $book['id'] ?>" aria-hidden="true">
  <div class="modal-dialog">
    <form method="post" class="modal-content" enctype="multipart/form-data">
      <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
      <div class="modal-header">
        <h5 class="modal-title" id="editBookModalLabel<?= $book['id'] ?>">Edit Book</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input name="title" class="form-control" value="<?= htmlspecialchars($book['title']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Author</label>
            <input name="author" class="form-control" value="<?= htmlspecialchars($book['author']) ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Category</label>
            <select name="category_id" class="form-select" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $book['category_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Price ($)</label>
            <input name="price" type="number" step="0.01" class="form-control" value="<?= $book['price'] ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Stock</label>
            <input name="stock" type="number" class="form-control" value="<?= $book['stock'] ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" required>
                <option value="active" <?= $book['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="out_of_stock" <?= $book['status'] === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                <option value="inactive" <?= $book['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        
<div class="mb-3">
    <label class="form-label">Description</label>
    <textarea name="description" class="form-control" required><?= isset($book) ? htmlspecialchars($book['description']) : '' ?></textarea>
</div>
        <div class="mb-3">
            <label class="form-label">Book Image</label>
            <?php if ($book['image'] && $book['image'] !== 'default.jpg'): ?>
                <div class="mb-2">
                    <img src="../assets/images/uploads/books/<?= htmlspecialchars($book['image']) ?>" class="book-thumb" alt="">
                </div>
            <?php endif; ?>
            <input type="file" name="image" class="form-control">
            <small class="text-muted">Leave blank to keep current image.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" name="edit_book" class="btn btn-primary">Update</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>