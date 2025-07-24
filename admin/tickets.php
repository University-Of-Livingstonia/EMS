<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

// Handle search/filter
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';

// Build query
$sql = "SELECT t.*, e.title AS event_title, u.username, u.email FROM tickets t JOIN events e ON t.event_id = e.event_id JOIN users u ON t.user_id = u.user_id WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $sql .= " AND (e.title LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR t.ticket_type LIKE ? OR t.payment_reference LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sssss';
}
if ($status) {
    $sql .= " AND t.payment_status = ?";
    $params[] = $status;
    $types .= 's';
}
$sql .= " ORDER BY t.created_at DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$tickets = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Get unique statuses for filter
$statusOptions = ['pending', 'completed', 'refunded', 'cancelled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets - EMS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f8f9fa; }
        .main-content { padding: 2rem; }
        .page-header { background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); margin-bottom: 2rem; }
        .table-card { background: white; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); overflow: hidden; }
        .table-header { padding: 1.5rem 2rem; background: #2196f3; color: white; }
        .table-title { font-size: 1.25rem; font-weight: 600; margin: 0; }
        .search-bar { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
        .search-bar input, .search-bar select { max-width: 220px; }
        .badge-status { font-size: 0.95em; }
        .badge-status.pending { background: #ffc107; color: #333; }
        .badge-status.completed { background: #4caf50; color: white; }
        .badge-status.refunded { background: #2196f3; color: white; }
        .badge-status.cancelled { background: #f44336; color: white; }
        .table-responsive { max-height: 600px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="layout-container" style="display:flex;min-height:100vh;">
        <?php include 'includes/navigation.php'; ?>
        <div class="main-content flex-grow-1">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-ticket-alt me-3"></i>Tickets</h1>
                <p class="page-subtitle">View and manage all event tickets</p>
            </div>
            <div class="table-card mb-4">
                <div class="table-header">
                    <h3 class="table-title"><i class="fas fa-ticket-alt me-2"></i> Ticket Records</h3>
                </div>
                <div class="p-4">
                    <form class="search-bar mb-3" method="get">
                        <input type="text" name="search" class="form-control" placeholder="Search event, user, type, ref..." value="<?php echo htmlspecialchars($search); ?>">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <?php foreach ($statusOptions as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php if ($status === $opt) echo 'selected'; ?>><?php echo ucfirst($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Event</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Reference</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($tickets): ?>
                                    <?php foreach ($tickets as $t): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($t['ticket_id']); ?></td>
                                            <td><?php echo htmlspecialchars($t['event_title']); ?></td>
                                            <td><?php echo htmlspecialchars($t['username']); ?></td>
                                            <td><?php echo htmlspecialchars($t['email']); ?></td>
                                            <td><?php echo htmlspecialchars($t['ticket_type']); ?></td>
                                            <td>MWK <?php echo number_format($t['price'], 2); ?></td>
                                            <td><span class="badge badge-status <?php echo $t['payment_status']; ?>"><?php echo ucfirst($t['payment_status']); ?></span></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($t['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($t['payment_reference']); ?></td>
                                            <td><a href="#" class="btn btn-sm btn-info disabled"><i class="fas fa-eye"></i> View</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="10" class="text-center text-muted">No tickets found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 