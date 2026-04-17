document.getElementById('login-form').addEventListener('submit' , function (e) {
    
    e.preventDefault();

    const feedback = document.getElementById('result');

    const formData = new FormData(this);

    fetch('../auth/login-action.php' , {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            feedback.style.color = 'green';
            feedback.innerText = data.message;

            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1000);
        }else {
            feedback.style.color = 'red';
            feedback.innerText = data.message;
        }
    })
    .catch(error => {
        console.error('Request Error:' , error);
    });
});