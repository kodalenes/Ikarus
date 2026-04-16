function validateForm() {
    //Get the input values from html
    let username = document.getElementById('username').value;
    let email = document.getElementById('email').value;
    let password = document.getElementById('password').value;
    let errorField = document.getElementById('jsError');

    errorField.innerText = "";
    errorField.style.color = "red";
    //username check
    if (username.length < 3) {
        errorField.innerText = "Username must be greater than 3 characters!";
        return false;
    }

    //Email check : it  must be include @
    if (!email.includes("@")) {
        errorField.innerText = "Please enter valid email!";
        return false;
    }

    //Password lenght check : it must be at least 6 character
    if (password.length < 6) {
        errorField.innerText = "Password is too short! Enter at least 6 characters!"
        return false;
    }

    return true;
}