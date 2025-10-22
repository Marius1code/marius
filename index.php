<?php
session_start();
include 'config.php';
// Numéro WhatsApp du magasin
$whatsapp_number = "229197044975";
// Initialiser le panier et le token CSRF
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Ajouter un produit ou modifier quantité via AJAX
if (isset($_POST['action'])) {
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die('CSRF token invalid');
    }
    $id = $_POST['id'] ?? 0;
    if ($_POST['action'] === 'add_to_cart') {
        $stmt = $pdo->prepare("SELECT * FROM produits WHERE id = ?");
        $stmt->execute([$id]);
        $produit = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($produit) {
            if(isset($_SESSION['panier'][$id])){
                $_SESSION['panier'][$id]['qty'] += 1;
            } else {
                $produit['qty'] = 1;
                $_SESSION['panier'][$id] = $produit;
            }
        }
    }
    if($_POST['action'] === 'update_qty'){
        $qty = max(1, (int)$_POST['qty']);
        if(isset($_SESSION['panier'][$id])) $_SESSION['panier'][$id]['qty'] = $qty;
    }
    if($_POST['action'] === 'remove'){
        if(isset($_SESSION['panier'][$id])) unset($_SESSION['panier'][$id]);
    }
    echo json_encode(['count' => array_sum(array_column($_SESSION['panier'], 'qty'))]);
    exit;
}
// Obtenir le lien WhatsApp et total
if (isset($_GET['action']) && $_GET['action'] === 'get_whatsapp_link') {
    $message = "🛒 Nouvelle commande NexaStore:%0A";
    $total = 0;
    foreach ($_SESSION['panier'] as $p) {
        $message .= "- " . $p['nom'] . " x".$p['qty']." (" . number_format($p['prix'], 0, ',', ' ') . " FCFA)%0A";
        $total += $p['prix'] * $p['qty'];
    }
    $message .= "%0ATotal : " . number_format($total, 0, ',', ' ') . " FCFA";
    $whatsapp_link = "https://wa.me/$whatsapp_number?text=" . $message;
    echo json_encode(['link' => $whatsapp_link, 'total' => $total]);
    exit;
}
// Endpoint pour récupérer le panier
if (isset($_GET['action']) && $_GET['action'] === 'get_cart') {
    header('Content-Type: application/json');
    echo json_encode(array_values($_SESSION['panier']), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    exit;
}
// --- FILTRES PRODUITS ---
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$order = $_GET['order'] ?? '';
$query = "SELECT * FROM produits WHERE 1=1";
if (!empty($search)) $query .= " AND nom LIKE :search";
if (!empty($category)) $query .= " AND categorie = :category";
if (!empty($order)) {
    $query .= $order == 'prix_asc' ? " ORDER BY prix ASC" : " ORDER BY prix DESC";
}
$stmt = $pdo->prepare($query);
if (!empty($search)) $stmt->bindValue(':search', "%$search%");
if (!empty($category)) $stmt->bindValue(':category', $category);
$stmt->execute();
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT DISTINCT categorie FROM produits")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NexaStore - Vente de PC & Accessoires High-Tech</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
<style>
:root {
    --primary-color: #004aad;
    --secondary-color: #009e60;
    --accent-color: #25d366;
    --dark-color: #002e6e;
    --light-color: #f4f7fb;
    --text-color: #333;
    --text-muted: #6c757d;
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.08);
    --shadow-md: 0 4px 12px rgba(0,0,0,0.1);
    --shadow-lg: 0 8px 24px rgba(0,0,0,0.12);
    --border-radius: 12px;
    --transition: all 0.3s ease;
}

body {
    background-color: var(--light-color);
    font-family: 'Poppins', sans-serif;
    color: var(--text-color);
    line-height: 1.6;
}

.navbar {
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    padding: 1rem 0;
    box-shadow: var(--shadow-sm);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.navbar-brand {
    font-weight: 700;
    font-size: 1.5rem;
    color: white !important;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.navbar-brand i {
    font-size: 1.8rem;
}

.nav-link {
    color: white !important;
    font-weight: 500;
    margin: 0 0.5rem;
    position: relative;
    transition: var(--transition);
}

.nav-link:hover {
    color: rgba(255, 255, 255, 0.9) !important;
}

.nav-link::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 0;
    width: 0;
    height: 2px;
    background-color: white;
    transition: var(--transition);
}

.nav-link:hover::after {
    width: 100%;
}

.btn-whatsapp {
    background-color: var(--accent-color);
    color: white;
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: var(--transition);
}

.btn-whatsapp:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.cart-count {
    font-weight: 600;
    background-color: white;
    color: var(--accent-color);
    border-radius: 50%;
    padding: 0.2rem 0.5rem;
    font-size: 0.8rem;
    margin-left: 0.3rem;
}

.hero {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    padding: 4rem 0;
    text-align: center;
    margin-bottom: 3rem;
    position: relative;
    overflow: hidden;
}

.hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><polygon fill="rgba(255,255,255,0.05)" points="0,0 100,0 80,100 0,100"/></svg>');
    background-size: cover;
}

