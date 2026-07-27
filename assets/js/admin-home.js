(function () {
    const ajaxUrl = (typeof gtp_ajax !== 'undefined' && gtp_ajax.ajax_url)
        ? gtp_ajax.ajax_url
        : null;
    if (!ajaxUrl) {
        return;
    }

    const modal = document.getElementById('gtp-tutor-profile-modal');
    const body = document.getElementById('gtp-tutor-profile-body');
    if (!modal || !body) {
        return;
    }

    function openModal() {
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatPreferences(prefs) {
        if (!prefs || typeof prefs !== 'object') {
            return '<p class="gtp-tutor-profile-empty">No subject preferences listed.</p>';
        }
        const keys = Object.keys(prefs);
        if (!keys.length) {
            return '<p class="gtp-tutor-profile-empty">No subject preferences listed.</p>';
        }
        let html = '<ul class="gtp-tutor-profile-prefs">';
        keys.forEach(function (subject) {
            html += '<li><strong>' + escapeHtml(subject) + ':</strong> ' + escapeHtml(prefs[subject]) + '</li>';
        });
        html += '</ul>';
        return html;
    }

    function formatAssignments(list) {
        if (!list || !list.length) {
            return '<p class="gtp-tutor-profile-empty">Not assigned to any classes yet.</p>';
        }
        let html = '<ul class="gtp-tutor-profile-classes">';
        list.forEach(function (c) {
            html += '<li><strong>' + escapeHtml(c.subject) + '</strong> at ' + escapeHtml(c.school);
            if (c.teacher) {
                html += ' <span>(' + escapeHtml(c.teacher) + ')</span>';
            }
            if (c.time) {
                html += '<br><span class="gtp-tutor-profile-muted">' + escapeHtml(c.time) + '</span>';
            }
            html += '</li>';
        });
        html += '</ul>';
        return html;
    }

    function renderProfile(data) {
        const initials = (data.name || 'U')
            .split(/\s+/)
            .map(function (p) { return p.charAt(0); })
            .join('')
            .slice(0, 2)
            .toUpperCase();

        let avatar = '<div class="gtp-home-avatar gtp-tutor-profile-avatar">' + escapeHtml(initials) + '</div>';
        if (data.headshot_url) {
            avatar = '<div class="gtp-home-avatar gtp-tutor-profile-avatar"><img src="' + escapeHtml(data.headshot_url) + '" alt="' + escapeHtml(data.name) + '"></div>';
        }

        body.innerHTML =
            '<div class="gtp-tutor-profile-header">' +
                avatar +
                '<div>' +
                    '<h2 id="gtp-tutor-profile-title">' + escapeHtml(data.name) + '</h2>' +
                    '<p class="gtp-tutor-profile-muted">@' + escapeHtml(data.username || '') + '</p>' +
                '</div>' +
            '</div>' +
            '<div class="gtp-tutor-profile-fields">' +
                '<p><strong>Email:</strong> ' + (data.email ? '<a href="mailto:' + escapeHtml(data.email) + '">' + escapeHtml(data.email) + '</a>' : '—') + '</p>' +
                '<p><strong>School (profile):</strong> ' + escapeHtml(data.school || '—') + '</p>' +
                '<p><strong>Bio:</strong></p>' +
                '<div class="gtp-tutor-profile-bio">' + escapeHtml(data.bio || 'No bio yet.') + '</div>' +
                '<p style="margin-top:14px;"><strong>Subject preferences:</strong></p>' +
                formatPreferences(data.subject_preferences) +
                '<p style="margin-top:14px;"><strong>Assigned classes:</strong></p>' +
                formatAssignments(data.assigned_classes) +
            '</div>';
    }

    document.querySelectorAll('.gtp-tutor-profile-trigger').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const tutorId = btn.getAttribute('data-tutor-id');
            body.innerHTML = '<p>Loading…</p>';
            openModal();

            const formData = new FormData();
            formData.append('action', 'gtp_get_tutor_profile');
            formData.append('tutor_id', tutorId);

            fetch(ajaxUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (json) {
                    if (!json || !json.success) {
                        body.innerHTML = '<p>' + escapeHtml((json && json.data) || 'Could not load profile.') + '</p>';
                        return;
                    }
                    renderProfile(json.data);
                })
                .catch(function () {
                    body.innerHTML = '<p>Could not load profile.</p>';
                });
        });
    });

    modal.querySelectorAll('[data-close-tutor-modal]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
})();
