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

$paiement_id = $_GET['id'] ?? 0;
if (!$paiement_id) {
    header('Location: gestion_paiements.php?error=id_manquant');
    exit;
}

$db = Database::getInstance()->getConnection();

// Récupérer les détails du paiement
$stmt = $db->prepare("
    SELECT p.*, 
           u.nom_complet as commercant_nom,
           u.matricule as commercant_matricule,
           u.telephone as commercant_telephone,
           u.email as commercant_email,
           e.numero as etalage_numero,
           e.localisation as etalage_localisation,
           s.designation as secteur_nom,
           l.id_location,
           l.date_debut as location_date_debut,
           l.date_fin as location_date_fin,
           l.montant_location as location_montant
    FROM paiement p
    INNER JOIN location l ON p.id_location = l.id_location
    INNER JOIN commercant c ON l.id_commercant = c.id_commercant
    INNER JOIN utilisateurs u ON c.id_user = u.id_user
    INNER JOIN etalage e ON l.id_etalage = e.id_etalage
    LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
    WHERE p.id_paiement = ?
");
$stmt->execute([$paiement_id]);
$paiement = $stmt->fetch();

if (!$paiement) {
    header('Location: gestion_paiements.php?error=paiement_introuvable');
    exit;
}

$page_title = 'Détails du paiement - Admin';
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
        .status-valide { background: #dcfce7; color: #166534; }
        .status-annule { background: #fee2e2; color: #991b1b; }
        .status-en_attente { background: #fef3c7; color: #92400e; }
        
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
                <a href="gestion_paiements.php" class="text-sm hover:text-accent transition">
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

<div class="max-w-4xl mx-auto px-4 py-6">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h1 class="text-2xl font-bold text-primary mb-6">
            <i class="fas fa-file-invoice text-accent mr-2"></i>Détails du paiement #<?= $paiement['id_paiement'] ?>
        </h1>
        
        <div class="grid md:grid-cols-2 gap-6">
            <!-- Informations du paiement -->
            <div>
                <h2 class="text-lg font-semibold text-primary mb-3">
                    <i class="fas fa-info-circle text-accent mr-2"></i>Informations du paiement
                </h2>
                <div class="space-y-3">
                    <div class="info-card">
                        <p class="label">Référence</p>
                        <p class="value"><?= htmlspecialchars($paiement['reference'] ?? 'N/A') ?></p>
                    </div>
                    <div class="info-card">
                        <p class="label">Date de paiement</p>
                        <p class="value"><?= date('d/m/Y à H:i', strtotime($paiement['date_paiement'])) ?></p>
                    </div>
                    <div class="info-card">
                        <p class="label">Montant</p>
                        <p class="value text-accent font-bold text-2xl"><?= number_format($paiement['montant'], 0, ',', ' ') ?> FCFA</p>
                    </div>
                    <div class="info-card">
                        <p class="label">Mode de paiement</p>
                        <p class="value"><?= htmlspecialchars($paiement['mode_paiement'] ?? 'Espèces') ?></p>
                    </div>
                    <div class="info-card">
                        <p class="label">Statut</p>
                        <p class="value">
                            <?php
                            $statut = $paiement['statut'] ?? 'en_attente';
                            $status_class = match($statut) {
                                'valide' => 'status-valide',
                                'annule' => 'status-annule',
                                default => 'status-en_attente'
                            };
                            ?>
                            <span class="status-badge <?= $status_class ?>">
                                <?= ucfirst($statut) ?>
                            </span>
                        </p>
                    </div>
                    <?php if ($paiement['commentaire']): ?>
                    <div class="info-card">
                        <p class="label">Commentaire</p>
                        <p class="value"><?= htmlspecialchars($paiement['commentaire']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Informations associées -->
            <div>
                <h2 class="text-lg font-semibold text-primary mb-3">
                    <i class="fas fa-link text-accent mr-2"></i>Informations associées
                </h2>
                <div class="space-y-3">
                    <div class="info-card">
                        <p class="label">Commerçant</p>
                        <p class="value"><?= htmlspecialchars($paiement['commercant_nom']) ?></p>
                        <p class="text-sm text-gray-500">Matricule: <?= htmlspecialchars($paiement['commercant_matricule']) ?></p>
                        <p class="text-sm text-gray-500">📞 <?= htmlspecialchars($paiement['commercant_telephone'] ?? 'Non renseigné') ?></p>
                        <p class="text-sm text-gray-500">✉️ <?= htmlspecialchars($paiement['commercant_email'] ?? 'Non renseigné') ?></p>
                    </div>
                    <div class="info-card">
                        <p class="label">Étalage</p>
                        <p class="value">#<?= htmlspecialchars($paiement['etalage_numero']) ?></p>
                        <p class="text-sm text-gray-500">📍 <?= htmlspecialchars($paiement['etalage_localisation'] ?? 'Non spécifiée') ?></p>
                        <p class="text-sm text-gray-500">Secteur: <?= htmlspecialchars($paiement['secteur_nom'] ?? 'Non défini') ?></p>
                    </div>
                    <div class="info-card">
                        <p class="label">Location</p>
                        <p class="value">#<?= $paiement['id_location'] ?></p>
                        <p class="text-sm text-gray-500">
                            Du <?= date('d/m/Y', strtotime($paiement['location_date_debut'])) ?>
                            au <?= date('d/m/Y', strtotime($paiement['location_date_fin'])) ?>
                        </p>
                        <p class="text-sm text-gray-500">Montant: <?= number_format($paiement['location_montant'], 0, ',', ' ') ?> FCFA</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actions -->
        <div class="mt-6 pt-6 border-t border-gray-200 flex gap-3">
            <a href="imprimer_recu.php?id=<?= $paiement['id_paiement'] ?>" 
               class="btn-accent px-4 py-2 rounded-lg font-semibold" target="_blank">
                <i class="fas fa-print mr-2"></i> Imprimer le reçu
            </a>
            <?php if ($paiement['statut'] == 'en_attente'): ?>
                <a href="valider_paiement.php?id=<?= $paiement['id_paiement'] ?>" 
                   class="btn-success px-4 py-2 rounded-lg font-semibold"
                   onclick="return confirm('Valider ce paiement ?')">
                    <i class="fas fa-check mr-2"></i> Valider
                </a>
                <a href="annuler_paiement.php?id=<?= $paiement['id_paiement'] ?>" 
                   class="btn-danger px-4 py-2 rounded-lg font-semibold"
                   onclick="return confirm('Annuler ce paiement ?')">
                    <i class="fas fa-times mr-2"></i> Annuler
                </a>
            <?php endif; ?>
            <a href="gestion_paiements.php" class="btn-outline px-4 py-2 rounded-lg font-semibold">
                <i class="fas fa-arrow-left mr-2"></i> Retour
            </a>
        </div>
    </div>
</div>

</body>
</html>