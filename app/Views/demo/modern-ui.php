<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<!-- Modern UI/UX Demo Page -->
<div class="animate-fade-in">
    <!-- Hero Section -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card bg-gradient-primary text-white border-0 shadow-lg">
                <div class="card-body p-5 text-center">
                    <h1 class="display-4 fw-bold mb-3">🎨 Modern UI/UX Demo</h1>
                    <p class="lead mb-4">Experience the enhanced Production Management System with 2025-level design</p>
                    <div class="d-flex justify-content-center gap-3">
                        <button class="btn btn-light btn-lg hover-scale">
                            <i class="bi bi-play-fill me-2"></i>
                            Explore Features
                        </button>
                        <button class="btn btn-outline-light btn-lg hover-scale">
                            <i class="bi bi-book me-2"></i>
                            Documentation
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Stat Cards -->
    <div class="row mb-5">
        <div class="col-md-3 mb-4">
            <div class="stat-card hover-lift">
                <div class="stat-icon bg-gradient-primary text-white">
                    <i class="bi bi-speedometer2"></i>
                </div>
                <div class="stat-value text-gradient-primary">2,847</div>
                <div class="stat-label">Total Orders</div>
                <div class="progress mt-3">
                    <div class="progress-bar" style="width: 78%"></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="stat-card hover-lift">
                <div class="stat-icon bg-gradient-success text-white">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-value text-gradient-primary">1,245</div>
                <div class="stat-label">Completed</div>
                <div class="progress mt-3">
                    <div class="progress-bar bg-gradient-success" style="width: 65%"></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="stat-card hover-lift">
                <div class="stat-icon bg-gradient-warning text-white">
                    <i class="bi bi-clock"></i>
                </div>
                <div class="stat-value text-gradient-primary">542</div>
                <div class="stat-label">In Progress</div>
                <div class="progress mt-3">
                    <div class="progress-bar bg-gradient-warning" style="width: 45%"></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="stat-card hover-lift">
                <div class="stat-icon bg-gradient-danger text-white">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="stat-value text-gradient-primary">23</div>
                <div class="stat-label">Overdue</div>
                <div class="progress mt-3">
                    <div class="progress-bar bg-gradient-danger" style="width: 15%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Component Showcase -->
    <div class="row">
        <!-- Enhanced Buttons -->
        <div class="col-lg-6 mb-4">
            <div class="card hover-lift">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-ui-radios me-2"></i>
                        Enhanced Buttons
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <button class="btn btn-primary hover-scale">
                            <i class="bi bi-plus me-2"></i>Primary
                        </button>
                        <button class="btn btn-success hover-scale">
                            <i class="bi bi-check me-2"></i>Success
                        </button>
                        <button class="btn btn-warning hover-scale">
                            <i class="bi bi-exclamation me-2"></i>Warning
                        </button>
                        <button class="btn btn-danger hover-scale">
                            <i class="bi bi-x me-2"></i>Danger
                        </button>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <button class="btn btn-outline-primary hover-scale">Outline Primary</button>
                        <button class="btn btn-outline-secondary hover-scale">Outline Secondary</button>
                    </div>
                    
                    <div class="d-flex gap-3">
                        <button class="btn btn-icon btn-primary">
                            <i class="bi bi-heart"></i>
                        </button>
                        <button class="btn btn-icon btn-success">
                            <i class="bi bi-star"></i>
                        </button>
                        <button class="btn btn-icon btn-warning">
                            <i class="bi bi-bookmark"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Form Elements -->
        <div class="col-lg-6 mb-4">
            <div class="card hover-lift">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-input-cursor me-2"></i>
                        Enhanced Forms
                    </h5>
                </div>
                <div class="card-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">Product Name</label>
                            <input type="text" class="form-control" placeholder="Enter product name">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select class="form-select">
                                <option>Select category</option>
                                <option>Electronics</option>
                                <option>Automotive</option>
                                <option>Manufacturing</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" rows="3" placeholder="Product description"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary hover-scale">
                            <i class="bi bi-save me-2"></i>
                            Save Product
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Table -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card hover-lift">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-table me-2"></i>
                        Enhanced Data Table
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Product ID</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Stock</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>PRD-001</code></td>
                                    <td>Advanced Widget Pro</td>
                                    <td>Electronics</td>
                                    <td><span class="badge status-active">Active</span></td>
                                    <td>1,250 units</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><code>PRD-002</code></td>
                                    <td>Smart Component X</td>
                                    <td>Automotive</td>
                                    <td><span class="badge status-pending">Pending</span></td>
                                    <td>856 units</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td><code>PRD-003</code></td>
                                    <td>Precision Tool Set</td>
                                    <td>Manufacturing</td>
                                    <td><span class="badge status-completed">Completed</span></td>
                                    <td>425 units</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Alerts -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>Success!</strong> Your modern UI/UX enhancements have been successfully applied.
            </div>
            
            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i>
                <strong>Info:</strong> The system now features 2025-level design standards with enhanced animations.
            </div>
            
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Note:</strong> Some features require modern browser support for optimal experience.
            </div>
        </div>
    </div>

    <!-- Timeline Component -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card hover-lift">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history me-2"></i>
                        Enhanced Timeline
                    </h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-content">
                                <h6 class="fw-bold">UI/UX Enhancement Complete</h6>
                                <p class="mb-1">Successfully implemented 2025-level design system with modern components.</p>
                                <small class="text-muted">2 minutes ago</small>
                            </div>
                        </div>
                        
                        <div class="timeline-item">
                            <div class="timeline-content">
                                <h6 class="fw-bold">JavaScript Enhancements Added</h6>
                                <p class="mb-1">Interactive components with micro-animations and performance optimizations.</p>
                                <small class="text-muted">5 minutes ago</small>
                            </div>
                        </div>
                        
                        <div class="timeline-item">
                            <div class="timeline-content">
                                <h6 class="fw-bold">CSS Framework Updated</h6>
                                <p class="mb-1">Modern design tokens, glass morphism, and responsive enhancements.</p>
                                <small class="text-muted">10 minutes ago</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
    // Demo-specific JavaScript for showcasing interactions
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🎨 Modern UI/UX Demo loaded successfully!');
        
        // Add some demo interactions
        const demoButtons = document.querySelectorAll('.btn');
        demoButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!this.type || this.type !== 'submit') {
                    e.preventDefault();
                    
                    // Show a temporary success message
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-check me-2"></i>Clicked!';
                    this.disabled = true;
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }, 1000);
                }
            });
        });
        
        // Animate stat values on scroll
        const animateCounters = () => {
            const counters = document.querySelectorAll('.stat-value');
            counters.forEach(counter => {
                if (window.ModernUI.Utils.isInViewport(counter)) {
                    const target = parseInt(counter.textContent.replace(/,/g, ''));
                    let current = 0;
                    const increment = target / 100;
                    
                    const timer = setInterval(() => {
                        current += increment;
                        if (current >= target) {
                            current = target;
                            clearInterval(timer);
                        }
                        counter.textContent = Math.floor(current).toLocaleString();
                    }, 20);
                }
            });
        };
        
        // Trigger counter animation
        setTimeout(animateCounters, 500);
    });
</script>
<?= $this->endSection() ?>
