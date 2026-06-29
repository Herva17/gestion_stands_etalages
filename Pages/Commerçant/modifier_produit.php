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

// Traitement du formulaire de modification
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nom_produit' => trim($_POST['nom_produit'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'prix_unitaire' => floatval($_POST['prix_unitaire'] ?? 0),
        'quantite_stock' => intval($_POST['quantite_stock'] ?? 0),
        'unite' => trim($_POST['unite'] ?? 'pièce')
    ];
    
    // Validation
    if (empty($data['nom_produit'])) {
        $message = '❌ Le nom du produit est requis';
        $message_type = 'error';
    } elseif ($data['prix_unitaire'] <= 0) {
        $message = '❌ Le prix unitaire doit être supérieur à 0';
        $message_type = 'error';
    } else {
        $result = $produitObj->update($id_produit, $data);
        
        if ($result['success']) {
            $message = '✅ ' . $result['message'];
            $message_type = 'success';
            // Recharger les données du produit
            $produit = $produitObj->getById($id_produit);
        } else {
            $message = '❌ ' . $result['error'];
            $message_type = 'error';
        }
    }
}

// Unités disponibles
$unites_disponibles = [
    'pièce' => 'Pièce',
    'kg' => 'Kilogramme (kg)',
    'g' => 'Gramme (g)',
    'litre' => 'Litre',
    'sac' => 'Sac',
    'botte' => 'Botte',
    'douzaine' => 'Douzaine',
    'carton' => 'Carton',
    'bouteille' => 'Bouteille',
    'paquet' => 'Paquet'
];

