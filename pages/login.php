<h2>Login</h2>
<form id="login-form">
    <input type="email" name="email" placeholder="Email" required>
    <div>
        <input id = "password" type="password" name="password" placeholder="Password" required>
        <button type="button" id="togglePassword" >
            <img id="toggleIcon" src="../assets/images/toggle_pass_hide.webp" alt="Show" >
        </button>
    </div>
    <button id = "login-btn" type="submit">Login</button>
</form>

<div id = "result"></div>

<script src="../assets/js/login-handler.js"></script>
<script src="../assets/js/utils.js"></script>