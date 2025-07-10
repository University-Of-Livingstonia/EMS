<?php
/**
 * 🧭 Organizer Navigation - EMS
 * Shared navigation component for organizer pages
 */

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);

// Get notification count (you can implement this based on your needs)
$notificationCount = 0;
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->bind_param("i", $currentUser['user_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $notificationCount = $result['count'] ?? 0;
} catch (Exception $e) {
    // Silently handle error
}

// Get pending events count
$pendingEventsCount = 0;
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM events 
        WHERE organizer_id = ? AND status = 'pending'
    ");
    $stmt->bind_param("i", $currentUser['user_id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $pendingEventsCount = $result['count'] ?? 0;
} catch (Exception $e) {
    // Silently handle error
}
?>

<style>
/* Navigation Styles */
.organizer-sidebar {
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

.organizer-sidebar.collapsed {
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
    background: #16213e;
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
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
    background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Mobile Toggle */
.mobile-nav-toggle {
    display: none;
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 1001;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    font-size: 1.2rem;
    cursor: pointer;
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
}

@media (max-width: 768px) {
    .organizer-sidebar {
        transform: translateX(-100%);
    }
    
    .organizer-sidebar.show {
        transform: translateX(0);
    }
    
    .mobile-nav-toggle {
        display: block;
    }
}
</style>

<!-- Mobile Navigation Toggle -->
<button class="mobile-nav-toggle" onclick="toggleMobileNav()">
    <i class="fas fa-bars"></i>
</button>

<!-- Organizer Sidebar -->
<div class="organizer-sidebar" id="organizerSidebar">
    <div class="sidebar-header">
        <h3>🎪 EMS</h3>
        <p>Event Organizer</p>
    </div>
    
    <nav class="organizer-nav">
        <div class="nav-section">
            <div class="nav-section-title">Dashboard</div>
            <div class="organizer-nav-item">
                <a href="dashboard.php" class="organizer-nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-tachometer-alt"></i>
                    <span class="nav-text">Overview</span>
                </a>
            </div>
            <div class="organizer-nav-item">
                <a href="analytics.php" class="organizer-nav-link <?= $currentPage === 'analytics.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-chart-line"></i>
                    <span class="nav-text">Analytics</span>
                </a>
            </div>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Events</div>
            <div class="organizer-nav-item">
                <a href="events.php" class="organizer-nav-link <?= $currentPage === 'events.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-calendar-alt"></i>
                    <span class="nav-text">My Events</span>
                </a>
            </div>
            <div class="organizer-nav-item">
                <a href="create_event.php" class="organizer-nav-link <?= $currentPage === 'create_event.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-plus-circle"></i>
                    <span class="nav-text">Create Event</span>
                </a>
            </div>
            <?php if ($pendingEventsCount > 0): ?>
            <div class="organizer-nav-item">
                <a href="pending_events.php" class="organizer-nav-link <?= $currentPage === 'pending_events.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-clock"></i>
                    <span class="nav-text">Pending Approval</span>
                    <span class="nav-badge"><?= $pendingEventsCount ?></span>
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Management</div>
            <div class="organizer-nav-item">
                <a href="attendees.php" class="organizer-nav-link <?= $currentPage === 'attendees.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-users"></i>
                    <span class="nav-text">Attendees</span>
                </a>
            </div>
            <div class="organizer-nav-item">
                <a href="tickets.php" class="organizer-nav-link <?= $currentPage === 'tickets.php' ? 'active' : '' ?>">
                                        <i class="nav-icon fas fa-ticket-alt"></i>
                    <span class="nav-text">Tickets</span>
                </a>
            </div>
            <div class="organizer-nav-item">
                <a href="revenue.php" class="organizer-nav-link <?= $currentPage === 'revenue.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-dollar-sign"></i>
                    <span class="nav-text">Revenue</span>
                </a>
            </div>
            <div class="organizer-nav-item">
                <a href="communications.php" class="organizer-nav-link <?= $currentPage === 'communications.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-envelope"></i>
                    <span class="nav-text">Communications</span>
                </a>
            </div>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Tools</div>
            <div class="organizer-nav-item">
                <a href="reports.php" class="organizer-nav-link <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-file-alt"></i>
                    <span class="nav-text">Reports</span>
                </a>
            </div>
            <div class="organizer-nav-item">
                <a href="notifications.php" class="organizer-nav-link <?= $currentPage === 'notifications.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-bell"></i>
                    <span class="nav-text">Notifications</span>
                    <?php if ($notificationCount > 0): ?>
                        <span class="nav-badge"><?= $notificationCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <div class="organizer-nav-item">
                <a href="settings.php" class="organizer-nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-cog"></i>
                    <span class="nav-text">Settings</span>
                </a>
            </div>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Account</div>
            <div class="organizer-nav-item">
                <a href="profile.php" class="organizer-nav-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                    <i class="nav-icon fas fa-user"></i>
                    <span class="nav-text">Profile</span>
                </a>
            </div>
            <div class="organizer-nav-item">
                <a href="../../dashboard/index.php" class="organizer-nav-link">
                    <i class="nav-icon fas fa-home"></i>
                    <span class="nav-text">Main Dashboard</span>
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

<script>
// Mobile navigation toggle
function toggleMobileNav() {
    const sidebar = document.getElementById('organizerSidebar');
    sidebar.classList.toggle('show');
}

// Close mobile nav when clicking outside
document.addEventListener('click', function(e) {
    const sidebar = document.getElementById('organizerSidebar');
    const toggle = document.querySelector('.mobile-nav-toggle');
    
    if (window.innerWidth <= 768 && 
        !sidebar.contains(e.target) && 
        !toggle.contains(e.target) && 
        sidebar.classList.contains('show')) {
        sidebar.classList.remove('show');
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('organizerSidebar');
    if (window.innerWidth > 768) {
        sidebar.classList.remove('show');
    }
});
</script>
