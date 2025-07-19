<?php
session_start();
require_once '../config/database.php';

$slug = trim($_GET['slug'] ?? '');
$category = null;
$books = [];
$error = '';

if ($slug !== '') {
    try {
        $catStmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
        $catStmt->execute([$slug]);
        $category = $catStmt->fetch();

        if ($category) {
            $booksStmt = $pdo->prepare("SELECT id, title, author, price, image FROM books WHERE category_id = ? AND status='active' AND deleted_at IS NULL ORDER BY id DESC");
            $booksStmt->execute([$category['id']]);
            $books = $booksStmt->fetchAll();
        } else {
            $error = "Category not found.";
        }
    } catch (PDOException $e) {
        $error = "Could not load category.";
    }
} else {
    $error = "Invalid category.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $category ? htmlspecialchars($category['name']) : 'Category' ?> | Book Haven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #1a365d;
            --secondary-color: #2d4a69;
            --light-bg: #f8fafc;
        }
        
        body {
            background: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: var(--primary-color);
        }
        
        .hero {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 3rem 0 2rem 0;
            margin-bottom: 2rem;
        }
        
        .book-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .book-card:hover {
            transform: translateY(-5px);
        }
        
        .book-card img {
            height: 200px;
            object-fit: contain;
            padding: 1rem;
        }
        
        .book-card-body {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
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
        
        .loading-spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-left: 7px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="../index.php"><i class="bi bi-book-half me-2"></i>Book Haven</a>
        </div>
    </nav>

    <!-- Category Hero Section -->
    <div class="hero text-center">
        <div class="container">
            <h1 class="mb-3"><?= $category ? htmlspecialchars($category['name']) : 'Category' ?></h1>
            <p class="lead"><?= count($books) ?> books available</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container my-5">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="row g-4">
            <?php foreach ($books as $book): ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <div class="book-card">
                        <img src="../assets/images/uploads/books/<?= htmlspecialchars($book['image']) ?>" 
                             alt="<?= htmlspecialchars($book['title']) ?>" 
                             class="w-100">
                        <div class="book-card-body">
                            <h5 class="book-title"><?= htmlspecialchars($book['title']) ?></h5>
                            <p class="book-author text-muted">by <?= htmlspecialchars($book['author']) ?></p>
                            <p class="book-price text-primary fw-bold">$<?= number_format($book['price'], 2) ?></p>
                            
                            <div class="d-flex gap-2 mt-auto">
                                <a href="details.php?id=<?= $book['id'] ?>" 
                                   class="btn btn-outline-primary btn-sm flex-grow-1">
                                    Details
                                </a>
                                
                                <form class="add-to-cart-form" data-book-id="<?= $book['id'] ?>">
                                    <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <button class="btn btn-primary btn-sm" type="submit">
                                        <i class="bi bi-cart-plus"></i>
                                        <span class="loading-spinner"></span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Add to cart functionality
    document.querySelectorAll('.add-to-cart-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            const spinner = this.querySelector('.loading-spinner');
            
            submitBtn.disabled = true;
            spinner.style.display = 'inline-block';
            
            fetch('../user/cart_ajax.php', {
                method: 'POST',
                body: new FormData(this)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('Item added to cart!');
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
                spinner.style.display = 'none';
            });
        });
    });
    </script>
</body>
</html>