(function () {
    const select = document.getElementById('gtp-checkin-classroom');
    const details = document.getElementById('gtp-checkin-class-details');
    const dataEl = document.getElementById('gtp-checkin-class-data');
    if (!select || !details || !dataEl) {
        return;
    }

    let map = {};
    try {
        map = JSON.parse(dataEl.textContent || '{}');
    } catch (e) {
        map = {};
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function render(id) {
        const info = map[id];
        if (!info) {
            details.hidden = true;
            details.innerHTML = '';
            return;
        }

        let html =
            '<p><strong>Subject:</strong> ' + escapeHtml(info.subject || '—') + '</p>' +
            '<p><strong>School:</strong> ' + escapeHtml(info.school || '—') + '</p>' +
            '<p><strong>Teacher:</strong> ' + escapeHtml(info.teacher || '—') + '</p>' +
            '<p><strong>Time:</strong> ' + escapeHtml(info.time || '—') + '</p>';

        if (info.zoom) {
            html += '<p><strong>Zoom:</strong> <a href="' + escapeHtml(info.zoom) + '" target="_blank" rel="noopener noreferrer">Link</a></p>';
        }

        details.innerHTML = html;
        details.hidden = false;
    }

    select.addEventListener('change', function () {
        render(select.value);
    });

    if (select.value) {
        render(select.value);
    }
})();
