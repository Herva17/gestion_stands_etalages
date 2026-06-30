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

// Filtres
$mois = $_GET['mois'] ?? date('Y-m');
$commercant_id = $_GET['commercant_id'] ?? '';

// Récupérer les paiements avec filtres - CORRIGÉ
$sql = "
    SELECT p.*, 
           u.nom_complet as commercant_nom, 
           u.matricule as commercant_matricule,
           l.id_commercant
    FROM paiement p
    INNER JOIN location l ON p.id_location = l.id_location
    INNER JOIN commercant c ON l.id_commercant = c.id_commercant
    INNER JOIN utilisateurs u ON c.id_user = u.id_user
    WHERE 1=1
";

$params = [];
if ($mois) {
    $sql .= " AND DATE_FORMAT(p.date_paiement, '%Y-%m') = ?";
    $params[] = $mois;
}
if ($commercant_id) {
    $sql .= " AND l.id_commercant = ?";
    $params[] = $commercant_id;
}
$sql .= " ORDER BY p.date_paiement DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$paiements = $stmt->fetchAll();

// Calculer les totaux
$total_general = 0;
foreach ($paiements as $p) {
    $total_general += $p['montant'];
}

// Récupérer la liste des commerçants pour le filtre
$stmt = $db->prepare("
    SELECT c.id_commercant, u.nom_complet, u.matricule
    FROM commercant c
    INNER JOIN utilisateurs u ON c.id_user = u.id_user
    ORDER BY u.nom_complet
");
$stmt->execute();
$commercants = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport des paiements</title>
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
        .btn-accent { background: #f59e0b; color: white; transition: all 0.3s ease; }
        .btn-accent:hover { background: #d97706; transform: scale(1.02); }
        .btn-primary { background: #1e3a5f; color: white; transition: all 0.3s ease; }
        .btn-primary:hover { background: #2d6a9f; transform: scale(1.02); }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-primary">
                    <i class="fas fa-file-pdf text-accent mr-2"></i>Rapport des paiements
                </h1>
                <a href="dashboard.php" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-arrow-left mr-1"></i> Retour
                </a>
            </div>
            
            <!-- Filtres -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mois</label>
                        <input type="month" name="mois" value="<?= htmlspecialchars($mois) ?>"
                               class="px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Commerçant</label>
                        <select name="commercant_id" 
                                class="px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                            <option value="">Tous les commerçants</option>
                            <?php foreach ($commercants as $c): ?>
                                <option value="<?= $c['id_commercant'] ?>" <?= ($commercant_id == $c['id_commercant']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nom_complet']) ?> (<?= htmlspecialchars($c['matricule']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn-accent px-4 py-2 rounded-lg font-semibold">
                            <i class="fas fa-search mr-2"></i> Filtrer
                        </button>
                    </div>
                    <div>
                        <a href="rapport_paiements.php" class="btn-primary px-4 py-2 rounded-lg font-semibold">
                            <i class="fas fa-redo mr-2"></i> Réinitialiser
                        </a>
                    </div>
                    <div class="ml-auto">
                        <button onclick="window.print()" class="btn-primary px-4 py-2 rounded-lg font-semibold">
                            <i class="fas fa-print mr-2"></i> Imprimer
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Résumé -->
            <div class="grid md:grid-cols-3 gap-4 mb-6">
                <div class="bg-green-50 rounded-lg p-4">
                    <p class="text-sm text-gray-500">Nombre de paiements</p>
                    <p class="text-2xl font-bold text-green-600"><?= count($paiements) ?></p>
                </div>
                <div class="bg-blue-50 rounded-lg p-4">
                    <p class="text-sm text-gray-500">Total encaissé</p>
                    <p class="text-2xl font-bold text-blue-600"><?= number_format($total_general, 0, ',', ' ') ?> FCFA</p>
                </div>
                <div class="bg-accent/20 rounded-lg p-4">
                    <p class="text-sm text-gray-500">Moyenne par paiement</p>
                    <p class="text-2xl font-bold text-accent">
                        <?= count($paiements) > 0 ? number_format($total_general / count($paiements), 0, ',', ' ') : '0' ?> FCFA
                    </p>
                </div>
            </div>
            
            <!-- Tableau des paiements -->
            <?php if (count($paiements) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commerçant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matricule</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mode</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Période</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Référence</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($paiements as $p): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 text-sm text-gray-600"><?= date('d/m/Y', strtotime($p['date_paiement'])) ?></td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-800"><?= htmlspecialchars($p['commercant_nom']) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($p['commercant_matricule']) ?></td>
                                    <td class="px-4 py-3 text-sm font-bold text-accent"><?= number_format($p['montant'], 0, ',', ' ') ?> FCFA</td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($p['mode_paiement'] ?? 'Espèces') ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($p['periode'] ?? date('F Y', strtotime($p['date_paiement']))) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($p['reference'] ?? 'N/A') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-gray-50 font-bold">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-right text-sm uppercase">Total</td>
                                <td class="px-4 py-3 text-sm text-accent"><?= number_format($total_general, 0, ',', ' ') ?> FCFA</td>
                                <td colspan="3" class="px-4 py-3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-12">
                    <div class="text-6xl text-gray-300 mb-4">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun paiement trouvé</h3>
                    <p class="text-gray-500">Aucun paiement ne correspond aux critères de recherche.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>