$page_title = 'Modifier un produit - Marché Virunga';
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
        
        .product-preview {
            border-left: 4px solid #f59e0b;
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
                <a href="../../logout.php" class="text-sm bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition flex items-center">
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
                <i class="fas fa-edit text-accent mr-2"></i>Modifier un produit
            </h1>
            <p class="text-gray-500 text-sm mt-1">
                Modifiez les informations de votre produit.
            </p>
        </div>
        <a href="dashboard.php#tab-produits" 
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

    <!-- Aperçu du produit -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6 product-preview">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 uppercase tracking-wider">Produit en cours de modification</p>
                <h3 class="text-lg font-bold text-primary"><?= htmlspecialchars($produit->getNomProduit()) ?></h3>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-tag mr-1"></i>
                    <?= number_format($produit->getPrixUnitaire(), 0, ',', ' ') ?> FCFA
                    <span class="mx-2">•</span>
                    <i class="fas fa-boxes mr-1"></i>
                    Stock: <?= $produit->getQuantiteStock() ?> <?= htmlspecialchars($produit->getUnite()) ?>
                    <span class="mx-2">•</span>
                    <i class="fas fa-hashtag mr-1"></i>
                    ID: #<?= $produit->getIdProduit() ?>
                </p>
            </div>
            <div class="flex gap-2">
                <span class="bg-<?= $produit->getQuantiteStock() > 0 ? 'green' : 'red' ?>-100 text-<?= $produit->getQuantiteStock() > 0 ? 'green' : 'red' ?>-700 px-2 py-1 rounded-full text-xs font-semibold">
                    <i class="fas fa-<?= $produit->getQuantiteStock() > 0 ? 'check-circle' : 'exclamation-circle' ?> mr-1"></i>
                    <?= $produit->getQuantiteStock() > 0 ? 'En stock' : 'Rupture' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Formulaire de modification -->
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden form-card border border-gray-100">
        <div class="p-6 md:p-8">
            <form id="form-edit-product" method="POST" action="" class="space-y-6">
                
                <div>
                    <label for="nom_produit" class="block text-sm font-medium text-gray-700 mb-1">
                        Nom du produit <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-tag text-gray-400"></i>
                        </div>
                        <input type="text" 
                               id="nom_produit" 
                               name="nom_produit" 
                               required
                               value="<?= htmlspecialchars($produit->getNomProduit()) ?>"
                               placeholder="Ex: Tomates fraîches"
                               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition">
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                        Description
                    </label>
                    <div class="relative">
                        <div class="absolute top-3 left-3 pointer-events-none">
                            <i class="fas fa-align-left text-gray-400"></i>
                        </div>
                        <textarea id="description" 
                                  name="description" 
                                  rows="3"
                                  placeholder="Décrivez votre produit..."
                                  class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition resize-y"><?= htmlspecialchars($produit->getDescription()) ?></textarea>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="prix_unitaire" class="block text-sm font-medium text-gray-700 mb-1">
                            Prix unitaire (FCFA) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-money-bill-wave text-gray-400"></i>
                            </div>
                            <input type="number" 
                                   id="prix_unitaire" 
                                   name="prix_unitaire" 
                                   required
                                   min="0"
                                   step="100"
                                   value="<?= htmlspecialchars($produit->getPrixUnitaire()) ?>"
                                   placeholder="0"
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition">
                        </div>
                    </div>

                    <div>
                        <label for="quantite_stock" class="block text-sm font-medium text-gray-700 mb-1">
                            Quantité en stock
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-boxes text-gray-400"></i>
                            </div>
                            <input type="number" 
                                   id="quantite_stock" 
                                   name="quantite_stock" 
                                   min="0"
                                   value="<?= htmlspecialchars($produit->getQuantiteStock()) ?>"
                                   placeholder="0"
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition">
                        </div>
                    </div>
                </div>

                <div>
                    <label for="unite" class="block text-sm font-medium text-gray-700 mb-1">
                        Unité de mesure
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-ruler text-gray-400"></i>
                        </div>
                        <select id="unite" 
                                name="unite" 
                                class="w-full pl-10 pr-10 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition appearance-none bg-white">
                            <?php foreach ($unites_disponibles as $value => $label): ?>
                                <option value="<?= $value ?>" <?= ($produit->getUnite() == $value) ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fas fa-chevron-down text-gray-400"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-500">
                    <i class="fas fa-clock mr-2"></i>
                    Créé le : <?= date('d/m/Y à H:i', strtotime($produit->getCreatedAt())) ?>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200">
                    <button type="submit" class="flex-1 btn-accent py-3 rounded-lg font-semibold text-base flex items-center justify-center">
                        <i class="fas fa-save mr-2"></i> Mettre à jour
                    </button>
                    <button type="reset" class="flex-1 btn-outline py-3 rounded-lg font-semibold text-center flex items-center justify-center">
                        <i class="fas fa-undo mr-2"></i> Réinitialiser
                    </button>
                    <a href="/pages/Commercant/dashboard.php#tab-produits" 
                       class="flex-1 btn-danger py-3 rounded-lg font-semibold text-center flex items-center justify-center">
                        <i class="fas fa-times mr-2"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('form-edit-product');
        const nomInput = document.getElementById('nom_produit');
        const prixInput = document.getElementById('prix_unitaire');
        
        nomInput.focus();
        
        form.addEventListener('submit', function(e) {
            const nom = nomInput.value.trim();
            const prix = prixInput.value;
            
            if (!nom) {
                e.preventDefault();
                alert('❌ Veuillez entrer un nom de produit.');
                nomInput.focus();
                nomInput.style.borderColor = '#ef4444';
                return false;
            }
            
            if (nom.length < 2) {
                e.preventDefault();
                alert('❌ Le nom du produit doit contenir au moins 2 caractères.');
                nomInput.focus();
                nomInput.style.borderColor = '#ef4444';
                return false;
            }
            
            if (!prix || parseFloat(prix) <= 0) {
                e.preventDefault();
                alert('❌ Veuillez entrer un prix valide (supérieur à 0).');
                prixInput.focus();
                prixInput.style.borderColor = '#ef4444';
                return false;
            }
            
            if (!confirm('Voulez-vous vraiment modifier ce produit ?')) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
        
        nomInput.addEventListener('input', function() {
            if (this.value.trim().length >= 2) {
                this.style.borderColor = '';
            }
        });
        
        prixInput.addEventListener('input', function() {
            if (this.value && parseFloat(this.value) > 0) {
                this.style.borderColor = '';
            }
        });
        
        prixInput.addEventListener('blur', function() {
            if (this.value && parseFloat(this.value) >= 0) {
                this.value = Math.round(parseFloat(this.value) / 100) * 100;
            }
        });
        
        document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Voulez-vous vraiment réinitialiser le formulaire ?')) {
                form.reset();
                nomInput.focus();
                nomInput.style.borderColor = '';
                prixInput.style.borderColor = '';
            }
        });
    });
</script>

</body>
</html>