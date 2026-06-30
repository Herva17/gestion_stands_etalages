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
$db = Database::getInstance()->getConnection();

// Vérifier que le commerçant existe dans la base avec son ID réel
$stmt = $db->prepare("SELECT id_commercant FROM commercant WHERE id_commercant = ?");
$stmt->execute([$id_commercant]);
$commercant_exists = $stmt->fetch();

if (!$commercant_exists) {
    // Si le commerçant n'existe pas, on le crée ou on redirige
    header('Location: dashboard.php?error=commercant_not_found');
    exit;
}

// Vérifier si un ID de location est passé
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php?error=invalid_location#tab-locations');
    exit;
}

$id_location = intval($_GET['id']);

// Récupérer les détails de la location
$stmt = $db->prepare("
    SELECT l.*, e.numero as etalage_numero, e.localisation, s.designation as secteur_nom,
           u.nom_complet as commercant_nom, u.matricule as commercant_matricule,
           u.email, u.telephone
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
    header('Location: dashboard.php?error=location_not_found#tab-locations');
    exit;
}

// Vérifier que la location est approuvée
if ($location['status'] !== 'approuve') {
    header('Location: dashboard.php?error=location_not_approved#tab-locations');
    exit;
}

// Vérifier si un paiement existe déjà pour cette location
$stmt = $db->prepare("
    SELECT * FROM paiement
    WHERE id_location = ? AND statut = 'valide'
");
$stmt->execute([$id_location]);
$paiement_existant = $stmt->fetch();

if ($paiement_existant) {
    header('Location: /pages/Commercant/recu_paiement.php?id=' . $paiement_existant['id_paiement']);
    exit;
}

// =============================================
// FONCTION POUR AJOUTER UNE NOTIFICATION DE MANIÈRE SÉCURISÉE
// =============================================
function addNotificationSafe($db, $id_commercant = null, $id_agent = null, $type, $title, $message, $lien = null) {
    // Vérifier que la table notifications existe et a les bonnes colonnes
    try {
        // Si c'est pour un commerçant, vérifier qu'il existe
        if ($id_commercant !== null) {
            $stmt = $db->prepare("SELECT id_commercant FROM commercant WHERE id_commercant = ?");
            $stmt->execute([$id_commercant]);
            if (!$stmt->fetch()) {
                return false; // Le commerçant n'existe pas
            }
        }
        
        // Si c'est pour un agent, vérifier qu'il existe
        if ($id_agent !== null) {
            $stmt = $db->prepare("SELECT id_agent FROM agent_marche WHERE id_agent = ?");
            $stmt->execute([$id_agent]);
            if (!$stmt->fetch()) {
                return false; // L'agent n'existe pas
            }
        }
        
        // Insérer la notification
        $stmt = $db->prepare("
            INSERT INTO notifications (id_commercant, id_agent, type, title, message, lien) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$id_commercant, $id_agent, $type, $title, $message, $lien]);
    } catch (Exception $e) {
        // En cas d'erreur, on log mais on continue
        error_log("Erreur lors de l'insertion de la notification: " . $e->getMessage());
        return false;
    }
}

