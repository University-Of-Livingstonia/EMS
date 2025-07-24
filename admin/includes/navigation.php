<?php
// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Get current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $_SESSION['role'] ?? 'user';
$user_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
$user_avatar = $_SESSION['profile_image'] ?? null;
?>

<!-- 🎯 Navigation Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-calendar-alt"></i>
            <span>EMS</span>
        </div>
        <button class="sidebar-close d-md-none" id="sidebarClose">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="sidebar-content">
        <!-- User Profile Section -->
        <div class="user-profile">
            <div class="user-avatar">
                <?php if ($user_avatar && file_exists("../uploads/" . $user_avatar)): ?>
                    <img src="../uploads/<?php echo htmlspecialchars($user_avatar); ?>" alt="Profile">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <h4><?php echo htmlspecialchars($user_name); ?></h4>
                <span class="user-role"><?php echo ucfirst($user_role); ?></span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="nav-menu">
            <?php if ($user_role === 'admin'): ?>
                <!-- Admin Navigation -->
                <div class="nav-section">
                    <h5 class="nav-section-title">Dashboard</h5>
                    <a href="dashboard.php" class="nav-item <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Overview</span>
                    </a>
                    <a href="analytics.php" class="nav-item <?php echo $current_page === 'analytics.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i>
                        <span>Analytics</span>
                    </a>
                </div>

                <div class="nav-section">
                    <h5 class="nav-section-title">Management</h5>
                    <a href="events.php" class="nav-item <?php echo $current_page === 'events.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-check"></i>
                        <span>Events</span>
                    </a>
                    <a href="proposals.php" class="nav-item <?php echo $current_page === 'proposals.php' ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span>Proposals</span>
                        <?php
                        // Get pending proposals count
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE status = 'pending'");
                        $stmt->execute();
                        $pending_count = $stmt->get_result()->fetch_assoc()['count'];
                        if ($pending_count > 0):
                        ?>
                            <span class="nav-badge"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="users.php" class="nav-item <?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                    <a href="tickets.php" class="nav-item <?php echo $current_page === 'tickets.php' ? 'active' : ''; ?>">
                        <i class="fas fa-ticket-alt"></i>
                        <span>Tickets</span>
                    </a>
                </div>

                <div class="nav-section">
                    <h5 class="nav-section-title">Reports</h5>
                    <a href="reports.php" class="nav-item <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>Reports</span>
                    </a>
                    <a href="revenue.php" class="nav-item <?php echo $current_page === 'revenue.php' ? 'active' : ''; ?>">
                        <i class="fas fa-dollar-sign"></i>
                        <span>Revenue</span>
                    </a>
                </div>

                <div class="nav-section">
                    <h5 class="nav-section-title">System</h5>
                    <a href="settings.php" class="nav-item <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="notifications.php" class="nav-item <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                    </a>
                    <a href="backup.php" class="nav-item <?php echo $current_page === 'backup.php' ? 'active' : ''; ?>">
                        <i class="fas fa-database"></i>
                        <span>Backup</span>
                    </a>
                    <a href="payment.php" class="nav-item <?php echo $current_page === 'payment.php' ? 'active' : ''; ?>">
                        <i class="fas fa-money-check-alt"></i>
                        <span>Payments</span>
                    </a>
                </div>

            <?php elseif ($user_role === 'organizer'): ?>
                <!-- Organizer Navigation -->
                <div class="nav-section">
                    <h5 class="nav-section-title">Dashboard</h5>
                    <a href="../dashboard/index.php" class="nav-item <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </div>

                <div class="nav-section">
                    <h5 class="nav-section-title">Events</h5>
                    <a href="../events/create.php" class="nav-item <?php echo $current_page === 'create.php' ? 'active' : ''; ?>">
                        <i class="fas fa-plus-circle"></i>
                        <span>Create Event</span>
                    </a>
                    <a href="../events/manage.php" class="nav-item <?php echo $current_page === 'manage.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        <span>My Events</span>
                    </a>
                    <a href="../events/analytics.php" class="nav-item <?php echo $current_page === 'analytics.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i>
                        <span>Analytics</span>
                    </a>
                </div>

                <div class="nav-section">
                    <h5 class="nav-section-title">Management</h5>
                    <a href="../tickets/scan.php" class="nav-item <?php echo $current_page === 'scan.php' ? 'active' : ''; ?>">
                        <i class="fas fa-qrcode"></i>
                        <span>Scan Tickets</span>
                    </a>
                    <a href="../attendees/list.php" class="nav-item <?php echo $current_page === 'list.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Attendees</span>
                    </a>
                </div>

            <?php else: ?>
                <!-- User Navigation -->
                <div class="nav-section">
                    <h5 class="nav-section-title">Dashboard</h5>
                    <a href="../dashboard/index.php" class="nav-item <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </div>

                <div class="nav-section">
                    <h5 class="nav-section-title">Events</h5>
                    <a href="../events/browse.php" class="nav-item <?php echo $current_page === 'browse.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Browse Events</span>
                    </a>
                    <a href="../admin/events.php" class="nav-item <?php echo $current_page === 'registered.php' ? 'active' : ''; ?>">
                        <i class="fas fa-ticket-alt"></i>
                        <span>My Events</span>
                    </a>
                </div>

                <div class="nav-section">
                    <h5 class="nav-section-title">Account</h5>
                    <a href="../admin/my-tickets.php" class="nav-item <?php echo $current_page === 'my-tickets.php' ? 'active' : ''; ?>">
                        <i class="fas fa-ticket-alt"></i>
                        <span>My Tickets</span>
                    </a>
                    <a href="../admin/notifications.php" class="nav-item <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                        <?php
                        // Get unread notifications count
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
                        $stmt->bind_param("i", $_SESSION['user_id']);
                        $stmt->execute();
                        $unread_count = $stmt->get_result()->fetch_assoc()['count'];
                        if ($unread_count > 0):
                        ?>
                            <span class="nav-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endif; ?>

            <!-- Common Navigation Items -->
            <div class="nav-section">
                <h5 class="nav-section-title">Account</h5>
                <a href="../profile/edit.php" class="nav-item <?php echo $current_page === 'edit.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-edit"></i>
                    <span>Profile</span>
                </a>
                <a href="../admin/settings.php" class="nav-item <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>

            <!-- Quick Actions -->
            <div class="nav-section">
                <h5 class="nav-section-title">Quick Actions</h5>
                <a href="../index.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="../support/help.php" class="nav-item">
                    <i class="fas fa-question-circle"></i>
                    <span>Help</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <a href="../auth/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<style>
    /* 🎨 Sidebar Styles */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        width: 280px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        z-index: 1000;
        overflow-y: auto;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    }

    .sidebar.active {
        transform: translateX(0);
    }

    .sidebar-header {
        padding: 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 1.5rem;
        font-weight: 700;
    }

    .logo i {
        font-size: 2rem;
        color: #ffd700;
    }

    .sidebar-close {
        background: none;
        border: none;
        color: white;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 50%;
        transition: background 0.3s ease;
    }

    .sidebar-close:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .sidebar-content {
        padding: 1rem 0;
        flex: 1;
    }

    .user-profile {
        padding: 1rem 1.5rem;
        text-align: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 1rem;
    }

    .user-avatar {
        width: 60px;
        height: 60px;
        margin: 0 auto 1rem;
        border-radius: 50%;
        overflow: hidden;
        border: 3px solid rgba(255, 255, 255, 0.2);
    }

    .user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .avatar-placeholder {
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .user-info h4 {
        margin: 0 0 0.25rem 0;
        font-size: 1.1rem;
        font-weight: 600;
    }

    .user-role {
        font-size: 0.85rem;
        opacity: 0.8;
        background: rgba(255, 255, 255, 0.1);
        padding: 0.25rem 0.75rem;
        border-radius: 15px;
        display: inline-block;
    }

    .nav-menu {
        padding: 0 1rem;
    }

    .nav-section {
        margin-bottom: 1.5rem;
    }

    .nav-section-title {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.7;
        margin-bottom: 0.75rem;
        padding: 0 0.5rem;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        border-radius: 10px;
        margin-bottom: 0.25rem;
        transition: all 0.3s ease;
        position: relative;
    }

    .nav-item:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        transform: translateX(5px);
    }

    .nav-item.active {
        background: rgba(255, 255, 255, 0.15);
        color: white;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .nav-item.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 20px;
        background: #ffd700;
        border-radius: 0 3px 3px 0;
    }

    .nav-item i {
        font-size: 1.1rem;
        width: 20px;
        text-align: center;
    }

    .nav-item span {
        font-weight: 500;
    }

    .nav-badge {
        background: #ff4757;
        color: white;
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
        border-radius: 10px;
        margin-left: auto;
        font-weight: 600;
        min-width: 20px;
        text-align: center;
    }

    .sidebar-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        margin-top: auto;
    }

    .logout-btn {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.05);
    }

    .logout-btn:hover {
        background: rgba(255, 0, 0, 0.1);
        color: #ff6b6b;
        transform: translateX(5px);
    }

    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }

    .sidebar-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    /* 📱 Mobile Responsive */
    @media (max-width: 768px) {
        .sidebar {
            width: 100%;
            max-width: 320px;
        }
    }

    @media (min-width: 769px) {
        .sidebar {
            transform: translateX(0);
            position: relative;
            height: auto;
            min-height: 100vh;
        }

        .sidebar-close {
            display: none;
        }

        .sidebar-overlay {
            display: none;
        }
    }

    /* 🎨 Scrollbar Styling */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 3px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.5);
    }

    /* 🎯 Animation Classes */
    .nav-item {
        animation: slideInLeft 0.3s ease-out;
    }

    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* 🔔 Notification Pulse Animation */
    .nav-badge {
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.7);
        }

        70% {
            box-shadow: 0 0 0 10px rgba(255, 71, 87, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(255, 71, 87, 0);
        }
    }

    /* 🎨 Dark Mode Support */
    @media (prefers-color-scheme: dark) {
        .sidebar {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        }

        .nav-item.active::before {
            background: #3498db;
        }
    }

    /* 🎯 Print Styles */
    @media print {
        .sidebar {
            display: none;
        }
    }
