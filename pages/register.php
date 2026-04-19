<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ikarus |Register</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <main>
        <h2>Register with E-mail</h2>
        <form id="register-form" novalidate>
            <div class="input-group">
                <input id="username" type="text" name="username" placeholder="" maxlength="20" required>
                <label for="username">Username</label>
                <span id="char-counter" class="char-counter">0 / 20</span>
                <span class="error-msg" id="username-error"></span>
            </div>
            <div class="input-group">
                <input id="email" type="email" name="email" placeholder="" required>
                <label for="email">Email</label>
                <span class="error-msg" id="email-error"></span>
            </div>
            <div>
                <div class="input-group">
                     <input id ="password" type="password" name="password" placeholder="" required>
                     <label for="password">Password</label>
                     <span class="error-msg" id="password-error"></span>
                </div>
                <button type="button" id="togglePassword" >
                    <img id="toggleIcon" src="../assets/images/toggle_pass_hide.webp" alt="Show" >
                </button>
            </div>
            <div class="input-group">
                <select name="user_type" id="user-type-select" required>
                    <option value="" disabled selected hidden></option>
                    <option value="player">Player</option>
                    <option value="organizer">Organizer</option>
                </select>
                <label for="user-type-select">User Type</label>
                <span class="error-msg" id="user-type-error" style="display: none;">Please select a user type!</span>
            </div>

            <button id="submit-btn" type="submit">Register</button>

            <div id="result"></div>
        </form>    
    </main>
</body>

<script src="../assets/js/utils.js"></script>
<script src="../assets/js/register-handler.js"></script>
</html>