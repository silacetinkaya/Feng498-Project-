document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const showRegisterBtn = document.getElementById('go-to-register');
    const showLoginBtn = document.getElementById('go-to-login');
    const messageBox = document.getElementById('message-box');

    // Toggle Forms
    showRegisterBtn.addEventListener('click', () => {
        loginForm.classList.add('hidden');
        registerForm.classList.remove('hidden');
        messageBox.style.display = 'none'; // hide messages on toggle
    });

    showLoginBtn.addEventListener('click', () => {
        registerForm.classList.add('hidden');
        loginForm.classList.remove('hidden');
        messageBox.style.display = 'none';
    });

    // Check URL Parameters for Errors/Success
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    const success = urlParams.get('success');

    if (error) {
        messageBox.style.display = 'block';
        messageBox.className = 'msg-error';
        
        if (error === 'email_exists') messageBox.textContent = "That email is already registered.";
        else if (error === 'invalid_credentials') messageBox.textContent = "Invalid email or password.";
        else if (error === 'empty_fields') messageBox.textContent = "Please fill in all fields.";
        else messageBox.textContent = "An error occurred. Please try again.";
    }

    if (success === 'registered') {
        messageBox.style.display = 'block';
        messageBox.className = 'msg-success';
        messageBox.textContent = "Account created! Please log in.";
    }
});