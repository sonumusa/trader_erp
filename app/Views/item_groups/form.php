<?php /** @var app\Core\View $this */
$group = $this->data['group'];
$accounts = $this->data['accounts'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Edit Item Group — <?= $this->e($group['name']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/item-groups') ?>">Item Groups</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="erp-card">
            <div class="erp-card-body">
                <form method="post" action="<?= $this->url('/item-groups/' . (int) $group['id']) ?>">
                    <?= $this->csrfField() ?>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="name">Group name <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required maxlength="120" value="<?= $this->e($group['name']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="code">Code</label>
                            <input type="text" class="form-control" id="code" name="code" maxlength="30" value="<?= $this->e($group['code']) ?>">
                        </div>
                        <?php foreach ([
                            'inventory_account_id' => 'Default Inventory account',
                            'cogs_account_id' => 'Default COGS account',
                            'sales_account_id' => 'Default Sales account',
                            'purchase_account_id' => 'Default Purchase account',
                        ] as $field => $label): ?>
                            <div class="col-md-6">
                                <label class="form-label" for="<?= $field ?>"><?= $label ?></label>
                                <select class="form-select" id="<?= $field ?>" name="<?= $field ?>">
                                    <option value="">— Company default —</option>
                                    <?php foreach ($accounts as $acc): ?>
                                        <option value="<?= (int) $acc['id'] ?>" <?= (int) ($group[$field] ?? 0) === (int) $acc['id'] ? 'selected' : '' ?>>
                                            <?= $this->e($acc['code'] . ' — ' . $acc['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> Save Changes</button>
                        <a href="<?= $this->url('/item-groups') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