.hero h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    animation: fadeInDown 0.8s ease;
}

.hero p {
    font-size: 1.2rem;
    max-width: 700px;
    margin: 0 auto 2rem;
    animation: fadeInUp 0.8s ease 0.2s both;
}

.search-bar {
    background-color: white;
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin: -2rem auto 3rem;
    box-shadow: var(--shadow-lg);
    max-width: 90%;
    animation: fadeIn 0.8s ease 0.4s both;
}

.search-bar .form-control {
    border-radius: 8px;
    padding: 0.75rem;
    border: 1px solid #e0e0e0;
    transition: var(--transition);
}

.search-bar .form-control:focus {
    border-color: var(--secondary-color);
    box-shadow: 0 0 0 0.25rem rgba(0, 158, 96, 0.15);
}

.search-bar .form-select {
    border-radius: 8px;
    padding: 0.75rem;
    border: 1px solid #e0e0e0;
}

.search-bar .btn-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    border-radius: 8px;
    padding: 0.75rem;
    font-weight: 500;
    transition: var(--transition);
}

.search-bar .btn-primary:hover {
    background-color: #003a88;
    transform: translateY(-1px);
}

.products-section {
    padding: 2rem 0;
}

.section-title {
    text-align: center;
    margin-bottom: 2rem;
    position: relative;
    display: inline-block;
}

.section-title h2 {
    font-size: 1.8rem;
    font-weight: 600;
    color: var(--dark-color);
}

.section-title::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 3px;
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    border-radius: 2px;
}

.product-card {
    border: none;
    border-radius: var(--border-radius);
    overflow: hidden;
    transition: var(--transition);
    box-shadow: var(--shadow-sm);
    background-color: white;
    margin-bottom: 1.5rem;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.product-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
}

.product-card img {
    height: 200px;
    object-fit: cover;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.card-body {
    padding: 1.25rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.card-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: var(--dark-color);
}

.card-text {
    color: var(--text-muted);
    font-size: 0.9rem;
    margin-bottom: 0.75rem;
}

.price {
    font-weight: 700;
    color: var(--secondary-color);
    font-size: 1.2rem;
    margin-bottom: 1rem;
}

.product-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: auto;
}

.product-actions .btn {
    flex: 1;
    padding: 0.5rem;
    border-radius: 6px;
    font-weight: 500;
    transition: var(--transition);
}

.product-actions .btn-outline-primary {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.product-actions .btn-outline-primary:hover {
    background-color: var(--primary-color);
    color: white;
}

.product-actions .btn-outline-secondary {
    border-color: #dee2e6;
    color: var(--text-color);
}

.product-actions .btn-outline-secondary:hover {
    background-color: #f8f9fa;
}

.modal-content {
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-lg);
}

