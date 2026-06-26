<?php
require_once __DIR__ . '/Database.php';

class Administrateur {
    private $db;
    private $id_admin;
    private $id_user;
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
    private function hydrate($user_data, $admin_data = null) {
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
        
        if ($admin_data) {
            $this->id_admin = $admin_data['id_admin'];
        }
    }
    
    // ============================================
    // MÉTHODES D'AUTHENTIFICATION
    // ============================================
    
    /**
     * Inscription d'un nouvel administrateur
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
                $data['matricule'],
                $data['nom_user'],
                $hashed_password,
                $data['telephone'],
                $data['email'] ?? null
            ]);
            
            $user_id = $this->db->lastInsertId();
            
            // Créer le compte administrateur
            $sql = "INSERT INTO administrateur (id_user) VALUES (?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user_id]);
            
            $admin_id = $this->db->lastInsertId();
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Administrateur inscrit avec succès !',
                'user_id' => $user_id,
                'admin_id' => $admin_id,
                'matricule' => $data['matricule']
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'errors' => ['database' => 'Erreur lors de l\'inscription: ' . $e->getMessage()]];
        }
    }
    
    /**
     * Connexion d'un administrateur
     */
    public function login($nom_user, $mot_de_passe) {
        if (empty($nom_user) || empty($mot_de_passe)) {
            return ['success' => false, 'error' => 'Veuillez remplir tous les champs'];
        }
        
        try {
            // Récupérer l'utilisateur
            $stmt = $this->db->prepare("
                SELECT u.*, a.id_admin 
                FROM utilisateurs u
                LEFT JOIN administrateur a ON u.id_user = a.id_user
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
            
            // Vérifier si c'est bien un administrateur
            if (is_null($user['id_admin'])) {
                return ['success' => false, 'error' => 'Vous n\'êtes pas enregistré comme administrateur'];
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
                    'id_admin' => $this->id_admin,
                    'nom_complet' => $this->nom_complet,
                    'nom_user' => $this->nom_user,
                    'matricule' => $this->matricule
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
        return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
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
                SELECT u.*, a.id_admin 
                FROM utilisateurs u
                INNER JOIN administrateur a ON u.id_user = a.id_user
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
     * Récupérer tous les administrateurs
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("
                SELECT u.*, a.id_admin
                FROM utilisateurs u
                INNER JOIN administrateur a ON u.id_user = a.id_user
                ORDER BY u.created_at DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer un administrateur par son ID
     */
    public function getById($id_admin) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, a.id_admin
                FROM utilisateurs u
                INNER JOIN administrateur a ON u.id_user = a.id_user
                WHERE a.id_admin = ?
            ");
            $stmt->execute([$id_admin]);
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
     * Récupérer un administrateur par son ID utilisateur
     */
    public function getByUserId($id_user) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, a.id_admin
                FROM utilisateurs u
                INNER JOIN administrateur a ON u.id_user = a.id_user
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
     * Mettre à jour un administrateur
     */
    public function update($id_admin, $data) {
        try {
            $this->db->beginTransaction();
            
            // Mettre à jour l'utilisateur
            $sql = "UPDATE utilisateurs u
                    INNER JOIN administrateur a ON u.id_user = a.id_user
                    SET u.nom_complet = ?,
                        u.sexe = ?,
                        u.nationalite = ?,
                        u.date_naissance = ?,
                        u.adresse = ?,
                        u.telephone = ?,
                        u.email = ?
                    WHERE a.id_admin = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['nom_complet'] ?? '',
                $data['sexe'] ?? 'Masculin',
                $data['nationalite'] ?? 'Congolaise',
                $data['date_naissance'] ?? null,
                $data['adresse'] ?? '',
                $data['telephone'] ?? '',
                $data['email'] ?? null,
                $id_admin
            ]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Administrateur mis à jour avec succès'];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()];
        }
    }
    
    /**
     * Supprimer un administrateur
     */
    public function delete($id_admin) {
        try {
            // Récupérer l'ID utilisateur
            $stmt = $this->db->prepare("SELECT id_user FROM administrateur WHERE id_admin = ?");
            $stmt->execute([$id_admin]);
            $admin = $stmt->fetch();
            
            if (!$admin) {
                return ['success' => false, 'error' => 'Administrateur non trouvé'];
            }
            
            $this->db->beginTransaction();
            
            // Supprimer l'administrateur (cascade supprime l'utilisateur)
            $stmt = $this->db->prepare("DELETE FROM administrateur WHERE id_admin = ?");
            $stmt->execute([$id_admin]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Administrateur supprimé avec succès'];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Erreur lors de la suppression: ' . $e->getMessage()];
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
    // MÉTHODES DE GESTION
    // ============================================
    
    /**
     * Récupérer les statistiques du système
     */
    public function getStats() {
        try {
            $stats = [];
            
            // Total utilisateurs
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM utilisateurs");
            $stats['total_utilisateurs'] = $stmt->fetch()['total'];
            
            // Total administrateurs
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM administrateur");
            $stats['total_admins'] = $stmt->fetch()['total'];
            
            // Total agents
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM agent_marche");
            $stats['total_agents'] = $stmt->fetch()['total'];
            
            // Total commerçants
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM commercant");
            $stats['total_commercants'] = $stmt->fetch()['total'];
            
            // Total étalages
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM etalage");
            $stats['total_etalages'] = $stmt->fetch()['total'];
            
            return $stats;
            
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Vérifier si un utilisateur est administrateur
     */
    public function isAdmin($id_user) {
        try {
            $stmt = $this->db->prepare("SELECT id_admin FROM administrateur WHERE id_user = ?");
            $stmt->execute([$id_user]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            return false;
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
        if (empty($data['matricule'])) {
            $errors['matricule'] = 'Le matricule est requis';
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
     * Démarrer la session
     */
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['user_id'] = $this->id_user;
        $_SESSION['id_admin'] = $this->id_admin;
        $_SESSION['nom_complet'] = $this->nom_complet;
        $_SESSION['nom_user'] = $this->nom_user;
        $_SESSION['matricule'] = $this->matricule;
        $_SESSION['role'] = 'admin';
        $_SESSION['logged_in'] = true;
    }
    
    // ============================================
    // GETTERS
    // ============================================
    
    public function getIdAdmin() { return $this->id_admin; }
    public function getIdUser() { return $this->id_user; }
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
     * Obtenir toutes les données de l'administrateur
     */
    public function toArray() {
        return [
            'id_admin' => $this->id_admin,
            'id_user' => $this->id_user,
            'nom_complet' => $this->nom_complet,
            'sexe' => $this->sexe,
            'nationalite' => $this->nationalite,
            'date_naissance' => $this->date_naissance,
            'adresse' => $this->adresse,
            'matricule' => $this->matricule,
            'nom_user' => $this->nom_user,
            'telephone' => $this->telephone,
            'email' => $this->email,
            'created_at' => $this->created_at
        ];
    }/**
 * Connexion d'un administrateur
 */


}