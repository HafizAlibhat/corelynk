<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
<?= $title ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><?= $title ?></h2>
                <a href="/workflow-templates" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>

            <?php if (session()->getFlashdata('errors')): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach (session()->getFlashdata('errors') as $error): ?>
                            <li><?= esc($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/workflow-templates/store" id="workflowForm">
                        <?= csrf_field() ?>
                        
                        <!-- Basic Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Workflow Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= old('name') ?>" required>
                                <div class="form-text">e.g., "Tweezer Manufacturing", "PCB Assembly"</div>
                            </div>
                            <div class="col-md-6">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" <?= old('category_id') == $category['id'] ? 'selected' : '' ?>>
                                            <?= esc($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= old('description') ?></textarea>
                                <div class="form-text">Describe what this workflow accomplishes</div>
                            </div>
                        </div>

                        <!-- Workflow Steps -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Workflow Steps</h5>
                                <button type="button" class="btn btn-primary btn-sm" onclick="addStep()">
                                    <i class="fas fa-plus"></i> Add Step
                                </button>
                            </div>

                            <div id="stepsContainer">
                                <!-- Steps will be added here dynamically -->
                            </div>

                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i>
                                <strong>Tip:</strong> Add steps in the order they should be performed. You can reorder them by dragging.
                            </div>
                        </div>

                        <!-- Hidden input for steps JSON -->
                        <input type="hidden" name="steps" id="stepsData">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="/workflow-templates" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Workflow Template
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let stepCounter = 0;
const processes = <?= json_encode($processes) ?>;

function addStep() {
    stepCounter++;
    const stepHtml = `
        <div class="step-item border rounded p-3 mb-3" data-step="${stepCounter}">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h6 class="mb-0">Step ${stepCounter}</h6>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeStep(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Process <span class="text-danger">*</span></label>
                    <select class="form-select process-select" required>
                        <option value="">Select Process</option>
                        ${processes.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Est. Time (minutes)</label>
                    <input type="number" class="form-control time-input" min="0" value="0">
                </div>
                <div class="col-md-3">
                    <div class="form-check mt-4">
                        <input class="form-check-input qc-check" type="checkbox">
                        <label class="form-check-label">QC Required</label>
                    </div>
                </div>
            </div>
            
            <div class="row mt-2">
                <div class="col-12">
                    <label class="form-label">Step Description</label>
                    <textarea class="form-control description-input" rows="2" placeholder="Additional instructions for this step..."></textarea>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('stepsContainer').insertAdjacentHTML('beforeend', stepHtml);
    updateStepsData();
}

function removeStep(button) {
    button.closest('.step-item').remove();
    renumberSteps();
    updateStepsData();
}

function renumberSteps() {
    const steps = document.querySelectorAll('.step-item');
    steps.forEach((step, index) => {
        const stepNumber = index + 1;
        step.querySelector('h6').textContent = `Step ${stepNumber}`;
        step.dataset.step = stepNumber;
    });
}

function updateStepsData() {
    const steps = [];
    document.querySelectorAll('.step-item').forEach((stepElement, index) => {
        const processSelect = stepElement.querySelector('.process-select');
        const timeInput = stepElement.querySelector('.time-input');
        const qcCheck = stepElement.querySelector('.qc-check');
        const descriptionInput = stepElement.querySelector('.description-input');
        
        if (processSelect.value) {
            steps.push({
                step_number: index + 1,
                process_template_id: parseInt(processSelect.value),
                estimated_time_minutes: parseInt(timeInput.value) || 0,
                qc_required: qcCheck.checked,
                description: descriptionInput.value
            });
        }
    });
    
    document.getElementById('stepsData').value = JSON.stringify(steps);
}

// Add event listeners to update steps data when inputs change
document.addEventListener('change', function(e) {
    if (e.target.matches('.process-select, .time-input, .qc-check, .description-input')) {
        updateStepsData();
    }
});

// Add at least one step by default
document.addEventListener('DOMContentLoaded', function() {
    addStep();
});

// Form validation
document.getElementById('workflowForm').addEventListener('submit', function(e) {
    const stepsData = document.getElementById('stepsData').value;
    if (!stepsData || JSON.parse(stepsData).length === 0) {
        e.preventDefault();
        alert('Please add at least one step to the workflow.');
        return false;
    }
});
</script>
<?= $this->endSection() ?>
