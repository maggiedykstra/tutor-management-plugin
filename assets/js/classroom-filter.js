
document.addEventListener('DOMContentLoaded', function () {
    const subjectSelect = document.getElementById('gtp-subject-select');
    const classroomSelect = document.getElementById('gtp-classroom-select');

    if (subjectSelect) {
        subjectSelect.addEventListener('change', function () {
            const subject = this.value;
            if (subject) {
                fetchClassrooms(subject);
            } else {
                classroomSelect.innerHTML = '<option value="">-- Select a subject first --</option>';
                classroomSelect.disabled = true;
            }
        });
    }

    function fetchClassrooms(subject) {
        const formData = new FormData();
        formData.append('action', 'gtp_get_classrooms_for_subject');
        formData.append('subject', subject);
        formData.append('is_substitute', window.location.href.includes('/log-substitute/'));

        fetch(gtp_ajax.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderClassroomOptions(data.data);
            }
        });
    }

    function renderClassroomOptions(classrooms) {
        let html = '<option value="">-- Select a Class --</option>';
        classrooms.forEach(classroom => {
            html += `<option value="${classroom.id}">${classroom.school} - ${classroom.teacher_first_name} ${classroom.teacher_last_name}</option>`;
        });
        classroomSelect.innerHTML = html;
        classroomSelect.disabled = false;
    }
});
