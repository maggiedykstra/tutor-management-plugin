document.addEventListener('DOMContentLoaded', function() {
    const banner = document.getElementById('gtp-success-banner');
    if (banner) {
        setTimeout(() => {
            banner.style.display = 'none';
        }, 3000);
    }
});
