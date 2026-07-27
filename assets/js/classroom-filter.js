document.addEventListener('DOMContentLoaded', function () {
    const subjectSelect = document.getElementById('gtp-subject-select');
    const classroomSelect = document.getElementById('gtp-classroom-select');
    const startTimeInput = document.getElementById('gtp-session-start-time');
    const endTimeInput = document.getElementById('gtp-session-end-time');

    function clearTimes() {
        if (startTimeInput) {
            startTimeInput.value = '';
        }
        if (endTimeInput) {
            endTimeInput.value = '';
        }
    }

    function fillTimesFromSelectedClass() {
        if (!classroomSelect) {
            return;
        }
        const selected = classroomSelect.options[classroomSelect.selectedIndex];
        if (startTimeInput) {
            startTimeInput.value = selected ? (selected.getAttribute('data-start-time') || '') : '';
        }
        if (endTimeInput) {
            endTimeInput.value = selected ? (selected.getAttribute('data-end-time') || '') : '';
        }
    }

    if (subjectSelect) {
        subjectSelect.addEventListener('change', function () {
            const subject = this.value;
            if (subject) {
                fetchClassrooms(subject);
            } else {
                classroomSelect.innerHTML = '<option value="">-- Select a subject first --</option>';
                classroomSelect.disabled = true;
                clearTimes();
            }
        });
    }

    if (classroomSelect) {
        classroomSelect.addEventListener('change', fillTimesFromSelectedClass);
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
            const startTime = classroom.start_time_input || '';
            const endTime = classroom.end_time_input || '';
            html += `<option value="${classroom.id}" data-start-time="${escapeAttr(startTime)}" data-end-time="${escapeAttr(endTime)}">${classroom.school} - ${classroom.teacher_first_name} ${classroom.teacher_last_name}</option>`;
        });
        classroomSelect.innerHTML = html;
        classroomSelect.disabled = false;
        clearTimes();
    }

    function escapeAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
});
