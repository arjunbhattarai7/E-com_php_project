<?php
session_start();
require_once '../config/database.php';

$books = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, author, price, image FROM books WHERE status='active' AND deleted_at IS NULL ORDER BY id DESC");
    $stmt->execute();
    $books = $stmt->fetchAll();
} catch (PDOException $e) {
    $books = [];
}
$image_path = '../assets/images/uploads/books/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Books | Book Haven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-color: #1a365d;
            --secondary-color: #2d4a69;
            --accent-color: #4a6fa5;
            --light-bg: #f8fafc;
        }
        
        body {
            background: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 3rem 0;
            margin-bottom: 3rem;
            text-align: center;
        }
        
        .book-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            height: 100%;
            overflow: hidden;
        }
        
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        
        .book-img {
            height: 220px;
            object-fit: contain;
            padding: 1.5rem;
            background: #f9f9f9;
            border-bottom: 1px solid #eee;
        }
        
        .book-body {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            height: calc(100% - 220px);
        }
        
        .book-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
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
        
        .action-buttons {
            margin-top: auto;
            display: flex;
            gap: 0.5rem;
        }
        
        .action-buttons .btn {
            flex: 1;
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
        
        .empty-state {
            padding: 4rem;
            text-align: center;
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1 class="mb-3">Browse All Books</h1>
            <p class="lead">Discover our complete collection of books</p>
            <a href="../index.php" class="btn btn-outline-light mt-3"><i class="bi bi-arrow-left"></i> Back to Home</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mb-5">
        <div class="row g-4">
            <?php if ($books): ?>
                <?php foreach ($books as $book): ?>
                    <?php
                        $img = ($book['image'] && $book['image'] !== 'default.jpg' && file_exists($image_path . $book['image']))
                            ? $image_path . htmlspecialchars($book['image'])
                            : 'https://via.placeholder.com/300x400?text=No+Image';
                    ?>
                    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                        <div class="book-card">
                            <img src="<?= $img ?>" class="book-img" alt="<?= htmlspecialchars($book['title']) ?>">
                            <div class="book-body">
                                <h5 class="book-title"><?= htmlspecialchars($book['title']) ?></h5>
                                <p class="book-author">by <?= htmlspecialchars($book['author']) ?></p>
                                <div class="book-price">$<?= number_format($book['price'], 2) ?></div>
                                <div class="action-buttons">
                                    <a href="#" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bookDetailsModal" onclick="loadBookDetails(<?= $book['id'] ?>)">Details</a>
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                        <form class="add-to-cart-form" data-book-id="<?= $book['id'] ?>">
                                            <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                            <input type="hidden" name="action" value="add">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <button class="btn btn-primary" type="submit">
                                                <i class="bi bi-cart-plus"></i>
                                                <span class="loading-spinner"></span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="bi bi-book"></i>
                        <h3>No Books Available</h3>
                        <p class="text-muted">We couldn't find any books in our collection.</p>
                    </div>
                </div>
            <?php endif; ?>
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
                    <!-- Book details will be loaded here via AJAX -->
                </div>
            </div>
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
                spinner.style.display = 'none';
            });
        });
    });

    // Load book details via AJAX
    function loadBookDetails(bookId) {
        fetch(`details_ajax.php?id=${bookId}`)
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
    </script>
</body>
</html>