<?php
session_start();
require_once __DIR__ . '/config.php';

// Définitions
$SEUIL_FAIBLE_STOCK = 5;

// === GÉRER ALERTES AUTOMATIQUES (produits en dessous du seuil) ===
try {
    $stmt = $pdo->prepare("SELECT id, nom, quantite FROM produits WHERE quantite <= :seuil");
    $stmt->execute([':seuil' => $SEUIL_FAIBLE_STOCK]);
    $produitsFaible = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $checkAlerte = $pdo->prepare("SELECT id FROM alertes WHERE message = :msg AND lue = 0 LIMIT 1");
    $insertAlerte = $pdo->prepare("INSERT INTO alertes (message, date_alert) VALUES (:msg, NOW())");

    foreach ($produitsFaible as $prod) {
        $nom = $prod['nom'];
        $qte = (int)$prod['quantite'];
        $msg = "Stock faible: '{$nom}' ({$qte} unités restantes)";
        $checkAlerte->execute([':msg' => $msg]);
        if (!$checkAlerte->fetch()) {
            $insertAlerte->execute([':msg' => $msg]);
        }
    }
} catch (Exception $e) {
    // error_log($e->getMessage());
}

// === STATISTIQUES ===
try {
    $totalProduits = (int)$pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE quantite <= :seuil");
    $stmt->execute([':seuil' => $SEUIL_FAIBLE_STOCK]);
    $produitsFaibleStockCount = (int)$stmt->fetchColumn();
    $totalVentes = (float)$pdo->query("SELECT COALESCE(SUM(prix_total),0) FROM ventes")->fetchColumn();
    $totalClients = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    $recentAlerts = $pdo->query("SELECT id, message, date_alert, lue FROM alertes ORDER BY date_alert DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $ventesParMois = $pdo->query("
        SELECT DATE_FORMAT(date_vente, '%Y-%m') AS mois, COALESCE(SUM(prix_total),0) AS total
        FROM ventes
        GROUP BY mois
        ORDER BY mois ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $categories = $pdo->query("
        SELECT c.id, c.nom AS categorie, COUNT(p.id) AS total
        FROM categories c
        LEFT JOIN produits p ON p.categorie_id = c.id
        GROUP BY c.id, c.nom
        ORDER BY c.nom
    ")->fetchAll(PDO::FETCH_ASSOC);
    $ventesParMoisJSON = json_encode($ventesParMois, JSON_UNESCAPED_UNICODE);
    $categoriesJSON = json_encode($categories, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $totalProduits = $produitsFaibleStockCount = $totalClients = 0;
    $totalVentes = 0.0;
    $recentAlerts = $ventesParMois = $categories = [];
    $ventesParMoisJSON = $categoriesJSON = '[]';
}

$userDisplay = htmlspecialchars((trim($_SESSION['user_nom'] ?? '') . ' ' . trim($_SESSION['user_prenom'] ?? '')), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - NexaStore</title>
<link rel="icon" type="image/x-icon" href="/static/favicon.ico">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.sidebar { background: linear-gradient(135deg, #e6f7ff 0%, #b3e0ff 100%); }
.frosty-bg { background-color: #f0f9ff; }
.alert-bubble { animation: pulse 2s infinite; }
@keyframes pulse { 0% {transform: scale(1);} 50% {transform: scale(1.05);} 100% {transform: scale(1);} }
</style>
</head>
<body class="frosty-bg">

<div class="flex h-screen">

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar w-64 shadow-lg flex flex-col fixed top-0 left-0 h-full transform -translate-x-full md:translate-x-0 transition-transform duration-300 z-50">
        <div class="p-4 text-center border-b border-blue-200">
            <h1 class="text-2xl font-bold text-blue-800 flex items-center justify-center">
                <i data-feather="wind" class="mr-2"></i> NexaStore
            </h1>
        </div>
        <div class="p-4 flex-1 overflow-auto">
            <nav>
                <ul class="space-y-1">
                    <li><a href="dash.php" class="flex items-center px-4 py-2 text-blue-900 bg-blue-100 rounded-lg"><i data-feather="home" class="mr-2"></i> Dashboard</a></li>
                    <li><a href="employes.php" class="flex items-center px-4 py-2 text-blue-800 hover:bg-blue-50 rounded-lg"><i data-feather="users" class="mr-2"></i> Employés</a></li>
                    <li><a href="clients.php" class="flex items-center px-4 py-2 text-blue-800 hover:bg-blue-50 rounded-lg"><i data-feather="user-check" class="mr-2"></i> Clients</a></li>
                    <li><a href="produits.php" class="flex items-center px-4 py-2 text-blue-800 hover:bg-blue-50 rounded-lg"><i data-feather="package" class="mr-2"></i> Produits</a></li>
                    <li><a href="categories.php" class="flex items-center px-4 py-2 text-blue-800 hover:bg-blue-50 rounded-lg"><i data-feather="grid" class="mr-2"></i> Catégories</a></li>
                    <li><a href="ventes.php" class="flex items-center px-4 py-2 text-blue-800 hover:bg-blue-50 rounded-lg"><i data-feather="shopping-cart" class="mr-2"></i> Ventes</a></li>
                    <li><a href="factures.php" class="flex items-center px-4 py-2 text-blue-800 hover:bg-blue-50 rounded-lg"><i data-feather="file-text" class="mr-2"></i> Factures</a></li>
                    <li><a href="alertes.php" class="flex items-center px-4 py-2 text-blue-800 hover:bg-blue-50 rounded-lg"><i data-feather="bell" class="mr-2"></i> Alertes</a></li>
                    <li><a href="statistiques.php" class="flex items-center px-4 py-2 text-blue-800 hover:bg-blue-50 rounded-lg"><i data-feather="bar-chart-2" class="mr-2"></i> Statistiques</a></li>
                    <li><a href="deconnexion.php" class="flex items-center px-4 py-2 text-red-700 hover:bg-red-50 rounded-lg"><i data-feather="log-out" class="mr-2"></i> Déconnexion</a></li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 overflow-auto md:ml-64">
        <header class="bg-white shadow-sm p-4 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <!-- Burger menu mobile -->
                <button id="burgerBtn" class="md:hidden p-2 rounded bg-blue-500 text-white"><i data-feather="menu"></i></button>
                <h2 class="text-xl font-semibold text-blue-800"><i data-feather="home" class="inline mr-2"></i> Dashboard</h2>
            </div>
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <i data-feather="bell" class="text-blue-600"></i>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center alert-bubble"><?= count($recentAlerts) ?></span>
                </div>
                <b><?= $userDisplay ?></b>
            </div>
        </header>

        <main class="p-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-500">Total Produits</p>
                            <h3 class="text-2xl font-bold text-blue-800"><?= $totalProduits ?></h3>
                        </div>
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600"><i data-feather="package"></i></div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-500">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-500">Produits Faible Stock (≤ <?= $SEUIL_FAIBLE_STOCK ?>)</p>
                            <h3 class="text-2xl font-bold text-red-800"><?= $produitsFaibleStockCount ?></h3>
                        </div>
                        <div class="p-3 rounded-full bg-red-100 text-red-600"><i data-feather="alert-circle"></i></div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-500">Total Ventes</p>
                            <h3 class="text-2xl font-bold text-green-800"><?= number_format($totalVentes, 2, ',', ' ') ?> FCFA</h3>
                        </div>
                        <div class="p-3 rounded-full bg-green-100 text-green-600"><i data-feather="dollar-sign"></i></div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6 border-l-4 border-purple-500">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm text-gray-500">Total Clients</p>
                            <h3 class="text-2xl font-bold text-purple-800"><?= $totalClients ?></h3>
                        </div>
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600"><i data-feather="users"></i></div>
                    </div>
                </div>
            </div>

            <!-- Recent Alerts -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="font-semibold text-blue-800 flex items-center"><i data-feather="bell" class="mr-2"></i> Alertes récentes</h3>
                </div>
                <div class="divide-y divide-gray-200">
                    <?php if (empty($recentAlerts)): ?>
                        <div class="p-4 text-gray-500">Aucune alerte récente.</div>
                    <?php else: ?>
                        <?php foreach ($recentAlerts as $alert): ?>
                            <div class="p-4 hover:bg-blue-50 flex items-start">
                                <div class="p-2 rounded-full bg-yellow-100 text-yellow-600 mr-3"><i data-feather="alert-triangle" class="w-4 h-4"></i></div>
                                <div>
                                    <p class="font-medium"><?= htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <p class="text-sm text-gray-500"><?= !empty($alert['date_alert']) ? date('d M Y H:i', strtotime($alert['date_alert'])) : '' ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="p-3 bg-gray-50 text-center">
                    <a href="alertes.php" class="text-sm text-blue-600 hover:underline">Voir toutes les alertes</a>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
feather.replace();

// Burger menu toggle
const burgerBtn = document.getElementById('burgerBtn');
const sidebar = document.getElementById('sidebar');
burgerBtn.addEventListener('click', () => {
    sidebar.classList.toggle('-translate-x-full');
});

// Animation simple pour les cards
document.querySelectorAll('.bg-white').forEach((card, index) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = `all 0.3s ease ${index * 0.06}s`;
    setTimeout(() => {
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
    }, 120);
});

// Données JSON utilisables en JS si nécessaire
const ventesParMois = <?= $ventesParMoisJSON ?>;
const categories = <?= $categoriesJSON ?>;
</script>

</body>
</html>
