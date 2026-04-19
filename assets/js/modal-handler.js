document.addEventListener("DOMContentLoaded" , () => {
    //URL parametresi ile ottomatik acma
    const urlParams = new URLSearchParams(window.location.search);
    const modalParam = urlParams.get('modal'); // login veya register veya null

    if(modalParam === 'login' || modalParam === 'register');{
        openModal(modalParam);
        //url den parametreyi temizle temiz gozukmesi icin
        history.replaceState(null ,'' ,window.location.pathname);
    }

    const modal        = document.getElementById('auth-modal');
    const modalTitle   = document.getElementById('modal-title');
    const feedback     = document.getElementById('modal-feedback');
    const loginForm    = document.getElementById('modal-login-form');
    const registerForm = document.getElementById('modal-register-form');
    const tabLogin     = document.getElementById('tab-login');
    const tabRegister  = document.getElementById('tab-register');    

    //Modal acma kapama
    window.openModal = function(tab = 'login'){
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        switchTab(tab);
    };

    window.closeModal =function() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        clearFeedback();
        loginForm.reset();
        registerForm.reset();
        clearAllErrors();
    };

    //Modal disina tiklayinca kapat
    modal.addEventListener('click' ,(e) => {
        if(e.target === modal) closeModal();
    });

    //Escape tusuna basinca kapat
    document.addEventListener('keydown' , (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    //Tab gecisi Login <-> Register
    window.switchTab = function(Tab) {
        clearFeedback();

        if (tab === 'login') {
            loginForm.classList.remove('hidden');
            registerForm.classList.add('hidden');
            tabLogin.classList.add('active');
            tabRegister.classList.remove('active');
            modalTitle.textContent = 'Login';
        } else {
            registerForm.classList.remove('hidden');
            loginForm.classList.add('hidden');
            tabRegister.classList.add('active');
            tabLogin.classList.remove('active');
            modalTitle.textContent = 'Register';
        }
    }

    //Feedback fonksiyonu

    function showFeedback(message, type ='error') {
        feedback.textContent = message;
        feedback.className = 'modal-feedback ${type}'; //.error veya .success classi ekler
        feedback.classList.remove('hidden');
    }

    function clearFeedback() {
        feedback.classList.add('hidden');
        feedback.textContent = '';
        feedback.className = 'modal-feedback hidden';
    }

    //Show / Hide Password
    //data-target attribute u ile hangi input u hedef aldigini gosterir
    //bu sekilde bir fonksiyon birden fazla sifre alanini yonetir.

    document.querySelectorAll('.toggle-pass-btn').forEach(btn => {
        btn.addEventListener('click' , () => {
            const targetId = btn.dataset.target;//data-target="modal-password"
            const input = document.getElementById(targetId);
            const icon = btn.querySelector('.toggle-pass-icon');

            if (!input) {
                return;
            }

            if(input.type === 'password'){
                input.type = 'text';
                icon.src = '/assets/images/toggle_pass_hide.webp';
            }
        });
    });

    //Veritabani form gonderimi
    //Iki formda ayni seyi kullaniyor 
    //FormData olusturup fetch ile gondericez
    async function submitForm(form , endpoint) {
        const submitBtn = form.querySelector('.btn-submit');

        //butonu devre disi birakicaz iki kere basmayi engellemek icin
        submitBtn.disabled = true;
        submitBtn.textContent = 'Please Wait...';

        try {
            const formData = new FormData(form);
            const response = await fetch(endpoint, {method: 'POST' , body: formData});
            const data = await response.json();

            return data;
        } catch (err) {
            console.error("Fetch eror:" , err);
            return { status: 'error' , message: 'Connection error, please try again.'};
        }finally{
            submitBtn.disabled = false;
            submitBtn.textContent = form === loginForm ? 'Login' : 'Register';
        }
    }

    //Login Form Submit
    loginForm.addEventListener('submit' , async (e) => {
        e.preventDefault();
        clearFeedback();

        //Bos alan kontrolu
        const email = loginForm.querySelector('[name="email"]').value.trim();
        const password = loginForm.querySelector('[name="password"]').value;

        if(!email || !password){
            showFeedback("Please fill all fields");
            return;
        }

        const data = await submitForm(loginForm, '/auth/login-action.php');

        if (data.status === 'success') {
            showFeedback('Logged In! You are being redirected...' , 'success');

            //1 saniye sorna sayfa yeniliyoruz Cunku PHP session olustu
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }else {
            showFeedback(data.message);
        }
    });

    //RegisterForm
    const usernameInput  = document.getElementById('modal-username');
    const regEmailInput  = document.getElementById('modal-reg-email');
    const regPassInput   = document.getElementById('modal-reg-password');
    const userTypeSelect = document.getElementById('modal-user-type');
    const charCounter    = document.getElementById('modal-char-counter');
 
    function setFieldError(input, errorId, message, isError) {
        const errorEl = document.getElementById(errorId);
        if (!errorEl) return;
 
        if (isError) {
            errorEl.textContent = message;
            errorEl.style.display = 'block';
            input.style.borderColor = 'var(--accent, #489fb5)';
            input.dataset.hasError = 'true';
        } else {
            errorEl.style.display = 'none';
            input.style.borderColor = '';
            input.dataset.hasError = 'false';
        }
    }
 
    function clearAllErrors() {
        document.querySelectorAll('.modal-form .error-msg').forEach(el => {
            el.style.display = 'none';
            el.textContent = '';
        });
        document.querySelectorAll('.modal-form input, .modal-form select').forEach(el => {
            el.style.borderColor = '';
            el.dataset.hasError = 'false';
        });
    }
 
    // Kullanıcı adı: anlık karakter sayacı ve format kontrolü
    if (usernameInput) {
        usernameInput.addEventListener('input', () => {
            const val = usernameInput.value.trim();
            charCounter.textContent = `${val.length} / 20`;
 
            if (val.length > 0 && val.length < 4) {
                setFieldError(usernameInput, 'modal-username-error', 'Must be include at least 5 characters!', true);
            } else {
                setFieldError(usernameInput, 'modal-username-error', '', false);
            }
        });
    }
 
    // E-posta: regex formatı kontrolü
    if (regEmailInput) {
        regEmailInput.addEventListener('input', () => {
            const val   = regEmailInput.value.trim();
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const isErr = val.length > 0 && !regex.test(val);
            setFieldError(regEmailInput, 'modal-email-error', 'Inalid email format!', isErr);
        });
    }
 
    // Şifre: uzunluk, harf ve rakam kontrolü
    if (regPassInput) {
        regPassInput.addEventListener('input', () => {
            const val = regPassInput.value;
            let msg = '';
            if (val.length > 0 && val.length < 6)      msg = 'Must be include at least 6 characters!';
            else if (val.length > 0 && !/[A-Za-z]/.test(val)) msg = 'Must be include at least one letter!';
            else if (val.length > 0 && !/\d/.test(val))        msg = 'Must be include at least one digit!';
            setFieldError(regPassInput, 'modal-password-error', msg, msg !== '');
        });
    }
 
    // Select kutusu değişince hata mesajını temizle
    if (userTypeSelect) {
        userTypeSelect.addEventListener('change', () => {
            setFieldError(userTypeSelect, 'modal-user-type-error', '', false);
        });
    }    

    //Register form submit
    registerForm.addEventListener('submit' , async (e) => {
        e.preventDefault();
        clearFeedback();

        [usernameInput, regEmailInput, regPassInput].forEach(input => {
            if (input) input.dispatchEvent(new Event('input'));
        });
 
        if (!userTypeSelect.value) {
            setFieldError(userTypeSelect, 'modal-user-type-error', 'Please select user type!', true);
            return;
        }
 
        // Herhangi bir alanda hata var mı?
        const hasErrors = Array.from(
            registerForm.querySelectorAll('input, select')
        ).some(el => el.dataset.hasError === 'true');
 
        if (hasErrors) return;
 
        // Mevcut register-action.php dosyan — hiç değişmedi!
        const data = await submitForm(registerForm, '/auth/register-action.php');
 
        if (data.status === 'success') {
            showFeedback('Successfully registered! Logging in...', 'success');
 
            // Kayıt başarılıysa kullanıcıyı otomatik giriş sayfasına yönlendir.
            // İleride doğrudan oturum açmak istersen register-action.php'de
            // session başlatabilir ve burada reload() çağırabilirsin.
            setTimeout(() => {
                switchTab('login');
                clearFeedback();
            }, 1500);
        } else {
            showFeedback(data.message);
        }
    })
});