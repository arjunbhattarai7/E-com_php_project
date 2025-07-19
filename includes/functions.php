
<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    // Fetch categories
    $categoriesStmt = $pdo->prepare("SELECT id, name, slug, image FROM categories LIMIT 6");
    $categoriesStmt->execute();
    $categories = $categoriesStmt->fetchAll();

    // Fetch featured books
    $booksStmt = $pdo->prepare("SELECT id, title, author, price, image FROM books LIMIT 8");
    $booksStmt->execute();
    $featuredBooks = $booksStmt->fetchAll();

    // Fetch bestsellers (for demo, just latest 4)
    $bestsellersStmt = $pdo->prepare("SELECT id, title, author, price, image FROM books LIMIT 4");
    $bestsellersStmt->execute();
    $bestsellers = $bestsellersStmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "Error loading content. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Haven | Modern Online Bookstore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap">
    <style>
  :root {
            --primary: #2A5D67;
            --primary-dark: #1D454D;
            --primary-light: #E8F4F6;
            --secondary: #F5B553;
            --accent: #E34F4F;
            --dark: #1a1a1a;
            --light: #f8f9fa;
            --gray-light: #f1f3f5;
            --transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1), 0 1px 3px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1), 0 5px 10px rgba(0,0,0,0.05);
            --shadow-xl: 0 20px 40px rgba(0,0,0,0.15), 0 10px 10px rgba(0,0,0,0.05);
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: var(--light);
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        h1, h2, h3, h4, h5 {
            font-family: 'Playfair Display', serif;
            font-weight: 600;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(8px);
            box-shadow: var(--shadow-sm);
            padding: 1rem 0;
            transition: var(--transition);
        }

        .navbar.scrolled {
            box-shadow: var(--shadow-md);
            padding: 0.5rem 0;
        }

        .navbar-brand img {
            height: 40px;
            transition: var(--transition);
        }

        .nav-link {
            font-weight: 500;
            padding: 0.5rem 1rem;
            color: var(--dark);
            position: relative;
        }

        .nav-link:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: var(--transition);
        }

        .nav-link:hover:after,
        .nav-link.active:after {
            width: 70%;
        }

        .hero-section {
            height: 80vh;
            min-height: 600px;
            background: linear-gradient(135deg, rgba(42, 93, 103, 0.9) 0%, rgba(42, 93, 103, 0.7) 100%),
                        url('assets/images/banner.jpg') center/cover no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
            margin-top: -80px;
            padding-top: 80px;
        }

        .hero-section:before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100px;
            background: linear-gradient(to bottom, transparent, var(--light));
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            padding: 0 20px;
        }

        .hero-section h1 {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
            animation: fadeInDown 0.8s ease-out both;
        }

        .hero-section p {
            font-size: clamp(1rem, 2vw, 1.25rem);
            margin-bottom: 2.5rem;
            opacity: 0.9;
            animation: fadeInUp 0.8s 0.3s ease-out both;
        }

        .hero-search {
            max-width: 600px;
            margin: 0 auto;
            animation: fadeInUp 0.8s 0.5s ease-out both;
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            padding: 0.75rem 2rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: var(--transition);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background-color: var(--secondary);
            border-color: var(--secondary);
            color: var(--dark);
            font-weight: 600;
        }

        .btn-secondary:hover {
            background-color: #e6a845;
            border-color: #e6a845;
        }

        .section-title {
            position: relative;
            margin-bottom: 3rem;
            text-align: center;
        }

        .section-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: var(--secondary);
            margin: 1rem auto 0;
        }

        .book-card {
            transition: var(--transition);
            border: none;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            border-radius: 8px;
            height: 100%;
            display: flex;
            flex-direction: column;
            background: white;
        }

        .book-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .book-card-img {
            height: 280px;
            object-fit: cover;
            width: 100%;
            transition: var(--transition);
        }

        .book-card:hover .book-card-img {
            opacity: 0.9;
        }

        .book-card-body {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .book-card-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .book-card-author {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .book-card-price {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.1rem;
            margin-top: auto;
        }

        .book-card-price .original-price {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 0.9rem;
            margin-right: 0.5rem;
        }

        .book-card-price .discount {
            color: var(--accent);
            font-size: 0.9rem;
            font-weight: 600;
        }

        .rating {
            color: var(--secondary);
            margin-bottom: 0.75rem;
        }

        .category-card {
            height: 180px;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .category-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }

        .category-card:hover img {
            transform: scale(1.05);
        }

        .category-card-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            padding: 1.5rem 1rem 1rem;
            color: white;
        }

        .category-card-title {
            font-weight: 600;
            margin: 0;
        }

        .bestseller-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--accent);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 2;
        }

        .new-release-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--primary);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 2;
        }

        .quick-link-card {
            border-radius: 8px;
            overflow: hidden;
            height: 100%;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            background: white;
        }

        .quick-link-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .quick-link-card-body {
            padding: 2rem;
            text-align: center;
        }

        .quick-link-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .newsletter-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 4rem 0;
            position: relative;
            overflow: hidden;
        }

        .newsletter-section:before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: url('assets/images/pattern.svg') center/cover;
            opacity: 0.1;
        }

        .newsletter-input {
            height: 50px;
            border: none;
            border-radius: 4px 0 0 4px;
            padding: 0 1rem;
        }

        .newsletter-btn {
            height: 50px;
            border: none;
            background: var(--secondary);
            color: var(--dark);
            font-weight: 600;
            border-radius: 0 4px 4px 0;
            padding: 0 1.5rem;
            transition: var(--transition);
        }

        .newsletter-btn:hover {
            background: #e6a845;
        }

        .auth-dropdown .dropdown-menu {
            min-width: 200px;
            padding: 0.5rem 0;
            border: none;
            box-shadow: var(--shadow-lg);
            border-radius: 8px;
        }

        .auth-dropdown .dropdown-item {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .auth-dropdown .dropdown-item:hover {
            background-color: var(--primary-light);
            color: var(--primary-dark);
        }

        .auth-dropdown .dropdown-divider {
            margin: 0.25rem 0;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: var(--secondary);
            color: var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-left: 0.5rem;
        }

        footer {
            background: var(--dark);
            color: white;
            padding: 4rem 0 2rem;
        }

        .footer-logo {
            height: 40px;
            margin-bottom: 1.5rem;
        }

        .footer-links h5 {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.75rem;
        }

        .footer-links h5:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 2px;
            background: var(--secondary);
        }

        .footer-links ul {
            list-style: none;
            padding: 0;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }

        .social-icons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            color: white;
            margin-right: 0.75rem;
            transition: var(--transition);
        }

        .social-icons a:hover {
            background: var(--secondary);
            color: var(--dark);
            transform: translateY(-3px);
        }

        .copyright {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 2rem;
            margin-top: 2rem;
            text-align: center;
            color: rgba(255,255,255,0.5);
            font-size: 0.9rem;
        }

        .deal-banner {
            background: linear-gradient(135deg, var(--accent) 0%, #d14343 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: center;
        }

        .deal-banner h3 {
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .deal-banner p {
            margin-bottom: 0;
            opacity: 0.9;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .hero-section {
                height: 70vh;
                min-height: 500px;
            }
        }

        @media (max-width: 768px) {
            .hero-section {
                height: 60vh;
                min-height: 400px;
            }
            
            .hero-section h1 {
                font-size: 2.5rem;
            }
            
            .book-card-img {
                height: 220px;
            }

            .auth-buttons .btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.875rem;
            }
        }

        @media (max-width: 576px) {
            .hero-section {
                height: 50vh;
                min-height: 300px;
            }
            
            .hero-section h1 {
                font-size: 2rem;
            }
            
            .section-title {
                margin-bottom: 2rem;
            }

            .navbar-brand img {
                height: 30px;
            }
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

<!-- Navigation (same as your code) -->
<nav class="navbar navbar-expand-lg navbar-light fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="assets/images/logo.svg" alt="Book Haven">
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="#">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#categories">Categories</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#bestsellers">Bestsellers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#featured">Featured</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#new-releases">New Releases</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">Deals</a>
                </li>
            </ul>
            
            <div class="d-flex align-items-center gap-3">
                <div class="search-box">
                    <button class="btn btn-outline-light border-0 search-btn">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
                <a href="cart.php" class="btn btn-outline-light position-relative border-0">
                    <i class="bi bi-cart3"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= isset($_SESSION['cart_count']) ? $_SESSION['cart_count'] : '0' ?>
                    </span>
                </a>
                <a href="wishlist.php" class="btn btn-outline-light position-relative border-0">
                    <i class="bi bi-heart"></i>
                </a>
                
                <!-- Authentication Dropdown -->
                <div class="dropdown auth-dropdown">
                    <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center" type="button" id="authDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i> Account
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="authDropdown">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <?php if ($_SESSION['is_admin']): ?>
                                <li><h6 class="dropdown-header">Admin Dashboard</h6></li>
                                <li><a class="dropdown-item" href="admin/admin.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a></li>
                                <li><a class="dropdown-item" href="admin/books.php"><i class="bi bi-book me-2"></i> Manage Books</a></li>
                                <li><a class="dropdown-item" href="admin/orders.php"><i class="bi bi-receipt me-2"></i> Manage Orders</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="admin/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                            <?php else: ?>
                                <li><h6 class="dropdown-header">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></h6></li>
                                <li><a class="dropdown-item" href="user/admin.php"><i class="bi bi-person-circle me-2"></i> My Account</a></li>
                                <li><a class="dropdown-item" href="user/orders.php"><i class="bi bi-receipt me-2"></i> My Orders</a></li>
                                <li><a class="dropdown-item" href="user/wishlist.php"><i class="bi bi-heart me-2"></i> Wishlist</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="user/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                            <?php endif; ?>
                        <?php else: ?>
                            <li><h6 class="dropdown-header">Welcome to Book Haven</h6></li>
                            <li><a class="dropdown-item" href="user/login.php"><i class="bi bi-box-arrow-in-right me-2"></i> Login as User</a></li>
                            <li><a class="dropdown-item" href="admin/login.php"><i class="bi bi-shield-lock me-2"></i> Login as Admin</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="user/signup.php"><i class="bi bi-person-plus me-2"></i> Sign Up</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>


<!-- Hero Section (same as your code) -->
<section class="hero-section">
    <div class="hero-content">
        <h1>Discover Your Next Reading Adventure</h1>
        <p>Explore our vast collection of books across all genres. Find your next favorite read today.</p>
        <div class="hero-search">
            <form action="search.php" method="GET" class="input-group">
                <input type="text" name="q" class="form-control form-control-lg" placeholder="Search for books, authors, or categories...">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-search"></i> Search
                </button>
            </form>
        </div>
    </div>
</section>

<main class="flex-grow-1 py-5">
    <div class="container">

        <!-- Categories -->
        <section id="categories" class="mb-5 py-5">
            <h2 class="section-title">Browse Categories</h2>
            <div class="row g-4">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <div class="col-md-4 col-lg-2">
                            <a href="products/category.php?slug=<?= htmlspecialchars($category['slug']) ?>" class="text-decoration-none">
                                <div class="category-card">
                                    <img src="uploads/categories/<?= htmlspecialchars($category['image']) ?>" alt="<?= htmlspecialchars($category['name']) ?>">
                                    <div class="category-card-overlay">
                                        <h3 class="category-card-title"><?= htmlspecialchars($category['name']) ?></h3>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-4">
                        <p class="text-muted">No categories available.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Bestsellers -->
        <section id="bestsellers" class="mb-5 py-5 bg-light">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title mb-0">Bestsellers</h2>
                    <a href="#" class="btn btn-outline-primary">View All <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
                <div class="row g-4">
                    <?php if (!empty($bestsellers)): ?>
                        <?php foreach ($bestsellers as $book): ?>
                            <div class="col-md-6 col-lg-3">
                                <div class="book-card">
                                    <span class="bestseller-badge">Bestseller</span>
                                    <img src="uploads/books/<?= htmlspecialchars($book['image']) ?>" 
                                         class="book-card-img" 
                                         alt="<?= htmlspecialchars($book['title']) ?>">
                                    <div class="book-card-body">
                                        <h5 class="book-card-title"><?= htmlspecialchars($book['title']) ?></h5>
                                        <p class="book-card-author">by <?= htmlspecialchars($book['author']) ?></p>
                                        <div class="book-card-price">
                                            $<?= number_format($book['price'], 2) ?>
                                        </div>
                                        <form action="cart.php" method="POST">
                                            <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                            <button type="submit" name="add_to_cart" class="btn btn-sm btn-primary mt-2 w-100">Add to Cart</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-4">
                            <p class="text-muted">No bestsellers available.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Featured Books -->
        <section id="featured" class="mb-5 py-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="section-title mb-0">Featured Books</h2>
                <a href="#" class="btn btn-outline-primary">View All <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
            <div class="row g-4">
                <?php if (!empty($featuredBooks)): ?>
                    <?php foreach ($featuredBooks as $book): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="book-card">
                                <img src="uploads/books/<?= htmlspecialchars($book['image']) ?>" 
                                     class="book-card-img" 
                                     alt="<?= htmlspecialchars($book['title']) ?>">
                                <div class="book-card-body">
                                    <h5 class="book-card-title"><?= htmlspecialchars($book['title']) ?></h5>
                                    <p class="book-card-author">by <?= htmlspecialchars($book['author']) ?></p>
                                    <div class="book-card-price">
                                        $<?= number_format($book['price'], 2) ?>
                                    </div>
                                    <a href="products/details.php?id=<?= $book['id'] ?>" class="btn btn-sm btn-outline-primary mt-2 w-100">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-4">
                        <p class="text-muted">No featured books available.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<footer>
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <img src="assets/images/logo-white.svg" alt="Book Haven" class="footer-logo">
                <p>Book Haven is your premier destination for discovering and purchasing books across all genres. We're passionate about connecting readers with their next great read.</p>
                <div class="social-icons mt-4">
                    <a href="#"><i class="bi bi-facebook"></i></a>
                    <a href="#"><i class="bi bi-twitter"></i></a>
                    <a href="#"><i class="bi bi-instagram"></i></a>
                    <a href="#"><i class="bi bi-pinterest"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <div class="footer-links">
                    <h5>Shop</h5>
                    <ul>
                        <li><a href="#">All Books</a></li>
                        <li><a href="#">New Releases</a></li>
                        <li><a href="#">Bestsellers</a></li>
                        <li><a href="#">Coming Soon</a></li>
                        <li><a href="#">Deals & Promotions</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <div class="footer-links">
                    <h5>Categories</h5>
                    <ul>
                        <?php if (isset($footer_categories) && count($footer_categories) > 0): ?>
                            <?php foreach ($footer_categories as $category): ?>
                                <li><a href="category.php?slug=<?= htmlspecialchars($category['slug']) ?>"><?= htmlspecialchars($category['name']) ?></a></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li><a href="#">Fiction</a></li>
                            <li><a href="#">Non-Fiction</a></li>
                            <li><a href="#">Science Fiction</a></li>
                            <li><a href="#">Biography</a></li>
                            <li><a href="#">Children's Books</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 mb-4">
                <div class="footer-links">
                    <h5>Company</h5>
                    <ul>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="careers.php">Careers</a></li>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                        <li><a href="terms.php">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-2 mb-4">
                <div class="footer-links">
                    <h5>Help</h5>
                    <ul>
                        <li><a href="faq.php">FAQs</a></li>
                        <li><a href="shipping.php">Shipping Info</a></li>
                        <li><a href="returns.php">Returns</a></li>
                        <li><a href="order-status.php">Order Status</a></li>
                        <li><a href="gift-cards.php">Gift Cards</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="copyright">
            <p>&copy; <?= date('Y') ?> Book Haven. All rights reserved.</p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
            document.querySelector('.navbar-brand img').style.height = '30px';
        } else {
            navbar.classList.remove('scrolled');
            document.querySelector('.navbar-brand img').style.height = '40px';
        }
    });

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });

    // Animation on scroll
    function animateOnScroll() {
        const elements = document.querySelectorAll('.book-card, .category-card, .quick-link-card');
        
        elements.forEach(element => {
            const elementPosition = element.getBoundingClientRect().top;
            const screenPosition = window.innerHeight / 1.2;
            
            if (elementPosition < screenPosition) {
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }
        });
    }

    // Set initial state for animation
    document.querySelectorAll('.book-card, .category-card, .quick-link-card').forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    });

    // Run on load and scroll
    window.addEventListener('load', animateOnScroll);
    window.addEventListener('scroll', animateOnScroll);
</script>
</body>
</html>
