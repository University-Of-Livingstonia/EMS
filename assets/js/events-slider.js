// ===== ENHANCED EVENTS SLIDER & FILTER SYSTEM =====
class EventsManager {
    constructor() {
        this.currentSlide = 1;
        this.totalSlides = 4;
        this.isAnimating = false;
        this.autoSlideInterval = null;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.startAutoSlide();
        this.initFilters();
        this.initDateFilter();
    }
    
    bindEvents() {
        // Slider navigation
        document.getElementById('prevBtn').addEventListener('click', () => this.prevSlide());
        document.getElementById('nextBtn').addEventListener('click', () => this.nextSlide());
        
        // Slide numbers
        document.querySelectorAll('.slide-number').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const slideNum = parseInt(e.target.dataset.slide);
                this.goToSlide(slideNum);
            });
        });
        
        // Touch/swipe support
        this.initTouchEvents();
        
        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') this.prevSlide();
            if (e.key === 'ArrowRight') this.nextSlide();
        });
    }
    
    initTouchEvents() {
        const container = document.getElementById('eventsContainer');
        let startX = 0;
        let endX = 0;
        
        container.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
        });
        
        container.addEventListener('touchend', (e) => {
            endX = e.changedTouches[0].clientX;
            this.handleSwipe();
        });
        
        // Mouse drag support
        let isDragging = false;
        
        container.addEventListener('mousedown', (e) => {
            isDragging = true;
            startX = e.clientX;
            container.style.cursor = 'grabbing';
        });
        
        container.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            e.preventDefault();
        });
        
        container.addEventListener('mouseup', (e) => {
            if (!isDragging) return;
            isDragging = false;
            endX = e.clientX;
            container.style.cursor = 'grab';
            this.handleSwipe();
        });
        
        container.addEventListener('mouseleave', () => {
            isDragging = false;
            container.style.cursor = 'grab';
        });
    }
    
    handleSwipe() {
        const threshold = 50;
        const diff = startX - endX;
        
        if (Math.abs(diff) > threshold) {
            if (diff > 0) {
                this.nextSlide();
            } else {
                this.prevSlide();
            }
        }
    }
    
    goToSlide(slideNum) {
        if (this.isAnimating || slideNum === this.currentSlide) return;
        
        this.isAnimating = true;
        this.currentSlide = slideNum;
        
        // Update container transform
        const container = document.getElementById('eventsContainer');
        const translateX = -(slideNum - 1) * 100;
        container.style.transform = `translateX(${translateX}%)`;
        
        // Update active states
        this.updateSlideStates();
        this.updateNavigationStates();
        
        // Add animation class
        container.classList.add('sliding');
        
        setTimeout(() => {
            this.isAnimating = false;
            container.classList.remove('sliding');
        }, 500);
        
        // Restart auto slide
        this.restartAutoSlide();
    }
    
    nextSlide() {
        const nextSlide = this.currentSlide >= this.totalSlides ? 1 : this.currentSlide + 1;
        this.goToSlide(nextSlide);
    }
    
    prevSlide() {
        const prevSlide = this.currentSlide <= 1 ? this.totalSlides : this.currentSlide - 1;
        this.goToSlide(prevSlide);
    }
    
    updateSlideStates() {
        // Update slide visibility
        document.querySelectorAll('.event-slide').forEach((slide, index) => {
            slide.classList.toggle('active', index + 1 === this.currentSlide);
        });
        
        // Update slide numbers
        document.querySelectorAll('.slide-number').forEach((btn, index) => {
            btn.classList.toggle('active', index + 1 === this.currentSlide);
        });
    }
    
    updateNavigationStates() {
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        
        // Enable/disable buttons (optional - can be removed for infinite scroll)
        prevBtn.disabled = false;
        nextBtn.disabled = false;
    }
    
    startAutoSlide() {
        this.autoSlideInterval = setInterval(() => {
            if (!this.isAnimating) {
                this.nextSlide();
            }
        }, 5000); // Change slide every 5 seconds
    }
    
    restartAutoSlide() {
        clearInterval(this.autoSlideInterval);
        this.startAutoSlide();
    }
    
    stopAutoSlide() {
        clearInterval(this.autoSlideInterval);
    }
    
    // ===== FILTER SYSTEM =====
    initFilters() {
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const filter = e.target.dataset.filter;
                this.applyFilter(filter);
                
                // Update active filter button
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
            });
        });
    }
    
    applyFilter(filter) {
        const allCards = document.querySelectorAll('.event-card-3d');
        
        allCards.forEach(card => {
            const category = card.dataset.category;
            const shouldShow = filter === 'all' || category === filter;
            
            if (shouldShow) {
                card.style.display = 'block';
                card.classList.add('fade-in-up');
                setTimeout(() => card.classList.remove('fade-in-up'), 600);
            } else {
                card.style.display = 'none';
            }
        });
        
        // Update slide visibility
        this.updateSlideVisibility();
    }
    
    initDateFilter() {
        const dateInput = document.getElementById('eventDate');
        dateInput.addEventListener('change', (e) => {
            this.applyDateFilter(e.target.value);
        });
    }
    
    applyDateFilter(selectedDate) {
        if (!selectedDate) {
            // Show all events if no date selected
            document.querySelectorAll('.event-card-3d').forEach(card => {
                card.style.display = 'block';
            });
            this.updateSlideVisibility();
            return;
        }
        
        const allCards = document.querySelectorAll('.event-card-3d');
        
        allCards.forEach(card => {
            const eventDate = card.dataset.date;
            const shouldShow = eventDate === selectedDate;
            
            if (shouldShow) {
                card.style.display = 'block';
                card.classList.add('fade-in-up');
                setTimeout(() => card.classList.remove('fade-in-up'), 600);
            } else {
                card.style.display = 'none';
            }
        });
        
        this.updateSlideVisibility();
    }
    
    updateSlideVisibility() {
        document.querySelectorAll('.event-slide').forEach(slide => {
            const visibleCards = slide.querySelectorAll('.event-card-3d[style*="block"], .event-card-3d:not([style*="none"])');
            slide.style.display = visibleCards.length > 0 ? 'grid' : 'none';
        });
    }
    
    // ===== ACCESSIBILITY FEATURES =====
    initAccessibility() {
        // Add ARIA labels
        document.getElementById('prevBtn').setAttribute('aria-label', 'Previous slide');
        document.getElementById('nextBtn').setAttribute('aria-label', 'Next slide');
        
        // Add keyboard navigation for cards
        document.querySelectorAll('.event-card-3d').forEach((card, index) => {
            card.setAttribute('tabindex', '0');
            card.setAttribute('role', 'button');
            card.setAttribute('aria-label', `Event card ${index + 1}`);
            
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.openEventDetails(card);
                }
            });
        });
    }
    
    openEventDetails(card) {
        // Add event details modal functionality here
        const title = card.querySelector('.event-title').textContent;
        const description = card.querySelector('.event-description').textContent;
        
        // For now, just show an alert (replace with modal later)
        alert(`Event: ${title}\n\nDescription: ${description}`);
    }
    
    // ===== RESPONSIVE HANDLING =====
    handleResize() {
        // Recalculate positions on window resize
        const container = document.getElementById('eventsContainer');
        const translateX = -(this.currentSlide - 1) * 100;
        container.style.transform = `translateX(${translateX}%)`;
    }
}

