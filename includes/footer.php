<!-- Footer Section -->
    <footer class="main-footer glass-morphism">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="logo-wrapper">
                        <img src="../../assets/images/logo.png" alt="UNILIA Logo" class="footer-logo">
                        <span class="gradient-text">UNILIA</span>
                    </div>
                    <p>Transforming campus events with cutting-edge technology</p>
                </div>
                
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="../../index.php">🏠 Home</a></li>
                        <li><a href="events.php">📅 Events</a></li>
                        <li><a href="about.php">ℹ️ About</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p>📧 info@unilia.edu</p>
                    <p>📞 +1 (555) 123-4567</p>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2025 UNILIA. All rights reserved. | Built with ❤️ and futuristic tech</p>
            </div>
        </div>
    </footer>

    <!-- Enhanced JavaScript -->
    <script src="../../assets/js/parallax.js"></script>
    <script>
        // Initialize accessibility features
        document.addEventListener('DOMContentLoaded', function() {
            const accessibilityButtons = document.querySelectorAll('.accessibility-options button');
            
            accessibilityButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const action = this.dataset.action;
                    
                    switch(action) {
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
                });
            });
        });
    </script>
</body>
</html>