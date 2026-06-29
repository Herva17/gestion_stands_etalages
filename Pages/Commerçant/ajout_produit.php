<?php
session_start();
require_once __DIR__ . '/../../Classes/Commercant.php';
require_once __DIR__ . '/../../Classes/Produit.php';

// Déterminer automatiquement le chemin de base
$script_path = $_SERVER['SCRIPT_NAME'];
$path_parts = explode('/', $script_path);
$base_path = '';
if (in_array('pages', $path_parts)) {
    $key = array_search('pages', $path_parts);
    $base_path = implode('/', array_slice($path_parts, 0, $key));
}
define('BASE_URL', $base_path);

// Vérifier si l'utilisateur est connecté
$commercant = new Commercant();
if (!$commercant->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/Client/login.php');
    exit;
}

// Récupérer les données du commerçant connecté
$user = $commercant->getLoggedInUser();
if (!$user) {
    session_destroy();
    header('Location: ' . BASE_URL . '/pages/Client/login.php?error=session_expired');
    exit;
}

$id_commercant = $user->getIdCommercant();

// Traitement du formulaire (TOUT SE PASSE ICI)
$message = '';
$message_type = '';
$produit_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produitObj = new Produit();
    
    $data = [
        'nom_produit' => trim($_POST['nom_produit'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'prix_unitaire' => floatval($_POST['prix_unitaire'] ?? 0),
        'quantite_stock' => intval($_POST['quantite_stock'] ?? 0),
        'unite' => trim($_POST['unite'] ?? 'pièce'),
        'id_commercant' => $id_commercant
    ];
    
    // Validation côté serveur
    if (empty($data['nom_produit'])) {
        $message = '❌ Le nom du produit est requis';
        $message_type = 'error';
        $produit_data = (object) $data;
    } elseif ($data['prix_unitaire'] <= 0) {
        $message = '❌ Le prix unitaire doit être supérieur à 0';
        $message_type = 'error';
        $produit_data = (object) $data;
    } else {
        $result = $produitObj->create($data);
        
        if ($result['success']) {
            $message = '✅ ' . $result['message'];
            $message_type = 'success';
            $produit_data = null; // Réinitialiser le formulaire
        } else {
            $message = '❌ ' . $result['error'];
            $message_type = 'error';
            $produit_data = (object) $data;
        }
    }
}

// Récupérer les unités disponibles
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

// Récupérer les derniers produits
$produitObj = new Produit();
$derniers_produits = $produitObj->getByCommercant($id_commercant);
$derniers_produits = array_slice($derniers_produits, 0, 5);

$page_title = 'Ajouter un produit - Marché Virunga';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
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
        
        .animated {
            animation: fadeInUp 0.5s ease forwards;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #d97706; }
    </style>
</head>
<body class="bg-gray-50">

<!-- ============================================ -->
<!-- NAVIGATION -->
<!-- ============================================ -->
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
                <a href="<?= BASE_URL ?>/api/auth/logout.php" class="text-sm bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition flex items-center">
                    <i class="fas fa-sign-out-alt mr-1"></i>
                    <span class="hidden sm:inline">Déconnexion</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- ============================================ -->
<!-- CONTENU PRINCIPAL -->
<!-- ============================================ -->
<div class="max-w-4xl mx-auto px-4 py-8">

    <!-- En-tête -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-primary">
                <i class="fas fa-plus-circle text-accent mr-2"></i>Ajouter un produit
            </h1>
            <p class="text-gray-500 text-sm mt-1">
                Remplissez le formulaire ci-dessous pour ajouter un nouveau produit à votre catalogue.
            </p>
        </div>
        <a href="<?= BASE_URL ?>/pages/Commercant/dashboard.php#tab-produits" 
           class="btn-primary px-4 py-2 rounded-lg text-sm font-semibold flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Retour au dashboard
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

    <!-- Formulaire - Action vers la même page -->
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden form-card border border-gray-100 animated">
        <div class="p-6 md:p-8">
            <form id="form-add-product" method="POST" action="" class="space-y-6">
                
                <!-- Nom du produit -->
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
                               value="<?= htmlspecialchars($produit_data->nom_produit ?? '') ?>"
                               placeholder="Ex: Tomates fraîches, Poulet fermier, etc."
                               class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Minimum 2 caractères</p>
                </div>

                <!-- Description -->
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
                                  placeholder="Décrivez votre produit (origine, qualité, particularités...)"
                                  class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition resize-y"><?= htmlspecialchars($produit_data->description ?? '') ?></textarea>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Description optionnelle</p>
                </div>

                <!-- Prix et Quantité -->
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
                                   value="<?= htmlspecialchars($produit_data->prix_unitaire ?? '') ?>"
                                   placeholder="0"
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Prix en Francs CFA (FCFA)</p>
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
                                   value="<?= htmlspecialchars($produit_data->quantite_stock ?? 0) ?>"
                                   placeholder="0"
                                   class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg input-focus focus:outline-none transition">
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Quantité disponible actuellement</p>
                    </div>
                </div>

                <!-- Unité de mesure -->
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
                                <option value="<?= $value ?>" <?= (($produit_data->unite ?? 'pièce') == $value) ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <i class="fas fa-chevron-down text-gray-400"></i>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Sélectionnez l'unité de mesure du produit</p>
                </div>

                <!-- Boutons d'action -->
                <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200">
                    <button type="submit" class="flex-1 btn-accent py-3 rounded-lg font-semibold text-base flex items-center justify-center">
                        <i class="fas fa-save mr-2"></i> Ajouter le produit
                    </button>
                    <button type="reset" class="flex-1 btn-outline py-3 rounded-lg font-semibold text-center flex items-center justify-center">
                        <i class="fas fa-undo mr-2"></i> Réinitialiser
                    </button>
                    <a href="<?= BASE_URL ?>/pages/Commercant/dashboard.php#tab-produits" 
                       class="flex-1 btn-danger py-3 rounded-lg font-semibold text-center flex items-center justify-center">
                        <i class="fas fa-times mr-2"></i> Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Conseils -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-start">
            <i class="fas fa-lightbulb text-blue-600 mt-0.5 mr-3 text-lg"></i>
            <div>
                <h4 class="font-semibold text-blue-800 text-sm">Conseils pour bien ajouter un produit</h4>
                <ul class="text-sm text-blue-700 mt-1 space-y-1">
                    <li>• Utilisez un nom clair et descriptif pour que vos clients puissent facilement trouver votre produit</li>
                    <li>• Indiquez le prix exact en FCFA, sans symboles ou espaces</li>
                    <li>• La description peut contenir des informations comme l'origine, la qualité, les certifications, etc.</li>
                    <li>• Mettez à jour régulièrement la quantité en stock pour éviter les ruptures</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Derniers produits ajoutés -->
    <?php if (!empty($derniers_produits)): ?>
    <div class="mt-8">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">
                <i class="fas fa-history mr-2"></i> Derniers produits ajoutés
            </h3>
            <span class="text-xs text-gray-400"><?= count($derniers_produits) ?> produits</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php foreach ($derniers_produits as $p): ?>
                <div class="bg-white rounded-lg shadow-sm p-3 border border-gray-100 flex items-center hover:shadow-md transition">
                    <div class="w-10 h-10 bg-accent/10 rounded-full flex items-center justify-center mr-3 flex-shrink-0">
                        <i class="fas fa-box text-accent"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-sm text-gray-800 truncate"><?= htmlspecialchars($p['nom_produit']) ?></p>
                        <p class="text-xs text-gray-500">
                            <?= number_format($p['prix_unitaire'], 0, ',', ' ') ?> FCFA
                            <span class="mx-1">•</span>
                            Stock: <?= $p['quantite_stock'] ?> <?= htmlspecialchars($p['unite'] ?? '') ?>
                        </p>
                    </div>
                    <span class="text-xs text-green-500 ml-2">
                        <i class="fas fa-check-circle"></i>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- ============================================ -->
<!-- SCRIPTS -->
<!-- ============================================ -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('form-add-product');
        const nomInput = document.getElementById('nom_produit');
        const prixInput = document.getElementById('prix_unitaire');
        
        // Focus sur le premier champ
        nomInput.focus();
        
        // Validation du formulaire
        form.addEventListener('submit', function(e) {
            const nom = nomInput.value.trim();
            const prix = prixInput.value;
            
            // Validation du nom
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
            
            // Validation du prix
            if (!prix || parseFloat(prix) <= 0) {
                e.preventDefault();
                alert('❌ Veuillez entrer un prix valide (supérieur à 0).');
                prixInput.focus();
                prixInput.style.borderColor = '#ef4444';
                return false;
            }
            
            // Si tout est valide, on soumet
            return true;
        });
        
        // Supprimer le style d'erreur quand l'utilisateur corrige
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
        
        // Auto-formatage du prix (arrondi à la centaine)
        prixInput.addEventListener('blur', function() {
            if (this.value && parseFloat(this.value) >= 0) {
                this.value = Math.round(parseFloat(this.value) / 100) * 100;
            }
        });
        
        // Réinitialisation du formulaire
        document.querySelector('button[type="reset"]').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Voulez-vous vraiment réinitialiser le formulaire ?')) {
                form.reset();
                nomInput.focus();
                // Réinitialiser les styles
                nomInput.style.borderColor = '';
                prixInput.style.borderColor = '';
            }
        });
        
        // Auto-complétion du prix avec des suggestions
        const prixSuggestions = [500, 1000, 1500, 2000, 2500, 3000, 5000, 10000];
        prixInput.addEventListener('focus', function() {
            // Si le champ est vide, suggérer un prix
            if (!this.value) {
                this.placeholder = 'Ex: 1000, 2000, 5000...';
            }
        });
    });
</script>

</body>
</html>