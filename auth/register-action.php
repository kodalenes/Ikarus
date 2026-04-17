<?php
    require_once '../includes/db.php';
    session_start();

    //Dosyanin json dondurecegini belirtir
    header('Content-Type: application/json');
  
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        //Formdan gelen verileri degiskenlere atama
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $user_type = $_POST['user_type'] ?? 'player';

        //Alanlar bos ise veritabani kontrolu yapilmaz Hata mesaji belirlenir.
        if (empty($username) || empty($email) || empty($password)) {
            echo json_encode(['status' => 'error' , 'message' => 'Please fill al fields!']);
            exit;
        }

        //Username dogrulamasi
        if (!preg_match("/^[a-zA-Z0-9_]{4,20}$/", $username)) {
            echo json_encode(['status' => 'error' , 'message' => 'The username contains invalid characters!']);
            exit;
        }

        //Email format kontrolu
        if (!filter_var($email,FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error' , 'message' => 'Invalid email format!']);
            exit;
        }

        //Sifre gucu dogrulamasi
        if (strlen($password) < 6) {
            echo json_encode(['status' => 'error' , 'message' => 'Password must be at least 6 characters!']);
            exit;
        }
        if (!preg_match("/[A-Za-z]/", $password)) {
            echo json_encode(['status' => 'error' , 'message' => 'Security warning: The password must contain at least one letter!']);
            exit;
        }
        if (!preg_match("/\d/", $password)) {
            echo json_encode(['status' => 'error' , 'message' => 'Security warning: The password must contain at least one digit!']);
            exit;
        }


        try {
            //Veritabani sorgusu gucenli sekilde calistirilir
            $checkSql = "SELECT * FROM Player WHERE username = ? OR email = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$username , $email]);

            //fetch den true donerse bu isimde veya email ile kullanici vardir ve yeni kayit olusturulamaz 
            //geribildirim yazilir false donerse kayit yoktur ve kayit ekleme kismina gecilir.
            if ($checkStmt->fetch()) {
                echo json_encode(['status' => 'error' , 'message' => 'Username or email have already taken!']);
                exit;
            }

            //Kullanicnin parolasi tek yonlu sifrelenir Ve veritabaninda gucenli sekilde saklanir 
            $hashed_password = password_hash($password , PASSWORD_DEFAULT);

            //Kullaniciyi veritabanina ekleme icin sql kodu
            $insertSql = "INSERT INTO Player (username ,email ,password_hash ,user_type) 
                        VALUES (:username ,:email ,:pass , :user_type)";
            $insertStmt = $pdo->prepare($insertSql);
            $result = $insertStmt->execute([
                'username' => $username,
                'email' => $email,
                'pass' => $hashed_password,
                'user_type' => $user_type
            ]);

            //Sorunusuz calisirsa geri bildirim yazilir.
            if ($result) {
                echo json_encode(['status' => 'success' , 'message' => 'Register successfull!']);
            }
        } catch (PDOException $e) {//Veritabaninda sikinti cikarsa geribildirim yazilir.
            echo json_encode(['status' => 'error' , 'message' => 'Database error occured!']);
        }
    }
?>

