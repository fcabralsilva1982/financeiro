<!DOCTYPE html>
<html lang="<?= CURRENT_LANG === 'pt-br' ? 'pt-BR' : 'en' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Selecionar Empresa — <?= e(__('app.name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><text y='14' font-size='14'>💰</text></svg>">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" integrity="sha384-XGjxtQfXaH2tnPFa9x+ruJTuLE3Aa6LhHSWRr1XeTyhezb4abCG4ccI5AkVDxqC+" crossorigin="anonymous">
    <link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body>
<?php $empresasDisponiveis = $empresasDisponiveis ?? []; ?>
<div class="login-wrapper">
    <div class="card login-card" style="max-width: 680px; width: 100%;">
        <div class="card-body">
            <div class="text-center mb-4">
                <div class="login-logo mb-3">
                    <i class="bi bi-buildings"></i>
                </div>
                <h4 class="fw-bold mb-1">Selecione a Empresa</h4>
                <p class="text-muted mb-0">Escolha em qual empresa voce deseja entrar.</p>
            </div>

            <?= flash_message() ?>

            <form action="<?= url('login', 'choose_empresa') ?>" method="POST">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <?php foreach ($empresasDisponiveis as $empresa): ?>
                        <div class="col-md-6">
                            <label class="card h-100 border rounded p-3" style="cursor: pointer;">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="empresa_id" value="<?= e($empresa['empresa_id']) ?>" required>
                                    <span class="form-check-label fw-semibold ms-2"><?= e($empresa['empresa_nome']) ?></span>
                                </div>
                                <div class="small text-muted mt-2 ms-4">
                                    <div><i class="bi bi-upc me-1"></i><?= e($empresa['empresa_codigo']) ?></div>
                                    <div><i class="bi bi-shield me-1"></i><?= e($empresa['perfil_nome']) ?></div>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i>Entrar na Empresa Selecionada
                    </button>
                </div>
            </form>

            <div class="text-center mt-3">
                <a href="<?= url('login', 'logout') ?>" class="small text-muted text-decoration-none">Cancelar e sair</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
