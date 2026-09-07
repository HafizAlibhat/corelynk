(function () {
    'use strict';

    var password = document.getElementById('password');
    var togglePassword = document.getElementById('togglePassword');
    var loginForm = document.getElementById('loginForm');
    var signInButton = document.getElementById('btnSignin');

    if (password && togglePassword) {
        togglePassword.addEventListener('click', function () {
            var showPassword = password.type === 'password';
            var icon = this.querySelector('i');
            password.type = showPassword ? 'text' : 'password';
            this.setAttribute('aria-pressed', showPassword ? 'true' : 'false');
            this.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
            if (icon) {
                icon.classList.toggle('bi-eye', !showPassword);
                icon.classList.toggle('bi-eye-slash', showPassword);
            }
        });
    }

    if (loginForm) {
        loginForm.addEventListener('submit', function (event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                this.classList.add('was-validated');
                return;
            }
            if (signInButton) {
                signInButton.disabled = true;
                signInButton.setAttribute('aria-busy', 'true');
                signInButton.querySelector('span').textContent = 'Signing in...';
                signInButton.querySelector('i').className = 'bi bi-arrow-repeat';
            }
        });
    }

    window.setTimeout(function () {
        document.querySelectorAll('.alert').forEach(function (alertElement) {
            try { bootstrap.Alert.getOrCreateInstance(alertElement).close(); } catch (error) {}
        });
    }, 6000);
}());