// =============================================
// INFORMATIONS DE PAIEMENT PAR MODE
// =============================================
$infos_paiement = [
    'Mobile Money' => [
        'icone' => 'fa-mobile-alt',
        'couleur' => 'bg-green-50 border-green-200',
        'titre' => '📱 Paiement par Mobile Money',
        'infos' => [
            ['label' => 'Opérateur', 'valeur' => 'Orange Money / M-Pesa / Airtel Money'],
            ['label' => 'Numéro à créditer', 'valeur' => '07 87 65 43 21'],
            ['label' => 'Nom du compte', 'valeur' => 'Marché Virunga'],
            ['label' => 'Référence à indiquer', 'valeur' => 'LOC-' . str_pad($id_location, 6, '0', STR_PAD_LEFT)]
        ],
        'instructions' => 'Effectuez le transfert vers le numéro ci-dessus et indiquez la référence de votre location.'
    ],
    'Espèces' => [
        'icone' => 'fa-money-bill-wave',
        'couleur' => 'bg-blue-50 border-blue-200',
        'titre' => '💵 Paiement en espèces',
        'infos' => [
            ['label' => 'Lieu de paiement', 'valeur' => 'Guichet du Marché Virunga'],
            ['label' => 'Horaires', 'valeur' => 'Lundi - Vendredi: 08h00 - 17h00'],
            ['label' => 'Adresse', 'valeur' => 'Marché Virunga, Bureau des paiements'],
            ['label' => 'Référence à indiquer', 'valeur' => 'LOC-' . str_pad($id_location, 6, '0', STR_PAD_LEFT)]
        ],
        'instructions' => 'Rendez-vous au guichet du marché avec le montant exact et indiquez la référence de votre location.'
    ],
    'Virement bancaire' => [
        'icone' => 'fa-university',
        'couleur' => 'bg-purple-50 border-purple-200',
        'titre' => '🏦 Virement bancaire',
        'infos' => [
            ['label' => 'Banque', 'valeur' => 'Banque Commerciale du Congo (BCC)'],
            ['label' => 'Numéro de compte', 'valeur' => '12345-67890-1234'],
            ['label' => 'Titulaire du compte', 'valeur' => 'Marché Virunga'],
            ['label' => 'Code SWIFT', 'valeur' => 'BCCDCDXX'],
            ['label' => 'Référence à indiquer', 'valeur' => 'LOC-' . str_pad($id_location, 6, '0', STR_PAD_LEFT)]
        ],
        'instructions' => 'Effectuez le virement vers le compte ci-dessus et indiquez la référence de votre location.'
    ],
    'Chèque' => [
        'icone' => 'fa-file-invoice',
        'couleur' => 'bg-yellow-50 border-yellow-200',
        'titre' => '📄 Paiement par chèque',
        'infos' => [
            ['label' => 'Libellé du chèque', 'valeur' => 'Marché Virunga'],
            ['label' => 'Montant', 'valeur' => 'À libeller au montant exact'],
            ['label' => 'Adresse d\'envoi', 'valeur' => 'Marché Virunga, BP 1234, Kinshasa'],
            ['label' => 'Référence à indiquer', 'valeur' => 'LOC-' . str_pad($id_location, 6, '0', STR_PAD_LEFT)]
        ],
        'instructions' => 'Libellez le chèque à l\'ordre de "Marché Virunga" et indiquez la référence de votre location.'
    ],
    'Carte bancaire' => [
        'icone' => 'fa-credit-card',
        'couleur' => 'bg-indigo-50 border-indigo-200',
        'titre' => '💳 Paiement par carte bancaire',
        'infos' => [
            ['label' => 'Type de carte', 'valeur' => 'Visa, Mastercard, American Express'],
            ['label' => 'Lieu de paiement', 'valeur' => 'Terminal de paiement au Marché Virunga'],
            ['label' => 'Horaires', 'valeur' => 'Lundi - Samedi: 08h00 - 16h00'],
            ['label' => 'Référence à indiquer', 'valeur' => 'LOC-' . str_pad($id_location, 6, '0', STR_PAD_LEFT)]
        ],
        'instructions' => 'Présentez-vous au guichet avec votre carte bancaire pour effectuer le paiement.'
    ]
];

