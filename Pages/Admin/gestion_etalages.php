<?php
session_start();
require_once __DIR__ . '/../../Classes/Administrateur.php';
require_once __DIR__ . '/../../Classes/Etalage.php';
require_once __DIR__ . '/../../Classes/Secteur.php';
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

// Récupérer tous les étalages avec leurs informations
$stmt = $db->query("
    SELECT e.*, 
           s.designation as secteur_nom,
           u.nom_complet as commercant_nom,
           l.id_location, l.status as location_status,
           l.date_debut, l.date_fin
    FROM etalage e
    LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
    LEFT JOIN commercant c ON e.id_commercant = c.id_commercant
    LEFT JOIN utilisateurs u ON c.id_user = u.id_user
    LEFT JOIN location l ON e.id_etalage = l.id_etalage AND l.status = 'actif'
    ORDER BY e.numero
");
$etalages = $stmt->fetchAll();

$secteur = new Secteur();
$secteurs = $secteur->getAll();

$page_title = 'Gestion des étalages - Admin';
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
        .btn-warning { background: #f59e0b; color: white; transition: all 0.3s ease; }
        .btn-warning:hover { background: #d97706; transform: scale(1.02); }
        
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
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-disponible { background: #dcfce7; color: #166534; }
        .status-occupe { background: #fee2e2; color: #991b1b; }
        .status-en_attente { background: #fef3c7; color: #92400e; }
        .status-reserve { background: #dbeafe; color: #1e40af; }
        
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
                <i class="fas fa-warehouse text-accent mr-2"></i>Gestion des étalages
            </h1>
            <div class="flex gap-2">
                <a href="ajouter_etalage.php" class="btn-accent px-4 py-2 rounded-lg font-semibold">
                    <i class="fas fa-plus mr-2"></i> Ajouter
                </a>
                <a href="gestion_secteurs.php" class="btn-primary px-4 py-2 rounded-lg font-semibold">
                    <i class="fas fa-layer-group mr-2"></i> Secteurs
                </a>
            </div>
        </div>
        
        <!-- Filtres -->
        <div class="bg-gray-50 rounded-lg p-4 mb-6">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Secteur</label>
                    <select name="secteur" class="px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                        <option value="">Tous</option>
                        <?php foreach ($secteurs as $s): ?>
                            <option value="<?= $s['id_secteur'] ?>"><?= htmlspecialchars($s['designation']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                    <select name="statut" class="px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                        <option value="">Tous</option>
                        <option value="disponible">Disponible</option>
                        <option value="occupe">Occupé</option>
                        <option value="en_attente">En attente</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn-primary px-4 py-2 rounded-lg font-semibold">
                        <i class="fas fa-filter mr-2"></i> Filtrer
                    </button>
                </div>
                <div>
                    <a href="gestion_etalages.php" class="btn-outline px-4 py-2 rounded-lg font-semibold">
                        <i class="fas fa-redo mr-2"></i> Réinitialiser
                    </a>
                </div>
            </form>
        </div>
        
        <?php if (count($etalages) > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Numéro</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Localisation</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Secteur</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commerçant</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($etalages as $e): ?>
                            <tr class="table-row-hover">
                                <td class="px-4 py-3 text-sm text-gray-500 text-center">
                                    <?= $e['id_etalage'] ?>
                                </td>
                                <td class="px-4 py-3 text-sm font-bold text-gray-900">
                                    <?= htmlspecialchars($e['numero']) ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?= htmlspecialchars($e['localisation'] ?? 'Non spécifiée') ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?= htmlspecialchars($e['secteur_nom'] ?? 'Non défini') ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php
                                    $statut = $e['statut'] ?? 'disponible';
                                    $status_class = match($statut) {
                                        'disponible' => 'status-disponible',
                                        'occupe' => 'status-occupe',
                                        'en_attente' => 'status-en_attente',
                                        default => 'status-disponible'
                                    };
                                    ?>
                                    <span class="status-badge <?= $status_class ?>">
                                        <?= ucfirst($statut) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?= htmlspecialchars($e['commercant_nom'] ?? '-') ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-1">
                                        <a href="modifier_etalage.php?id=<?= $e['id_etalage'] ?>" 
                                           class="action-btn btn-warning text-white" title="Modifier">
                                            <i class="fas fa-edit text-xs"></i>
                                        </a>
                                        <a href="supprimer_etalage.php?id=<?= $e['id_etalage'] ?>" 
                                           class="action-btn btn-danger text-white" title="Supprimer"
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet étalage ?')">
                                            <i class="fas fa-trash text-xs"></i>
                                        </a>
                                        <?php if ($e['id_commercant']): ?>
                                            <a href="liberer_etalage.php?id=<?= $e['id_etalage'] ?>" 
                                               class="action-btn btn-success text-white" title="Libérer"
                                               onclick="return confirm('Libérer cet étalage ?')">
                                                <i class="fas fa-unlock text-xs"></i>
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
                    <i class="fas fa-warehouse"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun étalage</h3>
                <p class="text-gray-500">Commencez par ajouter des étalages.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>