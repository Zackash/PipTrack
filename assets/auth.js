(() => {
    'use strict';

    const forms = [...document.querySelectorAll('.auth-form')];
    const error = document.getElementById('authError');
    const notice = document.getElementById('authNotice');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const copy = {
        login: ['SECURE SIGN IN', 'Welcome back.', 'Sign in to continue to your trading journal.'],
        signup: ['CREATE YOUR ACCOUNT', 'Start your journal.', 'Create an account with recovery contacts for your trading journal.'],
        forgot: ['ACCOUNT RECOVERY', 'Forgot your password?', 'Choose where to receive a secure reset code.'],
        reset: ['ACCOUNT RECOVERY', 'Choose a new password.', 'Enter the code sent to your recovery contact.'],
        contact_setup: ['ACCOUNT RECOVERY', 'Add recovery contacts.', 'Add an email address and phone number so you can recover your account.'],
    };

    function showMode(mode) {
        document.body.dataset.authMode = mode;
        forms.forEach((form) => {
            form.hidden = form.dataset.authModeForm !== mode;
            form.classList.toggle('hidden', form.hidden);
        });
        const [kicker, title, description] = copy[mode] || copy.login;
        document.getElementById('authKicker').textContent = kicker;
        document.getElementById('authTitle').textContent = title;
        document.getElementById('authDescription').textContent = description;
        document.querySelectorAll('button[data-auth-mode]').forEach((button) => {
            button.classList.toggle('active', button.dataset.authMode === mode);
        });
        error.classList.add('hidden');
        error.textContent = '';
        notice.classList.add('hidden');
        notice.textContent = '';
    }

    function showError(message) {
        error.textContent = message;
        error.classList.remove('hidden');
    }

    document.querySelectorAll('.password-toggle').forEach((button) => {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        const label = button.closest('.form-field').firstElementChild;
        input.setAttribute('aria-label', label.textContent.trim());
        button.addEventListener('click', () => {
            const isVisible = input.type === 'password';
            input.type = isVisible ? 'text' : 'password';
            const label = isVisible ? 'Hide password' : 'Show password';
            button.setAttribute('aria-label', label);
            button.setAttribute('aria-pressed', String(isVisible));
            button.title = label;
        });
    });

    async function submitForm(form) {
        if (!form.reportValidity()) return;

        const payload = Object.fromEntries(new FormData(form).entries());
        if (
            ['signup', 'reset_password'].includes(form.dataset.authAction)
            && payload.password !== payload.password_confirmation
        ) {
            showError('The passwords do not match.');
            return;
        }

        const button = form.querySelector('[type="submit"]');
        const label = button.textContent;
        button.disabled = true;
        button.textContent = 'Please wait…';
        error.classList.add('hidden');
        notice.classList.add('hidden');

        try {
            const response = await fetch('api.php?resource=auth', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ action: form.dataset.authAction, ...payload }),
            });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.error || 'Unable to complete the request. Please try again.');

            if (form.dataset.authAction === 'request_reset') {
                const resetForm = document.getElementById('recoveryCompleteForm');
                resetForm.elements.channel.value = payload.channel;
                resetForm.elements.contact.value = payload.contact.trim();
                showMode('reset');
                notice.textContent = result.message || 'If an account matches, a reset code has been sent.';
                notice.classList.remove('hidden');
                return;
            }
            if (form.dataset.authAction === 'reset_password') {
                showMode('login');
                notice.textContent = 'Your password was reset. Sign in with your new password.';
                notice.classList.remove('hidden');
                return;
            }
            await window.PipTrackPageTransition.start();
            window.location.replace('index.php');
        } catch (requestError) {
            showError(requestError.message);
        } finally {
            button.disabled = false;
            button.textContent = label;
        }
    }

    forms.forEach((form) => form.addEventListener('submit', (event) => {
        event.preventDefault();
        submitForm(form);
    }));

    document.querySelectorAll('button[data-auth-mode]').forEach((button) => {
        button.addEventListener('click', () => showMode(button.dataset.authMode));
    });

    const recoveryChannel = document.getElementById('recoveryChannel');
    if (recoveryChannel) {
        recoveryChannel.addEventListener('change', () => {
            const isEmail = recoveryChannel.value === 'email';
            const contact = document.getElementById('recoveryContact');
            document.getElementById('recoveryContactLabel').textContent = isEmail ? 'Email address' : 'Phone number';
            contact.type = isEmail ? 'email' : 'tel';
            contact.autocomplete = isEmail ? 'email' : 'tel';
            contact.maxLength = isEmail ? 254 : 32;
            contact.pattern = isEmail ? '' : '\\+[1-9][0-9]{6,14}';
            contact.placeholder = isEmail ? 'you@example.com' : '+254712345678';
        });
    }

    const contactSetupBack = document.getElementById('contactSetupBack');
    if (contactSetupBack) {
        contactSetupBack.addEventListener('click', async () => {
            contactSetupBack.disabled = true;
            try {
                const response = await fetch('api.php?resource=auth', {
                    method: 'DELETE',
                    headers: { 'X-CSRF-Token': csrfToken },
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(result.error || 'Unable to sign out. Please try again.');
                window.location.replace('index.php');
            } catch (requestError) {
                showError(requestError.message);
                contactSetupBack.disabled = false;
            }
        });
    }

})();
