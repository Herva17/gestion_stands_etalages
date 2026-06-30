<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';
require_once __DIR__ . '/../../Classes/Database.php';

$agent = new AgentMarche();
if (!$agent->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$paiement_id = $_GET['id'] ?? 0;
if (!$paiement_id) {
    header('Location: dashboard.php?error=id_paiement_manquant');
    exit;
}

$db = Database::getInstance()->getConnection();

// Récupérer le paiement avec les détails - CORRIGÉ
$stmt = $db->prepare("
    SELECT p.*, 
           u.nom_complet as commercant_nom, 
           u.matricule as commercant_matricule,
           u.telephone as commercant_telephone,
           u.email as commercant_email,
           e.numero as etalage_numero,
           e.localisation as etalage_localisation,
           s.designation as secteur_nom,
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
    header('Location: dashboard.php?error=paiement_introuvable');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du paiement</title>
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
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-valide { background: #dcfce7; color: #166534; }
        .status-annule { background: #fee2e2; color: #991b1b; }
        .status-en_attente { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-primary">
                    <i class="fas fa-file-invoice text-accent mr-2"></i>Détails du paiement #<?= $paiement['id_paiement'] ?>
                </h1>
                <a href="dashboard.php" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-arrow-left mr-1"></i> Retour
                </a>
            </div>
            
            <div class="grid md:grid-cols-2 gap-6">
                <!-- Colonne gauche -->
                <div class="space-y-4">
                    <div class="border-b pb-3">
                        <p class="text-sm text-gray-500">Référence</p>
                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($paiement['reference'] ?? 'N/A') ?></p>
                    </div>
                    
                    <div class="border-b pb-3">
                        <p class="text-sm text-gray-500">Commerçant</p>
                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($paiement['commercant_nom']) ?></p>
                        <p class="text-sm text-gray-600">Matricule: <?= htmlspecialchars($paiement['commercant_matricule']) ?></p>
                        <p class="text-sm text-gray-600">📞 <?= htmlspecialchars($paiement['commercant_telephone'] ?? 'Non renseigné') ?></p>
                        <p class="text-sm text-gray-600">✉️ <?= htmlspecialchars($paiement['commercant_email'] ?? 'Non renseigné') ?></p>
                    </div>
                    
                    <div class="border-b pb-3">
                        <p class="text-sm text-gray-500">Étalage</p>
                        <p class="font-semibold text-gray-800">#<?= htmlspecialchars($paiement['etalage_numero']) ?></p>
                        <p class="text-sm text-gray-600">📍 <?= htmlspecialchars($paiement['etalage_localisation'] ?? 'Non spécifiée') ?></p>
                        <p class="text-sm text-gray-600">Secteur: <?= htmlspecialchars($paiement['secteur_nom'] ?? 'Non défini') ?></p>
                    </div>
                </div>
                
                <!-- Colonne droite -->
                <div class="space-y-4">
                    <div class="border-b pb-3">
                        <p class="text-sm text-gray-500">Date du paiement</p>
                        <p class="font-semibold text-gray-800"><?= date('d/m/Y à H:i', strtotime($paiement['date_paiement'])) ?></p>
                    </div>
                    
                    <div class="border-b pb-3">
                        <p class="text-sm text-gray-500">Montant</p>
                        <p class="font-bold text-accent text-2xl"><?= number_format($paiement['montant'], 0, ',', ' ') ?> FCFA</p>
                        <?php if ($paiement['location_montant']): ?>
                            <p class="text-sm text-gray-500">Montant de la location: <?= number_format($paiement['location_montant'], 0, ',', ' ') ?> FCFA</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="border-b pb-3">
                        <p class="text-sm text-gray-500">Mode de paiement</p>
                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($paiement['mode_paiement'] ?? 'Espèces') ?></p>
                    </div>
                    
                    <div class="border-b pb-3">
                        <p class="text-sm text-gray-500">Période de location</p>
                        <p class="font-semibold text-gray-800">
                            Du <?= date('d/m/Y', strtotime($paiement['location_date_debut'])) ?> 
                            au <?= date('d/m/Y', strtotime($paiement['location_date_fin'])) ?>
                        </p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Statut</p>
                        <span class="status-badge status-<?= $paiement['statut'] ?? 'valide' ?>">
                            <?= ucfirst($paiement['statut'] ?? 'Valide') ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <?php if ($paiement['commentaire']): ?>
                <div class="mt-6 pt-6 border-t border-gray-200">
                    <p class="text-sm text-gray-500">Commentaire</p>
                    <p class="text-gray-700"><?= htmlspecialchars($paiement['commentaire']) ?></p>
                </div>
            <?php endif; ?>
            
            <div class="mt-6 pt-6 border-t border-gray-200 flex gap-3">
                <a href="imprimer_recu.php?id=<?= $paiement['id_paiement'] ?>" 
                   class="btn-accent px-4 py-2 rounded-lg font-semibold" target="_blank">
                    <i class="fas fa-print mr-2"></i> Imprimer le reçu
                </a>
                <a href="modifier_paiement.php?id=<?= $paiement['id_paiement'] ?>" 
                   class="btn-primary px-4 py-2 rounded-lg font-semibold">
                    <i class="fas fa-edit mr-2"></i> Modifier
                </a>
                <a href="supprimer_paiement.php?id=<?= $paiement['id_paiement'] ?>" 
                   class="btn-danger px-4 py-2 rounded-lg font-semibold"
                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce paiement ?')">
                    <i class="fas fa-trash mr-2"></i> Supprimer
                </a>
            </div>
        </div>
    </div>
</body>
</html>