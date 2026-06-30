<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';
require_once __DIR__ . '/../../Classes/Paiement.php';
require_once __DIR__ . '/../../Classes/Database.php';

$agent = new AgentMarche();
if (!$agent->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$db = Database::getInstance()->getConnection();

$location_id = $_GET['location_id'] ?? 0;
$error = '';
$success = '';

// Récupérer les locations actives sans paiement
if ($location_id > 0) {
    // Récupérer la location spécifique
    $stmt = $db->prepare("
        SELECT l.*, e.numero as etalage_numero, u.nom_complet as commercant_nom
        FROM location l
        INNER JOIN etalage e ON l.id_etalage = e.id_etalage
        INNER JOIN commercant c ON l.id_commercant = c.id_commercant
        INNER JOIN utilisateurs u ON c.id_user = u.id_user
        WHERE l.id_location = ?
        AND l.status = 'actif'
    ");
    $stmt->execute([$location_id]);
    $location = $stmt->fetch();
    
    if (!$location) {
        header('Location: dashboard.php?error=location_introuvable');
        exit;
    }
} else {
    // Récupérer toutes les locations actives sans paiement
    $stmt = $db->prepare("
        SELECT l.*, e.numero as etalage_numero, u.nom_complet as commercant_nom
        FROM location l
        INNER JOIN etalage e ON l.id_etalage = e.id_etalage
        INNER JOIN commercant c ON l.id_commercant = c.id_commercant
        INNER JOIN utilisateurs u ON c.id_user = u.id_user
        LEFT JOIN paiement p ON l.id_location = p.id_location
        WHERE l.status = 'actif'
        AND (p.id_paiement IS NULL OR p.statut != 'valide')
        ORDER BY l.created_at DESC
    ");
    $stmt->execute();
    $locations_sans_paiement = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_location = $_POST['id_location'] ?? 0;
    $montant = $_POST['montant'] ?? 0;
    $mode_paiement = $_POST['mode_paiement'] ?? 'Espèces';
    $commentaire = $_POST['commentaire'] ?? '';
    $date_paiement = $_POST['date_paiement'] ?? date('Y-m-d H:i:s');
    
    if (!$id_location || !$montant) {
        $error = 'Veuillez sélectionner une location et saisir un montant.';
    } else {
        try {
            $paiement_data = [
                'id_location' => $id_location,
                'montant' => $montant,
                'mode_paiement' => $mode_paiement,
                'date_paiement' => $date_paiement,
                'commentaire' => $commentaire,
                'statut' => 'valide'
            ];
            
            $paiement = new Paiement();
            $result = $paiement->create($paiement_data);
            
            if ($result['success']) {
                // Mettre à jour le statut de la location
                $stmt = $db->prepare("UPDATE location SET status = 'actif' WHERE id_location = ?");
                $stmt->execute([$id_location]);
                
                // Mettre à jour le statut de l'étalage
                $stmt = $db->prepare("
                    UPDATE etalage SET statut = 'occupe' 
                    WHERE id_etalage = (SELECT id_etalage FROM location WHERE id_location = ?)
                ");
                $stmt->execute([$id_location]);
                
                $_SESSION['success'] = 'Paiement enregistré avec succès ! Référence: ' . $result['reference'];
                header('Location: dashboard.php?success=paiement_enregistre');
                exit;
            } else {
                $error = $result['error'];
            }
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
    <title>Enregistrer un paiement</title>
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
                    <i class="fas fa-coins text-accent mr-2"></i>Enregistrer un paiement
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
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Location <span class="text-red-500">*</span>
                    </label>
                    <?php if ($location_id > 0 && isset($location)): ?>
                        <input type="hidden" name="id_location" value="<?= $location['id_location'] ?>">
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <p class="font-semibold">Étalage #<?= htmlspecialchars($location['etalage_numero']) ?></p>
                            <p class="text-sm text-gray-600">Commerçant: <?= htmlspecialchars($location['commercant_nom']) ?></p>
                            <p class="text-sm text-gray-600">Montant à payer: <?= number_format($location['montant_location'], 0, ',', ' ') ?> FCFA</p>
                        </div>
                    <?php else: ?>
                        <select name="id_location" required 
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                            <option value="">Sélectionner une location</option>
                            <?php if (isset($locations_sans_paiement) && count($locations_sans_paiement) > 0): ?>
                                <?php foreach ($locations_sans_paiement as $loc): ?>
                                    <option value="<?= $loc['id_location'] ?>">
                                        Étalage #<?= htmlspecialchars($loc['etalage_numero']) ?> - 
                                        <?= htmlspecialchars($loc['commercant_nom']) ?> - 
                                        <?= number_format($loc['montant_location'], 0, ',', ' ') ?> FCFA
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (!isset($locations_sans_paiement) || count($locations_sans_paiement) == 0): ?>
                            <p class="text-sm text-yellow-600 mt-1">
                                <i class="fas fa-info-circle mr-1"></i> Aucune location sans paiement disponible.
                                <a href="dashboard.php" class="text-accent hover:underline">Retour au tableau de bord</a>
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Montant (FCFA) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="montant" required min="100" step="100"
                           value="<?= isset($location) ? $location['montant_location'] : '' ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                           placeholder="Ex: 25000">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Mode de paiement
                    </label>
                    <select name="mode_paiement" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                        <option value="Espèces">Espèces</option>
                        <option value="Mobile Money">Mobile Money</option>
                        <option value="Virement">Virement</option>
                        <option value="Carte bancaire">Carte bancaire</option>
                        <option value="Chèque">Chèque</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Date du paiement
                    </label>
                    <input type="datetime-local" name="date_paiement" 
                           value="<?= date('Y-m-d\TH:i') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Commentaire
                    </label>
                    <textarea name="commentaire" rows="2"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                              placeholder="Commentaire sur le paiement..."></textarea>
                </div>
                
                <button type="submit" class="w-full btn-accent py-2 rounded-lg font-semibold">
                    <i class="fas fa-save mr-2"></i> Enregistrer le paiement
                </button>
            </form>
        </div>
    </div>
</body>
</html>