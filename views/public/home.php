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

<!-- ENHANCED FEATURED EVENTS SECTION -->
<section class="featured-events perspective-container">
    <div class="container">
        <h2 class="section-title">
            <span class="title-icon">⭐</span>
            Featured Events
        </h2>
        
        <!-- Event Controls -->
        <div class="event-controls">
            <div class="event-filters">
                <button class="filter-btn active" data-filter="all">🎯 All Events</button>
                <button class="filter-btn" data-filter="academic">🎓 Academic</button>
                <button class="filter-btn" data-filter="sports">⚽ Sports</button>
                <button class="filter-btn" data-filter="cultural">🎭 Cultural</button>
                <button class="filter-btn" data-filter="tech">💻 Tech</button>
            </div>
            
            <div class="event-calendar">
                <label for="eventDate">📅 Filter by Date:</label>
                <input type="date" id="eventDate" class="calendar-input">
            </div>
        </div>
        
        <!-- Events Slider -->
        <div class="events-slider">
            <div class="events-container" id="eventsContainer">
                <!-- Slide 1 -->
                <div class="event-slide active" data-slide="1">
                    <div class="event-card-3d" data-category="tech">
                        <div class="event-header">
                            <div class="event-date">
                                <div>JAN</div>
                                <div>28</div>
                            </div>
                            <span class="event-category">💻 Tech</span>
                        </div>
                        <h3 class="event-title">AI Innovation Summit</h3>
                        <p class="event-description">Explore the future of artificial intelligence with industry leaders and cutting-edge demonstrations.</p>
                        <div class="event-meta">
                            <span class="event-location">📍 Tech Hub Auditorium</span>
                            <span class="event-attendees">👥 250+ Attending</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
      
<!-- REDESIGNED ANNOUNCEMENTS SECTION -->
<section class="announcements-section perspective-container">
    <div class="container">
        <h2 class="section-title">
            <span class="title-icon">📢</span>
            Latest Announcements
        </h2>
        
        <div class="announcements-slider">
            <div class="announcements-container" id="announcementsContainer">
                <?php
                // Sample announcements data
                $announcements = [
                    [
                        'id' => 1,
                        'title' => 'Campus Innovation Fair Next Week',
                        'content' => 'Join us for the biggest innovation showcase of the year! Students will present their groundbreaking projects and compete for amazing prizes.',
                        'date' => '2025-01-28',
                        'author' => 'Dean of Students',
                        'priority' => 'high',
                        'category' => 'Event',
                        'icon' => '🚀'
                    ],
                    [
                        'id' => 2,
                        'title' => 'Guest Speaker from Oxford University',
                        'content' => 'Renowned AI researcher Dr. Sarah Johnson will be speaking about the future of artificial intelligence in education.',
                        'date' => '2025-01-27',
                        'author' => 'Academic Affairs',
                        'priority' => 'medium',
                        'category' => 'Academic',
                        'icon' => '🎓'
                    ],
                    [
                        'id' => 3,
                        'title' => 'New Library Hours Extended',
                        'content' => 'Starting next week, the library will be open 24/7 during exam period to support student studies and research.',
                        'date' => '2025-01-26',
                        'author' => 'Library Services',
                        'priority' => 'low',
                        'category' => 'Service',
                        'icon' => '📚'
                    ],
                    [
                        'id' => 4,
                        'title' => 'Student Health Center Updates',
                        'content' => 'New mental health support services are now available. Free counseling sessions every Tuesday and Thursday.',
                        'date' => '2025-01-25',
                        'author' => 'Health Services',
                        'priority' => 'medium',
                        'category' => 'Health',
                        'icon' => '🏥'
                    ]
                ];
                
                foreach ($announcements as $announcement): ?>
                    <div class="announcement-card-modern priority-<?php echo $announcement['priority']; ?>">
                        <div class="announcement-header">
                            <div class="announcement-icon">
                                <?php echo $announcement['icon']; ?>
                            </div>
                            <div class="announcement-meta">
                                <span class="announcement-category"><?php echo $announcement['category']; ?></span>
                                <div class="announcement-date">
                                    📅 <?php echo date('M j, Y', strtotime($announcement['date'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <h3 class="announcement-title"><?php echo $announcement['title']; ?></h3>
                        <p class="announcement-content"><?php echo $announcement['content']; ?></p>
                        
                        <div class="announcement-footer">
                            <span class="announcement-author">👤 <?php echo $announcement['author']; ?></span>
                            <button class="announcement-action">Read More</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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