<?php
    require_once '../includes/db.php';
    require_once '../includes/mailer.php';
    require_once '../includes/session.php';

    if (isLoggedIn()) { header('Location: /index.php'); exit; }

    $feedback = '';
    $feedbackType = '';
    $submitted = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim(filter_var($_POST['email'], FILTER_VALIDATE_EMAIL));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $feedback = 'Enter valid email address';
            $feedbackType = 'error';
        }else {
            try {
                $stmt = $pdo->prepare("SELECT id, username FROM Player WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    //Eski tokenlari temizle
                    $pdo->prepare("DELETE FROM Password_Reset_Tokens WHERE player_id = ?")
                    ->execute([$user['id']]);

                    //Yeni token
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);

                    $pdo->prepare("
                        INSERT INTO Password_Reset_Tokens (player_id, token_hash, expires_at)
                        VALUES (?, ? , DATE_ADD(NOW(), INTERVAL 1 HOUR))"
                    )->execute([$user['id'], $tokenHash]);

                    $appUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost', '/');
                    $resetUrl = "{$appUrl}/Ikarus/auth/reset-password.php?token={$token}";

                    $html = buildResetEmail($user['username'], $resetUrl);
                    if (!sendMail($email, $user['username'], 'Reset Password - Ikarus', $html)) {
                        $feedback = "Mail cannot sent! Please check logs.";
                        $feedbackType = "error";
                        $submitted = false; // Başarısızsa kutuyu gösterme
                    }
                }

                $submitted = true;

            } catch (Exception $e) {
                error_log($e->getMessage());
                $feedback = 'Error occured, please try again!';
                $feedbackType = 'error';
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Ikarus</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/modal.css">
    <link rel="stylesheet" href="../assets/css/auth-layout.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-box">
            <div class="auth-logo">IKARUS</div>

            <?php if($submitted): ?>
                <div class="auth-succes-icon">📬</div>
                <h2 class="auth-title">Mail Send</h2>
                <p class="auth-sub">
                    If this email is registered, a reset mail has been sent. <br>
                    Also check spam folder.
                </p>
                <a href="../index.php?modal=login" class="auth-back">← Back to Login</a>
            <?php else: ?>
                <h2 class="auth-title">Forgot Password</h2>
                <p class="auth-sub">Enter registered email address, we send reset password link.</p>

                <?php if ($feedback): ?>
                    <div class="modal-feedback <?= $feedbackType ?> show-feedback" style="margin-bottom:16px;">
                        <?= htmlspecialchars($feedback) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="modal-form" novalidate>
                    <div class="input-group">
                        <input class="reset-mail" type="email" name="email" placeholder=""
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                required autofocus>
                        <label for="reset-mail">Email Address</label>
                    </div>
                    <button type="submit" class="btn-submit">Send Reset Link</button>
                </form>
                <a href="../index.php?modal=login" class="auth-back">← Back to Login</a>
            <?php endif; ?>
        </div>
    </div>
    
</body>
</html>