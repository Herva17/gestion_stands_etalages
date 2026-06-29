<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';
require_once __DIR__ . '/../../Classes/Etalage.php';
require_once __DIR__ . '/../../Classes/Secteur.php';
require_once __DIR__ . '/../../Classes/Location.php';
require_once __DIR__ . '/../../Classes/Paiement.php';
require_once __DIR__ . '/../../Classes/Commercant.php';
require_once __DIR__ . '/../../Classes/Database.php';

$page_title = 'Dashboard Agent - Marché Virunga';

// Vérifier si l'utilisateur est connecté et est un agent
$agent = new AgentMarche();
if (!$agent->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

// Récupérer les données de l'agent connecté
$user = $agent->getLoggedInUser();
if (!$user) {
    session_destroy();
    header('Location: ../../login.php?error=session_expired');
    exit;
}

$id_agent = $user->getIdAgent();

$db = Database::getInstance()->getConnection();

// =============================================
// GESTION DES NOTIFICATIONS
// =============================================

// Marquer une notification comme lue
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $id_notif = intval($_GET['mark_read']);
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id_notification = ? AND id_agent = ?");
    $stmt->execute([$id_notif, $id_agent]);
    header('Location: dashboard.php');
    exit;
}

// Marquer toutes les notifications comme lues
if (isset($_GET['mark_all_read'])) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id_agent = ?");
    $stmt->execute([$id_agent]);
    header('Location: dashboard.php');
    exit;
}

// Supprimer une notification
if (isset($_GET['delete_notif']) && is_numeric($_GET['delete_notif'])) {
    $id_notif = intval($_GET['delete_notif']);
    $stmt = $db->prepare("DELETE FROM notifications WHERE id_notification = ? AND id_agent = ?");
    $stmt->execute([$id_notif, $id_agent]);
    header('Location: dashboard.php');
    exit;
}

// Récupérer les notifications
$stmt = $db->prepare("
    SELECT * FROM notifications 
    WHERE id_agent = ? 
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute([$id_agent]);
$notifications = $stmt->fetchAll();

// Compter les notifications non lues
$stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE id_agent = ? AND is_read = 0");
$stmt->execute([$id_agent]);
$unread_count = $stmt->fetch()['total'];

// Récupérer les 5 dernières notifications non lues
$stmt = $db->prepare("
    SELECT * FROM notifications 
    WHERE id_agent = ? AND is_read = 0 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$id_agent]);
$recent_notifications = $stmt->fetchAll();

// Initialiser les classes
$etalage = new Etalage();
$secteur = new Secteur();
$location = new Location();
$paiement = new Paiement();
$commercant = new Commercant();

// Initialiser toutes les variables avec des valeurs par défaut
$stats = ['total_etalages' => 0, 'etalages_disponibles' => 0, 'etalages_occupes' => 0, 
          'total_commercants' => 0, 'locations_actives' => 0, 'revenus_totaux' => 0, 'revenus_mois' => 0];
$allEtalages = [];
$etalages_disponibles = [];
$etalages_occupes = [];
$secteurs = [];
$commercants = [];
$locations = [];
$paiements = [];
$paiementStats = [];