// ===== ANNOUNCEMENTS SLIDER =====
class AnnouncementsSlider {
    constructor() {
        this.currentIndex = 0;
        this.autoSlideInterval = null;
        this.init();
    }
    
    init() {
        this.startAutoSlide();
        this.initHoverPause();
    }
    
    startAutoSlide() {
        const container = document.getElementById('announcementsContainer');
        const cards = container.querySelectorAll('.announcement-card-modern');
        
        if (cards.length <= 3) return; // Don't slide if 3 or fewer cards
        
        this.autoSlideInterval = setInterval(() => {
            this.currentIndex = (this.currentIndex + 1) % (cards.length - 2);
            const translateX = -this.currentIndex * 370; // Card width + gap
            container.style.transform = `translateX(${translateX}px)`;
        }, 3000);
    }
    
    initHoverPause() {
        const container = document.getElementById('announcementsContainer');
        
        container.addEventListener('mouseenter', () => {
            clearInterval(this.autoSlideInterval);
        });
        
        container.addEventListener('mouseleave', () => {
            this.startAutoSlide();
        });
    }
}

// ===== THEME TOGGLE ENHANCEMENT =====
class ThemeManager {
    constructor() {
        this.currentTheme = localStorage.getItem('theme') || 'dark';
        this.init();
    }
    
    init() {
        this.applyTheme(this.currentTheme);
        this.bindToggleEvent();
    }
    
