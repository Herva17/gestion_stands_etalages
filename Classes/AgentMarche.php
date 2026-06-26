<?php
require_once __DIR__ . '/Database.php';

class AgentMarche {
    private $db;
    private $id_agent;
    private $id_user;
    private $matricule_agent;
    private $user_data = [];
    
    // Propriétés de l'utilisateur
    private $nom_complet;
    private $sexe;
    private $nationalite;
    private $date_naissance;
    private $adresse;
    private $matricule;
    private $nom_user;
    private $telephone;
    private $email;
    private $created_at;
    
    /**
     * Constructeur - Initialise la connexion à la BDD
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Hydrate l'objet avec les données d'un utilisateur
     */
    private function hydrate($user_data, $agent_data = null) {
        $this->user_data = $user_data;
        $this->id_user = $user_data['id_user'];
        $this->nom_complet = $user_data['nom_complet'];
        $this->sexe = $user_data['sexe'];
        $this->nationalite = $user_data['nationalite'];
        $this->date_naissance = $user_data['date_naissance'];
        $this->adresse = $user_data['adresse'];
        $this->matricule = $user_data['matricule'];
        $this->nom_user = $user_data['nom_user'];
        $this->telephone = $user_data['telephone'];
        $this->email = $user_data['email'] ?? null;
        $this->created_at = $user_data['created_at'];
        
        if ($agent_data) {
            $this->id_agent = $agent_data['id_agent'];
            $this->matricule_agent = $agent_data['matricule'];
        }
    }
    
    // ============================================
    // MÉTHODES D'AUTHENTIFICATION
    // ============================================
    
