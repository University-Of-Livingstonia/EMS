// ===== ADVANCED PARALLAX CONTROLLER =====
class ParallaxController {
    constructor() {
        this.elements = document.querySelectorAll('[data-parallax]');
        this.floatingElements = document.querySelectorAll('.float-element');
        this.ticking = false;
        this.init();
    }
    
    init() {
        // Smooth scroll listener
        window.addEventListener('scroll', () => {
            if (!this.ticking) {
                requestAnimationFrame(() => {
                    this.updateParallax();
                    this.ticking = false;
                });
                this.ticking = true;
            }
        });
        
        this.createFloatingElements();
        this.initIntersectionObserver();
    }
    
    updateParallax() {
        const scrolled = window.pageYOffset;
        const windowHeight = window.innerHeight;
        
        this.elements.forEach(element => {
            const rect = element.getBoundingClientRect();
            const speed = element.dataset.parallax || 0.5;
            
            if (rect.bottom >= 0 && rect.top <= windowHeight) {
                const yPos = -(scrolled * speed);
                element.style.transform = `translate3d(0, ${yPos}px, 0)`;
            }
        });
    }
    
    createFloatingElements() {
        const hero = document.querySelector('.hero-3d');
        if (!hero) return;
        
        // Create floating particles
        for (let i = 0; i < 10; i++) {
            const particle = document.createElement('div');
            particle.className = 'floating-particle';
            particle.style.cssText = `
                position: absolute;
                width: ${Math.random() * 10 + 5}px;
                height: ${Math.random() * 10 + 5}px;
                background: var(--neon-blue);
                border-radius: 50%;
                left: ${Math.random() * 100}%;
                top: ${Math.random() * 100}%;
                animation: float ${Math.random() * 3 + 3}s ease-in-out infinite;
                animation-delay: ${Math.random() * 2}s;
                opacity: 0.7;
                box-shadow: 0 0 20px var(--neon-blue);
            `;
            hero.appendChild(particle);
        }
    }
    
    initIntersectionObserver() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, { threshold: 0.1 });
        
        document.querySelectorAll('.card-3d').forEach(card => {
            observer.observe(card);
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new ParallaxController();
});