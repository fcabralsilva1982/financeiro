<!DOCTYPE html>
<html lang="<?= CURRENT_LANG === 'pt-br' ? 'pt-BR' : 'en' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?= e(__('termos.titulo_pagina')) ?> — <?= e(__('app.name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><text y='14' font-size='14'>💰</text></svg>">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body>
<div class="login-wrapper">
    <div class="card login-card" style="max-width: 860px; width: 100%;">
        <div class="card-body">
            <div class="text-center mb-4">
                <div class="login-logo mb-3">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <h4 class="fw-bold mb-1"><?= e(__('termos.titulo')) ?></h4>
                <p class="text-muted mb-0"><?= e(__('termos.subtitulo')) ?> <span class="badge bg-secondary ms-1">v<?= e(TERMS_CURRENT_VERSION) ?></span></p>
            </div>

            <?= flash_message() ?>

            <div class="border rounded p-3 mb-3" style="max-height: 320px; overflow:auto; background: #fafafa;">
                <h6><?= e(__('termos.secao_1_titulo')) ?></h6>
                <p class="small text-muted mb-3"><?= e(__('termos.secao_1_texto')) ?></p>

                <h6><?= e(__('termos.secao_2_titulo')) ?></h6>
                <p class="small text-muted mb-3"><?= e(__('termos.secao_2_texto')) ?></p>

                <h6><?= e(__('termos.secao_3_titulo')) ?></h6>
                <p class="small text-muted mb-3"><?= e(__('termos.secao_3_texto')) ?></p>

                <h6><?= e(__('termos.secao_4_titulo')) ?></h6>
                <p class="small text-muted mb-0"><?= e(__('termos.secao_4_texto')) ?></p>
            </div>

            <form action="<?= url('login', 'aceitar_termos') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="aceite_termos" name="aceite_termos" value="1" required>
                    <label class="form-check-label" for="aceite_termos">
                        <?= e(__('termos.checkbox_aceite')) ?>
                    </label>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i><?= e(__('termos.botao_aceitar')) ?>
                    </button>
                    <a href="<?= url('login', 'logout') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-box-arrow-right me-1"></i><?= e(__('termos.botao_sair')) ?>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
