(() => {
    const toast = document.querySelector('.login-toast');
    if (!toast) return;

    let timer;
    function dismiss() {
        window.clearTimeout(timer);
        toast.classList.add('dismissed');
        window.setTimeout(() => toast.remove(), 500);
    }

    toast.querySelector('button').addEventListener('click', dismiss, { once: true });
    timer = window.setTimeout(dismiss, 6000);
})();
