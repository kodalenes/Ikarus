togglePassIcon();

function togglePassIcon() {
    document.addEventListener('DOMContentLoaded' , function () {
        const togglePasswordBtn = document.getElementById('togglePassword');
        const passwordinput = document.getElementById('password');

        if (togglePasswordBtn && passwordinput) {
            togglePasswordBtn.addEventListener('click' , function () {
                const currentType = passwordinput.getAttribute('type');
                const toggleIcon = document.getElementById('toggleIcon');

                if (currentType === 'password') {
                    passwordinput.setAttribute('type' , 'text');
                    toggleIcon.setAttribute('src' , '../assets/images/toggle_pass_eye.webp')    
                }
                else{
                    passwordinput.setAttribute('type' , 'password');
                    toggleIcon.setAttribute('src' , '../assets/images/toggle_pass_hide.webp')
                }
            })
        }
    })
}