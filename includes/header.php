<?php
    require_once __DIR__ . "/session.php";
?>

<header class="site-header">
    <div class="header-inner">

        <a href="../pages/index.php" class="logo">
            <div class="logo-icon">
                <img src="" alt="Logo">
            </div>
            IKARUS
        </a>

        <nav class="nav-links">
            <a href="../pages/tournaments.php">Tournaments</a>
            <a href="../pages/team.php">Team</a>
            <a href="../pages/leaderboard">Leaderboard</a>
        </nav>

        <div class="header-auth">
            <?php if (isLoggedIn()): ?>
                <!-- Kullanıcı giriş yapmışsa: adını ve çıkış butonunu göster -->
                <span class="welcome-text">Welcome, <strong><?= getUsername() ?></strong></span>
                <a href="../auth/logout.php" class="btn-outline-header">Logout</a>
            <?php else: ?>
                <button class="btn-outline-header" onclick="openModal('login')">Login</button>
                <button class="btn-primary-header" onclick="openModal('register')">Register</button>
            <?php endif; ?>
        </div>
    </div>

</header>

<!--Modal Yapisi  her sayfada hazir bulunur ve css ile gizlenir.
    model-overlay : tum sayfayi kaplayan yari saydam arka plan
    model-box: ortadaki beyaz kutu -->
<div id="auth-modal" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-box">
        <div class="modal-header">
            <h2 id="modal-title" class="modal-title">Login</h2>
            <button class="modal-close" onclick="closeModal()" aria-label="Close">X</button>
        </div>

        <div class="modal-tabs">
            <button id="tab-login" class="modal-tab active" onclick="switchTab('login')">Login</button>
            <button id="tab-register" class="modal-tab" onclick="switchTab('register')">Register</button>
        </div>

        <div id="modal-feedback" class="modal-feedback hidden"></div>

        <!--LOGIN FORM -->
        <form id="modal-login-form" class="modal-form" novalidate>
            
            <div class="input-group">
                <input id="modal-email" type="email" name="email" placeholder="" required>
                <label for="modal-email">Email</label>
            </div>
            
            <div class="input-group">
                <input id ="modal-password" type="password" name="password" placeholder="" required>
                <label for="modal-password">Password</label>
                
                <button type="button" class="toggle-pass-btn" data-target="modal-password" >
                    <img class="toggle-pass-icon" src="../assets/images/toggle_pass_hide.webp" alt="Show" >
                </button>

            </div>
            
            <button class ="btn-submit" type="submit">Login</button>

        </form>

        <!--REGISTER FORM -->
        <form id="modal-register-form" class="modal-form hidden" novalidate>
            
            <div class="input-group">
                <input id="modal-username" type="text" name="username" placeholder="" maxlength="20" required>
                <label for="modal-username">Username</label>
                <span id="modal-char-counter" class="char-counter">0 / 20</span>
                <span class="error-msg" id="modal-username-error"></span>
            </div>
            
            <div class="input-group">
                <input id="modal-reg-email" type="email" name="email" placeholder="" required>
                <label for="modal-reg-email">Email</label>
                <span class="error-msg" id="modal-email-error"></span>
            </div>
            
            <div class="input-group">
                 <input id ="modal-reg-password" type="password" name="password" placeholder="" required>
                 <label for="modal-reg-password">Password</label>

                 <button type="button" class="toggle-pass-btn" data-target="modal-reg-password">
                     <img class="toggle-pass-icon" src="../assets/images/toggle_pass_hide.webp" alt="Show" >
                 </button>
                 
                 <span class="error-msg" id="modal-password-error"></span>
            </div>
            
            <div class="input-group">
                <select name="user_type" id="modal-user-type" required>
                    <option value="" disabled selected hidden></option>
                    <option value="player">Player</option>
                    <option value="organizer">Organizer</option>
                </select>

                <label for="modal-user-type">User Type</label>
                <span class="error-msg" id="modal-user-type-error" style="display: none;">Please select a user type!</span>
            </div>

            <button class="btn-submit" type="submit">Register</button>

            <div id="result"></div>
        </form> 
    </div>
</div>
