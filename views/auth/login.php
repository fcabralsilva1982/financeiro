<!DOCTYPE html>
<html lang="<?= CURRENT_LANG === 'pt-br' ? 'pt-BR' : 'en' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?= e(__('login.title')) ?> — <?= e(__('app.name')) ?></title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><text y='14' font-size='14'>💰</text></svg>">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body>

<div class="login-wrapper">
    <div class="card login-card">
        <div class="card-body text-center">
            <!-- Logo -->
            <div class="login-logo mb-3">
                <i class="bi bi-wallet2"></i>
            </div>
            <h4 class="fw-bold mb-1"><?= e(__('app.name')) ?></h4>
            <p class="text-muted mb-4"><?= e(__('app.subtitle')) ?></p>

            <!-- Flash messages -->
            <?= flash_message() ?>

            <!-- Login Form -->
            <form action="<?= url('login', 'authenticate') ?>" method="POST">
                <?= csrf_field() ?>

                <div class="mb-3 text-start">
                    <label for="email" class="form-label"><?= e(__('login.email')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="<?= e(__('login.placeholder_email')) ?>" required>
                    </div>
                </div>

                <div class="mb-3 text-start">
                    <label for="password" class="form-label"><?= e(__('login.password')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="<?= e(__('login.placeholder_password')) ?>" required>
                    </div>
                </div>

                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember">
                        <label class="form-check-label small" for="remember"><?= e(__('login.remember')) ?></label>
                    </div>
                    <a href="#" class="small text-muted text-decoration-none disabled"><?= e(__('login.forgot')) ?></a>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="bi bi-box-arrow-in-right me-1"></i><?= e(__('login.submit')) ?>
                </button>
            </form>

            <!-- Demo: credenciais de teste -->
            <div class="border-top pt-3 mt-2">
                <p class="small text-muted mb-2">
                    <i class="bi bi-info-circle me-1"></i><?= e(__('login.demo_title')) ?>
                </p>
                <div class="small text-muted text-start" style="font-size: 0.8rem;">
                    <div class="mb-1"><strong>Admin Master:</strong> joao.silva@alphatech.com.br / admin123</div>
                    <div><strong>Empresa Cliente:</strong> maria.santos@betastore.com.br / user123</div>
                </div>
            </div>

            <!-- Language switch -->
            <div class="mt-3">
                <a href="<?= lang_switch_url() ?>" class="small text-muted text-decoration-none">
                    <i class="bi bi-translate me-1"></i><?= current_lang_label() === 'PT-BR' ? 'English' : 'Português' ?>
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
