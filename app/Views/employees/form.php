<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
<?= $page_title ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-person-plus me-2"></i><?= $page_title ?>
                    </h6>
                    <a href="<?= base_url('/employees') ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to List
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('success')): ?>
                    <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= esc(session()->getFlashdata('success')) ?></div>
                <?php endif; ?>
                <?= form_open(isset($employee) ? '/employees/' . $employee['id'] . '/update' : '/employees/store') ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control <?= session('validation') && session('validation')->hasError('first_name') ? 'is-invalid' : '' ?>" 
                                   id="first_name" 
                                   name="first_name" 
                                   value="<?= old('first_name', $employee['first_name'] ?? '') ?>" 
                                   required>
                            <?php if (session('validation') && session('validation')->hasError('first_name')): ?>
                                <div class="invalid-feedback"><?= session('validation')->getError('first_name') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control <?= session('validation') && session('validation')->hasError('last_name') ? 'is-invalid' : '' ?>" 
                                   id="last_name" 
                                   name="last_name" 
                                   value="<?= old('last_name', $employee['last_name'] ?? '') ?>" 
                                   required>
                            <?php if (session('validation') && session('validation')->hasError('last_name')): ?>
                                <div class="invalid-feedback"><?= session('validation')->getError('last_name') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?= old('phone', $employee['phone'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?= old('email', $employee['email'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="department" class="form-label">Department</label>
                            <select class="form-select" id="department" name="department">
                                <option value="">Select Department</option>
                                <option value="Production" <?= (old('department', $employee['department'] ?? '') === 'Production') ? 'selected' : '' ?>>Production</option>
                                <option value="Quality Control" <?= (old('department', $employee['department'] ?? '') === 'Quality Control') ? 'selected' : '' ?>>Quality Control</option>
                                <option value="Maintenance" <?= (old('department', $employee['department'] ?? '') === 'Maintenance') ? 'selected' : '' ?>>Maintenance</option>
                                <option value="Packing" <?= (old('department', $employee['department'] ?? '') === 'Packing') ? 'selected' : '' ?>>Packing</option>
                                <option value="Stores" <?= (old('department', $employee['department'] ?? '') === 'Stores') ? 'selected' : '' ?>>Stores</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Skills Section -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Skills & Tasks</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addSkillRow()">
                            <i class="bi bi-plus-lg me-1"></i>Add Skill
                        </button>
                    </div>
                    
                    <div id="skillsContainer">
                        <?php 
                        $existingSkills = $skills ?? [];
                        if (!empty($existingSkills)):
                            foreach ($existingSkills as $index => $skill): 
                        ?>
                        <div class="skill-row mb-2">
                            <div class="row">
                                <div class="col-md-6">
                                    <input type="text" 
                                           class="form-control" 
                                           name="skill_names[]" 
                                           value="<?= esc($skill['skill_name']) ?>" 
                                           placeholder="Skill/Task name (e.g., Laser Cutting)">
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select" name="skill_levels[]">
                                        <option value="basic" <?= (isset($skill['proficiency_level']) && $skill['proficiency_level'] === 'basic') ? 'selected' : '' ?>>Basic</option>
                                        <option value="intermediate" <?= (isset($skill['proficiency_level']) && $skill['proficiency_level'] === 'intermediate') ? 'selected' : '' ?>>Intermediate</option>
                                        <option value="advanced" <?= (isset($skill['proficiency_level']) && $skill['proficiency_level'] === 'advanced') ? 'selected' : '' ?>>Advanced</option>
                                        <option value="expert" <?= (isset($skill['proficiency_level']) && $skill['proficiency_level'] === 'expert') ? 'selected' : '' ?>>Expert</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-danger" onclick="removeSkillRow(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php 
                            endforeach;
                        else:
                        ?>
                        <div class="skill-row mb-2">
                            <div class="row">
                                <div class="col-md-6">
                                    <input type="text" 
                                           class="form-control" 
                                           name="skill_names[]" 
                                           placeholder="Skill/Task name (e.g., Laser Cutting)">
                                </div>
                                <div class="col-md-4">
                                    <select class="form-select" name="skill_levels[]">
                                        <option value="basic">Basic</option>
                                        <option value="intermediate">Intermediate</option>
                                        <option value="advanced">Advanced</option>
                                        <option value="expert">Expert</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-danger" onclick="removeSkillRow(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Common skills: Laser Cutting, Metal Forming, Quality Inspection, Packing, Laser Marking, Welding, Assembly, CNC Operation
                        </small>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-secondary me-2" onclick="window.history.back()">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i><?= isset($employee) ? 'Update Employee' : 'Add Employee' ?>
                    </button>
                </div>

                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>

<script>
function addSkillRow() {
    const container = document.getElementById('skillsContainer');
    const skillRow = document.createElement('div');
    skillRow.className = 'skill-row mb-2';
    skillRow.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <input type="text" 
                       class="form-control" 
                       name="skill_names[]" 
                       placeholder="Skill/Task name (e.g., Laser Cutting)">
            </div>
            <div class="col-md-4">
                <select class="form-select" name="skill_levels[]">
                    <option value="basic">Basic</option>
                    <option value="intermediate">Intermediate</option>
                    <option value="advanced">Advanced</option>
                    <option value="expert">Expert</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger" onclick="removeSkillRow(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(skillRow);
}

function removeSkillRow(button) {
    const skillRow = button.closest('.skill-row');
    skillRow.remove();
}
</script>

<?= $this->endSection() ?>
