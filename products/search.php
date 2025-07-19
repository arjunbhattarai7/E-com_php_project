<?php
session_start();
require_once '../config/database.php';

$q = trim($_GET['q'] ?? '');
$results = [];
$error = '';

if ($q !== '') {
    try {
        $stmt = $pdo->prepare("SELECT id, title, author, price, image FROM books WHERE status='active' AND (title LIKE ? OR author LIKE ? OR description LIKE ?) ORDER BY id DESC");
        $like = "%$q%";
        $stmt->execute([$like, $like, $like]);
        $results = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Search failed. Please try again.";
    }
}
$image_path = 'assets/images/uploads/books/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Results | Book Haven</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: #f5f7fb;
        }
        .search-results-card {
            max-width: 1100px;
            margin: 40px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(26,54,93,0.12);
            padding: 2rem 2.5rem;
        }
        .book-thumb {
            width: 100%;
            max-width: 180px;
            height: 240px;
            object-fit: cover;
            border-radius: 10px;
            background: #f3f3f3;
            margin: 0 auto;
            display: block;
        }
        .card-title {
            min-height: 2.5em;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }
        @media (max-width: 767px) {
            .search-results-card {
                padding: 1rem;
            }
            .book-thumb {
                max-width: 100%;
                height: auto;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="search-results-card">
        <a href="#" class="back-link text-decoration-none text-primary" data-bs-dismiss="modal">
            <i class="bi bi-arrow-left"></i> Back to Home
        </a>
        <h2 class="mb-4">Search Results for "<?= htmlspecialchars($q) ?>"</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <div class="row g-4">
            <?php if ($results): ?>
                <?php foreach ($results as $book): ?>
                    <?php
                        $img = ($book['image'] && $book['image'] !== 'default.jpg' && file_exists(__DIR__ . '/../' . $image_path . $book['image']))
                            ? $image_path . htmlspecialchars($book['image'])
                            : 'https://via.placeholder.com/180x240?text=No+Image';
                    ?>
                    <div class="col-md-3 col-sm-6">
                        <div class="card h-100 shadow-sm">
                            <img src="<?= $img ?>" class="book-thumb mt-3" alt="<?= htmlspecialchars($book['title']) ?>">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?= htmlspecialchars($book['title']) ?></h5>
                                <p class="card-text text-muted mb-1">by <?= htmlspecialchars($book['author']) ?></p>
                                <div class="mb-2 fw-bold text-success">$<?= number_format($book['price'], 2) ?></div>
                                <div class="d-flex gap-2 mt-auto">
                                    <a href="#" class="btn btn-outline-primary btn-sm flex-grow-1" data-bs-toggle="modal" data-bs-target="#bookDetailsModal" onclick="loadBookDetails(<?= $book['id'] ?>)">
                                        <i class="bi bi-eye"></i> Details
                                    </a>
                                    <form class="add-to-cart-form" data-book-id="<?= $book['id'] ?>" style="display:inline;">
                                        <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
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
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-warning text-center">No books found.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>