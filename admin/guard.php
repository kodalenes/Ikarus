<?php
    require_once '../includes/session.php';

    //Giris yapilmamissa kullaniciyi giris yapmaya yonlendiriryoruz
    if (!isLoggedIn()) {
        header('Location: ../pages/index.php?modal=login');
        exit;
    }  

    //Kullanici admin degilse ana sayfaya yonlendiriyiozu
    if (!isAdmin()) {
        if (isOrganizer()) {
            header('Location: ../organizer/dashboard.php');
        }else {
            header('Location: ../pages/index.php');
        }
        exit;
    }
?>