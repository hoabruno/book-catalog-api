// Asks for confirmation before submitting forms marked with data-confirm
// (kept out of inline handlers so the CSP can forbid inline scripts).
document.addEventListener('submit', (event) => {
    const message = event.target.dataset?.confirm;
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});
