<?php 
    require_once '../includes/db.php';

    session_start();
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = filter_var($_POST['email'] ?? '' , FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            echo json_encode(['status' => 'error' , 'message' => 'Please fill all fields']);
            exit;
        }

        try {
            $sql = "SELECT id ,username ,password_hash , user_type 
                FROM Player 
                WHERE email = :email";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password , $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];

                echo json_encode(['status' => 'success' , 'message' => 'Login succesfull!']);
            }else {
                echo json_encode(['status' => 'error' , 'message' => 'Email or password is incorrect.!']);
            }

        } catch (PDOException $e) {
            echo json_encode(['status' => 'error' , 'message' => 'System error occured!']);
        }
        exit();
    }
?>

