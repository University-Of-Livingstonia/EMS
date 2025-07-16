<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get unread notifications count
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $unread_count = $result->fetch_assoc()['count'];
}
?>

<style>
    .sidebar {
        width: 280px;
        height: 100vh;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1000;
        transition: all 0.3s ease;
        overflow-y: auto;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    }

    .sidebar.collapsed {
        width: 70px;
    }

    .sidebar-header {
        padding: 2rem 1.5rem;
        text-align: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar-logo {
        color: white;
        font-size: 1.5rem;
        font-weight: 700;
        text-decoration: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .sidebar-logo i {
        font-size: 2rem;
    }

    .sidebar.collapsed .sidebar-logo span {
        display: none;
    }

    .sidebar-nav {
        padding: 1rem 0;
    }

    .nav-item {
        margin-bottom: 0.5rem;
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 1rem 1.5rem;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: all 0.3s ease;
        position: relative;
    }

    .nav-link:hover,
    .nav-link.active {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        transform: translateX(5px);
    }

    .nav-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: white;
    }

    .nav-icon {
        width: 20px;
        margin-right: 1rem;
        text-align: center;
    }

    .nav-text {
        flex: 1;
    }

    .sidebar.collapsed .nav-text {
        display: none;
    }

    .nav-badge {
        background: #ff4757;
        color: white;
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 10px;
        min-width: 20px;
        text-align: center;
    }

    .sidebar.collapsed .nav-badge {
        position: absolute;
        right: -10px;
        top: 50%;
        transform: translateY(-50%);
    }

    .sidebar-toggle {
        position: absolute;
        top: 1rem;
        right: -15px;
        width: 30px;
        height: 30px;
        background: white;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .sidebar-toggle:hover {
        transform: scale(1.1);
    }

    .main-content {
        margin-left: 280px;
        transition: all 0.3s ease;
        min-height: 100vh;
        background: #f8f9fa;
    }

    .sidebar.collapsed+.main-content {
        margin-left: 70px;
    }

    .user-profile {
        padding: 1rem 1.5rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        margin-top: auto;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        color: white;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }

    .user-details h4 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
    }

    .user-details p {
        margin: 0;
        font-size: 0.8rem;
        opacity: 0.8;
        text-transform: capitalize;
    }

    .sidebar.collapsed .user-details {
        display: none;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.mobile-open {
            transform: translateX(0);
        }

        .main-content {
            margin-left: 0;
        }

        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .mobile-overlay.active {
            display: block;
        }
    }
</style>

<div class="sidebar" id="sidebar">
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-chevron-left"></i>
    </button>

    <div class="sidebar-header">
        <a href="../dashboard/" class="sidebar-logo">
            <i class="fas fa-calendar-alt"></i>
            <span>EMS</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-item">
            <a href="../dashboard/" class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-home nav-icon"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="../events/" class="nav-link <?php echo strpos($current_page, 'events') !== false ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt nav-icon"></i>
                <span class="nav-text">Events</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="../dashboard/notifications.php" class="nav-link <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">
                <i class="fas fa-bell nav-icon"></i>
                <span class="nav-text">Notifications</span>
                <?php if ($unread_count > 0): ?>
                    <span class="nav-badge"><?php echo $unread_count > 99 ? '99+' : $unread_count; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <div class="nav-item">
            <a href="../tickets/" class="nav-link <?php echo strpos($current_page, 'tickets') !== false ? 'active' : ''; ?>">
                <i class="fas fa-ticket-alt nav-icon"></i>
                <span class="nav-text">My Tickets</span>
            </a>
        </div>

        <?php if ($_SESSION['role'] === 'organizer' || $_SESSION['role'] === 'admin'): ?>
            <div class="nav-item">
                <a href="../organizer/" class="nav-link <?php echo strpos($current_page, 'organizer') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-users nav-icon"></i>
                    <span class="nav-text">My Events</span>
                </a>
            </div>
        <?php endif; ?>

        <?php if ($_SESSION['role'] === 'admin'): ?>
            <div class="nav-item">
                <a href="../admin/" class="nav-link <?php echo strpos($current_page, 'admin') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-cog nav-icon"></i>
                    <span class="nav-text">Admin Panel</span>
                </a>
            </div>
        <?php endif; ?>

        <div class="nav-item">
            <a href="../profile/" class="nav-link <?php echo strpos($current_page, 'profile') !== false ? 'active' : ''; ?>">
                <i class="fas fa-user nav-icon"></i>
                <span class="nav-text">Profile</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="../settings/" class="nav-link <?php echo strpos($current_page, 'settings') !== false ? 'active' : ''; ?>">
                <i class="fas fa-cog nav-icon"></i>
                <span class="nav-text">Settings</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="../help/" class="nav-link <?php echo strpos($current_page, 'help') !== false ? 'active' : ''; ?>">
                <i class="fas fa-question-circle nav-icon"></i>
                <span class="nav-text">Help & Support</span>
            </a>
        </div>

        <div class="nav-item">
            <a href="../auth/logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt nav-icon"></i>
                <span class="nav-text">Logout</span>
            </a>
        </div>
    </nav>

    <div class="user-profile">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1) . substr($_SESSION['last_name'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <h4><?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></h4>
                <p><?php echo htmlspecialchars($_SESSION['role']); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="mobile-overlay" id="mobileOverlay" onclick="closeMobileSidebar()"></div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const icon = document.querySelector('.sidebar-toggle i');

        sidebar.classList.toggle('collapsed');

        if (sidebar.classList.contains('collapsed')) {
            icon.classList.remove('fa-chevron-left');
            icon.classList.add('fa-chevron-right');
        } else {
            icon.classList.remove('fa-chevron-right');
            icon.classList.add('fa-chevron-left');
        }

        // Save state to localStorage
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    }

    function openMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobileOverlay');

        sidebar.classList.add('mobile-open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobileOverlay');

        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    // Restore sidebar state on page load
    document.addEventListener('DOMContentLoaded', function() {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            toggleSidebar();
        }

        // Add mobile menu toggle for responsive design
        const mobileToggle = document.getElementById('sidebarToggle');
        if (mobileToggle) {
            mobileToggle.addEventListener('click', openMobileSidebar);
        }
    });

    // Close mobile sidebar when clicking on nav links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                closeMobileSidebar();
            }
        });
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeMobileSidebar();
        }
    });
</script>