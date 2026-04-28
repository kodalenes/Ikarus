<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    require_once __DIR__ . '/../vendor/autoload.php';

    function loadEnv(string $path) : void {
        if (!file_exists($path)) { return;}

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            if (!str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }

    loadEnv(__DIR__ . '/../.env');

    function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody) : bool {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? 'sandbox.smtp.mailtrap.io';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USERNAME'] ?? '';
            $mail->Password = $_ENV['MAIL_PASSWORD'] ?? '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port= (int)($_ENV['MAIL_PORT'] ?? 2525);
            $mail->CharSet = 'UTF-8';

            $mail->setFrom(
                $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@ikarus.gg',
                $_ENV['MAIL_FROM_NAME'] ?? 'Ikarus'
            );
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer Error: ' . $mail->ErrorInfo);
            return false;
        }
    }

function buildResetEmail(string $username, string $resetUrl): string {
    $appName = $_ENV['APP_NAME'] ?? 'Ikarus';
    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#1e1e1e;font-family:Arial,sans-serif;">
      <div style="max-width:480px;margin:40px auto;background:#2f2f2f;border-radius:12px;
                  border:1px solid #3a3a3a;overflow:hidden;">
        <div style="background:#272727;padding:24px 32px;border-bottom:1px solid #3a3a3a;">
          <span style="color:#82c0cc;font-size:18px;font-weight:700;letter-spacing:4px;">{$appName}</span>
        </div>
        <div style="padding:32px;">
          <h2 style="color:#ede7e3;margin:0 0 12px;">Reset Password</h2>
          <p style="color:#a09d99;line-height:1.6;margin:0 0 24px;">
            Hello <strong style="color:#ede7e3;">{$username}</strong>,<br>
            A password reset request has been submitted for your account.
            You can set a new password by clicking the button below.
          </p>
          <a href="{$resetUrl}"
             style="display:inline-block;background:#489fb5;color:#272727;
                    padding:12px 28px;border-radius:8px;text-decoration:none;
                    font-weight:700;font-size:14px;letter-spacing:1px;">
            Sifremi Sifirla
          </a>
          <p style="color:#6b6880;font-size:12px;margin:24px 0 0;line-height:1.6;">
            This link available for <strong>1 hour</strong>.<br>
            If you did not make this request, please disregard this email.
          </p>
        </div>
        <div style="padding:16px 32px;border-top:1px solid #3a3a3a;text-align:center;">
          <span style="color:#6b6880;font-size:11px;">&copy; 2026 {$appName} Tournament Platform</span>
        </div>
      </div>
    </body>
    </html>
    HTML;
}
    
