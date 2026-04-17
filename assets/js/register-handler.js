document.getElementById('register-form').addEventListener('submit' , function (e) {
    e.preventDefault();

    const feedback = document.getElementById('result');
    const formData = new FormData(this);

    if (formData.get('username').length < 4) {
        feedback.style.color = 'red';
        feedback.innerText = "Username must be at least 4 characters!";
        return;
    }

    //Password format check
    if (formData.get('password').length < 6) {
        feedback.style.color = 'red';
        feedback.innerText = "Password must be at least 6 characters!";
        return;
    }

    fetch('../auth/register-action.php' , {
        method: 'POST' ,
        body: formData
    })
    .then(response => response.json())
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