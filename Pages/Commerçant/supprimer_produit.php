<?php
session_start();
require_once __DIR__ . '/../../Classes/Commercant.php';
require_once __DIR__ . '/../../Classes/Produit.php';

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

// Vérifier si un ID de produit est passé
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /pages/Commercant/dashboard.php?error=invalid_product#tab-produits');
    exit;
}

$id_produit = intval($_GET['id']);

// Récupérer le produit
$produitObj = new Produit();
$produit = $produitObj->getById($id_produit);

// Vérifier que le produit existe et appartient au commerçant
if (!$produit || $produit->getIdCommercant() != $id_commercant) {
    header('Location: /pages/Commercant/dashboard.php?error=product_not_found#tab-produits');
    exit;
}

// Traitement de la suppression
$message = '';
$message_type = '';
$deleted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
        $result = $produitObj->delete($id_produit);
        
        if ($result['success']) {
            $message = '✅ ' . $result['message'];
            $message_type = 'success';
            $deleted = true;
        } else {
            $message = '❌ ' . $result['error'];
            $message_type = 'error';
        }
    } else {
        // Annulation
        header('Location: /pages/Commercant/dashboard.php#tab-produits');
        exit;
    }
}

$page_title = 'Supprimer un produit - Marché Virunga';
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
        .btn-success { background: #22c55e; color: white; transition: all 0.3s ease; }
        .btn-success:hover { background: #16a34a; transform: scale(1.02); }
        
        .warning-card {
            border-left: 4px solid #ef4444;
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
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
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
<div class="max-w-2xl mx-auto px-4 py-12">

    <?php if ($deleted): ?>
        <!-- Message de succès -->
        <div class="bg-white rounded-2xl shadow-lg p-8 text-center">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check-circle text-green-600 text-4xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Produit supprimé !</h2>
            <p class="text-gray-600 mb-6">Le produit a été supprimé avec succès.</p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="/pages/Commercant/dashboard.php#tab-produits" class="btn-primary px-6 py-2 rounded-lg font-semibold">
                    <i class="fas fa-arrow-left mr-2"></i> Retour au dashboard
                </a>
                <a href="/pages/Commercant/ajout_produit.php" class="btn-accent px-6 py-2 rounded-lg font-semibold">
                    <i class="fas fa-plus mr-2"></i> Ajouter un nouveau produit
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Message d'erreur -->
        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg flex items-start toast bg-red-50 border border-red-200 text-red-700">
                <i class="fas fa-exclamation-circle mt-0.5 mr-3 text-lg"></i>
                <div><?= $message ?></div>
                <button onclick="this.parentElement.style.display='none'" class="ml-auto text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Carte de confirmation de suppression -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden warning-card">
            <div class="p-6 md:p-8">
                <!-- Icône d'avertissement -->
                <div class="flex justify-center mb-4">
                    <div class="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center pulse-animation">
                        <i class="fas fa-exclamation-triangle text-red-600 text-5xl"></i>
                    </div>
                </div>

                <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">
                    Supprimer le produit
                </h2>
                <p class="text-center text-gray-500 mb-6">
                    Êtes-vous sûr de vouloir supprimer ce produit ? Cette action est irréversible.
                </p>

                <!-- Informations du produit -->
                <div class="bg-gray-50 rounded-xl p-4 mb-6">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs text-gray-500">Nom du produit</p>
                            <p class="font-semibold text-gray-800"><?= htmlspecialchars($produit->getNomProduit()) ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">ID</p>
                            <p class="font-semibold text-gray-800">#<?= $produit->getIdProduit() ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Prix unitaire</p>
                            <p class="font-semibold text-accent"><?= number_format($produit->getPrixUnitaire(), 0, ',', ' ') ?> FCFA</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Quantité en stock</p>
                            <p class="font-semibold text-gray-800">
                                <?= $produit->getQuantiteStock() ?> <?= htmlspecialchars($produit->getUnite()) ?>
                            </p>
                        </div>
                    </div>
                    <?php if ($produit->getDescription()): ?>
                        <div class="mt-3 pt-3 border-t border-gray-200">
                            <p class="text-xs text-gray-500">Description</p>
                            <p class="text-sm text-gray-700"><?= htmlspecialchars($produit->getDescription()) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Formulaire de confirmation -->
                <form method="POST" action="">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button type="submit" name="confirm_delete" value="yes" 
                                class="flex-1 btn-danger py-3 rounded-lg font-semibold text-base flex items-center justify-center">
                            <i class="fas fa-trash mr-2"></i> Oui, supprimer
                        </button>
                        <button type="submit" name="confirm_delete" value="no" 
                                class="flex-1 btn-outline py-3 rounded-lg font-semibold text-center flex items-center justify-center">
                            <i class="fas fa-times mr-2"></i> Annuler
                        </button>
                    </div>
                </form>

                <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-3 flex items-start">
                    <i class="fas fa-info-circle text-yellow-600 mt-0.5 mr-2"></i>
                    <p class="text-xs text-yellow-700">
                        La suppression est définitive. Toutes les données associées à ce produit seront perdues.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

</body>
</html>