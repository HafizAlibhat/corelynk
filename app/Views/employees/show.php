<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Employee Details - <?= esc($employee['employee_code']) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-person me-2"></i>Employee Details - <?= esc($employee['employee_code']) ?>
                    </h6>
                    <div>
                        <a href="<?= base_url('/employees/' . $employee['id'] . '/edit') ?>" class="btn btn-warning me-2">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </a>
                        <a href="<?= base_url('/employees') ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back to List
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <!-- Basic Information -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">Basic Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Employee Code:</strong></td>
                                <td><?= esc($employee['employee_code']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td><?= esc($employee['first_name'] . ' ' . $employee['last_name']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Department:</strong></td>
                                <td>
                                    <?php if (!empty($employee['department'])): ?>
                                        <span class="badge bg-info"><?= esc($employee['department']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Not specified</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Phone:</strong></td>
                                <td><?= !empty($employee['phone']) ? esc($employee['phone']) : '<span class="text-muted">Not provided</span>' ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td><?= !empty($employee['email']) ? esc($employee['email']) : '<span class="text-muted">Not provided</span>' ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge <?= $employee['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $employee['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3">Activity Information</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Joined:</strong></td>
                                <td><?= date('d M Y', strtotime($employee['created_at'])) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Last Updated:</strong></td>
                                <td><?= date('d M Y H:i', strtotime($employee['updated_at'])) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Total Skills:</strong></td>
                                <td>
                                    <span class="badge bg-primary"><?= count($skills) ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Skills & Competencies -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-tools me-2"></i>Skills & Competencies
                    </h6>
                    <?php if (!empty($skills)): ?>
                        <div class="row">
                            <?php foreach ($skills as $skill): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card border-left-primary h-100">
                                        <div class="card-body py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="card-title mb-1"><?= esc($skill['skill_name']) ?></h6>
                                                    <span class="badge 
                                                        <?php 
                                                        switch($skill['proficiency_level']) {
                                                            case 'expert': echo 'bg-success'; break;
                                                            case 'advanced': echo 'bg-primary'; break;
                                                            case 'intermediate': echo 'bg-warning text-dark'; break;
                                                            default: echo 'bg-secondary'; break;
                                                        }
                                                        ?>">
                                                        <?= ucfirst($skill['proficiency_level']) ?>
                                                    </span>
                                                </div>
                                                <div>
                                                    <?php 
                                                    $stars = match($skill['proficiency_level']) {
                                                        'expert' => 5,
                                                        'advanced' => 4,
                                                        'intermediate' => 3,
                                                        default => 2
                                                    };
                                                    for($i = 1; $i <= 5; $i++): 
                                                    ?>
                                                        <i class="bi bi-star<?= $i <= $stars ? '-fill text-warning' : '' ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>No skills added yet. 
                            <a href="<?= base_url('/employees/' . $employee['id'] . '/edit') ?>">Add skills</a> to track competencies.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Batch Assignments -->
                <div class="mb-4">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-clipboard-check me-2"></i>Recent Batch Assignments
                    </h6>
                    <?php if (!empty($recent_assignments)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Work Order</th>
                                        <th>Product</th>
                                        <th>Process</th>
                                        <th>Quantity</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_assignments as $assignment): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= base_url('/work-orders/' . $assignment['work_order_id']) ?>">
                                                    <?= esc($assignment['work_order_code']) ?>
                                                </a>
                                            </td>
                                            <td><?= esc($assignment['product_name']) ?></td>
                                            <td><?= esc($assignment['process_name']) ?></td>
                                            <td><?= number_format($assignment['assigned_quantity']) ?></td>
                                            <td><?= date('d M Y', strtotime($assignment['assigned_date'])) ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php 
                                                    switch($assignment['status']) {
                                                        case 'completed': echo 'bg-success'; break;
                                                        case 'in_progress': echo 'bg-primary'; break;
                                                        case 'pending': echo 'bg-warning text-dark'; break;
                                                        default: echo 'bg-secondary'; break;
                                                    }
                                                    ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $assignment['status'])) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>No batch assignments found for this employee.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Performance Summary -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card border-left-info">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Assignments
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $performance['total_assignments'] ?? 0 ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-clipboard2 fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-left-success">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Completed
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $performance['completed'] ?? 0 ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-left-primary">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            In Progress
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $performance['in_progress'] ?? 0 ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-left-warning">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Completion Rate
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?= $performance['completion_rate'] ?? 0 ?>%
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-graph-up fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
