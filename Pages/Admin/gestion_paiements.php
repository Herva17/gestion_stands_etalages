<?php
session_start();
require_once __DIR__ . '/../../Classes/Administrateur.php';
require_once __DIR__ . '/../../Classes/Database.php';

$admin = new Administrateur();
if (!$admin->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$user = $admin->getLoggedInUser();
if (!$user) {
    session_destroy();
    header('Location: ../../login.php?error=session_expired');
    exit;
}

$db = Database::getInstance()->getConnection();

// Filtres
$mois = $_GET['mois'] ?? date('Y-m');
$statut = $_GET['statut'] ?? '';

// Récupérer tous les paiements avec filtres
$sql = "
    SELECT p.*, 
           u.nom_complet as commercant_nom,
           u.matricule as commercant_matricule,
           e.numero as etalage_numero,
           e.localisation as etalage_localisation,
           l.id_location
    FROM paiement p
    INNER JOIN location l ON p.id_location = l.id_location
    INNER JOIN commercant c ON l.id_commercant = c.id_commercant
    INNER JOIN utilisateurs u ON c.id_user = u.id_user
    INNER JOIN etalage e ON l.id_etalage = e.id_etalage
    WHERE 1=1
";

$params = [];
if ($mois) {
    $sql .= " AND DATE_FORMAT(p.date_paiement, '%Y-%m') = ?";
    $params[] = $mois;
}
if ($statut) {
    $sql .= " AND p.statut = ?";
    $params[] = $statut;
}
$sql .= " ORDER BY p.date_paiement DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$paiements = $stmt->fetchAll();

// Statistiques
$stmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) as valides,
        SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
        SUM(CASE WHEN statut = 'annule' THEN 1 ELSE 0 END) as annules,
        SUM(CASE WHEN statut = 'valide' THEN montant ELSE 0 END) as total_montant
    FROM paiement
");
$stats = $stmt->fetch();

