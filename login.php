<?php
// Page de connexion universelle
require_once __DIR__ . '/Classes/Commercant.php';
require_once __DIR__ . '/Classes/AgentMarche.php';
require_once __DIR__ . '/Classes/Administrateur.php';
require_once __DIR__ . '/Classes/Database.php';

$page_title = 'Connexion - Marché Virunga';

// Initialiser la variable d'erreur
$error = null;
$error_detail = null;

// Si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom_user = $_POST['nom_user'] ?? '';
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validation
    if (empty($nom_user) || empty($mot_de_passe)) {
        $error = 'empty';
    } else {
        try {
            // 1. Vérifier d'abord si c'est un administrateur
            $admin = new Administrateur();
            $result = $admin->login($nom_user, $mot_de_passe);
            
            if ($result['success']) {
                // Rediriger vers le dashboard admin
                header('Location: /pages/Admin/dashboard.php');
                exit;
            }
            
            // 2. Vérifier si c'est un agent
            $agent = new AgentMarche();
            $result = $agent->login($nom_user, $mot_de_passe);
            
            if ($result['success']) {
                // Rediriger vers le dashboard agent
                header('Location: ./pages/Agent_marche/dashboard.php');
                exit;
            }
            
            // 3. Vérifier si c'est un commerçant
            $commercant = new Commercant();
            $result = $commercant->login($nom_user, $mot_de_passe);
            
            if ($result['success']) {
                // Rediriger vers le dashboard commerçant
                header('Location: ./pages/Commerçant/dashboard.php');
                exit;
            }
            
            // Si aucun rôle ne correspond
            $error = 'not_found';
            $error_detail = 'Aucun compte trouvé avec ces identifiants. Vérifiez vos informations.';
            
        } catch (Exception $e) {
            $error = 'unknown';
            $error_detail = $e->getMessage();
        }
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
        
        /* Rôle selector */
        .role-selector {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .role-btn {
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            border: 2px solid #e5e7eb;
            background: white;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .role-btn:hover {
            border-color: #f59e0b;
            color: #1e3a5f;
        }
        .role-btn.active {
            border-color: #f59e0b;
            background: #fffbeb;
            color: #1e3a5f;
        }
        .role-btn .icon { margin-right: 0.25rem; }
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
                    <span class="text-xs text-accent block -mt-1">Connexion</span>
                </div>
            </div>
            <h2 class="text-2xl font-bold text-gray-800 mt-6">Bienvenue</h2>
            <p class="text-gray-500 text-sm mt-1">Connectez-vous à votre espace</p>
        </div>
        
        <!-- Message d'erreur -->
        <?php if (isset($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-4 text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?php 
                    if ($error === 'empty') {
                        echo 'Veuillez remplir tous les champs.';
                    } elseif ($error === 'not_found') {
                        echo isset($error_detail) ? htmlspecialchars($error_detail) : 'Aucun compte trouvé avec ces identifiants.';
                    } elseif ($error === 'unknown') {
                        echo isset($error_detail) ? htmlspecialchars($error_detail) : 'Une erreur est survenue. Veuillez réessayer.';
                    } else {
                        echo 'Une erreur est survenue. Veuillez réessayer.';
                    }
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Message de succès (après inscription) -->
        <?php if (isset($_GET['success'])): ?>
            <?php if ($_GET['success'] === 'register'): ?>
                <div class="bg-green-50 border border-green-200 text-green-600 px-4 py-3 rounded-lg mb-4 text-sm">
                    <i class="fas fa-check-circle mr-2"></i>
                    Inscription réussie ! Connectez-vous maintenant.
                </div>
            <?php elseif ($_GET['success'] === 'logout'): ?>
                <div class="bg-blue-50 border border-blue-200 text-blue-600 px-4 py-3 rounded-lg mb-4 text-sm">
                    <i class="fas fa-info-circle mr-2"></i>
                    Vous avez été déconnecté avec succès.
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Formulaire de connexion -->
        <div class="bg-white rounded-2xl shadow-lg p-8 auth-card">
            
            <!-- Sélecteur de rôle (purement visuel) -->
            <div class="role-selector">
                <button type="button" class="role-btn active" onclick="selectRole('tous')" id="role-tous">
                    <i class="fas fa-users icon"></i>Tous
                </button>
                <button type="button" class="role-btn" onclick="selectRole('admin')" id="role-admin">
                    <i class="fas fa-user-shield icon"></i>Admin
                </button>
                <button type="button" class="role-btn" onclick="selectRole('agent')" id="role-agent">
                    <i class="fas fa-user-tie icon"></i>Agent
                </button>
                <button type="button" class="role-btn" onclick="selectRole('commercant')" id="role-commercant">
                    <i class="fas fa-user icon"></i>Commerçant
                </button>
            </div>
            
            <form method="POST" action="">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user text-accent mr-2"></i>Nom d'utilisateur ou Email
                    </label>
                    <input type="text" name="nom_user" required 
                           class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg transition"
                           placeholder="Entrez votre nom d'utilisateur ou email"
                           value="<?= htmlspecialchars($_POST['nom_user'] ?? '') ?>">
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-lock text-accent mr-2"></i>Mot de passe
                    </label>
                    <div class="relative">
                        <input type="password" name="mot_de_passe" id="password" required 
                               class="input-field w-full px-4 py-3 border border-gray-300 rounded-lg transition"
                               placeholder="Entrez votre mot de passe">
                        <button type="button" onclick="togglePassword()" 
                                class="absolute right-3 top-3 text-gray-400 hover:text-gray-600">
                            <i id="eye-icon" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <input type="checkbox" name="remember" id="remember" 
                               class="w-4 h-4 text-accent border-gray-300 rounded focus:ring-accent">
                        <label for="remember" class="ml-2 text-sm text-gray-600">Se souvenir de moi</label>
                    </div>
                    <a href="/pages/Client/forgot-password.php" class="text-sm text-accent hover:text-accent-hover font-medium">
                        Mot de passe oublié ?
                    </a>
                </div>
                
                <button type="submit" class="btn-accent w-full py-3 rounded-lg font-bold text-lg transition-all hover:shadow-lg">
                    <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Pas encore de compte ? 
                    <a href="register.php" class="text-primary font-semibold hover:text-accent transition">
                        Créer un compte
                    </a>
                </p>
            </div>
            
            <!-- Lien retour accueil -->
            <div class="mt-4 text-center">
                <a href="index.php" class="text-sm text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-arrow-left mr-1"></i>Retour à l'accueil
                </a>
            </div>
        </div>
        
        <!-- Mentions -->
        <div class="text-center mt-6 text-xs text-gray-400">
            <p>© <?= date('Y') ?> Marché Virunga - Gestion du marché</p>
        </div>
    </div>
</div>

<script>
    // Afficher/masquer le mot de passe
    function togglePassword() {
        const password = document.getElementById('password');
        const icon = document.getElementById('eye-icon');
        if (password.type === 'password') {
            password.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            password.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
    
    // Sélection du rôle (effet visuel uniquement)
    function selectRole(role) {
        // Mettre à jour les boutons
        document.querySelectorAll('.role-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.getElementById('role-' + role).classList.add('active');
        
        // Mettre à jour le placeholder du champ nom d'utilisateur
        const input = document.querySelector('input[name="nom_user"]');
        const placeholders = {
            'tous': 'Entrez votre nom d\'utilisateur ou email',
            'admin': 'Entrez votre nom d\'utilisateur (Admin)',
            'agent': 'Entrez votre nom d\'utilisateur (Agent)',
            'commercant': 'Entrez votre nom d\'utilisateur (Commerçant)'
        };
        input.placeholder = placeholders[role] || placeholders['tous'];
    }
</script>

</body>
</html>