<?php
// Page d'inscription commerçant
require_once __DIR__ . '/Classes/Commercant.php';

$page_title = 'Inscription - Marché Virunga';

// Si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commercant = new Commercant();
    
    // Préparer les données
    $data = [
        'nom_complet' => $_POST['nom_complet'] ?? '',
        'sexe' => $_POST['sexe'] ?? 'Masculin',
        'nationalite' => $_POST['nationalite'] ?? 'Congolaise',
        'date_naissance' => $_POST['date_naissance'] ?? null,
        'adresse' => $_POST['adresse'] ?? '',
        'nom_user' => $_POST['nom_user'] ?? '',
        'mot_de_passe' => $_POST['mot_de_passe'] ?? '',
        'telephone' => $_POST['telephone'] ?? '',
        'email' => $_POST['email'] ?? '',
        'produits_vendu' => $_POST['produits_vendu'] ?? ''
    ];
    
    // Appeler la méthode register de la classe Commercant
    $result = $commercant->register($data);
    
    if ($result['success']) {
        // Rediriger vers la page de connexion avec succès
        header('Location: login.php?success=register');
        exit;
    } else {
        // Afficher les erreurs
        $errors = $result['errors'] ?? [];
        $error_message = implode(', ', $errors);
        header('Location: register.php?error=' . urlencode($error_message));
        exit;
    }
}
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
        .bg-accent-hover:hover { background: #d97706; }
        .text-primary { color: #1e3a5f; }
        .text-accent { color: #f59e0b; }
        .border-accent { border-color: #f59e0b; }
        .btn-primary { background: #1e3a5f; color: white; transition: all 0.3s ease; }
        .btn-primary:hover { background: #2d6a9f; transform: scale(1.02); }
        .btn-accent { background: #f59e0b; color: white; transition: all 0.3s ease; }
        .btn-accent:hover { background: #d97706; transform: scale(1.02); }
        .input-field:focus { border-color: #f59e0b; outline: none; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2); }
        .auth-card { transition: all 0.3s ease; }
        .auth-card:hover { box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .step-indicator { display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 2rem; }
        .step { width: 10px; height: 10px; border-radius: 50%; background: #e5e7eb; transition: all 0.3s; }
        .step.active { background: #f59e0b; transform: scale(1.2); }
        .step.done { background: #1e3a5f; }
        .error-text { color: #dc2626; font-size: 0.875rem; margin-top: 0.25rem; }
    </style>
</head>
<body class="bg-gray-50">

<div class="min-h-screen flex items-center justify-center py-12 px-4">
    <div class="max-w-md w-full">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center space-x-2">
                <div class="w-12 h-12 bg-primary rounded-xl flex items-center justify-center">
                    <i class="fas fa-store text-white text-xl"></i>
                </div>
                <div>
                    <span class="text-2xl font-bold text-primary">Marché Virunga</span>
                    <span class="text-xs text-accent block -mt-1">Créer un compte</span>
                </div>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mt-6">Inscription</h2>
            <p class="text-gray-500 text-sm mt-1">Créez votre espace commerçant</p>
        </div>
        
        <!-- Message d'erreur -->
        <?php if (isset($_GET['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-4 text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php 
                    $error = urldecode($_GET['error']);
                    echo htmlspecialchars($error);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Formulaire d'inscription -->
        <div class="bg-white rounded-2xl shadow-lg p-8 auth-card">
            <!-- Indicateur d'étapes -->
            <div class="step-indicator">
                <div class="step active"></div>
                <div class="step"></div>
                <div class="step"></div>
            </div>
            
            <form method="POST" action="" id="registerForm">
                <!-- Étape 1 : Informations personnelles -->
                <div class="step-content" id="step1">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user text-accent mr-2"></i>Nom complet <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nom_complet" required 
                               class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg transition"
                               placeholder="Votre nom et prénom"
                               value="<?= htmlspecialchars($_POST['nom_complet'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-venus-mars text-accent mr-2"></i>Sexe
                        </label>
                        <select name="sexe" class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg transition">
                            <option value="Masculin" <?= (isset($_POST['sexe']) && $_POST['sexe'] === 'Masculin') ? 'selected' : '' ?>>Masculin</option>
                            <option value="Féminin" <?= (isset($_POST['sexe']) && $_POST['sexe'] === 'Féminin') ? 'selected' : '' ?>>Féminin</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-flag text-accent mr-2"></i>Nationalité
                        </label>
                        <input type="text" name="nationalite" 
                               class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg transition"
                               placeholder="Votre nationalité" 
                               value="<?= htmlspecialchars($_POST['nationalite'] ?? 'Congolaise') ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-calendar text-accent mr-2"></i>Date de naissance
                        </label>
                        <input type="date" name="date_naissance" 
                               class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg transition"
                               value="<?= htmlspecialchars($_POST['date_naissance'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt text-accent mr-2"></i>Adresse
                        </label>
                        <input type="text" name="adresse" 
                               class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg transition"
                               placeholder="Votre adresse"
                               value="<?= htmlspecialchars($_POST['adresse'] ?? '') ?>">
                    </div>
                    
                    <button type="button" onclick="nextStep()" 
                            class="btn-accent w-full py-3 rounded-lg font-bold">
                        Suivant <i class="fas fa-arrow-right ml-2"></i>
                    </button>
                </div>
                
                <!-- Étape 2 : Coordonnées -->
                <div class="step-content hidden" id="step2">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-phone text-accent mr-2"></i>Téléphone <span class="text-red-500">*</span>
                        </label>
                        <input type="tel" name="telephone" required 
                               class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg transition"
                               placeholder="+243 XX XXX XXXX"
                               value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-user-tag text-accent mr-2"></i>Nom d'utilisateur <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nom_user" required 
                               class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg transition"
                               placeholder="Choisissez un nom d'utilisateur"
                               value="<?= htmlspecialchars($_POST['nom_user'] ?? '') ?>">
                        <p class="text-xs text-gray-400 mt-1">Utilisé pour vous connecter</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-envelope text-accent mr-2"></i>Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="email" required 
                               class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg transition"
                               placeholder="votre@email.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-boxes text-accent mr-2"></i>Produits vendus
                        </label>
                        <input type="text" name="produits_vendu" 
                               class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg transition"
                               placeholder="Ex: Fruits, Légumes, Viande"
                               value="<?= htmlspecialchars($_POST['produits_vendu'] ?? '') ?>">
                    </div>
                    
                    <div class="flex gap-3">
                        <button type="button" onclick="prevStep()" 
                                class="btn-primary flex-1 py-3 rounded-lg font-bold">
                            <i class="fas fa-arrow-left mr-2"></i>Retour
                        </button>
                        <button type="button" onclick="nextStep()" 
                                class="btn-accent flex-1 py-3 rounded-lg font-bold">
                            Suivant <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Étape 3 : Sécurité -->
                <div class="step-content hidden" id="step3">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-lock text-accent mr-2"></i>Mot de passe <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="mot_de_passe" id="password" required 
                                   class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg transition"
                                   placeholder="Minimum 6 caractères">
                            <button type="button" onclick="togglePassword('password', 'eye-icon1')" 
                                    class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                                <i id="eye-icon1" class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="mt-2">
                            <div class="flex items-center space-x-2 text-xs">
                                <span id="password-strength" class="text-gray-400">Force : </span>
                                <div class="flex gap-1">
                                    <div id="bar1" class="w-8 h-1 bg-gray-200 rounded"></div>
                                    <div id="bar2" class="w-8 h-1 bg-gray-200 rounded"></div>
                                    <div id="bar3" class="w-8 h-1 bg-gray-200 rounded"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-check-circle text-accent mr-2"></i>Confirmer le mot de passe <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="confirm_password" id="confirm_password" required 
                                   class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg transition"
                                   placeholder="Confirmez votre mot de passe">
                            <button type="button" onclick="togglePassword('confirm_password', 'eye-icon2')" 
                                    class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                                <i id="eye-icon2" class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex gap-3">
                        <button type="button" onclick="prevStep()" 
                                class="btn-primary flex-1 py-3 rounded-lg font-bold">
                            <i class="fas fa-arrow-left mr-2"></i>Retour
                        </button>
                        <button type="submit" class="btn-accent flex-1 py-3 rounded-lg font-bold">
                            <i class="fas fa-user-plus mr-2"></i>S'inscrire
                        </button>
                    </div>
                    
                    <p class="text-xs text-gray-400 text-center mt-4">
                        <i class="fas fa-info-circle mr-1"></i>
                        En vous inscrivant, vous acceptez nos conditions générales
                    </p>
                </div>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Déjà inscrit ? 
                    <a href="login.php" class="text-primary font-semibold hover:text-accent transition">
                        Se connecter
                    </a>
                </p>
            </div>
            
            <div class="mt-4 text-center">
                <a href="index.php" class="text-sm text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-arrow-left mr-1"></i>Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    let currentStep = 1;
    const totalSteps = 3;
    
    // Navigation entre les étapes
    function nextStep() {
        // Valider l'étape 1 avant de passer
        if (currentStep === 1) {
            const nomComplet = document.querySelector('input[name="nom_complet"]').value;
            if (!nomComplet.trim()) {
                alert('Veuillez entrer votre nom complet.');
                return;
            }
        }
        
        // Valider l'étape 2 avant de passer
        if (currentStep === 2) {
            const telephone = document.querySelector('input[name="telephone"]').value;
            const nomUser = document.querySelector('input[name="nom_user"]').value;
            const email = document.querySelector('input[name="email"]').value;
            
            if (!telephone.trim()) {
                alert('Veuillez entrer votre numéro de téléphone.');
                return;
            }
            if (!nomUser.trim()) {
                alert('Veuillez choisir un nom d\'utilisateur.');
                return;
            }
            if (!email.trim()) {
                alert('Veuillez entrer votre email.');
                return;
            }
        }
        
        if (currentStep < totalSteps) {
            document.getElementById(`step${currentStep}`).classList.add('hidden');
            currentStep++;
            document.getElementById(`step${currentStep}`).classList.remove('hidden');
            updateSteps();
        }
    }
    
    function prevStep() {
        if (currentStep > 1) {
            document.getElementById(`step${currentStep}`).classList.add('hidden');
            currentStep--;
            document.getElementById(`step${currentStep}`).classList.remove('hidden');
            updateSteps();
        }
    }
    
    function updateSteps() {
        const steps = document.querySelectorAll('.step');
        steps.forEach((step, index) => {
            step.className = 'step';
            if (index + 1 === currentStep) step.classList.add('active');
            else if (index + 1 < currentStep) step.classList.add('done');
        });
    }
    
    // Afficher/masquer le mot de passe
    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
    
    // Vérification de la force du mot de passe
    document.getElementById('password').addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        if (password.length >= 6) strength++;
        if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/) || password.match(/[^a-zA-Z0-9]/)) strength++;
        
        const bars = ['bar1', 'bar2', 'bar3'];
        const colors = ['#ef4444', '#f59e0b', '#22c55e'];
        const labels = ['Faible', 'Moyen', 'Fort'];
        
        bars.forEach((barId, index) => {
            const bar = document.getElementById(barId);
            if (index < strength) {
                bar.style.background = colors[strength - 1];
            } else {
                bar.style.background = '#e5e7eb';
            }
        });
        
        document.getElementById('password-strength').textContent = 
            `Force : ${labels[strength - 1] || 'Faible'}`;
    });
</script>

</body>
</html>