.modal-header {
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.modal-header.bg-primary {
    background: linear-gradient(90deg, var(--primary-color), #0066cc) !important;
}

.modal-header.bg-success {
    background: linear-gradient(90deg, var(--secondary-color), #008055) !important;
}

.modal-body {
    padding: 1.5rem;
}

.carousel-inner {
    border-radius: 8px;
    overflow: hidden;
}

.carousel-item img {
    height: 350px;
    object-fit: cover;
}

.modal-footer {
    border-top: 1px solid rgba(0,0,0,0.05);
    padding: 1rem 1.5rem;
}

#cartContent {
    min-height: 150px;
}

.cart-item {
    padding: 0.75rem 0;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.cart-item:last-child {
    border-bottom: none;
}

.cart-item-name {
    font-weight: 500;
    flex: 1;
}

.cart-item-price {
    font-weight: 600;
    color: var(--secondary-color);
}

.quantity-input {
    width: 60px;
    text-align: center;
    padding: 0.375rem 0.5rem;
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

.quantity-btn {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px !important;
    padding: 0;
}

.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1100;
}

.toast {
    min-width: 280px;
    border-radius: 8px;
    box-shadow: var(--shadow-md);
}

.footer {
    background-color: var(--dark-color);
    color: #dcdcdc;
    padding: 2rem 0 1rem;
    margin-top: 4rem;
}

.footer a {
    color: #a0c4ff;
    text-decoration: none;
    transition: var(--transition);
}

.footer a:hover {
    color: white;
    text-decoration: underline;
}

.footer-social a {
    color: #dcdcdc;
    font-size: 1.2rem;
    margin: 0 0.5rem;
    transition: var(--transition);
}

.footer-social a:hover {
    color: white;
    transform: translateY(-2px);
}

@media (max-width: 768px) {
    .hero h1 {
        font-size: 2rem;
    }

    .hero p {
        font-size: 1rem;
    }

    .product-card {
        margin-bottom: 1.25rem;
    }

    .product-actions {
        flex-direction: column;
    }

    .product-actions .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Animation pour les boutons */
.btn {
    transition: var(--transition);
}

.btn:hover {
    transform: translateY(-1px);
}

/* Style pour les badges promo (à ajouter plus tard) */
.promo-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background-color: #ff4757;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Style pour les étoiles de notation */
.rating {
    color: #ffc107;
    margin-bottom: 0.5rem;
}
</style>
</head>
<body>
<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark">
    
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fa-solid fa-laptop-code"></i> NexaStore
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link active" href="#">Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">Produits</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">Promotions</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">Contact</a>
                </li>
                <li class="nav-item ms-lg-3">
                    <button id="whatsappBtn" class="btn btn-whatsapp" data-bs-toggle="modal" data-bs-target="#cartModal">
                        <i class="fab fa-whatsapp"></i>
                        <span class="d-none d-sm-inline">Panier</span>
                        <span id="cartCount" class="cart-count"><?= array_sum(array_column($_SESSION['panier'],'qty')) ?></span>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- HERO SECTION -->
<section class="hero">
    <div class="container">
        <h1>Découvrez nos PC et accessoires high-tech</h1>
        <p>Des produits performants, fiables et design pour répondre à tous vos besoins technologiques</p>
    </div>
</section>

<!-- SEARCH BAR -->
<div class="container search-bar">
    <form method="GET" class="row g-3">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Rechercher un produit..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="category" class="form-select">
                <option value="">Toutes catégories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= $cat === $category ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="order" class="form-select">
                <option value="">Trier par</option>
                <option value="prix_asc" <?= $order=='prix_asc'?'selected':'' ?>>Prix: Croissant</option>
                <option value="prix_desc" <?= $order=='prix_desc'?'selected':'' ?>>Prix: Décroissant</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-filter me-2"></i> Filtrer
            </button>
        </div>
    </form>
</div>

<!-- PRODUCTS SECTION -->
<div class="container products-section">
    <div class="section-title">
        <h2>Nos Produits Phares</h2>
    </div>
    <div class="row">
        <?php foreach($produits as $p):
        $images = json_decode($p['images'], true) ?: [];
        $first = !empty($images) ? $images[0] : 'default.jpg';
        ?>
        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
            <div class="card product-card h-100">
                <div class="position-relative">
                    <img src="uploads/<?= htmlspecialchars($first) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['nom']) ?>">
                    <?php if (!empty($p['promo'])): ?>
                        <span class="promo-badge">-<?= $p['promo'] ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($p['nom']) ?></h5>
                    <p class="card-text text-muted"><?= htmlspecialchars($p['categorie']) ?></p>
                    <div class="rating mb-2">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star-half-alt"></i>
                    </div>
                    <p class="price"><?= number_format($p['prix'],0,","," ") ?> FCFA</p>
                    <div class="product-actions">
                        <button class="btn btn-outline-primary add-to-cart" data-id="<?= $p['id'] ?>">
                            <i class="fa-solid fa-cart-plus me-1"></i> Ajouter
                        </button>
                        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#prodModal<?= $p['id'] ?>">
                            <i class="fa-solid fa-eye me-1"></i> Détails
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL PRODUIT -->
        <div class="modal fade" id="prodModal<?= $p['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><?= htmlspecialchars($p['nom']) ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div id="carousel<?= $p['id'] ?>" class="carousel slide mb-3">
                            <div class="carousel-inner">
                                <?php if (!empty($images)): ?>
                                    <?php foreach($images as $k => $img): ?>
                                    <div class="carousel-item <?= $k === 0 ? 'active' : '' ?>">
                                        <img src="uploads/<?= htmlspecialchars($img) ?>" class="d-block w-100 rounded" style="height:350px; object-fit:cover;">
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="carousel-item active">
                                        <img src="uploads/default.jpg" class="d-block w-100 rounded" style="height:350px; object-fit:cover;">
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#carousel<?= $p['id'] ?>" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon"></span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#carousel<?= $p['id'] ?>" data-bs-slide="next">
                                <span class="carousel-control-next-icon"></span>
                            </button>
                        </div>
                        <div class="rating mb-3">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                            <span class="ms-2">4.7 (12 avis)</span>
                        </div>
                        <p class="mb-3"><?= nl2br(htmlspecialchars($p['description'])) ?></p>
                        <p class="fw-bold fs-4 text-success"><?= number_format($p['prix'],0,","," ") ?> FCFA</p>
                        <?php if (!empty($p['stock'])): ?>
                            <p class="text-success"><i class="fas fa-check-circle me-2"></i> En stock (<?= $p['stock'] ?>)</p>
                        <?php else: ?>
                            <p class="text-danger"><i class="fas fa-times-circle me-2"></i> Rupture de stock</p>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button class="btn btn-outline-primary add-to-cart" data-id="<?= $p['id'] ?>">
                            <i class="fa-solid fa-cart-plus me-2"></i> Ajouter au panier
                        </button>
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Continuer mes achats</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- CTA SECTION -->
<section class="py-5" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
    <div class="container text-center">
        <h2 class="mb-4">Besoin d'aide pour choisir ?</h2>
        <p class="mb-4 max-width-700 mx-auto">Notre équipe d'experts est disponible pour vous conseiller et vous aider à trouver le produit parfait pour vos besoins.</p>
        <button class="btn btn-primary btn-lg px-4 me-2">
            <i class="fas fa-phone-alt me-2"></i> Nous contacter
        </button>
        <button class="btn btn-outline-secondary btn-lg px-4">
            <i class="fas fa-comments me-2"></i> Chat en direct
        </button>
    </div>
</section>

<!-- MODAL PANIER -->
<div class="modal fade" id="cartModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fa-solid fa-cart-shopping me-2"></i> Votre panier
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="cartContent">
                <div class="text-center py-4">
                    <i class="fas fa-cart-arrow-down mb-3" style="font-size: 3rem; color: #ddd;"></i>
                    <p class="text-muted">Votre panier est vide.</p>
                    <p class="small">Ajoutez des produits pour commencer vos achats</p>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-success mb-0" id="cartTotal"></h5>
                <a id="goWhatsApp" href="#" target="_blank" class="btn btn-success btn-lg">
                    <i class="fab fa-whatsapp me-2"></i> Commander via WhatsApp
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Conteneur pour les toasts -->
<div class="toast-container"></div>

<!-- FOOTER -->
<footer class="footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4 text-center text-md-start mb-3 mb-md-0">
                <h5 class="text-white mb-3">NexaStore</h5>
                <p class="small">Votre partenaire high-tech pour des produits de qualité à des prix compétitifs.</p>
                <div class="footer-social mt-3">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-whatsapp"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
            <div class="col-md-2 text-center text-md-start mb-3 mb-md-0">
                <h6 class="text-white mb-3">Liens utiles</h6>
                <ul class="list-unstyled">
                    <li><a href="#">Accueil</a></li>
                    <li><a href="#">Produits</a></li>
                    <li><a href="#">Promotions</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
            </div>
            <div class="col-md-3 text-center text-md-start mb-3 mb-md-0">
                <h6 class="text-white mb-3">Service client</h6>
                <ul class="list-unstyled">
                    <li><a href="#">FAQ</a></li>
                    <li><a href="#">Livraison</a></li>
                    <li><a href="#">Retours</a></li>
                    <li><a href="#">Garanties</a></li>
                </ul>
            </div>
            <div class="col-md-3 text-center text-md-start">
                <h6 class="text-white mb-3">Légal</h6>
                <ul class="list-unstyled">
                    <li><a href="#">Mentions légales</a></li>
                    <li><a href="#">Politique de confidentialité</a></li>
                    <li><a href="#">Conditions générales</a></li>
                </ul>
            </div>
        </div>
        <hr class="my-3">
        <div class="text-center">
            <p class="small mb-0">&copy; <?= date('Y') ?> <strong>NexaStore</strong>. Tous droits réservés.</p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Fonction pour afficher un toast
function showToast(message, title, type = "success") {
    const toastContainer = document.querySelector('.toast-container');
    const toastId = 'toast-' + Date.now();
    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}</strong>
                    <p class="mb-0">${message}</p>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    const toast = new bootstrap.Toast(document.getElementById(toastId), {
        autohide: true,
        delay: 3000
    });
    toast.show();
    document.getElementById(toastId).addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}

// Ajouter au panier
document.querySelectorAll('.add-to-cart').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: 'action=add_to_cart&id=' + id + '&csrf_token=' + document.querySelector('meta[name="csrf-token"]').content
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('cartCount').textContent = data.count;
            showToast("Produit ajouté au panier avec succès !", "NexaStore", "success");
            loadCartContent();
        })
        .catch(error => {
            showToast("Une erreur est survenue. Veuillez réessayer.", "NexaStore", "danger");
        });
    });
});

