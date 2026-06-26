<?php
// Page d'accueil du Marché Virunga - Location d'étalages
$page_title = 'Marché Virunga - Location d\'étalages à Goma';
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
        
        /* Une seule couleur principale : Bleu */
        .bg-primary { background: #1e3a5f; }
        .bg-primary-light { background: #2d6a9f; }
        .text-primary { color: #1e3a5f; }
        .border-primary { border-color: #1e3a5f; }
        
        /* Une seule couleur d'accent : Orange */
        .bg-accent { background: #f59e0b; }
        .bg-accent-hover:hover { background: #d97706; }
        .text-accent { color: #f59e0b; }
        .border-accent { border-color: #f59e0b; }
        
        /* Hero avec dégradé simple */
        .hero-gradient {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d6a9f 100%);
        }
        
        /* Cards simplifiées */
        .card-simple {
            transition: all 0.3s ease;
            border: 1px solid #e5e7eb;
        }
        .card-simple:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: #f59e0b;
        }
        
        /* Boutons */
        .btn-primary {
            background: #1e3a5f;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: #2d6a9f;
            transform: scale(1.02);
        }
        
        .btn-accent {
            background: #f59e0b;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-accent:hover {
            background: #d97706;
            transform: scale(1.02);
        }
        
        .btn-outline {
            border: 2px solid #1e3a5f;
            color: #1e3a5f;
            transition: all 0.3s ease;
        }
        .btn-outline:hover {
            background: #1e3a5f;
            color: white;
        }
        
        /* Bannière */
        .banner {
            background: #f59e0b;
            color: #1e3a5f;
        }
        
        /* Animation simple */
        .float-slow {
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        
        /* Menu actif */
        .nav-link.active {
            color: #f59e0b;
            border-bottom: 2px solid #f59e0b;
        }
    </style>
</head>
<body>

<!-- ============================================ -->
<!-- BANNIÈRE SIMPLE -->
<!-- ============================================ -->
<div class="banner text-center py-2 text-sm font-semibold">
    <i class="fas fa-store mr-2"></i>
    🏪 LOCATION D'ÉTALAGES - Espaces disponibles
    <a href="login.php" class="ml-3 bg-white text-primary px-4 py-1 rounded-full hover:bg-gray-100 transition">
        Réserver
    </a>
</div>

<!-- ============================================ -->
<!-- NAVIGATION -->
<!-- ============================================ -->
<nav class="fixed top-0 left-0 right-0 z-50 bg-white shadow-sm border-b" style="margin-top: 36px;">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <div class="flex items-center space-x-2">
                <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
                    <i class="fas fa-store text-white"></i>
                </div>
                <div>
                    <span class="text-xl font-bold text-primary">Marché Virunga</span>
                    <span class="text-xs text-accent block -mt-1">Location d'étalages</span>
                </div>
            </div>
            
            <!-- Menu -->
            <div class="hidden md:flex items-center space-x-6">
                <a href="#accueil" class="text-gray-600 hover:text-accent font-medium">Accueil</a>
                <a href="#location" class="text-accent font-bold border-b-2 border-accent">📍 Location</a>
                <a href="#services" class="text-gray-600 hover:text-accent font-medium">Services</a>
                <a href="#contact" class="text-gray-600 hover:text-accent font-medium">Contact</a>
            </div>
            
            <!-- Boutons -->
            <div class="flex items-center space-x-2">
                <a href="login.php" class="hidden md:inline-block px-4 py-2 text-primary border-2 border-primary rounded-lg font-semibold hover:bg-primary hover:text-white transition">
                    Connexion
                </a>
                <a href="register.php" class="hidden md:inline-block px-4 py-2 btn-accent rounded-lg font-semibold">
                    Inscription
                </a>
                <button id="menu-toggle" class="md:hidden text-gray-600 text-xl">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Menu Mobile -->
    <div id="mobile-menu" class="hidden md:hidden bg-white border-t py-3">
        <div class="max-w-7xl mx-auto px-4 space-y-2">
            <a href="#accueil" class="block text-gray-600 hover:text-accent py-2">Accueil</a>
            <a href="#location" class="block text-accent font-bold py-2">📍 Location</a>
            <a href="#services" class="block text-gray-600 hover:text-accent py-2">Services</a>
            <a href="#contact" class="block text-gray-600 hover:text-accent py-2">Contact</a>
            <hr>
            <a href="/pages/Client/login.php" class="block text-center py-2 text-primary border border-primary rounded-lg">Connexion</a>
            <a href="/pages/Client/register.php" class="block text-center py-2 btn-accent rounded-lg">Inscription</a>
        </div>
    </div>
</nav>

<!-- ============================================ -->
<!-- HERO -->
<!-- ============================================ -->
<section id="accueil" class="hero-gradient min-h-screen flex items-center pt-24">
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="grid md:grid-cols-2 gap-12 items-center">
            <div>
                <div class="inline-block bg-white/20 px-4 py-1 rounded-full text-white text-sm mb-6">
                    🏪 Location d'étalages
                </div>
                
                <h1 class="text-4xl md:text-5xl font-extrabold text-white leading-tight">
                    Louez votre
                    <span class="text-accent block mt-1">étalage au marché</span>
                </h1>
                
                <p class="text-blue-100 text-lg mt-4 max-w-lg">
                    Des espaces commerciaux modernes et sécurisés au cœur du Marché Virunga à Goma.
                </p>
                
                <div class="flex flex-wrap gap-3 mt-6">
                    <a href="#location" class="btn-accent px-6 py-3 rounded-xl font-bold flex items-center">
                        <i class="fas fa-store mr-2"></i> Voir les étalages
                    </a>
                    <a href="#contact" class="bg-white/20 hover:bg-white/30 text-white px-6 py-3 rounded-xl border border-white/30 transition flex items-center">
                        <i class="fas fa-phone mr-2"></i> Nous contacter
                    </a>
                </div>
                
                <div class="flex items-center space-x-6 mt-8">
                    <div>
                        <div class="flex items-center text-white">
                            <i class="fas fa-warehouse text-accent mr-2"></i>
                            <span class="text-2xl font-bold">300+</span>
                        </div>
                        <p class="text-blue-200 text-sm">Étalages</p>
                    </div>
                    <div>
                        <div class="flex items-center text-white">
                            <i class="fas fa-users text-accent mr-2"></i>
                            <span class="text-2xl font-bold">150+</span>
                        </div>
                        <p class="text-blue-200 text-sm">Commerçants</p>
                    </div>
                </div>
            </div>
            
            <!-- Image simple -->
            <div class="hidden md:block">
                <div class="bg-white/10 backdrop-blur rounded-2xl p-6 border border-white/20 float-slow">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-white/20 rounded-xl p-4 text-center">
                            <i class="fas fa-store text-4xl text-accent"></i>
                            <p class="text-white text-sm mt-1 font-semibold">Étalages</p>
                            <span class="text-accent text-xs">📍 Disponible</span>
                        </div>
                        <div class="bg-white/20 rounded-xl p-4 text-center mt-6">
                            <i class="fas fa-utensils text-4xl text-accent"></i>
                            <p class="text-white text-sm mt-1 font-semibold">Restauration</p>
                            <span class="text-accent text-xs">📍 Disponible</span>
                        </div>
                        <div class="bg-white/20 rounded-xl p-4 text-center">
                            <i class="fas fa-fish text-4xl text-accent"></i>
                            <p class="text-white text-sm mt-1 font-semibold">Poissonnerie</p>
                            <span class="text-accent text-xs">📍 Disponible</span>
                        </div>
                        <div class="bg-white/20 rounded-xl p-4 text-center mt-6">
                            <i class="fas fa-carrot text-4xl text-accent"></i>
                            <p class="text-white text-sm mt-1 font-semibold">Légumes</p>
                            <span class="text-accent text-xs">📍 Disponible</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- LOCATION D'ÉTALAGES -->
<!-- ============================================ -->
<section id="location" class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center mb-12">
            <span class="text-accent font-bold text-sm uppercase tracking-wider">📍 Location</span>
            <h2 class="text-3xl md:text-4xl font-bold text-primary mt-1">
                Nos étalages disponibles
            </h2>
            <div class="w-20 h-1 bg-accent mx-auto mt-3 rounded"></div>
        </div>
        
        <div class="grid md:grid-cols-3 gap-6">
            <!-- Standard -->
            <div class="card-simple bg-white rounded-xl p-6 text-center">
                <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto">
                    <i class="fas fa-store text-primary text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-primary mt-4">Standard</h3>
                <div class="text-3xl font-bold text-accent my-2">250$</div>
                <p class="text-gray-500 text-sm">/mois</p>
                <ul class="text-sm text-gray-600 space-y-1 mt-4">
                    <li><i class="fas fa-check text-accent mr-2"></i>Espace 3m²</li>
                    <li><i class="fas fa-check text-accent mr-2"></i>Électricité</li>
                    <li><i class="fas fa-check text-accent mr-2"></i>Eau potable</li>
                    <li><i class="fas fa-check text-accent mr-2"></i>Sécurité 24/7</li>
                </ul>
                <a href="login.php" class="btn-primary block mt-6 py-2 rounded-lg font-semibold">
                    Réserver
                </a>
            </div>
            
            <!-- Premium -->
            <div class="card-simple bg-white rounded-xl p-6 text-center border-2 border-accent relative">
                <div class="absolute -top-3 right-4 bg-accent text-white px-3 py-0.5 rounded-full text-xs font-bold">
                    Populaire
                </div>
                <div class="w-16 h-16 bg-accent/20 rounded-full flex items-center justify-center mx-auto">
                    <i class="fas fa-warehouse text-accent text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-primary mt-4">Premium</h3>
                <div class="text-3xl font-bold text-accent my-2">450$</div>
                <p class="text-gray-500 text-sm">/mois</p>
                <ul class="text-sm text-gray-600 space-y-1 mt-4">
                    <li><i class="fas fa-check text-accent mr-2"></i>Espace 6m²</li>
                    <li><i class="fas fa-check text-accent mr-2"></i>Électricité + Clim</li>
                    <li><i class="fas fa-check text-accent mr-2"></i>Eau + évacuation</li>
                    <li><i class="fas fa-check text-accent mr-2"></i>Espace stockage</li>
                    <li><i class="fas fa-check text-accent mr-2"></i>Sécurité 24/7</li>
                </ul>
                <a href="login.php" class="btn-accent block mt-6 py-2 rounded-lg font-semibold">
                    Réserver
                </a>
            </div>
            
            <!-- Restauration -->
            <div class="card-simple bg-white rounded-xl p-6 text-center">
                <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto">
                    <i class="fas fa-utensils text-primary text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold text-primary mt-4">Restauration</h3>
                <div class="text-3xl font-bold text-accent my-2">600$</div>
                <p class="text-gray-500 text-sm">/mois</p>
                <ul class="text-sm text-gray-600 space-y-1 mt-4">
                    <li><i class="fas fa-check text-accent mr-2"></i>Espace 8m²</li>
                    <li><i class="fas fa-check text-accent mr-2"></i>Équipement cuisine</li>
                    <li><i class="fas fa-check text-accent mr-2"></i>Eau + évacuation</li>
                    <li><i class="fas fa-check text-accent mr-2"></i>Tables et chaises</li>
                    <li><i class="fas fa-check text-accent mr-2"></i>Emplacement stratégique</li>
                </ul>
                <a href="/pages/Client/register.php" class="btn-primary block mt-6 py-2 rounded-lg font-semibold">
                    Réserver
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- SERVICES SIMPLES -->
<!-- ============================================ -->
<section id="services" class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center mb-12">
            <span class="text-accent font-bold text-sm uppercase tracking-wider">Services</span>
            <h2 class="text-3xl font-bold text-primary mt-1">
                Ce que nous proposons
            </h2>
            <div class="w-20 h-1 bg-accent mx-auto mt-3 rounded"></div>
        </div>
        
        <div class="grid md:grid-cols-3 gap-6">
            <div class="text-center p-6 border border-gray-200 rounded-xl hover:border-accent transition">
                <div class="w-14 h-14 bg-primary rounded-full flex items-center justify-center mx-auto">
                    <i class="fas fa-store text-white text-xl"></i>
                </div>
                <h3 class="font-bold text-primary mt-4">Location d'étalages</h3>
                <p class="text-gray-600 text-sm mt-2">Des espaces modernes et bien situés</p>
            </div>
            
            <div class="text-center p-6 border border-gray-200 rounded-xl hover:border-accent transition">
                <div class="w-14 h-14 bg-primary rounded-full flex items-center justify-center mx-auto">
                    <i class="fas fa-hand-holding-usd text-white text-xl"></i>
                </div>
                <h3 class="font-bold text-primary mt-4">Gestion des paiements</h3>
                <p class="text-gray-600 text-sm mt-2">Transactions sécurisées et transparentes</p>
            </div>
            
            <div class="text-center p-6 border border-gray-200 rounded-xl hover:border-accent transition">
                <div class="w-14 h-14 bg-primary rounded-full flex items-center justify-center mx-auto">
                    <i class="fas fa-headset text-white text-xl"></i>
                </div>
                <h3 class="font-bold text-primary mt-4">Accompagnement</h3>
                <p class="text-gray-600 text-sm mt-2">Conseil et assistance personnalisés</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- CONTACT SIMPLE -->
<!-- ============================================ -->
<section id="contact" class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center mb-12">
            <span class="text-accent font-bold text-sm uppercase tracking-wider">Contact</span>
            <h2 class="text-3xl font-bold text-primary mt-1">
                Nous contacter
            </h2>
            <div class="w-20 h-1 bg-accent mx-auto mt-3 rounded"></div>
        </div>
        
        <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
            <!-- Infos -->
            <div class="space-y-4">
                <div class="flex items-start space-x-3">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                        <i class="fas fa-map-marker-alt text-primary"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-primary">Adresse</p>
                        <p class="text-gray-600 text-sm">Goma, RDC</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                        <i class="fas fa-phone text-primary"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-primary">Téléphone</p>
                        <p class="text-gray-600 text-sm">+243 81 234 5678</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                        <i class="fas fa-envelope text-primary"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-primary">Email</p>
                        <p class="text-gray-600 text-sm">location@marchevirunga.cd</p>
                    </div>
                </div>
                <div class="flex items-start space-x-3">
                    <div class="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-primary"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-primary">Horaires</p>
                        <p class="text-gray-600 text-sm">Lun - Sam : 06h - 18h</p>
                    </div>
                </div>
                
                <div class="flex space-x-2 pt-2">
                    <a href="#" class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white hover:bg-primary/80 transition">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white hover:bg-primary/80 transition">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white hover:bg-primary/80 transition">
                        <i class="fab fa-instagram"></i>
                    </a>
                </div>
            </div>
            
            <!-- Formulaire -->
            <div class="bg-white p-6 rounded-xl shadow-sm">
                <h3 class="font-bold text-primary text-lg mb-4">Demande de location</h3>
                <form>
                    <input type="text" placeholder="Nom complet" class="w-full px-4 py-2 border border-gray-300 rounded-lg mb-3 focus:border-accent focus:outline-none">
                    <input type="email" placeholder="Email" class="w-full px-4 py-2 border border-gray-300 rounded-lg mb-3 focus:border-accent focus:outline-none">
                    <input type="tel" placeholder="Téléphone" class="w-full px-4 py-2 border border-gray-300 rounded-lg mb-3 focus:border-accent focus:outline-none">
                    <select class="w-full px-4 py-2 border border-gray-300 rounded-lg mb-3 focus:border-accent focus:outline-none">
                        <option value="">Type d'étalage</option>
                        <option value="standard">Standard (250$)</option>
                        <option value="premium">Premium (450$)</option>
                        <option value="restauration">Restauration (600$)</option>
                    </select>
                    <button type="submit" class="btn-primary w-full py-2 rounded-lg font-semibold">
                        <i class="fas fa-paper-plane mr-2"></i>Envoyer
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- ============================================ -->
<!-- FOOTER -->
<!-- ============================================ -->
<footer class="bg-primary text-white py-8">
    <div class="max-w-7xl mx-auto px-4">
        <div class="grid md:grid-cols-3 gap-6">
            <div>
                <div class="flex items-center space-x-2 mb-2">
                    <i class="fas fa-store text-accent"></i>
                    <span class="font-bold text-lg">Marché Virunga</span>
                </div>
                <p class="text-blue-200 text-sm">Location d'étalages à Goma</p>
                <p class="text-accent text-sm font-semibold mt-1">300+ étalages disponibles</p>
            </div>
            <div>
                <h4 class="font-semibold mb-2">Liens</h4>
                <ul class="text-blue-200 text-sm space-y-1">
                    <li><a href="#accueil" class="hover:text-accent transition">Accueil</a></li>
                    <li><a href="#location" class="hover:text-accent transition text-accent">📍 Location</a></li>
                    <li><a href="#services" class="hover:text-accent transition">Services</a></li>
                    <li><a href="#contact" class="hover:text-accent transition">Contact</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-2">Newsletter</h4>
                <form class="flex">
                    <input type="email" placeholder="Votre email" class="flex-1 px-3 py-1 bg-white/10 border border-white/20 rounded-l-lg focus:outline-none text-sm">
                    <button type="submit" class="bg-accent hover:bg-accent-hover px-3 py-1 rounded-r-lg transition">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>
        <div class="border-t border-white/10 mt-6 pt-4 text-center text-blue-200 text-sm">
            &copy; <?= date('Y') ?> Marché Virunga - Location d'étalages
        </div>
    </div>
</footer>

<!-- ============================================ -->
<!-- SCRIPTS -->
<!-- ============================================ -->
<script>
    // Menu mobile
    document.getElementById('menu-toggle').addEventListener('click', function() {
        document.getElementById('mobile-menu').classList.toggle('hidden');
    });
    
    // Fermer le menu mobile
    document.querySelectorAll('#mobile-menu a').forEach(link => {
        link.addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.add('hidden');
        });
    });
    
    // Navigation fluide
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
</script>

</body>
</html>