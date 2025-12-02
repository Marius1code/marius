<?php
session_start();
require_once __DIR__ . '/config.php';

$uploadDir = __DIR__ . '/uploads';
$baseUploadUrl = 'uploads';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$msg = '';

// DELETE
if(isset($_GET['delete']) && is_numeric($_GET['delete'])){
    $id=(int)$_GET['delete'];
    $stmt=$pdo->prepare("SELECT images FROM produits WHERE id=?");
    $stmt->execute([$id]);
    $row=$stmt->fetch();
    if($row && !empty($row['images'])){
        $imgs=json_decode($row['images'], true);
        foreach($imgs as $img){
            if(file_exists($uploadDir.'/'.$img)) @unlink($uploadDir.'/'.$img);
        }
    }
    $pdo->prepare("DELETE FROM produits WHERE id=?")->execute([$id]);
    header('Location: produits.php?msg=Produit supprimé');
    exit;
}

// ADD / EDIT
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save'])){
    $nom=trim($_POST['nom']??'');
    $categorie=trim($_POST['categorie']??'');
    $description=trim($_POST['description']??'');
    $prix=(float)($_POST['prix']??0);

    $images = [];
    if(!empty($_FILES['images']['name'][0])){
        foreach($_FILES['images']['tmp_name'] as $k=>$tmp){
            $orig = basename($_FILES['images']['name'][$k]);
            $ext = pathinfo($orig, PATHINFO_EXTENSION);
            $allowed=['jpg','jpeg','png','gif','webp'];
            if(in_array(strtolower($ext), $allowed)){
                $name = time().'_'.bin2hex(random_bytes(4)).'.'.$ext;
                if(move_uploaded_file($tmp, $uploadDir.'/'.$name)){
                    $images[]=$name;
                }
            }
        }
    }

    if(!empty($_POST['id'])){
        $id=(int)$_POST['id'];
        $stmt=$pdo->prepare("SELECT images FROM produits WHERE id=?");
        $stmt->execute([$id]);
        $old=json_decode($stmt->fetchColumn(),true) ?: [];
        $allImages = array_merge($old, $images);
        $pdo->prepare("UPDATE produits SET nom=?, categorie=?, description=?, prix=?, images=? WHERE id=?")
            ->execute([$nom,$categorie,$description,$prix,json_encode($allImages),$id]);
        $msg="Produit mis à jour";
    } else {
        $pdo->prepare("INSERT INTO produits(nom,categorie,description,prix,images) VALUES(?,?,?,?,?)")
            ->execute([$nom,$categorie,$description,$prix,json_encode($images)]);
        $msg="Produit ajouté";
    }
    header('Location: produits.php?msg='.urlencode($msg));
    exit;
}

// Fetch produits
$produits = $pdo->query("SELECT * FROM produits ORDER BY id DESC")->fetchAll();
$message = $_GET['msg'] ?? '';

