document.addEventListener("DOMContentLoaded" , () => {
    
    //URL parametresi ile ottomatik acma
    const urlParams = new URLSearchParams(window.location.search);
    const modalParam = urlParams.get('modal'); // login veya register veya null

    if(modalParam === 'login' || modalParam === 'register'){
        openModal(modalParam);
        //url den parametreyi temizle temiz gozukmesi icin
        history.replaceState(null ,'' ,window.location.pathname);
    }

    //DOM elementleri
    const modal        = document.getElementById('auth-modal');
    const modalTitle   = document.getElementById('modal-title');
    const feedback     = document.getElementById('modal-feedback');
    const loginForm    = document.getElementById('modal-login-form');
    const registerForm = document.getElementById('modal-register-form');
    const tabLogin     = document.getElementById('tab-login');
    const tabRegister  = document.getElementById('tab-register');    

    const registerRules = {
        'modal-username' : {minLength: 4},
        'modal-reg-email' : {regex : /^[^\s@]+@[^\s@]+\.[^\s@]+$/ , regexMessage : 'Invalid email format!'},
        'modal-reg-password' : {
            custom: (val) => {
                if (val.length < 6) return 'Must include at least 6 characters!';
                if (!/[A-Za-z]/.test(val)) return 'Must include at least one letter!';
                if (!/\d/.test(val)) return 'Must include at least one digit!';
                return '';
            }
        },
        'modal-user-type' : {required: true}
    };

    const registerValidator = new FormValidator(registerForm, registerRules);


    //Modal acma kapama
    window.openModal = function(tab = 'login'){
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        switchTab(tab);
    };

    window.closeModal = function() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        clearFeedback();
        loginForm.reset();
        registerForm.reset();
        
        if (typeof registerValidator !== 'undefined') {
            registerValidator.resetErrors();
        }
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

    let resizeTimeout;
    //Tab gecisi Login <-> Register
    window.switchTab = function(tab) {
        clearFeedback();

        const modalBox = document.querySelector('.modal-box');
        
        const startHeight = modalBox.offsetHeight;
        modalBox.style.height = startHeight + 'px';

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

        modalBox.style.height = 'auto';
        const targetHeight = modalBox.offsetHeight;
        modalBox.style.height = startHeight + 'px';

        modalBox.offsetHeight;
        modalBox.style.height = targetHeight + 'px';

        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            modalBox.style.height = 'auto';
        }, 300);
    }

    //Feedback fonksiyonu
    function showFeedback(message, type ='error') {
        feedback.textContent = message;
        feedback.className = `modal-feedback ${type} show-feedback`; //.error veya .success classi ekler
    }

    function clearFeedback() {
        feedback.classList.remove('show-feedback');

        setTimeout(() => {
            if (!feedback.classList.contains('show-feedback')) {
                feedback.textContent = '';
                feedback.className = 'modal-feedback';
            }
        }, 300);
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
                icon.src = '../assets/images/toggle_pass_hide.webp';
            }else {
                input.type = 'password';
                icon.src = '../assets/images/toggle_pass_eye.webp';
            }
        });
    });

    // Auth endpointlerini mevcut sayfa konumuna gore uret.
    const loginEndpoint = new URL('../auth/login-action.php', window.location.href);
    const registerEndpoint = new URL('../auth/register-action.php', window.location.href);

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
            const rawResponse = await response.text();
            let data;

            try {
                data = JSON.parse(rawResponse);
            } catch (parseError) {
                console.error('Invalid JSON response:' , rawResponse);
                return {
                    status: 'error',
                    message: response.ok
                        ? 'Unexpected server response, please try again.'
                        : `Request failed (${response.status}).`
                };
            }

            if (!response.ok) {
                return {
                    status: 'error',
                    message: data.message || `Request failed (${response.status}).`
                };
            }

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

        const data = await submitForm(loginForm, loginEndpoint);

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

    //Register form submit
    registerForm.addEventListener('submit' , async (e) => {
        e.preventDefault();
        clearFeedback();

        if (registerValidator.validateAll()) {
            return;
        }
        
        // Mevcut register-action.php dosyan — hiç değişmedi!
        const data = await submitForm(registerForm, registerEndpoint);
 
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