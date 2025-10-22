<?php
// ==========================================
// ⚙️ CONFIGURATION AUTO (local / en ligne)
// ==========================================

// Détection automatique de l'environnement
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
    // ✅ Mode LOCAL (XAMPP)
    $servername = "127.0.0.1";
    $dbname     = "NexaStore";
    $username   = "root";
    $password   = "";
} else {
    // 🌐 Mode EN LIGNE (InfinityFree)
    $servername = "sql111.infinityfree.com";
    $dbname     = "if0_40225438_nexastore";
    $username   = "if0_40225438";
    $password   = "0xPyA0CYFV";
}

try {
    // Connexion PDO
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // (Facultatif) — message de test
    // echo "<p style='color:green;'>✅ Connecté à la base : <b>$dbname</b></p>";

} catch (PDOException $e) {
    echo "<p style='color:red; font-weight:bold;'>❌ Erreur de connexion à la base de données :</p>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    $pdo = null;
}
?>
