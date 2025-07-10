<?php
/**
 * 📋 Admin Event Proposals - EMS
 * Ekwendeni Mighty Campus Event Management System
 * Event Proposals Management System! 📝
 */

require_once '../includes/functions.php';

// Get database connection
$conn = require_once '../config/database.php';

// Initialize session manager
require_once '../includes/session.php';
$sessionManager = new SessionManager($conn);

// Require admin login
$sessionManager->requireLogin();
$currentUser = $sessionManager->getCurrentUser();

// Check if user is admin
if ($currentUser['role'] !== 'admin') {
    header('Location: ../dashboard/index.php');
    exit;
}

// Handle proposal actions
$message = '';
$messageType = '';

if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'approve_proposal':
                    $proposalId = (int)$_POST['proposal_id'];
                    
                    // Update proposal status
                    $stmt = $conn->prepare("UPDATE event_proposals SET status = 'approved', approved_at = NOW(), approved_by = ? WHERE proposal_id = ?");
                    $stmt->bind_param("ii", $currentUser['user_id'], $proposalId);
                    $stmt->execute();
                    
                    // Create event from proposal
                    $stmt = $conn->prepare("
                        INSERT INTO events (title, description, start_datetime, end_datetime, location, category, 
                                          max_attendees, ticket_price, organizer_id, status, created_at)
                        SELECT title, description, start_datetime, end_datetime, location, category,
                               max_attendees, ticket_price, organizer_id, 'approved', NOW()
                        FROM event_proposals WHERE proposal_id = ?
                    ");
                    $stmt->bind_param("i", $proposalId);
                    $stmt->execute();
                    
                    $message = 'Proposal approved and event created successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'reject_proposal':
                    $proposalId = (int)$_POST['proposal_id'];
                    $reason = trim($_POST['rejection_reason']);
                    
                    $stmt = $conn->prepare("UPDATE event_proposals SET status = 'rejected', rejection_reason = ?, rejected_at = NOW(), rejected_by = ? WHERE proposal_id = ?");
                    $stmt->bind_param("sii", $reason, $currentUser['user_id'], $proposalId);
                    $stmt->execute();
                    
                    $message = 'Proposal rejected successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'request_changes':
                    $proposalId = (int)$_POST['proposal_id'];
                    $changes = trim($_POST['requested_changes']);
                    
                    $stmt = $conn->prepare("UPDATE event_proposals SET status = 'changes_requested', requested_changes = ?, changes_requested_at = NOW(), changes_requested_by = ? WHERE proposal_id = ?");
                    $stmt->bind_param("sii", $changes, $currentUser['user_id'], $proposalId);
                    $stmt->execute();
                    
                    $message = 'Changes requested successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'delete_proposal':
                    $proposalId = (int)$_POST['proposal_id'];
                    
                                       $stmt = $conn->prepare("DELETE FROM event_proposals WHERE proposal_id = ?");
                    $stmt->bind_param("i", $proposalId);
                    $stmt->execute();
                    
                    $message = 'Proposal deleted successfully!';
                    $messageType = 'success';
                    break;
            }
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$categoryFilter = $_GET['category'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Build query
$whereConditions = [];
$params = [];
$paramTypes = "";

if ($statusFilter !== 'all') {
    $whereConditions[] = "ep.status = ?";
    $params[] = $statusFilter;
    $paramTypes .= "s";
}

if ($categoryFilter !== 'all') {
    $whereConditions[] = "ep.category = ?";
    $params[] = $categoryFilter;
    $paramTypes .= "s";
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(ep.title LIKE ? OR ep.description LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $paramTypes .= "ss";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get proposals
$proposals = [];
$totalProposals = 0;
try {
    // Count total
    $countQuery = "SELECT COUNT(*) as total FROM event_proposals ep $whereClause";
    if (!empty($params)) {
        $stmt = $conn->prepare($countQuery);
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $totalProposals = $stmt->get_result()->fetch_assoc()['total'];
    } else {
        $totalProposals = $conn->query($countQuery)->fetch_assoc()['total'];
    }
    
    // Get proposals
    $query = "
        SELECT ep.*, u.first_name, u.last_name, u.email as organizer_email
        FROM event_proposals ep
        LEFT JOIN users u ON ep.organizer_id = u.user_id
        $whereClause
        ORDER BY ep.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $allParams = array_merge($params, [$limit, $offset]);
    $allParamTypes = $paramTypes . "ii";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($allParamTypes, ...$allParams);
    $stmt->execute();
    $proposals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    error_log("Proposals query error: " . $e->getMessage());
}

$totalPages = ceil($totalProposals / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Proposals - Admin | EMS</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --admin-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --admin-success: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --admin-warning: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            --admin-danger: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            --admin-info: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            --content-bg: #f8f9fa;
            --card-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --border-color: #e9ecef;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--content-bg);
            margin-left: 300px;
        }
        
        .main-content {
            padding: 2rem;
            min-height: 100vh;
        }
        
        .page-header {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--admin-primary);
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: var(--admin-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
        }
        
        .proposal-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .proposal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        
        .proposal-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
        }
        
        .proposal-card.pending::before { background: var(--admin-warning); }
        .proposal-card.approved::before { background: var(--admin-success); }
        .proposal-card.rejected::before { background: var(--admin-danger); }
        .proposal-card.changes_requested::before { background: var(--admin-info); }
        
        .proposal-header {
            padding: 2rem 2rem 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .proposal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        
        .proposal-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #ff9800;
            border: 2px solid #ff9800;
        }
        
        .status-approved {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border: 2px solid #4CAF50;
        }
        
        .status-rejected {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border: 2px solid #f44336;
        }
        
        .status-changes_requested {
            background: rgba(33, 150, 243, 0.1);
            color: #2196F3;
            border: 2px solid #2196F3;
        }
        
        .proposal-body {
            padding: 0 2rem 2rem 2rem;
        }
        
        .proposal-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .meta-item i {
            color: #667eea;
            width: 16px;
        }
        
        .proposal-description {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }
        
        .proposal-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .btn-approve {
            background: var(--admin-success);
            color: white;
        }
        
        .btn-reject {
            background: var(--admin-danger);
            color: white;
        }
        
        .btn-changes {
            background: var(--admin-info);
            color: white;
        }
        
        .btn-delete {
            background: #6c757d;
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .filters-section {
            background: white;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .filters-row {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        
        .filter-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-select,
        .filter-input {
            padding: 0.5rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            min-width: 150px;
        }
        
        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .search-input {
            min-width: 250px;
        }
        
        .filter-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1.2rem;
        }
        
        .btn-primary {
            background: var(--admin-primary);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .summary-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .summary-card.total::before { background: var(--admin-primary); }
        .summary-card.pending::before { background: var(--admin-warning); }
        .summary-card.approved::before { background: var(--admin-success); }
        .summary-card.rejected::before { background: var(--admin-danger); }
        
        .summary-number {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .summary-label {
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: var(--card-shadow);
        }
        
        .modal-header {
            background: var(--admin-primary);
            color: white;
            border-radius: 20px 20px 0 0;
            border-bottom: none;
        }
        
        .modal-title {
            font-weight: 700;
        }
        
        .btn-close {
            filter: invert(1);
        }
        
        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 0.7rem;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .form-label {
                       font-weight: 600;
            color: var(--text-primary);
        }
        
        .alert {
            border-radius: 15px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
        }
        
        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
            border-left: 4px solid #4CAF50;
        }
        
        .alert-danger {
            background: rgba(244, 67, 54, 0.1);
            color: #f44336;
            border-left: 4px solid #f44336;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 3rem;
        }
        
        .pagination-btn {
            padding: 0.7rem 1.2rem;
            border: 2px solid var(--border-color);
            background: white;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .pagination-btn:hover,
        .pagination-btn.active {
            background: var(--admin-primary);
            color: white;
            border-color: transparent;
            text-decoration: none;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            body {
                margin-left: 0;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .filter-select,
            .filter-input {
                min-width: auto;
                width: 100%;
            }
            
            .proposal-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">📋 Event Proposals</h1>
            <p class="text-muted">Review and manage event proposals from organizers</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="stats-summary">
            <div class="summary-card total">
                <div class="summary-number"><?= number_format($totalProposals) ?></div>
                <div class="summary-label">Total Proposals</div>
            </div>
            <div class="summary-card pending">
                <div class="summary-number">
                    <?= count(array_filter($proposals, fn($p) => $p['status'] === 'pending')) ?>
                </div>
                <div class="summary-label">Pending</div>
            </div>
            <div class="summary-card approved">
                <div class="summary-number">
                    <?= count(array_filter($proposals, fn($p) => $p['status'] === 'approved')) ?>
                </div>
                <div class="summary-label">Approved</div>
            </div>
            <div class="summary-card rejected">
                <div class="summary-number">
                    <?= count(array_filter($proposals, fn($p) => $p['status'] === 'rejected')) ?>
                </div>
                <div class="summary-label">Rejected</div>
            </div>
        </div>
        
        <div class="filters-section">
            <form method="GET" class="filters-row">
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <select name="status" class="filter-select">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="changes_requested" <?= $statusFilter === 'changes_requested' ? 'selected' : '' ?>>Changes Requested</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Category</label>
                    <select name="category" class="filter-select">
                        <option value="all" <?= $categoryFilter === 'all' ? 'selected' : '' ?>>All Categories</option>
                        <option value="academic" <?= $categoryFilter === 'academic' ? 'selected' : '' ?>>Academic</option>
                        <option value="cultural" <?= $categoryFilter === 'cultural' ? 'selected' : '' ?>>Cultural</option>
                        <option value="sports" <?= $categoryFilter === 'sports' ? 'selected' : '' ?>>Sports</option>
                        <option value="social" <?= $categoryFilter === 'social' ? 'selected' : '' ?>>Social</option>
                        <option value="workshop" <?= $categoryFilter === 'workshop' ? 'selected' : '' ?>>Workshop</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Search</label>
                    <input type="text" name="search" class="filter-input search-input" 
                           placeholder="Search proposals..." value="<?= htmlspecialchars($searchQuery) ?>">
                </div>
                
                <button type="submit" class="filter-btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                
                <a href="proposals.php" class="filter-btn btn-secondary">
                    <i class="fas fa-refresh"></i> Reset
                </a>
            </form>
        </div>
        
        <?php if (empty($proposals)): ?>
            <div class="text-center py-5">
                <i class="fas fa-clipboard-list fa-5x text-muted mb-4"></i>
                <h3>No Proposals Found</h3>
                <p class="text-muted">No proposals match your current filters</p>
            </div>
        <?php else: ?>
            <?php foreach ($proposals as $proposal): ?>
                <div class="proposal-card <?= $proposal['status'] ?>">
                    <div class="proposal-header">
                        <h3 class="proposal-title"><?= htmlspecialchars($proposal['title']) ?></h3>
                        <span class="proposal-status status-<?= $proposal['status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $proposal['status'])) ?>
                        </span>
                    </div>
                    
                    <div class="proposal-body">
                        <div class="proposal-meta">
                            <div class="meta-item">
                                <i class="fas fa-user"></i>
                                <span><?= htmlspecialchars($proposal['first_name'] . ' ' . $proposal['last_name']) ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                <span><?= date('M j, Y', strtotime($proposal['start_datetime'])) ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-clock"></i>
                                <span><?= date('g:i A', strtotime($proposal['start_datetime'])) ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?= htmlspecialchars($proposal['location']) ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-tag"></i>
                                <span><?= htmlspecialchars(ucfirst($proposal['category'])) ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-users"></i>
                                <span><?= $proposal['max_attendees'] ?> max attendees</span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-dollar-sign"></i>
                                <span>K<?= number_format($proposal['ticket_price'], 2) ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-clock"></i>
                                <span>Submitted <?= date('M j, Y', strtotime($proposal['created_at'])) ?></span>
                            </div>
                        </div>
                        
                        <div class="proposal-description">
                            <?= nl2br(htmlspecialchars($proposal['description'])) ?>
                        </div>
                        
                        <?php if ($proposal['rejection_reason']): ?>
                            <div class="alert alert-danger">
                                <strong>Rejection Reason:</strong><br>
                                <?= nl2br(htmlspecialchars($proposal['rejection_reason'])) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($proposal['requested_changes']): ?>
                            <div class="alert alert-info">
                                <strong>Requested Changes:</strong><br>
                                <?= nl2br(htmlspecialchars($proposal['requested_changes'])) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="proposal-actions">
                            <?php if ($proposal['status'] === 'pending'): ?>
                                <button onclick="approveProposal(<?= $proposal['proposal_id'] ?>)" class="action-btn btn-approve">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button onclick="rejectProposal(<?= $proposal['proposal_id'] ?>)" class="action-btn btn-reject">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                                <button onclick="requestChanges(<?= $proposal['proposal_id'] ?>)" class="action-btn btn-changes">
                                    <i class="fas fa-edit"></i> Request Changes
                                </button>
                            <?php endif; ?>
                            
                            <button onclick="deleteProposal(<?= $proposal['proposal_id'] ?>, '<?= htmlspecialchars($proposal['title']) ?>')" 
                                    class="action-btn btn-delete">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                       class="pagination-btn">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                       class="pagination-btn <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                       class="pagination-btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Reject Proposal Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle"></i> Reject Proposal
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="rejectForm">
                    <input type="hidden" name="action" value="reject_proposal">
                    <input type="hidden" name="proposal_id" id="rejectProposalId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Rejection Reason *</label>
                            <textarea name="rejection_reason" class="form-control" rows="4" 
                                      placeholder="Please provide a reason for rejecting this proposal..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> Reject Proposal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Request Changes Modal -->
    <div class="modal fade" id="changesModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i> Request Changes
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="changesForm">
                    <input type="hidden" name="action" value="request_changes">
                    <input type="hidden" name="proposal_id" id="changesProposalId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Requested Changes *</label>
                            <textarea name="requested_changes" class="form-control" rows="4" 
                                      placeholder="Please specify what changes are needed..." required></textarea>
                        </div>
                    </div>
                    <?php
// Get current page for active navigation
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <h3>👑 EMS Admin</h3>
        <p>System Control Center</p>
    </div>
    
    <nav class="admin-nav">
        <div class="nav-section">
            <div class="nav-section-title">Dashboard</div>
            <div class="admin-nav-item">
                <a href="dashboard.php" class="admin-nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-tachometer-alt"></i>
                    <span class="nav-text">Overview</span>
                </a>
            </div>
            <div class="admin-nav-item">
                <a href="analytics.php" class="admin-nav-link <?= $currentPage === 'analytics.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-chart-line"></i>
                    <span class="nav-text">Analytics</span>
                </a>
            </div>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Events</div>
            <div class="admin-nav-item">
                <a href="events.php" class="admin-nav-link <?= $currentPage === 'events.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-calendar-alt"></i>
                    <span class="nav-text">All Events</span>
                </a>
            </div>
            <div class="admin-nav-item">
                <a href="proposals.php" class="admin-nav-link <?= $currentPage === 'proposals.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-clipboard-list"></i>
                    <span class="nav-text">Proposals</span>
                    <?php
                    // Get pending proposals count
                    try {
                        $stmt = $conn->query("SELECT COUNT(*) as count FROM event_proposals WHERE status = 'pending'");
                        $pendingCount = $stmt->fetch_assoc()['count'];
                        if ($pendingCount > 0):
                    ?>
                        <span class="nav-badge"><?= $pendingCount ?></span>
                    <?php endif; } catch (Exception $e) { } ?>
                </a>
            </div>
            <div class="admin-nav-item">
                <a href="featured.php" class="admin-nav-link <?= $currentPage === 'featured.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-star"></i>
                    <span class="nav-text">Featured Events</span>
                </a>
            </div>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Users</div>
            <div class="admin-nav-item">
                <a href="users.php" class="admin-nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-users"></i>
                    <span class="nav-text">All Users</span>
                </a>
            </div>
            <div class="admin-nav-item">
                <a href="organizers.php" class="admin-nav-link <?= $currentPage === 'organizers.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-user-tie"></i>
                    <span class="nav-text">Organizers</span>
                </a>
            </div>
            <div class="admin-nav-item">
                <a href="permissions.php" class="admin-nav-link <?= $currentPage === 'permissions.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-shield-alt"></i>
                    <span class="nav-text">Permissions</span>
                </a>
            </div>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Finance</div>
            <div class="admin-nav-item">
                <a href="revenue.php" class="admin-nav-link <?= $currentPage === 'revenue.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-dollar-sign"></i>
                    <span class="nav-text">Revenue</span>
                </a>
            </div>
            <div class="admin-nav-item">
                <a href="tickets.php" class="admin-nav-link <?= $currentPage === 'tickets.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-ticket-alt"></i>
                    <span class="nav-text">Tickets</span>
                </a>
            </div>
            <div class="admin-nav-item">
                <a href="payments.php" class="admin-nav-link <?= $currentPage === 'payments.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-credit-card"></i>
                    <span class="nav-text">Payments</span>
                </a>
            </div>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">System</div>
            <div class="admin-nav-item">
                <a href="reports.php" class="admin-nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-file-alt"></i>
                    <span class="nav-text">Reports</span>
                </a>
            </div>
            <div class="admin-nav-item">
                <a href="settings.php" class="admin-nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-cog"></i>
                    <span class="nav-text">Settings</span>
                </a>
            </div>
            <div class="admin-nav-item">
                <a href="logs.php" class="admin-nav-link <?= $currentPage === 'logs.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-list-alt"></i>
                    <span class="nav-text">System Logs</span>
                </a>
            </div>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Account</div>
            <div class="admin-nav-item">
                <a href="profile.php" class="admin-nav-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-user"></i>
                    <span class="nav-text">Profile</span>
                </a>
            </div>
            <div class="admin-nav-item">
                <a href="../auth/logout.php" class="admin-nav-link">
                    <i class="nav-icon fas fa-sign-out-alt"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </div>
    </nav>
</div>

<style>
/* Admin Sidebar Styles */
.admin-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 300px;
    background: #1a1a2e;
    color: white;
    transition: all 0.3s ease;
    z-index: 1000;
    overflow-y: auto;
    box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
}

.admin-sidebar.collapsed {
    width: 80px;
}

.sidebar-header {
    padding: 2rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

.admin-nav {
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

.admin-nav-item {
    margin: 0.3rem 0;
}

.admin-nav-link {
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

.admin-nav-link:hover,
.admin-nav-link.active {
    background: #16213e;
    color: white;
    transform: translateX(10px);
}

.admin-nav-link::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.admin-nav-link:hover::before,
.admin-nav-link.active::before {
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
    background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

@media (max-width: 768px) {
    .admin-sidebar {
        transform: translateX(-100%);
    }
    
    .admin-sidebar.show {
        transform: translateX(0);
    }
}
</style>

<script>
// Mobile sidebar toggle
function toggleSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    sidebar.classList.toggle('show');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('adminSidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    
    if (window.innerWidth <= 768 && 
        !sidebar.contains(e.target) && 
        !toggleBtn?.contains(e.target)) {
        sidebar.classList.remove('show');
    }
});
</script>


