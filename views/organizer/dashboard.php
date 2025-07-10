<?php
/**
 * 🎪 Organizer Dashboard - EMS
 * Ekwendeni Mighty Campus Event Management System
 * Event Organizer Control Center! 🎭
 */

require_once '../../includes/functions.php';

// Get database connection
$conn = require_once '../../config/database.php';

// Initialize session manager
require_once '../../includes/session.php';
$sessionManager = new SessionManager($conn);

// Require organizer login
$sessionManager->requireLogin();
$currentUser = $sessionManager->getCurrentUser();

// Check if user is organizer or admin
if (!in_array($currentUser['role'], ['organizer', 'admin'])) {
    header('Location: ../../dashboard/index.php');
    exit;
}

// Get organizer statistics
$organizerStats = [];
$organizerId = $currentUser['user_id'];

try {
    // My events statistics
    $stmt = $conn->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count
        FROM events 
        WHERE organizer_id = ?
        GROUP BY status
    ");
    $stmt->bind_param("i", $organizerId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $eventsByStatus = ['approved' => 0, 'pending' => 0, 'draft' => 0];
    while ($row = $result->fetch_assoc()) {
        $eventsByStatus[$row['status']] = $row['count'];
    }
    $organizerStats['events'] = $eventsByStatus;
    
    // Total events count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM events WHERE organizer_id = ?");
    $stmt->bind_param("i", $organizerId);
    $stmt->execute();
    $organizerStats['total_events'] = $stmt->get_result()->fetch_assoc()['total'];
    
    // Ticket sales statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(t.ticket_id) as total_tickets,
            SUM(CASE WHEN t.payment_status = 'completed' THEN 1 ELSE 0 END) as paid_tickets,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as total_revenue,
            SUM(CASE WHEN t.payment_status = 'pending' THEN t.price ELSE 0 END) as pending_revenue
        FROM tickets t
        JOIN events e ON t.event_id = e.event_id
        WHERE e.organizer_id = ?
    ");
    $stmt->bind_param("i", $organizerId);
    $stmt->execute();
    $organizerStats['tickets'] = $stmt->get_result()->fetch_assoc();
    
    // Upcoming events
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM events 
        WHERE organizer_id = ? 
        AND start_datetime > NOW() 
        AND status = 'approved'
    ");
    $stmt->bind_param("i", $organizerId);
    $stmt->execute();
    $organizerStats['upcoming_events'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Recent activity
    $organizerStats['recent'] = [
        'events_this_month' => getSingleStat($conn, "SELECT COUNT(*) as count FROM events WHERE organizer_id = $organizerId AND MONTH(created_at) = MONTH(NOW())"),
        'tickets_this_week' => getSingleStat($conn, "SELECT COUNT(*) as count FROM tickets t JOIN events e ON t.event_id = e.event_id WHERE e.organizer_id = $organizerId AND WEEK(t.created_at) = WEEK(NOW())"),
        'revenue_this_month' => getSingleStat($conn, "SELECT COALESCE(SUM(t.price), 0) as count FROM tickets t JOIN events e ON t.event_id = e.event_id WHERE e.organizer_id = $organizerId AND t.payment_status = 'completed' AND MONTH(t.created_at) = MONTH(NOW())")
    ];
    
} catch (Exception $e) {
    error_log("Organizer stats error: " . $e->getMessage());
    $organizerStats = [
        'events' => ['approved' => 0, 'pending' => 0, 'draft' => 0],
        'total_events' => 0,
        'tickets' => ['total_tickets' => 0, 'paid_tickets' => 0, 'total_revenue' => 0, 'pending_revenue' => 0],
        'upcoming_events' => 0,
        'recent' => ['events_this_month' => 0, 'tickets_this_week' => 0, 'revenue_this_month' => 0]
    ];
}

// Get my events
$myEvents = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            e.*,
            COUNT(t.ticket_id) as total_registrations,
            SUM(CASE WHEN t.payment_status = 'completed' THEN 1 ELSE 0 END) as paid_registrations,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as event_revenue
        FROM events e
        LEFT JOIN tickets t ON e.event_id = t.event_id
        WHERE e.organizer_id = ?
        GROUP BY e.event_id
        ORDER BY e.created_at DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $organizerId);
    $stmt->execute();
    $myEvents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("My events error: " . $e->getMessage());
}

