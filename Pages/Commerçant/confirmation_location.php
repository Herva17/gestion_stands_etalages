<?php
session_start();
require_once __DIR__ . '/../../Classes/Commercant.php';
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
    header('Location: /pages/Client/login.php?error=session_expired');
    exit;
}

$id_commercant = $user->getIdCommercant();

// Vérifier si un ID de location est passé
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /pages/Commercant/dashboard.php?error=invalid_location');
    exit;
}

$id_location = intval($_GET['id']);

// Récupérer les détails de la location
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("
    SELECT 
        l.*,
        e.numero as etalage_numero,
        e.localisation as etalage_localisation,
        s.designation as secteur_nom,
        u.nom_complet as commercant_nom,
        u.matricule as commercant_matricule,
        u.email as commercant_email,
        u.telephone as commercant_telephone
    FROM location l
    INNER JOIN etalage e ON l.id_etalage = e.id_etalage
    LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
    INNER JOIN commercant c ON l.id_commercant = c.id_commercant
    INNER JOIN utilisateurs u ON c.id_user = u.id_user
    WHERE l.id_location = ? AND l.id_commercant = ?
");
$stmt->execute([$id_location, $id_commercant]);
$location = $stmt->fetch();

if (!$location) {
    header('Location: /pages/Commercant/dashboard.php?error=location_not_found');
    exit;
}

// Déterminer le statut
$status = $location['status'] ?? 'en_attente';
$status_classes = [
    'en_attente' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
    'approuve' => 'bg-green-100 text-green-800 border-green-200',
    'refuse' => 'bg-red-100 text-red-800 border-red-200',
    'annule' => 'bg-gray-100 text-gray-800 border-gray-200'
];
$status_icones = [
    'en_attente' => 'fa-clock',
    'approuve' => 'fa-check-circle',
    'refuse' => 'fa-times-circle',
    'annule' => 'fa-ban'
];
$status_libelles = [
    'en_attente' => 'En attente de validation',
    'approuve' => 'Approuvé ✅',
    'refuse' => 'Refusé ❌',
    'annule' => 'Annulé'
];

// Calculer la durée en mois
$date1 = new DateTime($location['date_debut']);
$date2 = new DateTime($location['date_fin']);
$interval = $date1->diff($date2);
$mois = ($interval->y * 12) + $interval->m;
$jours = $interval->d;

$page_title = 'Confirmation de location - Marché Virunga';
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
        .btn-success { background: #22c55e; color: white; transition: all 0.3s ease; }
        .btn-success:hover { background: #16a34a; transform: scale(1.02); }
        .btn-danger { background: #ef4444; color: white; transition: all 0.3s ease; }
        .btn-danger:hover { background: #dc2626; transform: scale(1.02); }
        
        .confirmation-card {
            border-left: 4px solid #f59e0b;
        }
        
        .status-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        .fade-in {
            animation: fadeIn 0.8s ease forwards;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .slide-in {
            animation: slideIn 0.5s ease forwards;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .checkmark {
            animation: checkmark 0.8s ease forwards;
        }
        
        @keyframes checkmark {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.2);
                opacity: 1;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .confirmation-number {
            font-size: 2rem;
            font-weight: 800;
            color: #1e3a5f;
            letter-spacing: 2px;
        }
        
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
                <a href="/pages/Commercant/logout.php" class="text-sm bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition flex items-center">
                    <i class="fas fa-sign-out-alt mr-1"></i>
                    <span class="hidden sm:inline">Déconnexion</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Contenu principal -->
<div class="max-w-4xl mx-auto px-4 py-8">

    <!-- En-tête -->
    <div class="flex items-center justify-between mb-6 fade-in">
        <div>
            <h1 class="text-2xl font-bold text-primary">
                <i class="fas fa-check-circle text-accent mr-2"></i>Confirmation de location
            </h1>
            <p class="text-gray-500 text-sm mt-1">
                Récapitulatif de votre demande de location.
            </p>
        </div>
        <a href="/pages/Commercant/dashboard.php#tab-locations" 
           class="btn-outline px-4 py-2 rounded-lg text-sm font-semibold flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Retour
        </a>
    </div>

    <!-- Carte de confirmation -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden confirmation-card fade-in">
        <div class="p-6 md:p-8">
            
            <!-- En-tête avec statut -->
            <div class="flex flex-wrap items-center justify-between mb-6 pb-4 border-b border-gray-200">
                <div class="flex items-center">
                    <div class="w-16 h-16 bg-accent/10 rounded-full flex items-center justify-center mr-4 checkmark">
                        <i class="fas fa-handshake text-accent text-3xl"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">
                            Demande #<?= str_pad($id_location, 6, '0', STR_PAD_LEFT) ?>
                        </h2>
                        <p class="text-sm text-gray-500">
                            <i class="fas fa-calendar mr-1"></i>
                            <?= date('d/m/Y à H:i', strtotime($location['created_at'] ?? date('Y-m-d H:i:s'))) ?>
                        </p>
                    </div>
                </div>
                <div class="mt-2 sm:mt-0">
                    <span class="px-4 py-2 rounded-full text-sm font-semibold inline-flex items-center <?= $status_classes[$status] ?? 'bg-gray-100 text-gray-800' ?> status-badge">
                        <i class="fas <?= $status_icones[$status] ?? 'fa-info-circle' ?> mr-2"></i>
                        <?= $status_libelles[$status] ?? ucfirst($status) ?>
                    </span>
                </div>
            </div>

            <!-- Numéro de confirmation -->
            <div class="bg-accent/5 rounded-xl p-4 mb-6 text-center border border-accent/20">
                <p class="text-xs text-gray-500 uppercase tracking-wider">Numéro de confirmation</p>
                <p class="confirmation-number">LOC-<?= str_pad($id_location, 6, '0', STR_PAD_LEFT) ?></p>
                <p class="text-xs text-gray-400 mt-1">Conservez ce numéro pour toute référence</p>
            </div>

            <!-- Informations du demandeur -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 slide-in">
                <div class="bg-gray-50 rounded-xl p-4">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                        <i class="fas fa-user mr-1"></i> Demandeur
                    </h3>
                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($location['commercant_nom']) ?></p>
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-id-card mr-1"></i>
                        Matricule: <?= htmlspecialchars($location['commercant_matricule']) ?>
                    </p>
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-envelope mr-1"></i>
                        <?= htmlspecialchars($location['commercant_email']) ?>
                    </p>
                    <?php if ($location['commercant_telephone']): ?>
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-phone mr-1"></i>
                            <?= htmlspecialchars($location['commercant_telephone']) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="bg-gray-50 rounded-xl p-4">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                        <i class="fas fa-store mr-1"></i> Étalage
                    </h3>
                    <p class="font-semibold text-gray-800">
                        Étalage #<?= htmlspecialchars($location['etalage_numero']) ?>
                    </p>
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-tag mr-1"></i>
                        Secteur: <?= htmlspecialchars($location['secteur_nom'] ?? 'Non défini') ?>
                    </p>
                    <?php if ($location['etalage_localisation']): ?>
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-map-pin mr-1"></i>
                            <?= htmlspecialchars($location['etalage_localisation']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Détails de la location -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 slide-in">
                <div class="bg-green-50 rounded-xl p-4 text-center">
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Date de début</p>
                    <p class="text-lg font-bold text-green-700">
                        <?= date('d/m/Y', strtotime($location['date_debut'])) ?>
                    </p>
                </div>
                <div class="bg-red-50 rounded-xl p-4 text-center">
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Date de fin</p>
                    <p class="text-lg font-bold text-red-700">
                        <?= date('d/m/Y', strtotime($location['date_fin'])) ?>
                    </p>
                </div>
                <div class="bg-accent/10 rounded-xl p-4 text-center">
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Montant mensuel</p>
                    <p class="text-lg font-bold text-accent">
                        <?= number_format($location['montant_location'], 0, ',', ' ') ?> FCFA
                    </p>
                </div>
            </div>

            <!-- Durée et coût total -->
            <div class="bg-blue-50 rounded-xl p-4 mb-6 slide-in">
                <div class="flex flex-wrap items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wider">Durée totale de la location</p>
                        <p class="text-lg font-bold text-blue-800">
                            <?= $mois ?> mois <?= $jours > 0 ? 'et ' . $jours . ' jours' : '' ?>
                            <span class="text-sm font-normal text-gray-500 ml-2">
                                (<?= htmlspecialchars($location['duree_location']) ?>)
                            </span>
                        </p>
                    </div>
                    <div class="text-right mt-2 sm:mt-0">
                        <p class="text-xs text-gray-500 uppercase tracking-wider">Coût total estimé</p>
                        <p class="text-lg font-bold text-blue-800">
                            <?= number_format($location['montant_location'] * $mois, 0, ',', ' ') ?> FCFA
                        </p>
                    </div>
                </div>
            </div>

            <!-- Commentaire -->
            <?php if ($location['commentaire']): ?>
                <div class="bg-gray-50 rounded-xl p-4 mb-6 slide-in">
                    <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">
                        <i class="fas fa-comment mr-1"></i> Commentaire
                    </p>
                    <p class="text-gray-700"><?= htmlspecialchars($location['commentaire']) ?></p>
                </div>
            <?php endif; ?>

            <!-- Prochaines étapes -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6 slide-in">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-yellow-600 mt-0.5 mr-3 text-lg"></i>
                    <div>
                        <h4 class="font-semibold text-yellow-800 text-sm">Prochaines étapes</h4>
                        <ul class="text-sm text-yellow-700 mt-1 space-y-1">
                            <li>• Un agent du marché va examiner votre demande</li>
                            <li>• Vous serez contacté dans les 48 heures</li>
                            <li>• Un contrat de location vous sera proposé</li>
                            <li>• Le paiement se fait à la signature du contrat</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Boutons d'action -->
            <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200">
                <a href=" dashboard.php#tab-locations" 
                   class="flex-1 btn-primary py-3 rounded-lg font-semibold text-center flex items-center justify-center">
                    <i class="fas fa-list mr-2"></i> Voir toutes mes locations
                </a>
                <a href="confirmer_demande.php" 
                   class="flex-1 btn-accent py-3 rounded-lg font-semibold text-center flex items-center justify-center">
                    <i class="fas fa-plus mr-2"></i> Faire une autre demande
                </a>
                <?php if ($status === 'en_attente'): ?>
                    <button onclick="window.print()" 
                            class="flex-1 btn-outline py-3 rounded-lg font-semibold text-center flex items-center justify-center">
                        <i class="fas fa-print mr-2"></i> Imprimer
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Informations de contact -->
    <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4 fade-in">
        <div class="bg-white rounded-xl shadow-sm p-4 text-center">
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-shield-alt text-green-600 text-xl"></i>
            </div>
            <h4 class="font-semibold text-gray-800 text-sm">Sécurisé</h4>
            <p class="text-xs text-gray-500">Votre demande est enregistrée en toute sécurité</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center">
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-clock text-blue-600 text-xl"></i>
            </div>
            <h4 class="font-semibold text-gray-800 text-sm">Rapide</h4>
            <p class="text-xs text-gray-500">Réponse sous 48 heures maximum</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm p-4 text-center">
            <div class="w-12 h-12 bg-accent/20 rounded-full flex items-center justify-center mx-auto mb-2">
                <i class="fas fa-headset text-accent text-xl"></i>
            </div>
            <h4 class="font-semibold text-gray-800 text-sm">Support</h4>
            <p class="text-xs text-gray-500">Une équipe est à votre disposition</p>
        </div>
    </div>

</div>

<!-- ============================================ -->
<!-- SCRIPTS -->
<!-- ============================================ -->
<script>
    // Fonction pour imprimer la confirmation
    function imprimerConfirmation() {
        window.print();
    }
    
    // Ajouter un écouteur pour la touche Échap (fermer)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.location.href = '/pages/Commercant/dashboard.php#tab-locations';
        }
    });
    
    // Animation des éléments au scroll
    document.addEventListener('DOMContentLoaded', function() {
        const elements = document.querySelectorAll('.slide-in');
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateX(0)';
                }
            });
        }, {
            threshold: 0.1
        });
        
        elements.forEach(element => {
            element.style.opacity = '0';
            element.style.transform = 'translateX(-20px)';
            observer.observe(element);
        });
    });
</script>

<style>
    @media print {
        .no-print {
            display: none !important;
        }
        .confirmation-card {
            border: 1px solid #ddd;
            box-shadow: none !important;
        }
        .status-badge {
            animation: none !important;
        }
        body {
            background: white !important;
        }
        nav {
            display: none !important;
        }
        .btn-accent, .btn-primary, .btn-outline {
            display: none !important;
        }
        .confirmation-number {
            font-size: 2.5rem !important;
        }
    }
</style>

</body>
</html>