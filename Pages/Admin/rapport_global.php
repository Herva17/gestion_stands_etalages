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

// Statistiques globales
$stats = [];

// Total utilisateurs
$stmt = $db->query("SELECT COUNT(*) as total FROM utilisateurs");
$stats['total_utilisateurs'] = $stmt->fetch()['total'];

// Total commerçants
$stmt = $db->query("SELECT COUNT(*) as total FROM commercant");
$stats['total_commercants'] = $stmt->fetch()['total'];

// Total agents
$stmt = $db->query("SELECT COUNT(*) as total FROM agent_marche");
$stats['total_agents'] = $stmt->fetch()['total'];

// Total étalages
$stmt = $db->query("SELECT COUNT(*) as total FROM etalage");
$stats['total_etalages'] = $stmt->fetch()['total'];

// Étages par statut
$stmt = $db->query("SELECT statut, COUNT(*) as total FROM etalage GROUP BY statut");
$stats['etalages_par_statut'] = $stmt->fetchAll();

// Total locations
$stmt = $db->query("SELECT COUNT(*) as total FROM location");
$stats['total_locations'] = $stmt->fetch()['total'];

// Locations par statut
$stmt = $db->query("SELECT status, COUNT(*) as total FROM location GROUP BY status");
$stats['locations_par_statut'] = $stmt->fetchAll();

// Total paiements et montant
$stmt = $db->query("SELECT COUNT(*) as total, SUM(montant) as total_montant FROM paiement WHERE statut = 'valide'");
$result = $stmt->fetch();
$stats['total_paiements'] = $result['total'] ?? 0;
$stats['total_montant'] = $result['total_montant'] ?? 0;

