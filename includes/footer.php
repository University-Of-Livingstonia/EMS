<!-- Enhanced Footer Section -->
    <footer class="main-footer glass-morphism">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <div class="footer-links">
                        <a href="#contact">📧 Contact</a>
                        <a href="#privacy">📜 Privacy Policy</a>
                        <a href="#terms">🕹️ Terms</a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h4>Follow Us</h4>
                    <div class="footer-links social-links">
                        <a href="#instagram" target="_blank">🔗 Instagram</a>
                        <a href="#facebook" target="_blank">📘 Facebook</a>
                        <a href="#linkedin" target="_blank">💼 LinkedIn</a>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p class="copyright">Copyright © 2025 University of Livingstonia. All Rights Reserved</p>
            </div>
        </div>
    </footer>

    <!-- Enhanced JavaScript -->
    <script src="../../assets/js/parallax.js"></script>
    <script>
        // Floating Accessibility Panel
        function toggleAccessibilityMenu() {
            const menu = document.getElementById('accessibilityMenu');
            const fab = document.querySelector('.accessibility-fab');
            
            menu.classList.toggle('active');
            fab.classList.toggle('active');
        }
        
        // Mobile Menu Toggle
        function toggleMobileMenu() {
            const navLinks = document.getElementById('navLinks');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            navLinks.classList.toggle('mobile-active');
            toggle.classList.toggle('active');
        }
        
        // Theme Toggle Function
        function toggleTheme() {
            document.body.classList.toggle('light-mode');
            localStorage.setItem('theme', document.body.classList.contains('light-mode') ? 'light' : 'dark');
        }
        
        // Initialize accessibility features
        document.addEventListener('DOMContentLoaded', function() {
            // Load saved theme
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'light') {
                document.body.classList.add('light-mode');
            }
            
            const accessibilityItems = document.querySelectorAll('.menu-item');
            
            accessibilityItems.forEach(item => {
                item.addEventListener('click', function() {
                    const action = this.dataset.action;
                    
                    switch(action) {
                        case 'theme-toggle':
                            toggleTheme();
                            break;
                        case 'high-contrast':
                            document.body.classList.toggle('high-contrast');
                            break;
                        case 'large-text':
                            document.body.classList.toggle('large-text');
                            break;
                        case 'reduce-motion':
                            document.body.classList.toggle('reduce-motion');
                            break;
                        case 'focus-mode':
                            document.body.classList.toggle('focus-mode');
                            break;
                    }
                    
                    // Close menu after selection
                    toggleAccessibilityMenu();
                });
            });
            
            // Close accessibility menu when clicking outside
            document.addEventListener('click', function(e) {
                const floatingPanel = document.getElementById('floatingAccessibility');
                if (!floatingPanel.contains(e.target)) {
                    document.getElementById('accessibilityMenu').classList.remove('active');
                    document.querySelector('.accessibility-fab').classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>