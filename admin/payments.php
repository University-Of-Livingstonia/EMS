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
$method = $_GET['method'] ?? '';

// Build query
$sql = "SELECT p.*, u.username, u.email FROM payments p JOIN users u ON p.user_id = u.user_id WHERE 1=1";
$params = [];
$types = '';

if ($search) {
    $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR p.transaction_reference LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}
if ($status) {
    $sql .= " AND p.payment_status = ?";
    $params[] = $status;
    $types .= 's';
}
if ($method) {
    $sql .= " AND p.payment_method = ?";
    $params[] = $method;
    $types .= 's';
}
$sql .= " ORDER BY p.payment_date DESC LIMIT 200";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$payments = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Get unique statuses and methods for filters
$statusOptions = ['pending', 'completed', 'failed', 'refunded'];
$methodOptions = ['mpamba', 'airtel_money', 'credit_card', 'bank_transfer', 'cash'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - EMS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f8f9fa; }
        .main-content { padding: 2rem; }
        .page-header { background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); margin-bottom: 2rem; }
        .table-card { background: white; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); overflow: hidden; }
        .table-header { padding: 1.5rem 2rem; background: #667eea; color: white; }
        .table-title { font-size: 1.25rem; font-weight: 600; margin: 0; }
        .search-bar { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
        .search-bar input, .search-bar select { max-width: 220px; }
        .badge-status { font-size: 0.95em; }
        .badge-status.pending { background: #ffc107; color: #333; }
        .badge-status.completed { background: #4caf50; color: white; }
        .badge-status.failed { background: #f44336; color: white; }
        .badge-status.refunded { background: #2196f3; color: white; }
        .table-responsive { max-height: 600px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="layout-container" style="display:flex;min-height:100vh;">
        <?php include 'includes/navigation.php'; ?>
        <div class="main-content flex-grow-1">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-money-check-alt me-3"></i>Payments</h1>
                <p class="page-subtitle">View and manage all event payments</p>
            </div>
            <div class="table-card mb-4">
                <div class="table-header">
                    <h3 class="table-title"><i class="fas fa-credit-card me-2"></i> Payment Records</h3>
                </div>
                <div class="p-4">
                    <form class="search-bar mb-3" method="get">
                        <input type="text" name="search" class="form-control" placeholder="Search user, email, ref..." value="<?php echo htmlspecialchars($search); ?>">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <?php foreach ($statusOptions as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php if ($status === $opt) echo 'selected'; ?>><?php echo ucfirst($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="method" class="form-select">
                            <option value="">All Methods</option>
                            <?php foreach ($methodOptions as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php if ($method === $opt) echo 'selected'; ?>><?php echo ucwords(str_replace('_', ' ', $opt)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($payments): ?>
                                    <?php foreach ($payments as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['payment_id']); ?></td>
                                            <td><?php echo htmlspecialchars($p['username']); ?></td>
                                            <td><?php echo htmlspecialchars($p['email']); ?></td>
                                            <td>MWK <?php echo number_format($p['amount'], 2); ?></td>
                                            <td><?php echo ucwords(str_replace('_', ' ', $p['payment_method'])); ?></td>
                                            <td><span class="badge badge-status <?php echo $p['payment_status']; ?>"><?php echo ucfirst($p['payment_status']); ?></span></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($p['payment_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($p['transaction_reference']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" class="text-center text-muted">No payments found.</td></tr>
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