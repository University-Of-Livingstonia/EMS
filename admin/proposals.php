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
$status = $_GET['status'] ?? 'pending';

// Build query
$sql = "SELECT e.*, u.username, u.email, u.first_name, u.last_name FROM events e JOIN users u ON e.organizer_id = u.user_id WHERE e.status = ?";
$params = [$status];
$types = 's';

if ($search) {
    $sql .= " AND (e.title LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'sss';
}
$sql .= " ORDER BY e.created_at DESC LIMIT 200";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
    $stmt->execute();
$result = $stmt->get_result();
$proposals = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proposals - EMS Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f8f9fa; }
        .main-content { padding: 2rem; }
        .page-header { background: white; padding: 2rem; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); margin-bottom: 2rem; }
        .table-card { background: white; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); overflow: hidden; }
        .table-header { padding: 1.5rem 2rem; background: #764ba2; color: white; }
        .table-title { font-size: 1.25rem; font-weight: 600; margin: 0; }
        .search-bar { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
        .search-bar input { max-width: 220px; }
        .badge-status { font-size: 0.95em; }
        .badge-status.pending { background: #ffc107; color: #333; }
        .badge-status.approved { background: #4caf50; color: white; }
        .badge-status.rejected { background: #f44336; color: white; }
        .table-responsive { max-height: 600px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="layout-container" style="display:flex;min-height:100vh;">
    <?php include 'includes/navigation.php'; ?>
        <div class="main-content flex-grow-1">
        <div class="page-header">
                <h1 class="page-title"><i class="fas fa-file-alt me-3"></i>Proposals</h1>
                <p class="page-subtitle">Review and manage event proposals</p>
            </div>
            <div class="table-card mb-4">
                <div class="table-header">
                    <h3 class="table-title"><i class="fas fa-file-alt me-2"></i> Pending Proposals</h3>
                </div>
                <div class="p-4">
                    <form class="search-bar mb-3" method="get">
                        <input type="text" name="search" class="form-control" placeholder="Search title, organizer, email..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            </form>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Organizer</th>
                                    <th>Email</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($proposals): ?>
                                    <?php foreach ($proposals as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['event_id']); ?></td>
                                            <td><?php echo htmlspecialchars($p['title']); ?></td>
                                            <td><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($p['email']); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($p['created_at'])); ?></td>
                                            <td><span class="badge badge-status <?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                                            <td>
                                                <a href="event_details.php?id=<?php echo $p['event_id']; ?>" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> View</a>
                                                <button class="btn btn-sm btn-success" disabled><i class="fas fa-check"></i> Approve</button>
                                                <button class="btn btn-sm btn-danger" disabled><i class="fas fa-times"></i> Reject</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
        <?php else: ?>
                                    <tr><td colspan="7" class="text-center text-muted">No proposals found.</td></tr>
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
