<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';
require_once __DIR__ . '/../../Classes/Database.php';

$agent = new AgentMarche();
if (!$agent->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$location_id = $_GET['id'] ?? 0;
if (!$location_id) {
    header('Location: dashboard.php?error=id_location_manquant');
    exit;
}

$db = Database::getInstance()->getConnection();

// Récupérer la location avec tous les détails
$stmt = $db->prepare("
    SELECT l.*, e.numero as etalage_numero, e.localisation, s.designation as secteur_nom,
           u.nom_complet as commercant_nom, u.matricule as commercant_matricule,
           u.telephone as commercant_telephone, u.email as commercant_email
    FROM location l
    INNER JOIN etalage e ON l.id_etalage = e.id_etalage
    LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
    INNER JOIN commercant c ON l.id_commercant = c.id_commercant
    INNER JOIN utilisateurs u ON c.id_user = u.id_user
    WHERE l.id_location = ?
");
$stmt->execute([$location_id]);
$location = $stmt->fetch();

if (!$location) {
    header('Location: dashboard.php?error=location_introuvable');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails de la location</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .bg-primary { background: #1e3a5f; }
        .bg-accent { background: #f59e0b; }
        .text-primary { color: #1e3a5f; }
        .text-accent { color: #f59e0b; }
        .border-accent { border-color: #f59e0b; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-primary">
                    <i class="fas fa-eye text-accent mr-2"></i>Détails de la location #<?= $location['id_location'] ?>
                </h1>
                <a href="dashboard.php" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-arrow-left mr-1"></i> Retour
                </a>
            </div>
            
            <div class="grid md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div class="border-b pb-3">
                        <p class="text-sm text-gray-500">Étalage</p>
                        <p class="font-semibold text-gray-800">#<?= htmlspecialchars($location['etalage_numero']) ?></p>
                        <p class="text-sm text-gray-600"><?= htmlspecialchars($location['localisation'] ?? '') ?></p>
                        <p class="text-sm text-gray-600">Secteur: <?= htmlspecialchars($location['secteur_nom'] ?? 'Non défini') ?></p>
                    </div>
                    
                    <div class="border-b pb-3">
                        <p class="text-sm text-gray-500">Commerçant</p>
                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($location['commercant_nom']) ?></p>
                        <p class="text-sm text-gray-600">Matricule: <?= htmlspecialchars($location['commercant_matricule']) ?></p>
                        <p class="text-sm text-gray-600">Téléphone: <?= htmlspecialchars($location['commercant_telephone'] ?? 'Non renseigné') ?></p>
                        <p class="text-sm text-gray-600">Email: <?= htmlspecialchars($location['commercant_email'] ?? 'Non renseigné') ?></p>
                    </div>
                </div>
                
                <div class="space-y-4">
                    <div class="border-b pb-3">
                        <p class="text-sm text-gray-500">Montant</p>
                        <p class="font-bold text-accent text-xl"><?= number_format($location['montant_location'], 0, ',', ' ') ?> FCFA</p>
                    </div>
                    
                    <div class="border-b pb-3">
                        <p class="text-sm text-gray-500">Période</p>
                        <p class="text-gray-800">Début: <?= date('d/m/Y', strtotime($location['date_debut'])) ?></p>
                        <p class="text-gray-800">Fin: <?= date('d/m/Y', strtotime($location['date_fin'])) ?></p>
                        <p class="text-sm text-gray-600">Durée: <?= htmlspecialchars($location['duree_location']) ?> mois</p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Statut</p>
                        <?php if (strtotime($location['date_fin']) >= time()): ?>
                            <span class="status-badge status-actif inline-block mt-1">
                                <i class="fas fa-check-circle mr-1"></i> Active
                            </span>
                        <?php else: ?>
                            <span class="status-badge status-disponible inline-block mt-1">
                                <i class="fas fa-clock mr-1"></i> Expirée
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 pt-6 border-t border-gray-200 flex gap-3">
                <a href="modifier_location.php?id=<?= $location['id_location'] ?>" 
                   class="btn-primary px-4 py-2 rounded-lg font-semibold">
                    <i class="fas fa-edit mr-2"></i> Modifier
                </a>
                <a href="imprimer_location.php?id=<?= $location['id_location'] ?>" 
                   class="btn-accent px-4 py-2 rounded-lg font-semibold" target="_blank">
                    <i class="fas fa-print mr-2"></i> Imprimer
                </a>
            </div>
        </div>
    </div>
</body>
</html>