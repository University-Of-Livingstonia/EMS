/**
 * 🎯 Organizer Dashboard JavaScript
 * EMS - Event Management System
 * Enhanced functionality for organizer dashboard
 */

class OrganizerDashboard {
    constructor() {
        this.sidebar = document.querySelector('.organizer-sidebar');
        this.main = document.querySelector('.organizer-main');
        this.sidebarToggle = document.querySelector('.sidebar-toggle');
        this.mobileToggle = document.querySelector('.mobile-toggle');
        this.sidebarOverlay = document.querySelector('.sidebar-overlay');
        this.sidebarClose = document.querySelector('.sidebar-close');
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.initializeComponents();
        this.handleResponsive();
        this.loadUserPreferences();
    }
    
    bindEvents() {
        // Sidebar toggle events
        if (this.sidebarToggle) {
            this.sidebarToggle.addEventListener('click', () => this.toggleSidebar());
        }
        
        if (this.mobileToggle) {
            this.mobileToggle.addEventListener('click', () => this.showMobileSidebar());
        }
        
        if (this.sidebarClose) {
            this.sidebarClose.addEventListener('click', () => this.hideMobileSidebar());
        }
        
        if (this.sidebarOverlay) {
            this.sidebarOverlay.addEventListener('click', () => this.hideMobileSidebar());
        }
        
        // Window resize handler
        window.addEventListener('resize', () => this.handleResponsive());
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboardShortcuts(e));
        
        // Form enhancements
        this.enhanceForms();
        
        // Alert auto-dismiss
        this.initAlerts();
        
        // Search functionality
        this.initSearch();
        
