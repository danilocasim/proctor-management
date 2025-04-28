// password-toggle.js

document.addEventListener('DOMContentLoaded', function () {
    function setupToggle(inputId, toggleId) {
        var input = document.getElementById(inputId);
        var toggle = document.getElementById(toggleId);
        if (input && toggle) {
            toggle.addEventListener('mousedown', function(e) { e.preventDefault(); });
            toggle.addEventListener('click', function() {
                if (input.type === 'password') {
                    input.type = 'text';
                    toggle.textContent = 'Hide';
                } else {
                    input.type = 'password';
                    toggle.textContent = 'Show';
                }
            });
        }
    }
    setupToggle('login-password', 'toggle-login-password');
    setupToggle('signup-password', 'toggle-signup-password');
});
