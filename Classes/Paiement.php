<?php
require_once __DIR__ . '/Database.php';

class Paiement {
    private $db;
    private $id_paiement;
    private $id_location;
    private $montant;
    private $mode_paiement;
    private $date_paiement;
    private $reference;
    private $commentaire;
    private $statut;
    private $created_at;
    
    // Données jointes
    private $commercant_nom;
    private $etalage_numero;
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Hydrate l'objet
     */
    private function hydrate($data) {
        $this->id_paiement = $data['id_paiement'] ?? null;
        $this->id_location = $data['id_location'] ?? null;
        $this->montant = $data['montant'] ?? null;
        $this->mode_paiement = $data['mode_paiement'] ?? null;
        $this->date_paiement = $data['date_paiement'] ?? null;
        $this->reference = $data['reference'] ?? null;
        $this->commentaire = $data['commentaire'] ?? null;
        $this->statut = $data['statut'] ?? 'valide';
        $this->created_at = $data['created_at'] ?? null;
        $this->commercant_nom = $data['commercant_nom'] ?? null;
        $this->etalage_numero = $data['etalage_numero'] ?? null;
    }
    
    /**
     * Générer une référence unique
     */
    private function generateReference() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $reference = '';
        for ($i = 0; $i < 8; $i++) {
            $reference .= $chars[rand(0, strlen($chars) - 1)];
        }
        return 'PY' . $reference;
    }
    
    // ============================================
    // MÉTHODES CRUD
    // ============================================
    
    /**
     * Créer un paiement
     */
    public function create($data) {
        // Validation
        if (empty($data['id_location'])) {
            return ['success' => false, 'error' => 'La location est requise'];
        }
        if (empty($data['montant']) || $data['montant'] <= 0) {
            return ['success' => false, 'error' => 'Le montant est requis et doit être supérieur à 0'];
        }
        
        try {
            $reference = $this->generateReference();
            
            $sql = "INSERT INTO paiement (id_location, montant, mode_paiement, date_paiement, reference, commentaire, statut) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['id_location'],
                $data['montant'],
                $data['mode_paiement'] ?? 'Espèces',
                $data['date_paiement'] ?? date('Y-m-d H:i:s'),
                $reference,
                $data['commentaire'] ?? '',
                $data['statut'] ?? 'valide'
            ]);
            
            $id = $this->db->lastInsertId();
            
            // Mettre à jour le statut de la location si le paiement est validé
            if (($data['statut'] ?? 'valide') === 'valide') {
                $stmt = $this->db->prepare("UPDATE location SET status = 'actif' WHERE id_location = ?");
                $stmt->execute([$data['id_location']]);
            }
            
            return [
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'id_paiement' => $id,
                'reference' => $reference
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Récupérer tous les paiements
     */
    public function getAll() {
        try {
            $stmt = $this->db->query("
                SELECT p.*, 
                       u.nom_complet as commercant_nom,
                       e.numero as etalage_numero
                FROM paiement p
                INNER JOIN location l ON p.id_location = l.id_location
                INNER JOIN commercant c ON l.id_commercant = c.id_commercant
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                ORDER BY p.date_paiement DESC
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer un paiement par son ID
     */
    public function getById($id_paiement) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       u.nom_complet as commercant_nom,
                       e.numero as etalage_numero
                FROM paiement p
                INNER JOIN location l ON p.id_location = l.id_location
                INNER JOIN commercant c ON l.id_commercant = c.id_commercant
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                WHERE p.id_paiement = ?
            ");
            $stmt->execute([$id_paiement]);
            $data = $stmt->fetch();
            
            if ($data) {
                $this->hydrate($data);
                return $this;
            }
            return null;
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Récupérer les paiements d'une location
     */
    public function getByLocation($id_location) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM paiement 
                WHERE id_location = ?
                ORDER BY date_paiement DESC
            ");
            $stmt->execute([$id_location]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Récupérer les paiements d'un commerçant
     */
    public function getByCommercant($id_commercant) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       e.numero as etalage_numero
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
     * Récupérer les paiements par période
     */
    public function getByPeriode($mois, $annee) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       u.nom_complet as commercant_nom,
                       e.numero as etalage_numero
                FROM paiement p
                INNER JOIN location l ON p.id_location = l.id_location
                INNER JOIN commercant c ON l.id_commercant = c.id_commercant
                INNER JOIN utilisateurs u ON c.id_user = u.id_user
                INNER JOIN etalage e ON l.id_etalage = e.id_etalage
                WHERE MONTH(p.date_paiement) = ? AND YEAR(p.date_paiement) = ?
                ORDER BY p.date_paiement DESC
            ");
            $stmt->execute([$mois, $annee]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    /**
     * Mettre à jour un paiement
     */
    public function update($id_paiement, $data) {
        try {
            $sql = "UPDATE paiement SET 
                        montant = ?,
                        mode_paiement = ?,
                        commentaire = ?,
                        statut = ?
                    WHERE id_paiement = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['montant'],
                $data['mode_paiement'] ?? 'Espèces',
                $data['commentaire'] ?? '',
                $data['statut'] ?? 'valide',
                $id_paiement
            ]);
            
            return ['success' => true, 'message' => 'Paiement mis à jour avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Supprimer un paiement
     */
    public function delete($id_paiement) {
        try {
            $stmt = $this->db->prepare("DELETE FROM paiement WHERE id_paiement = ?");
            $stmt->execute([$id_paiement]);
            
            return ['success' => true, 'message' => 'Paiement supprimé avec succès'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Erreur: ' . $e->getMessage()];
        }
    }
    
    /**
     * Obtenir les statistiques des paiements
     */
    public function getStats() {
        try {
            $stats = [];
            
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM paiement WHERE statut = 'valide'");
            $stats['total'] = $stmt->fetch()['total'];
            
            $stmt = $this->db->query("SELECT SUM(montant) as total FROM paiement WHERE statut = 'valide'");
            $stats['total_montant'] = $stmt->fetch()['total'] ?? 0;
            
            $stmt = $this->db->query("
                SELECT SUM(montant) as total 
                FROM paiement 
                WHERE statut = 'valide'
                AND MONTH(date_paiement) = MONTH(CURDATE()) 
                AND YEAR(date_paiement) = YEAR(CURDATE())
            ");
            $stats['mois_montant'] = $stmt->fetch()['total'] ?? 0;
            
            // Paiements par mode
            $stmt = $this->db->query("
                SELECT mode_paiement, COUNT(*) as count, SUM(montant) as total
                FROM paiement
                WHERE statut = 'valide'
                GROUP BY mode_paiement
            ");
            $stats['par_mode'] = $stmt->fetchAll();
            
            return $stats;
        } catch (PDOException $e) {
            return ['total' => 0, 'total_montant' => 0, 'mois_montant' => 0, 'par_mode' => []];
        }
    }
    
    /**
     * Vérifier si un paiement existe pour une location
     */
    public function existsForLocation($id_location) {
        try {
            $stmt = $this->db->prepare("
                SELECT id_paiement FROM paiement 
                WHERE id_location = ? AND statut = 'valide'
            ");
            $stmt->execute([$id_location]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // ============================================
    // GETTERS
    // ============================================
    
    public function getIdPaiement() { return $this->id_paiement; }
    public function getIdLocation() { return $this->id_location; }
    public function getMontant() { return $this->montant; }
    public function getModePaiement() { return $this->mode_paiement; }
    public function getDatePaiement() { return $this->date_paiement; }
    public function getReference() { return $this->reference; }
    public function getCommentaire() { return $this->commentaire; }
    public function getStatut() { return $this->statut; }
    public function getCommercantNom() { return $this->commercant_nom; }
    public function getEtalageNumero() { return $this->etalage_numero; }
    public function getCreatedAt() { return $this->created_at; }
    
    public function toArray() {
        return [
            'id_paiement' => $this->id_paiement,
            'id_location' => $this->id_location,
            'montant' => $this->montant,
            'mode_paiement' => $this->mode_paiement,
            'date_paiement' => $this->date_paiement,
            'reference' => $this->reference,
            'commentaire' => $this->commentaire,
            'statut' => $this->statut,
            'created_at' => $this->created_at,
            'commercant_nom' => $this->commercant_nom,
            'etalage_numero' => $this->etalage_numero
        ];
    }
}
?>