<?php
session_start();
require_once __DIR__ . '/../../Classes/Commercant.php';
require_once __DIR__ . '/../../Classes/Produit.php';
require_once __DIR__ . '/../../Classes/Database.php';

// Vérifier si l'utilisateur est connecté
$commercant = new Commercant();
if (!$commercant->isLoggedIn()) {
    header('Location: /pages/Client/login.php');
    exit;
}

// Récupérer les données du commerçant connecté
$user = $commercant->getLoggedInUser();
if (!$user) {
    session_destroy();
    header('Location: ../../login.php?error=session_expired');
    exit;
}

$id_commercant = $user->getIdCommercant();
$id_user = $user->getIdUser();

$db = Database::getInstance()->getConnection();

// =============================================
// GESTION DES NOTIFICATIONS
// =============================================

// Marquer une notification comme lue
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $id_notif = intval($_GET['mark_read']);
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id_notification = ? AND id_commercant = ?");
    $stmt->execute([$id_notif, $id_commercant]);
    header('Location: dashboard.php');
    exit;
}

// Marquer toutes les notifications comme lues
if (isset($_GET['mark_all_read'])) {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id_commercant = ?");
    $stmt->execute([$id_commercant]);
    header('Location: dashboard.php');
    exit;
}

// Supprimer une notification
if (isset($_GET['delete_notif']) && is_numeric($_GET['delete_notif'])) {
    $id_notif = intval($_GET['delete_notif']);
    $stmt = $db->prepare("DELETE FROM notifications WHERE id_notification = ? AND id_commercant = ?");
    $stmt->execute([$id_notif, $id_commercant]);
    header('Location: dashboard.php');
    exit;
}

// Récupérer les notifications
$stmt = $db->prepare("
    SELECT * FROM notifications 
    WHERE id_commercant = ? 
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute([$id_commercant]);
$notifications = $stmt->fetchAll();

// Compter les notifications non lues
$stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE id_commercant = ? AND is_read = 0");
$stmt->execute([$id_commercant]);
$unread_count = $stmt->fetch()['total'];

// Récupérer les 5 dernières notifications non lues
$stmt = $db->prepare("
    SELECT * FROM notifications 
    WHERE id_commercant = ? AND is_read = 0 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$id_commercant]);
$recent_notifications = $stmt->fetchAll();

