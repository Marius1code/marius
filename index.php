<?php
session_start();
include 'config.php';
// Numéro WhatsApp du magasin
$whatsapp_number = "97044975";
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
            if (isset($_SESSION['panier'][$id])) {
                $_SESSION['panier'][$id]['qty'] += 1;
            } else {
                $produit['qty'] = 1;
                $_SESSION['panier'][$id] = $produit;
            }
        }
    }
    if ($_POST['action'] === 'update_qty') {
        $qty = max(1, (int)$_POST['qty']);
        if (isset($_SESSION['panier'][$id])) $_SESSION['panier'][$id]['qty'] = $qty;
    }
    if ($_POST['action'] === 'remove') {
        if (isset($_SESSION['panier'][$id])) unset($_SESSION['panier'][$id]);
    }
    echo json_encode(['count' => array_sum(array_column($_SESSION['panier'], 'qty'))]);
    exit;
}
// Obtenir le lien WhatsApp et total
if (isset($_GET['action']) && $_GET['action'] === 'get_whatsapp_link') {
    $message = "🛒 Nouvelle commande NexaStore:%0A";
    $total = 0;
    foreach ($_SESSION['panier'] as $p) {
        $images = json_decode($p['images'], true) ?: [];
        $firstImage = !empty($images) ? "https://nexastore.kesug.com//uploads/" . $images[0] : "";
        $message .= "- " . $p['nom'] . " x" . $p['qty'] . " (" . number_format($p['prix'], 0, ',', ' ') . " FCFA)%0A" . '=' . '' . number_format($p['prix'] * $p['qty'], 0, ',', ' ') . " FCFA%0A";
        if (!empty($firstImage)) {
            $message .= "📷 " . $firstImage . "%0A";
        }
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
  <link rel="stylesheet" href="style.css">
</head>

<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dash.php">
                NexaStores
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
                            <span id="cartCount" class="cart-count"><?= array_sum(array_column($_SESSION['panier'], 'qty')) ?></span>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <!-- HERO SECTION -->
    <section class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                   
                    <p class="lead mb-4">
                        Découvrez notre gamme complète de <strong>PC, disques durs, chargeurs, clés USB, batteries</strong> et bien plus.
                        Des produits performants, fiables et adaptés à tous vos besoins technologiques.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#products" class="btn btn-outline-primary  text-white btn-lg px-4 py-2">
                            <i class="fas fa-shopping-bag me-2"></i> Découvrir nos produits
                        </a>
                        <a href="https://wa.me/97044975" class="btn btn-success text-white btn-lg px-4 py-2" target="_blank">
                            <i class="fab fa-whatsapp me-2"></i> Nous contacter
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block">
                    <!-- Image illustrative (optionnelle) -->
                    <img src="images/img1.png"
                        alt="Produits high-tech : PC, disques durs, accessoires"
                        class="img-fluid rounded-3 shadow-lg"
                        style="max-width: 100%; border: 5px solid rgba(255, 255, 255, 0.2);">
                </div>
            </div>
        </div>
    </section>


    <!-- SEARCH BAR -->
    <div class="container">
        <div class="search-bar">
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
                        <option value="prix_asc" <?= $order == 'prix_asc' ? 'selected' : '' ?>>Prix: Croissant</option>
                        <option value="prix_desc" <?= $order == 'prix_desc' ? 'selected' : '' ?>>Prix: Décroissant</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i> Filtrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- PRODUCTS SECTION -->
    <div class="container products-section">
        <div class="section-title">
            <h2>Nos Produits Phares</h2>
        </div>
        <div class="row code">
            <?php foreach ($produits as $p):
                $images = json_decode($p['images'], true) ?: [];
                $first = !empty($images) ? $images[0] : 'default.jpg';
                $prix_promo = !empty($p['promo']) ? $p['prix'] * (1 - $p['promo'] / 100) : null;
            ?>
                <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                    <div class="card product-card h-100">
                        <div class="position-relative">
                            <img src="uploads/<?= htmlspecialchars($first) ?>" class="card-img-top" alt="<?= htmlspecialchars($p['nom']) ?>">
                            <?php if (!empty($p['promo'])): ?>
                                <span class="promo-badge">-<?= $p['promo'] ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($p['nom']) ?></h5>
                            <p class="card-text">
                                <?= htmlspecialchars(substr($p['description'], 0, 80)) ?>
                                <?= (strlen($p['description'])   > 80) ? '...' : '' ?>
                            </p>
                            <div class="rating mb-2">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                            </div>
                            <div class="d-flex align-items-center">
                                <?php if ($prix_promo): ?>
                                    <p class="price mb-0"><?= number_format($prix_promo, 0, ",", " ") ?> FCFA</p>
                                    <p class="old-price mb-0"><?= number_format($p['prix'], 0, ",", " ") ?> FCFA</p>
                                <?php else: ?>
                                    <p class="price mb-0"><b class="text-danger">Prix :</b> <b class="mx-2"><?= number_format($p['prix'], 0, ",", " ") ?> FCFA</b></p>
                                <?php endif; ?>
                            </div>
                            <div class="product-actions mt-auto">
                                <button class="btn btn-outline-primary add-to-cart" data-id="<?= $p['id'] ?>">
                                    <i class="fa-solid fa-cart-plus"></i> Ajouter
                                </button>
                                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#prodModal<?= $p['id'] ?>">
                                    <i class="fa-solid fa-eye"></i> Détails
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
                                            <?php foreach ($images as $k => $img): ?>
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
                                    <?php if (count($images) > 1): ?>
                                        <button class="carousel-control-prev" type="button" data-bs-target="#carousel<?= $p['id'] ?>" data-bs-slide="prev">
                                            <span class="carousel-control-prev-icon"></span>
                                        </button>
                                        <button class="carousel-control-next" type="button" data-bs-target="#carousel<?= $p['id'] ?>" data-bs-slide="next">
                                            <span class="carousel-control-next-icon"></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="rating mb-3">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star-half-alt"></i>
                                    <span class="ms-2">4.7 (12 avis)</span>
                                </div>
                                 <h5 class="card-title"><?= htmlspecialchars($p['nom']) ?></h5>
                                <p class="mb-3"><?= nl2br(htmlspecialchars($p['description'])) ?></p>
                                <div class="d-flex align-items-center mb-3">
                                    <?php if ($prix_promo): ?>
                                        <p class="price mb-0 me-2"><?= number_format($prix_promo, 0, ",", " ") ?> FCFA</p>
                                        <p class="old-price mb-0"><?= number_format($p['prix'], 0, ",", " ") ?> FCFA</p>
                                    <?php else: ?>
                                      prix  <p class="price mb-0"><?= number_format($p['prix'], 0, ",", " ") ?> FCFA</p>
                                    <?php endif; ?>
                                </div>
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


    <!-- AVIS CLIENTS SECTION -->
    <section class="py-5" style="background-color: #f8f9fa; ">
        <div class="container">
            <div class="section-title">
                <h2>Avis de nos clients</h2>
            </div>
            <div class="row g-4">
                <!-- Avis 1 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <img src="https://randomuser.me/api/portraits/women/44.jpg" alt="Client" class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                <div>
                                    <h5 class="mb-0">Marie D.</h5>
                                    <div class="rating mb-1">
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-0">
                                "J’ai acheté un PC portable et un disque dur SSD. Livraison rapide, produits conformes à la description, et le service client est très réactif ! Je recommande NexaStore à 100%."
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Avis 2 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="Client" class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                <div>
                                    <h5 class="mb-0">Jean L.</h5>
                                    <div class="rating mb-1">
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star-half-alt text-warning"></i>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-0">
                                "Excellente expérience d’achat. Les prix sont compétitifs, et la qualité des produits est au rendez-vous. J’ai commandé un chargeur et un clavier mécanique, tout est parfait !"
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Avis 3 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <img src="https://randomuser.me/api/portraits/women/68.jpg" alt="Client" class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                <div>
                                    <h5 class="mb-0">Sophie T.</h5>
                                    <div class="rating mb-1">
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-0">
                                "Le site est très intuitif, et la commande a été traitée en moins de 24h. J’ai reçu mon colis bien emballé et en parfait état. Merci NexaStore pour votre professionnalisme !"
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Avis 4 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <img src="https://randomuser.me/api/portraits/men/75.jpg" alt="Client" class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                <div>
                                    <h5 class="mb-0">Thomas R.</h5>
                                    <div class="rating mb-1">
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="far fa-star text-warning"></i>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-0">
                                "J’ai acheté une tour PC gaming sur mesure. Les conseils avant achat ont été précieux, et la machine est exactement comme je l’espérais. Un grand merci à l’équipe !"
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Avis 5 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <img src="https://randomuser.me/api/portraits/women/29.jpg" alt="Client" class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                <div>
                                    <h5 class="mb-0">Laura M.</h5>
                                    <div class="rating mb-1">
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-0">
                                "Service impeccable ! J’ai eu un problème avec un produit, et le SAV a résolu mon souci en moins de 48h. C’est rare de nos jours, donc je tenais à le souligner."
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Avis 6 -->
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <img src="https://randomuser.me/api/portraits/men/41.jpg" alt="Client" class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                <div>
                                    <h5 class="mb-0">David K.</h5>
                                    <div class="rating mb-1">
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                    </div>
                                </div>
                            </div>
                            <p class="mb-0">
                                "Je commande régulièrement chez NexaStore pour mon entreprise. Les produits sont toujours conformes, et les délais de livraison sont respectés. Un partenaire de confiance !"
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Bouton pour laisser un avis -->
            <div class="text-center mt-5">
                <a href="#" class="btn btn-outline-primary btn-lg px-4" data-bs-toggle="modal" data-bs-target="#leaveReviewModal">
                    <i class="fa-solid fa-comment-dots me-2"></i> Laisser un avis
                </a>
            </div>
        </div>
    </section>

    <!-- MODAL POUR LAISSER UN AVIS -->
    <div class="modal fade" id="leaveReviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fa-solid fa-star me-2"></i> Laisser un avis
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="reviewForm">
                        <div class="mb-3">
                            <label for="reviewName" class="form-label">Votre nom</label>
                            <input type="text" class="form-control" id="reviewName" placeholder="Ex: Jean Dupont" required>
                        </div>
                        <div class="mb-3">
                            <label for="reviewRating" class="form-label">Note</label>
                            <div class="rating-select">
                                <i class="far fa-star text-warning fs-4 me-1" data-value="1"></i>
                                <i class="far fa-star text-warning fs-4 me-1" data-value="2"></i>
                                <i class="far fa-star text-warning fs-4 me-1" data-value="3"></i>
                                <i class="far fa-star text-warning fs-4 me-1" data-value="4"></i>
                                <i class="far fa-star text-warning fs-4" data-value="5"></i>
                            </div>
                            <input type="hidden" id="reviewRatingValue" value="5">
                        </div>
                        <div class="mb-3">
                            <label for="reviewMessage" class="form-label">Votre avis</label>
                            <textarea class="form-control" id="reviewMessage" rows="4" placeholder="Partagez votre expérience..." required></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="button" class="btn btn-primary" id="submitReview">
                        <i class="fa-solid fa-paper-plane me-2"></i> Envoyer
                    </button>
                </div>
            </div>
        </div>
    </div>



    <!-- CTA SECTION -->
    <section class="py-5" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;">
        <div class="container text-center">
            <h2 class="mb-4">Besoin d'aide pour choisir ?</h2>
            <p class="mb-4 mx-auto" style="max-width: 700px;">Notre équipe d'experts est disponible pour vous conseiller et vous aider à trouver le produit parfait pour vos besoins.</p>
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
                <!-- Colonne 1 : À propos -->
                <div class="col-md-4 text-center text-md-start mb-4 mb-md-0">
                    <h5>NexaStore</h5>
                    <p class="small">
                        Votre partenaire high-tech pour des produits de qualité à des prix compétitifs. Nous proposons une large gamme de PC, accessoires, et solutions informatiques pour particuliers et professionnels.
                    </p>
                    <div class="footer-social mt-3">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="https://wa.me/97044975" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                <!-- Colonne 2 : Liens utiles -->
                <div class="col-md-2 text-center text-md-start mb-4 mb-md-0">
                    <h6>Liens utiles</h6>
                    <ul>
                        <li><a href="index.php">Accueil</a></li>
                        <li><a href="#">Produits</a></li>
                        <li><a href="#">Promotions</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
                </div>
                <!-- Colonne 3 : Service client -->
                <div class="col-md-3 text-center text-md-start mb-4 mb-md-0">
                    <h6>Service client</h6>
                    <ul>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Livraison & Retours</a></li>
                        <li><a href="#">Garanties</a></li>
                        <li><a href="#">SAV</a></li>
                    </ul>
                </div>
                <!-- Colonne 4 : Légal -->
                <div class="col-md-3 text-center text-md-start">
                    <h6>Légal</h6>
                    <ul>
                        <li><a href="#">Mentions légales</a></li>
                        <li><a href="#">Politique de confidentialité</a></li>
                        <li><a href="#">Conditions générales</a></li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="footer-bottom">
                <p class="mb-0">
                    &copy; <?= date('Y') ?> <a href="index.php"><strong>NexaStore</strong></a>. Tous droits réservés.
                </p>
            </div>
        </div>
    </footer>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
</body>

</html>