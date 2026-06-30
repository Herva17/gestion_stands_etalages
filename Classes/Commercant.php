<?php
require_once __DIR__ . '/Database.php';

class Commercant {
    private $db;
    private $id_commercant;
    private $id_user;
    private $produits_vendu;
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
    private function hydrate($user_data, $commercant_data = null) {
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
        
        if ($commercant_data) {
            $this->id_commercant = $commercant_data['id_commercant'];
            $this->produits_vendu = $commercant_data['produits_vendu'];
        }
    }
    
    // ============================================
    // MÉTHODES D'AUTHENTIFICATION
    // ============================================
    
    /**
     * Inscription d'un nouveau commerçant
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
            
            // Générer un matricule unique
            $matricule = $this->generateMatricule();
            
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
            
            // Créer le compte commerçant
            $sql = "INSERT INTO commercant (id_user, produits_vendu) VALUES (?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user_id, $data['produits_vendu'] ?? '']);
            
            $commercant_id = $this->db->lastInsertId();
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Inscription réussie !',
                'user_id' => $user_id,
                'commercant_id' => $commercant_id,
                'matricule' => $matricule
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'errors' => ['database' => 'Erreur lors de l\'inscription: ' . $e->getMessage()]];
        }
    }
    
    /**
     * Connexion d'un commerçant
     */
    public function login($nom_user, $mot_de_passe) {
        if (empty($nom_user) || empty($mot_de_passe)) {
            return ['success' => false, 'error' => 'Veuillez remplir tous les champs'];
        }
        
        try {
            // Récupérer l'utilisateur
            $stmt = $this->db->prepare("
                SELECT u.*, c.id_commercant, c.produits_vendu 
                FROM utilisateurs u
                LEFT JOIN commercant c ON u.id_user = c.id_user
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
            
            // Vérifier si c'est bien un commerçant
            if (is_null($user['id_commercant'])) {
                return ['success' => false, 'error' => 'Vous n\'êtes pas enregistré comme commerçant'];
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
                    'id_commercant' => $this->id_commercant,
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
        return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'commercant';
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
                SELECT u.*, c.id_commercant, c.produits_vendu 
                FROM utilisateurs u
                INNER JOIN commercant c ON u.id_user = c.id_user
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
     * Récupérer tous les commerçants
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("
                SELECT u.*, c.id_commercant, c.produits_vendu,
                       COUNT(DISTINCT l.id_location) as nb_locations,
                       COUNT(DISTINCT p.id_produit) as nb_produits
                FROM utilisateurs u
                INNER JOIN commercant c ON u.id_user = c.id_user
                LEFT JOIN location l ON c.id_commercant = l.id_commercant
                LEFT JOIN produit p ON c.id_commercant = p.id_commercant
                GROUP BY c.id_commercant
                ORDER BY u.created_at DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer un commerçant par son ID
     */
    public function getById($id_commercant) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, c.id_commercant, c.produits_vendu,
                       COUNT(DISTINCT l.id_location) as nb_locations,
                       COUNT(DISTINCT p.id_prod_id) as nb_produits
                FROM utilisateurs u
                INNER JOIN commercant c ON u.id_user = c.id_user
                LEFT JOIN location l ON c.id_commercant = l.id_commercant
                LEFT JOIN produit p ON c.id_commercant = p.id_commercant
                WHERE c.id_commercant = ?
                GROUP BY c.id_commercant
            ");
            $stmt->execute([$id_commercant]);
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
     * Récupérer un commerçant par son ID utilisateur
     */
    public function getByUserId($id_user) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, c.id_commercant, c.produits_vendu 
                FROM utilisateurs u
                INNER JOIN commercant c ON u.id_user = c.id_user
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
     * Mettre à jour un commerçant
     */
    public function update($id_commercant, $data) {
        try {
            $this->db->beginTransaction();
            
            // Mettre à jour l'utilisateur
            $sql = "UPDATE utilisateurs u
                    INNER JOIN commercant c ON u.id_user = c.id_user
                    SET u.nom_complet = ?,
                        u.sexe = ?,
                        u.nationalite = ?,
                        u.date_naissance = ?,
                        u.adresse = ?,
                        u.telephone = ?,
                        u.email = ?,
                        c.produits_vendu = ?
                    WHERE c.id_commercant = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['nom_complet'] ?? '',
                $data['sexe'] ?? 'Masculin',
                $data['nationalite'] ?? 'Congolaise',
                $data['date_naissance'] ?? null,
                $data['adresse'] ?? '',
                $data['telephone'] ?? '',
                $data['email'] ?? null,
                $data['produits_vendu'] ?? '',
                $id_commercant
            ]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Commerçant mis à jour avec succès'];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Erreur lors de la mise à jour: ' . $e->getMessage()];
        }
    }
    
    /**
     * Supprimer un commerçant
     */
    public function delete($id_commercant) {
        try {
            // Récupérer l'ID utilisateur
            $stmt = $this->db->prepare("SELECT id_user FROM commercant WHERE id_commercant = ?");
            $stmt->execute([$id_commercant]);
            $commercant = $stmt->fetch();
            
            if (!$commercant) {
                return ['success' => false, 'error' => 'Commerçant non trouvé'];
            }
            
            $this->db->beginTransaction();
            
            // Supprimer le commerçant (cascade supprime l'utilisateur)
            $stmt = $this->db->prepare("DELETE FROM commercant WHERE id_commercant = ?");
            $stmt->execute([$id_commercant]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Commerçant supprimé avec succès'];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Erreur lors de la suppression: ' . $e->getMessage()];
        }
    }
    
    // ============================================
    // MÉTHODES SPÉCIFIQUES
    // ============================================
    
    /**
     * Récupérer les étalages du commerçant
     */
    public function getEtalages($id_commercant) {
        try {
            $stmt = $this->db->prepare("
                SELECT e.*, s.designation as secteur_nom
                FROM etalage e
                LEFT JOIN secteur s ON e.id_secteur = s.id_secteur
                WHERE e.id_commercant = ?
                ORDER BY e.numero
            ");
            $stmt->execute([$id_commercant]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les locations du commerçant avec les paiements
     */
    public function getLocations($id_commercant) {
        try {
            $stmt = $this->db->prepare("
                SELECT l.*, 
                       e.numero as etalage_numero, 
                       e.localisation as etalage_localisation,
                       p.id_paiement,
                       p.montant as montant_paye,
                       p.date_paiement,
                       p.reference as paiement_reference,
                       p.statut as paiement_statut,
                       p.mode_paiement
                FROM location l
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                LEFT JOIN paiement p ON l.id_location = p.id_location
                WHERE l.id_commercant = ?
                ORDER BY l.created_at DESC
            ");
            $stmt->execute([$id_commercant]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Erreur dans getLocations: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Récupérer les produits du commerçant
     */
    public function getProduits($id_commercant) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM produit
                WHERE id_commercant = ?
                ORDER BY nom_produit
            ");
            $stmt->execute([$id_commercant]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les paiements du commerçant
     */
    public function getPaiements($id_commercant) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, l.montant_location, e.numero as etalage_numero
                FROM paiement p
                INNER JOIN location l ON p.id_location = l.id_location
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                WHERE l.id_commercant = ?
                ORDER BY p.date_paiement DESC
            ");
            $stmt->execute([$id_commercant]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Vérifier si un commerçant a des locations actives
     */
    public function hasActiveLocation($id_commercant) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM location 
                WHERE id_commercant = ? AND date_fin >= CURDATE()
            ");
            $stmt->execute([$id_commercant]);
            $result = $stmt->fetch();
            return $result['count'] > 0;
        } catch (PDOException $e) {
            return false;
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
     * Générer un matricule unique
     */
    private function generateMatricule() {
        $prefix = 'COM';
        $year = date('Y');
        $random = rand(1000, 9999);
        $matricule = $prefix . $year . $random;
        
        // Vérifier si le matricule existe déjà
        $stmt = $this->db->prepare("SELECT id_user FROM utilisateurs WHERE matricule = ?");
        $stmt->execute([$matricule]);
        
        if ($stmt->fetch()) {
            return $this->generateMatricule(); // Recursif
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
        $_SESSION['id_commercant'] = $this->id_commercant;
        $_SESSION['nom_complet'] = $this->nom_complet;
        $_SESSION['nom_user'] = $this->nom_user;
        $_SESSION['matricule'] = $this->matricule;
        $_SESSION['role'] = 'commercant';
        $_SESSION['logged_in'] = true;
    }
    
    // ============================================
    // GETTERS
    // ============================================
    
    public function getIdCommercant() { return $this->id_commercant; }
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
    public function getProduitsVendu() { return $this->produits_vendu; }
    public function getCreatedAt() { return $this->created_at; }
    
    /**
     * Obtenir toutes les données de l'utilisateur
     */
    public function toArray() {
        return [
            'id_commercant' => $this->id_commercant,
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
            'produits_vendu' => $this->produits_vendu,
            'created_at' => $this->created_at
        ];
    }
}
?>