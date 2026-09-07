/**
 * Production Management System - Main JavaScript
 * 
 * This file contains common JavaScript functionality used throughout the application
 */

// Global application object
window.PMS = window.PMS || {};

// Configuration
PMS.config = {
    baseUrl: window.location.origin,
    csrfToken: null,
    debug: false,
    apiEndpoint: '/api/v1',
    polling: {
        interval: 30000, // 30 seconds
        enabled: true
    },
    notifications: {
        duration: 5000,
        position: 'top-right'
    }
};

// Initialize CSRF token
PMS.setCsrfToken = function(token) {
    this.config.csrfToken = token;
    // Update all forms with the new token
    document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
        input.value = token;
    });
};

// Utility functions
PMS.utils = {
    /**
     * Format number with thousand separators
     */
    formatNumber: function(num, decimals = 0) {
        return Number(num).toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    },

    /**
     * Format currency
     */
    formatCurrency: function(amount, currency = 'USD') {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency
        }).format(amount);
    },

    /**
     * Format date
     */
    formatDate: function(date, format = 'short') {
        const d = new Date(date);
        const options = {
            short: { year: 'numeric', month: 'short', day: 'numeric' },
            long: { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' },
            time: { hour: '2-digit', minute: '2-digit', hour12: true }
        };
        return d.toLocaleDateString('en-US', options[format] || options.short);
    },

    /**
     * Calculate time ago
     */
    timeAgo: function(date) {
        const now = new Date();
        const diff = now - new Date(date);
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);

        if (days > 7) return this.formatDate(date);
        if (days > 0) return `${days} day${days > 1 ? 's' : ''} ago`;
        if (hours > 0) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        if (minutes > 0) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        return 'Just now';
    },

    /**
     * Debounce function
     */
    debounce: function(func, wait, immediate) {
        let timeout;
        return function executedFunction() {
            const context = this;
            const args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    },

    /**
     * Generate random ID
     */
    generateId: function(prefix = 'id') {
        return prefix + '_' + Math.random().toString(36).substr(2, 9);
    },

    /**
     * Escape HTML
     */
    escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Copy text to clipboard
     */
    copyToClipboard: function(text) {
        if (navigator.clipboard) {
            return navigator.clipboard.writeText(text);
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            const successful = document.execCommand('copy');
            document.body.removeChild(textArea);
            return Promise.resolve(successful);
        }
    }
};

// AJAX wrapper
PMS.ajax = {
    /**
     * Make AJAX request
     */
    request: function(options) {
        const defaults = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };

        // Add CSRF token to headers
        if (PMS.config.csrfToken) {
            defaults.headers['X-CSRF-TOKEN'] = PMS.config.csrfToken;
        }

        const config = Object.assign({}, defaults, options);

        // Convert data to JSON if needed
        if (config.data && typeof config.data === 'object' && config.method !== 'GET') {
            config.body = JSON.stringify(config.data);
        }

        return fetch(config.url, config)
            .then(response => {
                // Update CSRF token if provided
                const newToken = response.headers.get('X-CSRF-TOKEN');
                if (newToken) {
                    PMS.setCsrfToken(newToken);
                }

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                }
                return response.text();
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                throw error;
            });
    },

    /**
     * GET request
     */
    get: function(url, params = {}) {
        const urlParams = new URLSearchParams(params);
        const fullUrl = url + (Object.keys(params).length ? '?' + urlParams : '');
        return this.request({ url: fullUrl, method: 'GET' });
    },

    /**
     * POST request
     */
    post: function(url, data = {}) {
        return this.request({ url: url, method: 'POST', data: data });
    },

    /**
     * PUT request
     */
    put: function(url, data = {}) {
        return this.request({ url: url, method: 'PUT', data: data });
    },

    /**
     * DELETE request
     */
    delete: function(url) {
        return this.request({ url: url, method: 'DELETE' });
    }
};

