<?php
/**
 * API: Recent Reservations with Details
 */
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php';

try {
    $db = pfa_db();
    
    $sql = "SELECT r.id_reservation, r.date_reservation, r.heure_debut, r.heure_fin, r.statut_reservation,
        u.id_utilisateur, u.nom, u.prenom, u.email,
        p.id_place, p.zone, p.niveau,
        v.marque, v.matricule,
        COALESCE(pa.montant, 0) as montant, COALESCE(pa.statut_paiement, 'non_paye') as statut_paiement
    FROM reservation r
    LEFT JOIN utilisateur u ON r.id_utilisateur = u.id_utilisateur
    LEFT JOIN place_parking p ON r.id_place = p.id_place
    LEFT JOIN posseder po ON u.id_utilisateur = po.id_utilisateur
    LEFT JOIN vehicule v ON po.id_vehicule = v.id_vehicule
    LEFT JOIN paiement pa ON r.id_paiement = pa.id_paiement
    ORDER BY r.date_reservation DESC, r.heure_debut DESC
    LIMIT 6";
    
    $result = $db->query($sql);
    $reservations = [];
    
    while ($row = $result->fetch_assoc()) {
        $reservations[] = [
            'id' => $row['id_reservation'],
            'user' => ['nom' => $row['nom'], 'prenom' => $row['prenom']],
            'vehicle' => ['marque' => $row['marque'], 'matricule' => $row['matricule']],
            'place' => ['zone' => $row['zone'], 'niveau' => $row['niveau']],
            'date' => $row['date_reservation'],
            'time' => $row['heure_debut'],
            'statut' => $row['statut_reservation'],
            'montant' => $row['montant']
        ];
    }
    
    echo json_encode(['success' => true, 'reservations' => $reservations]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
