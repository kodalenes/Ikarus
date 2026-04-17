document.getElementById('register-form').addEventListener('submit' , function (e) {
    //Sayfanin yenilenmesini onleyen kod!
    e.preventDefault();

    //Geribildirim yapmak icin mesaji yazdiracagimiz kisim
    const feedback = document.getElementById('result');
    const formData = new FormData(this);

    const usernameRegex = /^[a-zA-Z0-9_]{4,20}$/;//4-20 karakter ,bosluk yok , sadece harf ve rakam icerecek
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; //Standart email formati    
    
    //Username format check
    if (!usernameRegex.test(formData.get('username'))) {
        feedback.style.color = 'red';
        feedback.innerText = "Username must be at least 4 characters!";
        return;
    }

    //Email format check
    if (!emailRegex.test(formData.get('email'))) {
        feedback.style.color = 'red';
        feedback.innerText = "Please enter valid email address!"
        return;
    }

    //Password format check
    const password = formData.get('password');
    let passwordError = "";

    if (password.length < 6) {
        passwordError = "The password must be at least 6 characters!";
    }else if(!/[A-Za-z]/.test(password)) {
        passwordError = "The password must contain at least one letter!";
    }else if (!/\d/.test(password)) {
        passwordError = "The password must contain at least one digit!";
    }

    if (passwordError !== "") {
        feedback.style.color = 'orange';
        feedback.innerText = passwordError;
        return;
    }

    //
    fetch('../auth/register-action.php' , {
        method: 'POST' ,
        body: formData //Verileri php ye yollar
    })
    .then(response => response.json()) //Sunucudan gelen metni json formatina donusturur.
    //Sonra gelen veriyi duruma gore ui yansitir.
    .then(data => {
        if(data.status === 'success'){
            feedback.style.color = 'green';
            feedback.innerText = data.message;
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 1000);
        }else{
            feedback.style.color = 'red';
            feedback.innerText = data.message;
        }
    })
    .catch(error => console.error('Error:', error));
});