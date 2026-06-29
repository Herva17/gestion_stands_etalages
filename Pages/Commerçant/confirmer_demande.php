<?php
session_start();
require_once __DIR__ . '/../../Classes/Commercant.php';
require_once __DIR__ . '/../../Classes/Database.php';

// Vérifier si l'utilisateur est connecté
$commercant = new Commercant();
if (!$commercant->isLoggedIn()) {
    header('Location: ../../login.php');
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

// Récupérer les informations du commerçant
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("
    SELECT c.*, u.nom_complet, u.matricule, u.email, u.telephone
    FROM commercant c
    INNER JOIN utilisateurs u ON c.id_user = u.id_user
    WHERE c.id_commercant = ?
");
$stmt->execute([$id_commercant]);
$commercant_info = $stmt->fetch();

if (!$commercant_info) {
    header('Location: dashboard.php?error=commercant_not_found');
    exit;
}

// Vérifier si un ID d'étalage est passé
$id_etalage = null;
$etalage_info = null;
$message = '';
$message_type = '';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_etalage = intval($_GET['id']);
    
    $stmt = $db->prepare("
        SELECT e.*, s.designation as secteur_nom
        FROM etalage e
        LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
        WHERE e.id_etalage = ? AND (e.statut = 'disponible' OR e.id_commercant IS NULL)
    ");
    $stmt->execute([$id_etalage]);
    $etalage_info = $stmt->fetch();
    
    if (!$etalage_info) {
        header('Location: dashboard.php?error=etalage_not_available#tab-disponibles');
        exit;
    }
}

// =============================================
// FONCTION POUR AJOUTER UNE NOTIFICATION
// =============================================
function addNotification($db, $id_commercant, $type, $title, $message, $lien = null) {
    $stmt = $db->prepare("
        INSERT INTO notifications (id_commercant, type, title, message, lien) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$id_commercant, $type, $title, $message, $lien]);
    return $db->lastInsertId();
}

// =============================================
// TRAITEMENT DU FORMULAIRE DE DEMANDE
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_location'])) {
    $id_etalage = intval($_POST['id_etalage'] ?? 0);
    $duree = intval($_POST['duree'] ?? 1);
    $date_debut = $_POST['date_debut'] ?? date('Y-m-d');
    $montant_location = floatval($_POST['montant_location'] ?? 0);
    $commentaire = trim($_POST['commentaire'] ?? '');
    
    // Validation
    $errors = [];
    if ($id_etalage <= 0) {
        $errors[] = 'Veuillez sélectionner un étalage valide.';
    }
    if ($duree <= 0) {
        $errors[] = 'La durée de location doit être d\'au moins 1 mois.';
    }
    if ($montant_location <= 0) {
        $errors[] = 'Le montant de la location est invalide.';
    }
    
    if (empty($errors)) {
        // Vérifier que l'étalage est toujours disponible
        $stmt = $db->prepare("
            SELECT id_etalage, numero FROM etalage 
            WHERE id_etalage = ? AND (statut = 'disponible' OR id_commercant IS NULL)
        ");
        $stmt->execute([$id_etalage]);
        $etalage_data = $stmt->fetch();
        
        if (!$etalage_data) {
            $message = '❌ Cet étalage n\'est plus disponible.';
            $message_type = 'error';
        } else {
            // Calculer la date de fin
            $date_fin = date('Y-m-d', strtotime($date_debut . ' + ' . $duree . ' months'));
            
            try {
                $db->beginTransaction();
                
                // Insérer la location
                $stmt = $db->prepare("
                    INSERT INTO location (
                        id_etalage, 
                        id_commercant, 
                        date_debut, 
                        date_fin, 
                        montant_location, 
                        duree_location,
                        status,
                        commentaire,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'en_attente', ?, NOW())
                ");
                $stmt->execute([
                    $id_etalage,
                    $id_commercant,
                    $date_debut,
                    $date_fin,
                    $montant_location,
                    $duree . ' mois',
                    $commentaire
                ]);
                
                $id_location = $db->lastInsertId();
                
                // Mettre à jour le statut de l'étalage
                $stmt = $db->prepare("
                    UPDATE etalage SET statut = 'en_attente' WHERE id_etalage = ?
                ");
                $stmt->execute([$id_etalage]);
                
                // =============================================
                // ✅ AJOUT DE LA NOTIFICATION POUR LE COMMERÇANT
                // =============================================
                $notification_title = '📋 Demande de location en attente';
                $notification_message = sprintf(
                    'Votre demande pour l\'étalage #%s a été enregistrée avec succès. Un agent du marché va examiner votre demande et vous contactera sous 48h.',
                    $etalage_data['numero']
                );
                $notification_lien = ' confirmation_location.php?id=' . $id_location;
                
                addNotification(
                    $db,
                    $id_commercant,
                    'info',
                    $notification_title,
                    $notification_message,
                    $notification_lien
                );
                
                $db->commit();
                
                // Redirection vers la page de confirmation
                header('Location: confirmation_location.php?id=' . $id_location);
                exit;
                
            } catch (Exception $e) {
                $db->rollBack();
                $message = '❌ Une erreur est survenue : ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    } else {
        $message = '❌ ' . implode(' ', $errors);
        $message_type = 'error';
    }
}

// Récupérer les étalages disponibles
$stmt = $db->prepare("
    SELECT e.*, s.designation as secteur_nom 
    FROM etalage e
    LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
    WHERE e.statut = 'disponible' OR e.id_commercant IS NULL
    ORDER BY e.numero
");
$stmt->execute();
$etalages_disponibles = $stmt->fetchAll();

// Si aucun id_etalage n'est passé, prendre le premier disponible
if (!$id_etalage && count($etalages_disponibles) > 0) {
    $id_etalage = $etalages_disponibles[0]['id_etalage'];
    $stmt = $db->prepare("
        SELECT e.*, s.designation as secteur_nom
        FROM etalage e
        LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
        WHERE e.id_etalage = ?
    ");
    $stmt->execute([$id_etalage]);
    $etalage_info = $stmt->fetch();
}

$montant_suggere = 50000;
$page_title = 'Confirmer la demande - Marché Virunga';
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
        
        .form-card { transition: all 0.3s ease; }
        .form-card:hover { box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        
        .input-focus:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
        }
        
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
        
        .etalage-preview {
            border-left: 4px solid #f59e0b;
        }
        
        .info-box {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
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
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-primary">
                <i class="fas fa-handshake text-accent mr-2"></i>Confirmer la demande
            </h1>
            <p class="text-gray-500 text-sm mt-1">
                Remplissez le formulaire pour confirmer votre demande de location.
            </p>
        </div>
        <a href="/pages/Commercant/dashboard.php#tab-disponibles" 
           class="btn-outline px-4 py-2 rounded-lg text-sm font-semibold flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Retour
        </a>
    </div>

    <!-- Message d'erreur -->
    <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-lg flex items-start toast <?= $message_type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700' ?>">
            <i class="fas <?= $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mt-0.5 mr-3 text-lg"></i>
            <div><?= $message ?></div>
            <button onclick="this.parentElement.style.display='none'" class="ml-auto text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
    <?php endif; ?>

    <?php if (count($etalages_disponibles) > 0): ?>
    
        <!-- Informations du commerçant -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6 info-box">
            <div class="flex flex-wrap items-center justify-between">
                <div>
                    <p class="text-xs text-gray-600 uppercase tracking-wider">Demandeur</p>
                    <h3 class="text-lg font-bold text-primary"><?= htmlspecialchars($commercant_info['nom_complet']) ?></h3>
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-id-card mr-1"></i>
                        Matricule: <?= htmlspecialchars($commercant_info['matricule']) ?>
                        <span class="mx-2">•</span>
                        <i class="fas fa-envelope mr-1"></i>
                        <?= htmlspecialchars($commercant_info['email']) ?>
                        <span class="mx-2">•</span>
                        <i class="fas fa-phone mr-1"></i>
                        <?= htmlspecialchars($commercant_info['telephone'] ?? 'Non renseigné') ?>
                    </p>
                </div>
                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm flex items-center">
                    <i class="fas fa-check-circle mr-1"></i> Compte actif
                </span>
            </div>
        </div>

        <!-- Aperçu de l'étalage sélectionné -->
        <?php if ($etalage_info): ?>
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6 etalage-preview">
            <div class="flex flex-wrap items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 uppercase tracking-wider">Étalage sélectionné</p>
                    <h3 class="text-lg font-bold text-primary">
                        Étalage #<?= htmlspecialchars($etalage_info['numero']) ?>
                    </h3>
                    <p class="text-sm text-gray-500">
                        <i class="fas fa-map-pin mr-1"></i>
                        <?= htmlspecialchars($etalage_info['localisation'] ?? 'Non spécifiée') ?>
                        <span class="mx-2">•</span>
                        <i class="fas fa-tag mr-1"></i>
                        Secteur: <?= htmlspecialchars($etalage_info['secteur_nom'] ?? 'Non défini') ?>
                    </p>
                </div>
                <div class="flex gap-2 mt-2 sm:mt-0">
                    <span class="bg-accent/20 text-accent-700 px-3 py-1 rounded-full text-sm font-semibold">
                        <i class="fas fa-circle mr-1 text-accent"></i> Disponible
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Formulaire de confirmation -->
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden form-card border border-gray-100">
            <div class="p-6 md:p-8">
                <form method="POST" action="" class="space-y-6">
                    
                    <input type="hidden" name="submit_location" value="1">
                    
                    <!-- Sélection de l'étalage -->
                    <div>
                        <label for="id_etalage" class="block text-sm font-medium text-gray-700 mb-1">
                            Choisir un étalage <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-store text-gray-400"></i>
                            </div>
                            <select id="id_etalage" 
                                    name="id_etalage" 
                                    required
                                    class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition appearance-none bg-white">
                                <option value="">-- Sélectionnez un étalage --</option>
                                <?php foreach ($etalages_disponibles as $etalage): ?>
                                    <option value="<?= $etalage['id_etalage'] ?>" 
                                            <?= ($id_etalage == $etalage['id_etalage']) ? 'selected' : '' ?>>
                                        Étalage #<?= $etalage['numero'] ?> - Secteur: <?= $etalage['secteur_nom'] ?? 'Non défini' ?>
                                        <?= $etalage['localisation'] ? ' - ' . $etalage['localisation'] : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Sélectionnez l'étalage que vous souhaitez louer</p>
                    </div>

                    <!-- Date de début -->
                    <div>
                        <label for="date_debut" class="block text-sm font-medium text-gray-700 mb-1">
                            Date de début <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-calendar text-gray-400"></i>
                            </div>
                            <input type="date" 
                                   id="date_debut" 
                                   name="date_debut" 
                                   required
                                   min="<?= date('Y-m-d') ?>"
                                   value="<?= date('Y-m-d') ?>"
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">La location commence à partir de cette date</p>
                    </div>

                    <!-- Durée -->
                    <div>
                        <label for="duree" class="block text-sm font-medium text-gray-700 mb-1">
                            Durée de location <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-clock text-gray-400"></i>
                            </div>
                            <select id="duree" 
                                    name="duree" 
                                    required
                                    class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition appearance-none bg-white">
                                <option value="1">1 mois</option>
                                <option value="2">2 mois</option>
                                <option value="3">3 mois</option>
                                <option value="4">4 mois</option>
                                <option value="5">5 mois</option>
                                <option value="6">6 mois</option>
                                <option value="12">1 an</option>
                                <option value="24">2 ans</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Durée minimale : 1 mois</p>
                    </div>

                    <!-- Montant -->
                    <div>
                        <label for="montant_location" class="block text-sm font-medium text-gray-700 mb-1">
                            Montant proposé (FCFA) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-money-bill-wave text-gray-400"></i>
                            </div>
                            <input type="number" 
                                   id="montant_location" 
                                   name="montant_location" 
                                   required
                                   min="10000"
                                   step="1000"
                                   value="<?= $montant_suggere ?>"
                                   placeholder="0"
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Montant en Francs CFA (FCFA) par mois</p>
                    </div>

                    <!-- Commentaire -->
                    <div>
                        <label for="commentaire" class="block text-sm font-medium text-gray-700 mb-1">
                            Commentaire (optionnel)
                        </label>
                        <div class="relative">
                            <div class="absolute top-3 left-3 pointer-events-none">
                                <i class="fas fa-comment text-gray-400"></i>
                            </div>
                            <textarea id="commentaire" 
                                      name="commentaire" 
                                      rows="3"
                                      placeholder="Ajoutez un commentaire ou des informations supplémentaires..."
                                      class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition resize-y"></textarea>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Informations supplémentaires pour le gestionnaire</p>
                    </div>

                    <!-- Informations importantes -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-start">
                            <i class="fas fa-info-circle text-blue-600 mt-0.5 mr-3 text-lg"></i>
                            <div>
                                <h4 class="font-semibold text-blue-800 text-sm">Informations importantes</h4>
                                <ul class="text-sm text-blue-700 mt-1 space-y-1">
                                    <li>• La demande sera examinée par un agent du marché</li>
                                    <li>• Vous serez contacté pour finaliser la location</li>
                                    <li>• Un contrat de location vous sera proposé</li>
                                    <li>• Le paiement se fait au moment de la signature du contrat</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Boutons d'action -->
                    <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200">
                        <button type="submit" class="flex-1 btn-accent py-3 rounded-lg font-semibold text-base flex items-center justify-center">
                            <i class="fas fa-paper-plane mr-2"></i> Confirmer la demande
                        </button>
                        <a href="/pages/Commercant/dashboard.php#tab-disponibles" 
                           class="flex-1 btn-outline py-3 rounded-lg font-semibold text-center flex items-center justify-center">
                            <i class="fas fa-times mr-2"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>

    <?php else: ?>
        <!-- Aucun étalage disponible -->
        <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
            <div class="text-6xl text-gray-300 mb-4">
                <i class="fas fa-store"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun étalage disponible</h3>
            <p class="text-gray-500 mb-6">
                Tous les étalages sont actuellement occupés. Revenez plus tard ou contactez l'administration.
            </p>
            <a href="/pages/Commercant/dashboard.php" class="btn-primary px-6 py-2 rounded-lg font-semibold">
                <i class="fas fa-arrow-left mr-2"></i> Retour au dashboard
            </a>
        </div>
    <?php endif; ?>

</div>

<!-- ============================================ -->
<!-- SCRIPTS -->
<!-- ============================================ -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const selectEtalage = document.getElementById('id_etalage');
        const dateDebut = document.getElementById('date_debut');
        const duree = document.getElementById('duree');
        const montant = document.getElementById('montant_location');
        
        function calculerMontant() {
            const dureeValue = parseInt(duree.value) || 1;
            const montantBase = 50000;
            let reduction = 0;
            if (dureeValue >= 12) reduction = 0.20;
            else if (dureeValue >= 6) reduction = 0.10;
            
            const montantCalcule = montantBase * (1 - reduction);
            montant.value = Math.round(montantCalcule / 1000) * 1000;
        }
        
        duree.addEventListener('change', calculerMontant);
        
        form.addEventListener('submit', function(e) {
            const etalage = selectEtalage.value;
            const date = dateDebut.value;
            const dureeValue = duree.value;
            const montantValue = montant.value;
            
            if (!etalage) {
                e.preventDefault();
                alert('❌ Veuillez sélectionner un étalage.');
                selectEtalage.focus();
                selectEtalage.style.borderColor = '#ef4444';
                return false;
            }
            
            if (!date) {
                e.preventDefault();
                alert('❌ Veuillez sélectionner une date de début.');
                dateDebut.focus();
                dateDebut.style.borderColor = '#ef4444';
                return false;
            }
            
            if (!dureeValue || parseInt(dureeValue) <= 0) {
                e.preventDefault();
                alert('❌ Veuillez sélectionner une durée valide.');
                duree.focus();
                duree.style.borderColor = '#ef4444';
                return false;
            }
            
            if (!montantValue || parseFloat(montantValue) <= 0) {
                e.preventDefault();
                alert('❌ Veuillez entrer un montant valide.');
                montant.focus();
                montant.style.borderColor = '#ef4444';
                return false;
            }
            
            const etalageTexte = selectEtalage.options[selectEtalage.selectedIndex].text;
            const message = confirm(
                'Confirmez-vous la demande de location pour :\n\n' +
                '📍 ' + etalageTexte + '\n' +
                '📅 Début : ' + date + '\n' +
                '⏱️ Durée : ' + dureeValue + ' mois\n' +
                '💰 Montant : ' + parseInt(montantValue).toLocaleString() + ' FCFA/mois\n\n' +
                'Cliquez sur OK pour confirmer.'
            );
            
            if (!message) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
        
        selectEtalage.addEventListener('change', function() {
            this.style.borderColor = '';
        });
        
        dateDebut.addEventListener('change', function() {
            this.style.borderColor = '';
        });
        
        duree.addEventListener('change', function() {
            this.style.borderColor = '';
        });
        
        montant.addEventListener('input', function() {
            if (this.value && parseFloat(this.value) > 0) {
                this.style.borderColor = '';
            }
        });
        
        montant.addEventListener('blur', function() {
            if (this.value && parseFloat(this.value) >= 0) {
                this.value = Math.round(parseFloat(this.value) / 1000) * 1000;
            }
        });
        
        const today = new Date().toISOString().split('T')[0];
        dateDebut.min = today;
        dateDebut.value = today;
        
        calculerMontant();
    });
</script>

</body>
</html>