// Traitement du paiement
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_paiement'])) {
    $montant = floatval($_POST['montant'] ?? 0);
    $mode_paiement = trim($_POST['mode_paiement'] ?? '');
    $date_paiement = $_POST['date_paiement'] ?? date('Y-m-d');
    $reference = trim($_POST['reference'] ?? '');
    $commentaire = trim($_POST['commentaire'] ?? '');
    
    // Validation
    $errors = [];
    if ($montant <= 0) {
        $errors[] = 'Le montant est requis et doit être supérieur à 0.';
    }
    if (empty($mode_paiement)) {
        $errors[] = 'Veuillez sélectionner un mode de paiement.';
    }
    if (empty($date_paiement)) {
        $errors[] = 'La date de paiement est requise.';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Générer une référence unique si non fournie
            if (empty($reference)) {
                $reference = 'PAY-' . date('Ymd') . '-' . str_pad($id_location, 6, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);
            }
            
            // Insérer le paiement
            $stmt = $db->prepare("
                INSERT INTO paiement (
                    id_location,
                    montant,
                    mode_paiement,
                    date_paiement,
                    reference,
                    commentaire,
                    statut,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'valide', NOW())
            ");
            $stmt->execute([
                $id_location,
                $montant,
                $mode_paiement,
                $date_paiement,
                $reference,
                $commentaire
            ]);
            
            $id_paiement = $db->lastInsertId();
            
            // =============================================
            // NOTIFICATION POUR LE COMMERÇANT (AVEC VÉRIFICATION)
            // =============================================
            $montant_formate = number_format($montant, 0, ',', ' ');
            $message_notif = 'Votre paiement de ' . $montant_formate . ' FCFA pour l\'étalage #' . $location['etalage_numero'] . ' a été enregistré avec succès.';
            $lien_notif = 'recu_paiement.php?id=' . $id_paiement;
            
            // Vérifier que le commerçant existe vraiment
            $stmt = $db->prepare("SELECT id_commercant FROM commercant WHERE id_commercant = ?");
            $stmt->execute([$id_commercant]);
            if ($stmt->fetch()) {
                // Insérer la notification UNIQUEMENT si le commerçant existe
                try {
                    $stmt = $db->prepare("
                        INSERT INTO notifications (id_commercant, type, title, message, lien) 
                        VALUES (?, 'success', '✅ Paiement effectué', ?, ?)
                    ");
                    $stmt->execute([$id_commercant, $message_notif, $lien_notif]);
                } catch (Exception $e) {
                    // On ignore l'erreur de notification pour ne pas bloquer le paiement
                    error_log("Erreur notification commerçant: " . $e->getMessage());
                }
            }
            
            // =============================================
            // NOTIFICATION POUR L'AGENT (AVEC VÉRIFICATION)
            // =============================================
            $stmt = $db->prepare("SELECT id_agent FROM agent_marche LIMIT 1");
            $stmt->execute();
            $agent = $stmt->fetch();
            
            if ($agent) {
                $nom_commercant = $user->getNomComplet();
                $message_agent = 'Le commerçant ' . $nom_commercant . ' a effectué un paiement de ' . $montant_formate . ' FCFA pour l\'étalage #' . $location['etalage_numero'] . '.';
                
                // Vérifier que la table notifications a la colonne id_agent
                try {
                    $stmt = $db->prepare("
                        INSERT INTO notifications (id_agent, type, title, message, lien) 
                        VALUES (?, 'success', '💰 Paiement reçu', ?, '/pages/Agent/dashboard.php?tab=paiements')
                    ");
                    $stmt->execute([$agent['id_agent'], $message_agent]);
                } catch (Exception $e) {
                    // Si la colonne id_agent n'existe pas, on ignore
                    error_log("Erreur notification agent: " . $e->getMessage());
                }
            }
            
            $db->commit();
            
            // Rediriger vers le reçu
            header('Location: recu_paiement.php?id=' . $id_paiement);
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $message = '❌ Erreur lors du paiement : ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = '❌ ' . implode('<br>', $errors);
        $message_type = 'error';
    }
}

// Calculer le montant restant
$montant_total = $location['montant_location'];
$montant_suggere = $montant_total;

$page_title = 'Effectuer un paiement - Marché Virunga';
?>
<!-- Le reste du HTML reste identique -->
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
        
        .location-preview {
            border-left: 4px solid #f59e0b;
        }
        
        .payment-info {
            transition: all 0.3s ease;
        }
        .payment-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .payment-detail {
            padding: 8px 12px;
            border-bottom: 1px solid #f3f4f6;
        }
        .payment-detail:last-child {
            border-bottom: none;
        }
        
        .copy-btn {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .copy-btn:hover {
            color: #f59e0b;
            transform: scale(1.1);
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
                <i class="fas fa-credit-card text-accent mr-2"></i>Effectuer le paiement
            </h1>
            <p class="text-gray-500 text-sm mt-1">
                Remplissez le formulaire pour effectuer le paiement de votre location.
            </p>
        </div>
        <a href="/pages/Commercant/dashboard.php#tab-locations" 
           class="btn-outline px-4 py-2 rounded-lg text-sm font-semibold flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Retour
        </a>
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

    <!-- Informations de la location -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6 location-preview">
        <div class="flex flex-wrap items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wider">Paiement pour</p>
                <h3 class="text-lg font-bold text-primary">
                    Étalage #<?= htmlspecialchars($location['etalage_numero']) ?>
                </h3>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-map-pin mr-1"></i>
                    <?= htmlspecialchars($location['localisation'] ?? 'Non spécifiée') ?>
                    <span class="mx-2">•</span>
                    <i class="fas fa-tag mr-1"></i>
                    Secteur: <?= htmlspecialchars($location['secteur_nom'] ?? 'Non défini') ?>
                </p>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-user mr-1"></i>
                    Commerçant: <?= htmlspecialchars($location['commercant_nom']) ?>
                </p>
            </div>
            <div class="flex gap-2 mt-2 sm:mt-0">
                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm font-semibold">
                    <i class="fas fa-check-circle mr-1"></i> Approuvée
                </span>
            </div>
        </div>
        <div class="mt-3 grid grid-cols-3 gap-4 pt-3 border-t border-gray-100">
            <div>
                <p class="text-xs text-gray-500">Montant total</p>
                <p class="text-lg font-bold text-accent"><?= number_format($location['montant_location'], 0, ',', ' ') ?> FCFA</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Début</p>
                <p class="text-sm font-medium text-gray-700"><?= date('d/m/Y', strtotime($location['date_debut'])) ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Fin</p>
                <p class="text-sm font-medium text-gray-700"><?= date('d/m/Y', strtotime($location['date_fin'])) ?></p>
            </div>
        </div>
    </div>

    <!-- Formulaire de paiement -->
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden form-card border border-gray-100">
        <div class="p-6 md:p-8">
            <form method="POST" action="" class="space-y-6" id="formPaiement">
                <input type="hidden" name="submit_paiement" value="1">
                
                <!-- Montant -->
                <div>
                    <label for="montant" class="block text-sm font-medium text-gray-700 mb-1">
                        Montant à payer (FCFA) <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-money-bill-wave text-gray-400"></i>
                        </div>
                        <input type="number" 
                               id="montant" 
                               name="montant" 
                               required
                               min="0"
                               step="100"
                               value="<?= htmlspecialchars($montant_suggere) ?>"
                               placeholder="0"
                               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Montant en Francs CFA (FCFA)</p>
                </div>

                <!-- Mode de paiement -->
                <div>
                    <label for="mode_paiement" class="block text-sm font-medium text-gray-700 mb-1">
                        Mode de paiement <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-credit-card text-gray-400"></i>
                        </div>
                        <select id="mode_paiement" 
                                name="mode_paiement" 
                                required
                                onchange="updatePaymentInfo(this.value)"
                                class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition appearance-none bg-white">
                            <option value="">-- Sélectionnez un mode --</option>
                            <option value="Mobile Money">📱 Mobile Money</option>
                            <option value="Espèces">💵 Espèces</option>
                            <option value="Virement bancaire">🏦 Virement bancaire</option>
                            <option value="Chèque">📄 Chèque</option>
                            <option value="Carte bancaire">💳 Carte bancaire</option>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fas fa-chevron-down text-gray-400"></i>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Sélectionnez votre moyen de paiement</p>
                </div>

                <!-- Informations de paiement selon le mode -->
                <div id="paymentInfoContainer" class="hidden">
                    <div id="paymentInfoContent" class="payment-info rounded-xl p-4">
                        <!-- Contenu dynamique -->
                    </div>
                </div>

                <!-- Date de paiement -->
                <div>
                    <label for="date_paiement" class="block text-sm font-medium text-gray-700 mb-1">
                        Date de paiement <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-calendar text-gray-400"></i>
                        </div>
                        <input type="date" 
                               id="date_paiement" 
                               name="date_paiement" 
                               required
                               max="<?= date('Y-m-d') ?>"
                               value="<?= date('Y-m-d') ?>"
                               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Date à laquelle le paiement est effectué</p>
                </div>

                <!-- Référence -->
                <div>
                    <label for="reference" class="block text-sm font-medium text-gray-700 mb-1">
                        Référence de paiement
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-hashtag text-gray-400"></i>
                        </div>
                        <input type="text" 
                               id="reference" 
                               name="reference" 
                               placeholder="Ex: REF-2024-001"
                               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Laissez vide pour une génération automatique</p>
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
                                  rows="2"
                                  placeholder="Informations supplémentaires sur le paiement..."
                                  class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition resize-y"></textarea>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Informations optionnelles</p>
                </div>

                <!-- Récapitulatif -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <i class="fas fa-info-circle text-green-600 mt-0.5 mr-3 text-lg"></i>
                        <div>
                            <h4 class="font-semibold text-green-800 text-sm">Récapitulatif du paiement</h4>
                            <ul class="text-sm text-green-700 mt-1 space-y-1">
                                <li>• Montant total : <strong><?= number_format($location['montant_location'], 0, ',', ' ') ?> FCFA</strong></li>
                                <li>• Référence : <strong id="reference_preview">Générée automatiquement</strong></li>
                                <li>• Après validation, vous recevrez un reçu officiel</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Boutons d'action -->
                <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200">
                    <button type="submit" class="flex-1 btn-accent py-3 rounded-lg font-semibold text-base flex items-center justify-center">
                        <i class="fas fa-check mr-2"></i> Confirmer le paiement
                    </button>
                    <a href="/pages/Commercant/dashboard.php#tab-locations" 
                       class="flex-1 btn-outline py-3 rounded-lg font-semibold text-center flex items-center justify-center">
                        <i class="fas fa-times mr-2"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- ============================================ -->
<!-- SCRIPTS -->
<!-- ============================================ -->
<script>
    // Informations de paiement par mode
    const paymentInfos = <?php echo json_encode($infos_paiement); ?>;
    const locationId = <?= $id_location ?>;

    function updatePaymentInfo(mode) {
        const container = document.getElementById('paymentInfoContainer');
        const content = document.getElementById('paymentInfoContent');
        
        if (!mode || !paymentInfos[mode]) {
            container.classList.add('hidden');
            return;
        }
        
        const info = paymentInfos[mode];
        
        let html = `
            <div class="${info.couleur} rounded-xl p-4 border-2">
                <div class="flex items-center mb-3">
                    <i class="fas ${info.icone} text-2xl text-accent mr-3"></i>
                    <h4 class="font-bold text-gray-800">${info.titre}</h4>
                </div>
                <div class="space-y-2">
        `;
        
        info.infos.forEach(item => {
            html += `
                <div class="payment-detail flex items-center justify-between">
                    <span class="text-sm text-gray-600">${item.label}</span>
                    <span class="text-sm font-medium text-gray-800 flex items-center">
                        ${item.valeur}
                        <button onclick="copierTexte('${item.valeur}')" class="copy-btn ml-2 text-gray-400 hover:text-accent" title="Copier">
                            <i class="fas fa-copy text-xs"></i>
                        </button>
                    </span>
                </div>
            `;
        });
        
        html += `
                </div>
                <div class="mt-3 pt-3 border-t border-gray-200">
                    <p class="text-sm text-yellow-700 flex items-start">
                        <i class="fas fa-info-circle mt-0.5 mr-2"></i>
                        ${info.instructions}
                    </p>
                </div>
            </div>
        `;
        
        content.innerHTML = html;
        container.classList.remove('hidden');
    }

    // Fonction pour copier le texte
    function copierTexte(texte) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(texte).then(() => {
                alert('✅ Texte copié dans le presse-papier !');
            }).catch(() => {
                copierTexteFallback(texte);
            });
        } else {
            copierTexteFallback(texte);
        }
    }

    function copierTexteFallback(texte) {
        const textarea = document.createElement('textarea');
        textarea.value = texte;
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            alert('✅ Texte copié dans le presse-papier !');
        } catch (err) {
            alert('❌ Impossible de copier le texte.');
        }
        document.body.removeChild(textarea);
    }

    // Sélectionner le premier mode par défaut si présent
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const montantInput = document.getElementById('montant');
        const referenceInput = document.getElementById('reference');
        const referencePreview = document.getElementById('reference_preview');
        const modeSelect = document.getElementById('mode_paiement');
        
        // Mettre à jour l'aperçu de la référence
        referenceInput.addEventListener('input', function() {
            if (this.value.trim()) {
                referencePreview.textContent = this.value.trim();
            } else {
                referencePreview.textContent = 'Générée automatiquement';
            }
        });
        
        // Si un mode est sélectionné par défaut
        if (modeSelect.value) {
            updatePaymentInfo(modeSelect.value);
        }
        
        // Validation du formulaire
        form.addEventListener('submit', function(e) {
            const montant = montantInput.value;
            const mode = document.getElementById('mode_paiement').value;
            const date = document.getElementById('date_paiement').value;
            
            if (!montant || parseFloat(montant) <= 0) {
                e.preventDefault();
                alert('❌ Veuillez entrer un montant valide.');
                montantInput.focus();
                montantInput.style.borderColor = '#ef4444';
                return false;
            }
            
            if (!mode) {
                e.preventDefault();
                alert('❌ Veuillez sélectionner un mode de paiement.');
                document.getElementById('mode_paiement').focus();
                document.getElementById('mode_paiement').style.borderColor = '#ef4444';
                return false;
            }
            
            if (!date) {
                e.preventDefault();
                alert('❌ Veuillez sélectionner une date de paiement.');
                document.getElementById('date_paiement').focus();
                document.getElementById('date_paiement').style.borderColor = '#ef4444';
                return false;
            }
            
            const montantFormate = parseInt(montant).toLocaleString();
            if (!confirm('Confirmez-vous ce paiement de ' + montantFormate + ' FCFA ?')) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
        
        // Supprimer les styles d'erreur
        montantInput.addEventListener('input', function() {
            if (this.value && parseFloat(this.value) > 0) {
                this.style.borderColor = '';
            }
        });
        
        document.getElementById('mode_paiement').addEventListener('change', function() {
            if (this.value) {
                this.style.borderColor = '';
            }
        });
        
        document.getElementById('date_paiement').addEventListener('change', function() {
            if (this.value) {
                this.style.borderColor = '';
            }
        });
        
        // Focus sur le premier champ
        montantInput.focus();
    });
</script>

</body>
</html>