$userDisplay = htmlspecialchars(trim($_SESSION['user_nom'] ?? '') . ' ' . trim($_SESSION['user_prenom'] ?? ''), ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Produits - NexaStore</title>
<link rel="icon" type="image/x-icon" href="/static/favicon.ico">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.sidebar { background: linear-gradient(135deg, #e6f7ff 0%, #b3e0ff 100%); }
.frosty-bg { background-color: #f0f9ff; }
.modal-bg { background-color: rgba(0,0,0,0.5); }
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
                    <li><a href="dash.php" class="flex items-center px-4 py-2 text-blue-900 hover:bg-blue-100 rounded-lg"><i data-feather="home" class="mr-2"></i> Dashboard</a></li>
                    <li><a href="employes.php" class="flex items-center px-4 py-2 text-blue-800 hover:bg-blue-50 rounded-lg"><i data-feather="users" class="mr-2"></i> Employés</a></li>
                    <li><a href="clients.php" class="flex items-center px-4 py-2 text-blue-800 hover:bg-blue-50 rounded-lg"><i data-feather="user-check" class="mr-2"></i> Clients</a></li>
                    <li><a href="produits.php" class="flex items-center px-4 py-2 text-blue-800 bg-blue-100 rounded-lg"><i data-feather="package" class="mr-2"></i> Produits</a></li>
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
                <h2 class="text-xl font-semibold text-blue-800"><i data-feather="package" class="inline mr-2"></i> Produits</h2>
            </div>
            <div class="flex items-center space-x-4">
                <b><?= $userDisplay ?></b>
            </div>
        </header>

        <main class="p-6">
            <?php if($message): ?>
                <div class="mb-4 p-2 bg-green-100 text-green-800 rounded"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <div class="bg-white shadow rounded p-4 overflow-auto">
                <div class="flex justify-end mb-4">
                    <button onclick="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded">Ajouter Produit</button>
                </div>

                <table class="min-w-full border border-gray-200">
                    <thead class="bg-blue-50">
                        <tr>
                            <th class="border px-3 py-2">ID</th>
                            <th class="border px-3 py-2">Nom</th>
                            <th class="border px-3 py-2">Catégorie</th>
                            <th class="border px-3 py-2">Description</th>
                            <th class="border px-3 py-2">Prix</th>
                            <th class="border px-3 py-2">Images</th>
                            <th class="border px-3 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($produits)): ?>
                            <tr><td colspan="7" class="text-center p-4 text-gray-500">Aucun produit</td></tr>
                        <?php else: ?>
                            <?php foreach($produits as $p): ?>
                            <tr class="hover:bg-blue-50">
                                <td class="border px-3 py-2"><?= (int)$p['id'] ?></td>
                                <td class="border px-3 py-2"><?= htmlspecialchars($p['nom']) ?></td>
                                <td class="border px-3 py-2"><?= htmlspecialchars($p['categorie']) ?></td>
                                <td class="border px-3 py-2"><?= nl2br(htmlspecialchars($p['description'])) ?></td>
                                <td class="border px-3 py-2"><?= htmlspecialchars($p['prix']) ?> €</td>
                                <td class="border px-3 py-2 flex space-x-2">
                                    <?php $imgs=json_decode($p['images'],true) ?: []; ?>
                                    <?php foreach($imgs as $img): ?>
                                        <?php if(file_exists($uploadDir.'/'.$img)): ?>
                                            <img src="<?= $baseUploadUrl.'/'.$img ?>" class="w-16 h-16 object-cover rounded">
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </td>
                                <td class="border px-3 py-2 space-x-2">
                                    <button onclick='openModal(<?= json_encode($p) ?>)' class="text-blue-600 hover:underline">Éditer</button>
                                    <a href="produits.php?delete=<?= (int)$p['id'] ?>" class="text-red-600 hover:underline" onclick="return confirm('Supprimer ?')">Supprimer</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<!-- Modal -->
<div id="modal" class="fixed inset-0 hidden items-center justify-center z-50 modal-bg">
    <div class="bg-white rounded-lg w-96 p-6 relative">
        <button onclick="closeModal()" class="absolute top-2 right-2 text-gray-500 hover:text-gray-800">×</button>
        <h2 class="text-lg font-semibold text-blue-800 mb-4" id="modalTitle">Ajouter Produit</h2>
        <form method="post" enctype="multipart/form-data" id="productForm" class="space-y-3">
            <input type="hidden" name="id" id="prodId">
            <div>
                <label class="block font-medium">Nom</label>
                <input type="text" name="nom" id="prodNom" required class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block font-medium">Catégorie</label>
                <input type="text" name="categorie" id="prodCat" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block font-medium">Description</label>
                <textarea name="description" id="prodDesc" class="w-full border rounded px-3 py-2"></textarea>
            </div>
            <div>
                <label class="block font-medium">Prix</label>
                <input type="number" step="0.01" name="prix" id="prodPrix" class="w-full border rounded px-3 py-2">
            </div>
            <div>
                <label class="block font-medium">Images</label>
                <input type="file" name="images[]" multiple class="w-full">
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal()" class="px-4 py-2 rounded border">Annuler</button>
                <button type="submit" name="save" class="bg-blue-600 text-white px-4 py-2 rounded">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
feather.replace();

// Burger menu toggle mobile
const burgerBtn = document.getElementById('burgerBtn');
const sidebar = document.getElementById('sidebar');
burgerBtn.addEventListener('click', () => {
    sidebar.classList.toggle('-translate-x-full');
});

function openModal(data=null){
    document.getElementById('modal').classList.remove('hidden');
    if(data){
        document.getElementById('modalTitle').innerText='Modifier Produit';
        document.getElementById('prodId').value=data.id;
        document.getElementById('prodNom').value=data.nom;
        document.getElementById('prodCat').value=data.categorie;
        document.getElementById('prodDesc').value=data.description;
        document.getElementById('prodPrix').value=data.prix;
    } else {
        document.getElementById('modalTitle').innerText='Ajouter Produit';
        document.getElementById('productForm').reset();
        document.getElementById('prodId').value='';
    }
}
function closeModal(){
    document.getElementById('modal').classList.add('hidden');
}
</script>

</body>
</html>
