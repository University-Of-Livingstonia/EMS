<?php 
$page_title = "UNILIA - Unleash Campus Events";
$current_page = "home";
include '../../includes/header.php'; 
?>

<!-- Hero Section with 3D Effects -->
<section class="hero-3d perspective-container" data-parallax="0.5">
    <div class="parallax-bg" data-parallax="0.3"></div>
    
    <!-- Floating Background Elements -->
    <div class="floating-elements">
        <div class="float-element neon-glow" style="--delay: 0s;"></div>
        <div class="float-element neon-glow" style="--delay: 2s;"></div>
        <div class="float-element neon-glow" style="--delay: 4s;"></div>
    </div>
    
    <div class="hero-content glass-morphism">
        <h1 class="gradient-text hero-title">
            Unleash the Power of Campus Events!
        </h1>
        <p class="hero-subtitle">
            Experience the future of event management with cutting-edge technology
        </p>
        
        <div class="holographic-buttons">
            <a href="events.php" class="btn-3d primary">
                <span>🚀 Browse Events</span>
                <div class="btn-glow"></div>
            </a>
            <a href="register.php" class="btn-3d secondary">
                <span>✨ Register Now</span>
                <div class="btn-glow"></div>
            </a>
        </div>
    </div>
</section>

<!-- Featured Events with 3D Cards -->
<section class="featured-events perspective-container">
    <div class="container">
        <h2 class="section-title">🔥 Featured Events</h2>
        
        <div class="featured-grid">
            <?php
            // TODO: Replace with database query
            // $featured_events = getFeaturedEvents(); // This will come from database
            
            // Temporary static data - will be replaced with dynamic database content
            $featured_events = [
                [
                    'id' => 1,
                    'title' => 'AI & Future Tech Summit', 
                    'type' => 'Academic', 
                    'date' => '2025-02-15',
                    'time' => '10:00 AM',
                    'location' => 'Main Auditorium',
                    'description' => 'Explore the latest in AI technology and its impact on education',
                    'icon' => '🤖',
                    'organizer' => 'Tech Department'
                ],
                [
                    'id' => 2,
                    'title' => 'Campus Music Festival', 
                    'type' => 'Social', 
                    'date' => '2025-02-20',
                    'time' => '6:00 PM',
                    'location' => 'Campus Grounds',
                    'description' => 'Join us for an evening of amazing music and entertainment',
                    'icon' => '🎵',
                    'organizer' => 'Student Union'
                ],
                [
                    'id' => 3,
                    'title' => 'Innovation Showcase', 
                    'type' => 'Academic', 
                    'date' => '2025-02-25',
                    'time' => '2:00 PM',
                    'location' => 'Innovation Lab',
                    'description' => 'Students present their groundbreaking projects and innovations',
                    'icon' => '💡',
                    'organizer' => 'Research Department'
                ]
            ];
            
            foreach ($featured_events as $index => $event): ?>
                <div class="event-card-3d card-3d float-element" style="animation-delay: <?php echo $index * 0.2; ?>s;">
                    <div class="event-content">
                        <div class="event-icon"><?php echo $event['icon']; ?></div>
                        <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                        <div class="event-meta">
                            <span class="event-type <?php echo strtolower($event['type']); ?>">
                                <?php echo $event['type']; ?>
                            </span>
                            <span class="event-date">📅 <?php echo date('M j, Y', strtotime($event['date'])); ?></span>
                        </div>
                        <div class="event-details">
                            <p class="event-time">⏰ <?php echo $event['time']; ?></p>
                            <p class="event-location">📍 <?php echo htmlspecialchars($event['location']); ?></p>
                            <p class="event-organizer">👤 <?php echo htmlspecialchars($event['organizer']); ?></p>
                            <p class="event-description"><?php echo htmlspecialchars($event['description']); ?></p>
                        </div>
                        <div class="event-actions">
                            <a href="event-details.php?id=<?php echo $event['id']; ?>" class="btn-3d small">
                                View Details
                            </a>
                            <button class="btn-3d small secondary" onclick="registerForEvent(<?php echo $event['id']; ?>)">
                                Register
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Dynamic Calendar & Filters Section -->
<section class="calendar-section perspective-container">
    <div class="container">
        <h2 class="section-title">📅 Event Calendar & Filters</h2>
        
        <!-- Calendar Navigation -->
        <div class="calendar-nav glass-morphism">
            <button class="nav-btn" onclick="previousMonth()">←</button>
            <span class="current-month" id="currentMonth">February 2025</span>
            <button class="nav-btn" onclick="nextMonth()">→</button>
        </div>
        
        <!-- Calendar Grid -->
        <div class="calendar-container glass-morphism">
            <div class="calendar-grid" id="calendarGrid">
                <!-- Calendar days will be dynamically generated -->
            </div>
        </div>
        
        <!-- Event Filters -->
        <div class="filters-container">
            <h3 class="filter-title">🎯 Filter Events</h3>
            <div class="filters">
                <button class="filter-btn active" data-filter="all">All Events</button>
                <button class="filter-btn" data-filter="academic">📚 Academic</button>
                <button class="filter-btn" data-filter="social">🎉 Social</button>
                <button class="filter-btn" data-filter="sports">⚽ Sports</button>
                <button class="filter-btn" data-filter="cultural">🎭 Cultural</button>
            </div>
        </div>
    </div>
</section>

