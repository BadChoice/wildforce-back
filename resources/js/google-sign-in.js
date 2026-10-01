const renderButtons = () => {
    const containers = document.querySelectorAll('[data-google-sign-in]:not([data-rendered])');

    if (containers.length === 0) return;

    if (!window.google?.accounts?.id) {
        window.setTimeout(renderButtons, 100);
        return;
    }

    containers.forEach((container) => {
        const form = container.closest('.js-google-sign-in-form');

        window.google.accounts.id.initialize({
            client_id: container.dataset.clientId,
            callback: ({ credential }) => {
                form.querySelector('[name="id_token"]').value = credential;
                form.submit();
            },
        });
        window.google.accounts.id.renderButton(container, {
            theme: 'outline',
            size: 'large',
            text: 'continue_with',
            width: 384,
        });
        container.dataset.rendered = 'true';
    });
};

document.addEventListener('DOMContentLoaded', renderButtons);
document.addEventListener('livewire:navigated', renderButtons);
