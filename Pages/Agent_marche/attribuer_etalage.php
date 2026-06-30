<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';
require_once __DIR__ . '/../../Classes/Etalage.php';
require_once __DIR__ . '/../../Classes/Commercant.php';
require_once __DIR__ . '/../../Classes/Location.php';
require_once __DIR__ . '/../../Classes/Database.php';

$agent = new AgentMarche();
if (!$agent->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$etalage_id = $_GET['id'] ?? 0;
if (!$etalage_id) {
    header('Location: dashboard.php?error=id_etalage_manquant');
    exit;
}

$db = Database::getInstance()->getConnection();

// Récupérer l'étalage
$stmt = $db->prepare("SELECT * FROM etalage WHERE id_etalage = ?");
$stmt->execute([$etalage_id]);
$etalage = $stmt->fetch();

if (!$etalage) {
    header('Location: dashboard.php?error=etalage_introuvable');
    exit;
}

$commercant = new Commercant();
$commercants = $commercant->getAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_commercant = $_POST['id_commercant'] ?? 0;
    $montant = $_POST['montant'] ?? 0;
    $duree = $_POST['duree'] ?? 1;
    
    if (!$id_commercant || !$montant) {
        $error = 'Veuillez sélectionner un commerçant et saisir un montant.';
    } else {
        try {
            $location = new Location();
            $location->create($etalage_id, $id_commercant, $montant, $duree);
            
            // Mettre à jour le statut de l'étalage
            $stmt = $db->prepare("UPDATE etalage SET statut = 'occupe' WHERE id_etalage = ?");
            $stmt->execute([$etalage_id]);
            
            $success = 'Étalage attribué avec succès !';
        } catch (Exception $e) {
            $error = 'Erreur: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attribuer un étalage</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .bg-primary { background: #1e3a5f; }
        .bg-accent { background: #f59e0b; }
        .text-primary { color: #1e3a5f; }
        .text-accent { color: #f59e0b; }
        .btn-accent { background: #f59e0b; color: white; transition: all 0.3s ease; }
        .btn-accent:hover { background: #d97706; transform: scale(1.02); }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-2xl mx-auto px-4 py-8">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-primary">
                    <i class="fas fa-handshake text-accent mr-2"></i>Attribuer l'étalage #<?= htmlspecialchars($etalage['numero']) ?>
                </h1>
                <a href="dashboard.php" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-arrow-left mr-1"></i> Retour
                </a>
            </div>
            
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                    <?= htmlspecialchars($success) ?>
                    <a href="dashboard.php" class="ml-4 text-green-700 font-semibold hover:text-green-900">Retour au tableau de bord</a>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Commerçant <span class="text-red-500">*</span>
                    </label>
                    <select name="id_commercant" required 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                        <option value="">Sélectionner un commerçant</option>
                        <?php foreach ($commercants as $c): ?>
                            <option value="<?= $c['id_commercant'] ?>"><?= htmlspecialchars($c['nom_complet']) ?> (<?= htmlspecialchars($c['matricule']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Montant de la location (FCFA) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="montant" required min="1000" step="1000"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                           placeholder="Ex: 25000">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Durée (mois)
                    </label>
                    <input type="number" name="duree" value="1" min="1"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                </div>
                
                <button type="submit" class="w-full btn-accent py-2 rounded-lg font-semibold">
                    <i class="fas fa-save mr-2"></i> Attribuer
                </button>
            </form>
        </div>
    </div>
</body>
</html>