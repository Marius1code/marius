<?php
include 'config.php';

// Gestion des filtres
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$order = $_GET['order'] ?? '';

// Préparer la requête SQL avec filtres dynamiques
$query = "SELECT * FROM produits WHERE 1=1";

if (!empty($search)) {
    $query .= " AND nom LIKE :search";
}
if (!empty($category)) {
    $query .= " AND categorie = :category";
}
if (!empty($order)) {
    if ($order == 'prix_asc') {
        $query .= " ORDER BY prix ASC";
    } elseif ($order == 'prix_desc') {
        $query .= " ORDER BY prix DESC";
    }
}

$stmt = $pdo->prepare($query);

// Liaison des paramètres
if (!empty($search)) {
    $stmt->bindValue(':search', "%$search%");
}
if (!empty($category)) {
    $stmt->bindValue(':category', $category);
}

$stmt->execute();
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les catégories distinctes
$categories = $pdo->query("SELECT DISTINCT categorie FROM produits")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>NexaStore - Vente de PC & Accessoires</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f8;
            font-family: 'Poppins', sans-serif;
        }
        .navbar {
            background-color: #004aad;
        }
        .navbar-brand {
            font-weight: bold;
            color: white !important;
            letter-spacing: 1px;
        }
        .nav-link {
            color: white !important;
            margin-right: 15px;
            transition: 0.3s;
        }
        .nav-link:hover {
            color: #04d47c !important;
        }
        .hero {
            background: linear-gradient(to right, #004aad, #04d47c);
            color: white;
            padding: 60px 0;
            text-align: center;
        }
        .hero h1 {
            font-size: 2.8rem;
            font-weight: 700;
        }
        .hero p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        .search-bar {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: -40px;
        }
        .product-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 15px;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        .card-img-top {
            height: 200px;
            object-fit: cover;
            border-radius: 15px 15px 0 0;
        }
        .footer {
            background-color: #002e6e;
            color: #dcdcdc;
            padding: 30px 0;
            margin-top: 60px;
        }
        .footer a {
            color: #04d47c;
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<!-- ==================== NAVBAR ==================== -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="#"><i class="fa-solid fa-laptop"></i> NexaStore</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a href="#" class="nav-link">Accueil</a></li>
                <li class="nav-item"><a href="#" class="nav-link">Produits</a></li>
                <li class="nav-item"><a href="#" class="nav-link">À propos</a></li>
                <li class="nav-item"><a href="#" class="nav-link">Contact</a></li>
                <li class="nav-item">
                    <a href="#" class="btn btn-success text-white"><i class="fa-brands fa-whatsapp"></i> Commander</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- ==================== HERO ==================== -->
<section class="hero">
    <div class="container">
        <h1>Découvrez les meilleurs PC et accessoires à prix compétitifs 💻</h1>
        <p>Ordinateurs portables, composants, périphériques et bien plus encore.</p>
    </div>
</section>

<!-- ==================== BARRE DE RECHERCHE ==================== -->
<div class="container search-bar shadow-sm">
    <form method="GET" class="row g-3 align-items-center">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="🔍 Rechercher un produit..."
                   value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-3">
            <select name="category" class="form-select">
                <option value="">Toutes les catégories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $cat === $category ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="order" class="form-select">
                <option value="">Trier par</option>
                <option value="prix_asc" <?= $order === 'prix_asc' ? 'selected' : '' ?>>Prix croissant</option>
                <option value="prix_desc" <?= $order === 'prix_desc' ? 'selected' : '' ?>>Prix décroissant</option>
            </select>
        </div>
        <div class="col-md-2 text-end">
            <button type="submit" class="btn btn-primary w-100">Filtrer</button>
        </div>
    </form>
</div>

<!-- ==================== PRODUITS ==================== -->
<div class="container my-5">
    <div class="row">
        <?php if (count($produits) > 0): ?>
            <?php foreach ($produits as $p): ?>
                <div class="col-md-3 mb-4">
                    <div class="card product-card shadow-sm">
                        <img src="uploads/<?= htmlspecialchars($p['image'] ?? 'default.jpg') ?>" class="card-img-top" alt="Produit">
                        <div class="card-body text-center">
                            <h5 class="card-title text-primary"><?= htmlspecialchars($p['nom']) ?></h5>
                            <p class="card-text text-muted"><?= htmlspecialchars($p['categorie']) ?></p>
                            <p class="fw-bold text-success"><?= number_format($p['prix'], 0, ',', ' ') ?> FCFA</p>
                            <a href="#" class="btn btn-outline-primary w-100"><i class="fa-solid fa-cart-plus"></i> Ajouter au panier</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-center text-muted">Aucun produit trouvé pour ces critères.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== FOOTER ==================== -->
<footer class="footer text-center">
    <div class="container">
        <p>&copy; <?= date('Y') ?> <strong>NexaStore</strong>. Tous droits réservés.</p>
        <p>
            <a href="#">Mentions légales</a> | 
            <a href="#">Politique de confidentialité</a> | 
            <a href="#">Contact</a>
        </p>
        <div class="mt-3">
            <a href="#" class="me-3"><i class="fa-brands fa-facebook"></i></a>
            <a href="#" class="me-3"><i class="fa-brands fa-instagram"></i></a>
            <a href="#"><i class="fa-brands fa-whatsapp"></i></a>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