// Notification system
PMS.notifications = {
    container: null,

    init: function() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container position-fixed top-0 end-0 p-3';
            this.container.style.zIndex = '9999';
            document.body.appendChild(this.container);
        }
    },

    show: function(message, type = 'info', title = null, duration = null) {
        this.init();

        const toastId = PMS.utils.generateId('toast');
        const toastDuration = duration || PMS.config.notifications.duration;

        const toastHtml = `
            <div class="toast" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header">
                    <i class="bi bi-${this.getIcon(type)} text-${type} me-2"></i>
                    <strong class="me-auto">${title || this.getTitle(type)}</strong>
                    <small class="text-muted">just now</small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `;

        this.container.insertAdjacentHTML('beforeend', toastHtml);

        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, {
            delay: toastDuration
        });

        toast.show();

        // Remove from DOM after hiding
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });

        return toast;
    },

    success: function(message, title = 'Success') {
        return this.show(message, 'success', title);
    },

    error: function(message, title = 'Error') {
        return this.show(message, 'danger', title);
    },

    warning: function(message, title = 'Warning') {
        return this.show(message, 'warning', title);
    },

    info: function(message, title = 'Info') {
        return this.show(message, 'info', title);
    },

    getIcon: function(type) {
        const icons = {
            success: 'check-circle',
            danger: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    },

    getTitle: function(type) {
        const titles = {
            success: 'Success',
            danger: 'Error',
            warning: 'Warning',
            info: 'Information'
        };
        return titles[type] || 'Notification';
    }
};

