<?php
    require_once '../includes/db.php';
    require_once '../includes/session.php';

    if(isLoggedIn()) { header('Location: /index.php'); exit; }

    $token = trim($_GET['token'] ?? '');
    $tokenHash = $token ? hash('sha256', $token) : '';
    $feedback = '';
    $feedbackType = '';
    $tokenValid = false;
    $userId = null;
    $username = '';
    $done = false;

    if ($tokenHash) {
        try {
            $stmt = $pdo->prepare("
                SELECT prt.id, prt.player_id, p.username
                FROM Password_Reset_Tokens prt
                JOIN Player p ON prt.player_id = p.id
                WHERE prt.token_hash = ?
                    AND prt.expires_at >NOW()
                    AND prt.used_at IS NULL   
            ");
            $stmt->execute([$tokenHash]);
            $row = $stmt->fetch();

            if ($row) {
                $tokenValid = true;
                $userId = $row['player_id'];
                $username = $row['username'];
            }else {
                $feedback = 'This link is invalid or expired!';
                $feedbackType = 'error';
            }
        } catch (Exception $e) {
            $feedback = 'Error occured!';
            $feedbackType = 'error';
        }
    }else {
        $feedback = 'Invalid request!';
        $feedbackType= 'error';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
        $newPassword = $_POST['password']   ?? '';
        $confirm = $_POST['password_confirm']   ?? '';

        if (strlen($newPassword) < 6) {
            $feedback = 'The password must be greater than 6';
            $feedbackType = 'error';
        }else if (!preg_match('/[A-Za-z]/', $newPassword)) {
            $feedback = 'The password must be include at least one letter!';
            $feedbackType = 'error';
        }else if (!preg_match('/\d/' , $newPassword)) {
            $feedback = 'The password must be include at least one digit!';
            $feedbackType = 'error';
        }else if ($newPassword !== $confirm){
            $feedback = 'The password doesnt match with confirm!';
            $feedbackType = 'error';
        }else {
            //Siferelerde sikinti sifreyi guncelleyelim
            try {
                $pdo->beginTransaction();

                $pdo->prepare("UPDATE Player SET password_hash = ? WHERE id = ?")
                    ->execute([password_hash($newPassword, PASSWORD_DEFAULT),$userId]);

                //Tokeni kullanidi olarak isaretleyelim
                $pdo->prepare("UPDATE Password_Reset_Tokens SET used_at = NOW() WHERE token_hash = ?")
                    ->execute([$tokenHash]);

                //Guvenlik icin remember me oturumlarini kapatiyoruz
                $pdo->prepare("DELETE FROM Remember_Tokens WHERE player_id = ?")
                    ->execute([$userId]);

                $pdo->commit();
                $done = true;
            } catch (Exception $e) {
                $pdo->rollBack();
                $feedback = 'Error occured while changing password!';
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
    <title>Reset Password — Ikarus</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/modal.css">
    <link rel="stylesheet" href="../assets/css/auth-layout.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-box">
        <div class="auth-logo">IKARUS</div>
 
        <?php if ($done): ?>
            <div style="text-align:center;font-size:40px;margin-bottom:16px;">✅</div>
            <h2 class="auth-title">Password changed</h2>
            <p class="auth-sub">New password saved. You can login.</p>
            <a href="../index.php?modal=login"
               style="display:block;background:var(--accent);color:var(--bg);padding:12px;
                      border-radius:8px;text-align:center;font-weight:600;text-decoration:none;margin-top:8px;">
                Login
            </a>
 
        <?php elseif (!$tokenValid): ?>
            <div style="text-align:center;font-size:36px;margin-bottom:12px;">⛔</div>
            <h2 class="auth-title">Invalid link</h2>
            <p class="auth-sub"><?= htmlspecialchars($feedback) ?></p>
            <a href="/auth/forgot-password.php" class="auth-back">Request reset again</a>
 
        <?php else: ?>
            <h2 class="auth-title">New Password</h2>
            <p class="auth-sub">
                Hello <strong style="color:var(--accent)"><?= htmlspecialchars($username) ?></strong>,
                Decide your new password.
            </p>
 
            <?php if ($feedback): ?>
                <div class="modal-feedback <?= $feedbackType ?> show-feedback" style="margin-bottom:16px;">
                    <?= htmlspecialchars($feedback) ?>
                </div>
            <?php endif; ?>
 
            <form method="POST" class="modal-form" novalidate>
                <div class="input-group">
                    <input id="rp-password" type="password" name="password"
                           placeholder="" required minlength="6">
                    <label for="rp-password">New Password</label>
                    <button type="button" class="toggle-pass-btn" data-target="rp-password">
                        <img class="toggle-pass-icon" src="../assets/images/toggle_pass_hide.webp" alt="">
                    </button>
                    <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                </div>
 
                <div class="input-group">
                    <input id="rp-confirm" type="password" name="password_confirm"
                           placeholder="" required>
                    <label for="rp-confirm">Password confirm</label>
                    <button type="button" class="toggle-pass-btn" data-target="rp-confirm">
                        <img class="toggle-pass-icon" src="../assets/images/toggle_pass_hide.webp" alt="">
                    </button>
                    <span class="error-msg" id="confirm-error"></span>
                </div>
 
                <button type="submit" class="btn-submit">Change Password</button>
            </form>
            <a href="../index.php?modal=login" class="auth-back">← Back to Login</a>
        <?php endif; ?>
    </div>
</div>
 
<script>
const pwInput = document.getElementById('rp-password');
const fill    = document.getElementById('strength-fill');
if (pwInput) {
    pwInput.addEventListener('input', () => {
        const v = pwInput.value;
        let score = 0;
        if (v.length >= 6)           score++;
        if (v.length >= 10)          score++;
        if (/[A-Z]/.test(v))         score++;
        if (/\d/.test(v))            score++;
        if (/[^A-Za-z0-9]/.test(v))  score++;
        const colors = ['','#f87171','#fbbf24','#facc15','#4ade80','#22c55e'];
        const widths = [0, 20, 40, 60, 80, 100];
        fill.style.width      = widths[score] + '%';
        fill.style.background = colors[score];
    });
}
 
const confirmInput = document.getElementById('rp-confirm');
const confirmError = document.getElementById('confirm-error');
if (confirmInput) {
    confirmInput.addEventListener('input', () => {
        if (confirmInput.value && confirmInput.value !== pwInput.value) {
            confirmError.textContent = 'Sifreler eslesmiyor.';
            confirmError.classList.add('show-error');
        } else {
            confirmError.classList.remove('show-error');
        }
    });
}
 
document.querySelectorAll('.toggle-pass-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.target);
        const icon  = btn.querySelector('.toggle-pass-icon');
        if (!input) return;
        input.type = input.type === 'password' ? 'text' : 'password';
        icon.src   = input.type === 'text'
            ? '../assets/images/toggle_pass_eye.webp'
            : '../assets/images/toggle_pass_hide.webp';
    });
});
</script>
</body>
</html>