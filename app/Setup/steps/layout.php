<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Favilla — Setup Guidato</title>
<link rel="stylesheet" href="public/assets/css/setup.css">
</head>
<body>
<div class="wz-card">
    <div class="wz-header">
        <h1>🚀 Favilla — Setup Guidato</h1>
        <p>Configura l'applicazione in pochi minuti</p>
    </div>
    <div class="wz-steps">
        <?php
        $stepLabels = ['1. Requisiti', '2. Database', '3. App', '4. Edizione', '5. Admin', '6. Fine'];
        foreach ($stepLabels as $i => $label):
            $n   = $i + 1;
            $cls = '';
            if ($n < $currentStep) {
                $cls = 'done';
            } elseif ($n === $currentStep) {
                $cls = 'active';
            }
            ?>
        <div class="wz-step <?= $cls ?>"><?= htmlspecialchars($label) ?></div>
        <?php endforeach; ?>
    </div>
    <div class="wz-body">
        <?= $content ?>
    </div>
</div>
</body>
</html>