// Traitement du formulaire d'ajout de produit
$message = '';
$message_type = '';
$produit_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $produitObj = new Produit();
    
    $data = [
        'nom_produit' => trim($_POST['nom_produit'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'prix_unitaire' => floatval($_POST['prix_unitaire'] ?? 0),
        'quantite_stock' => intval($_POST['quantite_stock'] ?? 0),
        'unite' => trim($_POST['unite'] ?? 'pièce'),
        'id_commercant' => $id_commercant
    ];
    
    if (empty($data['nom_produit'])) {
        $message = '❌ Le nom du produit est requis';
        $message_type = 'error';
        $produit_data = (object) $data;
    } elseif ($data['prix_unitaire'] <= 0) {
        $message = '❌ Le prix unitaire doit être supérieur à 0';
        $message_type = 'error';
        $produit_data = (object) $data;
    } else {
        $result = $produitObj->create($data);
        
        if ($result['success']) {
            $message = '✅ ' . $result['message'];
            $message_type = 'success';
            $produit_data = null;
            $produits = $commercant->getProduits($id_commercant);
        } else {
            $message = '❌ ' . $result['error'];
            $message_type = 'error';
            $produit_data = (object) $data;
        }
    }
}

// Récupérer les données
$etalages = $commercant->getEtalages($id_commercant);
$locations = $commercant->getLocations($id_commercant);
$produits = $commercant->getProduits($id_commercant);
$paiements = $commercant->getPaiements($id_commercant);

// Récupérer tous les étalages disponibles
$stmt = $db->prepare("
    SELECT e.*, s.designation as secteur_nom 
    FROM etalage e
    LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
    WHERE e.statut = 'disponible' OR e.id_commercant IS NULL
    ORDER BY e.numero
");
$stmt->execute();
$etalages_disponibles = $stmt->fetchAll();

// Unités disponibles
$unites_disponibles = [
    'pièce' => 'Pièce',
    'kg' => 'Kilogramme (kg)',
    'g' => 'Gramme (g)',
    'litre' => 'Litre',
    'sac' => 'Sac',
    'botte' => 'Botte',
    'douzaine' => 'Douzaine',
    'carton' => 'Carton',
    'bouteille' => 'Bouteille',
    'paquet' => 'Paquet'
];

// Statistiques
$total_etalages = count($etalages);
$total_produits = count($produits);
$total_locations = count($locations);
$total_paiements = array_sum(array_column($paiements, 'montant'));

// En haut du dashboard.php, après la connexion
// Récupérer les notifications non lues
$stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE id_commercant = ? AND is_read = 0");
$stmt->execute([$id_commercant]);
$unread_count = $stmt->fetch()['total'];

// Récupérer les 5 dernières notifications non lues
$stmt = $db->prepare("
    SELECT * FROM notifications 
    WHERE id_commercant = ? AND is_read = 0 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute([$id_commercant]);
$recent_notifications = $stmt->fetchAll();

$page_title = 'Dashboard - Marché Virunga';
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
        .border-accent { border-color: #f59e0b; }
        
        .btn-accent { background: #f59e0b; color: white; transition: all 0.3s ease; }
        .btn-accent:hover { background: #d97706; transform: scale(1.02); }
        .btn-primary { background: #1e3a5f; color: white; transition: all 0.3s ease; }
        .btn-primary:hover { background: #2d6a9f; transform: scale(1.02); }
        .btn-outline { border: 2px solid #1e3a5f; color: #1e3a5f; transition: all 0.3s ease; }
        .btn-outline:hover { background: #1e3a5f; color: white; }
        .btn-danger { background: #ef4444; color: white; transition: all 0.3s ease; }
        .btn-danger:hover { background: #dc2626; transform: scale(1.02); }
        
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        
        .product-card { transition: all 0.3s ease; }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        
        .etalage-card { transition: all 0.3s ease; }
        .etalage-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        
        .tab-active { border-bottom: 3px solid #f59e0b; color: #1e3a5f; font-weight: 600; }
        .tab-inactive { color: #6b7280; border-bottom: 3px solid transparent; }
        .tab-inactive:hover { color: #1e3a5f; border-bottom-color: #d1d5db; }
        
        .modal-overlay { background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        
        .toast {
            animation: slideInRight 0.5s ease forwards;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .input-focus:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
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
        
        /* Scrollbar personnalisée pour les notifications */
        .notification-dropdown::-webkit-scrollbar { width: 4px; }
        .notification-dropdown::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .notification-dropdown::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 10px; }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #d97706; }
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
                <span class="text-xs bg-accent/20 px-2 py-1 rounded-full">Commerçant</span>
            </div>
            <div class="flex items-center space-x-4">
                <div class="hidden md:flex items-center space-x-2">
                    <span class="text-sm">
                        <i class="fas fa-user mr-1"></i>
                        <?= htmlspecialchars($user->getNomComplet()) ?>
                    </span>
                    <span class="text-xs bg-white/20 px-2 py-1 rounded-full">
                        <?= htmlspecialchars($user->getMatricule()) ?>
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
                                    <a href="?mark_all_read=1" class="text-xs text-accent hover:text-accent/80 transition">
                                        <i class="fas fa-check-double mr-1"></i> Tout marquer lu
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="notification-list">
                            <?php if (count($recent_notifications) > 0): ?>
                                <?php foreach ($recent_notifications as $notif): ?>
                                    <div class="notification-item unread">
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
                                                    <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?>
                                                </p>
                                            </div>
                                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                                <?php if ($notif['lien']): ?>
                                                    <a href="<?= $notif['lien'] ?>" class="text-xs text-accent hover:text-accent/80">
                                                        <i class="fas fa-arrow-right"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="?mark_read=<?= $notif['id_notification'] ?>" class="text-xs text-gray-400 hover:text-gray-600" title="Marquer comme lu">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="?delete_notif=<?= $notif['id_notification'] ?>" class="text-xs text-gray-400 hover:text-red-600" title="Supprimer" onclick="return confirm('Supprimer cette notification ?')">
                                                    <i class="fas fa-times"></i>
                                                </a>
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
                                    <a href="#tab-locations" onclick="showTab('locations'); closeNotifications();" class="text-xs text-accent hover:text-accent/80 transition">
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

    <!-- En-tête avec bienvenue -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border-l-4 border-accent">
        <div class="flex flex-wrap justify-between items-center">
            <div>
                <p class="text-sm text-gray-500">Bonjour,</p>
                <h1 class="text-2xl font-bold text-primary">
                    <?= htmlspecialchars($user->getNomComplet()) ?>
                </h1>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-id-card mr-1"></i>
                    Matricule: <?= htmlspecialchars($user->getMatricule()) ?>
                    <?php if ($user->getProduitsVendu()): ?>
                        <span class="ml-3 bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full text-xs">
                            <i class="fas fa-boxes mr-1"></i>
                            <?= htmlspecialchars($user->getProduitsVendu()) ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex flex-wrap gap-2 mt-2 sm:mt-0">
                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm flex items-center">
                    <i class="fas fa-check-circle mr-1"></i> Compte actif
                </span>
                <?php if ($commercant->hasActiveLocation($id_commercant)): ?>
                    <span class="bg-accent/20 text-accent-700 px-3 py-1 rounded-full text-sm flex items-center">
                        <i class="fas fa-store mr-1"></i> Location active
                    </span>
                <?php endif; ?>
                <?php if ($unread_count > 0): ?>
                    <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-sm flex items-center animate-pulse">
                        <i class="fas fa-bell mr-1"></i> <?= $unread_count ?> notification(s)
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Message de notification -->
    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg flex items-start toast <?= $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mt-0.5 mr-3 text-lg"></i>
            <div><?= $message ?></div>
            <button onclick="this.parentElement.style.display='none'" class="ml-auto text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Mes étalages</p>
                    <p class="text-2xl font-bold text-primary"><?= $total_etalages ?></p>
                </div>
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-warehouse text-blue-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Mes produits</p>
                    <p class="text-2xl font-bold text-primary"><?= $total_produits ?></p>
                </div>
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-boxes text-green-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Locations</p>
                    <p class="text-2xl font-bold text-primary"><?= $total_locations ?></p>
                </div>
                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-handshake text-yellow-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Total payé</p>
                    <p class="text-2xl font-bold text-accent"><?= number_format($total_paiements, 0, ',', ' ') ?> FCFA</p>
                </div>
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-coins text-red-600"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="border-b border-gray-200">
            <nav class="flex flex-wrap -mb-px" id="tab-nav">
                <button onclick="showTab('etalages')" class="tab-active px-6 py-3 text-sm font-medium transition" id="tab-etalages">
                    <i class="fas fa-warehouse mr-2"></i>Mes étalages
                </button>
                <button onclick="showTab('produits')" class="tab-inactive px-6 py-3 text-sm font-medium transition" id="tab-produits">
                    <i class="fas fa-boxes mr-2"></i>Mes produits
                </button>
                <button onclick="showTab('disponibles')" class="tab-inactive px-6 py-3 text-sm font-medium transition" id="tab-disponibles">
                    <i class="fas fa-store mr-2"></i>Étalages disponibles
                </button>
                <button onclick="showTab('locations')" class="tab-inactive px-6 py-3 text-sm font-medium transition" id="tab-locations">
                    <i class="fas fa-handshake mr-2"></i>Mes locations
                </button>
                <?php if ($unread_count > 0): ?>
                    <button onclick="showTab('notifications')" class="tab-inactive px-6 py-3 text-sm font-medium transition relative" id="tab-notifications">
                        <i class="fas fa-bell mr-2"></i>Notifications
                        <span class="ml-1 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full"><?= $unread_count ?></span>
                    </button>
                <?php endif; ?>
            </nav>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB 1: MES ÉTALAGES -->
    <!-- ============================================ -->
    <div id="content-etalages" class="tab-content">
        <?php if (count($etalages) > 0): ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($etalages as $etalage): ?>
                    <div class="etalage-card bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                        <div class="p-5">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-bold text-primary text-lg">
                                        Étalage #<?= htmlspecialchars($etalage['numero']) ?>
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        <i class="fas fa-map-pin mr-1"></i>
                                        <?= htmlspecialchars($etalage['localisation'] ?? 'Non spécifiée') ?>
                                    </p>
                                </div>
                                <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-xs font-semibold">
                                    <i class="fas fa-check-circle mr-1"></i> Occupé
                                </span>
                            </div>
                            <div class="space-y-2 text-sm">
                                <p class="text-gray-600">
                                    <i class="fas fa-tag text-accent mr-2"></i>
                                    Secteur: <?= htmlspecialchars($etalage['secteur_nom'] ?? 'Non défini') ?>
                                </p>
                                <p class="text-gray-600">
                                    <i class="fas fa-calendar text-accent mr-2"></i>
                                    Statut: <span class="font-medium text-green-600">Actif</span>
                                </p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-gray-100 flex gap-2">
                                <button onclick="viewEtalage(<?= $etalage['id_etalage'] ?>)" 
                                        class="flex-1 btn-primary text-sm py-1.5 rounded-lg">
                                    <i class="fas fa-eye mr-1"></i> Voir
                                </button>
                                <button onclick="editEtalage(<?= $etalage['id_etalage'] ?>)" 
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
                <p class="text-gray-500 mb-4">Vous n'avez pas encore d'étalage. Consultez les étalages disponibles.</p>
                <button onclick="showTab('disponibles')" class="btn-accent px-6 py-2 rounded-lg font-semibold">
                    <i class="fas fa-search mr-2"></i> Voir les étalages disponibles
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- TAB 2: MES PRODUITS -->
    <!-- ============================================ -->
    <div id="content-produits" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-boxes text-accent mr-2"></i>Mes produits
            </h2>
            <button onclick="openModal('addProduct')" class="btn-accent px-4 py-2 rounded-lg font-semibold flex items-center">
                <i class="fas fa-plus mr-2"></i> Ajouter un produit
            </button>
        </div>

        <?php if (count($produits) > 0): ?>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($produits as $produit): ?>
                    <div class="product-card bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-bold text-primary"><?= htmlspecialchars($produit['nom_produit']) ?></h4>
                            <?php if ($produit['quantite_stock'] > 0): ?>
                                <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-xs">En stock</span>
                            <?php else: ?>
                                <span class="bg-red-100 text-red-700 px-2 py-0.5 rounded-full text-xs">Rupture</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-gray-600 line-clamp-2"><?= htmlspecialchars($produit['description'] ?? '') ?></p>
                        <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center">
                            <div>
                                <span class="text-lg font-bold text-accent"><?= number_format($produit['prix_unitaire'], 0, ',', ' ') ?> FCFA</span>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars($produit['unite'] ?? 'pièce') ?></p>
                            </div>
                            <div class="flex gap-1">
                                <a href="modifier_produit.php?id=<?= $produit['id_produit'] ?>" 
                                   class="text-blue-600 hover:text-blue-800 p-1 transition" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="supprimer_produit.php?id=<?= $produit['id_produit'] ?>" 
                                   class="text-red-600 hover:text-red-800 p-1 transition" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        <div class="mt-2 text-xs text-gray-400">
                            Stock: <?= $produit['quantite_stock'] ?> <?= htmlspecialchars($produit['unite'] ?? '') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-boxes"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun produit</h3>
                <p class="text-gray-500 mb-4">Commencez à ajouter vos produits à vendre.</p>
                <button onclick="openModal('addProduct')" class="btn-accent px-6 py-2 rounded-lg font-semibold">
                    <i class="fas fa-plus mr-2"></i> Ajouter un produit
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- TAB 3: ÉTALAGES DISPONIBLES -->
    <!-- ============================================ -->
    <div id="content-disponibles" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-store text-accent mr-2"></i>Étalages disponibles à la location
            </h2>
            <span class="text-sm text-gray-500"><?= count($etalages_disponibles) ?> étalages disponibles</span>
        </div>

        <?php if (count($etalages_disponibles) > 0): ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($etalages_disponibles as $etalage): ?>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden border-2 border-accent/30">
                        <div class="p-5">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-bold text-primary text-lg">
                                        Étalage #<?= htmlspecialchars($etalage['numero']) ?>
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        <i class="fas fa-map-pin mr-1"></i>
                                        <?= htmlspecialchars($etalage['localisation'] ?? 'Non spécifiée') ?>
                                    </p>
                                </div>
                                <span class="bg-accent/20 text-accent-700 px-2 py-0.5 rounded-full text-xs font-semibold animate-pulse">
                                    <i class="fas fa-circle mr-1 text-accent"></i> Disponible
                                </span>
                            </div>
                            <div class="space-y-2 text-sm">
                                <p class="text-gray-600">
                                    <i class="fas fa-tag text-accent mr-2"></i>
                                    Secteur: <?= htmlspecialchars($etalage['secteur_nom'] ?? 'Non défini') ?>
                                </p>
                                <p class="text-gray-600">
                                    <i class="fas fa-check-circle text-accent mr-2"></i>
                                    Prêt à être loué
                                </p>
                            </div>
                            <a href="confirmer_demande.php?id=<?= $etalage['id_etalage'] ?>" 
                               class="w-full mt-4 btn-accent py-2 rounded-lg font-semibold inline-block text-center">
                                <i class="fas fa-handshake mr-2"></i> Demander la location
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-store"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun étalage disponible</h3>
                <p class="text-gray-500">Tous les étalages sont actuellement occupés. Revenez plus tard.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- TAB 4: MES LOCATIONS -->
    <!-- ============================================ -->
    <div id="content-locations" class="tab-content hidden">
        <h2 class="text-xl font-bold text-primary mb-4">
            <i class="fas fa-handshake text-accent mr-2"></i>Mes locations
        </h2>

        <?php if (count($locations) > 0): ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
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
                            <?php foreach ($locations as $location): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        #<?= htmlspecialchars($location['etalage_numero']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-bold text-accent">
                                        <?= number_format($location['montant_location'], 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= date('d/m/Y', strtotime($location['date_debut'])) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= date('d/m/Y', strtotime($location['date_fin'])) ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if (strtotime($location['date_fin']) >= time()): ?>
                                            <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-xs font-semibold">
                                                <i class="fas fa-check-circle mr-1"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full text-xs font-semibold">
                                                <i class="fas fa-clock mr-1"></i> Expirée
                                            </span>
                                        <?php endif; ?>
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
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucune location</h3>
                <p class="text-gray-500">Vous n'avez pas encore de location active.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- TAB 5: TOUTES LES NOTIFICATIONS -->
    <!-- ============================================ -->
  <!-- Menu déroulant des notifications -->
<div id="content-notifications" class="tab-content hidden">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-primary">
            <i class="fas fa-bell text-accent mr-2"></i>Toutes les notifications
        </h2>
        <div class="flex gap-2">
            <?php if ($unread_count > 0): ?>
                <button onclick="markAllAsRead()" class="btn-outline px-3 py-1 rounded-lg text-sm font-semibold">
                    <i class="fas fa-check-double mr-1"></i> Tout marquer lu
                </button>
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
<!-- MODAL: AJOUTER UN PRODUIT -->
<!-- ============================================ -->
<div id="modal-addProduct" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-primary">
                <i class="fas fa-plus-circle text-accent mr-2"></i>Ajouter un produit
            </h3>
            <button onclick="closeModal('addProduct')" class="text-gray-400 hover:text-gray-600 text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="form-addProduct" method="POST" action="">
            <input type="hidden" name="action" value="add_product">
            <input type="hidden" name="id_commercant" value="<?= $id_commercant ?>">
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom du produit <span class="text-red-500">*</span></label>
                <input type="text" name="nom_produit" required 
                       value="<?= htmlspecialchars($produit_data->nom_produit ?? '') ?>"
                       placeholder="Ex: Tomates fraîches"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg input-focus focus:outline-none">
            </div>
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="2" 
                          placeholder="Décrivez votre produit..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg input-focus focus:outline-none"><?= htmlspecialchars($produit_data->description ?? '') ?></textarea>
            </div>
            
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prix (FCFA) <span class="text-red-500">*</span></label>
                    <input type="number" name="prix_unitaire" required min="0" step="100"
                           value="<?= htmlspecialchars($produit_data->prix_unitaire ?? '') ?>"
                           placeholder="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg input-focus focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantité en stock</label>
                    <input type="number" name="quantite_stock" min="0" 
                           value="<?= htmlspecialchars($produit_data->quantite_stock ?? 0) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg input-focus focus:outline-none">
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Unité de mesure</label>
                <select name="unite" class="w-full px-3 py-2 border border-gray-300 rounded-lg input-focus focus:outline-none">
                    <?php foreach ($unites_disponibles as $value => $label): ?>
                        <option value="<?= $value ?>" <?= (($produit_data->unite ?? 'pièce') == $value) ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="w-full btn-accent py-2 rounded-lg font-semibold">
                <i class="fas fa-save mr-2"></i> Ajouter le produit
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
    function openModal(modalId) {
        document.getElementById('modal-' + modalId).classList.remove('hidden');
        document.getElementById('modal-' + modalId).classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal(modalId) {
        document.getElementById('modal-' + modalId).classList.add('hidden');
        document.getElementById('modal-' + modalId).classList.remove('flex');
        document.body.style.overflow = 'auto';
    }
    
    // ============================================
    // GESTION DES NOTIFICATIONS
    // ============================================
    function toggleNotifications() {
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.classList.toggle('show');
    }
    
    function closeNotifications() {
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.classList.remove('show');
    }
    
    // Fermer les notifications en cliquant à l'extérieur
    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('notificationWrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            closeNotifications();
        }
    });
    
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
    
    // Voir un étalage
    function viewEtalage(id) {
        alert('Fonctionnalité à venir: Visualisation de l\'étalage #' + id);
    }
    
    // Modifier un étalage
    function editEtalage(id) {
        alert('Fonctionnalité à venir: Modification de l\'étalage #' + id);
    }
    
    // Soumission du formulaire produit
    document.getElementById('form-addProduct').addEventListener('submit', function(e) {
        const nom = this.querySelector('input[name="nom_produit"]').value.trim();
        const prix = this.querySelector('input[name="prix_unitaire"]').value;
        
        if (!nom) {
            e.preventDefault();
            alert('❌ Veuillez entrer un nom de produit.');
            this.querySelector('input[name="nom_produit"]').focus();
            return false;
        }
        if (!prix || parseFloat(prix) <= 0) {
            e.preventDefault();
            alert('❌ Veuillez entrer un prix valide.');
            this.querySelector('input[name="prix_unitaire"]').focus();
            return false;
        }
        if (nom.length < 2) {
            e.preventDefault();
            alert('❌ Le nom doit contenir au moins 2 caractères.');
            this.querySelector('input[name="nom_produit"]').focus();
            return false;
        }
    });

    // ============================================
// GESTION AVANCÉE DES NOTIFICATIONS
// ============================================

// Marquer une notification comme lue avec AJAX
function markAsRead(notifId) {
    fetch('/pages/Commercant/ajax/mark_notification_read.php', {
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
        }
    })
    .catch(error => console.error('Erreur:', error));
}

// Marquer toutes les notifications comme lues
function markAllAsRead() {
    if (!confirm('Marquer toutes les notifications comme lues ?')) return;
    
    fetch('/pages/Commercant/ajax/mark_all_read.php', {
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

// Supprimer une notification
function deleteNotification(notifId) {
    if (!confirm('Supprimer cette notification ?')) return;
    
    fetch('/pages/Commercant/ajax/delete_notification.php', {
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
        }
    })
    .catch(error => console.error('Erreur:', error));
}

// Ouvrir une notification (marquer comme lue et rediriger)
function openNotification(notifId, lien) {
    if (lien) {
        // Marquer comme lue avant de rediriger
        fetch('/pages/Commercant/ajax/mark_notification_read.php', {
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

// Auto-fermeture du dropdown après clic
document.addEventListener('click', function(e) {
    const wrapper = document.getElementById('notificationWrapper');
    if (wrapper && !wrapper.contains(e.target)) {
        closeNotifications();
    }
});

// Auto-disparition des notifications après 5 secondes (optionnel)
function autoDismissNotifications() {
    const notifItems = document.querySelectorAll('.notification-item.unread');
    notifItems.forEach((item, index) => {
        setTimeout(() => {
            const notifId = item.dataset.id;
            if (notifId) {
                markAsRead(notifId);
            }
        }, 5000 + (index * 1000)); // 5s + 1s par notification
    });
}

// Appeler autoDismissNotifications au chargement (optionnel)
// document.addEventListener('DOMContentLoaded', autoDismissNotifications);
    
    // Si un message de succès est affiché, fermer le modal automatiquement
    <?php if ($message_type === 'success'): ?>
        setTimeout(function() {
            closeModal('addProduct');
        }, 1000);
    <?php endif; ?>
</script>

</body>
</html>