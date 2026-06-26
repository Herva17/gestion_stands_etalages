<?php
session_start();
require_once __DIR__ . '/../../Classes/Commercant.php';
require_once __DIR__ . '/../../Classes/Database.php';

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
$id_user = $user->getIdUser();

// Récupérer les données
$etalages = $commercant->getEtalages($id_commercant);
$locations = $commercant->getLocations($id_commercant);
$produits = $commercant->getProduits($id_commercant);
$paiements = $commercant->getPaiements($id_commercant);

// Récupérer tous les étalages disponibles (pour la location)
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT e.*, s.designation as secteur_nom 
    FROM etalage e
    LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
    WHERE e.statut = 'disponible' OR e.id_commercant IS NULL
    ORDER BY e.numero
");
$stmt->execute();
$etalages_disponibles = $stmt->fetchAll();

// Statistiques
$total_etalages = count($etalages);
$total_produits = count($produits);
$total_locations = count($locations);
$total_paiements = array_sum(array_column($paiements, 'montant'));

$page_title = 'Dashboard - Marché Virunga';
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
        
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        
        .product-card { transition: all 0.3s ease; }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        
        .etalage-card { transition: all 0.3s ease; }
        .etalage-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        
        .tab-active { border-bottom: 3px solid #f59e0b; color: #1e3a5f; font-weight: 600; }
        .tab-inactive { color: #6b7280; border-bottom: 3px solid transparent; }
        .tab-inactive:hover { color: #1e3a5f; border-bottom-color: #d1d5db; }
        
        .modal-overlay { background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); }
        
        /* Scrollbar personnalisée */
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
                <a href="/api/auth/logout.php" class="text-sm bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg transition flex items-center">
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
<div class="max-w-7xl mx-auto px-4 py-6">

    <!-- En-tête avec bienvenue -->
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6 border-l-4 border-accent">
        <div class="flex flex-wrap justify-between items-center">
            <div>
                <p class="text-sm text-gray-500">Bonjour,</p>
                <h1 class="text-2xl font-bold text-primary">
                    <?= htmlspecialchars($user->getNomComplet()) ?>
                </h1>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-id-card mr-1"></i>
                    Matricule: <?= htmlspecialchars($user->getMatricule()) ?>
                    <?php if ($user->getProduitsVendu()): ?>
                        <span class="ml-3 bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full text-xs">
                            <i class="fas fa-boxes mr-1"></i>
                            <?= htmlspecialchars($user->getProduitsVendu()) ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex flex-wrap gap-2 mt-2 sm:mt-0">
                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm flex items-center">
                    <i class="fas fa-check-circle mr-1"></i> Compte actif
                </span>
                <?php if ($commercant->hasActiveLocation($id_commercant)): ?>
                    <span class="bg-accent/20 text-accent-700 px-3 py-1 rounded-full text-sm flex items-center">
                        <i class="fas fa-store mr-1"></i> Location active
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Mes étalages</p>
                    <p class="text-2xl font-bold text-primary"><?= $total_etalages ?></p>
                </div>
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-warehouse text-blue-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Mes produits</p>
                    <p class="text-2xl font-bold text-primary"><?= $total_produits ?></p>
                </div>
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-boxes text-green-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Locations</p>
                    <p class="text-2xl font-bold text-primary"><?= $total_locations ?></p>
                </div>
                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-handshake text-yellow-600"></i>
                </div>
            </div>
        </div>
        <div class="stat-card bg-white rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Total payé</p>
                    <p class="text-2xl font-bold text-accent"><?= number_format($total_paiements, 0, ',', ' ') ?> FCFA</p>
                </div>
                <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-coins text-red-600"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="border-b border-gray-200">
            <nav class="flex flex-wrap -mb-px" id="tab-nav">
                <button onclick="showTab('etalages')" class="tab-active px-6 py-3 text-sm font-medium transition" id="tab-etalages">
                    <i class="fas fa-warehouse mr-2"></i>Mes étalages
                </button>
                <button onclick="showTab('produits')" class="tab-inactive px-6 py-3 text-sm font-medium transition" id="tab-produits">
                    <i class="fas fa-boxes mr-2"></i>Mes produits
                </button>
                <button onclick="showTab('disponibles')" class="tab-inactive px-6 py-3 text-sm font-medium transition" id="tab-disponibles">
                    <i class="fas fa-store mr-2"></i>Étalages disponibles
                </button>
                <button onclick="showTab('locations')" class="tab-inactive px-6 py-3 text-sm font-medium transition" id="tab-locations">
                    <i class="fas fa-handshake mr-2"></i>Mes locations
                </button>
            </nav>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- TAB 1: MES ÉTALAGES -->
    <!-- ============================================ -->
    <div id="content-etalages" class="tab-content">
        <?php if (count($etalages) > 0): ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($etalages as $etalage): ?>
                    <div class="etalage-card bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                        <div class="p-5">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-bold text-primary text-lg">
                                        Étalage #<?= htmlspecialchars($etalage['numero']) ?>
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        <i class="fas fa-map-pin mr-1"></i>
                                        <?= htmlspecialchars($etalage['localisation'] ?? 'Non spécifiée') ?>
                                    </p>
                                </div>
                                <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-xs font-semibold">
                                    <i class="fas fa-check-circle mr-1"></i> Occupé
                                </span>
                            </div>
                            <div class="space-y-2 text-sm">
                                <p class="text-gray-600">
                                    <i class="fas fa-tag text-accent mr-2"></i>
                                    Secteur: <?= htmlspecialchars($etalage['secteur_nom'] ?? 'Non défini') ?>
                                </p>
                                <p class="text-gray-600">
                                    <i class="fas fa-calendar text-accent mr-2"></i>
                                    Statut: <span class="font-medium text-green-600">Actif</span>
                                </p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-gray-100 flex gap-2">
                                <button onclick="viewEtalage(<?= $etalage['id_etalage'] ?>)" 
                                        class="flex-1 btn-primary text-sm py-1.5 rounded-lg">
                                    <i class="fas fa-eye mr-1"></i> Voir
                                </button>
                                <button onclick="editEtalage(<?= $etalage['id_etalage'] ?>)" 
                                        class="flex-1 btn-outline text-sm py-1.5 rounded-lg">
                                    <i class="fas fa-edit mr-1"></i> Modifier
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-warehouse"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Aucun étalage</h3>
                <p class="text-gray-500 mb-4">Vous n'avez pas encore d'étalage. Consultez les étalages disponibles.</p>
                <button onclick="showTab('disponibles')" class="btn-accent px-6 py-2 rounded-lg font-semibold">
                    <i class="fas fa-search mr-2"></i> Voir les étalages disponibles
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- TAB 2: MES PRODUITS -->
    <!-- ============================================ -->
    <div id="content-produits" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-boxes text-accent mr-2"></i>Mes produits
            </h2>
            <button onclick="openModal('addProduct')" class="btn-accent px-4 py-2 rounded-lg font-semibold flex items-center">
                <i class="fas fa-plus mr-2"></i> Ajouter un produit
            </button>
        </div>

        <?php if (count($produits) > 0): ?>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($produits as $produit): ?>
                    <div class="product-card bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-bold text-primary"><?= htmlspecialchars($produit['nom_produit']) ?></h4>
                            <?php if ($produit['quantite_stock'] > 0): ?>
                                <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-xs">En stock</span>
                            <?php else: ?>
                                <span class="bg-red-100 text-red-700 px-2 py-0.5 rounded-full text-xs">Rupture</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-gray-600 line-clamp-2"><?= htmlspecialchars($produit['description'] ?? '') ?></p>
                        <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between items-center">
                            <div>
                                <span class="text-lg font-bold text-accent"><?= number_format($produit['prix_unitaire'], 0, ',', ' ') ?> FCFA</span>
                                <p class="text-xs text-gray-500"><?= htmlspecialchars($produit['unite'] ?? 'pièce') ?></p>
                            </div>
                            <div class="flex gap-1">
                                <button onclick="editProduct(<?= $produit['id_produit'] ?>)" 
                                        class="text-blue-600 hover:text-blue-800 p-1">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteProduct(<?= $produit['id_produit'] ?>)" 
                                        class="text-red-600 hover:text-red-800 p-1">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mt-2 text-xs text-gray-400">
                            Stock: <?= $produit['quantite_stock'] ?> <?= htmlspecialchars($produit['unite'] ?? '') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-boxes"></i>
                </div>
                <h3 className="text-xl font-semibold text-gray-700 mb-2">Aucun produit</h3>
                <p class="text-gray-500 mb-4">Commencez à ajouter vos produits à vendre.</p>
                <button onclick="openModal('addProduct')" class="btn-accent px-6 py-2 rounded-lg font-semibold">
                    <i class="fas fa-plus mr-2"></i> Ajouter un produit
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- TAB 3: ÉTALAGES DISPONIBLES -->
    <!-- ============================================ -->
    <div id="content-disponibles" class="tab-content hidden">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">
                <i class="fas fa-store text-accent mr-2"></i>Étalages disponibles à la location
            </h2>
            <span class="text-sm text-gray-500"><?= count($etalages_disponibles) ?> étalages disponibles</span>
        </div>

        <?php if (count($etalages_disponibles) > 0): ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($etalages_disponibles as $etalage): ?>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden border-2 border-accent/30">
                        <div class="p-5">
                            <div class="flex justify-between items-start mb-3">
                                <div>
                                    <h3 class="font-bold text-primary text-lg">
                                        Étalage #<?= htmlspecialchars($etalage['numero']) ?>
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        <i class="fas fa-map-pin mr-1"></i>
                                        <?= htmlspecialchars($etalage['localisation'] ?? 'Non spécifiée') ?>
                                    </p>
                                </div>
                                <span class="bg-accent/20 text-accent-700 px-2 py-0.5 rounded-full text-xs font-semibold animate-pulse">
                                    <i class="fas fa-circle mr-1 text-accent"></i> Disponible
                                </span>
                            </div>
                            <div class="space-y-2 text-sm">
                                <p class="text-gray-600">
                                    <i class="fas fa-tag text-accent mr-2"></i>
                                    Secteur: <?= htmlspecialchars($etalage['secteur_nom'] ?? 'Non défini') ?>
                                </p>
                                <p class="text-gray-600">
                                    <i class="fas fa-check-circle text-accent mr-2"></i>
                                    Prêt à être loué
                                </p>
                            </div>
                            <button onclick="demanderLocation(<?= $etalage['id_etalage'] ?>)" 
                                    class="w-full mt-4 btn-accent py-2 rounded-lg font-semibold">
                                <i class="fas fa-handshake mr-2"></i> Demander la location
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-store"></i>
                </div>
                <h3 className="text-xl font-semibold text-gray-700 mb-2">Aucun étalage disponible</h3>
                <p class="text-gray-500">Tous les étalages sont actuellement occupés. Revenez plus tard.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- TAB 4: MES LOCATIONS -->
    <!-- ============================================ -->
    <div id="content-locations" class="tab-content hidden">
        <h2 class="text-xl font-bold text-primary mb-4">
            <i class="fas fa-handshake text-accent mr-2"></i>Mes locations
        </h2>

        <?php if (count($locations) > 0): ?>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Étalage</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Montant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Début</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fin</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($locations as $location): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        #<?= htmlspecialchars($location['etalage_numero']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-bold text-accent">
                                        <?= number_format($location['montant_location'], 0, ',', ' ') ?> FCFA
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= date('d/m/Y', strtotime($location['date_debut'])) ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        <?= date('d/m/Y', strtotime($location['date_fin'])) ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php if (strtotime($location['date_fin']) >= time()): ?>
                                            <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-xs font-semibold">
                                                <i class="fas fa-check-circle mr-1"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded-full text-xs font-semibold">
                                                <i class="fas fa-clock mr-1"></i> Expirée
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-handshake"></i>
                </div>
                <h3 className="text-xl font-semibold text-gray-700 mb-2">Aucune location</h3>
                <p class="text-gray-500">Vous n'avez pas encore de location active.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- ============================================ -->
<!-- MODAL: AJOUTER UN PRODUIT -->
<!-- ============================================ -->
<div id="modal-addProduct" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-primary">
                <i class="fas fa-plus-circle text-accent mr-2"></i>Ajouter un produit
            </h3>
            <button onclick="closeModal('addProduct')" class="text-gray-400 hover:text-gray-600 text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="form-addProduct" method="POST" action="/api/produits/ajouter.php">
            <input type="hidden" name="id_commercant" value="<?= $id_commercant ?>">
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nom du produit <span class="text-red-500">*</span></label>
                <input type="text" name="nom_produit" required 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
            </div>
            
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="2" 
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none"></textarea>
            </div>
            
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Prix unitaire (FCFA) <span class="text-red-500">*</span></label>
                    <input type="number" name="prix_unitaire" required min="0" step="100"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantité en stock</label>
                    <input type="number" name="quantite_stock" min="0" value="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Unité de mesure</label>
                <select name="unite" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:border-accent focus:outline-none">
                    <option value="pièce">Pièce</option>
                    <option value="kg">Kilogramme (kg)</option>
                    <option value="g">Gramme (g)</option>
                    <option value="litre">Litre</option>
                    <option value="sac">Sac</option>
                    <option value="botte">Botte</option>
                    <option value="douzaine">Douzaine</option>
                </select>
            </div>
            
            <button type="submit" class="w-full btn-accent py-2 rounded-lg font-semibold">
                <i class="fas fa-save mr-2"></i> Ajouter le produit
            </button>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- MODAL: DEMANDER LOCATION -->
<!-- ============================================ -->
<div id="modal-location" class="fixed inset-0 modal-overlay hidden items-center justify-center z-50">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-primary">
                <i class="fas fa-handshake text-accent mr-2"></i>Demander la location
            </h3>
            <button onclick="closeModal('location')" class="text-gray-400 hover:text-gray-600 text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <p class="text-gray-600 mb-4">Confirmez votre demande de location pour cet étalage.</p>
        
        <form id="form-location" method="POST" action="/api/locations/demander.php">
            <input type="hidden" name="id_commercant" value="<?= $id_commercant ?>">
            <input type="hidden" name="id_etalage" id="location_etalage_id">
            
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <p class="text-sm text-gray-600">
                    <i class="fas fa-info-circle text-accent mr-1"></i>
                    Un agent du marché vous contactera pour finaliser la location.
                </p>
            </div>
            
            <button type="submit" class="w-full btn-accent py-2 rounded-lg font-semibold">
                <i class="fas fa-paper-plane mr-2"></i> Confirmer la demande
            </button>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- SCRIPTS -->
<!-- ============================================ -->
<script>
    // Gestion des tabs
    function showTab(tabName) {
        // Cacher tous les contenus
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
        });
        
        // Afficher le contenu sélectionné
        document.getElementById('content-' + tabName).classList.remove('hidden');
        
        // Mettre à jour les onglets
        document.querySelectorAll('#tab-nav button').forEach(btn => {
            btn.className = 'tab-inactive px-6 py-3 text-sm font-medium transition';
        });
        document.getElementById('tab-' + tabName).className = 'tab-active px-6 py-3 text-sm font-medium transition';
    }
    
    // Gestion des modals
    function openModal(modalId) {
        document.getElementById('modal-' + modalId).classList.remove('hidden');
        document.getElementById('modal-' + modalId).classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal(modalId) {
        document.getElementById('modal-' + modalId).classList.add('hidden');
        document.getElementById('modal-' + modalId).classList.remove('flex');
        document.body.style.overflow = 'auto';
    }
    
    // Demander une location
    function demanderLocation(etalageId) {
        document.getElementById('location_etalage_id').value = etalageId;
        openModal('location');
    }
    
    // Fermer les modals en cliquant à l'extérieur
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
                this.classList.remove('flex');
                document.body.style.overflow = 'auto';
            }
        });
    });
    
    // Voir un étalage
    function viewEtalage(id) {
        alert('Fonctionnalité à venir: Visualisation de l\'étalage #' + id);
    }
    
    // Modifier un étalage
    function editEtalage(id) {
        alert('Fonctionnalité à venir: Modification de l\'étalage #' + id);
    }
    
    // Modifier un produit
    function editProduct(id) {
        alert('Fonctionnalité à venir: Modification du produit #' + id);
    }
    
    // Supprimer un produit
    function deleteProduct(id) {
        if (confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')) {
            window.location.href = '/api/produits/supprimer.php?id=' + id;
        }
    }
    
    // Soumission du formulaire produit
    document.getElementById('form-addProduct').addEventListener('submit', function(e) {
        // Validation supplémentaire
        const nom = this.querySelector('input[name="nom_produit"]').value.trim();
        const prix = this.querySelector('input[name="prix_unitaire"]').value;
        
        if (!nom) {
            e.preventDefault();
            alert('Veuillez entrer un nom de produit.');
            return false;
        }
        if (!prix || prix <= 0) {
            e.preventDefault();
            alert('Veuillez entrer un prix valide.');
            return false;
        }
    });
    
    // Soumission du formulaire location
    document.getElementById('form-location').addEventListener('submit', function(e) {
        const idEtalage = this.querySelector('input[name="id_etalage"]').value;
        if (!idEtalage) {
            e.preventDefault();
            alert('Veuillez sélectionner un étalage.');
            return false;
        }
    });
</script>

</body>
</html>