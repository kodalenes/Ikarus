class FormValidator {
    constructor(formElement, rules) {
        this.form = formElement;
        this.rules = rules;//{'input-id': {minLength: 5, required: true...}}
        this.initListeners();
    }

    initListeners() {
        //Object.entries dizi yapar
        //For diziyi dolasir
        //const [inputId, ruleSet] diziyi parcalar ilki input id ikinci ruleSet olur
        for (const [inputId, ruleSet] of Object.entries(this.rules)) {
            //DOM dan inputu cekiyoruz
            const input = document.getElementById(inputId);
            if (input) {
                //input bulunmussa kurallari kontrol ediyoruz
                input.addEventListener('input' , () => this.validateField(input, ruleSet));
                input.addEventListener('change' , () => this.validateField(input, ruleSet));
            }
        }
    }

    //Tek bir alani kurallara gore kontrol yapan metot
    validateField(input, ruleSet) {
        const val = input.value.trim();
        let errorMessage = '';
        let isError = false;

        //Kurallari sirayla kontrol et
        if (ruleSet.required && val === '') {
            errorMessage = 'Please fill this field!';
            isError = true;
        }else if (ruleSet.minLength && val.length > 0 && val.length < ruleSet.minLength) {
            errorMessage =`Must include at least ${ruleSet.minLength} characters!`;
            isError = true;
        }else if (ruleSet.regex && val.length > 0 && !ruleSet.regex.test(val)) {
            errorMessage = ruleSet.regexMessage || 'Invalid format!';
            isError = true;
        }else if (ruleSet.custom && val.length > 0) {
            errorMessage = ruleSet.custom(val);
            isError = errorMessage !== '';
        }

        this.toggleErrorUI(input, isError, errorMessage);

        if (input.id === 'modal-username') {
            const charCounter = document.getElementById('modal-char-counter');
            if (charCounter) {
                charCounter.textContent = `${val.length} / 20`;
            }
        }
    } 

    //Hatayi arayuze bastirir
    toggleErrorUI(input, isError, message){
        //Hatay yazdiracagimiz elementi bul
        const errorElement = document.getElementById(`${input.id}-error`);

        if (!errorElement) {
            return;
        }

        //Hata varsa yazdir
        if (isError) {
            errorElement.textContent = message;
            errorElement.classList.add('show-error');
            input.style.borderColor = 'var(--accent, #489fb5)';
            input.dataset.hasError = 'true';
        }else {
            errorElement.classList.remove('show-error');
            input.style.borderColor = '';
            input.dataset.hasError = 'false';
        }
    }

    //Formu gondermeden once tum alanlari manuel kontrol edicez
    validateAll() {
        let hasErrors = false;
        for (const [inputId, ruleSet] of Object.entries(this.rules)) {
            const input = document.getElementById(inputId);
            if (input) {
                this.validateField(input, ruleSet);
                if (input.dataset.hasError === 'true') {
                    hasErrors = true;
                }
            }
        }
        return hasErrors;
    }

    resetErrors() {
        for (const inputId of Object.keys(this.rules)) {
            const input = document.getElementById(inputId);
            if (input) {
                this.toggleErrorUI(input, false, '');
            }
        }

        const charCounter = document.getElementById('modal-char-counter');
        if (charCounter) {
            charCounter.textContent = '0 / 20';
        }
    }
}