// Charger le contenu du panier
function loadCartContent() {
    fetch('?action=get_cart')
    .then(res => res.json())
    .then(panier => {
        let html = '';
        if (panier.length > 0) {
            let total = 0;
            panier.forEach(p => {
                const itemTotal = p.prix * p.qty;
                total += itemTotal;
                html += `
                <div class="cart-item d-flex justify-content-between align-items-center">
                    <div class="cart-item-name">
                        <h6 class="mb-0">${p.nom}</h6>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-outline-secondary quantity-btn decrease" data-id="${p.id}">-</button>
                        <input type="text" class="form-control form-control-sm quantity-input" value="${p.qty}" readonly>
                        <button class="btn btn-sm btn-outline-secondary quantity-btn increase" data-id="${p.id}">+</button>
                        <span class="cart-item-price">${itemTotal.toLocaleString('fr-FR')} FCFA</span>
                        <button class="btn btn-sm btn-outline-danger remove" data-id="${p.id}">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>`;
            });

            // Ajouter le résumé
            html += `
            <div class="border-top pt-3 mt-3">
                <div class="d-flex justify-content-between mb-2">
                    <span class="fw-bold">Sous-total:</span>
                    <span class="fw-bold">${total.toLocaleString('fr-FR')} FCFA</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="fw-bold">Total:</span>
                    <span class="fw-bold text-success fs-5">${total.toLocaleString('fr-FR')} FCFA</span>
            </div>`;
        } else {
            html = `
            <div class="text-center py-4">
                <i class="fas fa-cart-arrow-down mb-3" style="font-size: 3rem; color: #ddd;"></i>
                <p class="text-muted">Votre panier est vide.</p>
                <p class="small">Ajoutez des produits pour commencer vos achats</p>
            </div>`;
        }
        document.getElementById('cartContent').innerHTML = html;
fetch('?action=get_whatsapp_link')
    .then(res => res.json())
    .then(data => {
        document.getElementById('cartTotal').innerHTML = data.total > 0
            ? `<span class="fs-4 d-none">${data.total.toLocaleString('fr-FR')} FCFA</span>`
            : '';
        document.getElementById('goWhatsApp').href = data.link;
    });



        // Gestion + / - / supprimer
        setTimeout(() => {
            document.querySelectorAll('.increase').forEach(b => {
                b.addEventListener('click', () => updateQty(b.dataset.id, 'plus'));
            });
            document.querySelectorAll('.decrease').forEach(b => {
                b.addEventListener('click', () => updateQty(b.dataset.id, 'moins'));
            });
            document.querySelectorAll('.remove').forEach(b => {
                b.addEventListener('click', () => updateQty(b.dataset.id, 'remove'));
            });
        }, 300);
    });
}

// Mettre à jour la quantité
function updateQty(id, action) {
    let qtyElement = document.querySelector(`.increase[data-id="${id}"]`)?.previousElementSibling ||
                     document.querySelector(`.decrease[data-id="${id}"]`)?.nextElementSibling;
    let qty = 1;

    if (action === 'plus') {
        qty = parseInt(qtyElement.value) + 1;
    } else if (action === 'moins') {
        qty = Math.max(1, parseInt(qtyElement.value) - 1);
    }

    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: 'action=' + (action === 'remove' ? 'remove' : 'update_qty') + '&id=' + id + '&qty=' + qty + '&csrf_token=' + document.querySelector('meta[name="csrf-token"]').content
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('cartCount').textContent = data.count;
        if (action === 'remove') {
            showToast("Produit retiré du panier.", "NexaStore", "info");
        }
        loadCartContent();
    });
}

// Charger le contenu du panier au chargement de la modal
document.getElementById('cartModal').addEventListener('shown.bs.modal', function () {
    loadCartContent();
});
</script>
</body>
</html>