        // Chart initialization
        this.initCharts();
    }
    
    toggleSidebar() {
        if (this.sidebar && this.main) {
            this.sidebar.classList.toggle('collapsed');
            this.main.classList.toggle('expanded');
            
            // Save preference
            const isCollapsed = this.sidebar.classList.contains('collapsed');
            localStorage.setItem('organizer-sidebar-collapsed', isCollapsed);
            
            // Trigger resize event for charts
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 300);
        }
    }
    
    showMobileSidebar() {
        if (this.sidebar && this.sidebarOverlay) {
            this.sidebar.classList.add('show');
            this.sidebarOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    hideMobileSidebar() {
        if (this.sidebar && this.sidebarOverlay) {
            this.sidebar.classList.remove('show');
            this.sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    handleResponsive() {
        const isMobile = window.innerWidth <= 768;
        
        if (isMobile) {
            this.hideMobileSidebar();
        }
        
        // Update chart sizes
        this.updateChartSizes();
    }
    
    loadUserPreferences() {
        // Load sidebar state
        const sidebarCollapsed = localStorage.getItem('organizer-sidebar-collapsed');
        if (sidebarCollapsed === 'true' && this.sidebar && this.main) {
            this.sidebar.classList.add('collapsed');
            this.main.classList.add('expanded');
        }
        
        // Load theme preference
        const theme = localStorage.getItem('organizer-theme');
        if (theme) {
            document.body.className = document.body.className.replace(/organizer-theme-\w+/g, '');
            document.body.classList.add(theme);
        }
    }
    
    handleKeyboardShortcuts(e) {
        // Ctrl/Cmd + B: Toggle sidebar
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            this.toggleSidebar();
        }
        
        // Escape: Close mobile sidebar
        if (e.key === 'Escape') {
            this.hideMobileSidebar();
        }
        
        // Ctrl/Cmd + K: Focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('.organizer-search-input');
            if (searchInput) {
                searchInput.focus();
            }
        }
    }
    
    initializeComponents() {
        // Initialize tooltips
        this.initTooltips();
        
        // Initialize modals
        this.initModals();
        
        // Initialize dropdowns
        this.initDropdowns();
        
        // Initialize date pickers
        this.initDatePickers();
        
        // Initialize file uploads
        this.initFileUploads();
        
        // Initialize data tables
        this.initDataTables();
    }
    
    enhanceForms() {
        // Auto-resize textareas
        document.querySelectorAll('textarea.organizer-form-control').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
        
        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => this.validateForm(e, form));
        });
        
        // Real-time validation
        document.querySelectorAll('.organizer-form-control').forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', () => this.clearFieldError(input));
        });
    }
    
    validateForm(e, form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!this.validateField(field)) {
                isValid = false;
            }
        });
                
        if (!isValid) {
            e.preventDefault();
            this.showAlert('Please fix the errors below', 'danger');
            
            // Focus first invalid field
            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.focus();
            }
        }
    }
    
    validateField(field) {
        const value = field.value.trim();
        const type = field.type;
        let isValid = true;
        let errorMessage = '';
        
        // Required validation
        if (field.hasAttribute('required') && !value) {
            isValid = false;
            errorMessage = 'This field is required';
        }
        
        // Email validation
        else if (type === 'email' && value && !this.isValidEmail(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address';
        }
        
        // Phone validation
        else if (type === 'tel' && value && !this.isValidPhone(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid phone number';
        }
        
        // URL validation
        else if (type === 'url' && value && !this.isValidURL(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid URL';
        }
        
        // Number validation
        else if (type === 'number' && value) {
            const min = field.getAttribute('min');
            const max = field.getAttribute('max');
            const numValue = parseFloat(value);
            
            if (min && numValue < parseFloat(min)) {
                isValid = false;
                errorMessage = `Value must be at least ${min}`;
            } else if (max && numValue > parseFloat(max)) {
                isValid = false;
                errorMessage = `Value must not exceed ${max}`;
            }
        }
        
        // Date validation
        else if (type === 'date' && value) {
            const dateValue = new Date(value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (field.classList.contains('future-date') && dateValue < today) {
                isValid = false;
                errorMessage = 'Date must be in the future';
            }
        }
        
        this.updateFieldValidation(field, isValid, errorMessage);
        return isValid;
    }
    
    updateFieldValidation(field, isValid, errorMessage) {
        field.classList.remove('is-valid', 'is-invalid');
        
        // Remove existing error message
        const existingError = field.parentNode.querySelector('.organizer-form-error');
        if (existingError) {
            existingError.remove();
        }
        
        if (isValid) {
            field.classList.add('is-valid');
        } else {
            field.classList.add('is-invalid');
            
            // Add error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'organizer-form-error';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${errorMessage}`;
            field.parentNode.appendChild(errorDiv);
        }
    }
    
    clearFieldError(field) {
        field.classList.remove('is-invalid');
        const errorDiv = field.parentNode.querySelector('.organizer-form-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    
    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    isValidPhone(phone) {
        return /^[+]?[0-9\s\-\(\)]{10,}$/.test(phone);
    }
    
    isValidURL(url) {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }
    
    initAlerts() {
        // Auto-dismiss alerts after 5 seconds
        document.querySelectorAll('.organizer-alert').forEach(alert => {
            const closeBtn = alert.querySelector('.organizer-alert-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.dismissAlert(alert));
            }
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                this.dismissAlert(alert);
            }, 5000);
        });
    }
    
    dismissAlert(alert) {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => {
            alert.remove();
        }, 300);
    }
    
    showAlert(message, type = 'info', duration = 5000) {
        const alertContainer = document.querySelector('.alert-container') || document.body;
        
        const alert = document.createElement('div');
        alert.className = `organizer-alert organizer-alert-${type} fade-in`;
        alert.innerHTML = `
            <div class="organizer-alert-icon">
                <i class="fas fa-${this.getAlertIcon(type)}"></i>
            </div>
            <div class="organizer-alert-content">
                <div class="organizer-alert-message">${message}</div>
            </div>
            <button class="organizer-alert-close">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        alertContainer.appendChild(alert);
        
        // Bind close event
        const closeBtn = alert.querySelector('.organizer-alert-close');
        closeBtn.addEventListener('click', () => this.dismissAlert(alert));
        
        // Auto-dismiss
        if (duration > 0) {
            setTimeout(() => {
                this.dismissAlert(alert);
            }, duration);
        }
        
        return alert;
    }
    
    getAlertIcon(type) {
        const icons = {
            success: 'check-circle',
            danger: 'exclamation-triangle',
            warning: 'exclamation-circle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }
    
    initSearch() {
        const searchInputs = document.querySelectorAll('.organizer-search-input');
        
        searchInputs.forEach(input => {
            const clearBtn = input.parentNode.querySelector('.organizer-search-clear');
            
            if (clearBtn) {
                clearBtn.addEventListener('click', () => {
                    input.value = '';
                    input.focus();
                    this.performSearch(input, '');
                });
            }
            
            // Debounced search
            let searchTimeout;
            input.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.performSearch(input, input.value);
                }, 300);
            });
        });
    }
    
    performSearch(input, query) {
        const targetTable = input.dataset.target;
        if (targetTable) {
            this.searchTable(targetTable, query);
        }
        
        // Trigger custom search event
        input.dispatchEvent(new CustomEvent('organizer-search', {
            detail: { query }
        }));
    }
    
    searchTable(tableSelector, query) {
        const table = document.querySelector(tableSelector);
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr');
        const lowerQuery = query.toLowerCase();
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const shouldShow = !query || text.includes(lowerQuery);
            
            row.style.display = shouldShow ? '' : 'none';
        });
        
        // Show "no results" message if needed
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        this.toggleNoResultsMessage(table, visibleRows.length === 0 && query);
    }
    
    toggleNoResultsMessage(table, show) {
        let noResultsRow = table.querySelector('.no-results-row');
        
        if (show && !noResultsRow) {
            const tbody = table.querySelector('tbody');
            const colCount = table.querySelectorAll('thead th').length;
            
            noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row';
            noResultsRow.innerHTML = `
                <td colspan="${colCount}" class="text-center p-4">
                    <div class="empty-state">
                        <i class="fas fa-search empty-state-icon"></i>
                        <h5>No results found</h5>
                        <p>Try adjusting your search terms</p>
                    </div>
                </td>
            `;
            
            tbody.appendChild(noResultsRow);
        } else if (!show && noResultsRow) {
            noResultsRow.remove();
        }
    }
    
    initCharts() {
        // Initialize Chart.js charts
        this.charts = {};
        
        // Revenue chart
        this.initRevenueChart();
        
        // Event stats chart
        this.initEventStatsChart();
        
        // Registration timeline chart
        this.initRegistrationChart();
    }
    
    initRevenueChart() {
        const canvas = document.getElementById('revenueChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        
        this.charts.revenue = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Revenue',
                    data: [],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'MWK ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        this.loadRevenueData();
    }
    
    initEventStatsChart() {
        const canvas = document.getElementById('eventStatsChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        
        this.charts.eventStats = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Pending', 'Draft', 'Rejected'],
                datasets: [{
                    data: [0, 0, 0, 0],
                    backgroundColor: [
                        '#4CAF50',
                        '#FF9800',
                        '#9E9E9E',
                        '#f44336'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        this.loadEventStatsData();
    }
    
    initRegistrationChart() {
        const canvas = document.getElementById('registrationChart');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        
        this.charts.registration = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Registrations',
                    data: [],
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: '#667eea',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        
        this.loadRegistrationData();
    }
    
    async loadRevenueData() {
        try {
            const response = await fetch('/api/organizer/revenue-data.php');
            const data = await response.json();
            
            if (data.success && this.charts.revenue) {
                this.charts.revenue.data.labels = data.labels;
                this.charts.revenue.data.datasets[0].data = data.values;
                this.charts.revenue.update();
            }
        } catch (error) {
            console.error('Error loading revenue data:', error);
        }
    }
    
    async loadEventStatsData() {
        try {
            const response = await fetch('/api/organizer/event-stats.php');
            const data = await response.json();
            
            if (data.success && this.charts.eventStats) {
                this.charts.eventStats.data.datasets[0].data = [
                    data.approved || 0,
                    data.pending || 0,
                    data.draft || 0,
                    data.rejected || 0
                ];
                this.charts.eventStats.update();
            }
        } catch (error) {
            console.error('Error loading event stats:', error);
        }
    }
    
    async loadRegistrationData() {
        try {
            const response = await fetch('/api/organizer/registration-data.php');
            const data = await response.json();
            
            if (data.success && this.charts.registration) {
                this.charts.registration.data.labels = data.labels;
                this.charts.registration.data.datasets[0].data = data.values;
                this.charts.registration.update();
            }
        } catch (error) {
            console.error('Error loading registration data:', error);
        }
    }
    
    updateChartSizes() {
        Object.values(this.charts).forEach(chart => {
            if (chart) {
                chart.resize();
            }
        });
    }
    
    initTooltips() {
        // Simple tooltip implementation
        document.querySelectorAll('[data-tooltip]').forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target, e.target.dataset.tooltip);
            });
            
            element.addEventListener('mouseleave', () => {
                this.hideTooltip();
            });
        });
    }
    
    showTooltip(element, text) {
        const tooltip = document.createElement('div');
        tooltip.className = 'organizer-tooltip';
        tooltip.textContent = text;
        document.body.appendChild(tooltip);
        
        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
        
        this.currentTooltip = tooltip;
    }
    
    hideTooltip() {
        if (this.currentTooltip) {
            this.currentTooltip.remove();
            this.currentTooltip = null;
        }
    }
    
    initModals() {
        // Modal functionality
        document.querySelectorAll('[data-modal]').forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                const modalId = trigger.dataset.modal;
                this.showModal(modalId);
            });
        });
        
        document.querySelectorAll('.modal-close, .modal-overlay').forEach(element => {
            element.addEventListener('click', (e) => {
                if (e.target === element) {
                    this.hideModal();
                }
            });
        });
        
        // Escape key to close modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.hideModal();
            }
        });
    }
    
    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Focus first input
            const firstInput = modal.querySelector('input, textarea, select');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        }
    }
    
    hideModal() {
        const activeModal = document.querySelector('.modal.active');
        if (activeModal) {
            activeModal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    initDropdowns() {
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const dropdown = toggle.closest('.dropdown');
                const isActive = dropdown.classList.contains('active');
                
                // Close all dropdowns
                document.querySelectorAll('.dropdown.active').forEach(d => {
                    d.classList.remove('active');
                });
                
                // Toggle current dropdown
                if (!isActive) {
                    dropdown.classList.add('active');
                }
            });
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', () => {
            document.querySelectorAll('.dropdown.active').forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        });
    }
    
    initDatePickers() {
        // Enhanced date picker functionality
        document.querySelectorAll('input[type="date"], input[type="datetime-local"]').forEach(input => {
            // Set minimum date to today for future events
            if (input.classList.contains('future-date')) {
                const today = new Date();
                const todayString = today.toISOString().split('T')[0];
                input.min = todayString;
            }
            
            // Date validation
            input.addEventListener('change', () => {
                this.validateDateInput(input);
            });
        });
    }
    
    validateDateInput(input) {
        const value = input.value;
        if (!value) return;
        
        const selectedDate = new Date(value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (input.classList.contains('future-date') && selectedDate < today) {
            this.updateFieldValidation(input, false, 'Date must be in the future');
            return false;
        }
        
        // Check if end date is after start date
        const startDateInput = document.querySelector('input[name="start_date"], input[name="start_datetime"]');
        const endDateInput = document.querySelector('input[name="end_date"], input[name="end_datetime"]');
        
        if (startDateInput && endDateInput && startDateInput.value && endDateInput.value) {
            const startDate = new Date(startDateInput.value);
            const endDate = new Date(endDateInput.value);
            
            if (endDate <= startDate) {
                this.updateFieldValidation(endDateInput, false, 'End date must be after start date');
                return false;
            }
        }
        
        this.updateFieldValidation(input, true, '');
        return true;
    }
    
    initFileUploads() {
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', (e) => {
                this.handleFileUpload(e.target);
            });
        });
        
        // Drag and drop functionality
        document.querySelectorAll('.file-drop-zone').forEach(zone => {
            zone.addEventListener('dragover', (e) => {
                e.preventDefault();
                zone.classList.add('drag-over');
            });
            
            zone.addEventListener('dragleave', () => {
                zone.classList.remove('drag-over');
            });
            
            zone.addEventListener('drop', (e) => {
                e.preventDefault();
                zone.classList.remove('drag-over');
                
                const files = e.dataTransfer.files;
                const input = zone.querySelector('input[type="file"]');
                if (input && files.length > 0) {
                    input.files = files;
                    this.handleFileUpload(input);
                }
            });
        });
    }
    
    handleFileUpload(input) {
        const files = input.files;
        if (!files.length) return;
        
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        
        Array.from(files).forEach(file => {
            // Size validation
            if (file.size > maxSize) {
                this.showAlert(`File "${file.name}" is too large. Maximum size is 5MB.`, 'danger');
                input.value = '';
                return;
            }
            
            // Type validation
            if (!allowedTypes.includes(file.type)) {
                this.showAlert(`File "${file.name}" is not a supported format.`, 'danger');
                input.value = '';
                return;
            }
            
            // Show preview for images
            if (file.type.startsWith('image/')) {
                this.showImagePreview(file, input);
            }
        });
    }
    
    showImagePreview(file, input) {
        const reader = new FileReader();
        reader.onload = (e) => {
            let preview = input.parentNode.querySelector('.image-preview');
            if (!preview) {
                preview = document.createElement('div');
                preview.className = 'image-preview';
                input.parentNode.appendChild(preview);
            }
            
            preview.innerHTML = `
                <img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 8px;">
                <button type="button" class="btn btn-sm btn-danger mt-2" onclick="this.parentNode.remove(); document.querySelector('input[type=file]').value='';">
                    <i class="fas fa-trash"></i> Remove
                </button>
            `;
        };
        reader.readAsDataURL(file);
    }
    
    initDataTables() {
        // Enhanced table functionality
        document.querySelectorAll('.organizer-table').forEach(table => {
            this.enhanceTable(table);
        });
    }
    
    enhanceTable(table) {
        // Add sorting functionality
        const headers = table.querySelectorAll('th[data-sort]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.innerHTML += ' <i class="fas fa-sort sort-icon"></i>';
            
            header.addEventListener('click', () => {
                this.sortTable(table, header.dataset.sort, header);
            });
        });
        
        // Add row selection
        const checkboxes = table.querySelectorAll('input[type="checkbox"]');
        if (checkboxes.length > 0) {
            this.initTableSelection(table);
        }
    }
    
    sortTable(table, column, header) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const isAscending = !header.classList.contains('sort-asc');
        
        // Clear all sort classes
        table.querySelectorAll('th').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
            const icon = th.querySelector('.sort-icon');
            if (icon) {
                icon.className = 'fas fa-sort sort-icon';
            }
        });
        
        // Set current sort
        header.classList.add(isAscending ? 'sort-asc' : 'sort-desc');
        const icon = header.querySelector('.sort-icon');
        if (icon) {
            icon.className = `fas fa-sort-${isAscending ? 'up' : 'down'} sort-icon`;
        }
        
        // Sort rows
        rows.sort((a, b) => {
            const aValue = this.getCellValue(a, column);
            const bValue = this.getCellValue(b, column);
            
            if (aValue < bValue) return isAscending ? -1 : 1;
            if (aValue > bValue) return isAscending ? 1 : -1;
            return 0;
        });
        
        // Reorder rows
        rows.forEach(row => tbody.appendChild(row));
    }
    
    getCellValue(row, column) {
        const cell = row.querySelector(`[data-sort="${column}"]`) || row.cells[parseInt(column)];
        if (!cell) return '';
        
        const value = cell.textContent.trim();
        
        // Try to parse as number
        if (!isNaN(value) && value !== '') {
            return parseFloat(value);
        }
        
        // Try to parse as date
        const date = new Date(value);
        if (!isNaN(date.getTime())) {
            return date.getTime();
        }
        
        return value.toLowerCase();
    }
    
    initTableSelection(table) {
        const selectAllCheckbox = table.querySelector('thead input[type="checkbox"]');
        const rowCheckboxes = table.querySelectorAll('tbody input[type="checkbox"]');
        
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', () => {
                rowCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
                this.updateSelectionActions(table);
            });
        }
        
        rowCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                const checkedCount = table.querySelectorAll('tbody input[type="checkbox"]:checked').length;
                const totalCount = rowCheckboxes.length;
                
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = checkedCount === totalCount;
                    selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < totalCount;
                }
                
                this.updateSelectionActions(table);
            });
        });
    }
    
    updateSelectionActions(table) {
        const selectedCount = table.querySelectorAll('tbody input[type="checkbox"]:checked').length;
        const actionBar = document.querySelector('.selection-actions');
        
        if (actionBar) {
            if (selectedCount > 0) {
                actionBar.style.display = 'flex';
                actionBar.querySelector('.selected-count').textContent = selectedCount;
            } else {
                actionBar.style.display = 'none';
            }
        }
    }
    
    // Utility methods
    formatCurrency(amount, currency = 'MWK') {
        return new Intl.NumberFormat('en-MW', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 0
        }).format(amount);
    }
    
    formatDate(date, options = {}) {
        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        };
        
        return new Intl.DateTimeFormat('en-MW', { ...defaultOptions, ...options }).format(new Date(date));
    }
    
    formatDateTime(datetime, options = {}) {
        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        
        return new Intl.DateTimeFormat('en-MW', { ...defaultOptions, ...options }).format(new Date(datetime));
    }
    
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }
    
    // API helper methods
    async apiRequest(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        try {
            const response = await fetch(url, { ...defaultOptions, ...options });
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API Request Error:', error);
            this.showAlert(error.message || 'An error occurred', 'danger');
            throw error;
        }
    }
    
    async deleteEvent(eventId) {
        if (!confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
            return;
        }
        
        try {
            const response = await this.apiRequest(`/api/organizer/delete-event.php`, {
                method: 'POST',
                body: JSON.stringify({ event_id: eventId })
            });
            
            if (response.success) {
                this.showAlert('Event deleted successfully', 'success');
                
                // Remove row from table
                const row = document.querySelector(`tr[data-event-id="${eventId}"]`);
                if (row) {
                    row.remove();
                }
                
                // Refresh charts
                this.refreshCharts();
            }
        } catch (error) {
            // Error already handled in apiRequest
        }
    }
    
    async updateEventStatus(eventId, status) {
        try {
            const response = await this.apiRequest(`/api/organizer/update-event-status.php`, {
                method: 'POST',
                body: JSON.stringify({ event_id: eventId, status: status })
            });
            
            if (response.success) {
                this.showAlert(`Event ${status} successfully`, 'success');
                
                // Update status badge in table
                const statusBadge = document.querySelector(`tr[data-event-id="${eventId}"] .status-badge`);
                if (statusBadge) {
                    statusBadge.className = `organizer-badge organizer-badge-${status}`;
                    statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                }
                
                // Refresh charts
                this.refreshCharts();
            }
        } catch (error) {
            // Error already handled in apiRequest
        }
    }
    
    async exportData(type, format = 'csv') {
        try {
            const response = await fetch(`/api/organizer/export.php?type=${type}&format=${format}`);
            
            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${type}_export_${new Date().toISOString().split('T')[0]}.${format}`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                this.showAlert('Export completed successfully', 'success');
            } else {
                throw new Error('Export failed');
            }
        } catch (error) {
            this.showAlert('Export failed. Please try again.', 'danger');
        }
    }
    
    refreshCharts() {
        // Reload all chart data
        this.loadRevenueData();
        this.loadEventStatsData();
        this.loadRegistrationData();
    }
    
    // Theme management
    setTheme(theme) {
        document.body.className = document.body.className.replace(/organizer-theme-\w+/g, '');
        document.body.classList.add(`organizer-theme-${theme}`);
        localStorage.setItem('organizer-theme', `organizer-theme-${theme}`);
        
        this.showAlert(`Theme changed to ${theme}`, 'success', 2000);
    }
    
    // Notification management
    async loadNotifications() {
        try {
            const response = await this.apiRequest('/api/organizer/notifications.php');
            
            if (response.success) {
                this.updateNotificationBadge(response.unread_count);
                this.renderNotifications(response.notifications);
            }
        } catch (error) {
            console.error('Failed to load notifications:', error);
        }
    }
    
    updateNotificationBadge(count) {
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'block';
            } else {
                badge.style.display = 'none';
            }
        }
    }
    
    renderNotifications(notifications) {
        const container = document.querySelector('.notifications-list');
        if (!container) return;
        
        if (notifications.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-bell-slash empty-state-icon"></i>
                    <h5>No notifications</h5>
                    <p>You're all caught up!</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = notifications.map(notification => `
            <div class="notification-item ${notification.is_read ? '' : 'unread'}" data-id="${notification.id}">
                <div class="notification-icon">
                    <i class="fas fa-${this.getNotificationIcon(notification.type)}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${notification.title}</div>
                    <div class="notification-message">${notification.message}</div>
                    <div class="notification-time">${this.timeAgo(notification.created_at)}</div>
                </div>
                <div class="notification-actions">
                    ${!notification.is_read ? `
                        <button class="btn btn-sm btn-link" onclick="organizer.markNotificationRead(${notification.id})">
                            <i class="fas fa-check"></i>
                        </button>
                    ` : ''}
                    <button class="btn btn-sm btn-link text-danger" onclick="organizer.deleteNotification(${notification.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }
    
    getNotificationIcon(type) {
        const icons = {
            event_approved: 'check-circle',
            event_rejected: 'times-circle',
            new_registration: 'user-plus',
            payment_received: 'credit-card',
            event_reminder: 'clock',
            system: 'cog'
        };
        return icons[type] || 'bell';
    }
    
    async markNotificationRead(notificationId) {
        try {
            const response = await this.apiRequest('/api/organizer/mark-notification-read.php', {
                method: 'POST',
                body: JSON.stringify({ notification_id: notificationId })
            });
            
            if (response.success) {
                const item = document.querySelector(`[data-id="${notificationId}"]`);
                if (item) {
                    item.classList.remove('unread');
                    item.querySelector('.notification-actions').innerHTML = `
                        <button class="btn btn-sm btn-link text-danger" onclick="organizer.deleteNotification(${notificationId})">
                            <i class="fas fa-trash"></i>
                        </button>
                    `;
                }
                
                // Update badge
                const currentCount = parseInt(document.querySelector('.notification-badge').textContent) || 0;
                this.updateNotificationBadge(Math.max(0, currentCount - 1));
            }
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    }
    
    async deleteNotification(notificationId) {
        try {
            const response = await this.apiRequest('/api/organizer/delete-notification.php', {
                method: 'POST',
                body: JSON.stringify({ notification_id: notificationId })
            });
            
            if (response.success) {
                const item = document.querySelector(`[data-id="${notificationId}"]`);
                if (item) {
                    item.remove();
                }
                
                this.showAlert('Notification deleted', 'success', 2000);
            }
        } catch (error) {
            console.error('Failed to delete notification:', error);
        }
    }
    
    // Time utility
    timeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) return 'just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
        if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)} days ago`;
        if (diffInSeconds < 31536000) return `${Math.floor(diffInSeconds / 2592000)} months ago`;
        return `${Math.floor(diffInSeconds / 31536000)} years ago`;
    }
    
    // Auto-save functionality for forms
    initAutoSave() {
        const forms = document.querySelectorAll('form[data-autosave]');
        
        forms.forEach(form => {
            const formId = form.dataset.autosave;
            const inputs = form.querySelectorAll('input, textarea, select');
            
            // Load saved data
            this.loadAutoSavedData(form, formId);
            
            // Save on input
            inputs.forEach(input => {
                input.addEventListener('input', this.debounce(() => {
                    this.autoSaveForm(form, formId);
                }, 1000));
            });
        });
    }
    
    autoSaveForm(form, formId) {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        localStorage.setItem(`autosave_${formId}`, JSON.stringify({
            data: data,
            timestamp: Date.now()
        }));
        
        // Show auto-save indicator
        this.showAutoSaveIndicator();
    }
    
    loadAutoSavedData(form, formId) {
        const saved = localStorage.getItem(`autosave_${formId}`);
        if (!saved) return;
        
        try {
            const { data, timestamp } = JSON.parse(saved);
            
            // Only load if saved within last 24 hours
            if (Date.now() - timestamp > 24 * 60 * 60 * 1000) {
                localStorage.removeItem(`autosave_${formId}`);
                return;
            }
            
            // Populate form
            Object.entries(data).forEach(([key, value]) => {
                const input = form.querySelector(`[name="${key}"]`);
                if (input) {
                    input.value = value;
                }
            });
            
            // Show restore notification
            this.showAlert('Form data restored from auto-save', 'info', 3000);
            
        } catch (error) {
            console.error('Failed to load auto-saved data:', error);
            localStorage.removeItem(`autosave_${formId}`);
        }
    }
    
    showAutoSaveIndicator() {
        let indicator = document.querySelector('.autosave-indicator');
        
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.className = 'autosave-indicator';
            indicator.innerHTML = '<i class="fas fa-save"></i> Auto-saved';
            document.body.appendChild(indicator);
        }
        
        indicator.classList.add('show');
        
        setTimeout(() => {
            indicator.classList.remove('show');
        }, 2000);
    }
    
    clearAutoSave(formId) {
        localStorage.removeItem(`autosave_${formId}`);
    }
    
    // Keyboard shortcuts help
    showKeyboardShortcuts() {
        const shortcuts = [
            { key: 'Ctrl/Cmd + B', action: 'Toggle sidebar' },
            { key: 'Ctrl/Cmd + K', action: 'Focus search' },
            { key: 'Escape', action: 'Close modal/dropdown' },
            { key: 'Ctrl/Cmd + S', action: 'Save form (if applicable)' },
            { key: 'Ctrl/Cmd + N', action: 'New event (if applicable)' }
        ];
        
        const modal = document.createElement('div');
        modal.className = 'modal active';
        modal.innerHTML = `
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Keyboard Shortcuts</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="shortcuts-list">
                        ${shortcuts.map(shortcut => `
                            <div class="shortcut-item">
                                <kbd>${shortcut.key}</kbd>
                                <span>${shortcut.action}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close functionality
        modal.querySelector('.modal-close').addEventListener('click', () => {
            modal.remove();
        });
        
        modal.querySelector('.modal-overlay').addEventListener('click', () => {
            modal.remove();
        });
    }
    
    // Performance monitoring
    initPerformanceMonitoring() {
        // Monitor page load time
        window.addEventListener('load', () => {
            const loadTime = performance.now();
            console.log(`Page loaded in ${loadTime.toFixed(2)}ms`);
            
            // Send to analytics if needed
            if (loadTime > 3000) {
                console.warn('Slow page load detected');
            }
        });
        
        // Monitor memory usage
        if ('memory' in performance) {
            setInterval(() => {
                const memory = performance.memory;
                if (memory.usedJSHeapSize > 50 * 1024 * 1024) { // 50MB
                    console.warn('High memory usage detected');
                }
            }, 30000);
        }
    }
    
    // Cleanup
    destroy() {
        // Remove event listeners
        window.removeEventListener('resize', this.handleResponsive);
        document.removeEventListener('keydown', this.handleKeyboardShortcuts);
        
        // Destroy charts
        Object.values(this.charts).forEach(chart => {
            if (chart) {
                chart.destroy();
            }
        });
        
        // Clear intervals/timeouts
        if (this.notificationInterval) {
            clearInterval(this.notificationInterval);
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.organizer = new OrganizerDashboard();
    
    // Auto-save initialization
    window.organizer.initAutoSave();
    
    // Performance monitoring
    window.organizer.initPerformanceMonitoring();
    
    // Load notifications periodically
    window.organizer.loadNotifications();
    window.organizer.notificationInterval = setInterval(() => {
        window.organizer.loadNotifications();
    }, 30000); // Every 30 seconds
});

// Global utility functions
window.deleteEvent = (eventId) => window.organizer.deleteEvent(eventId);
window.updateEventStatus = (eventId, status) => window.organizer.updateEventStatus(eventId, status);
window.exportData = (type, format) => window.organizer.exportData(type, format);
window.setTheme = (theme) => window.organizer.setTheme(theme);
window.showKeyboardShortcuts = () => window.organizer.showKeyboardShortcuts();

// Service Worker registration for offline functionality
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('SW registered: ', registration);
            })
            .catch(registrationError => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}

