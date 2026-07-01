<?php
session_start();
require_once __DIR__ . '/../../Classes/Administrateur.php';
require_once __DIR__ . '/../../Classes/Etalage.php';
require_once __DIR__ . '/../../Classes/Secteur.php';

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

$secteur = new Secteur();
$secteurs = $secteur->getAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'numero' => $_POST['numero'] ?? '',
        'localisation' => $_POST['localisation'] ?? '',
        'id_secteur' => $_POST['id_secteur'] ?? '',
        'statut' => $_POST['statut'] ?? 'disponible'
    ];
    
    if (empty($data['numero']) || empty($data['id_secteur'])) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        $etalage = new Etalage();
        $result = $etalage->create($data);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['error'];
        }
    }
}

$page_title = 'Ajouter un étalage - Admin';
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
        .input-focus:focus {
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
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
                <a href="gestion_etalages.php" class="text-sm hover:text-accent transition">
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

<div class="max-w-2xl mx-auto px-4 py-8">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h1 class="text-2xl font-bold text-primary mb-6">
            <i class="fas fa-plus-circle text-accent mr-2"></i>Ajouter un étalage
        </h1>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                <?= htmlspecialchars($success) ?>
                <a href="gestion_etalages.php" class="ml-4 text-green-700 font-semibold hover:text-green-900">Voir la liste</a>
            </div>
        <?php else: ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Numéro de l'étalage <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="numero" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg input-focus focus:outline-none"
                           placeholder="Ex: ET001">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Localisation
                    </label>
                    <input type="text" name="localisation"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg input-focus focus:outline-none"
                           placeholder="Ex: Côté Nord, allée centrale">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Secteur <span class="text-red-500">*</span>
                    </label>
                    <select name="id_secteur" required class="w-full px-4 py-2 border border-gray-300 rounded-lg input-focus focus:outline-none">
                        <option value="">Sélectionner un secteur</option>
                        <?php foreach ($secteurs as $s): ?>
                            <option value="<?= $s['id_secteur'] ?>"><?= htmlspecialchars($s['designation']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Statut initial
                    </label>
                    <select name="statut" class="w-full px-4 py-2 border border-gray-300 rounded-lg input-focus focus:outline-none">
                        <option value="disponible">Disponible</option>
                        <option value="en_attente">En attente</option>
                    </select>
                </div>
                
                <button type="submit" class="w-full btn-accent py-2 rounded-lg font-semibold">
                    <i class="fas fa-save mr-2"></i> Ajouter l'étalage
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>