// Get recent attendees
$recentAttendees = [];
try {
    $stmt = $conn->prepare("
        SELECT 
            u.first_name, u.last_name, u.email,
            e.title as event_title,
            t.created_at, t.payment_status, t.price
        FROM tickets t
        JOIN users u ON t.user_id = u.user_id
        JOIN events e ON t.event_id = e.event_id
        WHERE e.organizer_id = ?
        ORDER BY t.created_at DESC
        LIMIT 15
    ");
    $stmt->bind_param("i", $organizerId);
    $stmt->execute();
    $recentAttendees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Recent attendees error: " . $e->getMessage());
}

// Get performance insights
$performanceInsights = [];
try {
    // Best performing event
    $stmt = $conn->prepare("
        SELECT 
            e.title,
            COUNT(t.ticket_id) as registrations,
            SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as revenue
        FROM events e
        LEFT JOIN tickets t ON e.event_id = t.event_id
        WHERE e.organizer_id = ? AND e.status = 'approved'
        GROUP BY e.event_id
        ORDER BY registrations DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $organizerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $performanceInsights['best_event'] = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    
    // Average event performance
    $stmt = $conn->prepare("
        SELECT 
            AVG(registrations) as avg_registrations,
            AVG(revenue) as avg_revenue
        FROM (
            SELECT 
                COUNT(t.ticket_id) as registrations,
                SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as revenue
            FROM events e
            LEFT JOIN tickets t ON e.event_id = t.event_id
            WHERE e.organizer_id = ? AND e.status = 'approved'
            GROUP BY e.event_id
        ) as event_stats
    ");
    $stmt->bind_param("i", $organizerId);
    $stmt->execute();
    $performanceInsights['averages'] = $stmt->get_result()->fetch_assoc();
    
} catch (Exception $e) {
    error_log("Performance insights error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organizer Dashboard - EMS</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Chart.js for Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --organizer-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --organizer-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --organizer-success: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --organizer-warning: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            --organizer-danger: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            --organizer-info: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            --organizer-purple: linear-gradient(135deg, #9c27b0 0%, #673ab7 100%);
            --sidebar-bg: #1a1a2e;
            --sidebar-hover: #16213e;
            --content-bg: #f8f9fa;
            --card-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            --card-hover-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --border-color: #e9ecef;
        }
        
        * {
            margin: 0;
            padding: 0;
                        box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--content-bg);
            overflow-x: hidden;
        }
        
        /* 🎪 Organizer Sidebar */
        .organizer-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 300px;
            background: var(--sidebar-bg);
            color: white;
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
        }
        
        .organizer-sidebar.collapsed {
            width: 80px;
        }
        
        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            background: var(--organizer-primary);
        }
        
        .sidebar-header h3 {
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: white;
        }
        
        .sidebar-header p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .organizer-nav {
            padding: 1.5rem 0;
        }
        
        .nav-section {
            margin-bottom: 2rem;
        }
        
        .nav-section-title {
            padding: 0 1.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 1rem;
        }
        
        .organizer-nav-item {
            margin: 0.3rem 0;
        }
        
        .organizer-nav-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            border-radius: 0 25px 25px 0;
            margin-right: 1rem;
        }
        
        .organizer-nav-link:hover,
        .organizer-nav-link.active {
            background: var(--sidebar-hover);
            color: white;
            transform: translateX(10px);
        }
        
        .organizer-nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--organizer-secondary);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }
        
        .organizer-nav-link:hover::before,
        .organizer-nav-link.active::before {
            transform: scaleY(1);
        }
        
        .nav-icon {
            font-size: 1.3rem;
            margin-right: 1rem;
            width: 25px;
            text-align: center;
        }
        
        .nav-text {
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .nav-badge {
            margin-left: auto;
            background: var(--organizer-danger);
            color: white;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        /* 📱 Main Content */
        .organizer-main {
            margin-left: 300px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        .organizer-main.expanded {
            margin-left: 80px;
        }
        
        /* 🎯 Organizer Top Bar */
        .organizer-topbar {
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .organizer-title {
            font-size: 2rem;
            font-weight: 800;
            background: var(--organizer-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        
        .organizer-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .organizer-search {
            position: relative;
        }
        
        .organizer-search input {
            padding: 0.7rem 1rem 0.7rem 2.5rem;
            border: 2px solid var(--border-color);
            border-radius: 25px;
            width: 300px;
            transition: all 0.3s ease;
        }
        
        .organizer-search input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .organizer-search i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }
        
        .organizer-user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .organizer-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--organizer-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.2rem;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .organizer-user-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .organizer-user-details small {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        /* 📊 Dashboard Content */
        .organizer-content {
            padding: 2rem;
        }
        
        /* 🎯 Super Stats Cards */
        .super-stats {
            margin-bottom: 2rem;
        }
        
        .super-stat-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            height: 100%;
            border: 1px solid var(--border-color);
        }
        
        .super-stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
        }
        
        .super-stat-card.primary::before { background: var(--organizer-primary); }
        .super-stat-card.success::before { background: var(--organizer-success); }
        .super-stat-card.warning::before { background: var(--organizer-warning); }
        .super-stat-card.danger::before { background: var(--organizer-danger); }
        .super-stat-card.info::before { background: var(--organizer-info); }
        .super-stat-card.purple::before { background: var(--organizer-purple); }
        
        .super-stat-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--card-hover-shadow);
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .stat-icon.primary { background: var(--organizer-primary); }
        .stat-icon.success { background: var(--organizer-success); }
        .stat-icon.warning { background: var(--organizer-warning); }
        .stat-icon.danger { background: var(--organizer-danger); }
        .stat-icon.info { background: var(--organizer-info); }
        .stat-icon.purple { background: var(--organizer-purple); }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-change {
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            display: inline-block;
        }
        
        .stat-change.positive {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .stat-change.negative {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
        }
        
        .stat-change.neutral {
            background: rgba(108, 117, 125, 0.1);
            color: var(--text-secondary);
        }
        
        /* 🎪 Organizer Content Cards */
        .organizer-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }
        
        .organizer-card:hover {
            box-shadow: var(--card-hover-shadow);
            transform: translateY(-5px);
        }
        
        .organizer-card-header {
            padding: 2rem 2rem 1rem 2rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        }
        
        .organizer-card-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }
        
        .organizer-card-subtitle {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .organizer-card-body {
            padding: 2rem;
        }
        
        /* 📊 Charts Container */
        .chart-container {
            position: relative;
            height: 400px;
            margin: 1rem 0;
        }
        
        .mini-chart-container {
            position: relative;
            height: 200px;
            margin: 1rem 0;
        }
        
        /* 🎪 Event Cards */
        .organizer-event-card {
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
            background: white;
        }
        
        .organizer-event-card:hover {
            transform: translateX(10px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .organizer-event-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            border-radius: 5px 0 0 5px;
        }
        
        .organizer-event-card.approved::before { background: var(--organizer-success); }
        .organizer-event-card.pending::before { background: var(--organizer-warning); }
        .organizer-event-card.draft::before { background: var(--organizer-info); }
        
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .event-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        
        .event-status {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-approved {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 2px solid #4CAF50;
        }
        
        .status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
            border: 2px solid #ff9800;
        }
        
        .status-draft {
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
            border: 2px solid #2196F3;
        }
        
        .event-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .event-meta-item {
            display: flex;
            align-items: center;
                        gap: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .event-meta-item i {
            color: var(--organizer-primary);
            width: 18px;
        }
        
        .event-actions {
            display: flex;
            gap: 0.7rem;
            flex-wrap: wrap;
        }
        
        .organizer-btn {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            border-radius: 20px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .organizer-btn-primary {
            background: var(--organizer-primary);
            color: white;
        }
        
        .organizer-btn-success {
            background: var(--organizer-success);
            color: white;
        }
        
        .organizer-btn-warning {
            background: var(--organizer-warning);
            color: white;
        }
        
        .organizer-btn-danger {
            background: var(--organizer-danger);
            color: white;
        }
        
        .organizer-btn-info {
            background: var(--organizer-info);
            color: white;
        }
        
        .organizer-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        /* 👥 Attendees Table */
        .organizer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .organizer-table th,
        .organizer-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .organizer-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .organizer-table tr:hover {
            background: #f8f9fa;
        }
        
        .attendee-avatar-small {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: var(--organizer-primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.8rem;
            margin-right: 0.5rem;
        }
        
        .payment-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .payment-completed {
            background: var(--organizer-success);
            color: white;
        }
        
        .payment-pending {
            background: var(--organizer-warning);
            color: white;
        }
        
        .payment-failed {
            background: var(--organizer-danger);
            color: white;
        }
        
        /* 🎨 Performance Insights */
        .insight-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }
        
        .insight-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: rotate(45deg);
        }
        
        .insight-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }
        
        .insight-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .insight-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }
        
        .insight-value {
            font-size: 2.5rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }
        
        .insight-description {
            opacity: 0.9;
            font-size: 0.9rem;
            position: relative;
            z-index: 2;
        }
        
        /* 📱 Responsive Design */
        @media (max-width: 768px) {
            .organizer-sidebar {
                transform: translateX(-100%);
            }
            
            .organizer-sidebar.show {
                transform: translateX(0);
            }
            
            .organizer-main {
                margin-left: 0;
            }
            
            .organizer-topbar {
                padding: 1rem;
            }
            
            .organizer-content {
                padding: 1rem;
            }
            
            .organizer-search input {
                width: 200px;
            }
            
            .super-stat-card {
                margin-bottom: 1rem;
            }
            
            .event-meta {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
            
            .event-actions {
                justify-content: flex-start;
            }
        }
        
        /* 🎨 Animations */
        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .slide-in-right {
            animation: slideInRight 0.6s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* 🎯 Loading States */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10;
        }
        
        /* 🎪 Quick Actions Grid */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .quick-action-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-primary);
        }
        
        .quick-action-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.15);
            color: var(--text-primary);
            text-decoration: none;
        }
        
        .quick-action-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #667eea;
        }
        
        .quick-action-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .quick-action-desc {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <!-- 🎪 Organizer Sidebar -->
    <div class="organizer-sidebar" id="organizerSidebar">
        <div class="sidebar-header">
            <h3>🎪 EMS</h3>
            <p>Event Organizer</p>
        </div>
        
        <nav class="organizer-nav">
            <div class="nav-section">
                <div class="nav-section-title">Dashboard</div>
                <div class="organizer-nav-item">
                    <a href="dashboard.php" class="organizer-nav-link active">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <span class="nav-text">Overview</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="analytics.php" class="organizer-nav-link">
                        <i class="nav-icon fas fa-chart-line"></i>
                        <span class="nav-text">Analytics</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Events</div>
                <div class="organizer-nav-item">
                    <a href="events.php" class="organizer-nav-link">
                        <i class="nav-icon fas fa-calendar-alt"></i>
                        <span class="nav-text">My Events</span>
                        <span class="nav-badge"><?= $organizerStats['total_events'] ?></span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="create_event.php" class="organizer-nav-link">
                        <i class="nav-icon fas fa-plus-circle"></i>
                        <span class="nav-text">Create Event</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="pending_events.php" class="organizer-nav-link">
                        <i class="nav-icon fas fa-clock"></i>
                        <span class="nav-text">Pending Approval</span>
                        <span class="nav-badge"><?= $organizerStats['events']['pending'] ?></span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Management</div>
                <div class="organizer-nav-item">
                    <a href="attendees.php" class="organizer-nav-link">
                        <i class="nav-icon fas fa-users"></i>
                        <span class="nav-text">Attendees</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="tickets.php" class="organizer-nav-link">
                        <i class="nav-icon fas fa-ticket-alt"></i>
                        <span class="nav-text">Tickets</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="revenue.php" class="organizer-nav-link">
                        <i class="nav-icon fas fa-dollar-sign"></i>
                        <span class="nav-text">Revenue</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Tools</div>
                <div class="organizer-nav-item">
                    <a href="reports.php" class="organizer-nav-link">
                        <i class="nav-icon fas fa-file-alt"></i>
                        <span class="nav-text">Reports</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="notifications.php" class="organizer-nav-link">
                        <i class="nav-icon fas fa-bell"></i>
                        <span class="nav-text">Notifications</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="settings.php" class="organizer-nav-link">
                        <i class="nav-icon fas fa-cog"></i>
                        <span class="nav-text">Settings</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Account</div>
                <div class="organizer-nav-item">
                    <a href="profile.php" class="organizer-nav-link">
                        <i class="nav-icon fas fa-user"></i>
                        <span class="nav-text">Profile</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="../../auth/logout.php" class="organizer-nav-link">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <span class="nav-text">Logout</span>
                    </a>
                </div>
            </div>
        </nav>
    </div>
    
    <!-- 📱 Main Content -->
    <div class="organizer-main" id="organizerMain">
        <!-- 🎯 Organizer Top Bar -->
        <div class="organizer-topbar">
            <div class="organizer-title-section">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="organizer-title">Organizer Dashboard</h1>
            </div>
            
            <div class="organizer-controls">
                <div class="organizer-search">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search events, attendees..." id="organizerSearch">
                </div>
                
                <button class="notification-btn" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="notification-count">3</span>
                </button>
                
                <div class="organizer-user-info">
                    <div class="organizer-user-details">
                                               <h6><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></h6>
                        <small>Event Organizer</small>
                    </div>
                    <div class="organizer-avatar">
                        <?= strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)) ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 📊 Dashboard Content -->
        <div class="organizer-content">
            <!-- 🎯 Super Stats Cards -->
            <div class="super-stats">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="super-stat-card primary fade-in-up">
                            <div class="stat-header">
                                <div class="stat-icon primary">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                            </div>
                            <div class="stat-number"><?= $organizerStats['total_events'] ?></div>
                            <div class="stat-label">Total Events</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i> +<?= $organizerStats['recent']['events_this_month'] ?> this month
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="super-stat-card success fade-in-up">
                            <div class="stat-header">
                                <div class="stat-icon success">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                            <div class="stat-number"><?= $organizerStats['tickets']['total_tickets'] ?></div>
                            <div class="stat-label">Total Attendees</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i> +<?= $organizerStats['recent']['tickets_this_week'] ?> this week
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="super-stat-card warning fade-in-up">
                            <div class="stat-header">
                                <div class="stat-icon warning">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                            </div>
                            <div class="stat-number">K<?= number_format($organizerStats['tickets']['total_revenue'] / 1000, 1) ?></div>
                            <div class="stat-label">Total Revenue</div>
                            <div class="stat-change positive">
                                <i class="fas fa-arrow-up"></i> K<?= number_format($organizerStats['recent']['revenue_this_month'] / 1000, 1) ?> this month
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="super-stat-card purple fade-in-up">
                            <div class="stat-header">
                                <div class="stat-icon purple">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                            <div class="stat-number"><?= $organizerStats['upcoming_events'] ?></div>
                            <div class="stat-label">Upcoming Events</div>
                            <div class="stat-change neutral">
                                <i class="fas fa-calendar"></i> Next 30 days
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 🎪 Quick Actions -->
            <div class="organizer-card fade-in-up">
                <div class="organizer-card-header">
                    <h5 class="organizer-card-title">
                        <i class="fas fa-bolt"></i>
                        Quick Actions
                    </h5>
                    <p class="organizer-card-subtitle">Manage your events efficiently</p>
                </div>
                <div class="organizer-card-body">
                    <div class="quick-actions-grid">
                        <a href="create_event.php" class="quick-action-card">
                            <div class="quick-action-icon">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <div class="quick-action-title">Create Event</div>
                            <div class="quick-action-desc">Start planning your next event</div>
                        </a>
                        
                        <a href="events.php" class="quick-action-card">
                            <div class="quick-action-icon">
                                <i class="fas fa-edit"></i>
                            </div>
                            <div class="quick-action-title">Manage Events</div>
                            <div class="quick-action-desc">Edit and update your events</div>
                        </a>
                        
                        <a href="attendees.php" class="quick-action-card">
                            <div class="quick-action-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="quick-action-title">View Attendees</div>
                            <div class="quick-action-desc">Check who's attending</div>
                        </a>
                        
                        <a href="reports.php" class="quick-action-card">
                            <div class="quick-action-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="quick-action-title">Generate Reports</div>
                            <div class="quick-action-desc">Download event analytics</div>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- 📊 Performance Analytics -->
                <div class="col-lg-8">
                    <div class="organizer-card slide-in-right">
                        <div class="organizer-card-header">
                            <h5 class="organizer-card-title">
                                <i class="fas fa-chart-line"></i>
                                Performance Analytics
                            </h5>
                            <p class="organizer-card-subtitle">Track your event success over time</p>
                        </div>
                        <div class="organizer-card-body">
                            <div class="chart-container">
                                <canvas id="performanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 🎯 Event Status Distribution -->
                <div class="col-lg-4">
                    <div class="organizer-card slide-in-right">
                        <div class="organizer-card-header">
                            <h5 class="organizer-card-title">
                                <i class="fas fa-pie-chart"></i>
                                Event Status
                            </h5>
                        </div>
                        <div class="organizer-card-body">
                            <div class="mini-chart-container">
                                <canvas id="eventStatusChart"></canvas>
                            </div>
                            <div class="chart-legend">
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #4CAF50;"></div>
                                    <span>Approved (<?= $organizerStats['events']['approved'] ?>)</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #ff9800;"></div>
                                    <span>Pending (<?= $organizerStats['events']['pending'] ?>)</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-color" style="background: #2196F3;"></div>
                                    <span>Draft (<?= $organizerStats['events']['draft'] ?>)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 🎪 My Events -->
            <div class="organizer-card fade-in-up">
                <div class="organizer-card-header">
                    <h5 class="organizer-card-title">
                        <i class="fas fa-calendar-alt"></i>
                        My Recent Events
                    </h5>
                    <p class="organizer-card-subtitle">Manage and track your events</p>
                </div>
                <div class="organizer-card-body">
                    <?php if (empty($myEvents)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-plus fa-3x text-muted mb-3"></i>
                            <h5>No Events Yet</h5>
                            <p class="text-muted">Create your first event to get started!</p>
                            <a href="create_event.php" class="organizer-btn organizer-btn-primary">
                                <i class="fas fa-plus"></i> Create Event
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($myEvents as $event): ?>
                            <div class="organizer-event-card <?= $event['status'] ?>" data-event-id="<?= $event['event_id'] ?>">
                                <div class="event-header">
                                    <h6 class="event-title"><?= htmlspecialchars($event['title']) ?></h6>
                                    <span class="event-status status-<?= $event['status'] ?>">
                                        <?= ucfirst($event['status']) ?>
                                    </span>
                                </div>
                                
                                <div class="event-meta">
                                    <div class="event-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <span><?= date('M j, Y', strtotime($event['start_datetime'])) ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-clock"></i>
                                        <span><?= date('g:i A', strtotime($event['start_datetime'])) ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?= htmlspecialchars($event['location']) ?></span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-users"></i>
                                        <span><?= $event['total_registrations'] ?> registered</span>
                                    </div>
                                    <div class="event-meta-item">
                                        <i class="fas fa-dollar-sign"></i>
                                        <span>K<?= number_format($event['event_revenue'], 2) ?> revenue</span>
                                    </div>
                                </div>
                                
                                <div class="event-actions">
                                    <a href="view_event.php?id=<?= $event['event_id'] ?>" class="organizer-btn organizer-btn-primary">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <?php if ($event['status'] !== 'approved'): ?>
                                        <a href="edit_event.php?id=<?= $event['event_id'] ?>" class="organizer-btn organizer-btn-warning">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                    <a href="attendees.php?event_id=<?= $event['event_id'] ?>" class="organizer-btn organizer-btn-info">
                                        <i class="fas fa-users"></i> Attendees
                                    </a>
                                    <?php if ($event['status'] === 'draft'): ?>
                                        <button onclick="submitForApproval(<?= $event['event_id'] ?>)" class="organizer-btn organizer-btn-success">
                                            <i class="fas fa-paper-plane"></i> Submit
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-4">
                            <a href="events.php" class="organizer-btn organizer-btn-primary">
                                <i class="fas fa-list"></i> View All Events
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="row">
                <!-- 👥 Recent Attendees -->
                <div class="col-lg-8">
                    <div class="organizer-card slide-in-right">
                        <div class="organizer-card-header">
                            <h5 class="organizer-card-title">
                                <i class="fas fa-users"></i>
                                Recent Attendees
                            </h5>
                            <p class="organizer-card-subtitle">Latest registrations across your events</p>
                        </div>
                        <div class="organizer-card-body">
                            <?php if (empty($recentAttendees)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-user-plus fa-2x text-muted mb-3"></i>
                                    <p class="text-muted">No attendees yet</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="organizer-table">
                                        <thead>
                                            <tr>
                                                <th>Attendee</th>
                                                <th>Event</th>
                                                <th>Registration Date</th>
                                                <th>Payment Status</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentAttendees as $attendee): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="attendee-avatar-small">
                                                                <?= strtoupper(substr($attendee['first_name'], 0, 1) . substr($attendee['last_name'], 0, 1)) ?>
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold"><?= htmlspecialchars($attendee['first_name'] . ' ' . $attendee['last_name']) ?></div>
                                                                <small class="text-muted"><?= htmlspecialchars($attendee['email']) ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?= htmlspecialchars($attendee['event_title']) ?></td>
                                                                                                        <td><?= date('M j, Y', strtotime($attendee['created_at'])) ?></td>
                                                    <td>
                                                        <span class="payment-badge payment-<?= $attendee['payment_status'] ?>">
                                                            <?= ucfirst($attendee['payment_status']) ?>
                                                        </span>
                                                    </td>
                                                    <td>K<?= number_format($attendee['price'], 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 🎯 Performance Insights -->
                <div class="col-lg-4">
                    <div class="organizer-card slide-in-right">
                        <div class="organizer-card-header">
                            <h5 class="organizer-card-title">
                                <i class="fas fa-trophy"></i>
                                Performance Insights
                            </h5>
                        </div>
                        <div class="organizer-card-body">
                            <?php if ($performanceInsights['best_event']): ?>
                                <div class="insight-card">
                                    <div class="insight-header">
                                        <div class="insight-icon">
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <div class="insight-title">Best Performing Event</div>
                                    </div>
                                    <div class="insight-value"><?= $performanceInsights['best_event']['registrations'] ?></div>
                                    <div class="insight-description">
                                        "<?= htmlspecialchars($performanceInsights['best_event']['title']) ?>" 
                                        generated K<?= number_format($performanceInsights['best_event']['revenue'], 2) ?> revenue
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($performanceInsights['averages']): ?>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <h4 class="text-primary mb-1"><?= round($performanceInsights['averages']['avg_registrations']) ?></h4>
                                            <small class="text-muted">Avg. Attendees</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-3 bg-light rounded">
                                            <h4 class="text-success mb-1">K<?= number_format($performanceInsights['averages']['avg_revenue'], 0) ?></h4>
                                            <small class="text-muted">Avg. Revenue</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Payment Completion Rate</span>
                                    <span class="fw-bold">
                                        <?= $organizerStats['tickets']['total_tickets'] > 0 ? 
                                            round(($organizerStats['tickets']['paid_tickets'] / $organizerStats['tickets']['total_tickets']) * 100) : 0 ?>%
                                    </span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?= $organizerStats['tickets']['total_tickets'] > 0 ? 
                                        round(($organizerStats['tickets']['paid_tickets'] / $organizerStats['tickets']['total_tickets']) * 100) : 0 ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 🔔 Notification Panel -->
    <div class="notification-panel" id="notificationPanel">
        <div class="notification-header">
            <h6><i class="fas fa-bell"></i> Notifications</h6>
            <button class="btn-close" onclick="toggleNotifications()"></button>
        </div>
        <div class="notification-body">
            <div class="notification-item">
                <div class="notification-icon bg-success">
                    <i class="fas fa-check"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">Event Approved</div>
                    <div class="notification-text">Your event "Tech Conference 2024" has been approved!</div>
                    <div class="notification-time">2 hours ago</div>
                </div>
            </div>
            
            <div class="notification-item">
                <div class="notification-icon bg-info">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">New Registration</div>
                    <div class="notification-text">John Doe registered for your event</div>
                    <div class="notification-time">4 hours ago</div>
                </div>
            </div>
            
            <div class="notification-item">
                <div class="notification-icon bg-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">Payment Pending</div>
                    <div class="notification-text">5 payments are still pending for your events</div>
                    <div class="notification-time">1 day ago</div>
                </div>
            </div>
        </div>
        <div class="notification-footer">
            <a href="notifications.php" class="btn btn-sm btn-primary w-100">View All Notifications</a>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 🎪 Organizer Dashboard JavaScript
        
        // Sidebar Toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('organizerSidebar');
            const main = document.getElementById('organizerMain');
            
            sidebar.classList.toggle('collapsed');
            main.classList.toggle('expanded');
        }
        
        // Notification Panel Toggle
        function toggleNotifications() {
            const panel = document.getElementById('notificationPanel');
            panel.classList.toggle('show');
        }
        
        // Close notification panel when clicking outside
        document.addEventListener('click', function(e) {
            const panel = document.getElementById('notificationPanel');
            const btn = document.querySelector('.notification-btn');
            
            if (!panel.contains(e.target) && !btn.contains(e.target)) {
                panel.classList.remove('show');
            }
        });
        
        // Search functionality
        document.getElementById('organizerSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const eventCards = document.querySelectorAll('.organizer-event-card');
            
            eventCards.forEach(card => {
                const title = card.querySelector('.event-title').textContent.toLowerCase();
                const location = card.querySelector('.event-meta-item:nth-child(3) span').textContent.toLowerCase();
                
                if (title.includes(searchTerm) || location.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Submit event for approval
        function submitForApproval(eventId) {
            if (confirm('Are you sure you want to submit this event for approval?')) {
                fetch('../../api/submit_event.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ event_id: eventId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Event submitted for approval successfully!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(data.message || 'Error submitting event', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Network error occurred', 'error');
                });
            }
        }
        
        // Toast notification system
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            document.body.appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 5000);
        }
        
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Performance Chart
            const performanceCtx = document.getElementById('performanceChart').getContext('2d');
            new Chart(performanceCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Event Registrations',
                        data: [12, 19, 15, 25, 22, 30],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Revenue (K)',
                        data: [5, 8, 6, 12, 10, 15],
                        borderColor: '#f093fb',
                        backgroundColor: 'rgba(240, 147, 251, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(0,0,0,0.1)'
                            }
                        }
                    }
                }
            });
            
            // Event Status Chart
            const statusCtx = document.getElementById('eventStatusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Approved', 'Pending', 'Draft'],
                    datasets: [{
                        data: [
                            <?= $organizerStats['events']['approved'] ?>,
                            <?= $organizerStats['events']['pending'] ?>,
                            <?= $organizerStats['events']['draft'] ?>
                        ],
                        backgroundColor: ['#4CAF50', '#ff9800', '#2196F3'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        });
        
        // Mobile responsiveness
        if (window.innerWidth <= 768) {
            document.getElementById('organizerSidebar').classList.add('collapsed');
            document.getElementById('organizerMain').classList.add('expanded');
        }
        
        // Add additional styles for toast notifications
        const additionalStyles = `
            <style>
                .toast-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
                    padding: 1rem;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    min-width: 300px;
                    z-index: 9999;
                    animation: slideInRight 0.3s ease-out;
                }
                
                .toast-success {
                    border-left: 4px solid #4CAF50;
                }
                
                .toast-error {
                    border-left: 4px solid #f44336;
                }
                
                .toast-info {
                    border-left: 4px solid #2196F3;
                }
                
                .toast-content {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                }
                
                .toast-content i {
                    font-size: 1.2rem;
                }
                
                .toast-success .toast-content i {
                    color: #4CAF50;
                }
                
                .toast-error .toast-content i {
                    color: #f44336;
                }
                
                .toast-info .toast-content i {
                    color: #2196F3;
                }
                
                .toast-close {
                    background: none;
                    border: none;
                    font-size: 1rem;
                    color: #999;
                    cursor: pointer;
                    padding: 0.2rem;
                }
                
                .toast-close:hover {
                    color: #333;
                }
                
                .notification-panel {
                    position: fixed;
                    top: 0;
                    right: -400px;
                    width: 400px;
                    height: 100vh;
                    background: white;
                    box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
                    transition: right 0.3s ease;
                    z-index: 999;
                    display: flex;
                    flex-direction: column;
                }
                
                .notification-panel.show {
                    right: 0;
                }
                
                .notification-header {
                    padding: 1.5rem;
                    border-bottom: 1px solid var(--border-color);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                                        background: var(--organizer-primary);
                    color: white;
                }
                
                .notification-header h6 {
                    margin: 0;
                    font-weight: 600;
                }
                
                .btn-close {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 1.2rem;
                    cursor: pointer;
                    padding: 0.5rem;
                    border-radius: 50%;
                    transition: background 0.3s ease;
                }
                
                .btn-close:hover {
                    background: rgba(255, 255, 255, 0.1);
                }
                
                .notification-body {
                    flex: 1;
                    overflow-y: auto;
                    padding: 1rem;
                }
                
                .notification-item {
                    display: flex;
                    align-items: flex-start;
                    gap: 1rem;
                    padding: 1rem;
                    border-radius: 10px;
                    margin-bottom: 0.5rem;
                    transition: background 0.3s ease;
                }
                
                .notification-item:hover {
                    background: #f8f9fa;
                }
                
                .notification-icon {
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 0.9rem;
                    flex-shrink: 0;
                }
                
                .notification-content {
                    flex: 1;
                }
                
                .notification-title {
                    font-weight: 600;
                    color: var(--text-primary);
                    margin-bottom: 0.3rem;
                }
                
                .notification-text {
                    font-size: 0.9rem;
                    color: var(--text-secondary);
                    margin-bottom: 0.5rem;
                }
                
                .notification-time {
                    font-size: 0.8rem;
                    color: #999;
                }
                
                .notification-footer {
                    padding: 1rem;
                    border-top: 1px solid var(--border-color);
                }
                
                .notification-btn {
                    position: relative;
                    background: none;
                    border: none;
                    font-size: 1.2rem;
                    color: var(--text-secondary);
                    cursor: pointer;
                    padding: 0.5rem;
                    border-radius: 50%;
                    transition: all 0.3s ease;
                }
                
                .notification-btn:hover {
                    background: #f8f9fa;
                    color: var(--text-primary);
                }
                
                .notification-count {
                    position: absolute;
                    top: -5px;
                    right: -5px;
                    background: #f44336;
                    color: white;
                    border-radius: 50%;
                    width: 20px;
                    height: 20px;
                    font-size: 0.7rem;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: 600;
                }
                
                .sidebar-toggle {
                    background: none;
                    border: none;
                    font-size: 1.2rem;
                    color: var(--text-primary);
                    cursor: pointer;
                    padding: 0.5rem;
                    border-radius: 5px;
                    margin-right: 1rem;
                    transition: background 0.3s ease;
                }
                
                .sidebar-toggle:hover {
                    background: #f8f9fa;
                }
                
                .organizer-title-section {
                    display: flex;
                    align-items: center;
                }
                
                .chart-legend {
                    margin-top: 1rem;
                }
                
                .legend-item {
                    display: flex;
                    align-items: center;
                    gap: 0.5rem;
                    margin-bottom: 0.5rem;
                    font-size: 0.9rem;
                }
                
                .legend-color {
                    width: 12px;
                    height: 12px;
                    border-radius: 2px;
                }
                
                @keyframes slideInRight {
                    from {
                        opacity: 0;
                        transform: translateX(30px);
                    }
                    to {
                        opacity: 1;
                        transform: translateX(0);
                    }
                }
                
                @media (max-width: 768px) {
                    .notification-panel {
                        width: 100%;
                        right: -100%;
                    }
                    
                    .toast-notification {
                        right: 10px;
                        left: 10px;
                        min-width: auto;
                    }
                }
            </style>
        `;
        
        document.head.insertAdjacentHTML('beforeend', additionalStyles);
        
        // Auto-refresh data every 5 minutes
        setInterval(function() {
            // Refresh notification count
            fetch('../../api/get_notifications_count.php')
                .then(response => response.json())
                .then(data => {
                    const countElement = document.querySelector('.notification-count');
                    if (countElement && data.count > 0) {
                        countElement.textContent = data.count;
                        countElement.style.display = 'flex';
                    } else if (countElement) {
                        countElement.style.display = 'none';
                    }
                })
                .catch(error => console.error('Error fetching notifications:', error));
        }, 300000); // 5 minutes
        
        // Initialize tooltips if Bootstrap is available
        if (typeof bootstrap !== 'undefined') {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N for new event
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'create_event.php';
            }
            
            // Ctrl/Cmd + E for events list
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                window.location.href = 'events.php';
            }
            
            // Escape to close notification panel
            if (e.key === 'Escape') {
                document.getElementById('notificationPanel').classList.remove('show');
            }
        });
        
        // Add loading states for buttons
        document.querySelectorAll('.organizer-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (this.classList.contains('loading')) return;
                
                const originalText = this.innerHTML;
                this.classList.add('loading');
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                
                // Reset after 3 seconds (adjust based on your needs)
                setTimeout(() => {
                    this.classList.remove('loading');
                    this.innerHTML = originalText;
                }, 3000);
            });
        });
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        console.log('🎪 Organizer Dashboard Loaded Successfully!');
    </script>
</body>
</html>



