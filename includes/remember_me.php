<?php 
    const REMEMBER_COOKIE = 'remember_token';
    const REMEMBER_DAYS = 30;
    const COOKIE_SEPERATOR = ':';

    //Remember_me.php token olusturur , DB ye kaydeder ve cookie ye yazar
    function setRememberToken(PDO $pdo,int $userId) : void {
        $token =bin2hex(random_bytes(32));
        $tokenHash =hash('sha256' , $token);
        $expiresAt = date('Y-m-d H:i:s' , time() + (REMEMBER_DAYS * 24 * 3600));

        //Eski tokeni sil(ayni cihazdan tekrar login)
        $pdo->prepare("DELETE FROM Remember_Tokens WHERE player_id = ?")
            ->execute([$userId]);

        $pdo->prepare("INSERT INTO Remember_Tokens (player_id, token_hash, expires_at)
                        VALUES(?,?,?)")
            ->execute([$userId, $tokenHash, $expiresAt]);

        //Cookie: "userId:plainText_token" formatinda
        $cookieValue = $userId . COOKIE_SEPERATOR . $token;
        $expires = time() + (REMEMBER_DAYS * 24 * 3600);

        setcookie(REMEMBER_COOKIE, $cookieValue , [
            'expires' => $expires,
            'path' => '/',
            'httponly' => true,//JS erisemes XSS korumasi
            'samesite' => 'Lax',//CSRF korumasi
        ]);
    }

    //Cookie varsa dogrular, gecerliyse session baslatir
    //Token rotation uygular: her kontrolde yeni token verilir
    function checkRememberToken(PDO $pdo): bool {
        if (empty($_COOKIE[REMEMBER_COOKIE])) {
            return false;
        }

        $parts = explode(COOKIE_SEPERATOR, $_COOKIE[REMEMBER_COOKIE], 2);
        if (count($parts) !== 2) {
            clearRememberCookie();
            return false;
        }

        [$userId, $token] = $parts;
        $userId = (int) $userId;
        $tokenHash = hash('sha256' , $token);

        $stmt = $pdo->prepare("SELECT * FROM Remember_Tokens
                                WHERE player_id = ?
                                AND token_hash = ?
                                AND expires_at >NOW()");
        $stmt->execute([$userId, $tokenHash]);
        $row = $stmt->fetch();

        if (!$row) {
            clearRememberCookie();
            return false;
        }

        //Kullanici bilgilerini alalim
        $user = $pdo->prepare("SELECT id, username, user_type FROM Player WHERE id = ?");
        $user->execute([$userId]);
        $player = $user->fetch();

        if (!$player) {
            clearRememberCookie();
            return false;
        }

        //SEssion baslat
        $_SESSION['user_id'] = $player['id'];
        $_SESSION['username'] = $player['username'];
        $_SESSION['user_type'] = $player['user_type'];

        setRememberToken($pdo,$userId);

        return true;
    }

    //Tokeni DB den ve cookie den temizler
    function clearRememberToken(PDO $pdo, int $userId): void {
        $pdo->prepare("DELETE FROM Remember_Tokens WHERE player_id = ?")
            ->execute([$userId]);
        clearRememberCookie();
    }   

    function clearRememberCookie(): void {
        setcookie(REMEMBER_COOKIE, '', [
            'expires' => time() - 3600,
            'path'    => '/',
            'httponly'=> true,
            'samesite' => 'Lax',
        ]);
    }
?>