    /**
     * Inscription d'un nouvel agent du marché
     */
    public function register($data) {
        // Validation des données
        $errors = $this->validateRegisterData($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        try {
            $this->db->beginTransaction();
            
            // Vérifier si le nom d'utilisateur existe déjà
            if ($this->usernameExists($data['nom_user'])) {
                return ['success' => false, 'errors' => ['nom_user' => 'Ce nom d\'utilisateur est déjà utilisé']];
            }
            
            // Vérifier si l'email existe déjà
            if (isset($data['email']) && $this->emailExists($data['email'])) {
                return ['success' => false, 'errors' => ['email' => 'Cet email est déjà utilisé']];
            }
            
            // Vérifier si le téléphone existe déjà
            if ($this->telephoneExists($data['telephone'])) {
                return ['success' => false, 'errors' => ['telephone' => 'Ce numéro de téléphone est déjà utilisé']];
            }
            
            // Générer un matricule unique
            $matricule = $this->generateMatricule();
            
            // Générer un matricule d'agent
            $matricule_agent = $this->generateAgentMatricule();
            
            // Hasher le mot de passe
            $hashed_password = password_hash($data['mot_de_passe'], PASSWORD_DEFAULT);
            
            // Insérer l'utilisateur
            $sql = "INSERT INTO utilisateurs (
                nom_complet, sexe, nationalite, date_naissance, adresse,
                matricule, nom_user, mot_de_passe, telephone, email
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['nom_complet'],
                $data['sexe'] ?? 'Masculin',
                $data['nationalite'] ?? 'Congolaise',
                $data['date_naissance'] ?? null,
                $data['adresse'] ?? '',
                $matricule,
                $data['nom_user'],
                $hashed_password,
                $data['telephone'],
                $data['email'] ?? null
            ]);
            
            $user_id = $this->db->lastInsertId();
            
            // Créer le compte agent
            $sql = "INSERT INTO agent_marche (id_user, matricule) VALUES (?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user_id, $matricule_agent]);
            
            $agent_id = $this->db->lastInsertId();
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Agent du marché inscrit avec succès !',
                'user_id' => $user_id,
                'agent_id' => $agent_id,
                'matricule' => $matricule,
                'matricule_agent' => $matricule_agent
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'errors' => ['database' => 'Erreur lors de l\'inscription: ' . $e->getMessage()]];
        }
    }
    
    /**
     * Connexion d'un agent du marché
     */
    public function login($nom_user, $mot_de_passe) {
        if (empty($nom_user) || empty($mot_de_passe)) {
            return ['success' => false, 'error' => 'Veuillez remplir tous les champs'];
        }
        
        try {
            // Récupérer l'utilisateur
            $stmt = $this->db->prepare("
                SELECT u.*, a.id_agent, a.matricule as matricule_agent 
                FROM utilisateurs u
                LEFT JOIN agent_marche a ON u.id_user = a.id_user
                WHERE u.nom_user = ? OR u.email = ?
            ");
            $stmt->execute([$nom_user, $nom_user]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'error' => 'Identifiants incorrects'];
            }
            
            // Vérifier le mot de passe
            if (!password_verify($mot_de_passe, $user['mot_de_passe'])) {
                return ['success' => false, 'error' => 'Identifiants incorrects'];
            }
            
            // Vérifier si c'est bien un agent
            if (is_null($user['id_agent'])) {
                return ['success' => false, 'error' => 'Vous n\'êtes pas enregistré comme agent du marché'];
            }
            
            // Hydrater l'objet
            $this->hydrate($user, $user);
            
            // Démarrer la session
            $this->startSession();
            
            return [
                'success' => true,
                'message' => 'Connexion réussie',
                'user' => [
                    'id_user' => $this->id_user,
                    'id_agent' => $this->id_agent,
                    'nom_complet' => $this->nom_complet,
                    'nom_user' => $this->nom_user,
                    'matricule' => $this->matricule,
                    'matricule_agent' => $this->matricule_agent
                ]
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur de connexion: ' . $e->getMessage()];
        }
    }
    
    /**
     * Déconnexion
     */
    public function logout() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        return ['success' => true, 'message' => 'Déconnecté avec succès'];
    }
    
    /**
     * Vérifier si l'utilisateur est connecté
     */
    public function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'agent';
    }
    
    /**
     * Récupérer l'utilisateur connecté
     */
    public function getLoggedInUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, a.id_agent, a.matricule as matricule_agent 
                FROM utilisateurs u
                INNER JOIN agent_marche a ON u.id_user = a.id_user
                WHERE u.id_user = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                $this->hydrate($user, $user);
                return $this;
            }
            return null;
            
        } catch (PDOException $e) {
            return null;
        }
    }
    
    // ============================================
    // MÉTHODES CRUD
    // ============================================
    
    /**
     * Récupérer tous les agents du marché
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("
                SELECT u.*, a.id_agent, a.matricule as matricule_agent
                FROM utilisateurs u
                INNER JOIN agent_marche a ON u.id_user = a.id_user
                ORDER BY u.created_at DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer un agent par son ID
     */
    public function getById($id_agent) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, a.id_agent, a.matricule as matricule_agent
                FROM utilisateurs u
                INNER JOIN agent_marche a ON u.id_user = a.id_user
                WHERE a.id_agent = ?
            ");
            $stmt->execute([$id_agent]);
            $data = $stmt->fetch();
            
            if ($data) {
                $this->hydrate($data, $data);
                return $this;
            }
            return null;
            
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Récupérer un agent par son ID utilisateur
     */
    public function getByUserId($id_user) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, a.id_agent, a.matricule as matricule_agent
                FROM utilisateurs u
                INNER JOIN agent_marche a ON u.id_user = a.id_user
                WHERE u.id_user = ?
            ");
            $stmt->execute([$id_user]);
            $data = $stmt->fetch();
            
            if ($data) {
                $this->hydrate($data, $data);
                return $this;
            }
            return null;
            
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Mettre à jour un agent
     */
    public function update($id_agent, $data) {
        try {
            $this->db->beginTransaction();
            
            // Mettre à jour l'utilisateur
            $sql = "UPDATE utilisateurs u
                    INNER JOIN agent_marche a ON u.id_user = a.id_user
                    SET u.nom_complet = ?,
                        u.sexe = ?,
                        u.nationalite = ?,
                        u.date_naissance = ?,
                        u.adresse = ?,
                        u.telephone = ?,
                        u.email = ?
                    WHERE a.id_agent = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['nom_complet'] ?? '',
                $data['sexe'] ?? 'Masculin',
                $data['nationalite'] ?? 'Congolaise',
                $data['date_naissance'] ?? null,
                $data['adresse'] ?? '',
                $data['telephone'] ?? '',
                $data['email'] ?? null,
                $id_agent
            ]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Agent mis à jour avec succès'];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()];
        }
    }
    
    /**
     * Supprimer un agent
     */
    public function delete($id_agent) {
        try {
            // Récupérer l'ID utilisateur
            $stmt = $this->db->prepare("SELECT id_user FROM agent_marche WHERE id_agent = ?");
            $stmt->execute([$id_agent]);
            $agent = $stmt->fetch();
            
            if (!$agent) {
                return ['success' => false, 'error' => 'Agent non trouvé'];
            }
            
            $this->db->beginTransaction();
            
            // Supprimer l'agent (cascade supprime l'utilisateur)
            $stmt = $this->db->prepare("DELETE FROM agent_marche WHERE id_agent = ?");
            $stmt->execute([$id_agent]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Agent supprimé avec succès'];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Erreur lors de la suppression: ' . $e->getMessage()];
        }
    }
    
    // ============================================
    // MÉTHODES DE GESTION DU MARCHÉ
    // ============================================
    
    /**
     * Récupérer tous les étalages
     */
    public function getAllEtalages() {
        try {
            $stmt = $this->db->query("
                SELECT e.*, s.designation as secteur_nom,
                       u.nom_complet as commerçant_nom
                FROM etalage e
                LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
                LEFT JOIN commercant c ON e.id_commercant = c.id_commercant
                LEFT JOIN utilisateurs u ON c.id_user = u.id_user
                ORDER BY e.numero
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les étalages disponibles
     */
    public function getEtalagesDisponibles() {
        try {
            $stmt = $this->db->query("
                SELECT e.*, s.designation as secteur_nom
                FROM etalage e
                LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
                WHERE e.statut = 'disponible' OR e.id_commercant IS NULL
                ORDER BY e.numero
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les étalages occupés
     */
    public function getEtalagesOccupes() {
        try {
            $stmt = $this->db->query("
                SELECT e.*, s.designation as secteur_nom,
                       u.nom_complet as commerçant_nom
                FROM etalage e
                LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
                INNER JOIN commercant c ON e.id_commercant = c.id_commercant
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                WHERE e.statut = 'occupe'
                ORDER BY e.numero
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Assigner un étalage à un commerçant
     */
    public function assignerEtalage($id_etalage, $id_commercant, $montant_location, $duree) {
        try {
            $this->db->beginTransaction();
            
            // Vérifier que l'étalage est disponible
            $stmt = $this->db->prepare("SELECT statut FROM etalage WHERE id_etalage = ?");
            $stmt->execute([$id_etalage]);
            $etalage = $stmt->fetch();
            
            if (!$etalage || ($etalage['statut'] !== 'disponible' && $etalage['statut'] !== null)) {
                return ['success' => false, 'error' => 'Cet étalage n\'est pas disponible'];
            }
            
            // Mettre à jour l'étalage
            $stmt = $this->db->prepare("UPDATE etalage SET statut = 'occupe', id_commercant = ? WHERE id_etalage = ?");
            $stmt->execute([$id_commercant, $id_etalage]);
            
            // Créer la location
            $date_debut = date('Y-m-d');
            $date_fin = date('Y-m-d', strtotime("+$duree days"));
            
            $sql = "INSERT INTO location (montant_location, duree_location, date_debut, date_fin, id_commercant, id_etalage) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$montant_location, "$duree jours", $date_debut, $date_fin, $id_commercant, $id_etalage]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Étalage assigné avec succès'];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Libérer un étalage
     */
    public function libererEtalage($id_etalage) {
        try {
            $this->db->beginTransaction();
            
            // Mettre à jour l'étalage
            $stmt = $this->db->prepare("UPDATE etalage SET statut = 'disponible', id_commercant = NULL WHERE id_etalage = ?");
            $stmt->execute([$id_etalage]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Étalage libéré avec succès'];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Récupérer tous les secteurs
     */
    public function getAllSecteurs() {
        try {
            $stmt = $this->db->query("SELECT * FROM secteur ORDER BY designation");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Ajouter un secteur
     */
    public function ajouterSecteur($designation) {
        try {
            $stmt = $this->db->prepare("INSERT INTO secteur (designation) VALUES (?)");
            $stmt->execute([$designation]);
            return ['success' => true, 'message' => 'Secteur ajouté avec succès', 'id_secteur' => $this->db->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Ajouter un étalage
     */
    public function ajouterEtalage($data) {
        try {
            $sql = "INSERT INTO etalage (numero, localisation, statut, id_secteur) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['numero'],
                $data['localisation'],
                'disponible',
                $data['id_secteur']
            ]);
            
            return ['success' => true, 'message' => 'Étalage ajouté avec succès', 'id_etalage' => $this->db->lastInsertId()];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Récupérer tous les commerçants
     */
    public function getAllCommercants() {
        try {
            $stmt = $this->db->query("
                SELECT c.*, u.nom_complet, u.telephone, u.email, u.matricule,
                       COUNT(DISTINCT l.id_location) as nb_locations
                FROM commercant c
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                LEFT JOIN location l ON c.id_commercant = l.id_commercant
                GROUP BY c.id_commercant
                ORDER BY u.nom_complet
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer toutes les locations
     */
    public function getAllLocations() {
        try {
            $stmt = $this->db->query("
                SELECT l.*, 
                       u.nom_complet as commerçant_nom,
                       e.numero as etalage_numero,
                       e.localisation
                FROM location l
                INNER JOIN commercant c ON l.id_commercant = c.id_commercant
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                ORDER BY l.date_debut DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les paiements
     */
    public function getAllPaiements() {
        try {
            $stmt = $this->db->query("
                SELECT p.*, 
                       u.nom_complet as commerçant_nom,
                       e.numero as etalage_numero,
                       c.nom_user as caissier_nom
                FROM paiement p
                INNER JOIN location l ON p.id_location = l.id_location
                INNER JOIN commercant cm ON l.id_commercant = cm.id_commercant
                INNER JOIN utilisateurs u ON cm.id_user = u.id_user
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                LEFT JOIN caissier ca ON p.id_caissier = ca.id_caissier
                LEFT JOIN utilisateurs c ON ca.id_user = c.id_user
                ORDER BY p.date_paiement DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les statistiques du marché
     */
    public function getStats() {
        try {
            $stats = [];
            
            // Total étalages
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM etalage");
            $stats['total_etalages'] = $stmt->fetch()['total'];
            
            // Étages disponibles
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM etalage WHERE statut = 'disponible' OR statut IS NULL");
            $stats['etalages_disponibles'] = $stmt->fetch()['total'];
            
            // Étages occupés
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM etalage WHERE statut = 'occupe'");
            $stats['etalages_occupes'] = $stmt->fetch()['total'];
            
            // Total commerçants
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM commercant");
            $stats['total_commercants'] = $stmt->fetch()['total'];
            
            // Total locations actives
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM location WHERE date_fin >= CURDATE()");
            $stats['locations_actives'] = $stmt->fetch()['total'];
            
            // Revenus totaux
            $stmt = $this->db->query("SELECT SUM(montant) as total FROM paiement");
            $stats['revenus_totaux'] = $stmt->fetch()['total'] ?? 0;
            
            // Revenus du mois
            $stmt = $this->db->query("SELECT SUM(montant) as total FROM paiement WHERE MONTH(date_paiement) = MONTH(CURDATE()) AND YEAR(date_paiement) = YEAR(CURDATE())");
            $stats['revenus_mois'] = $stmt->fetch()['total'] ?? 0;
            
            return $stats;
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Changer le mot de passe
     */
    public function changePassword($id_user, $old_password, $new_password) {
        try {
            // Vérifier l'ancien mot de passe
            $stmt = $this->db->prepare("SELECT mot_de_passe FROM utilisateurs WHERE id_user = ?");
            $stmt->execute([$id_user]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($old_password, $user['mot_de_passe'])) {
                return ['success' => false, 'error' => 'Ancien mot de passe incorrect'];
            }
            
            if (strlen($new_password) < 6) {
                return ['success' => false, 'error' => 'Le nouveau mot de passe doit contenir au moins 6 caractères'];
            }
            
            // Mettre à jour le mot de passe
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id_user = ?");
            $stmt->execute([$hashed_password, $id_user]);
            
            return ['success' => true, 'message' => 'Mot de passe modifié avec succès'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    // ============================================
    // MÉTHODES PRIVÉES
    // ============================================
    
    /**
     * Valider les données d'inscription
     */
    private function validateRegisterData($data) {
        $errors = [];
        
        if (empty($data['nom_complet'])) {
            $errors['nom_complet'] = 'Le nom complet est requis';
        }
        if (empty($data['nom_user'])) {
            $errors['nom_user'] = 'Le nom d\'utilisateur est requis';
        }
        if (empty($data['mot_de_passe'])) {
            $errors['mot_de_passe'] = 'Le mot de passe est requis';
        } elseif (strlen($data['mot_de_passe']) < 6) {
            $errors['mot_de_passe'] = 'Le mot de passe doit contenir au moins 6 caractères';
        }
        if (empty($data['telephone'])) {
            $errors['telephone'] = 'Le numéro de téléphone est requis';
        }
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'L\'email n\'est pas valide';
        }
        
        return $errors;
    }
    
    /**
     * Vérifier si un nom d'utilisateur existe déjà
     */
    private function usernameExists($nom_user) {
        $stmt = $this->db->prepare("SELECT id_user FROM utilisateurs WHERE nom_user = ?");
        $stmt->execute([$nom_user]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Vérifier si un email existe déjà
     */
    private function emailExists($email) {
        if (empty($email)) return false;
        $stmt = $this->db->prepare("SELECT id_user FROM utilisateurs WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Vérifier si un téléphone existe déjà
     */
    private function telephoneExists($telephone) {
        $stmt = $this->db->prepare("SELECT id_user FROM utilisateurs WHERE telephone = ?");
        $stmt->execute([$telephone]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Générer un matricule unique
     */
    private function generateMatricule() {
        $prefix = 'AG';
        $year = date('Y');
        $random = rand(1000, 9999);
        $matricule = $prefix . $year . $random;
        
        $stmt = $this->db->prepare("SELECT id_user FROM utilisateurs WHERE matricule = ?");
        $stmt->execute([$matricule]);
        
        if ($stmt->fetch()) {
            return $this->generateMatricule();
        }
        
        return $matricule;
    }
    
    /**
     * Générer un matricule d'agent unique
     */
    private function generateAgentMatricule() {
        $prefix = 'AGM';
        $year = date('Y');
        $random = rand(100, 999);
        $matricule = $prefix . $year . $random;
        
        $stmt = $this->db->prepare("SELECT id_agent FROM agent_marche WHERE matricule = ?");
        $stmt->execute([$matricule]);
        
        if ($stmt->fetch()) {
            return $this->generateAgentMatricule();
        }
        
        return $matricule;
    }
    
    /**
     * Démarrer la session
     */
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['user_id'] = $this->id_user;
        $_SESSION['id_agent'] = $this->id_agent;
        $_SESSION['nom_complet'] = $this->nom_complet;
        $_SESSION['nom_user'] = $this->nom_user;
        $_SESSION['matricule'] = $this->matricule;
        $_SESSION['matricule_agent'] = $this->matricule_agent;
        $_SESSION['role'] = 'agent';
        $_SESSION['logged_in'] = true;
    }
    
    // ============================================
    // GETTERS
    // ============================================
    
    public function getIdAgent() { return $this->id_agent; }
    public function getIdUser() { return $this->id_user; }
    public function getMatriculeAgent() { return $this->matricule_agent; }
    public function getNomComplet() { return $this->nom_complet; }
    public function getSexe() { return $this->sexe; }
    public function getNationalite() { return $this->nationalite; }
    public function getDateNaissance() { return $this->date_naissance; }
    public function getAdresse() { return $this->adresse; }
    public function getMatricule() { return $this->matricule; }
    public function getNomUser() { return $this->nom_user; }
    public function getTelephone() { return $this->telephone; }
    public function getEmail() { return $this->email; }
    public function getCreatedAt() { return $this->created_at; }
    
    /**
     * Obtenir toutes les données de l'agent
     */
    public function toArray() {
        return [
            'id_agent' => $this->id_agent,
            'id_user' => $this->id_user,
            'nom_complet' => $this->nom_complet,
            'sexe' => $this->sexe,
            'nationalite' => $this->nationalite,
            'date_naissance' => $this->date_naissance,
            'adresse' => $this->adresse,
            'matricule' => $this->matricule,
            'matricule_agent' => $this->matricule_agent,
            'nom_user' => $this->nom_user,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'created_at' => $this->created_at
        ];
    }
}