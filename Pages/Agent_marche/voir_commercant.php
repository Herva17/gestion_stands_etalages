<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';
require_once __DIR__ . '/../../Classes/Database.php';

$agent = new AgentMarche();
if (!$agent->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$commercant_id = $_GET['id'] ?? 0;
if (!$commercant_id) {
    header('Location: dashboard.php?error=id_commercant_manquant');
    exit;
}

$db = Database::getInstance()->getConnection();

// Récupérer le commerçant avec ses locations
$stmt = $db->prepare("
    SELECT u.*, c.*, 
           (SELECT COUNT(*) FROM location WHERE id_commercant = c.id_commercant) as nb_locations,
           (SELECT COUNT(*) FROM location WHERE id_commercant = c.id_commercant AND status = 'actif') as nb_actives
    FROM commercant c
    INNER JOIN utilisateurs u ON c.id_user = u.id_user
    WHERE c.id_commercant = ?
");
$stmt->execute([$commercant_id]);
$commercant = $stmt->fetch();

if (!$commercant) {
    header('Location: dashboard.php?error=commercant_introuvable');
    exit;
}

// Récupérer les locations du commerçant
$stmt = $db->prepare("
    SELECT l.*, e.numero as etalage_numero, e.localisation
    FROM location l
    INNER JOIN etalage e ON l.id_etalage = e.id_etalage
    WHERE l.id_commercant = ?
    ORDER BY l.created_at DESC
");
$stmt->execute([$commercant_id]);
$locations = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du commerçant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .bg-primary { background: #1e3a5f; }
        .bg-accent { background: #f59e0b; }
        .text-primary { color: #1e3a5f; }
        .text-accent { color: #f59e0b; }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-actif { background: #dbeafe; color: #1e40af; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-6xl mx-auto px-4 py-8">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-primary">
                    <i class="fas fa-user text-accent mr-2"></i>Détails du commerçant
                </h1>
                <a href="dashboard.php" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-arrow-left mr-1"></i> Retour
                </a>
            </div>
            
            <div class="grid md:grid-cols-3 gap-6 mb-6">
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-500">Matricule</p>
                    <p class="font-bold text-gray-800"><?= htmlspecialchars($commercant['matricule']) ?></p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-500">Nom complet</p>
                    <p class="font-bold text-gray-800"><?= htmlspecialchars($commercant['nom_complet']) ?></p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-500">Statut</p>
                    <p class="font-bold text-green-600">
                        <i class="fas fa-check-circle mr-1"></i> Actif
                    </p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-500">Téléphone</p>
                    <p class="font-bold text-gray-800"><?= htmlspecialchars($commercant['telephone'] ?? 'Non renseigné') ?></p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-500">Email</p>
                    <p class="font-bold text-gray-800"><?= htmlspecialchars($commercant['email'] ?? 'Non renseigné') ?></p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm text-gray-500">Locations</p>
                    <p class="font-bold text-gray-800">
                        <?= $commercant['nb_locations'] ?? 0 ?> au total
                        <span class="text-xs text-blue-600">(<?= $commercant['nb_actives'] ?? 0 ?> actives)</span>
                    </p>
                </div>
            </div>
            
            <div class="mt-6">
                <h2 class="text-lg font-bold text-primary mb-4">
                    <i class="fas fa-history text-accent mr-2"></i>Historique des locations
                </h2>
                
                <?php if (count($locations) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Étalage</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Début</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fin</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($locations as $loc): ?>
                                    <tr>
                                        <td class="px-4 py-3 text-sm">#<?= htmlspecialchars($loc['etalage_numero']) ?></td>
                                        <td class="px-4 py-3 text-sm font-bold text-accent"><?= number_format($loc['montant_location'], 0, ',', ' ') ?> FCFA</td>
                                        <td class="px-4 py-3 text-sm"><?= date('d/m/Y', strtotime($loc['date_debut'])) ?></td>
                                        <td class="px-4 py-3 text-sm"><?= date('d/m/Y', strtotime($loc['date_fin'])) ?></td>
                                        <td class="px-4 py-3">
                                            <?php if (strtotime($loc['date_fin']) >= time()): ?>
                                                <span class="status-badge status-actif">Active</span>
                                            <?php else: ?>
                                                <span class="status-badge bg-gray-100 text-gray-600">Expirée</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-4">Aucune location trouvée.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>