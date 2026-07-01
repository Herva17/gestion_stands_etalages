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

$location_id = $_GET['id'] ?? 0;
if (!$location_id) {
    header('Location: gestion_locations.php?error=id_manquant');
    exit;
}

$db = Database::getInstance()->getConnection();

// Récupérer les détails de la location
$stmt = $db->prepare("
    SELECT l.*, 
           e.numero as etalage_numero,
           e.localisation as etalage_localisation,
           s.designation as secteur_nom,
           u.nom_complet as commercant_nom,
           u.matricule as commercant_matricule,
           u.telephone as commercant_telephone,
           u.email as commercant_email,
           u.adresse as commercant_adresse,
           p.id_paiement,
           p.montant as montant_paye,
           p.mode_paiement,
           p.date_paiement,
           p.reference as paiement_reference,
           p.statut as paiement_statut,
           p.commentaire as paiement_commentaire
    FROM location l
    INNER JOIN etalage e ON l.id_etalage = e.id_etalage
    LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
    INNER JOIN commercant c ON l.id_commercant = c.id_commercant
    INNER JOIN utilisateurs u ON c.id_user = u.id_user
    LEFT JOIN paiement p ON l.id_location = p.id_location
    WHERE l.id_location = ?
");
$stmt->execute([$location_id]);
$location = $stmt->fetch();

if (!$location) {
    header('Location: gestion_locations.php?error=location_introuvable');
    exit;
}

$page_title = 'Détails de la location - Admin';
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
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-actif { background: #dbeafe; color: #1e40af; }
        .status-en_attente { background: #fef3c7; color: #92400e; }
        .status-approuve { background: #dcfce7; color: #166534; }
        .status-refuse { background: #fee2e2; color: #991b1b; }
        .status-termine { background: #e5e7eb; color: #4b5563; }
        .status-valide { background: #dcfce7; color: #166534; }
        
        .info-card {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
        }
        .info-card .label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
        }
        .info-card .value {
            font-size: 16px;
            color: #1e293b;
            font-weight: 500;
            margin-top: 4px;
        }
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
                <a href="gestion_locations.php" class="text-sm hover:text-accent transition">
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
        <h1 class="text-2xl font-bold text-primary mb-6">
            <i class="fas fa-file-invoice text-accent mr-2"></i>Détails de la location #<?= $location['id_location'] ?>
        </h1>
        
        <div class="grid md:grid-cols-2 gap-6">
            <!-- Informations de l'étalage -->
            <div>
                <h2 class="text-lg font-semibold text-primary mb-3">
                    <i class="fas fa-warehouse text-accent mr-2"></i>Étalage
                </h2>
                <div class="info-card">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="label">Numéro</p>
                            <p class="value">#<?= htmlspecialchars($location['etalage_numero']) ?></p>
                        </div>
                        <div>
                            <p class="label">Secteur</p>
                            <p class="value"><?= htmlspecialchars($location['secteur_nom'] ?? 'Non défini') ?></p>
                        </div>
                        <div class="col-span-2">
                            <p class="label">Localisation</p>
                            <p class="value"><?= htmlspecialchars($location['etalage_localisation'] ?? 'Non spécifiée') ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Informations du commerçant -->
            <div>
                <h2 class="text-lg font-semibold text-primary mb-3">
                    <i class="fas fa-user-tie text-accent mr-2"></i>Commerçant
                </h2>
                <div class="info-card">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <p class="label">Nom</p>
                            <p class="value"><?= htmlspecialchars($location['commercant_nom']) ?></p>
                        </div>
                        <div>
                            <p class="label">Matricule</p>
                            <p class="value"><?= htmlspecialchars($location['commercant_matricule']) ?></p>
                        </div>
                        <div>
                            <p class="label">Téléphone</p>
                            <p class="value"><?= htmlspecialchars($location['commercant_telephone'] ?? 'Non renseigné') ?></p>
                        </div>
                        <div class="col-span-2">
                            <p class="label">Email</p>
                            <p class="value"><?= htmlspecialchars($location['commercant_email'] ?? 'Non renseigné') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Détails de la location -->
        <div class="mt-6">
            <h2 class="text-lg font-semibold text-primary mb-3">
                <i class="fas fa-handshake text-accent mr-2"></i>Détails de la location
            </h2>
            <div class="grid md:grid-cols-3 gap-4">
                <div class="info-card">
                    <p class="label">Montant</p>
                    <p class="value text-accent font-bold"><?= number_format($location['montant_location'], 0, ',', ' ') ?> FCFA</p>
                </div>
                <div class="info-card">
                    <p class="label">Période</p>
                    <p class="value">
                        Du <?= date('d/m/Y', strtotime($location['date_debut'])) ?><br>
                        au <?= date('d/m/Y', strtotime($location['date_fin'])) ?>
                    </p>
                </div>
                <div class="info-card">
                    <p class="label">Statut</p>
                    <p class="value">
                        <?php
                        $status = $location['status'] ?? 'en_attente';
                        $status_class = match($status) {
                            'actif' => 'status-actif',
                            'en_attente' => 'status-en_attente',
                            'approuve' => 'status-approuve',
                            'refuse' => 'status-refuse',
                            'termine' => 'status-termine',
                            default => 'status-en_attente'
                        };
                        ?>
                        <span class="status-badge <?= $status_class ?>">
                            <?= ucfirst(str_replace('_', ' ', $status)) ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Informations de paiement -->
        <?php if ($location['id_paiement']): ?>
        <div class="mt-6">
            <h2 class="text-lg font-semibold text-primary mb-3">
                <i class="fas fa-coins text-accent mr-2"></i>Informations de paiement
            </h2>
            <div class="grid md:grid-cols-4 gap-4">
                <div class="info-card">
                    <p class="label">Référence</p>
                    <p class="value"><?= htmlspecialchars($location['paiement_reference'] ?? 'N/A') ?></p>
                </div>
                <div class="info-card">
                    <p class="label">Montant payé</p>
                    <p class="value text-accent font-bold"><?= number_format($location['montant_paye'], 0, ',', ' ') ?> FCFA</p>
                </div>
                <div class="info-card">
                    <p class="label">Mode de paiement</p>
                    <p class="value"><?= htmlspecialchars($location['mode_paiement'] ?? 'Espèces') ?></p>
                </div>
                <div class="info-card">
                    <p class="label">Date de paiement</p>
                    <p class="value"><?= $location['date_paiement'] ? date('d/m/Y', strtotime($location['date_paiement'])) : 'Non renseignée' ?></p>
                </div>
                <?php if ($location['paiement_commentaire']): ?>
                <div class="col-span-4 info-card">
                    <p class="label">Commentaire</p>
                    <p class="value"><?= htmlspecialchars($location['paiement_commentaire']) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="mt-6">
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-lg">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Aucun paiement enregistré pour cette location.
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Actions -->
        <div class="mt-6 pt-6 border-t border-gray-200 flex gap-3">
            <?php if ($location['status'] == 'en_attente'): ?>
                <a href="valider_location.php?id=<?= $location['id_location'] ?>" 
                   class="btn-success px-4 py-2 rounded-lg font-semibold"
                   onclick="return confirm('Valider cette demande de location ?')">
                    <i class="fas fa-check mr-2"></i> Valider
                </a>
                <a href="refuser_location.php?id=<?= $location['id_location'] ?>" 
                   class="btn-danger px-4 py-2 rounded-lg font-semibold"
                   onclick="return confirm('Refuser cette demande de location ?')">
                    <i class="fas fa-times mr-2"></i> Refuser
                </a>
            <?php endif; ?>
            <?php if ($location['status'] == 'actif'): ?>
                <a href="terminer_location.php?id=<?= $location['id_location'] ?>" 
                   class="btn-warning px-4 py-2 rounded-lg font-semibold"
                   onclick="return confirm('Terminer cette location ?')">
                    <i class="fas fa-stop mr-2"></i> Terminer
                </a>
            <?php endif; ?>
            <?php if ($location['id_paiement']): ?>
                <a href="imprimer_recu.php?id=<?= $location['id_paiement'] ?>" 
                   class="btn-primary px-4 py-2 rounded-lg font-semibold" target="_blank">
                    <i class="fas fa-print mr-2"></i> Imprimer le reçu
                </a>
            <?php endif; ?>
            <a href="gestion_locations.php" class="btn-outline px-4 py-2 rounded-lg font-semibold">
                <i class="fas fa-arrow-left mr-2"></i> Retour
            </a>
        </div>
    </div>
</div>

</body>
</html>