// Modal helpers
PMS.modal = {
    show: function(modalId, options = {}) {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement, options);
            modal.show();
            return modal;
        }
    },

    hide: function(modalId) {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        }
    },

    confirm: function(message, title = 'Confirm Action', callback = null) {
        const modalId = PMS.utils.generateId('confirm-modal');
        const modalHtml = `
            <div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>${message}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="${modalId}-confirm">Confirm</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const modalElement = document.getElementById(modalId);
        const modal = new bootstrap.Modal(modalElement);

        // Handle confirm button
        document.getElementById(`${modalId}-confirm`).addEventListener('click', () => {
            if (callback && typeof callback === 'function') {
                callback();
            }
            modal.hide();
        });

        // Clean up modal after hiding
        modalElement.addEventListener('hidden.bs.modal', () => {
            modalElement.remove();
        });

        modal.show();
        return modal;
    }
};

// Form helpers
PMS.forms = {
    /**
     * Serialize form data to object
     */
    serialize: function(form) {
        const formData = new FormData(form);
        const data = {};
        for (let [key, value] of formData.entries()) {
            if (data[key]) {
                if (Array.isArray(data[key])) {
                    data[key].push(value);
                } else {
                    data[key] = [data[key], value];
                }
            } else {
                data[key] = value;
            }
        }
        return data;
    },

    /**
     * Submit form via AJAX
     */
    submit: function(form, options = {}) {
        const formData = this.serialize(form);
        const url = options.url || form.action;
        const method = options.method || form.method || 'POST';

        // Add loading state
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton ? submitButton.innerHTML : '';
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
        }

        return PMS.ajax.request({
            url: url,
            method: method.toUpperCase(),
            data: formData
        }).then(response => {
            if (options.onSuccess && typeof options.onSuccess === 'function') {
                options.onSuccess(response);
            }
            return response;
        }).catch(error => {
            if (options.onError && typeof options.onError === 'function') {
                options.onError(error);
            }
            throw error;
        }).finally(() => {
            // Remove loading state
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }
        });
    },

    /**
     * Reset form and remove validation classes
     */
    reset: function(form) {
        form.reset();
        form.classList.remove('was-validated');
        form.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('.is-valid').forEach(el => {
            el.classList.remove('is-valid');
        });
    },

    /**
     * Validate form
     */
    validate: function(form) {
        form.classList.add('was-validated');
        return form.checkValidity();
    }
};

// Data table helpers
PMS.datatable = {
    /**
     * Initialize data table with search and pagination
     */
    init: function(tableId, options = {}) {
        const table = document.getElementById(tableId);
        if (!table) return;

        const defaults = {
            searchable: true,
            pageable: true,
            pageSize: 20,
            sortable: true
        };

        const config = Object.assign({}, defaults, options);

        // Add search functionality
        if (config.searchable) {
            this.addSearch(table, config);
        }

        // Add sorting functionality
        if (config.sortable) {
            this.addSorting(table, config);
        }

        // Add pagination
        if (config.pageable) {
            this.addPagination(table, config);
        }
    },

    addSearch: function(table, config) {
        // Implementation would go here
    },

    addSorting: function(table, config) {
        const headers = table.querySelectorAll('th[data-sortable]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                // Sorting implementation would go here
            });
        });
    },

    addPagination: function(table, config) {
        // Pagination implementation would go here
    }
};

// Loading states
PMS.loading = {
    show: function(element, message = 'Loading...') {
        if (typeof element === 'string') {
            element = document.getElementById(element);
        }

        if (element) {
            const loadingHtml = `
                <div class="d-flex justify-content-center align-items-center p-4">
                    <div class="spinner-border text-primary me-2" role="status"></div>
                    <span>${message}</span>
                </div>
            `;
            element.innerHTML = loadingHtml;
        }
    },

    hide: function(element) {
        if (typeof element === 'string') {
            element = document.getElementById(element);
        }

        if (element) {
            element.innerHTML = '';
        }
    },

    overlay: function(show = true, message = 'Loading...') {
        let overlay = document.getElementById('loading-overlay');

        if (show) {
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'loading-overlay';
                overlay.className = 'loading-overlay';
                overlay.innerHTML = `
                    <div class="text-center">
                        <div class="loading-spinner mb-3"></div>
                        <p class="text-muted">${message}</p>
                    </div>
                `;
                document.body.appendChild(overlay);
            }
            overlay.style.display = 'flex';
        } else {
            if (overlay) {
                overlay.style.display = 'none';
            }
        }
    }
};

// Chart helpers
PMS.charts = {
    colors: [
        '#0d6efd', '#6c757d', '#198754', '#dc3545', '#ffc107',
        '#0dcaf0', '#6f42c1', '#fd7e14', '#20c997', '#f8f9fa'
    ],

    getColor: function(index) {
        return this.colors[index % this.colors.length];
    },

    defaultOptions: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
};

// Real-time updates
PMS.realtime = {
    interval: null,
    callbacks: [],

    start: function() {
        if (PMS.config.polling.enabled && !this.interval) {
            this.interval = setInterval(() => {
                this.update();
            }, PMS.config.polling.interval);
        }
    },

    stop: function() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    },

    addCallback: function(callback) {
        if (typeof callback === 'function') {
            this.callbacks.push(callback);
        }
    },

    update: function() {
        this.callbacks.forEach(callback => {
            try {
                callback();
            } catch (error) {
                console.error('Realtime update error:', error);
            }
        });
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize CSRF token
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) {
        PMS.setCsrfToken(csrfMeta.getAttribute('content'));
    }

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-submit forms with data-auto-submit
    document.querySelectorAll('form[data-auto-submit]').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            PMS.forms.submit(this, {
                onSuccess: function(response) {
                    if (response.message) {
                        PMS.notifications.success(response.message);
                    }
                    if (response.redirect) {
                        window.location.href = response.redirect;
                    }
                },
                onError: function(error) {
                    PMS.notifications.error('An error occurred. Please try again.');
                }
            });
        });
    });

    // Auto-confirm delete buttons
    document.querySelectorAll('[data-confirm-delete]').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const message = this.getAttribute('data-confirm-delete') || 'Are you sure you want to delete this item?';
            PMS.modal.confirm(message, 'Confirm Delete', () => {
                if (this.href) {
                    window.location.href = this.href;
                } else if (this.form) {
                    this.form.submit();
                }
            });
        });
    });

    // Update timestamps
    setInterval(() => {
        document.querySelectorAll('[data-timestamp]').forEach(el => {
            const timestamp = el.getAttribute('data-timestamp');
            el.textContent = PMS.utils.timeAgo(timestamp);
        });
    }, 60000); // Update every minute

    // Start real-time updates
    PMS.realtime.start();

    console.log('PMS JavaScript initialized');
});

// Export for global use
window.PMS = PMS;
