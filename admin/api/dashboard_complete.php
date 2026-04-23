<?php
/**
 * API: Dashboard Complete Stats with Graph Data
 */
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php';

try {
    $db = pfa_db();
    $stats = [];
    
    // Basic stats
    $result = $db->query("SELECT COUNT(*) as total FROM reservation");
    $stats['total_reservations'] = $result->fetch_assoc()['total'] ?? 0;
    
    $result = $db->query("SELECT COUNT(*) as total FROM reservation WHERE statut_reservation IN ('en_attente', 'confirmee')");
    $stats['active_reservations'] = $result->fetch_assoc()['total'] ?? 0;
    
    $result = $db->query("SELECT COUNT(*) as total FROM utilisateur");
    $stats['total_users'] = $result->fetch_assoc()['total'] ?? 0;
    
    $result = $db->query("SELECT COUNT(*) as total FROM vehicule");
    $stats['total_vehicles'] = $result->fetch_assoc()['total'] ?? 0;
    
    $result = $db->query("SELECT COALESCE(SUM(montant), 0) as total FROM paiement WHERE statut_paiement = 'paye'");
    $stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;
    
    // Occupation stats
    $result = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'disponible' THEN 1 ELSE 0 END) as disponible,
        SUM(CASE WHEN statut = 'occupee' THEN 1 ELSE 0 END) as occupee,
        SUM(CASE WHEN statut = 'reservee' THEN 1 ELSE 0 END) as reservee
    FROM place_parking");
    $stats['places'] = $result->fetch_assoc();
    
    // Daily occupation for chart (30 days)
    $result = $db->query("SELECT DATE(date) as jour, COUNT(*) as entrees 
        FROM historique 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(date) 
        ORDER BY jour DESC");
    $daily = [];
    while ($row = $result->fetch_assoc()) {
        $daily[] = $row;
    }
    $stats['daily_occupation'] = $daily;
    
    // Zone stats for chart
    $result = $db->query("SELECT p.zone, COUNT(*) as total_entrees
        FROM historique h
        INNER JOIN place_parking p ON h.id_place = p.id_place
        WHERE h.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY p.zone
        ORDER BY total_entrees DESC");
    $zones = [];
    while ($row = $result->fetch_assoc()) {
        $zones[] = $row;
    }
    $stats['zone_stats'] = $zones;
    
    // Revenue for chart (30 days)
    $result = $db->query("SELECT DATE(pa.date_paiement) as date, SUM(pa.montant) as total
        FROM paiement pa
        WHERE pa.date_paiement >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND pa.statut_paiement = 'paye'
        GROUP BY DATE(pa.date_paiement)
        ORDER BY date DESC");
    $revenue = [];
    while ($row = $result->fetch_assoc()) {
        $revenue[] = $row;
    }
    $stats['revenue_daily'] = $revenue;
    
    // Monthly trends
    $result = $db->query("SELECT 
        DATE_FORMAT(date, '%Y-%m') as month,
        COUNT(*) as entrees,
        COUNT(DISTINCT id_place) as places_utilisees
        FROM historique 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(date, '%Y-%m')
        ORDER BY month DESC");
    $monthly = [];
    while ($row = $result->fetch_assoc()) {
        $monthly[] = $row;
    }
    $stats['monthly_trends'] = $monthly;
    
    echo json_encode(['success' => true, 'stats' => $stats]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
