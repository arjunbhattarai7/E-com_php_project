<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $book_id = intval($_POST['book_id'] ?? 0);
    $csrf = $_POST['csrf_token'] ?? '';
    if ($csrf !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
    // Check if already in cart
    $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND book_id = ?");
    $stmt->execute([$user_id, $book_id]);
    if ($row = $stmt->fetch()) {
        // Update quantity
        $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND book_id = ?");
        $stmt->execute([$user_id, $book_id]);
    } else {
        // Insert new
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, book_id, quantity) VALUES (?, ?, 1)");
        $stmt->execute([$user_id, $book_id]);
    }
    // Get updated cart count
    $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_count = (int)$stmt->fetchColumn();
    echo json_encode(['success' => true, 'cart_count' => $cart_count]);
    exit;
}

// Update quantity (increase/decrease)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_id'], $_POST['action']) && in_array($_POST['action'], ['increase', 'decrease'])) {
    $book_id = intval($_POST['book_id']);
    $csrf = $_POST['csrf_token'] ?? '';
    if ($csrf !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
    if ($_POST['action'] === 'increase') {
        $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND book_id = ?");
        $stmt->execute([$user_id, $book_id]);
    } elseif ($_POST['action'] === 'decrease') {
        // Only decrease if quantity > 1
        $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND book_id = ?");
        $stmt->execute([$user_id, $book_id]);
        $qty = (int)$stmt->fetchColumn();
        if ($qty > 1) {
            $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity - 1 WHERE user_id = ? AND book_id = ?");
            $stmt->execute([$user_id, $book_id]);
        } else {
            // Remove item if quantity would go below 1
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND book_id = ?");
            $stmt->execute([$user_id, $book_id]);
        }
    }
    // Get updated cart count
    $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_count = (int)$stmt->fetchColumn();
    echo json_encode(['success' => true, 'cart_count' => $cart_count]);
    exit;
}

// Remove from cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
    $book_id = intval($_POST['remove_id']);
    $csrf = $_POST['csrf_token'] ?? '';
    if ($csrf !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND book_id = ?");
    $stmt->execute([$user_id, $book_id]);
    // Get updated cart count
    $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_count = (int)$stmt->fetchColumn();
    echo json_encode(['success' => true, 'cart_count' => $cart_count]);
    exit;
}

// Get cart HTML for modal
if (isset($_GET['get_cart'])) {
    $cart = [];
    $total = 0.0;
    $stmt = $pdo->prepare("SELECT c.book_id, c.quantity, b.title, b.author, b.price, b.image FROM cart c JOIN books b ON c.book_id = b.id WHERE c.user_id = ?");
    $stmt->execute([$user_id]);
    $cart = $stmt->fetchAll();
    foreach ($cart as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    ob_start();
    ?>
    <div class="modal-header">
        <h5 class="modal-title" id="cartModalLabel"><i class="bi bi-cart me-2"></i>My Cart</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
        <?php if ($cart): ?>
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Autr</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cart as $item): ?>
                    <tr>
                        <td>
                            <img src="assets/images/uploads/books/<?= htmlspecialchars($item['image']) ?>" alt="" class="cart-img me-2">
                            <?= htmlspecialchars($item['title']) ?>
                        </td>
                        <td><?= htmlspecialchars($item['author']) ?></td>
                        <td><?= htmlspecialchars($item['quantity']) ?></td>
                        <td>$<?= number_format($item['price'], 2) ?></td>
                        <td>$<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                        <td>
                            <button class="btn btn-sm btn-danger remove-from-cart-btn" data-book-id="<?= $item['book_id'] ?>">Remove</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="text-end">
                <h5>Total: $<?= number_format($total, 2) ?></h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#checkoutModal" data-bs-dismiss="modal">Proceed to Checkout</button>
            </div>
        <?php else: ?>
            <div class="alert alert-info">Your cart is empty.</div>
        <?php endif; ?>
    </div>
    <?php
    echo ob_get_clean();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);