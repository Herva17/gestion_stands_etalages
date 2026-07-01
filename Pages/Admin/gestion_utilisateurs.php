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

// Récupérer tous les utilisateurs avec leurs rôles - CORRIGÉ
$stmt = $db->query("
    SELECT u.*, 
           c.id_commercant as is_commercant,
           a.id_admin as is_admin,
           ag.id_agent as is_agent
    FROM utilisateurs u
    LEFT JOIN commercant c ON u.id_user = c.id_user
    LEFT JOIN administrateur a ON u.id_user = a.id_user
    LEFT JOIN agent_marche ag ON u.id_user = ag.id_user
    ORDER BY u.created_at DESC
");
$utilisateurs = $stmt->fetchAll();

$page_title = 'Gestion des utilisateurs - Admin';
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
        
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .role-admin { background: #dbeafe; color: #1e40af; }
        .role-commercant { background: #dcfce7; color: #166534; }
        .role-agent { background: #fef3c7; color: #92400e; }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #d97706; }
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
                <a href="../../logout.php" class="text-sm bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition flex items-center">
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
                <i class="fas fa-users text-accent mr-2"></i>Gestion des utilisateurs
            </h1>
            <a href="ajouter_utilisateur.php" class="btn-accent px-4 py-2 rounded-lg font-semibold">
                <i class="fas fa-plus mr-2"></i> Ajouter un utilisateur
            </a>
        </div>
        
        <?php if (count($utilisateurs) > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matricule</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom complet</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Téléphone</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rôle</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($utilisateurs as $u): ?>
                            <tr class="table-row-hover">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($u['matricule']) ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?= htmlspecialchars($u['nom_complet']) ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?= htmlspecialchars($u['email'] ?? '-') ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?= htmlspecialchars($u['telephone'] ?? '-') ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($u['is_admin']): ?>
                                        <span class="role-badge role-admin"><i class="fas fa-user-shield mr-1"></i>Admin</span>
                                    <?php elseif ($u['is_commercant']): ?>
                                        <span class="role-badge role-commercant"><i class="fas fa-user-tie mr-1"></i>Commerçant</span>
                                    <?php elseif ($u['is_agent']): ?>
                                        <span class="role-badge role-agent"><i class="fas fa-user-cog mr-1"></i>Agent</span>
                                    <?php else: ?>
                                        <span class="role-badge bg-gray-100 text-gray-600"><i class="fas fa-user mr-1"></i>Utilisateur</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-1">
                                        <a href="modifier_utilisateur.php?id=<?= $u['id_user'] ?>" 
                                           class="action-btn btn-warning text-white" title="Modifier">
                                            <i class="fas fa-edit text-xs"></i>
                                        </a>
                                        <a href="supprimer_utilisateur.php?id=<?= $u['id_user'] ?>" 
                                           class="action-btn btn-danger text-white" title="Supprimer"
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')">
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
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun utilisateur</h3>
                <p class="text-gray-500">Commencez par ajouter des utilisateurs.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>