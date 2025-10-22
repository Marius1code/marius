<?php
// index.php - Simple product management with PHP + SQLite + Bootstrap
// GitHub Copilot

session_start();

// CSRF token
if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(32));
}
$token = $_SESSION['token'];

// Flash helpers
function flash($msg, $type = 'success') {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}
function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// SQLite DB (products.db in same folder)
try {
    $db = new PDO('sqlite:' . __DIR__ . '/products.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            price REAL NOT NULL DEFAULT 0,
            stock INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {
    die("DB error: " . htmlspecialchars($e->getMessage()));
}

// Helper: redirect
function redirect($url = '') {
    header('Location: ' . ($url ?: $_SERVER['PHP_SELF']));
    exit;
}

// Validate token
function check_token($posted) {
    return isset($posted) && hash_equals($_SESSION['token'], $posted);
}

// Handle POST actions: add, update, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    if (!check_token($_POST['token'] ?? '')) {
        flash('Invalid CSRF token', 'danger');
        redirect();
    }

    $action = $_POST['action'];
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);

        if ($name === '') {
            flash('Le nom du produit est requis', 'danger');
            redirect();
        }

        $stmt = $db->prepare("INSERT INTO products (name, description, price, stock) VALUES (:name, :desc, :price, :stock)");
        $stmt->execute([':name' => $name, ':desc' => $desc, ':price' => $price, ':stock' => $stock]);
        flash('Produit ajouté avec succès');
        redirect();
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);

        if ($id <= 0 || $name === '') {
            flash('Données invalides pour la mise à jour', 'danger');
            redirect();
        }

        $stmt = $db->prepare("UPDATE products SET name = :name, description = :desc, price = :price, stock = :stock WHERE id = :id");
        $stmt->execute([':name' => $name, ':desc' => $desc, ':price' => $price, ':stock' => $stock, ':id' => $id]);
        flash('Produit mis à jour');
        redirect();
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            flash('ID invalide', 'danger');
            redirect();
        }
        $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
        $stmt->execute([':id' => $id]);
        flash('Produit supprimé');
        redirect();
    }
}

// If editing, fetch product
$editing = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

// Fetch all products
$products = $db->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$flash = get_flash();

function e($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Gestion des produits</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Gestion des produits</h1>
        <a href="<?php echo e($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-secondary btn-sm">Réinitialiser</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?> alert-dismissible">
            <?php echo e($flash['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $editing ? 'Modifier le produit' : 'Ajouter un produit'; ?></h5>
                    <form method="post" class="needs-validation" novalidate>
                        <input type="hidden" name="token" value="<?php echo e($token); ?>">
                        <?php if ($editing): ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?php echo e($editing['id']); ?>">
                        <?php else: ?>
                            <input type="hidden" name="action" value="add">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Nom</label>
                            <input name="name" type="text" class="form-control" required value="<?php echo e($editing['name'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?php echo e($editing['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="row gx-2">
                            <div class="col">
                                <label class="form-label">Prix (€)</label>
                                <input name="price" type="number" step="0.01" min="0" class="form-control" required value="<?php echo e($editing['price'] ?? '0.00'); ?>">
                            </div>
                            <div class="col">
                                <label class="form-label">Stock</label>
                                <input name="stock" type="number" min="0" class="form-control" required value="<?php echo e($editing['stock'] ?? '0'); ?>">
                            </div>
                        </div>

                        <div class="mt-3 d-flex gap-2">
                            <button class="btn btn-primary" type="submit"><?php echo $editing ? 'Mettre à jour' : 'Ajouter'; ?></button>
                            <?php if ($editing): ?>
                                <a href="<?php echo e($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-secondary">Annuler</a>
                            <?php else: ?>
                                <button type="reset" class="btn btn-outline-secondary">Réinitialiser</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mt-3 text-muted small">
                Base de données: SQLite (products.db)
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Liste des produits (<?php echo count($products); ?>)</h5>
                    <?php if (empty($products)): ?>
                        <p class="text-muted">Aucun produit. Ajoutez-en un via le formulaire.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nom</th>
                                        <th>Prix</th>
                                        <th>Stock</th>
                                        <th>Créé</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($products as $p): ?>
                                    <tr>
                                        <td><?php echo e($p['name']); ?><div class="text-muted small"><?php echo e($p['description']); ?></div></td>
                                        <td><?php echo number_format($p['price'], 2, ',', ' '); ?> €</td>
                                        <td><?php echo e($p['stock']); ?></td>
                                        <td class="text-nowrap"><?php echo e($p['created_at']); ?></td>
                                        <td class="text-end">
                                            <a href="?action=edit&id=<?php echo e($p['id']); ?>" class="btn btn-sm btn-outline-primary">Modifier</a>

                                            <form method="post" class="d-inline-block" onsubmit="return confirm('Supprimer ce produit ?');">
                                                <input type="hidden" name="token" value="<?php echo e($token); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo e($p['id']); ?>">
                                                <button class="btn btn-sm btn-outline-danger">Supprimer</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-4 text-center text-muted small">
        Exemple simple — PHP + SQLite + Bootstrap
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>