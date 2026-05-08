<!-- Footerda tum JS dosyalari yuklenir-->
 <footer class="site-footer">
    <div class="footer-inner">
        <span class="footer-logo">IKARUS</span>
        <p class="footer-text">&copy; 2026 Ikarus Tournament Platform. All rights reserved.</p>
    </div>

<div class="music-control-wrapper">
    <audio id="bgMusic" loop>
        <source src="../assets/images/VOLKSWAGEN FUNK.ogg" type="audio/mpeg">
    </audio>
    
    <button id="musicToggle" class="music-btn" title="Müziği Başlat/Durdur">
        <span id="musicStatusIcon">🎵</span>
    </button>
</div>

<script>
    const music = document.getElementById('bgMusic');
    const btn = document.getElementById('musicToggle');
    const icon = document.getElementById('musicStatusIcon');

    btn.addEventListener('click', () => {
        if (music.paused) {
            music.play();
            btn.classList.add('playing');
            icon.innerText = "⏸️";
        } else {
            music.pause();
            btn.classList.remove('playing');
            icon.innerText = "🎵";
        }
    });
    
    //Ikarus Arka Plan Motoru (Sessiz Cron Tetikleyici)
    // Sayfa yüklendikten 5 saniye sonra kullanıcıyı hiç yormadan arka planda çalışır
    setTimeout(function() {
        fetch('https://ikarusgg.me/cron.php?key=/Enes5384&i=1')
            .then(response => console.log('Ikarus Engine Checked'))
            .catch(error => console.log('Engine Skip'));
    }, 5000);

</script>

<?php $currentPage = basename($_SERVER['PHP_SELF'] ?? ''); ?>
<?php if ($currentPage !== 'team.php'): ?>
 <script src="../assets/js/header-notifications.js"></script>
<?php endif; ?>
 <script src="../assets/js/FormValidator.js"></script>
 <script src="../assets/js/utils.js"></script>
 <script src="../assets/js/modal-handler.js"></script>
