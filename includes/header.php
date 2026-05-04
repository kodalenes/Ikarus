<?php
    require_once __DIR__ . "/session.php";
?>
<header class="site-header">
    <div class="header-inner">

        <a href="../pages/index.php" class="logo">
            <div class="logo-icon">
                <img src="../assets/images/Ikarus_Logo.webp" alt="Logo">
            </div>
            IKARUS
        </a>

        <nav class="nav-links">
            <a href="../pages/tournaments.php">Tournaments</a>
            <a href="../pages/team.php">Team</a>
            <a href="../pages/leaderboard.php">Leaderboard</a>
            <?php if(isAdmin()): ?>
                <a href="../organizer/dashboard.php" class="nav-link-panel">Organizer Panel</a>
                <a href="../admin/dashboard.php" class="btn-admin-header">Admin Panel</a>
            <?php elseif(isOrganizer()): ?>
                <a href="../organizer/dashboard.php" class="nav-link-panel">Organizer Panel</a>
            <?php endif; ?>
        </nav>

        <div class="header-auth">
        <?php if (isLoggedIn()): ?>
            <span class="welcome-text">Welcome, <strong><?= getUsername() ?></strong></span>
        
            <!-- Notification Bell -->
            <div class="hdr-notif-wrap" id="hdr-notif-wrap">
                <button class="hdr-notif-btn" id="hdr-notif-btn"
                        onclick="NotifApp.togglePanel()" title="Notifications"
                        aria-label="Notifications">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <span class="hdr-notif-badge hidden" id="hdr-notif-count">0</span>
                </button>
        
                <!-- Notification Dropdown Panel -->
                <div class="hdr-notif-panel" id="hdr-notif-panel" role="dialog" aria-label="Notifications">
                    <div class="hdr-notif-header">
                        <span class="hdr-notif-title">Invitations</span>
                        <span id="hdr-notif-panel-count" class="hdr-notif-panel-count"></span>
                    </div>
                    <div id="hdr-notif-body" class="hdr-notif-body">
                        <div class="hdr-notif-empty">Loading…</div>
                    </div>
                    <a href="team.php" class="hdr-notif-footer">Go to My Team →</a>
                </div>
            </div>
        
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
            
            <div class="forgot-pass-group">
                <a href="../auth/forgot-password.php">Forgot Password</a>
            </div>

            <div class="remember-me-group">
                <label class="remember-label">
                    <input type="checkbox" name="remember-me" id="remember-me-check">
                    <span class="rememebr-text">Remember Me</span>
                </label>
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
                <span class="error-msg" id="modal-reg-email-error"></span>
            </div>
            
            <div class="input-group">
                 <input id ="modal-reg-password" type="password" name="password" placeholder="" required>
                 <label for="modal-reg-password">Password</label>

                 <button type="button" class="toggle-pass-btn" data-target="modal-reg-password">
                     <img class="toggle-pass-icon" src="../assets/images/toggle_pass_hide.webp" alt="Show" >
                 </button>

                 <div class="strength-bar">
                    <div class="strength-fill" id="reg-strength-fill"></div>
                 </div>
                
                 <span class="error-msg" id="modal-reg-password-error"></span>
            </div>
            
            <div class="input-group">
                <select name="user_type" id="modal-user-type" required>
                    <option value="" disabled selected hidden></option>
                    <option value="player">Player</option>
                    <option value="organizer">Organizer</option>
                </select>

                <label for="modal-user-type">User Type</label>
                <span class="error-msg" id="modal-user-type-error">Please select a user type!</span>
            </div>

            <button class="btn-submit" type="submit">Register</button>

            <div id="result"></div>
        </form> 
    </div>
</div>
