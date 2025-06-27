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
        <h2 class="section-title gradient-text">🔥 Featured Events</h2>
        
        <div class="featured-grid">
            <?php
            // This will be dynamic from database later
            $featured_events = [
                [
                    'title' => 'AI & Future Tech Summit', 
                    'type' => 'Academic', 
                    'date' => '2025-02-15',
                    'time' => '10:00 AM',
                    'location' => 'Main Auditorium',
                    'description' => 'Explore the latest in AI technology and its impact on education',
                    'icon' => '🤖'
                ],
                [
                    'title' => 'Campus Music Festival', 
                    'type' => 'Social', 
                    'date' => '2025-02-20',
                    'time' => '6:00 PM',
                    'location' => 'Campus Grounds',
                    'description' => 'Join us for an evening of amazing music and entertainment',
                    'icon' => '🎵'
                ],
                [
                    'title' => 'Innovation Showcase', 
                    'type' => 'Academic', 
                    'date' => '2025-02-25',
                    'time' => '2:00 PM',
                    'location' => 'Innovation Lab',
                    'description' => 'Students present their groundbreaking projects and innovations',
                    'icon' => '💡'
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
                            <p class="event-description"><?php echo htmlspecialchars($event['description']); ?></p>
                        </div>
                        <div class="event-actions">
                            <a href="event-details.php?id=<?php echo $index + 1; ?>" class="btn-3d small">
                                View Details
                            </a>
                            <button class="btn-3d small secondary" onclick="registerForEvent(<?php echo $index + 1; ?>)">
                                Register
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Stats Section with 3D Counters -->
<section class="stats-section perspective-container" data-parallax="0.2">
    <div class="container">
        <h2 class="section-title gradient-text">📊 Platform Statistics</h2>
        
        <div class="stats-grid">
            <div class="stat-card card-3d neon-glow">
                <div class="stat-icon">🎉</div>
                <div class="stat-number" data-count="150">0</div>
                <div class="stat-label">Total Events</div>
            </div>
            
            <div class="stat-card card-3d neon-glow">
                <div class="stat-icon">👥</div>
                <div class="stat-number" data-count="2500">0</div>
                <div class="stat-label">Active Students</div>
            </div>
            
            <div class="stat-card card-3d neon-glow">
                <div class="stat-icon">🏆</div>
                <div class="stat-number" data-count="95">0</div>
                <div class="stat-label">Success Rate %</div>
            </div>
            
            <div class="stat-card card-3d neon-glow">
                <div class="stat-icon">⭐</div>
                <div class="stat-number" data-count="4.8">0</div>
                <div class="stat-label">User Rating</div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section perspective-container">
    <div class="container">
        <h2 class="section-title gradient-text">✨ Why Choose Our Platform?</h2>
        
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
<script src="assets/js/parallax.js"></script>
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

// Animated Counter for Stats
function animateCounters() {
    const counters = document.querySelectorAll('.stat-number');
    
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-count'));
        const increment = target / 100;
        let current = 0;
        
        const updateCounter = () => {
            if (current < target) {
                current += increment;
                counter.textContent = Math.ceil(current);
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target;
            }
        };
        
        updateCounter();
    });
}

// Initialize animations when stats section is visible
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            animateCounters();
            observer.unobserve(entry.target);
        }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const statsSection = document.querySelector('.stats-section');
    if (statsSection) {
        observer.observe(statsSection);
    }
});
</script>