// Paiements par mois (derniers 12 mois)
$stmt = $db->query("
    SELECT DATE_FORMAT(date_paiement, '%Y-%m') as mois, 
           COUNT(*) as total, 
           SUM(montant) as montant 
    FROM paiement 
    WHERE statut = 'valide' 
    AND date_paiement >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(date_paiement, '%Y-%m')
    ORDER BY mois DESC
");
$stats['paiements_par_mois'] = $stmt->fetchAll();

// Top 5 commerçants (par montant payé)
$stmt = $db->query("
    SELECT u.nom_complet, u.matricule, 
           COUNT(p.id_paiement) as nb_paiements,
           SUM(p.montant) as total_paye
    FROM paiement p
    INNER JOIN location l ON p.id_location = l.id_location
    INNER JOIN commercant c ON l.id_commercant = c.id_commercant
    INNER JOIN utilisateurs u ON c.id_user = u.id_user
    WHERE p.statut = 'valide'
    GROUP BY c.id_commercant
    ORDER BY total_paye DESC
    LIMIT 5
");
$stats['top_commercants'] = $stmt->fetchAll();

// Demande en attente
$stmt = $db->query("SELECT COUNT(*) as total FROM location WHERE status = 'en_attente'");
$stats['demandes_attente'] = $stmt->fetch()['total'];

$page_title = 'Rapport global - Admin';
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
        .btn-accent { background: #f59e0b; color: white; transition: all 0.3s ease; }
        .btn-accent:hover { background: #d97706; transform: scale(1.02); }
        .btn-primary { background: #1e3a5f; color: white; transition: all 0.3s ease; }
        .btn-primary:hover { background: #2d6a9f; transform: scale(1.02); }
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
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
                <button onclick="window.print()" class="text-sm bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition flex items-center">
                    <i class="fas fa-print mr-1"></i>
                    <span class="hidden sm:inline">Imprimer</span>
                </button>
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
                <i class="fas fa-file-alt text-accent mr-2"></i>Rapport global
            </h1>
            <span class="text-sm text-gray-500">Généré le <?= date('d/m/Y à H:i') ?></span>
        </div>
        
        <!-- Statistiques générales -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card bg-blue-50 rounded-lg p-4">
                <p class="text-sm text-gray-500">Utilisateurs</p>
                <p class="text-2xl font-bold text-blue-600"><?= $stats['total_utilisateurs'] ?></p>
            </div>
            <div class="stat-card bg-green-50 rounded-lg p-4">
                <p class="text-sm text-gray-500">Commerçants</p>
                <p class="text-2xl font-bold text-green-600"><?= $stats['total_commercants'] ?></p>
            </div>
            <div class="stat-card bg-yellow-50 rounded-lg p-4">
                <p class="text-sm text-gray-500">Agents</p>
                <p class="text-2xl font-bold text-yellow-600"><?= $stats['total_agents'] ?></p>
            </div>
            <div class="stat-card bg-purple-50 rounded-lg p-4">
                <p class="text-sm text-gray-500">Étalages</p>
                <p class="text-2xl font-bold text-purple-600"><?= $stats['total_etalages'] ?></p>
            </div>
        </div>
        
        <!-- Deuxième ligne -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card bg-indigo-50 rounded-lg p-4">
                <p class="text-sm text-gray-500">Locations totales</p>
                <p class="text-2xl font-bold text-indigo-600"><?= $stats['total_locations'] ?></p>
            </div>
            <div class="stat-card bg-red-50 rounded-lg p-4">
                <p class="text-sm text-gray-500">Demandes en attente</p>
                <p class="text-2xl font-bold text-red-600"><?= $stats['demandes_attente'] ?></p>
            </div>
            <div class="stat-card bg-accent/20 rounded-lg p-4">
                <p class="text-sm text-gray-500">Total des paiements</p>
                <p class="text-2xl font-bold text-accent"><?= number_format($stats['total_montant'], 0, ',', ' ') ?> FCFA</p>
            </div>
            <div class="stat-card bg-teal-50 rounded-lg p-4">
                <p class="text-sm text-gray-500">Nombre de paiements</p>
                <p class="text-2xl font-bold text-teal-600"><?= $stats['total_paiements'] ?></p>
            </div>
        </div>
        
        <!-- Graphiques -->
        <div class="grid md:grid-cols-2 gap-6 mb-6">
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="font-semibold text-gray-700 mb-3">Étalages par statut</h3>
                <canvas id="etalageStatusChart" height="200"></canvas>
            </div>
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="font-semibold text-gray-700 mb-3">Locations par statut</h3>
                <canvas id="locationStatusChart" height="200"></canvas>
            </div>
        </div>
        
        <!-- Top commerçants -->
        <div class="mb-6">
            <h3 class="font-bold text-primary mb-4">
                <i class="fas fa-trophy text-accent mr-2"></i>Top 5 des commerçants
            </h3>
            <?php if (count($stats['top_commercants']) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commerçant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matricule</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paiements</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total payé</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php $i = 1; foreach ($stats['top_commercants'] as $c): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 text-sm text-gray-500 text-center"><?= $i++ ?></td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($c['nom_complet']) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($c['matricule']) ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600 text-center"><?= $c['nb_paiements'] ?></td>
                                    <td class="px-4 py-3 text-sm font-bold text-accent"><?= number_format($c['total_paye'], 0, ',', ' ') ?> FCFA</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">Aucun paiement enregistré.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Graphique des étalages par statut
    const ctx1 = document.getElementById('etalageStatusChart')?.getContext('2d');
    if (ctx1) {
        const data = <?php 
            $labels = [];
            $values = [];
            foreach ($stats['etalages_par_statut'] as $s) {
                $labels[] = ucfirst($s['statut'] ?? 'Non défini');
                $values[] = $s['total'];
            }
            echo json_encode(['labels' => $labels, 'values' => $values]);
        ?>;
        new Chart(ctx1, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: ['#22c55e', '#ef4444', '#f59e0b', '#3b82f6'],
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
    
    // Graphique des locations par statut
    const ctx2 = document.getElementById('locationStatusChart')?.getContext('2d');
    if (ctx2) {
        const data = <?php 
            $labels = [];
            $values = [];
            foreach ($stats['locations_par_statut'] as $s) {
                $labels[] = ucfirst($s['status'] ?? 'Non défini');
                $values[] = $s['total'];
            }
            echo json_encode(['labels' => $labels, 'values' => $values]);
        ?>;
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Nombre de locations',
                    data: data.values,
                    backgroundColor: ['#3b82f6', '#22c55e', '#ef4444', '#f59e0b'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
});
</script>

</body>
</html>