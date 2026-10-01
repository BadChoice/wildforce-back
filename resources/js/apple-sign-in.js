const initializeAppleSignIn = () => {
    const forms = document.querySelectorAll('.js-apple-sign-in-form:not([data-initialized])');

    if (forms.length === 0) return;

    if (!window.AppleID?.auth) {
        window.setTimeout(initializeAppleSignIn, 100);
        return;
    }

    forms.forEach((form) => {
        window.AppleID.auth.init({
            clientId: form.dataset.clientId,
            redirectURI: form.dataset.redirectUri,
            scope: 'name email',
            usePopup: true,
        });

        form.querySelector('[data-apple-sign-in]').addEventListener('click', () => {
            window.AppleID.auth.signIn().then(({ authorization, user }) => {
                form.querySelector('[name="authorization_code"]').value = authorization.code;
                form.querySelector('[name="full_name"]').value = [user?.name?.firstName, user?.name?.lastName]
                    .filter(Boolean)
                    .join(' ');
                form.submit();
            });
        });

        form.dataset.initialized = 'true';
    });
};

document.addEventListener('DOMContentLoaded', initializeAppleSignIn);
document.addEventListener('livewire:navigated', initializeAppleSignIn);