<!-- Dynamic Announcements Section -->
<section class="announcements-section perspective-container">
    <div class="container">
        <h2 class="section-title">📢 Latest Announcements</h2>
        
        <div class="announcements-grid">
            <?php
            // TODO: Replace with database query
            // $announcements = getLatestAnnouncements(); // This will come from database
            
            // Temporary static data - will be replaced with dynamic database content
            $announcements = [
                [
                    'id' => 1,
                    'title' => 'Campus Innovation Fair Next Week',
                    'content' => 'Join us for the biggest innovation showcase of the year! Students will present their groundbreaking projects.',
                    'date' => '2025-01-28',
                    'author' => 'Dean of Students',
                    'priority' => 'high'
                ],
                [
                    'id' => 2,
                    'title' => 'Guest Speaker from Oxford University',
                    'content' => 'Renowned AI researcher Dr. Sarah Johnson will be speaking about the future of artificial intelligence.',
                    'date' => '2025-01-27',
                    'author' => 'Academic Affairs',
                    'priority' => 'medium'
                ],
                [
                    'id' => 3,
                    'title' => 'New Library Hours Extended',
                    'content' => 'Starting next week, the library will be open 24/7 during exam period to support student studies.',
                    'date' => '2025-01-26',
                    'author' => 'Library Services',
                    'priority' => 'low'
                ]
            ];
            
            foreach ($announcements as $announcement): ?>
                <div class="announcement-card card-3d priority-<?php echo $announcement['priority']; ?>">
                    <div class="announcement-header">
                        <h3 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                        <span class="announcement-date">📅 <?php echo date('M j, Y', strtotime($announcement['date'])); ?></span>
                    </div>
                    <div class="announcement-content">
                        <p><?php echo htmlspecialchars($announcement['content']); ?></p>
                    </div>
                    <div class="announcement-footer">
                        <span class="announcement-author">👤 <?php echo htmlspecialchars($announcement['author']); ?></span>
                        <span class="priority-badge priority-<?php echo $announcement['priority']; ?>">
                            <?php echo ucfirst($announcement['priority']); ?> Priority
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="view-all-announcements">
            <a href="announcements.php" class="btn-3d">📢 View All Announcements</a>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section perspective-container">
    <div class="container">
        <h2 class="section-title">✨ Why Choose Our Platform?</h2>
        
        <div class="features-grid">
            <div class="feature-card card-3d">
                <div class="feature-icon">🚀</div>
                <h3>Lightning Fast</h3>
                <p>Experience blazing-fast event browsing and registration with our optimized platform.</p>
            </div>
            
            <div class="feature-card card-3d">
                <div class="feature-icon">🔒</div>
                <h3>Secure & Safe</h3>
                <p>Your data is protected with enterprise-level security and encryption.</p>
            </div>
            
            <div class="feature-card card-3d">
                <div class="feature-icon">📱</div>
                <h3>Mobile Friendly</h3>
                <p>Access events anywhere, anytime with our responsive mobile design.</p>
            </div>
            
            <div class="feature-card card-3d">
                <div class="feature-icon">🎯</div>
                <h3>Smart Recommendations</h3>
                <p>Get personalized event suggestions based on your interests and activity.</p>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Section -->
<section class="cta-section perspective-container">
    <div class="container">
        <div class="cta-content glass-morphism">
            <h2 class="gradient-text">Ready to Transform Your Campus Experience?</h2>
            <p>Join thousands of students who are already using our platform to discover amazing events!</p>
            
            <div class="cta-buttons">
                <a href="register.php" class="btn-3d large primary">
                    🎯 Get Started Now
                </a>
                <a href="about.php" class="btn-3d large secondary">
                    📖 Learn More
                </a>
            </div>
        </div>
    </div>
</section>

<?php include '../../includes/footer.php'; ?>

<!-- Enhanced JavaScript -->
<script src="../../assets/js/parallax.js"></script>
<script src="../../assets/js/calendar.js"></script>
<script>
// Event Registration Function
function registerForEvent(eventId) {
    // Check if user is logged in
    <?php if (!isset($_SESSION['user_id'])): ?>
        alert('Please login to register for events!');
        window.location.href = 'login.php';
        return;
    <?php endif; ?>
    
    // Show registration modal or redirect
    alert(`Registering for event ${eventId}! This will be enhanced with a modal.`);
}

// Filter Events Function
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            filterEvents(filter);
        });
    });
});

function filterEvents(category) {
    // This will be enhanced to filter events dynamically
    console.log('Filtering events by:', category);
    // TODO: Implement AJAX call to filter events from database
}

// Calendar Navigation Functions
let currentDate = new Date();

function previousMonth() {
    currentDate.setMonth(currentDate.getMonth() - 1);
    updateCalendar();
}

function nextMonth() {
    currentDate.setMonth(currentDate.getMonth() + 1);
    updateCalendar();
}

function updateCalendar() {
    const monthNames = ["January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"];
    
    document.getElementById('currentMonth').textContent = 
        monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
    
    generateCalendarDays();
}

function generateCalendarDays() {
    const calendarGrid = document.getElementById('calendarGrid');
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    // Clear existing calendar
    calendarGrid.innerHTML = '';
    
    // Get first day of month and number of days
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    // Add day headers
    const dayHeaders = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    dayHeaders.forEach(day => {
        const dayHeader = document.createElement('div');
        dayHeader.className = 'calendar-day-header';
        dayHeader.textContent = day;
        calendarGrid.appendChild(dayHeader);
    });
    
    // Add empty cells for days before month starts
    for (let i = 0; i < firstDay; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day';
        calendarGrid.appendChild(emptyDay);
    }
    
    // Add days of the month
    for (let day = 1; day <= daysInMonth; day++) {
        const dayCell = document.createElement('div');
        dayCell.className = 'calendar-day';
        dayCell.textContent = day;
        calendarGrid.appendChild(dayCell);
    }
}
</script>