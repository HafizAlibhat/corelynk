/**
 * ==================================================================================
 * PRODUCTION MANAGEMENT SYSTEM - MODERN UI/UX JAVASCRIPT ENHANCEMENTS
 * 2025 Interactive Experience Implementation
 * ==================================================================================
 * 
 * Features:
 * - Smooth scrolling and page transitions
 * - Enhanced micro-interactions
 * - Real-time UI feedback
 * - Advanced animation controls
 * - Theme management
 * - Performance optimized interactions
 * 
 * ==================================================================================
 */

(function(window, document) {
    'use strict';

    // Configuration
    const CONFIG = {
        animations: {
            duration: {
                fast: 150,
                normal: 250,
                slow: 350
            },
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
        },
        performance: {
            throttleDelay: 16, // 60fps
            debounceDelay: 300
        }
    };

    // Utility Functions
    const Utils = {
        // Throttle function for performance
        throttle(func, delay) {
            let timeoutId;
            let lastExecTime = 0;
            return function(...args) {
                const currentTime = Date.now();
                if (currentTime - lastExecTime > delay) {
                    func.apply(this, args);
                    lastExecTime = currentTime;
                } else {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => {
                        func.apply(this, args);
                        lastExecTime = Date.now();
                    }, delay - (currentTime - lastExecTime));
                }
            };
        },

        // Debounce function
        debounce(func, delay) {
            let timeoutId;
            return function(...args) {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => func.apply(this, args), delay);
            };
        },

        // Check if element is in viewport
        isInViewport(element) {
            const rect = element.getBoundingClientRect();
            return (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                rect.right <= (window.innerWidth || document.documentElement.clientWidth)
            );
        },

        // Smooth scroll to element
        smoothScrollTo(element, offset = 0) {
            const targetPosition = element.offsetTop - offset;
            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });
        },

        // Add CSS class with animation
        addClassWithAnimation(element, className, duration = CONFIG.animations.duration.normal) {
            element.classList.add(className);
            return new Promise(resolve => {
                setTimeout(() => resolve(), duration);
            });
        },

        // Remove CSS class with animation
        removeClassWithAnimation(element, className, duration = CONFIG.animations.duration.normal) {
            element.classList.remove(className);
            return new Promise(resolve => {
                setTimeout(() => resolve(), duration);
            });
        }
    };

    // Modern Page Loader with sophisticated animations
    class PageLoader {
        constructor() {
            this.isLoading = false;
            this.init();
        }

        init() {
            this.createLoader();
            this.bindEvents();
        }

        createLoader() {
            const loader = document.createElement('div');
            loader.className = 'modern-page-loader';
            loader.innerHTML = `
                <div class="loader-content">
                    <div class="loader-logo">
                        <i class="bi bi-gear-fill"></i>
                    </div>
                    <div class="loader-text">Production MS</div>
                    <div class="loader-progress">
                        <div class="loader-progress-bar"></div>
                    </div>
                    <div class="loader-dots">
                        <div></div>
                        <div></div>
                        <div></div>
                    </div>
                    <div class="loader-skip" style="margin-top: 1rem;">
                        <button onclick="this.closest('.modern-page-loader').style.display='none'" 
                                style="background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.3); 
                                       color: #374151; padding: 0.5rem 1rem; border-radius: 0.5rem; cursor: pointer;">
                            Skip Loading
                        </button>
                    </div>
                </div>
            `;
            
            // Add styles
            const style = document.createElement('style');
            style.textContent = `
                .modern-page-loader {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95));
                    backdrop-filter: blur(20px);
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    opacity: 1;
                    transition: opacity 0.5s ease;
                }
                
                .loader-content {
                    text-align: center;
                    animation: loaderPulse 2s ease-in-out infinite;
                }
                
                .loader-logo {
                    width: 80px;
                    height: 80px;
                    background: linear-gradient(135deg, #3b82f6, #2563eb);
                    border-radius: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 1rem;
                    color: white;
                    font-size: 2rem;
                    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
                    animation: loaderSpin 2s linear infinite;
                }
                
                .loader-text {
                    font-size: 1.5rem;
                    font-weight: 700;
                    color: #374151;
                    margin-bottom: 1.5rem;
                    opacity: 0.8;
                }
                
                .loader-progress {
                    width: 200px;
                    height: 4px;
                    background: #e5e7eb;
                    border-radius: 2px;
                    margin: 0 auto 1.5rem;
                    overflow: hidden;
                }
                
                .loader-progress-bar {
                    height: 100%;
                    background: linear-gradient(90deg, #3b82f6, #2563eb);
                    border-radius: 2px;
                    animation: loaderProgress 2s ease-in-out infinite;
                }
                
                .loader-dots {
                    display: flex;
                    justify-content: center;
                    gap: 0.5rem;
                }
                
                .loader-dots div {
                    width: 8px;
                    height: 8px;
                    background: #3b82f6;
                    border-radius: 50%;
                    animation: loaderDots 1.4s ease-in-out infinite both;
                }
                
                .loader-dots div:nth-child(1) { animation-delay: -0.32s; }
                .loader-dots div:nth-child(2) { animation-delay: -0.16s; }
                
                @keyframes loaderSpin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                @keyframes loaderPulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                }
                
                @keyframes loaderProgress {
                    0% { transform: translateX(-100%); }
                    50% { transform: translateX(0%); }
                    100% { transform: translateX(100%); }
                }
                
                @keyframes loaderDots {
                    0%, 80%, 100% { transform: scale(0); }
                    40% { transform: scale(1); }
                }
            `;
            document.head.appendChild(style);
            document.body.appendChild(loader);
            this.loader = loader;
        }

        show() {
            if (this.isLoading) return;
            this.isLoading = true;
            this.loader.style.display = 'flex';
            this.loader.style.opacity = '1';
        }

        hide() {
            if (!this.isLoading) return;
            this.isLoading = false;
            this.loader.style.opacity = '0';
            setTimeout(() => {
                this.loader.style.display = 'none';
            }, 500);
        }

        bindEvents() {
            // Disabled automatic loader for now
            // window.addEventListener('beforeunload', () => this.show());
            
            // Hide loader immediately when page is loaded
            window.addEventListener('load', () => {
                this.hide();
            });
            
            // Hide loader on DOM ready as well
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.hide());
            } else {
                this.hide();
            }
        }
    }

    // Enhanced Sidebar with modern interactions
    class ModernSidebar {
        constructor() {
            this.sidebar = document.querySelector('.sidebar');
            this.overlay = null;
            this.isOpen = false;
            this.init();
        }

        init() {
            this.createOverlay();
            this.bindEvents();
            this.enhanceNavigation();
        }

        createOverlay() {
            this.overlay = document.createElement('div');
            this.overlay.className = 'sidebar-overlay';
            this.overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(4px);
                z-index: 1045;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            `;
            document.body.appendChild(this.overlay);
        }

        toggle() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        }

        open() {
            this.isOpen = true;
            this.sidebar.classList.add('show');
            this.overlay.style.visibility = 'visible';
            this.overlay.style.opacity = '1';
            document.body.style.overflow = 'hidden';
        }

        close() {
            this.isOpen = false;
            this.sidebar.classList.remove('show');
            this.overlay.style.visibility = 'hidden';
            this.overlay.style.opacity = '0';
            document.body.style.overflow = '';
        }

        enhanceNavigation() {
            const navLinks = this.sidebar.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                // Add ripple effect
                link.addEventListener('click', (e) => {
                    this.createRipple(e, link);
                });

                // Add tooltip for collapsed state
                link.addEventListener('mouseenter', () => {
                    if (window.innerWidth <= 768) return;
                    this.showTooltip(link);
                });

                link.addEventListener('mouseleave', () => {
                    this.hideTooltip(link);
                });
            });
        }

        createRipple(event, element) {
            const ripple = document.createElement('span');
            const rect = element.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = event.clientX - rect.left - size / 2;
            const y = event.clientY - rect.top - size / 2;

            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;

            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    to {
                        transform: scale(4);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);

            element.style.position = 'relative';
            element.appendChild(ripple);

            setTimeout(() => {
                ripple.remove();
            }, 600);
        }

        showTooltip(element) {
            const text = element.textContent.trim();
            const tooltip = document.createElement('div');
            tooltip.className = 'nav-tooltip';
            tooltip.textContent = text;
            tooltip.style.cssText = `
                position: absolute;
                left: 100%;
                top: 50%;
                transform: translateY(-50%);
                background: rgba(0, 0, 0, 0.8);
                color: white;
                padding: 0.5rem 0.75rem;
                border-radius: 0.375rem;
                font-size: 0.875rem;
                white-space: nowrap;
                z-index: 1000;
                margin-left: 0.5rem;
                opacity: 0;
                transition: opacity 0.2s ease;
            `;
            
            element.appendChild(tooltip);
            setTimeout(() => {
                tooltip.style.opacity = '1';
            }, 10);
        }

        hideTooltip(element) {
            const tooltip = element.querySelector('.nav-tooltip');
            if (tooltip) {
                tooltip.style.opacity = '0';
                setTimeout(() => {
                    tooltip.remove();
                }, 200);
            }
        }

        bindEvents() {
            // Mobile menu toggle
            const menuToggle = document.querySelector('[data-bs-toggle="sidebar"]');
            if (menuToggle) {
                menuToggle.addEventListener('click', () => this.toggle());
            }

            // Overlay click to close
            this.overlay.addEventListener('click', () => this.close());

            // ESC key to close
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.close();
                }
            });

            // Auto-close on window resize
            window.addEventListener('resize', Utils.throttle(() => {
                if (window.innerWidth > 768 && this.isOpen) {
                    this.close();
                }
            }, CONFIG.performance.throttleDelay));
        }
    }

    // Enhanced Card Interactions
    class CardEnhancer {
        constructor() {
            this.init();
        }

        init() {
            this.enhanceCards();
            this.addParallaxEffect();
        }

        enhanceCards() {
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                // Add 3D tilt effect
                this.addTiltEffect(card);
                
                // Add progressive loading animation
                this.addLoadAnimation(card);
                
                // Add enhanced hover effects
                this.addHoverEffects(card);
            });
        }

        addTiltEffect(card) {
            card.addEventListener('mousemove', (e) => {
                if (window.innerWidth <= 768) return;
                
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = (y - centerY) / 10;
                const rotateY = (centerX - x) / 10;
                
                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateZ(10px)`;
            });
            
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateZ(0)';
            });
        }

        addLoadAnimation(card) {
            if (Utils.isInViewport(card)) {
                card.style.animation = 'fadeInUp 0.6s ease-out';
            }
        }

        addHoverEffects(card) {
            card.addEventListener('mouseenter', () => {
                const cardHeader = card.querySelector('.card-header');
                if (cardHeader) {
                    cardHeader.style.background = 'linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(147, 197, 253, 0.1))';
                }
            });

            card.addEventListener('mouseleave', () => {
                const cardHeader = card.querySelector('.card-header');
                if (cardHeader) {
                    cardHeader.style.background = '';
                }
            });
        }

        addParallaxEffect() {
            const cards = document.querySelectorAll('.stat-card');
            
            window.addEventListener('scroll', Utils.throttle(() => {
                const scrolled = window.pageYOffset;
                cards.forEach((card, index) => {
                    const rate = scrolled * -0.5;
                    const yPos = Math.round(rate / (index + 1));
                    card.style.transform = `translateY(${yPos}px)`;
                });
            }, CONFIG.performance.throttleDelay));
        }
    }

    // Enhanced Button Interactions
    class ButtonEnhancer {
        constructor() {
            this.init();
        }

        init() {
            this.enhanceButtons();
        }

        enhanceButtons() {
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                this.addRippleEffect(button);
                this.addMagneticEffect(button);
                this.addLoadingState(button);
            });
        }

        addRippleEffect(button) {
            button.addEventListener('click', (e) => {
                const ripple = document.createElement('span');
                const rect = button.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;

                ripple.style.cssText = `
                    position: absolute;
                    width: ${size}px;
                    height: ${size}px;
                    left: ${x}px;
                    top: ${y}px;
                    background: rgba(255, 255, 255, 0.4);
                    border-radius: 50%;
                    transform: scale(0);
                    animation: buttonRipple 0.6s ease-out;
                    pointer-events: none;
                `;

                button.style.position = 'relative';
                button.style.overflow = 'hidden';
                button.appendChild(ripple);

                setTimeout(() => ripple.remove(), 600);
            });
        }

        addMagneticEffect(button) {
            if (window.innerWidth <= 768) return;

            button.addEventListener('mousemove', (e) => {
                const rect = button.getBoundingClientRect();
                const x = e.clientX - rect.left - rect.width / 2;
                const y = e.clientY - rect.top - rect.height / 2;
                
                button.style.transform = `translate(${x * 0.1}px, ${y * 0.1}px)`;
            });

            button.addEventListener('mouseleave', () => {
                button.style.transform = '';
            });
        }

        addLoadingState(button) {
            // Add loading state for form submissions
            const form = button.closest('form');
            if (form) {
                form.addEventListener('submit', () => {
                    button.classList.add('loading');
                    button.disabled = true;
                    
                    const originalText = button.innerHTML;
                    button.innerHTML = `
                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                        Loading...
                    `;

                    // Reset after form handling (this would be controlled by the backend)
                    setTimeout(() => {
                        button.classList.remove('loading');
                        button.disabled = false;
                        button.innerHTML = originalText;
                    }, 2000);
                });
            }
        }
    }

    // Enhanced Table Interactions
    class TableEnhancer {
        constructor() {
            this.init();
        }

        init() {
            this.enhanceTables();
        }

        enhanceTables() {
            const tables = document.querySelectorAll('.table');
            tables.forEach(table => {
                this.addSortingVisuals(table);
                this.addRowHighlight(table);
                this.addSmoothScrolling(table);
            });
        }

        addSortingVisuals(table) {
            const headers = table.querySelectorAll('th[data-sortable="true"]');
            headers.forEach(header => {
                header.style.cursor = 'pointer';
                header.addEventListener('click', () => {
                    // Add sorting animation
                    header.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        header.style.transform = '';
                    }, 150);
                });
            });
        }

        addRowHighlight(table) {
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                row.addEventListener('mouseenter', () => {
                    row.style.background = 'linear-gradient(90deg, rgba(59, 130, 246, 0.05), transparent)';
                    row.style.borderLeft = '3px solid #3b82f6';
                });

                row.addEventListener('mouseleave', () => {
                    row.style.background = '';
                    row.style.borderLeft = '';
                });
            });
        }

        addSmoothScrolling(table) {
            const tableContainer = table.closest('.table-responsive');
            if (tableContainer) {
                tableContainer.style.scrollBehavior = 'smooth';
            }
        }
    }

    // Modern Form Enhancements
    class FormEnhancer {
        constructor() {
            this.init();
        }

        init() {
            this.enhanceForms();
        }

        enhanceForms() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                this.addFloatingLabels(form);
                this.addRealTimeValidation(form);
                this.addProgressiveEnhancement(form);
            });
        }

        addFloatingLabels(form) {
            const formGroups = form.querySelectorAll('.form-group, .mb-3');
            formGroups.forEach(group => {
                const input = group.querySelector('.form-control');
                const label = group.querySelector('.form-label');
                
                if (input && label) {
                    input.addEventListener('focus', () => {
                        label.style.transform = 'translateY(-1.5rem) scale(0.85)';
                        label.style.color = '#3b82f6';
                    });

                    input.addEventListener('blur', () => {
                        if (!input.value) {
                            label.style.transform = '';
                            label.style.color = '';
                        }
                    });
                }
            });
        }

        addRealTimeValidation(form) {
            const inputs = form.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.addEventListener('input', Utils.debounce(() => {
                    this.validateInput(input);
                }, CONFIG.performance.debounceDelay));
            });
        }

        validateInput(input) {
            const isValid = input.checkValidity();
            const feedbackElement = input.parentNode.querySelector('.invalid-feedback') || 
                                  input.parentNode.querySelector('.valid-feedback');

            if (isValid) {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
            } else {
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
            }
        }

        addProgressiveEnhancement(form) {
            // Add smooth transitions for form state changes
            const elements = form.querySelectorAll('.form-control, .btn');
            elements.forEach(element => {
                element.style.transition = 'all 0.2s ease';
            });
        }
    }

    // Enhanced Alert System
    class AlertEnhancer {
        constructor() {
            this.init();
        }

        init() {
            this.enhanceAlerts();
        }

        enhanceAlerts() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                this.addEnhancedAnimation(alert);
                this.addProgressBar(alert);
            });
        }

        addEnhancedAnimation(alert) {
            // Slide in from top
            alert.style.transform = 'translateY(-100%)';
            alert.style.opacity = '0';
            
            setTimeout(() => {
                alert.style.transform = 'translateY(0)';
                alert.style.opacity = '1';
            }, 100);
        }

        addProgressBar(alert) {
            const progressBar = document.createElement('div');
            progressBar.style.cssText = `
                position: absolute;
                bottom: 0;
                left: 0;
                height: 3px;
                background: currentColor;
                opacity: 0.3;
                border-radius: 0 0 0.375rem 0.375rem;
                animation: alertProgress 5s linear;
            `;

            const style = document.createElement('style');
            style.textContent = `
                @keyframes alertProgress {
                    from { width: 100%; }
                    to { width: 0%; }
                }
            `;
            document.head.appendChild(style);

            alert.style.position = 'relative';
            alert.appendChild(progressBar);
        }
    }

    // Theme Manager with smooth transitions
    class ThemeManager {
        constructor() {
            this.currentTheme = localStorage.getItem('theme') || 'light';
            this.init();
        }

        init() {
            this.applyTheme();
            this.createToggleButton();
        }

        createToggleButton() {
            const button = document.createElement('button');
            button.className = 'theme-toggle';
            button.innerHTML = this.currentTheme === 'dark' ? '☀️' : '🌙';
            button.addEventListener('click', () => this.toggleTheme());
            document.body.appendChild(button);
        }

        toggleTheme() {
            this.currentTheme = this.currentTheme === 'light' ? 'dark' : 'light';
            this.applyTheme();
            localStorage.setItem('theme', this.currentTheme);
            
            // Update button icon
            const button = document.querySelector('.theme-toggle');
            button.innerHTML = this.currentTheme === 'dark' ? '☀️' : '🌙';
        }

        applyTheme() {
            document.documentElement.setAttribute('data-theme', this.currentTheme);
            
            // Add smooth transition for theme change
            document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';
            setTimeout(() => {
                document.body.style.transition = '';
            }, 300);
        }
    }

    // Performance Monitor
    class PerformanceMonitor {
        constructor() {
            this.metrics = {
                pageLoadTime: 0,
                domContentLoadedTime: 0,
                firstPaintTime: 0
            };
            this.init();
        }

        init() {
            this.measurePerformance();
        }

        measurePerformance() {
            window.addEventListener('load', () => {
                const navigation = performance.getEntriesByType('navigation')[0];
                this.metrics.pageLoadTime = navigation.loadEventEnd - navigation.loadEventStart;
                this.metrics.domContentLoadedTime = navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart;
                
                // Log performance metrics (only in development)
                if (window.location.hostname === 'localhost') {
                    console.log('🚀 Performance Metrics:', this.metrics);
                }
            });
        }
    }

    // Initialize all enhancements when DOM is ready
    function initializeEnhancements() {
        // Check if we should enable animations
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (prefersReducedMotion) {
            document.documentElement.style.setProperty('--duration-fast', '0ms');
            document.documentElement.style.setProperty('--duration-normal', '0ms');
            document.documentElement.style.setProperty('--duration-slow', '0ms');
        }

        // Initialize all enhancement classes (LOADER DISABLED)
        // const pageLoader = new PageLoader(); // COMMENTED OUT
        const sidebar = new ModernSidebar();
        const cardEnhancer = new CardEnhancer();
        const buttonEnhancer = new ButtonEnhancer();
        const tableEnhancer = new TableEnhancer();
        const formEnhancer = new FormEnhancer();
        const alertEnhancer = new AlertEnhancer();
        const themeManager = new ThemeManager();
        const performanceMonitor = new PerformanceMonitor();

        // Add global keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + K for quick search (if implemented)
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('input[type="search"]');
                if (searchInput) {
                    searchInput.focus();
                }
            }
            
            // Ctrl/Cmd + / for help (if implemented)
            if ((e.ctrlKey || e.metaKey) && e.key === '/') {
                e.preventDefault();
                // Show help modal or tooltip
                console.log('Help shortcut pressed');
            }
        });

        // Add smooth scrolling to all anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    Utils.smoothScrollTo(target, 100);
                }
            });
        });

        console.log('🎨 Modern UI/UX enhancements loaded successfully!');
    }

    // Load enhancements when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeEnhancements);
    } else {
        initializeEnhancements();
    }

    // Expose utilities globally for other scripts to use
    window.ModernUI = {
        Utils,
        CONFIG
    };

})(window, document);
