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

// Récupérer toutes les locations
$stmt = $db->query("
    SELECT l.*, 
           e.numero as etalage_numero,
           e.localisation as etalage_localisation,
           s.designation as secteur_nom,
           u.nom_complet as commercant_nom,
           u.matricule as commercant_matricule,
           u.telephone as commercant_telephone,
           p.id_paiement,
           p.montant as montant_paye,
           p.statut as paiement_statut,
           p.reference as paiement_reference
    FROM location l
    INNER JOIN etalage e ON l.id_etalage = e.id_etalage
    LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
    INNER JOIN commercant c ON l.id_commercant = c.id_commercant
    INNER JOIN utilisateurs u ON c.id_user = u.id_user
    LEFT JOIN paiement p ON l.id_location = p.id_location AND p.statut = 'valide'
    ORDER BY l.created_at DESC
");
$locations = $stmt->fetchAll();

// Statistiques
$stmt = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'actif' THEN 1 ELSE 0 END) as actives,
        SUM(CASE WHEN status = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
        SUM(CASE WHEN status = 'approuve' THEN 1 ELSE 0 END) as approuvees,
        SUM(CASE WHEN status = 'refuse' THEN 1 ELSE 0 END) as refusees,
        SUM(CASE WHEN status = 'termine' THEN 1 ELSE 0 END) as terminees
    FROM location
");
$stats = $stmt->fetch();

$page_title = 'Gestion des locations - Admin';
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
        .btn-success { background: #22c55e; color: white; transition: all 0.3s ease; }
        .btn-success:hover { background: #16a34a; transform: scale(1.02); }
        .btn-warning { background: #f59e0b; color: white; transition: all 0.3s ease; }
        .btn-warning:hover { background: #d97706; transform: scale(1.02); }
        .btn-info { background: #3b82f6; color: white; transition: all 0.3s ease; }
        .btn-info:hover { background: #2563eb; transform: scale(1.02); }
        
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
        .status-actif { background: #dbeafe; color: #1e40af; }
        .status-en_attente { background: #fef3c7; color: #92400e; }
        .status-approuve { background: #dcfce7; color: #166534; }
        .status-refuse { background: #fee2e2; color: #991b1b; }
        .status-termine { background: #e5e7eb; color: #4b5563; }
        
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
                <i class="fas fa-handshake text-accent mr-2"></i>Gestion des locations
            </h1>
        </div>
        
        <!-- Statistiques -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="stat-card bg-blue-50 rounded-lg p-4">
                <p class="text-sm text-gray-500">Total</p>
                <p class="text-2xl font-bold text-blue-600"><?= $stats['total'] ?? 0 ?></p>
            </div>
            <div class="stat-card bg-yellow-50 rounded-lg p-4">
                <p class="text-sm text-gray-500">En attente</p>
                <p class="text-2xl font-bold text-yellow-600"><?= $stats['en_attente'] ?? 0 ?></p>
            </div>
            <div class="stat-card bg-green-50 rounded-lg p-4">
                <p class="text-sm text-gray-500">Approuvées</p>
                <p class="text-2xl font-bold text-green-600"><?= $stats['approuvees'] ?? 0 ?></p>
            </div>
            <div class="stat-card bg-red-50 rounded-lg p-4">
                <p class="text-sm text-gray-500">Refusées</p>
                <p class="text-2xl font-bold text-red-600"><?= $stats['refusees'] ?? 0 ?></p>
            </div>
            <div class="stat-card bg-gray-50 rounded-lg p-4">
                <p class="text-sm text-gray-500">Terminées</p>
                <p class="text-2xl font-bold text-gray-600"><?= $stats['terminees'] ?? 0 ?></p>
            </div>
        </div>
        
        <?php if (count($locations) > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Étalage</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commerçant</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Période</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paiement</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($locations as $l): ?>
                            <tr class="table-row-hover">
                                <td class="px-4 py-3 text-sm text-gray-500 text-center">
                                    <?= $l['id_location'] ?>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    #<?= htmlspecialchars($l['etalage_numero']) ?>
                                    <p class="text-xs text-gray-400"><?= htmlspecialchars($l['etalage_localisation'] ?? '') ?></p>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($l['commercant_nom']) ?></p>
                                        <p class="text-xs text-gray-400"><?= htmlspecialchars($l['commercant_matricule']) ?></p>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm font-bold text-accent">
                                    <?= number_format($l['montant_location'], 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <p><?= date('d/m/Y', strtotime($l['date_debut'])) ?></p>
                                    <p class="text-xs text-gray-400">au <?= date('d/m/Y', strtotime($l['date_fin'])) ?></p>
                                </td>
                                <td class="px-4 py-3">
                                    <?php
                                    $status = $l['status'] ?? 'en_attente';
                                    $status_class = match($status) {
                                        'actif' => 'status-actif',
                                        'en_attente' => 'status-en_attente',
                                        'approuve' => 'status-approuve',
                                        'refuse' => 'status-refuse',
                                        'termine' => 'status-termine',
                                        default => 'status-en_attente'
                                    };
                                    ?>
                                    <span class="status-badge <?= $status_class ?>">
                                        <?= ucfirst(str_replace('_', ' ', $status)) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($l['id_paiement']): ?>
                                        <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-xs">
                                            <i class="fas fa-check-circle mr-1"></i> Payé
                                        </span>
                                        <p class="text-xs text-gray-400 mt-1">
                                            <?= number_format($l['montant_paye'], 0, ',', ' ') ?> FCFA
                                        </p>
                                    <?php else: ?>
                                        <span class="bg-red-100 text-red-700 px-2 py-0.5 rounded-full text-xs">
                                            <i class="fas fa-times-circle mr-1"></i> Non payé
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-1">
                                        <a href="details_location.php?id=<?= $l['id_location'] ?>" 
                                           class="action-btn btn-info text-white" title="Voir">
                                            <i class="fas fa-eye text-xs"></i>
                                        </a>
                                        <?php if ($l['status'] == 'en_attente'): ?>
                                            <a href="valider_location.php?id=<?= $l['id_location'] ?>" 
                                               class="action-btn btn-success text-white" title="Valider"
                                               onclick="return confirm('Valider cette demande de location ?')">
                                                <i class="fas fa-check text-xs"></i>
                                            </a>
                                            <a href="refuser_location.php?id=<?= $l['id_location'] ?>" 
                                               class="action-btn btn-danger text-white" title="Refuser"
                                               onclick="return confirm('Refuser cette demande de location ?')">
                                                <i class="fas fa-times text-xs"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($l['status'] == 'actif'): ?>
                                            <a href="terminer_location.php?id=<?= $l['id_location'] ?>" 
                                               class="action-btn btn-warning text-white" title="Terminer"
                                               onclick="return confirm('Terminer cette location ?')">
                                                <i class="fas fa-stop text-xs"></i>
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
                    <i class="fas fa-handshake"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucune location</h3>
                <p class="text-gray-500">Aucune location n'a encore été enregistrée.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>