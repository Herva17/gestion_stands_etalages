<?php
session_start();
require_once __DIR__ . '/../../Classes/Administrateur.php';
require_once __DIR__ . '/../../Classes/Database.php';

$page_title = 'Dashboard Administrateur - Marché Virunga';

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
$stats = $admin->getStats();

$admin_nom = $user->getNomComplet();
$admin_matricule = $user->getMatricule();

// Récupérer les dernières activités
$stmt = $db->prepare("
    (SELECT 'paiement' as type, p.date_paiement as date, 
            CONCAT('Paiement de ', FORMAT(p.montant, 0), ' FCFA par ', u.nom_complet) as description
     FROM paiement p
     INNER JOIN location l ON p.id_location = l.id_location
     INNER JOIN commercant c ON l.id_commercant = c.id_commercant
     INNER JOIN utilisateurs u ON c.id_user = u.id_user
     WHERE p.statut = 'valide'
     ORDER BY p.date_paiement DESC LIMIT 5)
    UNION
    (SELECT 'location' as type, l.created_at as date,
            CONCAT('Nouvelle location pour l\'étalage #', e.numero) as description
     FROM location l
     INNER JOIN etalage e ON l.id_etalage = e.id_etalage
     ORDER BY l.created_at DESC LIMIT 5)
    UNION
    (SELECT 'inscription' as type, u.created_at as date,
            CONCAT('Inscription de ', u.nom_complet) as description
     FROM utilisateurs u
     INNER JOIN commercant c ON u.id_user = c.id_user
     ORDER BY u.created_at DESC LIMIT 5)
    ORDER BY date DESC LIMIT 10
");
$stmt->execute();
$activites = $stmt->fetchAll();
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        .btn-outline { border: 2px solid #1e3a5f; color: #1e3a5f; transition: all 0.3s ease; }
        .btn-outline:hover { background: #1e3a5f; color: white; }
        .btn-danger { background: #ef4444; color: white; transition: all 0.3s ease; }
        .btn-danger:hover { background: #dc2626; transform: scale(1.02); }
        .btn-success { background: #22c55e; color: white; transition: all 0.3s ease; }
        .btn-success:hover { background: #16a34a; transform: scale(1.02); }
        .btn-info { background: #3b82f6; color: white; transition: all 0.3s ease; }
        .btn-info:hover { background: #2563eb; transform: scale(1.02); }
        .btn-warning { background: #f59e0b; color: white; transition: all 0.3s ease; }
        .btn-warning:hover { background: #d97706; transform: scale(1.02); }
        
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        
        .activity-item {
            padding: 12px 16px;
            border-left: 3px solid #f59e0b;
            margin-bottom: 8px;
            background: #f8fafc;
            border-radius: 0 8px 8px 0;
            transition: all 0.2s;
        }
        .activity-item:hover {
            background: #f1f5f9;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-disponible { background: #dcfce7; color: #166534; }
        .status-occupe { background: #fee2e2; color: #991b1b; }
        .status-en_attente { background: #fef3c7; color: #92400e; }
        .status-actif { background: #dbeafe; color: #1e40af; }
        .status-valide { background: #dcfce7; color: #166534; }
        
        .glass-effect {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(10px);
        }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #d97706; }
    </style>
</head>
<body class="bg-gray-50">

<!-- Navigation Admin -->
<nav class="bg-primary text-white shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center space-x-3">
                <i class="fas fa-store text-accent text-2xl"></i>
                <span class="font-bold text-lg">Marché Virunga</span>
                <span class="text-xs bg-accent/20 px-2 py-1 rounded-full">Admin</span>
            </div>
            <div class="flex items-center space-x-4">
                <div class="hidden md:flex items-center space-x-2">
                    <span class="text-sm">
                        <i class="fas fa-user mr-1"></i>
                        <?= htmlspecialchars($admin_nom) ?>
                    </span>
                    <span class="text-xs bg-white/20 px-2 py-1 rounded-full">
                        <?= htmlspecialchars($admin_matricule) ?>
                    </span>
                </div>
                <a href="../../login.php" class="text-sm bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition flex items-center">
                    <i class="fas fa-sign-out-alt mr-1"></i>
                    <span class="hidden sm:inline">Déconnexion</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Contenu principal -->
<div class="max-w-7xl mx-auto px-4 py-6">

    <!-- En-tête -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border-l-4 border-accent">
        <div class="flex flex-wrap justify-between items-center">
            <div>
                <p class="text-sm text-gray-500">Tableau de bord</p>
                <h1 class="text-2xl font-bold text-primary">
                    <i class="fas fa-user-shield text-accent mr-2"></i>
                    <?= htmlspecialchars($admin_nom) ?>
                </h1>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-id-card mr-1"></i>
                    Admin: <?= htmlspecialchars($admin_matricule) ?>
                    <span class="ml-3 bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-xs">
                        <i class="fas fa-check-circle mr-1"></i> En ligne
                    </span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2 mt-2 sm:mt-0">
                <a href="gestion_etalages.php" class="btn-accent px-4 py-2 rounded-lg font-semibold flex items-center text-sm">
                    <i class="fas fa-warehouse mr-2"></i> Gérer les étalages
                </a>
                <a href="gestion_utilisateurs.php" class="btn-primary px-4 py-2 rounded-lg font-semibold flex items-center text-sm">
                    <i class="fas fa-users mr-2"></i> Gérer les utilisateurs
                </a>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 mb-6">
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Utilisateurs</p>
                    <p class="text-2xl font-bold text-primary"><?= $stats['total_utilisateurs'] ?? 0 ?></p>
                </div>
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-users text-blue-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Commerçants</p>
                    <p class="text-2xl font-bold text-purple-600"><?= $stats['total_commercants'] ?? 0 ?></p>
                </div>
                <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-tie text-purple-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Agents</p>
                    <p class="text-2xl font-bold text-yellow-600"><?= $stats['total_agents'] ?? 0 ?></p>
                </div>
                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-user-cog text-yellow-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Étalages</p>
                    <p class="text-2xl font-bold text-primary"><?= $stats['total_etalages'] ?? 0 ?></p>
                </div>
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-warehouse text-green-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Demandes en attente</p>
                    <p class="text-2xl font-bold text-orange-600"><?= $stats['demandes_attente'] ?? 0 ?></p>
                </div>
                <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-clock text-orange-600"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Deuxième ligne de stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Disponibles</p>
                    <p class="text-2xl font-bold text-green-600"><?= $stats['etalages_disponibles'] ?? 0 ?></p>
                </div>
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Occupés</p>
                    <p class="text-2xl font-bold text-red-600"><?= $stats['etalages_occupes'] ?? 0 ?></p>
                </div>
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-store text-red-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Locations actives</p>
                    <p class="text-2xl font-bold text-blue-600"><?= $stats['locations_actives'] ?? 0 ?></p>
                </div>
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-handshake text-blue-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Revenus totaux</p>
                    <p class="text-2xl font-bold text-accent"><?= number_format($stats['revenus_totaux'] ?? 0, 0, ',', ' ') ?></p>
                </div>
                <div class="w-10 h-10 bg-accent/20 rounded-full flex items-center justify-center">
                    <i class="fas fa-coins text-accent"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Graphiques et Activités -->
    <div class="grid md:grid-cols-2 gap-6">
        <!-- Graphique -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="font-bold text-primary mb-4">
                <i class="fas fa-chart-pie text-accent mr-2"></i>Répartition des étalages
            </h3>
            <canvas id="etalageChart" height="200"></canvas>
        </div>
        
        <!-- Activités récentes -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="font-bold text-primary mb-4">
                <i class="fas fa-bolt text-accent mr-2"></i>Activités récentes
            </h3>
            <div class="max-h-[300px] overflow-y-auto">
                <?php if (count($activites) > 0): ?>
                    <?php foreach ($activites as $activite): ?>
                        <div class="activity-item">
                            <div class="flex items-center gap-2">
                                <?php if ($activite['type'] == 'paiement'): ?>
                                    <span class="text-green-500"><i class="fas fa-coins"></i></span>
                                <?php elseif ($activite['type'] == 'location'): ?>
                                    <span class="text-blue-500"><i class="fas fa-handshake"></i></span>
                                <?php else: ?>
                                    <span class="text-purple-500"><i class="fas fa-user-plus"></i></span>
                                <?php endif; ?>
                                <div>
                                    <p class="text-sm text-gray-700"><?= htmlspecialchars($activite['description']) ?></p>
                                    <p class="text-xs text-gray-400"><?= date('d/m/Y à H:i', strtotime($activite['date'])) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-500 text-center py-8">Aucune activité récente</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="gestion_utilisateurs.php" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition text-center">
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-users text-blue-600 text-xl"></i>
            </div>
            <p class="text-sm font-medium text-gray-700">Utilisateurs</p>
        </a>
        <a href="gestion_commercants.php" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition text-center">
            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-user-tie text-purple-600 text-xl"></i>
            </div>
            <p class="text-sm font-medium text-gray-700">Commerçants</p>
        </a>
        <a href="gestion_etalages.php" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition text-center">
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-warehouse text-green-600 text-xl"></i>
            </div>
            <p class="text-sm font-medium text-gray-700">Étalages</p>
        </a>
        <a href="gestion_paiements.php" class="bg-white rounded-xl shadow-sm p-4 hover:shadow-md transition text-center">
            <div class="w-12 h-12 bg-accent/20 rounded-full flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-coins text-accent text-xl"></i>
            </div>
            <p class="text-sm font-medium text-gray-700">Paiements</p>
        </a>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('etalageChart')?.getContext('2d');
        if (ctx) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Disponibles', 'Occupés', 'En attente'],
                    datasets: [{
                        data: [
                            <?= $stats['etalages_disponibles'] ?? 0 ?>, 
                            <?= $stats['etalages_occupes'] ?? 0 ?>,
                            <?= $stats['demandes_attente'] ?? 0 ?>
                        ],
                        backgroundColor: ['#22c55e', '#ef4444', '#f59e0b'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    });
</script>

</body>
</html>