</style>

<script>
    // 🎯 Navigation JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebarToggle = document.getElementById('sidebarToggle');

        // Toggle sidebar on mobile
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            });
        }

        // Close sidebar
        function closeSidebar() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        if (sidebarClose) {
            sidebarClose.addEventListener('click', closeSidebar);
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }

        // Close sidebar on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                closeSidebar();
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });

        // Add active class to current page
        const currentPath = window.location.pathname;
        const navItems = document.querySelectorAll('.nav-item');

        navItems.forEach(item => {
            const href = item.getAttribute('href');
            if (href && currentPath.includes(href.replace('../', ''))) {
                item.classList.add('active');
            }
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
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

        // Add loading state to navigation links
        navItems.forEach(item => {
            item.addEventListener('click', function(e) {
                // Don't add loading for external links or logout
                if (this.getAttribute('href').startsWith('http') ||
                    this.getAttribute('href').includes('logout')) {
                    return;
                }

                // Add loading spinner
                const icon = this.querySelector('i');
                const originalClass = icon.className;
                icon.className = 'fas fa-spinner fa-spin';

                // Restore original icon after a short delay if navigation fails
                setTimeout(() => {
                    if (icon.className.includes('fa-spinner')) {
                        icon.className = originalClass;
                    }
                }, 3000);
            });
        });

        // Update notification badges dynamically
        function updateNotificationBadges() {
            fetch('../api/notifications/count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const notificationBadge = document.querySelector('.nav-item[href*="notifications"] .nav-badge');
                        if (notificationBadge) {
                            if (data.count > 0) {
                                notificationBadge.textContent = data.count;
                                notificationBadge.style.display = 'inline-block';
                            } else {
                                notificationBadge.style.display = 'none';
                            }
                        }
                    }
                })
                .catch(error => console.error('Error updating notification badges:', error));
        }

        // Update badges every 30 seconds
        setInterval(updateNotificationBadges, 30000);

        // Collapse/expand navigation sections on mobile
        const navSections = document.querySelectorAll('.nav-section');
        navSections.forEach(section => {
            const title = section.querySelector('.nav-section-title');
            if (title && window.innerWidth <= 768) {
                title.style.cursor = 'pointer';
                title.addEventListener('click', function() {
                    const items = section.querySelectorAll('.nav-item');
                    const isCollapsed = section.classList.contains('collapsed');

                    if (isCollapsed) {
                        section.classList.remove('collapsed');
                        items.forEach(item => {
                            item.style.display = 'flex';
                        });
                    } else {
                        section.classList.add('collapsed');
                        items.forEach(item => {
                            item.style.display = 'none';
                        });
                    }
                });
            }
        });

        // Add tooltip functionality for collapsed sidebar
        const navItemsWithTooltip = document.querySelectorAll('.nav-item');
        navItemsWithTooltip.forEach(item => {
            item.addEventListener('mouseenter', function() {
                if (sidebar.classList.contains('collapsed')) {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'nav-tooltip';
                    tooltip.textContent = this.querySelector('span').textContent;
                    document.body.appendChild(tooltip);

                    const rect = this.getBoundingClientRect();
                    tooltip.style.left = rect.right + 10 + 'px';
                    tooltip.style.top = rect.top + (rect.height / 2) - (tooltip.offsetHeight / 2) + 'px';
                }
            });

            item.addEventListener('mouseleave', function() {
                const tooltip = document.querySelector('.nav-tooltip');
                if (tooltip) {
                    tooltip.remove();
                }
            });
        });
    });

    // 🎯 Utility Functions
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button class="notification-close">&times;</button>
    `;

        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);

        // Manual close
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.remove();
        });
    }

    // Export for use in other scripts
    window.NavigationUtils = {
        showNotification,
        closeSidebar: function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    };
</script>

<!-- Additional CSS for tooltips and notifications -->
<style>
    .nav-tooltip {
        position: fixed;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 5px;
        font-size: 0.9rem;
        z-index: 1001;
        pointer-events: none;
        white-space: nowrap;
    }

    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        z-index: 1002;
        min-width: 300px;
        animation: slideInRight 0.3s ease-out;
    }

    .notification-success {
        border-left: 4px solid #4CAF50;
        color: #4CAF50;
    }

    .notification-error {
        border-left: 4px solid #f44336;
        color: #f44336;
    }

    .notification-info {
        border-left: 4px solid #2196F3;
        color: #2196F3;
    }

    .notification-close {
        background: none;
        border: none;
        font-size: 1.2rem;
        cursor: pointer;
        margin-left: auto;
        opacity: 0.7;
    }

    .notification-close:hover {
        opacity: 1;
    }

    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* 🎨 Responsive Navigation Adjustments */
    @media (max-width: 480px) {
        .sidebar {
            width: 100%;
        }

        .nav-section-title {
            font-size: 0.7rem;
        }

        .nav-item {
            padding: 0.6rem 0.8rem;
            font-size: 0.9rem;
        }

        .user-profile {
            padding: 0.8rem 1rem;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
        }

        .user-info h4 {
            font-size: 1rem;
        }

        .notification {
            min-width: 280px;
            right: 10px;
            top: 10px;
        }
    }

    /* 🎯 High Contrast Mode */
    @media (prefers-contrast: high) {
        .sidebar {
            background: #000;
            border-right: 2px solid #fff;
        }

        .nav-item {
            border: 1px solid transparent;
        }

        .nav-item:hover,
        .nav-item.active {
            border-color: #fff;
            background: #333;
        }
    }

    /* 🎨 Reduced Motion */
    @media (prefers-reduced-motion: reduce) {

        .sidebar,
        .nav-item,
        .sidebar-overlay,
        .notification {
            transition: none;
            animation: none;
        }

        .nav-badge {
            animation: none;
        }
    }

    /* 🔧 Focus Styles for Accessibility */
    .nav-item:focus,
    .logout-btn:focus,
    .sidebar-close:focus {
        outline: 2px solid #ffd700;
        outline-offset: 2px;
    }

    /* 🎯 Loading States */
    .nav-item.loading {
        opacity: 0.7;
        pointer-events: none;
    }

    .nav-item.loading i {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    /* 🎨 Custom Scrollbar for Navigation */
    .nav-menu {
        scrollbar-width: thin;
        scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
    }

    .nav-menu::-webkit-scrollbar {
        width: 4px;
    }

    .nav-menu::-webkit-scrollbar-track {
        background: transparent;
    }

    .nav-menu::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 2px;
    }

    /* 🎯 Sidebar Collapsed State */
    .sidebar.collapsed {
        width: 70px;
    }

    .sidebar.collapsed .sidebar-header .logo span,
    .sidebar.collapsed .user-info,
    .sidebar.collapsed .nav-section-title,
    .sidebar.collapsed .nav-item span,
    .sidebar.collapsed .nav-badge {
        display: none;
    }

    .sidebar.collapsed .nav-item {
        justify-content: center;
        padding: 0.75rem;
    }

    .sidebar.collapsed .user-profile {
        padding: 1rem 0.5rem;
    }

    /* 🎨 Theme Variations */
    .sidebar.theme-dark {
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    }

    .sidebar.theme-blue {
        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    }

    .sidebar.theme-green {
        background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    }

    .sidebar.theme-purple {
        background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
    }

    /* 🔔 Status Indicators */
    .nav-item .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-left: auto;
    }

    .nav-item .status-indicator.online {
        background: #4CAF50;
    }

    .nav-item .status-indicator.offline {
        background: #f44336;
    }

    .nav-item .status-indicator.away {
        background: #ff9800;
    }

    /* 🎯 Breadcrumb Integration */
    .nav-breadcrumb {
        padding: 0.5rem 1.5rem;
        background: rgba(255, 255, 255, 0.05);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        font-size: 0.8rem;
    }

    .nav-breadcrumb a {
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
    }

    .nav-breadcrumb a:hover {
        color: white;
    }

    .nav-breadcrumb .separator {
        margin: 0 0.5rem;
        opacity: 0.5;
    }

    /* 🎨 Search Integration */
    .nav-search {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .nav-search input {
        width: 100%;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 20px;
        padding: 0.5rem 1rem;
        color: white;
        font-size: 0.9rem;
    }

    .nav-search input::placeholder {
        color: rgba(255, 255, 255, 0.5);
    }

    .nav-search input:focus {
        outline: none;
        border-color: #ffd700;
        background: rgba(255, 255, 255, 0.15);
    }

    /* 🎯 Quick Actions */
    .nav-quick-actions {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .quick-action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.1);
        border: none;
        border-radius: 10px;
        color: white;
        margin-right: 0.5rem;
        margin-bottom: 0.5rem;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .quick-action-btn:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
    }

    /* 🔧 Utility Classes */
    .nav-divider {
        height: 1px;
        background: rgba(255, 255, 255, 0.1);
        margin: 1rem 1.5rem;
    }

    .nav-spacer {
        height: 1rem;
    }

    .nav-text-muted {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.8rem;
    }

    .nav-text-small {
        font-size: 0.75rem;
    }

    .nav-text-bold {
        font-weight: 600;
    }

    /* 🎨 Animation Delays for Staggered Effect */
    .nav-section:nth-child(1) .nav-item {
        animation-delay: 0.1s;
    }

    .nav-section:nth-child(2) .nav-item {
        animation-delay: 0.2s;
    }

    .nav-section:nth-child(3) .nav-item {
        animation-delay: 0.3s;
    }

    .nav-section:nth-child(4) .nav-item {
        animation-delay: 0.4s;
    }

    .nav-section:nth-child(5) .nav-item {
        animation-delay: 0.5s;
    }

    /* 🎯 Print Optimization */
    @media print {

        .sidebar,
        .sidebar-overlay,
        .notification {
            display: none !important;
        }
    }

    /* 🔧 RTL Support */
    [dir="rtl"] .sidebar {
        left: auto;
        right: 0;
        transform: translateX(100%);
    }

    [dir="rtl"] .sidebar.active {
        transform: translateX(0);
    }

    [dir="rtl"] .nav-item.active::before {
        left: auto;
        right: 0;
        border-radius: 3px 0 0 3px;
    }

    [dir="rtl"] .nav-item:hover {
        transform: translateX(-5px);
    }

    [dir="rtl"] .logout-btn:hover {
        transform: translateX(-5px);
    }

    /* 🎨 Custom Properties for Easy Theming */
    :root {
        --sidebar-width: 280px;
        --sidebar-collapsed-width: 70px;
        --sidebar-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --sidebar-text: white;
        --sidebar-text-muted: rgba(255, 255, 255, 0.7);
        --sidebar-hover-bg: rgba(255, 255, 255, 0.1);
        --sidebar-active-bg: rgba(255, 255, 255, 0.15);
        --sidebar-accent: #ffd700;
        --sidebar-border: rgba(255, 255, 255, 0.1);
    }

    /* 🎯 Component Variants */
    .sidebar.variant-minimal {
        --sidebar-bg: #ffffff;
        --sidebar-text: #333333;
        --sidebar-text-muted: #666666;
        --sidebar-hover-bg: #f5f5f5;
        --sidebar-active-bg: #e3f2fd;
        --sidebar-accent: #2196F3;
        --sidebar-border: #e0e0e0;

        background: var(--sidebar-bg);
        color: var(--sidebar-text);
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    }

    .sidebar.variant-minimal .nav-item {
        color: var(--sidebar-text-muted);
    }

    .sidebar.variant-minimal .nav-item:hover,
    .sidebar.variant-minimal .nav-item.active {
        background: var(--sidebar-hover-bg);
        color: var(--sidebar-text);
    }

    .sidebar.variant-minimal .nav-item.active::before {
        background: var(--sidebar-accent);
    }

    /* 🔧 Performance Optimizations */
    .sidebar {
        will-change: transform;
        contain: layout style paint;
    }

    .nav-item {
        will-change: transform, background-color;
    }

    /* 🎨 Final Responsive Adjustments */
    @media (max-width: 320px) {
        .sidebar {
            font-size: 0.85rem;
        }

        .nav-item {
            padding: 0.5rem 0.75rem;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
        }
    }

    @media (min-width: 1200px) {
        .sidebar {
            width: 300px;
        }

        .nav-item {
            padding: 0.875rem 1.25rem;
        }
    }

    /* 🎯 Accessibility Enhancements */
    @media (prefers-reduced-motion: reduce) {
        * {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }

    /* 🔧 Browser Specific Fixes */
    @supports (-webkit-backdrop-filter: blur(10px)) {
        .sidebar-overlay {
            -webkit-backdrop-filter: blur(10px);
            backdrop-filter: blur(10px);
            background: rgba(0, 0, 0, 0.3);
        }
    }

    /* 🎨 Hover Effects for Better UX */
    .nav-item::after {
        content: '';
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        width: 0;
        height: 2px;
        background: var(--sidebar-accent, #ffd700);
        transition: width 0.3s ease;
    }

    .nav-item:hover::after {
        width: 20px;
    }

    .nav-item.active::after {
        width: 25px;
    }

    /* 🎯 Final Touch - Smooth Transitions */
    * {
        box-sizing: border-box;
    }

    .sidebar * {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
</style>

<?php
// Add JavaScript for enhanced functionality
?>
<script>
    // 🎯 Enhanced Navigation Features
    (function() {
        'use strict';

        // Navigation state management
        const NavigationState = {
            isCollapsed: localStorage.getItem('sidebar-collapsed') === 'true',
            theme: localStorage.getItem('sidebar-theme') || 'default',

            save() {
                localStorage.setItem('sidebar-collapsed', this.isCollapsed);
                localStorage.setItem('sidebar-theme', this.theme);
            },

            load() {
                const sidebar = document.getElementById('sidebar');
                if (this.isCollapsed && window.innerWidth > 768) {
                    sidebar.classList.add('collapsed');
                }
                if (this.theme !== 'default') {
                    sidebar.classList.add(`theme-${this.theme}`);
                }
            }
        };

        // Initialize navigation state
        document.addEventListener('DOMContentLoaded', function() {
            NavigationState.load();

            // Add collapse toggle for desktop
            if (window.innerWidth > 768) {
                addCollapseToggle();
            }

            // Add keyboard shortcuts
            addKeyboardShortcuts();

            // Add search functionality
            addSearchFunctionality();

            // Add theme switcher
            addThemeSwitcher();
        });

        // Add collapse toggle button
        function addCollapseToggle() {
            const sidebar = document.getElementById('sidebar');
            const header = sidebar.querySelector('.sidebar-header');

            const collapseBtn = document.createElement('button');
            collapseBtn.className = 'collapse-toggle';
            collapseBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
            collapseBtn.title = 'Collapse Sidebar';

            collapseBtn.addEventListener('click', function() {
                NavigationState.isCollapsed = !NavigationState.isCollapsed;
                sidebar.classList.toggle('collapsed');

                const icon = this.querySelector('i');
                icon.className = NavigationState.isCollapsed ?
                    'fas fa-chevron-right' : 'fas fa-chevron-left';

                this.title = NavigationState.isCollapsed ?
                    'Expand Sidebar' : 'Collapse Sidebar';

                NavigationState.save();
            });

            header.appendChild(collapseBtn);
        }

        // Add keyboard shortcuts
        function addKeyboardShortcuts() {
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + B to toggle sidebar
                if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                    e.preventDefault();
                    const sidebar = document.getElementById('sidebar');
                    if (window.innerWidth <= 768) {
                        sidebar.classList.toggle('active');
                        document.getElementById('sidebarOverlay').classList.toggle('active');
                    } else {
                        NavigationState.isCollapsed = !NavigationState.isCollapsed;
                        sidebar.classList.toggle('collapsed');
                        NavigationState.save();
                    }
                }

                // Alt + 1-9 for quick navigation
                if (e.altKey && e.key >= '1' && e.key <= '9') {
                    e.preventDefault();
                    const navItems = document.querySelectorAll('.nav-item');
                    const index = parseInt(e.key) - 1;
                    if (navItems[index]) {
                        navItems[index].click();
                    }
                }
            });
        }

        // Add search functionality
        function addSearchFunctionality() {
            const sidebar = document.getElementById('sidebar');
            const sidebarContent = sidebar.querySelector('.sidebar-content');

            const searchHTML = `
            <div class="nav-search">
                <input type="text" placeholder="Search navigation..." id="navSearch">
            </div>
        `;

            sidebarContent.insertAdjacentHTML('afterbegin', searchHTML);

            const searchInput = document.getElementById('navSearch');
            const navItems = document.querySelectorAll('.nav-item');

            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase();

                navItems.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    const section = item.closest('.nav-section');

                    if (text.includes(query)) {
                        item.style.display = 'flex';
                        section.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });

                // Hide empty sections
                document.querySelectorAll('.nav-section').forEach(section => {
                    const visibleItems = section.querySelectorAll('.nav-item[style*="flex"]');
                    section.style.display = visibleItems.length > 0 ? 'block' : 'none';
                });

                // Show all if search is empty
                if (query === '') {
                    navItems.forEach(item => item.style.display = 'flex');
                    document.querySelectorAll('.nav-section').forEach(section => {
                        section.style.display = 'block';
                    });
                }
            });
        }

        // Add theme switcher
        function addThemeSwitcher() {
            const sidebar = document.getElementById('sidebar');
            const footer = sidebar.querySelector('.sidebar-footer');

            const themeHTML = `
            <div class="nav-quick-actions">
                <button class="quick-action-btn" id="themeToggle" title="Switch Theme">
                    <i class="fas fa-palette"></i>
                </button>
                <button class="quick-action-btn" id="fullscreenToggle" title="Toggle Fullscreen">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
        `;

            footer.insertAdjacentHTML('beforebegin', themeHTML);

            // Theme toggle
            document.getElementById('themeToggle').addEventListener('click', function() {
                const themes = ['default', 'dark', 'blue', 'green', 'purple'];
                const currentIndex = themes.indexOf(NavigationState.theme);
                const nextIndex = (currentIndex + 1) % themes.length;

                // Remove current theme
                sidebar.classList.remove(`theme-${NavigationState.theme}`);

                // Apply new theme
                NavigationState.theme = themes[nextIndex];
                if (NavigationState.theme !== 'default') {
                    sidebar.classList.add(`theme-${NavigationState.theme}`);
                }

                NavigationState.save();

                // Show notification
                if (window.NavigationUtils) {
                    window.NavigationUtils.showNotification(
                        `Theme changed to ${NavigationState.theme}`,
                        'success'
                    );
                }
            });

            // Fullscreen toggle
            document.getElementById('fullscreenToggle').addEventListener('click', function() {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen();
                    this.querySelector('i').className = 'fas fa-compress';
                    this.title = 'Exit Fullscreen';
                } else {
                    document.exitFullscreen();
                    this.querySelector('i').className = 'fas fa-expand';
                    this.title = 'Toggle Fullscreen';
                }
            });
        }

        // Add breadcrumb functionality
        function updateBreadcrumb() {
            const sidebar = document.getElementById('sidebar');
            const sidebarContent = sidebar.querySelector('.sidebar-content');
            const currentPath = window.location.pathname;
            const pathParts = currentPath.split('/').filter(part => part);

            let breadcrumbHTML = '<div class="nav-breadcrumb">';
            let currentUrl = '';

            pathParts.forEach((part, index) => {
                currentUrl += '/' + part;
                const isLast = index === pathParts.length - 1;
                const displayName = part.charAt(0).toUpperCase() + part.slice(1).replace(/[-_]/g, ' ');

                if (isLast) {
                    breadcrumbHTML += `<span class="current">${displayName}</span>`;
                } else {
                    breadcrumbHTML += `<a href="${currentUrl}">${displayName}</a>`;
                    breadcrumbHTML += '<span class="separator">/</span>';
                }
            });

            breadcrumbHTML += '</div>';

            // Insert breadcrumb after search
            const search = sidebarContent.querySelector('.nav-search');
            if (search) {
                search.insertAdjacentHTML('afterend', breadcrumbHTML);
            }
        }

        // Performance optimization: Lazy load navigation badges
        function loadNavigationBadges() {
            const badgeElements = document.querySelectorAll('.nav-badge[data-endpoint]');

            badgeElements.forEach(badge => {
                const endpoint = badge.dataset.endpoint;

                fetch(endpoint)
                    .then(response => response.json())
                    .then(data => {
                        if (data.count > 0) {
                            badge.textContent = data.count;
                            badge.style.display = 'inline-block';
                        } else {
                            badge.style.display = 'none';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading badge:', error);
                        badge.style.display = 'none';
                    });
            });
        }

        // Initialize badge loading
        document.addEventListener('DOMContentLoaded', function() {
            loadNavigationBadges();
            setInterval(loadNavigationBadges, 60000); // Update every minute
        });

        // Add resize observer for responsive behavior
        if (window.ResizeObserver) {
            const resizeObserver = new ResizeObserver(entries => {
                const sidebar = document.getElementById('sidebar');
                const width = entries[0].contentRect.width;

                if (width <= 768) {
                    sidebar.classList.remove('collapsed');
                } else if (NavigationState.isCollapsed) {
                    sidebar.classList.add('collapsed');
                }
            });

            resizeObserver.observe(document.body);
        }

        // Add service worker for offline functionality
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    console.log('SW registered:', registration);
                })
                .catch(error => {
                    console.log('SW registration failed:', error);
                });
        }

        // Export navigation utilities
        window.Navigation = {
            state: NavigationState,
            toggleSidebar() {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');

                if (window.innerWidth <= 768) {
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('active');
                } else {
                    NavigationState.isCollapsed = !NavigationState.isCollapsed;
                    sidebar.classList.toggle('collapsed');
                    NavigationState.save();
                }
            },

            setTheme(theme) {
                const sidebar = document.getElementById('sidebar');
                sidebar.classList.remove(`theme-${NavigationState.theme}`);
                NavigationState.theme = theme;
                if (theme !== 'default') {
                    sidebar.classList.add(`theme-${theme}`);
                }
                NavigationState.save();
            },

            highlightNavItem(href) {
                document.querySelectorAll('.nav-item').forEach(item => {
                    item.classList.remove('active');
                });

                const targetItem = document.querySelector(`.nav-item[href="${href}"]`);
                if (targetItem) {
                    targetItem.classList.add('active');
                }
            },

            updateBadge(selector, count) {
                const badge = document.querySelector(selector);
                if (badge) {
                    if (count > 0) {
                        badge.textContent = count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }
        };

    })();

    // 🎯 Additional CSS for new features
    const additionalStyles = `
