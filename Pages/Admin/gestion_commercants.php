<?php
session_start();
require_once __DIR__ . '/../../Classes/Administrateur.php';
require_once __DIR__ . '/../../Classes/Commercant.php';
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

// Récupérer tous les commerçants avec leurs statistiques
$stmt = $db->query("
    SELECT u.*, c.id_commercant, c.produits_vendu,
           COUNT(DISTINCT l.id_location) as nb_locations,
           COUNT(DISTINCT e.id_etalage) as nb_etalages,
           COUNT(DISTINCT p.id_produit) as nb_produits,
           (SELECT SUM(montant) FROM paiement 
            WHERE id_location IN (SELECT id_location FROM location WHERE id_commercant = c.id_commercant)
            AND statut = 'valide') as total_paye
    FROM utilisateurs u
    INNER JOIN commercant c ON u.id_user = c.id_user
    LEFT JOIN location l ON c.id_commercant = l.id_commercant AND l.status = 'actif'
    LEFT JOIN etalage e ON c.id_commercant = e.id_commercant
    LEFT JOIN produit p ON c.id_commercant = p.id_commercant
    GROUP BY c.id_commercant
    ORDER BY u.created_at DESC
");
$commercants = $stmt->fetchAll();

$page_title = 'Gestion des commerçants - Admin';
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
        .stat-badge {
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 10px; }
    </style>
</head>
<body class="bg-gray-50">

<!-- Navigation -->
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
                <i class="fas fa-user-tie text-accent mr-2"></i>Gestion des commerçants
            </h1>
            <a href="ajouter_commercant.php" class="btn-accent px-4 py-2 rounded-lg font-semibold">
                <i class="fas fa-plus mr-2"></i> Ajouter un commerçant
            </a>
        </div>
        
        <?php if (count($commercants) > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matricule</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Étalages</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Locations</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total payé</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($commercants as $c): ?>
                            <tr class="table-row-hover">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($c['matricule']) ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($c['nom_complet']) ?></p>
                                        <p class="text-xs text-gray-400"><?= htmlspecialchars($c['nom_user']) ?></p>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <p><?= htmlspecialchars($c['telephone'] ?? '-') ?></p>
                                    <p class="text-xs text-gray-400"><?= htmlspecialchars($c['email'] ?? '-') ?></p>
                                </td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <span class="stat-badge bg-blue-100 text-blue-700">
                                        <?= $c['nb_etalages'] ?? 0 ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-center">
                                    <span class="stat-badge bg-yellow-100 text-yellow-700">
                                        <?= $c['nb_locations'] ?? 0 ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm font-bold text-accent">
                                    <?= number_format($c['total_paye'] ?? 0, 0, ',', ' ') ?> FCFA
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-1">
                                        <a href="voir_commercant.php?id=<?= $c['id_commercant'] ?>" 
                                           class="action-btn btn-info text-white" title="Voir">
                                            <i class="fas fa-eye text-xs"></i>
                                        </a>
                                        <a href="modifier_commercant.php?id=<?= $c['id_commercant'] ?>" 
                                           class="action-btn btn-warning text-white" title="Modifier">
                                            <i class="fas fa-edit text-xs"></i>
                                        </a>
                                        <a href="supprimer_commercant.php?id=<?= $c['id_commercant'] ?>" 
                                           class="action-btn btn-danger text-white" title="Supprimer"
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce commerçant ?')">
                                            <i class="fas fa-trash text-xs"></i>
                                        </a>
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
                    <i class="fas fa-user-tie"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun commerçant</h3>
                <p class="text-gray-500">Commencez par ajouter des commerçants.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>