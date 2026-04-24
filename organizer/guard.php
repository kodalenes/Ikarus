<?php
    /** Organizator Paneli icin kontrol 
     * Her organizer sayfasinin basina require ince yapilir
     * Oturum yoksa login yap yetki yoksa index e gonder
    */

    require_once '../includes/session.php';

    //Giris yapilmamissa giris yap
    if (!isLoggedIn()) {
        header('Location: /pages/index.php?modal=login');
        exit;
    }

    //Yetkin yoksa ana menuye git
    if (!isOrganizer() && !isAdmin()) {
        header('Location: /pages/index.php');
        exit;
    }
?>