<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';
require_once __DIR__ . '/../../Classes/Database.php';

$agent = new AgentMarche();
if (!$agent->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$paiement_id = $_GET['id'] ?? 0;
if (!$paiement_id) {
    header('Location: dashboard.php?error=id_paiement_manquant');
    exit;
}

$db = Database::getInstance()->getConnection();

// Récupérer le paiement
$stmt = $db->prepare("SELECT * FROM paiement WHERE id_paiement = ?");
$stmt->execute([$paiement_id]);
$paiement = $stmt->fetch();

if (!$paiement) {
    header('Location: dashboard.php?error=paiement_introuvable');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $montant = $_POST['montant'] ?? 0;
    $mode_paiement = $_POST['mode_paiement'] ?? 'Espèces';
    $periode = $_POST['periode'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (!$montant) {
        $error = 'Veuillez saisir un montant valide.';
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE paiement 
                SET montant = ?, mode_paiement = ?, periode = ?, description = ?
                WHERE id_paiement = ?
            ");
            $stmt->execute([$montant, $mode_paiement, $periode, $description, $paiement_id]);
            $success = 'Paiement modifié avec succès !';
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
    <title>Modifier le paiement</title>
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
                    <i class="fas fa-edit text-accent mr-2"></i>Modifier le paiement #<?= $paiement['id_paiement'] ?>
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
            <?php else: ?>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Montant (FCFA) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="montant" required min="100" step="100"
                               value="<?= $paiement['montant'] ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Mode de paiement
                        </label>
                        <select name="mode_paiement" 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                            <option value="Espèces" <?= ($paiement['mode_paiement'] == 'Espèces') ? 'selected' : '' ?>>Espèces</option>
                            <option value="Mobile Money" <?= ($paiement['mode_paiement'] == 'Mobile Money') ? 'selected' : '' ?>>Mobile Money</option>
                            <option value="Virement" <?= ($paiement['mode_paiement'] == 'Virement') ? 'selected' : '' ?>>Virement</option>
                            <option value="Carte bancaire" <?= ($paiement['mode_paiement'] == 'Carte bancaire') ? 'selected' : '' ?>>Carte bancaire</option>
                            <option value="Chèque" <?= ($paiement['mode_paiement'] == 'Chèque') ? 'selected' : '' ?>>Chèque</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Période
                        </label>
                        <input type="month" name="periode" value="<?= htmlspecialchars($paiement['periode'] ?? date('Y-m')) ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Description
                        </label>
                        <textarea name="description" rows="2"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"><?= htmlspecialchars($paiement['description'] ?? '') ?></textarea>
                    </div>
                    
                    <button type="submit" class="w-full btn-accent py-2 rounded-lg font-semibold">
                        <i class="fas fa-save mr-2"></i> Modifier
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>