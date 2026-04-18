<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ikarus |Login</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <main>
        <h2>Login</h2>
        <form id="login-form">
            <div class="input-group">
                <input type="email" name="email"  required>
                <label for="email">Email</label>
            </div>
            <div class="input-group">
                <div class="input-group">
                     <input id ="password" type="password" name="password"  required>
                     <label for="password">Password</label>
                </div>
                <button type="button" id="togglePassword" >
                    <img id="toggleIcon" src="../assets/images/toggle_pass_hide.webp" alt="Show" >
                </button>
            </div>
            <button id = "password-btn" type="submit">Login</button>
        </form>
    
        <div id = "result"></div>
    </main>
</body>

<script src="../assets/js/utils.js"></script>
<script src="../assets/js/login-handler.js"></script>
</html>