// Récupérer les données avec gestion d'erreur
try {
    $stats = $agent->getStats();
    if (!is_array($stats)) {
        $stats = ['total_etalages' => 0, 'etalages_disponibles' => 0, 'etalages_occupes' => 0, 
                  'total_commercants' => 0, 'locations_actives' => 0, 'revenus_totaux' => 0, 'revenus_mois' => 0];
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des stats: " . $e->getMessage());
}

try {
    $allEtalages = $etalage->getAll();
    if (!is_array($allEtalages)) {
        $allEtalages = [];
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des étalages: " . $e->getMessage());
    $allEtalages = [];
}

try {
    $etalages_disponibles = $etalage->getDisponibles();
    if (!is_array($etalages_disponibles)) {
        $etalages_disponibles = [];
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des étalages disponibles: " . $e->getMessage());
    $etalages_disponibles = [];
}

try {
    $etalages_occupes = $etalage->getOccupes();
    if (!is_array($etalages_occupes)) {
        $etalages_occupes = [];
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des étalages occupés: " . $e->getMessage());
    $etalages_occupes = [];
}

try {
    $secteurs = $secteur->getAll();
    if (!is_array($secteurs)) {
        $secteurs = [];
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des secteurs: " . $e->getMessage());
    $secteurs = [];
}

try {
    $commercants = $commercant->getAll();
    if (!is_array($commercants)) {
        $commercants = [];
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des commerçants: " . $e->getMessage());
    $commercants = [];
}

try {
    $locations = $location->getAll();
    if (!is_array($locations)) {
        $locations = [];
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des locations: " . $e->getMessage());
    $locations = [];
}

try {
    $paiements = $paiement->getAll();
    if (!is_array($paiements)) {
        $paiements = [];
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des paiements: " . $e->getMessage());
    $paiements = [];
}

try {
    $paiementStats = $paiement->getStats();
    if (!is_array($paiementStats)) {
        $paiementStats = [];
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des stats paiements: " . $e->getMessage());
    $paiementStats = [];
}

// Récupérer le nom de l'agent
$agent_nom = $user->getNomComplet();
$agent_matricule = $user->getMatriculeAgent();

// S'assurer que les variables sont définies avant le HTML
if (!isset($allEtalages)) $allEtalages = [];
if (!isset($etalages_disponibles)) $etalages_disponibles = [];
if (!isset($etalages_occupes)) $etalages_occupes = [];
if (!isset($secteurs)) $secteurs = [];
if (!isset($commercants)) $commercants = [];
if (!isset($locations)) $locations = [];
if (!isset($paiements)) $paiements = [];
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Chart.js pour les graphiques -->
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
        .btn-danger { background: #dc2626; color: white; transition: all 0.3s ease; }
        .btn-danger:hover { background: #b91c1c; transform: scale(1.02); }
        .btn-success { background: #16a34a; color: white; transition: all 0.3s ease; }
        .btn-success:hover { background: #15803d; transform: scale(1.02); }
        .btn-warning { background: #f59e0b; color: white; transition: all 0.3s ease; }
        .btn-warning:hover { background: #d97706; transform: scale(1.02); }
        .btn-info { background: #3b82f6; color: white; transition: all 0.3s ease; }
        .btn-info:hover { background: #2563eb; transform: scale(1.02); }
        
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        
        .etalage-card { transition: all 0.3s ease; }
        .etalage-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        
        .tab-active { border-bottom: 3px solid #f59e0b; color: #1e3a5f; font-weight: 600; }
        .tab-inactive { color: #6b7280; border-bottom: 3px solid transparent; }
        .tab-inactive:hover { color: #1e3a5f; border-bottom-color: #d1d5db; }
        
        .modal-overlay { background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #d97706; }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-disponible { background: #dcfce7; color: #166534; }
        .status-occupe { background: #fee2e2; color: #991b1b; }
        .status-en-attente { background: #fef3c7; color: #92400e; }
        .status-actif { background: #dbeafe; color: #1e40af; }
        
        .glass-effect {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(10px);
        }
        
        .recent-activity {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .activity-item {
            border-left: 3px solid #f59e0b;
            padding-left: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .table-row-hover:hover {
            background-color: #f8fafc;
        }
        
        .action-btn {
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            transition: all 0.2s;
        }
        .action-btn:hover {
            transform: scale(1.05);
        }

        /* ============================================ */
        /* STYLES DES NOTIFICATIONS */
        /* ============================================ */
        .notification-bell {
            position: relative;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .notification-bell:hover {
            transform: scale(1.1);
        }
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            animation: pulse-badge 2s infinite;
        }
        @keyframes pulse-badge {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 380px;
            max-height: 400px;
            overflow-y: auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            display: none;
            z-index: 1000;
            margin-top: 10px;
            border: 1px solid #e5e7eb;
        }
        .notification-dropdown.show {
            display: block;
            animation: slideDown 0.3s ease forwards;
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .notification-item {
            padding: 12px 16px;
            border-bottom: 1px solid #f3f4f6;
            transition: background 0.2s ease;
            cursor: pointer;
        }
        .notification-item:hover {
            background: #f9fafb;
        }
        .notification-item.unread {
            background: #f0f7ff;
            border-left: 3px solid #f59e0b;
        }
        .notification-item .notif-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .notification-item .notif-icon.success { background: #dcfce7; color: #16a34a; }
        .notification-item .notif-icon.warning { background: #fef3c7; color: #d97706; }
        .notification-item .notif-icon.info { background: #dbeafe; color: #2563eb; }
        .notification-item .notif-icon.error { background: #fee2e2; color: #dc2626; }
        
        .notification-time {
            font-size: 11px;
            color: #9ca3af;
        }
        
        .notification-empty {
            padding: 30px;
            text-align: center;
            color: #9ca3af;
        }
        .notification-empty i {
            font-size: 40px;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        .notification-dropdown::-webkit-scrollbar { width: 4px; }
        .notification-dropdown::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .notification-dropdown::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 10px; }
    </style>
</head>
<body class="bg-gray-50">

<!-- ============================================ -->
<!-- NAVIGATION AVEC NOTIFICATIONS -->
<!-- ============================================ -->
<nav class="bg-primary text-white shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center space-x-3">
                <i class="fas fa-store text-accent text-2xl"></i>
                <span class="font-bold text-lg">Marché Virunga</span>
                <span class="text-xs bg-accent/20 px-2 py-1 rounded-full">Agent</span>
            </div>
            <div class="flex items-center space-x-4">
                <div class="hidden md:flex items-center space-x-2">
                    <span class="text-sm">
                        <i class="fas fa-user mr-1"></i>
                        <?= htmlspecialchars($agent_nom) ?>
                    </span>
                    <span class="text-xs bg-white/20 px-2 py-1 rounded-full">
                        <?= htmlspecialchars($agent_matricule) ?>
                    </span>
                </div>
                
                <!-- ============================================ -->
                <!-- ICÔNE DE NOTIFICATION AVEC MENU DÉROULANT -->
                <!-- ============================================ -->
                <div class="relative notification-bell" id="notificationWrapper">
                    <button onclick="toggleNotifications()" class="relative text-white hover:text-accent transition">
                        <i class="fas fa-bell text-xl"></i>
                        <?php if ($unread_count > 0): ?>
                            <span class="notification-badge"><?= $unread_count > 99 ? '99+' : $unread_count ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Menu déroulant des notifications -->
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="flex items-center justify-between p-3 border-b border-gray-200 bg-gray-50 rounded-t-xl">
                            <span class="font-semibold text-gray-700 text-sm">
                                <i class="fas fa-bell mr-2 text-accent"></i>
                                Notifications
                            </span>
                            <div class="flex gap-2">
                                <?php if ($unread_count > 0): ?>
                                    <button onclick="markAllAsRead()" class="text-xs text-accent hover:text-accent/80 transition">
                                        <i class="fas fa-check-double mr-1"></i> Tout marquer lu
                                    </button>
                                <?php endif; ?>
                                <button onclick="closeNotifications()" class="text-xs text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="notification-list">
                            <?php if (count($recent_notifications) > 0): ?>
                                <?php foreach ($recent_notifications as $notif): ?>
                                    <div class="notification-item unread" data-id="<?= $notif['id_notification'] ?>" onclick="openNotification(<?= $notif['id_notification'] ?>, '<?= $notif['lien'] ?>')">
                                        <div class="flex items-start gap-3">
                                            <div class="notif-icon <?= $notif['type'] ?>">
                                                <?php if ($notif['type'] == 'success'): ?>
                                                    <i class="fas fa-check-circle"></i>
                                                <?php elseif ($notif['type'] == 'warning'): ?>
                                                    <i class="fas fa-exclamation-triangle"></i>
                                                <?php elseif ($notif['type'] == 'error'): ?>
                                                    <i class="fas fa-times-circle"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-info-circle"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($notif['title']) ?></p>
                                                <p class="text-xs text-gray-600 truncate"><?= htmlspecialchars($notif['message']) ?></p>
                                                <p class="text-xs text-gray-400 mt-1">
                                                    <i class="far fa-clock mr-1"></i>
                                                    <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?>
                                                </p>
                                            </div>
                                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                                <button onclick="event.stopPropagation(); markAsRead(<?= $notif['id_notification'] ?>)" 
                                                        class="text-xs text-gray-400 hover:text-green-600 transition" title="Marquer comme lu">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button onclick="event.stopPropagation(); deleteNotification(<?= $notif['id_notification'] ?>)" 
                                                        class="text-xs text-gray-400 hover:text-red-600 transition" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if ($unread_count > 5): ?>
                                    <div class="p-3 text-center border-t border-gray-200">
                                        <span class="text-xs text-gray-500">
                                            +<?= $unread_count - 5 ?> notification(s) non lue(s)
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="p-3 text-center border-t border-gray-200">
                                    <a href="#" onclick="showTab('notifications'); closeNotifications(); return false;" class="text-xs text-accent hover:text-accent/80 transition">
                                        <i class="fas fa-eye mr-1"></i> Voir toutes les notifications
                                    </a>
                                </div>
                                
                            <?php else: ?>
                                <div class="notification-empty">
                                    <i class="fas fa-bell-slash"></i>
                                    <p class="text-sm">Aucune notification</p>
                                    <p class="text-xs mt-1">Vous serez notifié des mises à jour</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <a href="../../login.php" class="text-sm bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition flex items-center">
                    <i class="fas fa-sign-out-alt mr-1"></i>
                    <span class="hidden sm:inline">Déconnexion</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- ============================================ -->
<!-- CONTENU PRINCIPAL -->
<!-- ============================================ -->
<div class="max-w-7xl mx-auto px-4 py-6">

    <!-- En-tête -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border-l-4 border-accent">
        <div class="flex flex-wrap justify-between items-center">
            <div>
                <p class="text-sm text-gray-500">Tableau de bord</p>
                <h1 class="text-2xl font-bold text-primary">
                    <i class="fas fa-user-tie text-accent mr-2"></i>
                    <?= htmlspecialchars($agent_nom) ?>
                </h1>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-id-card mr-1"></i>
                    Agent: <?= htmlspecialchars($agent_matricule) ?>
                    <span class="ml-3 bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-xs">
                        <i class="fas fa-check-circle mr-1"></i> En ligne
                    </span>
                    <?php if ($unread_count > 0): ?>
                        <span class="ml-3 bg-red-100 text-red-700 px-2 py-0.5 rounded-full text-xs animate-pulse">
                            <i class="fas fa-bell mr-1"></i> <?= $unread_count ?> notification(s)
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex flex-wrap gap-2 mt-2 sm:mt-0">
                <button onclick="openModal('addEtalage')" class="btn-accent px-4 py-2 rounded-lg font-semibold flex items-center text-sm">
                    <i class="fas fa-plus mr-2"></i> Ajouter un étalage
                </button>
                <button onclick="openModal('addSecteur')" class="btn-primary px-4 py-2 rounded-lg font-semibold flex items-center text-sm">
                    <i class="fas fa-layer-group mr-2"></i> Ajouter un secteur
                </button>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Total étalages</p>
                    <p class="text-2xl font-bold text-primary"><?= $stats['total_etalages'] ?? 0 ?></p>
                </div>
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-warehouse text-blue-600"></i>
                </div>
            </div>
        </div>
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
                    <p class="text-xs text-gray-500">Commerçants</p>
                    <p class="text-2xl font-bold text-primary"><?= $stats['total_commercants'] ?? 0 ?></p>
                </div>
                <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-users text-purple-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Locations actives</p>
                    <p class="text-2xl font-bold text-yellow-600"><?= $stats['locations_actives'] ?? 0 ?></p>
                </div>
                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-handshake text-yellow-600"></i>
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

    <!-- Graphiques -->
    <div class="grid md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="font-bold text-primary mb-4">
                <i class="fas fa-chart-pie text-accent mr-2"></i>Répartition des étalages
            </h3>
            <canvas id="etalageChart" height="200"></canvas>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="font-bold text-primary mb-4">
                <i class="fas fa-chart-line text-accent mr-2"></i>Évolution des revenus
            </h3>
            <canvas id="revenueChart" height="200"></canvas>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="border-b border-gray-200 overflow-x-auto">
            <nav class="flex flex-nowrap -mb-px" id="tab-nav">
                <button onclick="showTab('etalages')" class="tab-active px-6 py-3 text-sm font-medium transition whitespace-nowrap" id="tab-etalages">
                    <i class="fas fa-warehouse mr-2"></i>Tous les étalages
                </button>
                <button onclick="showTab('disponibles')" class="tab-inactive px-6 py-3 text-sm font-medium transition whitespace-nowrap" id="tab-disponibles">
                    <i class="fas fa-check-circle mr-2"></i>Disponibles
                </button>
                <button onclick="showTab('occupes')" class="tab-inactive px-6 py-3 text-sm font-medium transition whitespace-nowrap" id="tab-occupes">
                    <i class="fas fa-store mr-2"></i>Occupés
                </button>
                <button onclick="showTab('attributions')" class="tab-inactive px-6 py-3 text-sm font-medium transition whitespace-nowrap" id="tab-attributions">
                    <i class="fas fa-handshake mr-2"></i>Attributions
                </button>
                <button onclick="showTab('commercants')" class="tab-inactive px-6 py-3 text-sm font-medium transition whitespace-nowrap" id="tab-commercants">
                    <i class="fas fa-users mr-2"></i>Commerçants
                </button>
                <button onclick="showTab('paiements')" class="tab-inactive px-6 py-3 text-sm font-medium transition whitespace-nowrap" id="tab-paiements">
                    <i class="fas fa-coins mr-2"></i>Paiements
                </button>
                <?php if ($unread_count > 0): ?>
                    <button onclick="showTab('notifications')" class="tab-inactive px-6 py-3 text-sm font-medium transition whitespace-nowrap relative" id="tab-notifications">
                        <i class="fas fa-bell mr-2"></i>Notifications
                        <span class="ml-1 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full"><?= $unread_count ?></span>
                    </button>
                <?php endif; ?>
            </nav>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB 1: TOUS LES ÉTALAGES -->
    <!-- ============================================ -->
    <div id="content-etalages" class="tab-content">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-warehouse text-accent mr-2"></i>Gestion des étalages
            </h2>
            <div class="flex gap-2">
                <input type="text" id="searchEtalage" placeholder="Rechercher..." 
                       class="px-3 py-1 border border-gray-300 rounded-lg text-sm focus:border-accent focus:outline-none">
                <select id="filterSecteur" class="px-3 py-1 border border-gray-300 rounded-lg text-sm focus:border-accent focus:outline-none">
                    <option value="">Tous les secteurs</option>
                    <?php foreach ($secteurs as $s): ?>
                        <option value="<?= $s['id_secteur'] ?>"><?= htmlspecialchars($s['designation']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <?php if (count($allEtalages) > 0): ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4" id="etalageGrid">
                <?php foreach ($allEtalages as $e): ?>
                    <div class="etalage-card bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100" 
                         data-secteur="<?= $e['id_secteur'] ?>">
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-bold text-primary">
                                        Étalage #<?= htmlspecialchars($e['numero']) ?>
                                    </h3>
                                    <p class="text-xs text-gray-500">
                                        <i class="fas fa-map-pin mr-1"></i>
                                        <?= htmlspecialchars($e['localisation'] ?? 'Non spécifiée') ?>
                                    </p>
                                </div>
                                <span class="status-badge status-<?= $e['statut'] ?? 'disponible' ?>">
                                    <?= ucfirst($e['statut'] ?? 'Disponible') ?>
                                </span>
                            </div>
                            <div class="space-y-1 text-sm">
                                <p class="text-gray-600">
                                    <i class="fas fa-tag text-accent mr-1"></i>
                                    Secteur: <?= htmlspecialchars($e['secteur_nom'] ?? 'Non défini') ?>
                                </p>
                                <?php if ($e['commercant_nom']): ?>
                                    <p class="text-gray-600">
                                        <i class="fas fa-user text-accent mr-1"></i>
                                        Commerçant: <?= htmlspecialchars($e['commercant_nom']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-100 flex gap-2">
                                <?php if ($e['statut'] === 'disponible' || $e['statut'] === null): ?>
                                    <button onclick="openModal('attribuer', <?= $e['id_etalage'] ?>)" 
                                            class="flex-1 btn-accent text-sm py-1.5 rounded-lg font-semibold">
                                        <i class="fas fa-handshake mr-1"></i> Attribuer
                                    </button>
                                <?php else: ?>
                                    <button onclick="libererEtalage(<?= $e['id_etalage'] ?>)" 
                                            class="flex-1 btn-danger text-sm py-1.5 rounded-lg font-semibold">
                                        <i class="fas fa-unlock mr-1"></i> Libérer
                                    </button>
                                <?php endif; ?>
                                <button onclick="editEtalage(<?= $e['id_etalage'] ?>)" 
                                        class="flex-1 btn-outline text-sm py-1.5 rounded-lg">
                                    <i class="fas fa-edit mr-1"></i> Modifier
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-warehouse"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun étalage</h3>
                <p class="text-gray-500">Commencez par ajouter des étalages au marché.</p>
                <button onclick="openModal('addEtalage')" class="btn-accent mt-4 px-6 py-2 rounded-lg font-semibold">
                    <i class="fas fa-plus mr-2"></i> Ajouter un étalage
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- TAB 2: ÉTALAGES DISPONIBLES -->
    <!-- ============================================ -->
    <div id="content-disponibles" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-check-circle text-accent mr-2"></i>Étalages disponibles
            </h2>
            <span class="text-sm text-gray-500"><?= count($etalages_disponibles) ?> étalages disponibles</span>
        </div>

        <?php if (count($etalages_disponibles) > 0): ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($etalages_disponibles as $e): ?>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden border-2 border-green-200">
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-bold text-primary text-lg">
                                        Étalage #<?= htmlspecialchars($e['numero']) ?>
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        <i class="fas fa-map-pin mr-1"></i>
                                        <?= htmlspecialchars($e['localisation'] ?? 'Non spécifiée') ?>
                                    </p>
                                </div>
                                <span class="status-badge status-disponible">
                                    <i class="fas fa-circle mr-1 text-green-500"></i> Disponible
                                </span>
                            </div>
                            <p class="text-sm text-gray-600">
                                <i class="fas fa-tag text-accent mr-1"></i>
                                Secteur: <?= htmlspecialchars($e['secteur_nom'] ?? 'Non défini') ?>
                            </p>
                            <button onclick="openModal('attribuer', <?= $e['id_etalage'] ?>)" 
                                    class="w-full mt-3 btn-accent py-2 rounded-lg font-semibold">
                                <i class="fas fa-handshake mr-2"></i> Attribuer à un commerçant
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun étalage disponible</h3>
                <p class="text-gray-500">Tous les étalages sont actuellement occupés.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- TAB 3: ÉTALAGES OCCUPÉS -->
    <!-- ============================================ -->
    <div id="content-occupes" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-store text-accent mr-2"></i>Étalages occupés
            </h2>
            <span class="text-sm text-gray-500"><?= count($etalages_occupes) ?> étalages occupés</span>
        </div>

        <?php if (count($etalages_occupes) > 0): ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($etalages_occupes as $e): ?>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden border-2 border-red-200">
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-bold text-primary text-lg">
                                        Étalage #<?= htmlspecialchars($e['numero']) ?>
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        <i class="fas fa-map-pin mr-1"></i>
                                        <?= htmlspecialchars($e['localisation'] ?? 'Non spécifiée') ?>
                                    </p>
                                </div>
                                <span class="status-badge status-occupe">
                                    <i class="fas fa-circle mr-1 text-red-500"></i> Occupé
                                </span>
                            </div>
                            <p class="text-sm text-gray-600">
                                <i class="fas fa-user text-accent mr-1"></i>
                                Commerçant: <?= htmlspecialchars($e['commercant_nom'] ?? 'Inconnu') ?>
                            </p>
                            <p class="text-sm text-gray-600">
                                <i class="fas fa-tag text-accent mr-1"></i>
                                Secteur: <?= htmlspecialchars($e['secteur_nom'] ?? 'Non défini') ?>
                            </p>
                            <button onclick="libererEtalage(<?= $e['id_etalage'] ?>)" 
                                    class="w-full mt-3 btn-danger py-2 rounded-lg font-semibold">
                                <i class="fas fa-unlock mr-2"></i> Libérer l'étalage
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-store"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun étalage occupé</h3>
                <p class="text-gray-500">Tous les étalages sont disponibles.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- TAB 4: ATTRIBUTIONS -->
    <!-- ============================================ -->
    <div id="content-attributions" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-handshake text-accent mr-2"></i>Historique des attributions
            </h2>
            <button onclick="openModal('addLocation')" class="btn-accent px-4 py-2 rounded-lg font-semibold text-sm">
                <i class="fas fa-plus mr-2"></i> Nouvelle attribution
            </button>
        </div>

        <?php if (count($locations) > 0): ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Étalage</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commerçant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Début</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fin</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($locations as $loc): ?>
                                <tr class="table-row-hover">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        #<?= htmlspecialchars($loc['etalage_numero']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= htmlspecialchars($loc['commercant_nom']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-bold text-accent">
                                        <?= number_format($loc['montant_location'], 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= date('d/m/Y', strtotime($loc['date_debut'])) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= date('d/m/Y', strtotime($loc['date_fin'])) ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if (strtotime($loc['date_fin']) >= time()): ?>
                                            <span class="status-badge status-actif">
                                                <i class="fas fa-check-circle mr-1"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-disponible">
                                                <i class="fas fa-clock mr-1"></i> Expirée
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex gap-1">
                                            <button onclick="viewLocation(<?= $loc['id_location'] ?>)" 
                                                    class="action-btn btn-info text-white" title="Voir">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                            <?php if (strtotime($loc['date_fin']) >= time()): ?>
                                                <button onclick="editLocation(<?= $loc['id_location'] ?>)" 
                                                        class="action-btn btn-warning text-white" title="Modifier">
                                                    <i class="fas fa-edit text-xs"></i>
                                                </button>
                                                <button onclick="renouvelerLocation(<?= $loc['id_location'] ?>)" 
                                                        class="action-btn btn-success text-white" title="Renouveler">
                                                    <i class="fas fa-sync text-xs"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button onclick="deleteLocation(<?= $loc['id_location'] ?>)" 
                                                    class="action-btn btn-danger text-white" title="Supprimer">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-handshake"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucune attribution</h3>
                <p class="text-gray-500">Aucune location n'a encore été enregistrée.</p>
                <button onclick="openModal('addLocation')" class="btn-accent mt-4 px-6 py-2 rounded-lg font-semibold">
                    <i class="fas fa-plus mr-2"></i> Nouvelle attribution
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- TAB 5: COMMERÇANTS -->
    <!-- ============================================ -->
    <div id="content-commercants" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-users text-accent mr-2"></i>Liste des commerçants
            </h2>
            <button onclick="openModal('addCommercant')" class="btn-accent px-4 py-2 rounded-lg font-semibold text-sm">
                <i class="fas fa-user-plus mr-2"></i> Ajouter un commerçant
            </button>
        </div>

        <?php if (count($commercants) > 0): ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matricule</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Téléphone</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Locations</th>
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
                                        <?= htmlspecialchars($c['nom_complet']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= htmlspecialchars($c['telephone'] ?? '-') ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= htmlspecialchars($c['email'] ?? '-') ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full text-xs">
                                            <?= $c['nb_locations'] ?? 0 ?> location(s)
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex gap-1">
                                            <button onclick="viewCommercant(<?= $c['id_commercant'] ?>)" 
                                                    class="action-btn btn-info text-white" title="Voir">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                            <button onclick="editCommercant(<?= $c['id_commercant'] ?>)" 
                                                    class="action-btn btn-warning text-white" title="Modifier">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button onclick="deleteCommercant(<?= $c['id_commercant'] ?>)" 
                                                    class="action-btn btn-danger text-white" title="Supprimer">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun commerçant</h3>
                <p class="text-gray-500">Aucun commerçant n'est encore enregistré.</p>
                <button onclick="openModal('addCommercant')" class="btn-accent mt-4 px-6 py-2 rounded-lg font-semibold">
                    <i class="fas fa-user-plus mr-2"></i> Ajouter un commerçant
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- TAB 6: PAIEMENTS -->
    <!-- ============================================ -->
    <div id="content-paiements" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-coins text-accent mr-2"></i>Historique des paiements
            </h2>
            <div class="flex gap-2">
                <button onclick="openModal('addPaiement')" class="btn-accent px-4 py-2 rounded-lg font-semibold text-sm">
                    <i class="fas fa-plus mr-2"></i> Enregistrer un paiement
                </button>
                <button onclick="generateReport()" class="btn-primary px-4 py-2 rounded-lg font-semibold text-sm">
                    <i class="fas fa-file-pdf mr-2"></i> Rapport
                </button>
            </div>
        </div>

        <?php if (count($paiements) > 0): ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commerçant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Étalage</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mode</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Période</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($paiements as $p): ?>
                                <tr class="table-row-hover">
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= date('d/m/Y', strtotime($p['date_paiement'])) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= htmlspecialchars($p['commercant_nom']) ?>
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
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= htmlspecialchars($p['periode'] ?? '-') ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex gap-1">
                                            <button onclick="viewPaiement(<?= $p['id_paiement'] ?>)" 
                                                    class="action-btn btn-info text-white" title="Voir">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>
                                            <button onclick="editPaiement(<?= $p['id_paiement'] ?>)" 
                                                    class="action-btn btn-warning text-white" title="Modifier">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button onclick="printRecu(<?= $p['id_paiement'] ?>)" 
                                                    class="action-btn btn-primary text-white" title="Imprimer">
                                                <i class="fas fa-print text-xs"></i>
                                            </button>
                                            <button onclick="deletePaiement(<?= $p['id_paiement'] ?>)" 
                                                    class="action-btn btn-danger text-white" title="Supprimer">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-coins"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun paiement</h3>
                <p class="text-gray-500">Aucun paiement n'a encore été enregistré.</p>
                <button onclick="openModal('addPaiement')" class="btn-accent mt-4 px-6 py-2 rounded-lg font-semibold">
                    <i class="fas fa-plus mr-2"></i> Enregistrer un paiement
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- TAB 7: NOTIFICATIONS -->
    <!-- ============================================ -->
    <div id="content-notifications" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-bell text-accent mr-2"></i>Toutes les notifications
            </h2>
            <div class="flex gap-2">
                <?php if ($unread_count > 0): ?>
                    <a href="?mark_all_read=1" class="btn-outline px-3 py-1 rounded-lg text-sm font-semibold">
                        <i class="fas fa-check-double mr-1"></i> Tout marquer lu
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (count($notifications) > 0): ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="divide-y divide-gray-200">
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-tab-item p-4 <?= $notif['is_read'] ? 'bg-white' : 'bg-blue-50 border-l-4 border-accent' ?> hover:bg-gray-50 transition" data-id="<?= $notif['id_notification'] ?>">
                            <div class="flex items-start gap-3">
                                <div class="notif-icon <?= $notif['type'] ?> flex-shrink-0">
                                    <?php if ($notif['type'] == 'success'): ?>
                                        <i class="fas fa-check-circle"></i>
                                    <?php elseif ($notif['type'] == 'warning'): ?>
                                        <i class="fas fa-exclamation-triangle"></i>
                                    <?php elseif ($notif['type'] == 'error'): ?>
                                        <i class="fas fa-times-circle"></i>
                                    <?php else: ?>
                                        <i class="fas fa-info-circle"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between">
                                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($notif['title']) ?></p>
                                        <div class="flex gap-2">
                                            <?php if (!$notif['is_read']): ?>
                                                <span class="text-xs bg-accent/20 text-accent-700 px-2 py-0.5 rounded-full animate-pulse">Nouveau</span>
                                            <?php endif; ?>
                                            <button onclick="markAsRead(<?= $notif['id_notification'] ?>)" 
                                                    class="text-xs text-gray-400 hover:text-green-600 transition" title="Marquer comme lu">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button onclick="deleteNotification(<?= $notif['id_notification'] ?>)" 
                                                    class="text-xs text-gray-400 hover:text-red-600 transition" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($notif['message']) ?></p>
                                    <div class="flex items-center justify-between mt-1">
                                        <p class="text-xs text-gray-400">
                                            <i class="far fa-clock mr-1"></i>
                                            <?= date('d/m/Y à H:i', strtotime($notif['created_at'])) ?>
                                        </p>
                                        <?php if ($notif['lien']): ?>
                                            <a href="<?= $notif['lien'] ?>" class="text-xs text-accent hover:text-accent/80 transition">
                                                Voir plus <i class="fas fa-arrow-right ml-1"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-bell-slash"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucune notification</h3>
                <p class="text-gray-500">Vous n'avez pas encore de notifications.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- ============================================ -->
<!-- MODAL: AJOUTER UN ÉTALAGE -->
<!-- ============================================ -->
<div id="modal-addEtalage" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-primary">
                <i class="fas fa-plus-circle text-accent mr-2"></i>Ajouter un étalage
            </h3>
            <button onclick="closeModal('addEtalage')" class="text-gray-400 hover:text-gray-600 text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="form-addEtalage" method="POST" action="/api/agent/ajouter_etalage.php">
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Numéro de l'étalage <span class="text-red-500">*</span></label>
                <input type="text" name="numero" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                       placeholder="Ex: A-01, B-12">
            </div>
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Localisation</label>
                <input type="text" name="localisation" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                       placeholder="Ex: Allée centrale, côté nord">
            </div>
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Secteur <span class="text-red-500">*</span></label>
                <select name="id_secteur" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                    <option value="">Sélectionner un secteur</option>
                    <?php foreach ($secteurs as $s): ?>
                        <option value="<?= $s['id_secteur'] ?>"><?= htmlspecialchars($s['designation']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="w-full btn-accent py-2 rounded-lg font-semibold">
                <i class="fas fa-save mr-2"></i> Ajouter l'étalage
            </button>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL: AJOUTER UN SECTEUR -->
<!-- ============================================ -->
<div id="modal-addSecteur" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-primary">
                <i class="fas fa-layer-group text-accent mr-2"></i>Ajouter un secteur
            </h3>
            <button onclick="closeModal('addSecteur')" class="text-gray-400 hover:text-gray-600 text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="form-addSecteur" method="POST" action="/api/agent/ajouter_secteur.php">
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Désignation du secteur <span class="text-red-500">*</span></label>
                <input type="text" name="designation" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                       placeholder="Ex: Fruits et Légumes, Boucherie">
            </div>
            
            <button type="submit" class="w-full btn-primary py-2 rounded-lg font-semibold">
                <i class="fas fa-save mr-2"></i> Ajouter le secteur
            </button>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL: ATTRIBUER UN ÉTALAGE -->
<!-- ============================================ -->
<div id="modal-attribuer" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-primary">
                <i class="fas fa-handshake text-accent mr-2"></i>Attribuer un étalage
            </h3>
            <button onclick="closeModal('attribuer')" class="text-gray-400 hover:text-gray-600 text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="form-attribuer" method="POST" action="/api/agent/attribuer_etalage.php">
            <input type="hidden" name="id_etalage" id="attribuer_etalage_id">
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Commerçant <span class="text-red-500">*</span></label>
                <select name="id_commercant" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                    <option value="">Sélectionner un commerçant</option>
                    <?php foreach ($commercants as $c): ?>
                        <option value="<?= $c['id_commercant'] ?>"><?= htmlspecialchars($c['nom_complet']) ?> (<?= htmlspecialchars($c['matricule']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Montant (FCFA) <span class="text-red-500">*</span></label>
                    <input type="number" name="montant_location" required min="0" step="1000"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                           placeholder="250000">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Durée (jours) <span class="text-red-500">*</span></label>
                    <input type="number" name="duree" required min="1" value="30"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                </div>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                <p class="text-sm text-yellow-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    La location débutera aujourd'hui et prendra fin après la durée indiquée.
                </p>
            </div>
            
            <button type="submit" class="w-full btn-accent py-2 rounded-lg font-semibold">
                <i class="fas fa-check mr-2"></i> Attribuer l'étalage
            </button>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL: AJOUTER UN COMMERÇANT -->
<!-- ============================================ -->
<div id="modal-addCommercant" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-primary">
                <i class="fas fa-user-plus text-accent mr-2"></i>Ajouter un commerçant
            </h3>
            <button onclick="closeModal('addCommercant')" class="text-gray-400 hover:text-gray-600 text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="form-addCommercant" method="POST" action="/api/agent/ajouter_commercant.php">
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom complet <span class="text-red-500">*</span></label>
                <input type="text" name="nom_complet" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                       placeholder="Ex: Jean-Pierre KABUYA">
            </div>
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Téléphone <span class="text-red-500">*</span></label>
                <input type="tel" name="telephone" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                       placeholder="Ex: 08XXXXXXXX">
            </div>
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                       placeholder="Ex: commerçant@email.com">
            </div>
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
                <input type="text" name="adresse" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                       placeholder="Ex: Quartier, Ville">
            </div>
            
            <button type="submit" class="w-full btn-accent py-2 rounded-lg font-semibold">
                <i class="fas fa-save mr-2"></i> Ajouter le commerçant
            </button>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL: NOUVELLE LOCATION -->
<!-- ============================================ -->
<div id="modal-addLocation" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-primary">
                <i class="fas fa-handshake text-accent mr-2"></i>Nouvelle location
            </h3>
            <button onclick="closeModal('addLocation')" class="text-gray-400 hover:text-gray-600 text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="form-addLocation" method="POST" action="/api/agent/ajouter_location.php">
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Étalage <span class="text-red-500">*</span></label>
                <select name="id_etalage" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                    <option value="">Sélectionner un étalage</option>
                    <?php foreach ($allEtalages as $e): ?>
                        <option value="<?= $e['id_etalage'] ?>">#<?= htmlspecialchars($e['numero']) ?> - <?= htmlspecialchars($e['localisation'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Commerçant <span class="text-red-500">*</span></label>
                <select name="id_commercant" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                    <option value="">Sélectionner un commerçant</option>
                    <?php foreach ($commercants as $c): ?>
                        <option value="<?= $c['id_commercant'] ?>"><?= htmlspecialchars($c['nom_complet']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Montant (FCFA) <span class="text-red-500">*</span></label>
                    <input type="number" name="montant_location" required min="0" step="1000"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                           placeholder="250000">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Durée (mois) <span class="text-red-500">*</span></label>
                    <input type="number" name="duree_mois" required min="1" value="1"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                </div>
            </div>
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Date de début <span class="text-red-500">*</span></label>
                <input type="date" name="date_debut" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
            </div>
            
            <button type="submit" class="w-full btn-accent py-2 rounded-lg font-semibold">
                <i class="fas fa-save mr-2"></i> Enregistrer la location
            </button>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL: ENREGISTRER UN PAIEMENT -->
<!-- ============================================ -->
<div id="modal-addPaiement" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-primary">
                <i class="fas fa-coins text-accent mr-2"></i>Enregistrer un paiement
            </h3>
            <button onclick="closeModal('addPaiement')" class="text-gray-400 hover:text-gray-600 text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="form-addPaiement" method="POST" action="/api/agent/ajouter_paiement.php">
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Commerçant <span class="text-red-500">*</span></label>
                <select name="id_commercant" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                    <option value="">Sélectionner un commerçant</option>
                    <?php foreach ($commercants as $c): ?>
                        <option value="<?= $c['id_commercant'] ?>"><?= htmlspecialchars($c['nom_complet']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Étalage <span class="text-red-500">*</span></label>
                <select name="id_etalage" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                    <option value="">Sélectionner un étalage</option>
                    <?php foreach ($allEtalages as $e): ?>
                        <option value="<?= $e['id_etalage'] ?>">#<?= htmlspecialchars($e['numero']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Montant (FCFA) <span class="text-red-500">*</span></label>
                    <input type="number" name="montant" required min="0" step="100"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                           placeholder="50000">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mode de paiement <span class="text-red-500">*</span></label>
                    <select name="mode_paiement" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                        <option value="Espèces">Espèces</option>
                        <option value="Mobile Money">Mobile Money</option>
                        <option value="Virement">Virement</option>
                        <option value="Chèque">Chèque</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Période concernée</label>
                <input type="text" name="periode" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"
                       placeholder="Ex: Janvier 2024">
            </div>
            
            <button type="submit" class="w-full btn-accent py-2 rounded-lg font-semibold">
                <i class="fas fa-save mr-2"></i> Enregistrer le paiement
            </button>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- SCRIPTS -->
<!-- ============================================ -->
<script>
    // Gestion des tabs
    function showTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
        });
        const content = document.getElementById('content-' + tabName);
        if (content) {
            content.classList.remove('hidden');
        }
        
        document.querySelectorAll('#tab-nav button').forEach(btn => {
            btn.className = 'tab-inactive px-6 py-3 text-sm font-medium transition';
        });
        const tabBtn = document.getElementById('tab-' + tabName);
        if (tabBtn) {
            tabBtn.className = 'tab-active px-6 py-3 text-sm font-medium transition';
        }
    }

    // Gestion des modals
    function openModal(modalId, param = null) {
        const modal = document.getElementById('modal-' + modalId);
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
        
        if (modalId === 'attribuer' && param) {
            document.getElementById('attribuer_etalage_id').value = param;
        }
    }
    
    function closeModal(modalId) {
        const modal = document.getElementById('modal-' + modalId);
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        }
    }

    // Fermer les modals en cliquant à l'extérieur
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
                this.classList.remove('flex');
                document.body.style.overflow = 'auto';
            }
        });
    });

    // ============================================
    // GESTION DES NOTIFICATIONS
    // ============================================
    
    function toggleNotifications() {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            dropdown.classList.toggle('show');
        }
    }
    
    function closeNotifications() {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            dropdown.classList.remove('show');
        }
    }
    
    // Marquer une notification comme lue avec AJAX
    function markAsRead(notifId) {
        fetch('/pages/Agent/ajax/mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + notifId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Supprimer la notification du menu déroulant
                const notifElement = document.querySelector(`.notification-item[data-id="${notifId}"]`);
                if (notifElement) {
                    notifElement.style.opacity = '0';
                    notifElement.style.transform = 'translateX(50px)';
                    setTimeout(() => {
                        notifElement.remove();
                        updateNotificationBadge();
                    }, 300);
                }
                // Mettre à jour l'onglet notifications
                const tabNotif = document.querySelector(`.notification-tab-item[data-id="${notifId}"]`);
                if (tabNotif) {
                    tabNotif.style.opacity = '0';
                    setTimeout(() => {
                        tabNotif.remove();
                        updateNotificationBadge();
                    }, 300);
                }
                // Recharger la page pour mettre à jour le badge
                setTimeout(() => {
                    location.reload();
                }, 500);
            }
        })
        .catch(error => console.error('Erreur:', error));
    }

    // Marquer toutes les notifications comme lues
    function markAllAsRead() {
        if (!confirm('Marquer toutes les notifications comme lues ?')) return;
        
        fetch('/pages/Agent/ajax/mark_all_read.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => console.error('Erreur:', error));
    }

    // Supprimer une notification
    function deleteNotification(notifId) {
        if (!confirm('Supprimer cette notification ?')) return;
        
        fetch('/pages/Agent/ajax/delete_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + notifId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notifElement = document.querySelector(`.notification-item[data-id="${notifId}"]`);
                if (notifElement) {
                    notifElement.remove();
                    updateNotificationBadge();
                }
                const tabNotif = document.querySelector(`.notification-tab-item[data-id="${notifId}"]`);
                if (tabNotif) {
                    tabNotif.remove();
                }
                location.reload();
            }
        })
        .catch(error => console.error('Erreur:', error));
    }

    // Ouvrir une notification (marquer comme lue et rediriger)
    function openNotification(notifId, lien) {
        if (lien) {
            fetch('/pages/Agent/ajax/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + notifId
            })
            .then(() => {
                window.location.href = lien;
            });
        }
    }

    // Mettre à jour le badge de notification
    function updateNotificationBadge() {
        const badge = document.querySelector('.notification-badge');
        const notifItems = document.querySelectorAll('.notification-item.unread');
        const count = notifItems.length;
        
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
        
        // Mettre à jour l'onglet notifications
        const tabNotifBadge = document.querySelector('#tab-notifications .ml-1');
        if (tabNotifBadge) {
            if (count > 0) {
                tabNotifBadge.textContent = count;
                tabNotifBadge.style.display = 'inline';
            } else {
                tabNotifBadge.style.display = 'none';
            }
        }
    }

    // Fermer les notifications en cliquant à l'extérieur
    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('notificationWrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            closeNotifications();
        }
    });

    // Actions pour les attributions (locations)
    function viewLocation(id) {
        alert('Affichage du détail de la location #' + id);
    }

    function editLocation(id) {
        alert('Modification de la location #' + id);
    }

    function renouvelerLocation(id) {
        if (confirm('Voulez-vous renouveler cette location ?')) {
            alert('Renouvellement de la location #' + id);
        }
    }

    function deleteLocation(id) {
        if (confirm('Êtes-vous sûr de vouloir supprimer cette location ? Cette action est irréversible.')) {
            alert('Suppression de la location #' + id);
        }
    }

    // Actions pour les commerçants
    function viewCommercant(id) {
        alert('Affichage du commerçant #' + id);
    }

    function editCommercant(id) {
        alert('Modification du commerçant #' + id);
    }

    function deleteCommercant(id) {
        if (confirm('Êtes-vous sûr de vouloir supprimer ce commerçant ? Cette action est irréversible.')) {
            alert('Suppression du commerçant #' + id);
        }
    }

    // Actions pour les paiements
    function viewPaiement(id) {
        alert('Affichage du paiement #' + id);
    }

    function editPaiement(id) {
        alert('Modification du paiement #' + id);
    }

    function printRecu(id) {
        alert('Impression du reçu pour le paiement #' + id);
    }

    function deletePaiement(id) {
        if (confirm('Êtes-vous sûr de vouloir supprimer ce paiement ? Cette action est irréversible.')) {
            alert('Suppression du paiement #' + id);
        }
    }

    // Générer un rapport
    function generateReport() {
        alert('Génération du rapport des paiements');
    }

    // Libérer un étalage
    function libererEtalage(id) {
        if (confirm('Êtes-vous sûr de vouloir libérer cet étalage ?')) {
            window.location.href = '/api/agent/liberer_etalage.php?id=' + id;
        }
    }

    // Modifier un étalage
    function editEtalage(id) {
        alert('Fonctionnalité à venir: Modification de l\'étalage #' + id);
    }

    // Recherche et filtre
    document.getElementById('searchEtalage')?.addEventListener('input', function() {
        const search = this.value.toLowerCase();
        document.querySelectorAll('#etalageGrid .etalage-card').forEach(card => {
            const text = card.textContent.toLowerCase();
            card.style.display = text.includes(search) ? '' : 'none';
        });
    });

    document.getElementById('filterSecteur')?.addEventListener('change', function() {
        const secteur = this.value;
        document.querySelectorAll('#etalageGrid .etalage-card').forEach(card => {
            if (!secteur || card.dataset.secteur === secteur) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });

    // Graphiques
    document.addEventListener('DOMContentLoaded', function() {
        // Graphique des étalages
        const ctx1 = document.getElementById('etalageChart')?.getContext('2d');
        if (ctx1) {
            new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: ['Disponibles', 'Occupés'],
                    datasets: [{
                        data: [<?= $stats['etalages_disponibles'] ?? 0 ?>, <?= $stats['etalages_occupes'] ?? 0 ?>],
                        backgroundColor: ['#22c55e', '#ef4444'],
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

        // Graphique des revenus
        const ctx2 = document.getElementById('revenueChart')?.getContext('2d');
        if (ctx2) {
            new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin'],
                    datasets: [{
                        label: 'Revenus (FCFA)',
                        data: [125000, 180000, 220000, 195000, 250000, <?= $stats['revenus_mois'] ?? 0 ?>],
                        backgroundColor: '#f59e0b',
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
                                callback: function(value) {
                                    return value.toLocaleString() + ' FCFA';
                                }
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