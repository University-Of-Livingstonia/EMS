<?php
/**
 * 📊 Reports Dashboard - EMS Organizer
 * Ekwendeni Mighty Campus Event Management System
 * Generate Comprehensive Event Reports! 📈
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

$organizerId = $currentUser['user_id'];

// Get report parameters
$reportType = $_GET['report_type'] ?? 'overview';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$eventId = $_GET['event_id'] ?? 'all';
$format = $_GET['format'] ?? 'web';

// Generate report based on type
$reportData = [];
$reportTitle = '';
$reportDescription = '';

switch ($reportType) {
    case 'overview':
        $reportTitle = 'Event Overview Report';
        $reportDescription = 'Comprehensive overview of all your events and their performance';
        $reportData = generateOverviewReport($conn, $organizerId, $startDate, $endDate, $eventId);
        break;
        
    case 'revenue':
        $reportTitle = 'Revenue Analysis Report';
        $reportDescription = 'Detailed revenue breakdown and financial performance';
        $reportData = generateRevenueReport($conn, $organizerId, $startDate, $endDate, $eventId);
        break;
        
    case 'attendees':
        $reportTitle = 'Attendee Analytics Report';
        $reportDescription = 'Attendee demographics and engagement metrics';
        $reportData = generateAttendeeReport($conn, $organizerId, $startDate, $endDate, $eventId);
        break;
        
    case 'performance':
        $reportTitle = 'Performance Metrics Report';
        $reportDescription = 'Event performance indicators and success metrics';
        $reportData = generatePerformanceReport($conn, $organizerId, $startDate, $endDate, $eventId);
        break;
        
    default:
        $reportType = 'overview';
        $reportTitle = 'Event Overview Report';
        $reportDescription = 'Comprehensive overview of all your events and their performance';
        $reportData = generateOverviewReport($conn, $organizerId, $startDate, $endDate, $eventId);
}

// Get organizer's events for filter
$organizerEvents = [];
try {
    $stmt = $conn->prepare("
        SELECT event_id, title, start_datetime
        FROM events 
        WHERE organizer_id = ? 
        AND status = 'approved'
        ORDER BY start_datetime DESC
    ");
    $stmt->bind_param("i", $organizerId);
    $stmt->execute();
    $organizerEvents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Organizer events error: " . $e->getMessage());
}

// Report generation functions
function generateOverviewReport($conn, $organizerId, $startDate, $endDate, $eventId) {
    $data = [];
    
    try {
        $whereClause = "WHERE e.organizer_id = ?";
        $params = [$organizerId];
        $types = "i";
        
        if ($eventId !== 'all') {
            $whereClause .= " AND e.event_id = ?";
            $params[] = $eventId;
            $types .= "i";
        }
        
        $whereClause .= " AND DATE(e.created_at) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $types .= "ss";
        
        // Event summary
        $stmt = $conn->prepare("
            SELECT 
                COUNT(e.event_id) as total_events,
                COUNT(CASE WHEN e.status = 'approved' THEN 1 END) as approved_events,
                COUNT(CASE WHEN e.status = 'pending' THEN 1 END) as pending_events,
                COUNT(CASE WHEN e.start_datetime > NOW() THEN 1 END) as upcoming_events,
                COUNT(CASE WHEN e.start_datetime <= NOW() THEN 1 END) as past_events,
                AVG(e.max_attendees) as avg_capacity,
                SUM(e.max_attendees) as total_capacity
            FROM events e
            $whereClause
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['event_summary'] = $stmt->get_result()->fetch_assoc();
        
        // Ticket summary
        $stmt = $conn->prepare("
            SELECT 
                COUNT(t.ticket_id) as total_tickets,
                COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) as paid_tickets,
                COUNT(CASE WHEN t.payment_status = 'pending' THEN 1 END) as pending_tickets,
                SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as total_revenue,
                AVG(t.price) as avg_ticket_price
            FROM events e
            JOIN tickets t ON e.event_id = t.event_id
            $whereClause
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['ticket_summary'] = $stmt->get_result()->fetch_assoc();
        
        // Event details
        $stmt = $conn->prepare("
            SELECT 
                e.event_id,
                e.title,
                e.start_datetime,
                e.end_datetime,
                e.venue,
                e.max_attendees,
                e.ticket_price,
                e.status,
                COUNT(t.ticket_id) as tickets_sold,
                SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as event_revenue,
                (COUNT(t.ticket_id) * 100.0 / e.max_attendees) as capacity_percentage
            FROM events e
            LEFT JOIN tickets t ON e.event_id = t.event_id
            $whereClause
            GROUP BY e.event_id
            ORDER BY e.start_datetime DESC
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['event_details'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
    } catch (Exception $e) {
        error_log("Overview report error: " . $e->getMessage());
    }
    
    return $data;
}

function generateRevenueReport($conn, $organizerId, $startDate, $endDate, $eventId) {
    $data = [];
    
    try {
        $whereClause = "WHERE e.organizer_id = ?";
        $params = [$organizerId];
        $types = "i";
        
        if ($eventId !== 'all') {
            $whereClause .= " AND e.event_id = ?";
            $params[] = $eventId;
            $types .= "i";
        }
        
        $whereClause .= " AND DATE(t.created_at) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $types .= "ss";
        
        // Revenue summary
        $stmt = $conn->prepare("
            SELECT 
                SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as total_revenue,
                SUM(CASE WHEN t.payment_status = 'pending' THEN t.price ELSE 0 END) as pending_revenue,
                SUM(CASE WHEN t.payment_status = 'failed' THEN t.price ELSE 0 END) as failed_revenue,
                COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) as completed_transactions,
                COUNT(CASE WHEN t.payment_status = 'pending' THEN 1 END) as pending_transactions,
                COUNT(CASE WHEN t.payment_status = 'failed' THEN 1 END) as failed_transactions,
                AVG(CASE WHEN t.payment_status = 'completed' THEN t.price END) as avg_transaction_value
            FROM events e
            JOIN tickets t ON e.event_id = t.event_id
            $whereClause
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['revenue_summary'] = $stmt->get_result()->fetch_assoc();
        
        // Daily revenue breakdown
        $stmt = $conn->prepare("
            SELECT 
                DATE(t.created_at) as revenue_date,
                SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as daily_revenue,
                                COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) as daily_transactions
            FROM events e
            JOIN tickets t ON e.event_id = t.event_id
            $whereClause
            GROUP BY DATE(t.created_at)
            ORDER BY revenue_date DESC
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['daily_revenue'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Payment method breakdown
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(t.payment_method, 'Not Specified') as payment_method,
                COUNT(t.ticket_id) as method_transactions,
                SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as method_revenue,
                AVG(CASE WHEN t.payment_status = 'completed' THEN t.price END) as avg_method_value
            FROM events e
            JOIN tickets t ON e.event_id = t.event_id
            $whereClause
            AND t.payment_status = 'completed'
            GROUP BY t.payment_method
            ORDER BY method_revenue DESC
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['payment_methods'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Event revenue ranking
        $stmt = $conn->prepare("
            SELECT 
                e.title,
                e.start_datetime,
                e.ticket_price,
                COUNT(t.ticket_id) as tickets_sold,
                SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as event_revenue,
                (COUNT(t.ticket_id) * 100.0 / e.max_attendees) as capacity_utilization
            FROM events e
            LEFT JOIN tickets t ON e.event_id = t.event_id
            $whereClause
            GROUP BY e.event_id
            ORDER BY event_revenue DESC
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['event_revenue_ranking'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
    } catch (Exception $e) {
        error_log("Revenue report error: " . $e->getMessage());
    }
    
    return $data;
}

function generateAttendeeReport($conn, $organizerId, $startDate, $endDate, $eventId) {
    $data = [];
    
    try {
        $whereClause = "WHERE e.organizer_id = ?";
        $params = [$organizerId];
        $types = "i";
        
        if ($eventId !== 'all') {
            $whereClause .= " AND e.event_id = ?";
            $params[] = $eventId;
            $types .= "i";
        }
        
        $whereClause .= " AND DATE(t.created_at) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $types .= "ss";
        
        // Attendee summary
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT u.user_id) as unique_attendees,
                COUNT(t.ticket_id) as total_registrations,
                COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) as confirmed_attendees,
                AVG(TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE())) as avg_age,
                COUNT(CASE WHEN u.gender = 'male' THEN 1 END) as male_attendees,
                COUNT(CASE WHEN u.gender = 'female' THEN 1 END) as female_attendees
            FROM events e
            JOIN tickets t ON e.event_id = t.event_id
            JOIN users u ON t.user_id = u.user_id
            $whereClause
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['attendee_summary'] = $stmt->get_result()->fetch_assoc();
        
        // Registration timeline
        $stmt = $conn->prepare("
            SELECT 
                DATE(t.created_at) as registration_date,
                COUNT(t.ticket_id) as daily_registrations,
                COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) as daily_confirmations
            FROM events e
            JOIN tickets t ON e.event_id = t.event_id
            $whereClause
            GROUP BY DATE(t.created_at)
            ORDER BY registration_date DESC
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['registration_timeline'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Top attendees (frequent participants)
        $stmt = $conn->prepare("
            SELECT 
                u.first_name,
                u.last_name,
                u.email,
                COUNT(t.ticket_id) as events_attended,
                SUM(t.price) as total_spent,
                MAX(t.created_at) as last_registration
            FROM events e
            JOIN tickets t ON e.event_id = t.event_id
            JOIN users u ON t.user_id = u.user_id
            $whereClause
            AND t.payment_status = 'completed'
            GROUP BY u.user_id
            ORDER BY events_attended DESC, total_spent DESC
            LIMIT 20
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['top_attendees'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Event popularity
        $stmt = $conn->prepare("
            SELECT 
                e.title,
                e.start_datetime,
                e.max_attendees,
                COUNT(t.ticket_id) as registrations,
                COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) as confirmed,
                (COUNT(t.ticket_id) * 100.0 / e.max_attendees) as registration_rate
            FROM events e
            LEFT JOIN tickets t ON e.event_id = t.event_id
            $whereClause
            GROUP BY e.event_id
            ORDER BY registration_rate DESC
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['event_popularity'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
    } catch (Exception $e) {
        error_log("Attendee report error: " . $e->getMessage());
    }
    
    return $data;
}

function generatePerformanceReport($conn, $organizerId, $startDate, $endDate, $eventId) {
    $data = [];
    
    try {
        $whereClause = "WHERE e.organizer_id = ?";
        $params = [$organizerId];
        $types = "i";
        
        if ($eventId !== 'all') {
            $whereClause .= " AND e.event_id = ?";
            $params[] = $eventId;
            $types .= "i";
        }
        
        $whereClause .= " AND DATE(e.created_at) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $types .= "ss";
        
        // Performance metrics
        $stmt = $conn->prepare("
            SELECT 
                COUNT(e.event_id) as total_events,
                AVG(CASE WHEN t.ticket_count > 0 THEN (t.confirmed_tickets * 100.0 / e.max_attendees) END) as avg_capacity_utilization,
                AVG(CASE WHEN t.ticket_count > 0 THEN (t.confirmed_tickets * 100.0 / t.ticket_count) END) as avg_conversion_rate,
                AVG(t.revenue_per_event) as avg_revenue_per_event,
                COUNT(CASE WHEN t.confirmed_tickets >= (e.max_attendees * 0.8) THEN 1 END) as successful_events,
                COUNT(CASE WHEN t.confirmed_tickets < (e.max_attendees * 0.3) THEN 1 END) as underperforming_events
            FROM events e
            LEFT JOIN (
                SELECT 
                    event_id,
                    COUNT(ticket_id) as ticket_count,
                    COUNT(CASE WHEN payment_status = 'completed' THEN 1 END) as confirmed_tickets,
                    SUM(CASE WHEN payment_status = 'completed' THEN price ELSE 0 END) as revenue_per_event
                FROM tickets
                GROUP BY event_id
            ) t ON e.event_id = t.event_id
            $whereClause
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['performance_metrics'] = $stmt->get_result()->fetch_assoc();
        
        // Event success factors
        $stmt = $conn->prepare("
            SELECT 
                e.title,
                e.start_datetime,
                e.ticket_price,
                e.max_attendees,
                COUNT(t.ticket_id) as total_registrations,
                COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) as confirmed_attendees,
                SUM(CASE WHEN t.payment_status = 'completed' THEN t.price ELSE 0 END) as event_revenue,
                (COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) * 100.0 / e.max_attendees) as capacity_utilization,
                (COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) * 100.0 / NULLIF(COUNT(t.ticket_id), 0)) as conversion_rate,
                CASE 
                    WHEN COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) >= (e.max_attendees * 0.8) THEN 'Excellent'
                    WHEN COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) >= (e.max_attendees * 0.6) THEN 'Good'
                    WHEN COUNT(CASE WHEN t.payment_status = 'completed' THEN 1 END) >= (e.max_attendees * 0.3) THEN 'Average'
                    ELSE 'Poor'
                END as performance_rating
            FROM events e
            LEFT JOIN tickets t ON e.event_id = t.event_id
            $whereClause
            GROUP BY e.event_id
            ORDER BY capacity_utilization DESC
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['event_performance'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Monthly performance trends
        $stmt = $conn->prepare("
            SELECT 
                DATE_FORMAT(e.start_datetime, '%Y-%m') as month,
                COUNT(e.event_id) as events_count,
                AVG(CASE WHEN t.ticket_count > 0 THEN (t.confirmed_tickets * 100.0 / e.max_attendees) END) as avg_capacity,
                SUM(t.revenue_per_event) as monthly_revenue
            FROM events e
            LEFT JOIN (
                SELECT 
                    event_id,
                    COUNT(ticket_id) as ticket_count,
                    COUNT(CASE WHEN payment_status = 'completed' THEN 1 END) as confirmed_tickets,
                    SUM(CASE WHEN payment_status = 'completed' THEN price ELSE 0 END) as revenue_per_event
                FROM tickets
                GROUP BY event_id
            ) t ON e.event_id = t.event_id
            $whereClause
            GROUP BY DATE_FORMAT(e.start_datetime, '%Y-%m')
            ORDER BY month DESC
        ");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $data['monthly_trends'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
    } catch (Exception $e) {
        error_log("Performance report error: " . $e->getMessage());
    }
    
    return $data;
}

// Handle export requests
if ($format !== 'web') {
    switch ($format) {
        case 'pdf':
            exportToPDF($reportData, $reportTitle, $reportType);
            break;
        case 'excel':
            exportToExcel($reportData, $reportTitle, $reportType);
            break;
        case 'csv':
            exportToCSV($reportData, $reportTitle, $reportType);
            break;
    }
    exit;
}

function exportToPDF($data, $title, $type) {
    // Simple HTML to PDF export
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . strtolower(str_replace(' ', '_', $title)) . '.pdf"');
    
    // This would typically use a PDF library like TCPDF or DOMPDF
    // For now, we'll create a simple HTML version
    echo generateReportHTML($data, $title, $type);
}

function exportToExcel($data, $title, $type) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . strtolower(str_replace(' ', '_', $title)) . '.xls"');
    
    echo generateReportHTML($data, $title, $type);
}

function exportToCSV($data, $title, $type) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . strtolower(str_replace(' ', '_', $title)) . '.csv"');
    
    // Generate CSV content based on report type
    echo generateReportCSV($data, $type);
}

function generateReportHTML($data, $title, $type) {
    $html = "
    <html>
    <head>
        <title>$title</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .summary { background: #f5f5f5; padding: 15px; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .metric { display: inline-block; margin: 10px; padding: 10px; background: #e9e9e9; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>$title</h1>
            <p>Generated on " . date('F j, Y g:i A') . "</p>
        </div>
    ";
    
    // Add content based on report type
    switch ($type) {
        case 'overview':
                      $html .= generateOverviewHTML($data);
            break;
        case 'revenue':
            $html .= generateRevenueHTML($data);
            break;
        case 'attendees':
            $html .= generateAttendeeHTML($data);
            break;
        case 'performance':
            $html .= generatePerformanceHTML($data);
            break;
    }
    
    $html .= "</body></html>";
    return $html;
}

function generateOverviewHTML($data) {
    $html = "<div class='summary'>";
    $html .= "<h2>Event Summary</h2>";
    if (isset($data['event_summary'])) {
        $summary = $data['event_summary'];
        $html .= "<div class='metric'>Total Events: " . $summary['total_events'] . "</div>";
        $html .= "<div class='metric'>Approved: " . $summary['approved_events'] . "</div>";
        $html .= "<div class='metric'>Upcoming: " . $summary['upcoming_events'] . "</div>";
        $html .= "<div class='metric'>Total Capacity: " . number_format($summary['total_capacity']) . "</div>";
    }
    $html .= "</div>";
    
    if (isset($data['event_details']) && !empty($data['event_details'])) {
        $html .= "<h2>Event Details</h2>";
        $html .= "<table>";
        $html .= "<tr><th>Event</th><th>Date</th><th>Venue</th><th>Tickets Sold</th><th>Revenue</th><th>Capacity %</th></tr>";
        foreach ($data['event_details'] as $event) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($event['title']) . "</td>";
            $html .= "<td>" . date('M j, Y', strtotime($event['start_datetime'])) . "</td>";
            $html .= "<td>" . htmlspecialchars($event['venue']) . "</td>";
            $html .= "<td>" . $event['tickets_sold'] . "</td>";
            $html .= "<td>K" . number_format($event['event_revenue']) . "</td>";
            $html .= "<td>" . number_format($event['capacity_percentage'], 1) . "%</td>";
            $html .= "</tr>";
        }
        $html .= "</table>";
    }
    
    return $html;
}

function generateRevenueHTML($data) {
    $html = "<div class='summary'>";
    $html .= "<h2>Revenue Summary</h2>";
    if (isset($data['revenue_summary'])) {
        $summary = $data['revenue_summary'];
        $html .= "<div class='metric'>Total Revenue: K" . number_format($summary['total_revenue']) . "</div>";
        $html .= "<div class='metric'>Pending: K" . number_format($summary['pending_revenue']) . "</div>";
        $html .= "<div class='metric'>Completed Transactions: " . $summary['completed_transactions'] . "</div>";
        $html .= "<div class='metric'>Average Transaction: K" . number_format($summary['avg_transaction_value']) . "</div>";
    }
    $html .= "</div>";
    
    if (isset($data['event_revenue_ranking']) && !empty($data['event_revenue_ranking'])) {
        $html .= "<h2>Event Revenue Ranking</h2>";
        $html .= "<table>";
        $html .= "<tr><th>Event</th><th>Date</th><th>Tickets Sold</th><th>Revenue</th><th>Capacity %</th></tr>";
        foreach ($data['event_revenue_ranking'] as $event) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($event['title']) . "</td>";
            $html .= "<td>" . date('M j, Y', strtotime($event['start_datetime'])) . "</td>";
            $html .= "<td>" . $event['tickets_sold'] . "</td>";
            $html .= "<td>K" . number_format($event['event_revenue']) . "</td>";
            $html .= "<td>" . number_format($event['capacity_utilization'], 1) . "%</td>";
            $html .= "</tr>";
        }
        $html .= "</table>";
    }
    
    return $html;
}

function generateAttendeeHTML($data) {
    $html = "<div class='summary'>";
    $html .= "<h2>Attendee Summary</h2>";
    if (isset($data['attendee_summary'])) {
        $summary = $data['attendee_summary'];
        $html .= "<div class='metric'>Unique Attendees: " . $summary['unique_attendees'] . "</div>";
        $html .= "<div class='metric'>Total Registrations: " . $summary['total_registrations'] . "</div>";
        $html .= "<div class='metric'>Confirmed: " . $summary['confirmed_attendees'] . "</div>";
        $html .= "<div class='metric'>Average Age: " . number_format($summary['avg_age'], 1) . " years</div>";
    }
    $html .= "</div>";
    
    if (isset($data['top_attendees']) && !empty($data['top_attendees'])) {
        $html .= "<h2>Top Attendees</h2>";
        $html .= "<table>";
        $html .= "<tr><th>Name</th><th>Email</th><th>Events Attended</th><th>Total Spent</th></tr>";
        foreach ($data['top_attendees'] as $attendee) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($attendee['first_name'] . ' ' . $attendee['last_name']) . "</td>";
            $html .= "<td>" . htmlspecialchars($attendee['email']) . "</td>";
            $html .= "<td>" . $attendee['events_attended'] . "</td>";
            $html .= "<td>K" . number_format($attendee['total_spent']) . "</td>";
            $html .= "</tr>";
        }
        $html .= "</table>";
    }
    
    return $html;
}

function generatePerformanceHTML($data) {
    $html = "<div class='summary'>";
    $html .= "<h2>Performance Metrics</h2>";
    if (isset($data['performance_metrics'])) {
        $metrics = $data['performance_metrics'];
        $html .= "<div class='metric'>Total Events: " . $metrics['total_events'] . "</div>";
        $html .= "<div class='metric'>Avg Capacity Utilization: " . number_format($metrics['avg_capacity_utilization'], 1) . "%</div>";
        $html .= "<div class='metric'>Avg Conversion Rate: " . number_format($metrics['avg_conversion_rate'], 1) . "%</div>";
        $html .= "<div class='metric'>Successful Events: " . $metrics['successful_events'] . "</div>";
    }
    $html .= "</div>";
    
    if (isset($data['event_performance']) && !empty($data['event_performance'])) {
        $html .= "<h2>Event Performance</h2>";
        $html .= "<table>";
        $html .= "<tr><th>Event</th><th>Date</th><th>Confirmed Attendees</th><th>Capacity %</th><th>Revenue</th><th>Rating</th></tr>";
        foreach ($data['event_performance'] as $event) {
            $html .= "<tr>";
            $html .= "<td>" . htmlspecialchars($event['title']) . "</td>";
            $html .= "<td>" . date('M j, Y', strtotime($event['start_datetime'])) . "</td>";
            $html .= "<td>" . $event['confirmed_attendees'] . "</td>";
            $html .= "<td>" . number_format($event['capacity_utilization'], 1) . "%</td>";
            $html .= "<td>K" . number_format($event['event_revenue']) . "</td>";
            $html .= "<td>" . $event['performance_rating'] . "</td>";
            $html .= "</tr>";
        }
        $html .= "</table>";
    }
    
    return $html;
}

function generateReportCSV($data, $type) {
    $csv = '';
    
    switch ($type) {
        case 'overview':
            if (isset($data['event_details'])) {
                $csv .= "Event,Date,Venue,Tickets Sold,Revenue,Capacity %\n";
                foreach ($data['event_details'] as $event) {
                    $csv .= '"' . $event['title'] . '",';
                    $csv .= '"' . date('M j, Y', strtotime($event['start_datetime'])) . '",';
                    $csv .= '"' . $event['venue'] . '",';
                    $csv .= $event['tickets_sold'] . ',';
                    $csv .= $event['event_revenue'] . ',';
                    $csv .= number_format($event['capacity_percentage'], 1) . "\n";
                }
            }
            break;
            
        case 'revenue':
            if (isset($data['event_revenue_ranking'])) {
                $csv .= "Event,Date,Tickets Sold,Revenue,Capacity %\n";
                foreach ($data['event_revenue_ranking'] as $event) {
                    $csv .= '"' . $event['title'] . '",';
                    $csv .= '"' . date('M j, Y', strtotime($event['start_datetime'])) . '",';
                    $csv .= $event['tickets_sold'] . ',';
                    $csv .= $event['event_revenue'] . ',';
                    $csv .= number_format($event['capacity_utilization'], 1) . "\n";
                }
            }
            break;
            
        case 'attendees':
            if (isset($data['top_attendees'])) {
                $csv .= "Name,Email,Events Attended,Total Spent\n";
                foreach ($data['top_attendees'] as $attendee) {
                    $csv .= '"' . $attendee['first_name'] . ' ' . $attendee['last_name'] . '",';
                    $csv .= '"' . $attendee['email'] . '",';
                    $csv .= $attendee['events_attended'] . ',';
                    $csv .= $attendee['total_spent'] . "\n";
                }
            }
            break;
            
        case 'performance':
            if (isset($data['event_performance'])) {
                $csv .= "Event,Date,Confirmed Attendees,Capacity %,Revenue,Rating\n";
                foreach ($data['event_performance'] as $event) {
                    $csv .= '"' . $event['title'] . '",';
                    $csv .= '"' . date('M j, Y', strtotime($event['start_datetime'])) . '",';
                    $csv .= $event['confirmed_attendees'] . ',';
                    $csv .= number_format($event['capacity_utilization'], 1) . ',';
                    $csv .= $event['event_revenue'] . ',';
                    $csv .= '"' . $event['performance_rating'] . '"' . "\n";
                }
            }
            break;
    }
    
    return $csv;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Dashboard - EMS Organizer</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Chart.js for Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    <style>
        :root {
            --organizer-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --organizer-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --organizer-success: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --organizer-warning: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            --organizer-danger: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            --organizer-info: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
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
        
        /* 📊 Reports Content */
        .reports-content {
            padding: 2rem;
        }
        
        /* 🎯 Report Filters */
        .report-filters {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .filter-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }
        
        .report-type-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .report-tab {
            padding: 0.7rem 1.5rem;
            border: 2px solid var(--border-color);
            border-radius: 25px;
            background: white;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .report-tab.active,
        .report-tab:hover {
            background: var(--organizer-primary);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .filter-input {
            padding: 0.8rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .filter-btn {
            padding: 0.8rem 1.5rem;
            background: var(--organizer-primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .export-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .export-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            text-decoration: none;
        }
        
        .export-btn-primary {
            background: var(--organizer-primary);
            color: white;
        }
        
        .export-btn-success {
            background: var(--organizer-success);
            color: white;
        }
        
        .export-btn-warning {
            background: var(--organizer-warning);
            color: white;
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }
        
        /* 📊 Report Cards */
        .report-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }
        
        .report-card:hover {
            box-shadow: var(--card-hover-shadow);
            transform: translateY(-5px);
        }
        
        .report-card-header {
            padding: 2rem 2rem 1rem 2rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .report-card-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }
        
        .report-card-body {
            padding: 2rem;
        }
        
        /* 🎯 Summary Stats */
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .summary-stat {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .summary-stat:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin: 0 auto 1rem auto;
        }
        
        .stat-icon.primary { background: var(--organizer-primary); }
        .stat-icon.success { background: var(--organizer-success); }
        .stat-icon.warning { background: var(--organizer-warning); }
        .stat-icon.info { background: var(--organizer-info); }
        .stat-icon.danger { background: var(--organizer-danger); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* 📊 Charts */
        .chart-container {
            position: relative;
            height: 400px;
            margin: 1rem 0;
        }
        
        .mini-chart-container {
            position: relative;
            height: 250px;
            margin: 1rem 0;
        }
        
        /* 📋 Report Tables */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .report-table th,
        .report-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .report-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .report-table tr:hover {
            background: #f8f9fa;
        }
        
        .performance-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .performance-excellent {
            background: var(--organizer-success);
            color: white;
        }
        
        .performance-good {
            background: var(--organizer-info);
            color: white;
        }
        
        .performance-average {
            background: var(--organizer-warning);
            color: white;
        }
        
        .performance-poor {
            background: var(--organizer-danger);
            color: white;
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
            
            .reports-content {
                padding: 1rem;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .report-type-tabs {
                flex-direction: column;
            }
            
            .summary-stats {
                grid-template-columns: 1fr;
            }
            
            .export-buttons {
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
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* 🎯 Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state h4 {
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        
        .empty-state p {
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <!-- 🎪 Organizer Sidebar -->
    <div class="organizer-sidebar" id="organizerSidebar">
        <div class="sidebar-header">
            <h3>🎪 EMS Organizer</h3>
            <p>Event Management Hub</p>
        </div>
        
        <nav class="organizer-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <div class="organizer-nav-item">
                    <a href="dashboard.php" class="organizer-nav-link">
                        <i class="fas fa-tachometer-alt nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="analytics.php" class="organizer-nav-link">
                        <i class="fas fa-chart-line nav-icon"></i>
                        <span class="nav-text">Analytics</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="reports.php" class="organizer-nav-link active">
                        <i class="fas fa-file-chart-line nav-icon"></i>
                        <span class="nav-text">Reports</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Events</div>
                <div class="organizer-nav-item">
                    <a href="events.php" class="organizer-nav-link">
                        <i class="fas fa-calendar-alt nav-icon"></i>
                        <span class="nav-text">My Events</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="create-event.php" class="organizer-nav-link">
                        <i class="fas fa-plus-circle nav-icon"></i>
                        <span class="nav-text">Create Event</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="attendees.php" class="organizer-nav-link">
                        <i class="fas fa-users nav-icon"></i>
                        <span class="nav-text">Attendees</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Financial</div>
                <div class="organizer-nav-item">
                    <a href="revenue.php" class="organizer-nav-link">
                        <i class="fas fa-dollar-sign nav-icon"></i>
                        <span class="nav-text">Revenue</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="payouts.php" class="organizer-nav-link">
                        <i class="fas fa-money-check-alt nav-icon"></i>
                        <span class="nav-text">Payouts</span>
                    </a>
                </div>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">Account</div>
                <div class="organizer-nav-item">
                    <a href="profile.php" class="organizer-nav-link">
                        <i class="fas fa-user-edit nav-icon"></i>
                        <span class="nav-text">Profile</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="../../dashboard/index.php" class="organizer-nav-link">
                        <i class="fas fa-arrow-left nav-icon"></i>
                        <span class="nav-text">Back to User</span>
                    </a>
                </div>
                <div class="organizer-nav-item">
                    <a href="../../auth/logout.php" class="organizer-nav-link">
                        <i class="fas fa-sign-out-alt nav-icon"></i>
                        <span class="nav-text">Logout</span>
                    </a>
                </div>
            </div>
        </nav>
    </div>
    
    <!-- 📱 Main Content Area -->
    <div class="organizer-main" id="organizerMain">
        <!-- 🎯 Organizer Top Bar -->
        <div class="organizer-topbar">
            <div class="organizer-title-section">
                <button class="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="organizer-title">📊 Reports Dashboard</h1>
            </div>
            
            <div class="organizer-user-info">
                <div class="organizer-avatar">
                    <?= strtoupper(substr($currentUser['first_name'], 0, 1) . substr($currentUser['last_name'], 0, 1)) ?>
                </div>
                <div class="organizer-user-details">
                    <h6><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></h6>
                    <small>Event Organizer</small>
                </div>
            </div>
        </div>
        
        <!-- 📊 Reports Content -->
        <div class="reports-content">
            <!-- 🎯 Report Filters -->
            <div class="report-filters fade-in-up">
                <div class="filter-header">
                    <h3 class="filter-title">
                        <i class="fas fa-filter"></i>
                        Report Configuration
                    </h3>
                    <div class="export-buttons">
                        <a href="?<?= http_build_query(array_merge($_GET, ['format' => 'pdf'])) ?>" class="export-btn export-btn-primary">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['format' => 'excel'])) ?>" class="export-btn export-btn-success">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['format' => 'csv'])) ?>" class="export-btn export-btn-warning">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                    </div>
                </div>
                
                <div class="report-type-tabs">
                    <a href="?report_type=overview&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&event_id=<?= $eventId ?>" 
                       class="report-tab <?= $reportType === 'overview' ? 'active' : '' ?>">
                        <i class="fas fa-chart-pie"></i> Overview
                    </a>
                    <a href="?report_type=revenue&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&event_id=<?= $eventId ?>" 
                       class="report-tab <?= $reportType === 'revenue' ? 'active' : '' ?>">
                        <i class="fas fa-dollar-sign"></i> Revenue
                    </a>
                    <a href="?report_type=attendees&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&event_id=<?= $eventId ?>" 
                       class="report-tab <?= $reportType === 'attendees' ? 'active' : '' ?>">
                        <i class="fas fa-users"></i> Attendees
                    </a>
                    <a href="?report_type=performance&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&event_id=<?= $eventId ?>" 
                       class="report-tab <?= $reportType === 'performance' ? 'active' : '' ?>">
                        <i class="fas fa-chart-line"></i> Performance
                    </a>
                </div>
                
                <form method="GET" class="filter-form">
                    <input type="hidden" name="report_type" value="<?= $reportType ?>">
                    
                    <div class="filter-group">
                        <label class="filter-label">Start Date</label>
                        <input type="date" name="start_date" value="<?= $startDate ?>" class="filter-input">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">End Date</label>
                        <input type="date" name="end_date" value="<?= $endDate ?>" class="filter-input">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Event</label>
                        <select name="event_id" class="filter-input">
                            <option value="all" <?= $eventId === 'all' ? 'selected' : '' ?>>All Events</option>
                            <?php foreach ($organizerEvents as $event): ?>
                                <option value="<?= $event['event_id'] ?>" <?= $eventId == $event['event_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($event['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="filter-btn">
                            <i class="fas fa-search"></i> Generate Report
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- 📊 Report Content -->
            <div class="report-card slide-in-right">
                <div class="report-card-header">
                    <h3 class="report-card-title">
                        <i class="fas fa-chart-bar"></i>
                        <?= $reportTitle ?>
                    </h3>
                    <small class="text-muted"><?= $reportDescription ?></small>
                </div>
                <div class="report-card-body">
                    <?php if ($reportType === 'overview'): ?>
                        <!-- Overview Report -->
                        <?php if (isset($reportData['event_summary'])): ?>
                            <div class="summary-stats">
                                <div class="summary-stat">
                                    <div class="stat-icon primary">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="stat-value"><?= $reportData['event_summary']['total_events'] ?></div>
                                    <div class="stat-label">Total Events</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="stat-icon success">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="stat-value"><?= $reportData['event_summary']['approved_events'] ?></div>
                                    <div class="stat-label">Approved Events</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="stat-icon warning">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="stat-value"><?= $reportData['event_summary']['upcoming_events'] ?></div>
                                    <div class="stat-label">Upcoming Events</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="stat-icon info">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-value"><?= number_format($reportData['event_summary']['total_capacity']) ?></div>
                                    <div class="stat-label">Total Capacity</div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($reportData['event_details']) && !empty($reportData['event_details'])): ?>
                            <h4>Event Details</h4>
                            <div class="table-responsive">
                                <table class="report-table">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Date</th>
                                            <th>Venue</th>
                                            <th>Tickets Sold</th>
                                            <th>Revenue</th>
                                            <th>Capacity %</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData['event_details'] as $event): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($event['title']) ?></td>
                                                <td><?= date('M j, Y', strtotime($event['start_datetime'])) ?></td>
                                                <td><?= htmlspecialchars($event['venue']) ?></td>
                                                <td><?= $event['tickets_sold'] ?></td>
                                                <td>K<?= number_format($event['event_revenue']) ?></td>
                                                <td><?= number_format($event['capacity_percentage'], 1) ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-times"></i>
                                <h4>No Events Found</h4>
                                <p>No events found for the selected date range.</p>
                                <a href="create-event.php" class="filter-btn">
                                    <i class="fas fa-plus-circle"></i> Create Your First Event
                                </a>
                            </div>
                        <?php endif; ?>
                        
                    <?php elseif ($reportType === 'revenue'): ?>
                        <!-- Revenue Report -->
                        <?php if (isset($reportData['revenue_summary'])): ?>
                            <div class="summary-stats">
                                <div class="summary-stat">
                                    <div class="stat-icon success">
                                        <i class="fas fa-dollar-sign"></i>
                                    </div>
                                    <div class="stat-value">K<?= number_format($reportData['revenue_summary']['total_revenue']) ?></div>
                                    <div class="stat-label">Total Revenue</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="stat-icon warning">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="stat-value">K<?= number_format($reportData['revenue_summary']['pending_revenue']) ?></div>
                                    <div class="stat-label">Pending Revenue</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="stat-icon info">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <div class="stat-value"><?= $reportData['revenue_summary']['completed_transactions'] ?></div>
                                    <div class="stat-label">Completed Transactions</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="stat-icon primary">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div class="stat-value">K<?= number_format($reportData['revenue_summary']['avg_transaction_value']) ?></div>
                                    <div class="stat-label">Avg Transaction</div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Daily Revenue Chart -->
                        <?php if (isset($reportData['daily_revenue']) && !empty($reportData['daily_revenue'])): ?>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h4>Daily Revenue Trend</h4>
                                    <div class="chart-container">
                                        <canvas id="dailyRevenueChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Event Revenue Ranking -->
                        <?php if (isset($reportData['event_revenue_ranking']) && !empty($reportData['event_revenue_ranking'])): ?>
                            <h4>Event Revenue Ranking</h4>
                            <div class="table-responsive">
                                <table class="report-table">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Date</th>
                                            <th>Tickets Sold</th>
                                            <th>Revenue</th>
                                            <th>Capacity %</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData['event_revenue_ranking'] as $event): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($event['title']) ?></td>
                                                <td><?= date('M j, Y', strtotime($event['start_datetime'])) ?></td>
                                                <td><?= $event['tickets_sold'] ?></td>
                                                <td>K<?= number_format($event['event_revenue']) ?></td>
                                                <td><?= number_format($event['capacity_utilization'], 1) ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Payment Methods -->
                        <?php if (isset($reportData['payment_methods']) && !empty($reportData['payment_methods'])): ?>
                            <div class="row mt-4">
                                <div class="col-lg-6">
                                    <h4>Payment Methods</h4>
                                    <div class="mini-chart-container">
                                        <canvas id="paymentMethodsChart"></canvas>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <h4>Payment Method Details</h4>
                                    <div class="table-responsive">
                                        <table class="report-table">
                                            <thead>
                                                <tr>
                                                    <th>Method</th>
                                                    <th>Transactions</th>
                                                    <th>Revenue</th>
                                                    <th>Avg Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($reportData['payment_methods'] as $method): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($method['payment_method']) ?></td>
                                                        <td><?= $method['method_transactions'] ?></td>
                                                        <td>K<?= number_format($method['method_revenue']) ?></td>
                                                        <td>K<?= number_format($method['avg_method_value']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                    <?php elseif ($reportType === 'attendees'): ?>
                        <!-- Attendees Report -->
                        <?php if (isset($reportData['attendee_summary'])): ?>
                            <div class="summary-stats">
                                <div class="summary-stat">
                                    <div class="stat-icon primary">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-value"><?= $reportData['attendee_summary']['unique_attendees'] ?></div>
                                    <div class="stat-label">Unique Attendees</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="stat-icon success">
                                        <i class="fas fa-ticket-alt"></i>
                                    </div>
                                    <div class="stat-value"><?= $reportData['attendee_summary']['total_registrations'] ?></div>
                                    <div class="stat-label">Total Registrations</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="stat-icon info">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="stat-value"><?= $reportData['attendee_summary']['confirmed_attendees'] ?></div>
                                    <div class="stat-label">Confirmed Attendees</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="stat-icon warning">
                                        <i class="fas fa-birthday-cake"></i>
                                    </div>
                                    <div class="stat-value"><?= number_format($reportData['attendee_summary']['avg_age'], 1) ?></div>
                                    <div class="stat-label">Average Age</div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Registration Timeline -->
                        <?php if (isset($reportData['registration_timeline']) && !empty($reportData['registration_timeline'])): ?>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h4>Registration Timeline</h4>
                                    <div class="chart-container">
                                        <canvas id="registrationTimelineChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Top Attendees -->
                        <?php if (isset($reportData['top_attendees']) && !empty($reportData['top_attendees'])): ?>
                            <div class="row">
                                <div class="col-lg-8">
                                    <h4>Top Attendees</h4>
                                    <div class="table-responsive">
                                        <table class="report-table">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Events Attended</th>
                                                    <th>Total Spent</th>
                                                    <th>Last Registration</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($reportData['top_attendees'] as $attendee): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($attendee['first_name'] . ' ' . $attendee['last_name']) ?></td>
                                                        <td><?= htmlspecialchars($attendee['email']) ?></td>
                                                        <td><?= $attendee['events_attended'] ?></td>
                                                        <td>K<?= number_format($attendee['total_spent']) ?></td>
                                                        <td><?= date('M j, Y', strtotime($attendee['last_registration'])) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <!-- Gender Distribution -->
                                    <?php if (isset($reportData['attendee_summary'])): ?>
                                        <h4>Gender Distribution</h4>
                                        <div class="mini-chart-container">
                                            <canvas id="genderDistributionChart"></canvas>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Event Popularity -->
                        <?php if (isset($reportData['event_popularity']) && !empty($reportData['event_popularity'])): ?>
                            <h4>Event Popularity</h4>
                            <div class="table-responsive">
                                <table class="report-table">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Date</th>
                                            <th>Max Capacity</th>
                                            <th>Registrations</th>
                                            <th>Confirmed</th>
                                            <th>Registration Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData['event_popularity'] as $event): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($event['title']) ?></td>
                                                <td><?= date('M j, Y', strtotime($event['start_datetime'])) ?></td>
                                                <td><?= $event['max_attendees'] ?></td>
                                                <td><?= $event['registrations'] ?></td>
                                                <td><?= $event['confirmed'] ?></td>
                                                <td><?= number_format($event['registration_rate'], 1) ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                    <?php elseif ($reportType === 'performance'): ?>
                        <!-- Performance Report -->
                        <?php if (isset($reportData['performance_metrics'])): ?>
                            <div class="summary-stats">
                                <div class="summary-stat">
                                    <div class="stat-icon primary">
                                        <i class="fas fa-calendar-alt"></i>
                                    </div>
                                    <div class="stat-value"><?= $reportData['performance_metrics']['total_events'] ?></div>
                                    <div class="stat-label">Total Events</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="stat-icon success">
                                        <i class="fas fa-percentage"></i>
                                    </div>
                                    <div class="stat-value"><?= number_format($reportData['performance_metrics']['avg_capacity_utilization'], 1) ?>%</div>
                                    <div class="stat-label">Avg Capacity Utilization</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="stat-icon info">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div class="stat-value"><?= number_format($reportData['performance_metrics']['avg_conversion_rate'], 1) ?>%</div>
                                    <div class="stat-label">Avg Conversion Rate</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="stat-icon warning">
                                        <i class="fas fa-trophy"></i>
                                    </div>
                                    <div class="stat-value"><?= $reportData['performance_metrics']['successful_events'] ?></div>
                                    <div class="stat-label">Successful Events</div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Monthly Performance Trends -->
                        <?php if (isset($reportData['monthly_trends']) && !empty($reportData['monthly_trends'])): ?>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h4>Monthly Performance Trends</h4>
                                    <div class="chart-container">
                                        <canvas id="monthlyTrendsChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Event Performance Details -->
                        <?php if (isset($reportData['event_performance']) && !empty($reportData['event_performance'])): ?>
                            <h4>Event Performance Details</h4>
                            <div class="table-responsive">
                                <table class="report-table">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Date</th>
                                            <th>Confirmed Attendees</th>
                                            <th>Capacity %</th>
                                            <th>Conversion %</th>
                                                                                        <th>Revenue</th>
                                            <th>Performance Rating</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData['event_performance'] as $event): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($event['title']) ?></td>
                                                <td><?= date('M j, Y', strtotime($event['start_datetime'])) ?></td>
                                                <td><?= $event['confirmed_attendees'] ?></td>
                                                <td><?= number_format($event['capacity_utilization'], 1) ?>%</td>
                                                <td><?= number_format($event['conversion_rate'], 1) ?>%</td>
                                                <td>K<?= number_format($event['event_revenue']) ?></td>
                                                <td>
                                                    <span class="performance-badge performance-<?= strtolower($event['performance_rating']) ?>">
                                                        <?= $event['performance_rating'] ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Performance Insights -->
                        <?php if (isset($reportData['performance_insights']) && !empty($reportData['performance_insights'])): ?>
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h4>Performance Insights</h4>
                                    <div class="row">
                                        <?php foreach ($reportData['performance_insights'] as $insight): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="alert alert-<?= $insight['type'] ?> border-0 shadow-sm">
                                                    <h6><i class="fas fa-<?= $insight['icon'] ?>"></i> <?= $insight['title'] ?></h6>
                                                    <p class="mb-0"><?= $insight['message'] ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                    <?php endif; ?>
                    
                    <?php if (empty($reportData) || (isset($reportData['event_details']) && empty($reportData['event_details']))): ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-bar"></i>
                            <h4>No Data Available</h4>
                            <p>No data found for the selected criteria. Try adjusting your filters.</p>
                            <a href="create-event.php" class="filter-btn">
                                <i class="fas fa-plus-circle"></i> Create Your First Event
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 📱 Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 🎯 Reports Dashboard Controller
        class ReportsDashboard {
            constructor() {
                this.init();
            }
            
            init() {
                this.initCharts();
                this.bindEvents();
            }
            
            // 📊 Initialize Charts
            initCharts() {
                // Daily Revenue Chart
                const dailyRevenueCtx = document.getElementById('dailyRevenueChart');
                if (dailyRevenueCtx && <?= json_encode(isset($reportData['daily_revenue'])) ?>) {
                    const dailyRevenueData = <?= json_encode($reportData['daily_revenue'] ?? []) ?>;
                    new Chart(dailyRevenueCtx, {
                        type: 'line',
                        data: {
                            labels: dailyRevenueData.map(item => item.date),
                            datasets: [{
                                label: 'Daily Revenue (K)',
                                data: dailyRevenueData.map(item => item.revenue),
                                borderColor: 'rgb(102, 126, 234)',
                                backgroundColor: 'rgba(102, 126, 234, 0.1)',
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
                                    title: {
                                        display: true,
                                        text: 'Revenue (K)'
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Date'
                                    }
                                }
                            }
                        }
                    });
                }
                
                // Payment Methods Chart
                const paymentMethodsCtx = document.getElementById('paymentMethodsChart');
                if (paymentMethodsCtx && <?= json_encode(isset($reportData['payment_methods'])) ?>) {
                    const paymentMethodsData = <?= json_encode($reportData['payment_methods'] ?? []) ?>;
                    new Chart(paymentMethodsCtx, {
                        type: 'doughnut',
                        data: {
                            labels: paymentMethodsData.map(item => item.payment_method),
                            datasets: [{
                                data: paymentMethodsData.map(item => item.method_revenue),
                                backgroundColor: [
                                    'rgb(102, 126, 234)',
                                    'rgb(76, 175, 80)',
                                    'rgb(255, 152, 0)',
                                    'rgb(33, 150, 243)',
                                    'rgb(244, 67, 54)'
                                ],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
                
                // Registration Timeline Chart
                const registrationTimelineCtx = document.getElementById('registrationTimelineChart');
                if (registrationTimelineCtx && <?= json_encode(isset($reportData['registration_timeline'])) ?>) {
                    const timelineData = <?= json_encode($reportData['registration_timeline'] ?? []) ?>;
                    new Chart(registrationTimelineCtx, {
                        type: 'bar',
                        data: {
                            labels: timelineData.map(item => item.date),
                            datasets: [{
                                label: 'Registrations',
                                data: timelineData.map(item => item.registrations),
                                backgroundColor: 'rgba(102, 126, 234, 0.8)',
                                borderColor: 'rgb(102, 126, 234)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Number of Registrations'
                                    }
                                }
                            }
                        }
                    });
                }
                
                // Gender Distribution Chart
                const genderDistributionCtx = document.getElementById('genderDistributionChart');
                if (genderDistributionCtx && <?= json_encode(isset($reportData['attendee_summary'])) ?>) {
                    const genderData = [
                        { gender: 'Male', count: <?= $reportData['attendee_summary']['male_attendees'] ?? 0 ?> },
                        { gender: 'Female', count: <?= $reportData['attendee_summary']['female_attendees'] ?? 0 ?> },
                        { gender: 'Other', count: <?= $reportData['attendee_summary']['other_attendees'] ?? 0 ?> }
                    ];
                    
                    new Chart(genderDistributionCtx, {
                        type: 'pie',
                        data: {
                            labels: genderData.map(item => item.gender),
                            datasets: [{
                                data: genderData.map(item => item.count),
                                backgroundColor: [
                                    'rgb(102, 126, 234)',
                                    'rgb(244, 67, 54)',
                                    'rgb(255, 152, 0)'
                                ],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                }
                
                // Monthly Trends Chart
                const monthlyTrendsCtx = document.getElementById('monthlyTrendsChart');
                if (monthlyTrendsCtx && <?= json_encode(isset($reportData['monthly_trends'])) ?>) {
                    const trendsData = <?= json_encode($reportData['monthly_trends'] ?? []) ?>;
                    new Chart(monthlyTrendsCtx, {
                        type: 'line',
                        data: {
                            labels: trendsData.map(item => item.month),
                            datasets: [{
                                label: 'Events',
                                data: trendsData.map(item => item.events),
                                borderColor: 'rgb(102, 126, 234)',
                                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                                tension: 0.4,
                                yAxisID: 'y'
                            }, {
                                label: 'Revenue (K)',
                                data: trendsData.map(item => item.revenue),
                                borderColor: 'rgb(76, 175, 80)',
                                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                                tension: 0.4,
                                yAxisID: 'y1'
                            }, {
                                label: 'Attendees',
                                data: trendsData.map(item => item.attendees),
                                borderColor: 'rgb(255, 152, 0)',
                                backgroundColor: 'rgba(255, 152, 0, 0.1)',
                                tension: 0.4,
                                yAxisID: 'y'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            plugins: {
                                legend: {
                                    position: 'top',
                                }
                            },
                            scales: {
                                x: {
                                    display: true,
                                    title: {
                                        display: true,
                                        text: 'Month'
                                    }
                                },
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    title: {
                                        display: true,
                                        text: 'Events / Attendees'
                                    }
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    title: {
                                        display: true,
                                        text: 'Revenue (K)'
                                    },
                                    grid: {
                                        drawOnChartArea: false,
                                    },
                                }
                            }
                        }
                    });
                }
            }
            
            // 🎯 Event Handlers
            bindEvents() {
                // Sidebar toggle
                window.toggleSidebar = () => {
                    const sidebar = document.getElementById('organizerSidebar');
                    const main = document.getElementById('organizerMain');
                    
                    if (window.innerWidth <= 768) {
                        sidebar.classList.toggle('show');
                    } else {
                        sidebar.classList.toggle('collapsed');
                        main.classList.toggle('expanded');
                    }
                };
                
                // Auto-submit form when date changes
                const dateInputs = document.querySelectorAll('input[type="date"]');
                dateInputs.forEach(input => {
                    input.addEventListener('change', () => {
                        // Auto-submit after a short delay
                        setTimeout(() => {
                            document.querySelector('.filter-form').submit();
                        }, 500);
                    });
                });
                
                // Event selector auto-submit
                const eventSelect = document.querySelector('select[name="event_id"]');
                if (eventSelect) {
                    eventSelect.addEventListener('change', () => {
                        document.querySelector('.filter-form').submit();
                    });
                }
                
                // Print functionality
                window.printReport = () => {
                    window.print();
                };
                
                // Export functionality
                const exportButtons = document.querySelectorAll('.export-btn');
                exportButtons.forEach(button => {
                    button.addEventListener('click', (e) => {
                        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
                        button.style.pointerEvents = 'none';
                        
                        // Re-enable after 3 seconds
                        setTimeout(() => {
                            const format = button.href.includes('pdf') ? 'PDF' : 
                                          button.href.includes('excel') ? 'Excel' : 'CSV';
                            button.innerHTML = `<i class="fas fa-file-${format.toLowerCase()}"></i> ${format}`;
                            button.style.pointerEvents = 'auto';
                        }, 3000);
                    });
                });
                
                // Responsive handling
                window.addEventListener('resize', () => {
                    if (window.innerWidth > 768) {
                        document.getElementById('organizerSidebar').classList.remove('show');
                    }
                });
            }
        }
        
        // 🚀 Initialize Reports Dashboard
        document.addEventListener('DOMContentLoaded', () => {
            new ReportsDashboard();
        });
        
        // 🎨 Print Styles
        const printStyles = `
            <style media="print">
                .organizer-sidebar,
                .organizer-topbar,
                .report-filters,
                .export-buttons {
                    display: none !important;
                }
                
                .organizer-main {
                    margin-left: 0 !important;
                }
                
                .reports-content {
                    padding: 0 !important;
                }
                
                .report-card {
                    box-shadow: none !important;
                    border: 1px solid #ddd !important;
                }
                
                .report-table {
                    font-size: 12px;
                }

                
                .summary-stats {
                    display: flex !important;
                    flex-wrap: wrap !important;
                }
                
                .summary-stat {
                    flex: 1 !important;
                    min-width: 150px !important;
                    margin: 0.5rem !important;
                }
                
                .chart-container {
                    height: 300px !important;
                }
                
                body {
                    font-size: 12px !important;
                }
                
                h1, h2, h3, h4 {
                    color: #000 !important;
                }
                
                .performance-badge {
                    border: 1px solid #000 !important;
                    background: #fff !important;
                    color: #000 !important;
                }
            </style>
        `;
        
        document.head.insertAdjacentHTML('beforeend', printStyles);
        
        // 🎯 Additional Helper Functions
        function formatCurrency(amount) {
            return 'K' + new Intl.NumberFormat().format(amount);
        }
        
        function formatPercentage(value) {
            return value.toFixed(1) + '%';
        }
        
        function animateNumbers() {
            const numbers = document.querySelectorAll('.stat-value');
            numbers.forEach(number => {
                const target = parseInt(number.textContent.replace(/[^\d]/g, ''));
                let current = 0;
                const increment = target / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    number.textContent = number.textContent.replace(/\d+/, Math.floor(current));
                }, 20);
            });
        }
        
        // Animate numbers on page load
        setTimeout(animateNumbers, 500);
        
        // 🔄 Auto-refresh functionality
        let autoRefreshInterval;
        
        function startAutoRefresh() {
            autoRefreshInterval = setInterval(() => {
                // Only refresh if user is active
                if (document.visibilityState === 'visible') {
                    location.reload();
                }
            }, 300000); // 5 minutes
        }
        
        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        }
        
        // Start auto-refresh
        startAutoRefresh();
        
        // Stop auto-refresh when page is hidden
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
            }
        });
        
        // 📊 Data Export Functions
        function exportToCSV(data, filename) {
            const csv = data.map(row => Object.values(row).join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        function exportTableToCSV(tableId, filename) {
            const table = document.getElementById(tableId);
            if (!table) return;
            
            const rows = Array.from(table.querySelectorAll('tr'));
            const csv = rows.map(row => {
                const cells = Array.from(row.querySelectorAll('th, td'));
                return cells.map(cell => `"${cell.textContent.trim()}"`).join(',');
            }).join('\n');
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        // 🎨 Theme Toggle (if needed)
        function toggleTheme() {
            document.body.classList.toggle('dark-theme');
            localStorage.setItem('theme', document.body.classList.contains('dark-theme') ? 'dark' : 'light');
        }
        
        // Load saved theme
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-theme');
        }
        
        // 📱 Mobile Optimizations
        if (window.innerWidth <= 768) {
            // Optimize charts for mobile
            Chart.defaults.responsive = true;
            Chart.defaults.maintainAspectRatio = false;
            
            // Reduce animation duration on mobile
            Chart.defaults.animation.duration = 500;
        }
        
        // 🎯 Keyboard Shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + P for print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.printReport();
            }
            
            // Ctrl/Cmd + E for export
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                document.querySelector('.export-btn-primary').click();
            }
            
            // Escape to close modals/overlays
            if (e.key === 'Escape') {
                // Close any open modals or overlays
                const modals = document.querySelectorAll('.modal.show');
                modals.forEach(modal => {
                    const bsModal = bootstrap.Modal.getInstance(modal);
                    if (bsModal) bsModal.hide();
                });
            }
        });
        
        // 🔍 Search functionality for tables
        function addTableSearch() {
            const tables = document.querySelectorAll('.report-table');
            tables.forEach(table => {
                const searchInput = document.createElement('input');
                searchInput.type = 'text';
                searchInput.placeholder = 'Search table...';
                searchInput.className = 'form-control mb-3';
                
                table.parentNode.insertBefore(searchInput, table);
                
                searchInput.addEventListener('input', (e) => {
                    const searchTerm = e.target.value.toLowerCase();
                    const rows = table.querySelectorAll('tbody tr');
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                });
            });
        }
        
        // Add search to tables after page load
        setTimeout(addTableSearch, 1000);
        
        // 📊 Chart Interaction Enhancements
        function enhanceChartInteractions() {
            // Add click handlers to charts for drill-down functionality
            const charts = Chart.instances;
            Object.values(charts).forEach(chart => {
                chart.options.onClick = (event, elements) => {
                    if (elements.length > 0) {
                        const element = elements[0];
                        const datasetIndex = element.datasetIndex;
                        const index = element.index;
                        const label = chart.data.labels[index];
                        const value = chart.data.datasets[datasetIndex].data[index];
                        
                        console.log(`Clicked on ${label}: ${value}`);
                        // Implement drill-down functionality here
                    }
                };
            });
        }
        
        // Enhance charts after they're created
        setTimeout(enhanceChartInteractions, 2000);
        
        // 🎯 Performance Monitoring
        function monitorPerformance() {
            // Monitor page load time
            window.addEventListener('load', () => {
                const loadTime = performance.now();
                console.log(`Page loaded in ${loadTime.toFixed(2)}ms`);
                
                // Send performance data to analytics (if implemented)
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'page_load_time', {
                        value: Math.round(loadTime),
                        event_category: 'Performance'
                    });
                }
            });
            
            // Monitor chart rendering time
            const originalRender = Chart.prototype.render;
            Chart.prototype.render = function() {
                const start = performance.now();
                const result = originalRender.apply(this, arguments);
                const end = performance.now();
                console.log(`Chart rendered in ${(end - start).toFixed(2)}ms`);
                return result;
            };
        }
        
        monitorPerformance();
        
        // 🎨 Accessibility Enhancements
        function enhanceAccessibility() {
            // Add ARIA labels to charts
            const canvases = document.querySelectorAll('canvas');
            canvases.forEach((canvas, index) => {
                canvas.setAttribute('role', 'img');
                canvas.setAttribute('aria-label', `Chart ${index + 1}: Data visualization`);
            });
            
            // Add keyboard navigation to tables
            const tables = document.querySelectorAll('.report-table');
            tables.forEach(table => {
                table.setAttribute('role', 'table');
                const rows = table.querySelectorAll('tr');
                rows.forEach((row, index) => {
                    row.setAttribute('tabindex', index === 0 ? '0' : '-1');
                    row.addEventListener('keydown', (e) => {
                        if (e.key === 'ArrowDown' && row.nextElementSibling) {
                            row.nextElementSibling.focus();
                        } else if (e.key === 'ArrowUp' && row.previousElementSibling) {
                            row.previousElementSibling.focus();
                        }
                    });
                });
            });
        }
        
        enhanceAccessibility();
        
        // 🔄 Error Handling
        window.addEventListener('error', (e) => {
            console.error('JavaScript error:', e.error);
            
            // Show user-friendly error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger alert-dismissible fade show';
            errorDiv.innerHTML = `
                <strong>Oops!</strong> Something went wrong. Please refresh the page or try again later.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('.reports-content');
            if (container) {
                container.insertBefore(errorDiv, container.firstChild);
            }
        });
        
        // 📱 Service Worker Registration (for offline functionality)
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    console.log('Service Worker registered:', registration);
                })
                .catch(error => {
                    console.log('Service Worker registration failed:', error);
                });
        }
        
        // 🎯 Final Initialization
        console.log('📊 Reports Dashboard initialized successfully!');
    </script>
    
    <!-- 🎨 Additional Styles for Dark Theme -->
    <style>
        .dark-theme {
            --content-bg: #1a1a2e;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --border-color: #333;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .dark-theme .report-card,
        .dark-theme .report-filters,
        .dark-theme .summary-stat {
            background: #2d2d44;
            color: var(--text-primary);
        }
        
        .dark-theme .report-table th {
            background: #3d3d5c;
            color: var(--text-primary);
        }
        
        .dark-theme .filter-input {
            background: #3d3d5c;
            color: var(--text-primary);
            border-color: var(--border-color);
        }
        
        .dark-theme .organizer-topbar {
            background: #2d2d44;
            color: var(--text-primary);
        }
        
        @media print {
            .dark-theme * {
                background: white !important;
                color: black !important;
            }
        }
    </style>
</body>
</html>

