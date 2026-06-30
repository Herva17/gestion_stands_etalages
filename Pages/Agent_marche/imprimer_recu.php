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
    die('ID de paiement manquant');
}

$db = Database::getInstance()->getConnection();

// Récupérer le paiement avec les détails - CORRIGÉ
$stmt = $db->prepare("
    SELECT p.*, 
           u.nom_complet as commercant_nom, 
           u.matricule as commercant_matricule,
           u.telephone as commercant_telephone,
           e.numero as etalage_numero,
           e.localisation as etalage_localisation,
           l.date_debut as location_date_debut,
           l.date_fin as location_date_fin,
           l.montant_location as location_montant
    FROM paiement p
    INNER JOIN location l ON p.id_location = l.id_location
    INNER JOIN commercant c ON l.id_commercant = c.id_commercant
    INNER JOIN utilisateurs u ON c.id_user = u.id_user
    INNER JOIN etalage e ON l.id_etalage = e.id_etalage
    WHERE p.id_paiement = ?
");
$stmt->execute([$paiement_id]);
$paiement = $stmt->fetch();

if (!$paiement) {
    die('Paiement introuvable');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu de paiement</title>
    <style>
        * { font-family: 'Arial', sans-serif; }
        body { 
            background: white; 
            padding: 40px; 
            max-width: 800px; 
            margin: 0 auto;
        }
        .header { 
            text-align: center; 
            border-bottom: 3px double #1e3a5f; 
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 { 
            color: #1e3a5f; 
            font-size: 28px;
            margin: 0;
        }
        .header p { 
            color: #666; 
            margin: 5px 0;
        }
        .info-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px;
            margin-bottom: 30px;
        }
        .info-item { 
            border-bottom: 1px solid #eee; 
            padding: 10px 0;
        }
        .info-item .label { 
            font-weight: bold; 
            color: #555;
            font-size: 12px;
            text-transform: uppercase;
        }
        .info-item .value { 
            font-size: 16px; 
            color: #222;
            margin-top: 3px;
        }
        .amount { 
            text-align: center; 
            background: #f59e0b20; 
            padding: 20px;
            border-radius: 10px;
            margin: 30px 0;
        }
        .amount .value { 
            font-size: 36px; 
            font-weight: bold; 
            color: #f59e0b;
        }
        .amount .label { 
            font-size: 14px; 
            color: #555;
        }
        .footer { 
            text-align: center; 
            border-top: 2px solid #eee; 
            padding-top: 20px;
            margin-top: 40px;
            color: #888;
            font-size: 12px;
        }
        .print-btn {
            background: #1e3a5f;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
        }
        .print-btn:hover { background: #2d6a9f; }
        @media print {
            .no-print { display: none; }
            body { padding: 20px; }
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-valide { background: #dcfce7; color: #166534; }
        .status-annule { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print()" class="print-btn">
            <i class="fas fa-print"></i> Imprimer / Télécharger PDF
        </button>
        <a href="dashboard.php" style="margin-left: 10px; background: #6b7280; color: white; padding: 12px 20px; border-radius: 8px; text-decoration: none; display: inline-block;">
            Retour
        </a>
    </div>
    
    <div class="header">
        <h1>🏪 Marché Virunga</h1>
        <p>Reçu de paiement #<?= str_pad($paiement['id_paiement'], 6, '0', STR_PAD_LEFT) ?></p>
        <p>Référence: <?= htmlspecialchars($paiement['reference'] ?? 'N/A') ?></p>
        <p>Date: <?= date('d/m/Y à H:i', strtotime($paiement['date_paiement'])) ?></p>
    </div>
    
    <div class="info-grid">
        <div class="info-item">
            <div class="label">Commerçant</div>
            <div class="value"><?= htmlspecialchars($paiement['commercant_nom']) ?></div>
        </div>
        <div class="info-item">
            <div class="label">Matricule</div>
            <div class="value"><?= htmlspecialchars($paiement['commercant_matricule']) ?></div>
        </div>
        <div class="info-item">
            <div class="label">Téléphone</div>
            <div class="value"><?= htmlspecialchars($paiement['commercant_telephone'] ?? 'Non renseigné') ?></div>
        </div>
        <div class="info-item">
            <div class="label">Étalage</div>
            <div class="value">#<?= htmlspecialchars($paiement['etalage_numero']) ?></div>
        </div>
        <div class="info-item">
            <div class="label">Localisation</div>
            <div class="value"><?= htmlspecialchars($paiement['etalage_localisation'] ?? 'Non spécifiée') ?></div>
        </div>
        <div class="info-item">
            <div class="label">Mode de paiement</div>
            <div class="value"><?= htmlspecialchars($paiement['mode_paiement'] ?? 'Espèces') ?></div>
        </div>
        <div class="info-item">
            <div class="label">Période de location</div>
            <div class="value">
                Du <?= date('d/m/Y', strtotime($paiement['location_date_debut'])) ?> 
                au <?= date('d/m/Y', strtotime($paiement['location_date_fin'])) ?>
            </div>
        </div>
        <div class="info-item">
            <div class="label">Statut</div>
            <div class="value">
                <span class="status-badge status-<?= $paiement['statut'] ?? 'valide' ?>">
                    <?= ucfirst($paiement['statut'] ?? 'Valide') ?>
                </span>
            </div>
        </div>
    </div>
    
    <div class="amount">
        <div class="label">Montant payé</div>
        <div class="value"><?= number_format($paiement['montant'], 0, ',', ' ') ?> FCFA</div>
        <?php if ($paiement['location_montant']): ?>
            <div style="font-size: 14px; color: #888; margin-top: 5px;">
                Montant de la location: <?= number_format($paiement['location_montant'], 0, ',', ' ') ?> FCFA
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($paiement['commentaire']): ?>
        <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
            <p style="font-weight: bold; color: #555; margin: 0 0 5px 0;">Commentaire :</p>
            <p style="color: #666; margin: 0;"><?= htmlspecialchars($paiement['commentaire']) ?></p>
        </div>
    <?php endif; ?>
    
    <div style="text-align: center; color: #666; font-size: 14px;">
        <p>✓ Paiement confirmé et enregistré dans le système</p>
    </div>
    
    <div class="footer">
        <p>Ce reçu est généré automatiquement par le système de gestion du Marché Virunga</p>
        <p>Pour toute question, veuillez contacter l'administration</p>
    </div>
</body>
</html>