<?php
    require_once '../includes/db.php';
    session_start();
    header('Content-Type: application/json');
  
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $user_type = $_POST['user_type'] ?? 'player';

        if (empty($username) || empty($email) || empty($password)) {
            echo json_encode(['status' => 'error' , 'message' => 'Please fill al fields!']);
            exit;
        }

        try {
            $checkSql = "SELECT * FROM Player WHERE username = ? OR email = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$username , $email]);

            if ($checkStmt->fetch()) {
                echo json_encode(['status' => 'error' , 'message' => 'Username or email have already taken!']);
                exit;
            }

            $hashed_password = password_hash($password , PASSWORD_DEFAULT);

            $insertSql = "INSERT INTO Player (username ,email ,password_hash ,user_type) 
                        VALUES (:username ,:email ,:pass , :user_type)";
            $insertStmt = $pdo->prepare($insertSql);
            $result = $insertStmt->execute([
                'username' => $username,
                'email' => $email,
                'pass' => $hashed_password,
                'user_type' => $user_type
            ]);

            if ($result) {
                echo json_encode(['status' => 'success' , 'message' => 'Register successfull!']);
            }
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error' , 'message' => 'Database error occured!']);
        }
    }
?>