$page_title = 'Gestion des paiements - Admin';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
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
        .btn-primary { background: #1e3a5f; color: white; transition: all 0.3s ease; }
        .btn-primary:hover { background: #2d6a9f; transform: scale(1.02); }
        .btn-danger { background: #ef4444; color: white; transition: all 0.3s ease; }
        .btn-danger:hover { background: #dc2626; transform: scale(1.02); }
        .btn-info { background: #3b82f6; color: white; transition: all 0.3s ease; }
        .btn-info:hover { background: #2563eb; transform: scale(1.02); }
        .btn-success { background: #22c55e; color: white; transition: all 0.3s ease; }
        .btn-success:hover { background: #16a34a; transform: scale(1.02); }
        
        .action-btn {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            transition: all 0.2s;
        }
        .action-btn:hover {
            transform: scale(1.05);
        }
        .table-row-hover:hover {
            background-color: #f8fafc;
        }
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-3px); }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-valide { background: #dcfce7; color: #166534; }
        .status-annule { background: #fee2e2; color: #991b1b; }
        .status-en_attente { background: #fef3c7; color: #92400e; }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 10px; }
    </style>
</head>
<body class="bg-gray-50">

<nav class="bg-primary text-white shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center space-x-3">
                <i class="fas fa-store text-accent text-2xl"></i>
                <span class="font-bold text-lg">Marché Virunga</span>
                <span class="text-xs bg-accent/20 px-2 py-1 rounded-full">Admin</span>
            </div>
            <div class="flex items-center space-x-4">
                <a href="dashboard.php" class="text-sm hover:text-accent transition">
                    <i class="fas fa-arrow-left mr-1"></i> Retour
                </a>
                <a href="../../login.php" class="text-sm bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition flex items-center">
                    <i class="fas fa-sign-out-alt mr-1"></i>
                    <span class="hidden sm:inline">Déconnexion</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-primary">
                <i class="fas fa-coins text-accent mr-2"></i>Gestion des paiements
            </h1>
            <a href="rapport_paiements.php" class="btn-primary px-4 py-2 rounded-lg font-semibold">
                <i class="fas fa-file-pdf mr-2"></i> Rapport complet
            </a>
        </div>
        
        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card bg-blue-50 rounded-lg p-4">
                <p class="text-sm text-gray-500">Total</p>
                <p class="text-2xl font-bold text-blue-600"><?= $stats['total'] ?? 0 ?></p>
            </div>
            <div class="stat-card bg-green-50 rounded-lg p-4">
                <p class="text-sm text-gray-500">Validés</p>
                <p class="text-2xl font-bold text-green-600"><?= $stats['valides'] ?? 0 ?></p>
            </div>
            <div class="stat-card bg-yellow-50 rounded-lg p-4">
                <p class="text-sm text-gray-500">En attente</p>
                <p class="text-2xl font-bold text-yellow-600"><?= $stats['en_attente'] ?? 0 ?></p>
            </div>
            <div class="stat-card bg-accent/20 rounded-lg p-4">
                <p class="text-sm text-gray-500">Total encaissé</p>
                <p class="text-2xl font-bold text-accent"><?= number_format($stats['total_montant'] ?? 0, 0, ',', ' ') ?> FCFA</p>
            </div>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                    <select name="statut" class="px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                        <option value="">Tous</option>
                        <option value="valide" <?= $statut == 'valide' ? 'selected' : '' ?>>Validé</option>
                        <option value="en_attente" <?= $statut == 'en_attente' ? 'selected' : '' ?>>En attente</option>
                        <option value="annule" <?= $statut == 'annule' ? 'selected' : '' ?>>Annulé</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg font-semibold">
                        <i class="fas fa-filter mr-2"></i> Filtrer
                    </button>
                </div>
                <div>
                    <a href="gestion_paiements.php" class="btn-outline px-4 py-2 rounded-lg font-semibold">
                        <i class="fas fa-redo mr-2"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
        
        <?php if (count($paiements) > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Réf</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commerçant</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Étalage</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mode</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($paiements as $p): ?>
                            <tr class="table-row-hover">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($p['reference'] ?? 'N/A') ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?= date('d/m/Y', strtotime($p['date_paiement'])) ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($p['commercant_nom']) ?></p>
                                        <p class="text-xs text-gray-400"><?= htmlspecialchars($p['commercant_matricule']) ?></p>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    #<?= htmlspecialchars($p['etalage_numero']) ?>
                                </td>
                                <td class="px-4 py-3 text-sm font-bold text-accent">
                                    <?= number_format($p['montant'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?= htmlspecialchars($p['mode_paiement'] ?? 'Espèces') ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php
                                    $statut_p = $p['statut'] ?? 'en_attente';
                                    $status_class = match($statut_p) {
                                        'valide' => 'status-valide',
                                        'annule' => 'status-annule',
                                        default => 'status-en_attente'
                                    };
                                    ?>
                                    <span class="status-badge <?= $status_class ?>">
                                        <?= ucfirst($statut_p) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-1">
                                        <a href="voir_paiement.php?id=<?= $p['id_paiement'] ?>" 
                                           class="action-btn btn-info text-white" title="Voir">
                                            <i class="fas fa-eye text-xs"></i>
                                        </a>
                                        <a href="imprimer_recu.php?id=<?= $p['id_paiement'] ?>" 
                                           class="action-btn btn-primary text-white" title="Imprimer" target="_blank">
                                            <i class="fas fa-print text-xs"></i>
                                        </a>
                                        <?php if ($p['statut'] !== 'annule' && $p['statut'] !== 'valide'): ?>
                                            <a href="valider_paiement.php?id=<?= $p['id_paiement'] ?>" 
                                               class="action-btn btn-success text-white" title="Valider"
                                               onclick="return confirm('Valider ce paiement ?')">
                                                <i class="fas fa-check text-xs"></i>
                                            </a>
                                            <a href="annuler_paiement.php?id=<?= $p['id_paiement'] ?>" 
                                               class="action-btn btn-danger text-white" title="Annuler"
                                               onclick="return confirm('Annuler ce paiement ?')">
                                                <i class="fas fa-times text-xs"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-12">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-coins"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun paiement trouvé</h3>
                <p class="text-gray-500">Aucun paiement ne correspond aux critères de recherche.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>