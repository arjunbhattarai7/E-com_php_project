<?php
// products/details_ajax.php
session_start();
require_once '../config/database.php';

$id = intval($_GET['id'] ?? 0);
$book = null;
$error = '';

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ? AND status='active'");
        $stmt->execute([$id]);
        $book = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "Could not load book details.";
    }
} else {
    $error = "Invalid book ID.";
}

// Set correct image path for modal context
$image_path = 'assets/images/uploads/books/';
if ($book && $book['image'] && $book['image'] !== 'default.jpg') {
    $book_image = $image_path . htmlspecialchars($book['image']);
} else {
    $book_image = 'https://via.placeholder.com/300x400?text=No+Image';
}
?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php elseif ($book): ?>
    <div class="row">
        <div class="col-md-5 text-center mb-4 mb-md-0">
            <img src="<?= $book_image ?>" class="book-details-img" alt="<?= htmlspecialchars($book['title']) ?>">
        </div>
        <div class="col-md-7">
            <h2 class="mb-2"><?= htmlspecialchars($book['title']) ?></h2>
            <p class="text-muted mb-1">by <?= htmlspecialchars($book['author']) ?></p>
            <div class="mb-3 fs-4 fw-bold text-success">$<?= number_format($book['price'], 2) ?></div>
            <p><?= nl2br(htmlspecialchars($book['description'])) ?></p>
            <form class="add-to-cart-form mt-4" data-book-id="<?= $book['id'] ?>">
                <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-cart-plus"></i> Add to Cart
                </button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-warning">Book not found.</div>
<?php endif; ?>