    bindToggleEvent() {
        // Assuming you have a theme toggle button
        const themeToggle = document.querySelector('[data-theme-toggle]');
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                this.toggleTheme();
            });
        }
    }
    
    toggleTheme() {
        this.currentTheme = this.currentTheme === 'dark' ? 'light' : 'dark';
        this.applyTheme(this.currentTheme);
        localStorage.setItem('theme', this.currentTheme);
    }
    
    applyTheme(theme) {
        document.body.classList.toggle('light-mode', theme === 'light');
        
        // Update theme toggle icon if exists
        const themeToggle = document.querySelector('[data-theme-toggle]');
        if (themeToggle) {
            themeToggle.textContent = theme === 'dark' ? '☀️' : '🌙';
        }
    }
}

// ===== ACCESSIBILITY PANEL =====
class AccessibilityPanel {
    constructor() {
        this.isOpen = false;
        this.settings = {
            highContrast: false,
            largeText: false,
            reduceMotion: false,
            focusMode: false
        };
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadSettings();
    }
    
    bindEvents() {
        const fab = document.querySelector('.accessibility-fab');
        const menu = document.querySelector('.accessibility-menu');
        
        if (fab) {
            fab.addEventListener('click', () => {
                this.toggleMenu();
            });
        }
        
        // Bind menu item events
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const action = e.currentTarget.dataset.action;
                this.handleAction(action);
            });
        });
    }
    
    toggleMenu() {
        const fab = document.querySelector('.accessibility-fab');
        const menu = document.querySelector('.accessibility-menu');
        
        this.isOpen = !this.isOpen;
        
        fab.classList.toggle('active', this.isOpen);
        menu.classList.toggle('active', this.isOpen);
    }
    
    handleAction(action) {
        switch(action) {
            case 'high-contrast':
                this.toggleHighContrast();
                break;
            case 'large-text':
                this.toggleLargeText();
                break;
            case 'reduce-motion':
                this.toggleReduceMotion();
                break;
            case 'focus-mode':
                this.toggleFocusMode();
                break;
        }
        
        this.saveSettings();
    }
    
    toggleHighContrast() {
        this.settings.highContrast = !this.settings.highContrast;
        document.body.classList.toggle('high-contrast', this.settings.highContrast);
    }
    
    toggleLargeText() {
        this.settings.largeText = !this.settings.largeText;
        document.body.classList.toggle('large-text', this.settings.largeText);
    }
    
    toggleReduceMotion() {
        this.settings.reduceMotion = !this.settings.reduceMotion;
        document.body.classList.toggle('reduce-motion', this.settings.reduceMotion);
    }
    
    toggleFocusMode() {
        this.settings.focusMode = !this.settings.focusMode;
        document.body.classList.toggle('focus-mode', this.settings.focusMode);
    }
    
    saveSettings() {
        localStorage.setItem('accessibility-settings', JSON.stringify(this.settings));
    }
    
    loadSettings() {
        const saved = localStorage.getItem('accessibility-settings');
        if (saved) {
            this.settings = JSON.parse(saved);
            
            // Apply saved settings
            if (this.settings.highContrast) this.toggleHighContrast();
            if (this.settings.largeText) this.toggleLargeText();
            if (this.settings.reduceMotion) this.toggleReduceMotion();
            if (this.settings.focusMode) this.toggleFocusMode();
        }
    }
}

// ===== INITIALIZE EVERYTHING =====
document.addEventListener('DOMContentLoaded', () => {
    // Initialize all components
    const eventsManager = new EventsManager();
    const announcementsSlider = new AnnouncementsSlider();
    const themeManager = new ThemeManager();
    const accessibilityPanel = new AccessibilityPanel();
    
    // Handle window resize
    window.addEventListener('resize', () => {
        eventsManager.handleResize();
    });
    
    // Add smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
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
    
    // Add loading animation removal
    window.addEventListener('load', () => {
        document.body.classList.add('loaded');
    });
    
    console.log('🔥 EMS Phase 1 - All Systems Initialized Successfully! 🚀');
});

// ===== PERFORMANCE OPTIMIZATION =====
// Lazy loading for images
const observerOptions = {
    threshold: 0.1,
    rootMargin: '50px'
};

const imageObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const img = entry.target;
            if (img.dataset.src) {
                img.src = img.dataset.src;
                img.classList.add('loaded');
                imageObserver.unobserve(img);
            }
        }
    });
}, observerOptions);

// Observe all images with data-src
document.querySelectorAll('img[data-src]').forEach(img => {
    imageObserver.observe(img);
});