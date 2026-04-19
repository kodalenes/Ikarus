document.addEventListener("DOMContentLoaded", () => {
	const registerForm = document.getElementById("register-form");
	const usernameInput = document.getElementById("username");
	const charCounter = document.getElementById("char-counter");
	const feedback = document.getElementById("result");
	const userTypeSelect = document.getElementById("user-type-select");
	const userTypeError = document.getElementById("user-type-error");

	const updateUI = (inputEl, errorId, message, isError) => {
		const errorEl = document.getElementById(errorId);
		if (!errorEl) {
			return;
		}

		if (isError) {
			errorEl.textContent = message;
			errorEl.style.display = "block";
			inputEl.style.borderColor = "var(--accent)";
			inputEl.dataset.hasError = "true";
		} else {
			errorEl.style.display = "none";
			inputEl.style.borderColor = "";
			inputEl.dataset.hasError = "false";
		}
	};

	const allInputs = document.querySelectorAll("input");

	allInputs.forEach((input) => {
		input.addEventListener("input", () => {
			const val = input.value.trim();

			//Username check
			if (input.id === "username") {
				const maxLength = input.getAttribute("maxlength");
				charCounter.textContent = `${val.length} / ${maxLength}`;

				if (val.length < 4) {
					updateUI(input, "username-error", "Username must be at least 4 characters!", true);
				} else {
					updateUI(input, "username-error", "", false);
				}
			}

			//Email check
			if (input.id === "email") {
				const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; //Standart email formati
				if (val.length > 0 && !emailRegex.test(val)) {
					updateUI(input, "email-error", "Invalid email format!", true);
				} else {
					updateUI(input, "email-error", "", false);
				}
			}

			//Password check
			if (input.type === "password") {
				let msg = "";
				if (val.length < 6) {
					msg = "Min 6 characters!";
				} else if (!/[A-Za-z]/.test(val)) {
					msg = "Need at least one letter!";
				} else if (val.length > 0 && !/\d/.test(val)) {
					msg = "Need at least one digit!";
				}

				updateUI(input, "password-error", msg, msg !== "");
			}
		});
	});

	//Clear error when Select box changed
	userTypeSelect.addEventListener("change", () => {
		updateUI(userTypeSelect, "user-type-error", "", userTypeSelect.value === "");
	});

	if (registerForm) {
		registerForm.addEventListener("submit", function (e) {
			//Sayfanin yenilenmesini onleyen kod!
			e.preventDefault();

			allInputs.forEach((input) => input.dispatchEvent(new Event("input")));

			if (!userTypeSelect.value) {
				updateUI(userTypeSelect, "user-type-error", "Please select a type!", true);
				return;
			}

			const hasErrors = Array.from(allInputs).some((i) => i.dataset.hasError === "true");
			if (hasErrors) return;

			const formData = new FormData(registerForm);

			//
			fetch("../auth/register-action.php", {
				method: "POST",
				body: formData, //Verileri php ye yollar
			})
				.then((response) => response.json()) //Sunucudan gelen metni json formatina donusturur.
				//Sonra gelen veriyi duruma gore ui yansitir.
				.then((data) => {
					if (data.status === "success") {
						feedback.style.color = "green";
						feedback.innerText = data.message;
						setTimeout(() => {
							window.location.href = "login.php";
						}, 1000);
					} else {
						feedback.style.color = "red";
						feedback.innerText = data.message;
					}
				})
				.catch((error) => console.error("Error:", error));
		});
	}
});
