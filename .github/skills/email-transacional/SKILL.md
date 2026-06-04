---
name: email-transacional
description: 'Implementar ou revisar envio de e-mails transacionais em PHP. Use para: configurar PHPMailer com SMTP, templates HTML de e-mail, e-mails de confirmação, reset de senha, notificações, rate limiting de envio, DKIM/SPF/DMARC, testes de e-mail e boas práticas de entregabilidade.'
argument-hint: 'Descreva o que precisa (ex: "e-mail de reset de senha", "template de boas-vindas", "configurar SMTP com autenticação")'
---

# E-mail Transacional — PHPMailer

## Configuração SMTP

```php
// includes/email.php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function criar_mailer(): PHPMailer
{
    $mail = new PHPMailer(exceptions: true);
    $mail->isSMTP();
    $mail->Host       = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['SMTP_USER'];
    $mail->Password   = $_ENV['SMTP_PASS'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = (int)$_ENV['SMTP_PORT'];
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom($_ENV['MAIL_FROM'], $_ENV['MAIL_FROM_NAME']);
    return $mail;
}
```

```ini
# .env.example
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=noreply@example.com
SMTP_PASS=
MAIL_FROM=noreply@example.com
MAIL_FROM_NAME="Nome do Sistema"
```

## Envio com tratamento de erro

```php
function enviar_email(string $para, string $assunto, string $htmlBody, string $textoPlano = ''): bool
{
    try {
        $mail = criar_mailer();
        $mail->addAddress(filter_var($para, FILTER_VALIDATE_EMAIL) ? $para : throw new InvalidArgumentException('E-mail inválido.'));
        $mail->Subject = $assunto;
        $mail->isHTML(true);
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textoPlano ?: strip_tags($htmlBody);
        $mail->send();
        return true;
    } catch (Exception $e) {
        logar_erro('email_falhou', ['para' => $para, 'erro' => $mail->ErrorInfo]);
        return false;
    }
}
```

## Template HTML de e-mail

```php
// includes/email_template.php
function template_email(string $titulo, string $conteudo, string $rodape = ''): string
{
    $titulo   = htmlspecialchars($titulo,   ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $rodape   = htmlspecialchars($rodape,   ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    // conteúdo HTML já deve estar sanitizado antes de chamar esta função

    return <<<HTML
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>{$titulo}</title>
    </head>
    <body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center" style="padding:40px 0;">
          <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;">
            <tr><td style="background:#1a73e8;padding:24px 32px;">
              <h1 style="margin:0;color:#fff;font-size:20px;">{$titulo}</h1>
            </td></tr>
            <tr><td style="padding:32px;">
              {$conteudo}
            </td></tr>
            <tr><td style="background:#f4f4f4;padding:16px 32px;font-size:12px;color:#666;">
              {$rodape}
            </td></tr>
          </table>
        </td></tr>
      </table>
    </body>
    </html>
    HTML;
}
```

## E-mail de reset de senha

```php
function enviar_reset_senha(string $email, string $token): bool
{
    $link = htmlspecialchars(BASE_URL . '/redefinir-senha?token=' . $token, ENT_QUOTES, 'UTF-8');

    $conteudo = <<<HTML
    <p>Recebemos uma solicitação para redefinir sua senha.</p>
    <p>Clique no botão abaixo. O link é válido por <strong>1 hora</strong> e pode ser usado apenas uma vez.</p>
    <p style="text-align:center;margin:32px 0;">
      <a href="{$link}" style="background:#1a73e8;color:#fff;padding:12px 24px;border-radius:4px;text-decoration:none;">
        Redefinir senha
      </a>
    </p>
    <p style="font-size:13px;color:#666;">Se não foi você, ignore este e-mail. Sua senha não será alterada.</p>
    HTML;

    return enviar_email(
        para: $email,
        assunto: 'Redefinição de senha',
        htmlBody: template_email('Redefinição de senha', $conteudo, 'Este é um e-mail automático, não responda.'),
    );
}
```

## Rate limiting de envio

```php
function verificar_rate_email(PDO $pdo, string $email, string $tipo, int $max = 3, int $janela = 3600): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM emails_enviados
        WHERE email = :email AND tipo = :tipo AND criado_em > DATE_SUB(NOW(), INTERVAL :janela SECOND)
    ");
    $stmt->execute([':email' => $email, ':tipo' => $tipo, ':janela' => $janela]);
    return (int)$stmt->fetchColumn() < $max;
}

function registrar_envio(PDO $pdo, string $email, string $tipo): void
{
    $stmt = $pdo->prepare("INSERT INTO emails_enviados (email, tipo, criado_em) VALUES (:email, :tipo, NOW())");
    $stmt->execute([':email' => $email, ':tipo' => $tipo]);
}
```

## Entregabilidade — DNS

| Registro | Propósito |
|----------|-----------|
| **SPF** | Autorizar servidores a enviar pelo domínio |
| **DKIM** | Assinar e-mails criptograficamente |
| **DMARC** | Política de falha + relatórios |

```
# Exemplos de registros DNS
v=spf1 include:_spf.provedor.com ~all
v=DKIM1; k=rsa; p=<chave_publica>
v=DMARC1; p=quarantine; rua=mailto:dmarc@seudominio.com
```

## Testes

- Desenvolvimento: usar [Mailpit](https://github.com/axllent/mailpit) ou Mailtrap para capturar e-mails sem enviar
- Verificar renderização em clientes reais: Gmail, Outlook, Apple Mail
- Testar modo texto alternativo (`AltBody`)
- Verificar links de rastreamento e tokens antes de enviar para produção

## Nunca fazer

- Usar `mail()` nativa em produção
- Credenciais SMTP hardcoded no código
- Token de reset em claro no banco (armazenar `hash('sha256', $token)`)
- Incluir dados pessoais sensíveis no assunto do e-mail (aparecem em notificações)
- Enviar sem rate limiting (risco de abuso e blacklist)
- HTML gerado diretamente de input do usuário sem sanitização
