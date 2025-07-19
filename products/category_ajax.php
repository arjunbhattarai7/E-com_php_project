<?php
session_start();
require_once '../config/database.php';

header('Content-Type: text/html; charset=UTF-8');

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
<div class="modal-header">
    <h5 class="modal-title"><i class="bi bi-collection me-2"></i><?= $category ? htmlspecialchars($category['name']) : 'Category' ?> Books</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <div class="row g-4">
        <?php if ($books): ?>
            <?php foreach ($books as $book): ?>
                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <div class="book-card h-100">
                        <?php
                        // Fix image path for modal context
                        $img_path = 'assets/images/uploads/books/';
                        ?>
                        <img src="<?= ($book['image'] && $book['image'] !== 'default.jpg' && file_exists(__DIR__ . '/../' . $img_path . $book['image'])) ? $img_path . htmlspecialchars($book['image']) : 'https://via.placeholder.com/180x240?text=No+Image'; ?>" 
                             alt="<?= htmlspecialchars($book['title']) ?>" 
                             class="w-100">
                        <div class="book-card-body">
                            <h5 class="book-title"><?= htmlspecialchars($book['title']) ?></h5>
                            <p class="book-author text-muted">by <?= htmlspecialchars($book['author']) ?></p>
                            <p class="book-price text-primary fw-bold">$<?= number_format($book['price'], 2) ?></p>
                            
                            <div class="d-flex gap-2 mt-auto">
                                <a href="products/details.php?id=<?= $book['id'] ?>" 
                                   class="btn btn-sm btn-outline-primary flex-grow-1">
                                    Details
                                </a>
                                
                                <form class="add-to-cart-form" data-book-id="<?= $book['id'] ?>">
                                    <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
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
        <?php else: ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="bi bi-book"></i>
                    <h3>No Books Found</h3>
                    <p class="text-muted">We couldn't find any books in this category.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>