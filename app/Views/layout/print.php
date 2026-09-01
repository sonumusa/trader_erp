<?php /** @var app\Core\View $this */
$title = $this->data['title'] ?? 'Document';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= $this->e($title) ?></title>
<link rel="stylesheet" href="<?= $this->asset('vendor/bootstrap.min.css') ?>">
<style>
    @media print {
        .no-print { display: none !important; }
        body { font-size: 12px; }
    }
    body { background: #fff; font-family: "Segoe UI", Arial, sans-serif; }
    .print-head { border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 16px; }
    .print-title { font-size: 20px; font-weight: 800; letter-spacing: .5px; }
    .print-table th { background: #f1f5f9 !important; font-size: 11px; text-transform: uppercase; }
    .print-table td, .print-table th { padding: 7px 10px; }
    .print-sign { margin-top: 56px; }
    .print-sign .col { border-top: 1px solid #999; }
</style>
</head>
<body>
<div class="container-fluid p-3">
    <?= $this->content() ?>
</div>
<div class="text-center mt-4 no-print">
    <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    <a class="btn btn-outline-secondary" href="<?= $this->e($this->data['backUrl'] ?? 'javascript:history.back()') ?>">Back</a>
</div>
</body>
</html>
