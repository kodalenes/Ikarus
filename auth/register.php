<?php
    require_once '../includes/db.php';
  
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $user = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        $checkSql = "SELECT * FROM Player WHERE username = ? OR email = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$user , $email]);

        //rowcount() sorgudan donen satir sayisini verir
        //0 dan buyukse girilen bilgilerle bir kayit var demektir
        if ($checkStmt->rowCount() > 0) {
            echo "Error : This information has already been used to register!";
        }else {
            $hashed_pass = password_hash($password ,PASSWORD_DEFAULT);

            //Kullaniciyi db ye ekleme
            $insertSql = "INSERT INTO Player (username ,email ,password_hash) VALUES (? ,? ,?)";
            $insertStmt = $pdo->prepare($insertSql);

            if ($insertStmt->execute([$user ,$email, $hashed_pass])) {
                echo "Registration successfully created!";
            }
        }
    }
?>
