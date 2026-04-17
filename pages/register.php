<h2>Register</h2>
<form id="register-form" novalidate>
    <input id="username" type="text" name="username" placeholder="Username" required>
    <input id="email" type="email" name="email" placeholder="Email" required>
    <input id="password" type="password" name="password" placeholder="Password" required>

    <select name="user_type" id="user-type-select">
        <option value="player">Player</option>
        <option value="organizer">Organizer</option>
    </select>

    <button id="password-btn" type="submit">Register</button>
</form>

<div id = "result"></div>

<script src="../assets/js/register-handler.js"></script>