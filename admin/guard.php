<?php
    require_once '../includes/session.php';

    //Giris yapilmamissa kullaniciyi giris yapmaya yonlendiriryoruz
    if (!isLoggedIn()) {
        header('Location: ../index.php?modal=login');
        exit;
    }  

    //Kullanici admin degilse ana sayfaya yonlendiriyiozu
    if (!isAdmin()) {
        if (isOrganizer()) {
            header('Location: ../organizer/dashboard.php');
        }else {
            header('Location: ../index.php');
        }
        exit;
    }

    //CSRF token yoksa uretiyoruz
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    //Global CSRF verifiaction function
    function verifyCsrfToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'],$token);
    }

    function enforceAjaxCsrf() {
        $submittedToken = $_POST['csrf_token'] ?? '';
        //CSRF kontrolu
        if (!verifyCsrfToken($submittedToken)) {
            echo json_encode(['status' => 'error' , 'message' => 'Invalid CSRF Token. Request denied.']);
            exit;
        }
    }
?>