<style>
.collapse-toggle {
    background: rgba(255,255,255,0.1);
    border: none;
    color: white;
    padding: 0.5rem;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.collapse-toggle:hover {
    background: rgba(255,255,255,0.2);
}

.nav-breadcrumb .current {
    color: white;
    font-weight: 600;
}

.nav-search input:focus {
    box-shadow: 0 0 0 2px rgba(255, 215, 0, 0.3);
}

@media (max-width: 768px) {
    .nav-search {
        padding: 0.75rem 1rem;
    }
    
    .nav-quick-actions {
        padding: 0.75rem 1rem;
    }
    
    .quick-action-btn {
        width: 35px;
        height: 35px;
    }
}

/* Loading skeleton for badges */
.nav-badge.loading {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Enhanced focus styles */
.nav-search input:focus,
.quick-action-btn:focus,
.collapse-toggle:focus {
    outline: 2px solid #ffd700;
    outline-offset: 2px;
}

/* Smooth scrolling for navigation */
.sidebar-content {
    scroll-behavior: smooth;
}

/* Enhanced mobile experience */
@media (max-width: 480px) {
    .sidebar-header {
        padding: 1rem;
    }
    
    .user-profile {
        padding: 0.75rem 1rem;
    }
    
    .nav-menu {
        padding: 0 0.75rem;
    }
    
    .sidebar-footer {
        padding: 0.75rem 1rem;
    }
}
</style>
`;

    // Inject additional styles
    document.head.insertAdjacentHTML('beforeend', additionalStyles);
</script>

<?php
// 🎯 PHP Helper Functions for Navigation

/**
 * Check if current page matches navigation item
 */
function isCurrentPage($href)
{
    $current = basename($_SERVER['PHP_SELF']);
    $target = basename($href);
    return $current === $target;
}

/**
 * Get navigation badge count
 */
function getNavigationBadgeCount($type, $conn, $userId)
{
    switch ($type) {
        case 'notifications':
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->bind_param("i", $userId);
            break;

        case 'proposals':
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE status = 'pending'");
            break;

        case 'messages':
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? AND is_read = 0");
            $stmt->bind_param("i", $userId);
            break;

        default:
            return 0;
    }

    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['count'] ?? 0;
}

/**
 * Generate navigation menu based on user role
 */
function generateNavigationMenu($role, $currentPage)
{
    $menus = [
        'admin' => [
            'Dashboard' => [
                ['href' => 'dashboard.php', 'icon' => 'tachometer-alt', 'text' => 'Overview'],
                ['href' => 'analytics.php', 'icon' => 'chart-line', 'text' => 'Analytics']
            ],
            'Management' => [
                ['href' => 'events.php', 'icon' => 'calendar-check', 'text' => 'Events'],
                ['href' => 'proposals.php', 'icon' => 'file-alt', 'text' => 'Proposals', 'badge' => 'proposals'],
                ['href' => 'users.php', 'icon' => 'users', 'text' => 'Users'],
                ['href' => 'tickets.php', 'icon' => 'ticket-alt', 'text' => 'Tickets']
            ],
            'Reports' => [
                ['href' => 'reports.php', 'icon' => 'chart-bar', 'text' => 'Reports'],
                ['href' => 'revenue.php', 'icon' => 'dollar-sign', 'text' => 'Revenue']
            ],
            'System' => [
                ['href' => 'settings.php', 'icon' => 'cog', 'text' => 'Settings'],
                ['href' => 'notifications.php', 'icon' => 'bell', 'text' => 'Notifications'],
                ['href' => 'backup.php', 'icon' => 'database', 'text' => 'Backup']
            ]
        ],
        'organizer' => [
            'Dashboard' => [
                ['href' => '../dashboard/index.php', 'icon' => 'tachometer-alt', 'text' => 'Dashboard']
            ],
            'Events' => [
                ['href' => '../events/create.php', 'icon' => 'plus-circle', 'text' => 'Create Event'],
                ['href' => '../events/manage.php', 'icon' => 'calendar-alt', 'text' => 'My Events'],
                ['href' => '../events/analytics.php', 'icon' => 'chart-line', 'text' => 'Analytics']
            ],
            'Management' => [
                ['href' => '../tickets/scan.php', 'icon' => 'qrcode', 'text' => 'Scan Tickets'],
                ['href' => '../attendees/list.php', 'icon' => 'users', 'text' => 'Attendees']
            ]
        ],
        'user' => [
            'Dashboard' => [
                ['href' => '../dashboard/index.php', 'icon' => 'tachometer-alt', 'text' => 'Dashboard']
            ],
            'Events' => [
                ['href' => '../events/browse.php', 'icon' => 'calendar-alt', 'text' => 'Browse Events'],
                ['href' => '../events/registered.php', 'icon' => 'ticket-alt', 'text' => 'My Events']
            ],
            'Account' => [
                ['href' => '../tickets/my-tickets.php', 'icon' => 'ticket-alt', 'text' => 'My Tickets'],
                ['href' => '../profile/notifications.php', 'icon' => 'bell', 'text' => 'Notifications', 'badge' => 'notifications']
            ]
        ]
    ];

    return $menus[$role] ?? $menus['user'];
}

/**
 * Render navigation badge
 */
function renderNavigationBadge($type, $count = null)
{
    if ($count === null) {
        return '<span class="nav-badge loading" data-endpoint="../api/badges/' . $type . '.php"></span>';
    }

    if ($count > 0) {
        return '<span class="nav-badge">' . $count . '</span>';
    }

    return '';
}

/**
 * Get user's recent activity for navigation
 */
function getUserRecentActivity($conn, $userId, $limit = 5)
{
    $stmt = $conn->prepare("
        SELECT 'event' as type, title as description, created_at 
        FROM events 
        WHERE organizer_id = ? 
        UNION ALL
        SELECT 'ticket' as type, CONCAT('Registered for ', e.title) as description, t.created_at
        FROM tickets t 
        JOIN events e ON t.event_id = e.event_id 
        WHERE t.user_id = ?
        ORDER BY created_at DESC 
        LIMIT ?
    ");

    $stmt->bind_param("iii", $userId, $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    return $result->fetch_all(MYSQLI_ASSOC);
}

// 🎯 End of Navigation Component
?>

<!-- 🎨 Additional Styles for Enhanced Navigation -->
<style>
    /* Final responsive adjustments */
    @media (max-width: 360px) {
        .sidebar {
            font-size: 0.8rem;
        }

        .nav-item {
            padding: 0.4rem 0.6rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
        }

        .user-info h4 {
            font-size: 0.9rem;
        }

        .nav-section-title {
            font-size: 0.65rem;
        }
    }

    /* Enhanced accessibility */
    .nav-item[aria-current="page"] {
        background: rgba(255, 255, 255, 0.2);
        font-weight: 600;
    }

    /* Improved loading states */
    .nav-item.loading::after {
        content: '';
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-top: 2px solid white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    /* Better visual hierarchy */
    .nav-section:not(:last-child) {
        margin-bottom: 2rem;
    }

    .nav-section:not(:last-child)::after {
        content: '';
        display: block;
        width: 80%;
        height: 1px;
        background: rgba(255, 255, 255, 0.1);
        margin: 1rem auto 0;
    }

    /* Enhanced hover effects */
    .nav-item {
        position: relative;
        overflow: hidden;
    }

    .nav-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        transition: left 0.5s ease;
    }

    .nav-item:hover::before {
        left: 100%;
    }

    /* Status indicators for navigation items */
    .nav-item.has-updates::after {
        content: '';
        position: absolute;
        right: 0.5rem;
        top: 50%;
        transform: translateY(-50%);
        width: 6px;
        height: 6px;
        background: #ff4757;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    /* Improved collapsed state */
    .sidebar.collapsed .nav-item {
        position: relative;
    }

    .sidebar.collapsed .nav-item:hover {
        background: rgba(255, 255, 255, 0.15);
    }

    .sidebar.collapsed .nav-item:hover::after {
        content: attr(title);
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 5px;
        white-space: nowrap;
        z-index: 1001;
        margin-left: 10px;
        font-size: 0.9rem;
    }

    /* Better mobile experience */
    @media (max-width: 768px) {
        .sidebar {
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .sidebar-content {
            padding-bottom: 2rem;
        }

        .nav-item {
            margin-bottom: 0.1rem;
        }

        .nav-section {
            margin-bottom: 1rem;
        }
    }

    /* Print styles */
    @media print {

        .sidebar,
        .sidebar-overlay,
        .nav-tooltip,
        .notification {
            display: none !important;
        }
    }

    /* High contrast mode improvements */
    @media (prefers-contrast: high) {
        .nav-item {
            border: 1px solid transparent;
        }

        .nav-item:hover,
        .nav-item.active {
            border-color: currentColor;
            background: rgba(255, 255, 255, 0.2);
        }

        .nav-badge {
            border: 1px solid currentColor;
        }
    }

    /* Final performance optimizations */
    .sidebar {
        transform: translateZ(0);
        backface-visibility: hidden;
    }

    .nav-item {
        transform: translateZ(0);
    }

    /* Smooth scrolling for navigation sections */
    .nav-section {
        scroll-margin-top: 2rem;
    }

    /* Enhanced focus management */
    .sidebar:focus-within .nav-item:not(:focus) {
        opacity: 0.7;
    }

    .sidebar:focus-within .nav-item:focus {
        opacity: 1;
        transform: translateX(5px);
    }
</style>

<script>
    // 🎯 Final JavaScript enhancements
    document.addEventListener('DOMContentLoaded', function() {
        // Add ARIA attributes for better accessibility
        const sidebar = document.getElementById('sidebar');
        const navItems = document.querySelectorAll('.nav-item');

        sidebar.setAttribute('role', 'navigation');
        sidebar.setAttribute('aria-label', 'Main navigation');

        navItems.forEach((item, index) => {
            item.setAttribute('role', 'menuitem');
            item.setAttribute('tabindex', index === 0 ? '0' : '-1');

            // Add title attribute for collapsed state
            const text = item.querySelector('span').textContent;
            item.setAttribute('title', text);

            // Add aria-current for active items
            if (item.classList.contains('active')) {
                item.setAttribute('aria-current', 'page');
            }
        });

        // Keyboard navigation
        sidebar.addEventListener('keydown', function(e) {
            const focusedItem = document.activeElement;
            const items = Array.from(navItems);
            const currentIndex = items.indexOf(focusedItem);

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    const nextIndex = (currentIndex + 1) % items.length;
                    items[nextIndex].focus();
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    const prevIndex = currentIndex === 0 ? items.length - 1 : currentIndex - 1;
                    items[prevIndex].focus();
                    break;

                case 'Home':
                    e.preventDefault();
                    items[0].focus();
                    break;

                case 'End':
                    e.preventDefault();
                    items[items.length - 1].focus();
                    break;

                case 'Enter':
                case ' ':
                    e.preventDefault();
                    focusedItem.click();
                    break;
            }
        });

        // Update tabindex based on focus
        navItems.forEach(item => {
            item.addEventListener('focus', function() {
                navItems.forEach(i => i.setAttribute('tabindex', '-1'));
                this.setAttribute('tabindex', '0');
            });
        });

        // Add loading states for navigation
        navItems.forEach(item => {
            item.addEventListener('click', function(e) {
                if (!this.href || this.href.includes('#') || this.href.includes('logout')) {
                    return;
                }

                this.classList.add('loading');

                // Remove loading state after navigation or timeout
                setTimeout(() => {
                    this.classList.remove('loading');
                }, 3000);
            });
        });

        // Initialize intersection observer for navigation highlighting
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const id = entry.target.id;
                        const navLink = document.querySelector(`.nav-item[href="#${id}"]`);
                        if (navLink) {
                            document.querySelectorAll('.nav-item').forEach(item => {
                                item.classList.remove('active');
                            });
                            navLink.classList.add('active');
                        }
                    }
                });
            }, {
                threshold: 0.5
            });

            // Observe sections if they exist
            document.querySelectorAll('section[id]').forEach(section => {
                observer.observe(section);
            });
        }

        // Add swipe gestures for mobile
        if ('ontouchstart' in window) {
            let startX = 0;
            let startY = 0;

            document.addEventListener('touchstart', function(e) {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            });

            document.addEventListener('touchend', function(e) {
                const endX = e.changedTouches[0].clientX;
                const endY = e.changedTouches[0].clientY;
                const diffX = startX - endX;
                const diffY = startY - endY;

                // Swipe right to open sidebar
                if (Math.abs(diffX) > Math.abs(diffY) && diffX < -50 && startX < 50) {
                    const sidebar = document.getElementById('sidebar');
                    const overlay = document.getElementById('sidebarOverlay');
                    sidebar.classList.add('active');
                    overlay.classList.add('active');
                }

                // Swipe left to close sidebar
                if (Math.abs(diffX) > Math.abs(diffY) && diffX > 50) {
                    const sidebar = document.getElementById('sidebar');
                    const overlay = document.getElementById('sidebarOverlay');
                    if (sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                        overlay.classList.remove('active');
                    }
                }
            });
        }

        console.log('🎯 Navigation component loaded successfully');
    });

    // Export final navigation utilities
    window.EMS_Navigation = {
        version: '1.0.0',
        initialized: true,

        // Public API
        toggle: () => window.Navigation.toggleSidebar(),
        setTheme: (theme) => window.Navigation.setTheme(theme),
        highlight: (href) => window.Navigation.highlightNavItem(href),
        updateBadge: (selector, count) => window.Navigation.updateBadge(selector, count),

        // State management
        getState: () => window.Navigation.state,

        // Utility functions
        showNotification: (message, type) => {
            if (window.NavigationUtils) {
                window.NavigationUtils.showNotification(message, type);
            }
        }
    };
</script>

<?php
// 🎯 Final PHP cleanup and validation
if (!function_exists('validateNavigationAccess')) {
    function validateNavigationAccess($requiredRole, $userRole)
    {
        $roleHierarchy = ['user' => 1, 'organizer' => 2, 'admin' => 3];
        return ($roleHierarchy[$userRole] ?? 0) >= ($roleHierarchy[$requiredRole] ?? 0);
    }
}

// Log navigation load for debugging
if (defined('DEVELOPMENT') && DEVELOPMENT) {
    error_log("Navigation component loaded for user: " . ($_SESSION['user_id'] ?? 'guest') . " with role: " . ($_SESSION['role'] ?? 'none'));
}
?>