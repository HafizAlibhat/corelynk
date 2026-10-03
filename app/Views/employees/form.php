<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
<?= $page_title ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="row cl-form-page">
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
                <?= form_open_multipart(isset($employee) ? '/employees/' . $employee['id'] . '/update' : '/employees/store') ?>
                
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
                            <div class="form-text">If a login is linked to this employee, the login's email is used as the main one and this becomes the secondary email.</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="department" class="form-label">Department</label>
                            <?php $selectedDept = old('department', $employee['department'] ?? ''); ?>
                            <input type="text" class="form-control" id="department" name="department"
                                   list="departmentOptions" maxlength="50" autocomplete="off"
                                   value="<?= esc($selectedDept) ?>"
                                   placeholder="Pick one, or type a new department">
                            <datalist id="departmentOptions">
                                <?php foreach (($departments ?? []) as $dept): ?>
                                    <option value="<?= esc($dept, 'attr') ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <div class="form-text">Not in the list? Just type it — the new department is saved with the employee.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="photo" class="form-label">Profile picture</label>
                            <div class="d-flex align-items-center gap-3">
                                <?php $photo = $employee['photo_path'] ?? null; ?>
                                <img id="photoPreview"
                                     src="<?= $photo ? base_url($photo) : 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==' ?>"
                                     alt="" class="rounded-circle border <?= $photo ? '' : 'd-none' ?>"
                                     style="width:56px;height:56px;object-fit:cover;">
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control" id="photo" name="photo"
                                           accept="image/jpeg,image/png,image/webp">
                                    <div class="form-text">JPG, PNG or WEBP, up to 5 MB.</div>
                                    <?php if ($photo): ?>
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="removePhoto">
                                            <label class="form-check-label small" for="removePhoto">Remove current picture</label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php helper('currency'); $catalog = currency_catalog(); $baseCode = base_currency_code(); ?>
                <hr class="my-4">
                <h6 class="mb-3"><i class="bi bi-cash-stack me-2"></i>Employment &amp; Salary</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="designation" class="form-label">Designation</label>
                            <input type="text" class="form-control" id="designation" name="designation"
                                   value="<?= esc(old('designation', $employee['designation'] ?? '')) ?>"
                                   placeholder="Machine Operator, Supervisor…">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="joining_date" class="form-label">Joining Date</label>
                            <input type="date" class="form-control" id="joining_date" name="joining_date"
                                   value="<?= esc(old('joining_date', $employee['joining_date'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="monthly_salary" class="form-label">Monthly Salary</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="monthly_salary" name="monthly_salary"
                                   value="<?= esc(old('monthly_salary', $employee['monthly_salary'] ?? '')) ?>">
                            <div class="form-text">Used to prepare each month's salary slip. Leave blank if not on a fixed salary.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="salary_currency" class="form-label">Salary Currency</label>
                            <select class="form-select" id="salary_currency" name="salary_currency">
                                <?php $selectedCurrency = old('salary_currency', $employee['salary_currency'] ?? $baseCode); ?>
                                <?php foreach ($catalog as $code => $info): ?>
                                    <option value="<?= esc($code) ?>" <?= $selectedCurrency === $code ? 'selected' : '' ?>><?= esc($code) ?></option>
                                <?php endforeach; ?>
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

// Show the picked picture before saving, so the wrong file is obvious.
document.getElementById('photo').addEventListener('change', function () {
    const img = document.getElementById('photoPreview');
    if (!this.files || !this.files[0]) { return; }
    img.src = URL.createObjectURL(this.files[0]);
    img.classList.remove('d-none');
});

function removeSkillRow(button) {
    const skillRow = button.closest('.skill-row');
    skillRow.remove();
}
</script>

<?= $this->endSection() ?>
