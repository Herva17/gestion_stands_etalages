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
// GESTION DES DEMANDES DE LOCATION
// =============================================

// Valider une demande
if (isset($_GET['valider']) && is_numeric($_GET['valider'])) {
    $id_location = intval($_GET['valider']);
    
    try {
        $db->beginTransaction();
        
        // Mettre à jour le statut de la location
        $stmt = $db->prepare("UPDATE location SET status = 'approuve' WHERE id_location = ?");
        $stmt->execute([$id_location]);
        
        // Récupérer les informations pour la notification
        $stmt = $db->prepare("
            SELECT l.id_commercant, e.numero as etalage_numero
            FROM location l
            INNER JOIN etalage e ON l.id_etalage = e.id_etalage
            WHERE l.id_location = ?
        ");
        $stmt->execute([$id_location]);
        $info = $stmt->fetch();
        
        if ($info) {
            // Mettre à jour le statut de l'étalage
            $stmt = $db->prepare("
                UPDATE etalage SET statut = 'occupe' 
                WHERE id_etalage = (SELECT id_etalage FROM location WHERE id_location = ?)
            ");
            $stmt->execute([$id_location]);
            
            // Notifier le commerçant
            $stmt = $db->prepare("
                INSERT INTO notifications (id_commercant, type, title, message, lien) 
                VALUES (?, 'success', '✅ Demande approuvée', 
                        'Votre demande pour l\'étalage #" . $info['etalage_numero'] . " a été approuvée. Vous pouvez maintenant occuper votre étalage.',
                        '/pages/Commercant/dashboard.php#tab-locations')
            ");
            $stmt->execute([$info['id_commercant']]);
        }
        
        $db->commit();
        header('Location: dashboard.php?success=demande_validee');
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: dashboard.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Refuser une demande
if (isset($_GET['refuser']) && is_numeric($_GET['refuser'])) {
    $id_location = intval($_GET['refuser']);
    
    try {
        $db->beginTransaction();
        
        // Mettre à jour le statut de la location
        $stmt = $db->prepare("UPDATE location SET status = 'refuse' WHERE id_location = ?");
        $stmt->execute([$id_location]);
        
        // Récupérer les informations pour la notification
        $stmt = $db->prepare("
            SELECT l.id_commercant, e.numero as etalage_numero
            FROM location l
            INNER JOIN etalage e ON l.id_etalage = e.id_etalage
            WHERE l.id_location = ?
        ");
        $stmt->execute([$id_location]);
        $info = $stmt->fetch();
        
        if ($info) {
            // Remettre l'étalage disponible
            $stmt = $db->prepare("
                UPDATE etalage SET statut = 'disponible' 
                WHERE id_etalage = (SELECT id_etalage FROM location WHERE id_location = ?)
            ");
            $stmt->execute([$id_location]);
            
            // Notifier le commerçant
            $stmt = $db->prepare("
                INSERT INTO notifications (id_commercant, type, title, message, lien) 
                VALUES (?, 'error', '❌ Demande refusée', 
                        'Votre demande pour l\'étalage #" . $info['etalage_numero'] . " a été refusée. Veuillez contacter l\'administration pour plus d\'informations.',
                        '/pages/Commercant/dashboard.php#tab-locations')
            ");
            $stmt->execute([$info['id_commercant']]);
        }
        
        $db->commit();
        header('Location: dashboard.php?success=demande_refusee');
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: dashboard.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// Libérer un étalage
if (isset($_GET['liberer']) && is_numeric($_GET['liberer'])) {
    $id_etalage = intval($_GET['liberer']);
    
    try {
        $db->beginTransaction();
        
        // Mettre à jour le statut de l'étalage
        $stmt = $db->prepare("UPDATE etalage SET statut = 'disponible' WHERE id_etalage = ?");
        $stmt->execute([$id_etalage]);
        
        // Mettre à jour la location associée
        $stmt = $db->prepare("UPDATE location SET status = 'termine' WHERE id_etalage = ? AND status = 'actif'");
        $stmt->execute([$id_etalage]);
        
        $db->commit();
        header('Location: dashboard.php?success=etalage_libere');
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: dashboard.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

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

// Récupérer les demandes en attente
$stmt = $db->prepare("
    SELECT l.*, e.numero as etalage_numero, e.localisation, s.designation as secteur_nom,
           u.nom_complet as commercant_nom, u.matricule as commercant_matricule,
           u.telephone as commercant_telephone
    FROM location l
    INNER JOIN etalage e ON l.id_etalage = e.id_etalage
    LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
    INNER JOIN commercant c ON l.id_commercant = c.id_commercant
    INNER JOIN utilisateurs u ON c.id_user = u.id_user
    WHERE l.status = 'en_attente'
    ORDER BY l.created_at DESC
");
$stmt->execute();
$demandes_attente = $stmt->fetchAll();
$nb_demandes_attente = count($demandes_attente);

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
$etalages_payes = [];
$etalages_attente_paiement = [];
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

// =============================================
// RÉCUPÉRATION DES ÉTALAGES PAR STATUT DE PAIEMENT
// =============================================

// Récupérer les étalages occupés (payés)
try {
    $stmt = $db->prepare("
        SELECT DISTINCT e.*, 
               s.designation as secteur_nom,
               u.nom_complet as commercant_nom,
               l.id_location, l.date_debut, l.date_fin, l.montant_location,
               p.id_paiement as paiement_id
        FROM etalage e
        INNER JOIN location l ON e.id_etalage = l.id_etalage
        LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
        INNER JOIN commercant c ON l.id_commercant = c.id_commercant
        INNER JOIN utilisateurs u ON c.id_user = u.id_user
        INNER JOIN paiement p ON l.id_location = p.id_location
        WHERE e.statut = 'occupe'
        AND l.status = 'actif'
        GROUP BY e.id_etalage
        ORDER BY e.numero
    ");
    $etalages_payes = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des étalages payés: " . $e->getMessage());
    $etalages_payes = [];
}

// Récupérer les étalages occupés (payés)
try {
    $stmt = $db->prepare("
        SELECT DISTINCT e.*, 
               s.designation as secteur_nom,
               u.nom_complet as commercant_nom,
               l.id_location, l.date_debut, l.date_fin, l.montant_location,
               p.id_paiement as paiement_id,
               p.montant as paiement_montant,
               p.mode_paiement,
               p.date_paiement,
               p.reference as paiement_reference
        FROM etalage e
        INNER JOIN location l ON e.id_etalage = l.id_etalage
        LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
        INNER JOIN commercant c ON l.id_commercant = c.id_commercant
        INNER JOIN utilisateurs u ON c.id_user = u.id_user
        INNER JOIN paiement p ON l.id_location = p.id_location
        WHERE e.statut = 'occupe'
        AND l.status = 'actif'
        AND p.statut = 'valide'
        GROUP BY e.id_etalage
        ORDER BY e.numero
    ");
    $etalages_payes = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des étalages payés: " . $e->getMessage());
    $etalages_payes = [];
}

// Récupérer les étalages en attente de paiement (loués mais non payés)
try {
    $stmt = $db->prepare("
        SELECT DISTINCT e.*, 
               s.designation as secteur_nom,
               u.nom_complet as commercant_nom,
               l.id_location, l.date_debut, l.date_fin, l.montant_location
        FROM etalage e
        INNER JOIN location l ON e.id_etalage = l.id_etalage
        LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
        INNER JOIN commercant c ON l.id_commercant = c.id_commercant
        INNER JOIN utilisateurs u ON c.id_user = u.id_user
        LEFT JOIN paiement p ON l.id_location = p.id_location AND p.statut = 'valide'
        WHERE e.statut = 'occupe'
        AND l.statut = 'actif'
        AND p.id_paiement IS NULL
        GROUP BY e.id_etalage
        ORDER BY e.numero
    ");
    $etalages_attente_paiement = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Erreur lors de la récupération des étalages en attente de paiement: " . $e->getMessage());
    $etalages_attente_paiement = [];
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
if (!isset($etalages_payes)) $etalages_payes = [];
if (!isset($etalages_attente_paiement)) $etalages_attente_paiement = [];
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
        .status-paye { background: #dcfce7; color: #166534; border: 1px solid #22c55e; }
        .status-attente-paiement { background: #fef3c7; color: #92400e; border: 1px solid #f59e0b; }
        
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

        /* Styles des notifications */
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

<!-- Navigation -->
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
                
                <!-- Icône de notification -->
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
                                    <a href="dashboard.php?mark_all_read=1" class="text-xs text-accent hover:text-accent/80 transition">
                                        <i class="fas fa-check-double mr-1"></i> Tout marquer lu
                                    </a>
                                <?php endif; ?>
                                <button onclick="closeNotifications()" class="text-xs text-gray-400 hover:text-gray-600">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="notification-list">
                            <?php if (count($recent_notifications) > 0): ?>
                                <?php foreach ($recent_notifications as $notif): ?>
                                    <div class="notification-item unread" data-id="<?= $notif['id_notification'] ?>">
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
                                                <a href="dashboard.php?mark_read=<?= $notif['id_notification'] ?>" 
                                                   class="text-xs text-gray-400 hover:text-green-600 transition" title="Marquer comme lu">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="dashboard.php?delete_notif=<?= $notif['id_notification'] ?>" 
                                                   class="text-xs text-gray-400 hover:text-red-600 transition" title="Supprimer"
                                                   onclick="return confirm('Supprimer cette notification ?')">
                                                    <i class="fas fa-trash"></i>
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

<!-- Contenu principal -->
<div class="max-w-7xl mx-auto px-4 py-6">

    <!-- Messages de succès/erreur -->
    <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
            <span>
                <?php if ($_GET['success'] == 'demande_validee'): ?>
                    <i class="fas fa-check-circle mr-2"></i> Demande validée avec succès !
                <?php elseif ($_GET['success'] == 'demande_refusee'): ?>
                    <i class="fas fa-check-circle mr-2"></i> Demande refusée avec succès !
                <?php elseif ($_GET['success'] == 'etalage_libere'): ?>
                    <i class="fas fa-check-circle mr-2"></i> Étalage libéré avec succès !
                <?php elseif ($_GET['success'] == 'paiement_enregistre'): ?>
                    <i class="fas fa-check-circle mr-2"></i> Paiement enregistré avec succès !
                <?php endif; ?>
            </span>
            <button onclick="this.parentElement.remove()" class="text-green-700 hover:text-green-900">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center justify-between">
            <span>
                <i class="fas fa-exclamation-circle mr-2"></i> Erreur: <?= htmlspecialchars($_GET['error']) ?>
            </span>
            <button onclick="this.parentElement.remove()" class="text-red-700 hover:text-red-900">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

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
                    <?php if ($nb_demandes_attente > 0): ?>
                        <span class="ml-3 bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full text-xs animate-pulse">
                            <i class="fas fa-clock mr-1"></i> <?= $nb_demandes_attente ?> demande(s) en attente
                        </span>
                    <?php endif; ?>
                    <?php if (count($etalages_attente_paiement) > 0): ?>
                        <span class="ml-3 bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full text-xs animate-pulse">
                            <i class="fas fa-coins mr-1"></i> <?= count($etalages_attente_paiement) ?> paiement(s) en attente
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex flex-wrap gap-2 mt-2 sm:mt-0">
                <a href="ajouter_etalage.php" class="btn-accent px-4 py-2 rounded-lg font-semibold flex items-center text-sm">
                    <i class="fas fa-plus mr-2"></i> Ajouter un étalage
                </a>
                <a href="ajouter_secteur.php" class="btn-primary px-4 py-2 rounded-lg font-semibold flex items-center text-sm">
                    <i class="fas fa-layer-group mr-2"></i> Ajouter un secteur
                </a>
                <?php if (count($etalages_attente_paiement) > 0): ?>
                    <a href="#tab-attente_paiement" onclick="showTab('attente_paiement')" 
                       class="btn-warning px-4 py-2 rounded-lg font-semibold flex items-center text-sm">
                        <i class="fas fa-clock mr-2"></i> 
                        <?= count($etalages_attente_paiement) ?> paiement(s) en attente
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-6">
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
                    <p class="text-xs text-gray-500">En attente paiement</p>
                    <p class="text-2xl font-bold text-yellow-600"><?= count($etalages_attente_paiement) ?></p>
                </div>
                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Occupés payés</p>
                    <p class="text-2xl font-bold text-green-600"><?= count($etalages_payes) ?></p>
                </div>
                <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-store text-emerald-600"></i>
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
                    <p class="text-xs text-gray-500">Demandes</p>
                    <p class="text-2xl font-bold text-orange-600"><?= $nb_demandes_attente ?></p>
                </div>
                <div class="w-10 h-10 bg-orange-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-clock text-orange-600"></i>
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
                <button onclick="showTab('attente_paiement')" class="tab-inactive px-6 py-3 text-sm font-medium transition whitespace-nowrap" id="tab-attente_paiement">
                    <i class="fas fa-clock text-yellow-500 mr-2"></i>En attente paiement
                    <?php if (count($etalages_attente_paiement) > 0): ?>
                        <span class="ml-1 bg-yellow-500 text-white text-xs px-1.5 py-0.5 rounded-full"><?= count($etalages_attente_paiement) ?></span>
                    <?php endif; ?>
                </button>
                <button onclick="showTab('payes')" class="tab-inactive px-6 py-3 text-sm font-medium transition whitespace-nowrap" id="tab-payes">
                    <i class="fas fa-check-circle text-green-500 mr-2"></i>Occupés payés
                    <?php if (count($etalages_payes) > 0): ?>
                        <span class="ml-1 bg-green-500 text-white text-xs px-1.5 py-0.5 rounded-full"><?= count($etalages_payes) ?></span>
                    <?php endif; ?>
                </button>
                <button onclick="showTab('occupes')" class="tab-inactive px-6 py-3 text-sm font-medium transition whitespace-nowrap" id="tab-occupes">
                    <i class="fas fa-store mr-2"></i>Tous les occupés
                </button>
                <button onclick="showTab('demandes')" class="tab-inactive px-6 py-3 text-sm font-medium transition whitespace-nowrap" id="tab-demandes">
                    <i class="fas fa-clock mr-2"></i>Demandes en attente
                    <?php if ($nb_demandes_attente > 0): ?>
                        <span class="ml-1 bg-yellow-500 text-white text-xs px-1.5 py-0.5 rounded-full"><?= $nb_demandes_attente ?></span>
                    <?php endif; ?>
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

    <!-- TAB 1: TOUS LES ÉTALAGES -->
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
                <a href="ajouter_etalage.php" class="btn-accent px-4 py-1 rounded-lg font-semibold text-sm flex items-center">
                    <i class="fas fa-plus mr-1"></i> Ajouter
                </a>
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
                                    <a href="attribuer_etalage.php?id=<?= $e['id_etalage'] ?>" 
                                       class="flex-1 btn-accent text-sm py-1.5 rounded-lg font-semibold text-center">
                                        <i class="fas fa-handshake mr-1"></i> Attribuer
                                    </a>
                                <?php else: ?>
                                    <a href="dashboard.php?liberer=<?= $e['id_etalage'] ?>" 
                                       class="flex-1 btn-danger text-sm py-1.5 rounded-lg font-semibold text-center"
                                       onclick="return confirm('Êtes-vous sûr de vouloir libérer cet étalage ?')">
                                        <i class="fas fa-unlock mr-1"></i> Libérer
                                    </a>
                                <?php endif; ?>
                                <a href="modifier_etalage.php?id=<?= $e['id_etalage'] ?>" 
                                   class="flex-1 btn-outline text-sm py-1.5 rounded-lg text-center">
                                    <i class="fas fa-edit mr-1"></i> Modifier
                                </a>
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
                <a href="ajouter_etalage.php" class="btn-accent mt-4 px-6 py-2 rounded-lg font-semibold inline-block">
                    <i class="fas fa-plus mr-2"></i> Ajouter un étalage
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 2: ÉTALAGES DISPONIBLES -->
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
                            <a href="attribuer_etalage.php?id=<?= $e['id_etalage'] ?>" 
                               class="block w-full mt-3 btn-accent py-2 rounded-lg font-semibold text-center">
                                <i class="fas fa-handshake mr-2"></i> Attribuer à un commerçant
                            </a>
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

    <!-- TAB: ÉTALAGES EN ATTENTE DE PAIEMENT -->
    <div id="content-attente_paiement" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-clock text-yellow-500 mr-2"></i>Étalages en attente de paiement
            </h2>
            <span class="text-sm text-gray-500"><?= count($etalages_attente_paiement) ?> étalage(s) en attente de paiement</span>
        </div>

        <?php if (count($etalages_attente_paiement) > 0): ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($etalages_attente_paiement as $e): ?>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden border-2 border-yellow-400">
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
                                <span class="status-badge status-attente-paiement">
                                    <i class="fas fa-clock mr-1"></i> En attente
                                </span>
                            </div>
                            <div class="space-y-1 text-sm">
                                <p class="text-gray-600">
                                    <i class="fas fa-user text-yellow-500 mr-1"></i>
                                    Commerçant: <?= htmlspecialchars($e['commercant_nom'] ?? 'Inconnu') ?>
                                </p>
                                <p class="text-gray-600">
                                    <i class="fas fa-tag text-yellow-500 mr-1"></i>
                                    Secteur: <?= htmlspecialchars($e['secteur_nom'] ?? 'Non défini') ?>
                                </p>
                                <p class="text-gray-600">
                                    <i class="fas fa-calendar text-yellow-500 mr-1"></i>
                                    Début: <?= date('d/m/Y', strtotime($e['date_debut'])) ?>
                                </p>
                                <p class="text-gray-600">
                                    <i class="fas fa-calendar text-yellow-500 mr-1"></i>
                                    Fin: <?= date('d/m/Y', strtotime($e['date_fin'])) ?>
                                </p>
                                <p class="text-gray-600 font-semibold text-yellow-600">
                                    <i class="fas fa-money-bill-wave mr-1"></i>
                                    Montant: <?= number_format($e['montant_location'], 0, ',', ' ') ?> FCFA
                                </p>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-200 flex gap-2">
                                <a href="enregistrer_paiement.php?location_id=<?= $e['id_location'] ?>" 
                                   class="flex-1 btn-accent text-sm py-1.5 rounded-lg font-semibold text-center">
                                    <i class="fas fa-coins mr-1"></i> Enregistrer paiement
                                </a>
                                <a href="voir_location.php?id=<?= $e['id_location'] ?>" 
                                   class="flex-1 btn-outline text-sm py-1.5 rounded-lg text-center">
                                    <i class="fas fa-eye mr-1"></i> Voir
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-check-circle text-green-500"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun étalage en attente de paiement</h3>
                <p class="text-gray-500">Tous les paiements sont à jour.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB: ÉTALAGES OCCUPÉS PAYÉS -->
    <div id="content-payes" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-check-circle text-green-500 mr-2"></i>Étalages occupés et payés
            </h2>
            <span class="text-sm text-gray-500"><?= count($etalages_payes) ?> étalage(s) payé(s)</span>
        </div>

        <?php if (count($etalages_payes) > 0): ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($etalages_payes as $e): ?>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden border-2 border-green-400">
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
                                <span class="status-badge status-paye">
                                    <i class="fas fa-check-circle mr-1"></i> Payé
                                </span>
                            </div>
                            <div class="space-y-1 text-sm">
                                <p class="text-gray-600">
                                    <i class="fas fa-user text-green-500 mr-1"></i>
                                    Commerçant: <?= htmlspecialchars($e['commercant_nom'] ?? 'Inconnu') ?>
                                </p>
                                <p class="text-gray-600">
                                    <i class="fas fa-tag text-green-500 mr-1"></i>
                                    Secteur: <?= htmlspecialchars($e['secteur_nom'] ?? 'Non défini') ?>
                                </p>
                                <p class="text-gray-600">
                                    <i class="fas fa-calendar text-green-500 mr-1"></i>
                                    Début: <?= date('d/m/Y', strtotime($e['date_debut'])) ?>
                                </p>
                                <p class="text-gray-600">
                                    <i class="fas fa-calendar text-green-500 mr-1"></i>
                                    Fin: <?= date('d/m/Y', strtotime($e['date_fin'])) ?>
                                </p>
                                <p class="text-gray-600 font-semibold text-green-600">
                                    <i class="fas fa-money-bill-wave mr-1"></i>
                                    Montant: <?= number_format($e['montant_location'], 0, ',', ' ') ?> FCFA
                                </p>
                                <?php if ($e['paiement_id']): ?>
                                    <p class="text-xs text-gray-500">
                                        <i class="fas fa-receipt mr-1"></i>
                                        Paiement #<?= $e['paiement_id'] ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-200 flex gap-2">
                                <a href="dashboard.php?liberer=<?= $e['id_etalage'] ?>" 
                                   class="flex-1 btn-danger text-sm py-1.5 rounded-lg font-semibold text-center"
                                   onclick="return confirm('Êtes-vous sûr de vouloir libérer cet étalage ?')">
                                    <i class="fas fa-unlock mr-1"></i> Libérer
                                </a>
                                <a href="voir_location.php?id=<?= $e['id_location'] ?>" 
                                   class="flex-1 btn-outline text-sm py-1.5 rounded-lg text-center">
                                    <i class="fas fa-eye mr-1"></i> Voir
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-store"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun étalage occupé payé</h3>
                <p class="text-gray-500">Aucun étalage n'est actuellement occupé avec paiement enregistré.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 3: ÉTALAGES OCCUPÉS -->
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
                            <a href="dashboard.php?liberer=<?= $e['id_etalage'] ?>" 
                               class="block w-full mt-3 btn-danger py-2 rounded-lg font-semibold text-center"
                               onclick="return confirm('Êtes-vous sûr de vouloir libérer cet étalage ?')">
                                <i class="fas fa-unlock mr-2"></i> Libérer l'étalage
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
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun étalage occupé</h3>
                <p class="text-gray-500">Tous les étalages sont disponibles.</p>
            </div>
        <?php endif; ?>
    </div>
        <!-- TAB 4: DEMANDES EN ATTENTE -->
    <div id="content-demandes" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-clock text-accent mr-2"></i>Demandes de location en attente
            </h2>
            <span class="text-sm text-gray-500"><?= $nb_demandes_attente ?> demande(s) en attente</span>
        </div>

        <?php if ($nb_demandes_attente > 0): ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Commerçant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Étalage</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Secteur</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Durée</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($demandes_attente as $demande): ?>
                                <tr class="table-row-hover">
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= date('d/m/Y H:i', strtotime($demande['created_at'])) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <div>
                                            <p class="font-medium text-gray-800"><?= htmlspecialchars($demande['commercant_nom']) ?></p>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($demande['commercant_matricule']) ?></p>
                                            <p class="text-xs text-gray-500">📞 <?= htmlspecialchars($demande['commercant_telephone'] ?? 'Non renseigné') ?></p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        #<?= htmlspecialchars($demande['etalage_numero']) ?>
                                        <p class="text-xs text-gray-500"><?= htmlspecialchars($demande['localisation'] ?? '') ?></p>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= htmlspecialchars($demande['secteur_nom'] ?? 'Non défini') ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-bold text-accent">
                                        <?= number_format($demande['montant_location'], 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= htmlspecialchars($demande['duree_location']) ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex gap-2">
                                            <a href="dashboard.php?valider=<?= $demande['id_location'] ?>" 
                                               class="btn-success text-white px-3 py-1 rounded-lg text-sm font-semibold flex items-center"
                                               onclick="return confirm('Confirmez-vous la validation de cette demande de location ?')">
                                                <i class="fas fa-check mr-1"></i> Valider
                                            </a>
                                            <a href="dashboard.php?refuser=<?= $demande['id_location'] ?>" 
                                               class="btn-danger text-white px-3 py-1 rounded-lg text-sm font-semibold flex items-center"
                                               onclick="return confirm('Confirmez-vous le refus de cette demande de location ?')">
                                                <i class="fas fa-times mr-1"></i> Refuser
                                            </a>
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
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucune demande en attente</h3>
                <p class="text-gray-500">Toutes les demandes ont été traitées.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 5: ATTRIBUTIONS -->
    <div id="content-attributions" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-handshake text-accent mr-2"></i>Historique des attributions
            </h2>
            <a href="nouvelle_location.php" class="btn-accent px-4 py-2 rounded-lg font-semibold text-sm">
                <i class="fas fa-plus mr-2"></i> Nouvelle attribution
            </a>
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
                                            <a href="voir_location.php?id=<?= $loc['id_location'] ?>" 
                                               class="action-btn btn-info text-white" title="Voir">
                                                <i class="fas fa-eye text-xs"></i>
                                            </a>
                                            <?php if (strtotime($loc['date_fin']) >= time()): ?>
                                                <a href="modifier_location.php?id=<?= $loc['id_location'] ?>" 
                                                   class="action-btn btn-warning text-white" title="Modifier">
                                                    <i class="fas fa-edit text-xs"></i>
                                                </a>
                                                <a href="renouveler_location.php?id=<?= $loc['id_location'] ?>" 
                                                   class="action-btn btn-success text-white" title="Renouveler"
                                                   onclick="return confirm('Voulez-vous renouveler cette location ?')">
                                                    <i class="fas fa-sync text-xs"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="supprimer_location.php?id=<?= $loc['id_location'] ?>" 
                                               class="action-btn btn-danger text-white" title="Supprimer"
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette location ?')">
                                                <i class="fas fa-trash text-xs"></i>
                                            </a>
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
                <a href="nouvelle_location.php" class="btn-accent mt-4 px-6 py-2 rounded-lg font-semibold inline-block">
                    <i class="fas fa-plus mr-2"></i> Nouvelle attribution
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 6: COMMERÇANTS -->
    <div id="content-commercants" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-users text-accent mr-2"></i>Liste des commerçants
            </h2>
            <a href="ajouter_commercant.php" class="btn-accent px-4 py-2 rounded-lg font-semibold text-sm">
                <i class="fas fa-user-plus mr-2"></i> Ajouter un commerçant
            </a>
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
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun commerçant</h3>
                <p class="text-gray-500">Aucun commerçant n'est encore enregistré.</p>
                <a href="ajouter_commercant.php" class="btn-accent mt-4 px-6 py-2 rounded-lg font-semibold inline-block">
                    <i class="fas fa-user-plus mr-2"></i> Ajouter un commerçant
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 7: PAIEMENTS -->
    <div id="content-paiements" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-coins text-accent mr-2"></i>Historique des paiements
            </h2>
            <div class="flex gap-2">
                <a href="enregistrer_paiement.php" class="btn-accent px-4 py-2 rounded-lg font-semibold text-sm">
                    <i class="fas fa-plus mr-2"></i> Enregistrer un paiement
                </a>
                <a href="rapport_paiements.php" class="btn-primary px-4 py-2 rounded-lg font-semibold text-sm">
                    <i class="fas fa-file-pdf mr-2"></i> Rapport
                </a>
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
                                            <a href="voir_paiement.php?id=<?= $p['id_paiement'] ?>" 
                                               class="action-btn btn-info text-white" title="Voir">
                                                <i class="fas fa-eye text-xs"></i>
                                            </a>
                                            <a href="modifier_paiement.php?id=<?= $p['id_paiement'] ?>" 
                                               class="action-btn btn-warning text-white" title="Modifier">
                                                <i class="fas fa-edit text-xs"></i>
                                            </a>
                                            <a href="imprimer_recu.php?id=<?= $p['id_paiement'] ?>" 
                                               class="action-btn btn-primary text-white" title="Imprimer" target="_blank">
                                                <i class="fas fa-print text-xs"></i>
                                            </a>
                                            <a href="supprimer_paiement.php?id=<?= $p['id_paiement'] ?>" 
                                               class="action-btn btn-danger text-white" title="Supprimer"
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce paiement ?')">
                                                <i class="fas fa-trash text-xs"></i>
                                            </a>
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
                <a href="enregistrer_paiement.php" class="btn-accent mt-4 px-6 py-2 rounded-lg font-semibold inline-block">
                    <i class="fas fa-plus mr-2"></i> Enregistrer un paiement
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 8: NOTIFICATIONS -->
    <div id="content-notifications" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-bell text-accent mr-2"></i>Toutes les notifications
            </h2>
            <div class="flex gap-2">
                <?php if ($unread_count > 0): ?>
                    <a href="dashboard.php?mark_all_read=1" class="btn-outline px-3 py-1 rounded-lg text-sm font-semibold">
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
                                            <a href="dashboard.php?mark_read=<?= $notif['id_notification'] ?>" 
                                               class="text-xs text-gray-400 hover:text-green-600 transition" title="Marquer comme lu">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="dashboard.php?delete_notif=<?= $notif['id_notification'] ?>" 
                                               class="text-xs text-gray-400 hover:text-red-600 transition" title="Supprimer"
                                               onclick="return confirm('Supprimer cette notification ?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
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

<!-- Scripts -->
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

    // Gestion des notifications
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

    // Fermer les notifications en cliquant à l'extérieur
    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('notificationWrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            closeNotifications();
        }
    });

    // Recherche et filtre
    const searchInput = document.getElementById('searchEtalage');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const search = this.value.toLowerCase();
            document.querySelectorAll('#etalageGrid .etalage-card').forEach(card => {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(search) ? '' : 'none';
            });
        });
    }

    const filterSelect = document.getElementById('filterSecteur');
    if (filterSelect) {
        filterSelect.addEventListener('change', function() {
            const secteur = this.value;
            document.querySelectorAll('#etalageGrid .etalage-card').forEach(card => {
                if (!secteur || card.dataset.secteur === secteur) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }

    // Graphiques
    document.addEventListener('DOMContentLoaded', function() {
        // Graphique des étalages avec les nouvelles catégories
        const ctx1 = document.getElementById('etalageChart')?.getContext('2d');
        if (ctx1) {
            new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: ['Disponibles', 'En attente paiement', 'Occupés payés'],
                    datasets: [{
                        data: [
                            <?= $stats['etalages_disponibles'] ?? 0 ?>, 
                            <?= count($etalages_attente_paiement) ?>, 
                            <?= count($etalages_payes) ?>
                        ],
                        backgroundColor: ['#22c55e', '#f59e0b', '#3b82f6'],
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