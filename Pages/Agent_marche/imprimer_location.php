<?php
session_start();
require_once __DIR__ . '/../../Classes/AgentMarche.php';
require_once __DIR__ . '/../../Classes/Database.php';

$agent = new AgentMarche();
if (!$agent->isLoggedIn()) {
    header('Location: ../../login.php');
    exit;
}

$location_id = $_GET['id'] ?? 0;
if (!$location_id) {
    die('ID de location manquant');
}

$db = Database::getInstance()->getConnection();

// Récupérer la location avec tous les détails
$stmt = $db->prepare("
    SELECT l.*, e.numero as etalage_numero, e.localisation, s.designation as secteur_nom,
           u.nom_complet as commercant_nom, u.matricule as commercant_matricule,
           u.telephone as commercant_telephone, u.email as commercant_email
    FROM location l
    INNER JOIN etalage e ON l.id_etalage = e.id_etalage
    LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
    INNER JOIN commercant c ON l.id_commercant = c.id_commercant
    INNER JOIN utilisateurs u ON c.id_user = u.id_user
    WHERE l.id_location = ?
");
$stmt->execute([$location_id]);
$location = $stmt->fetch();

if (!$location) {
    die('Location introuvable');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrat de location</title>
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
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #1e3a5f;
            margin: 30px 0 15px 0;
            border-left: 4px solid #f59e0b;
            padding-left: 15px;
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
        .signature-area {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .signature-box {
            text-align: center;
        }
        .signature-box .line {
            border-bottom: 1px solid #333;
            margin-top: 40px;
            margin-bottom: 5px;
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
        <p>Contrat de location d'étalage</p>
        <p>Référence: LOC-<?= str_pad($location['id_location'], 6, '0', STR_PAD_LEFT) ?></p>
        <p>Date: <?= date('d/m/Y', strtotime($location['created_at'])) ?></p>
    </div>
    
    <div class="section-title">Informations sur l'étalage</div>
    <div class="info-grid">
        <div class="info-item">
            <div class="label">Numéro d'étalage</div>
            <div class="value">#<?= htmlspecialchars($location['etalage_numero']) ?></div>
        </div>
        <div class="info-item">
            <div class="label">Localisation</div>
            <div class="value"><?= htmlspecialchars($location['localisation'] ?? 'Non spécifiée') ?></div>
        </div>
        <div class="info-item">
            <div class="label">Secteur</div>
            <div class="value"><?= htmlspecialchars($location['secteur_nom'] ?? 'Non défini') ?></div>
        </div>
    </div>
    
    <div class="section-title">Informations sur le commerçant</div>
    <div class="info-grid">
        <div class="info-item">
            <div class="label">Nom complet</div>
            <div class="value"><?= htmlspecialchars($location['commercant_nom']) ?></div>
        </div>
        <div class="info-item">
            <div class="label">Matricule</div>
            <div class="value"><?= htmlspecialchars($location['commercant_matricule']) ?></div>
        </div>
        <div class="info-item">
            <div class="label">Téléphone</div>
            <div class="value"><?= htmlspecialchars($location['commercant_telephone'] ?? 'Non renseigné') ?></div>
        </div>
        <div class="info-item">
            <div class="label">Email</div>
            <div class="value"><?= htmlspecialchars($location['commercant_email'] ?? 'Non renseigné') ?></div>
        </div>
    </div>
    
    <div class="section-title">Conditions de la location</div>
    <div class="info-grid">
        <div class="info-item">
            <div class="label">Date de début</div>
            <div class="value"><?= date('d/m/Y', strtotime($location['date_debut'])) ?></div>
        </div>
        <div class="info-item">
            <div class="label">Date de fin</div>
            <div class="value"><?= date('d/m/Y', strtotime($location['date_fin'])) ?></div>
        </div>
        <div class="info-item">
            <div class="label">Durée</div>
            <div class="value"><?= htmlspecialchars($location['duree_location']) ?> mois</div>
        </div>
        <div class="info-item">
            <div class="label">Statut</div>
            <div class="value">
                <?php if (strtotime($location['date_fin']) >= time()): ?>
                    <span style="color: #16a34a;">✓ Actif</span>
                <?php else: ?>
                    <span style="color: #ef4444;">✗ Expiré</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="amount">
        <div class="label">Montant total de la location</div>
        <div class="value"><?= number_format($location['montant_location'], 0, ',', ' ') ?> FCFA</div>
    </div>
    
    <div style="margin: 30px 0;">
        <h3 style="color: #1e3a5f; margin-bottom: 10px;">Clauses et conditions</h3>
        <ul style="color: #555; line-height: 1.8; padding-left: 20px;">
            <li>Le commerçant s'engage à respecter les règles et règlements du marché.</li>
            <li>Le paiement de la location doit être effectué conformément aux modalités convenues.</li>
            <li>L'étalage doit être maintenu en bon état et propre.</li>
            <li>Toute infraction aux règles peut entraîner la résiliation du contrat.</li>
        </ul>
    </div>
    
    <div class="signature-area">
        <div class="signature-box">
            <p><strong>Le commerçant</strong></p>
            <div class="line"></div>
            <p style="font-size: 12px; color: #888; margin-top: 5px;">Signature et date</p>
        </div>
        <div class="signature-box">
            <p><strong>L'agent du marché</strong></p>
            <div class="line"></div>
            <p style="font-size: 12px; color: #888; margin-top: 5px;">Signature et date</p>
        </div>
    </div>
    
    <div class="footer">
        <p>Ce contrat est généré automatiquement par le système de gestion du Marché Virunga</p>
        <p>Pour toute question, veuillez contacter l'administration</p>
    </div>
</body>
</html>