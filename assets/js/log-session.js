document.addEventListener('DOMContentLoaded', function () {
    const classroomSelect = document.getElementById('gtp-classroom-select');
    const attendanceContainer = document.getElementById('attendance-checklist-container');
    const addStudentButton = document.getElementById('add-student-button');
    const firstNameInput = document.getElementById('new-student-first-name');
    const lastNameInput = document.getElementById('new-student-last-name');
    const showAddStudentBtn = document.getElementById('gtp-show-add-student');
    const cancelAddStudentBtn = document.getElementById('gtp-cancel-add-student');
    const addStudentPanel = document.getElementById('gtp-add-student-panel');
    const noShowToggle = document.getElementById('gtp-no-show');
    const startTimeInput = document.getElementById('gtp-session-start-time');
    const endTimeInput = document.getElementById('gtp-session-end-time');
    const topicInput = document.getElementById('gtp-session-topic');
    const topicLabel = document.getElementById('gtp-topic-label');

    function syncTopicRequired() {
        if (!topicInput) {
            return;
        }
        const optional = isNoShow();
        topicInput.required = !optional;
        if (topicLabel) {
            topicLabel.textContent = optional ? 'Topic covered (optional)' : 'Topic covered';
        }
    }

    function showAddStudentPanel() {
        if (!addStudentPanel || !showAddStudentBtn) {
            return;
        }
        addStudentPanel.hidden = false;
        addStudentPanel.style.display = '';
        showAddStudentBtn.hidden = true;
        if (firstNameInput) {
            firstNameInput.focus();
        }
    }

    function hideAddStudentPanel() {
        if (!addStudentPanel || !showAddStudentBtn) {
            return;
        }
        addStudentPanel.hidden = true;
        addStudentPanel.style.display = '';
        showAddStudentBtn.hidden = false;
        if (firstNameInput) {
            firstNameInput.value = '';
        }
        if (lastNameInput) {
            lastNameInput.value = '';
        }
    }

    if (showAddStudentBtn) {
        showAddStudentBtn.addEventListener('click', function () {
            if (!classroomSelect || !classroomSelect.value) {
                alert('Please select a class first.');
                return;
            }
            showAddStudentPanel();
        });
    }

    if (cancelAddStudentBtn) {
        cancelAddStudentBtn.addEventListener('click', hideAddStudentPanel);
    }
    function fillTimesFromSelectedClass() {
        if (!classroomSelect) {
            return;
        }
        const selected = classroomSelect.options[classroomSelect.selectedIndex];
        const startTime = selected ? (selected.getAttribute('data-start-time') || '') : '';
        const endTime = selected ? (selected.getAttribute('data-end-time') || '') : '';
        if (startTimeInput) {
            startTimeInput.value = startTime;
        }
        if (endTimeInput) {
            endTimeInput.value = endTime;
        }
    }

    if (classroomSelect) {
        classroomSelect.addEventListener('change', function () {
            const classroomId = this.value;
            fillTimesFromSelectedClass();
            if (classroomId) {
                fetchStudents(classroomId);
            } else if (attendanceContainer) {
                attendanceContainer.innerHTML = '';
            }
        });
    }

    if (addStudentButton) {
        addStudentButton.addEventListener('click', function () {
            const firstName = (firstNameInput.value || '').trim();
            const lastName = (lastNameInput.value || '').trim();
            const classroomId = classroomSelect.value;
            if (!classroomId) {
                alert('Please select a class first.');
                return;
            }
            if (!firstName) {
                alert('Student first name cannot be empty.');
                return;
            }
            addStudent(classroomId, firstName, lastName);
        });
    }

    if (noShowToggle) {
        noShowToggle.addEventListener('change', function () {
            setAttendanceChecked(!this.checked);
            syncTopicRequired();
        });
        syncTopicRequired();
    }

    function isNoShow() {
        return noShowToggle && noShowToggle.checked;
    }

    function setAttendanceChecked(checked) {
        if (!attendanceContainer) {
            return;
        }
        attendanceContainer.querySelectorAll('input[name="attendance[]"]').forEach(function (checkbox) {
            checkbox.checked = checked;
        });
    }

    function displayName(student) {
        if (student.display_name) {
            return student.display_name;
        }
        const first = student.first_name || '';
        const last = student.last_name || '';
        return (first + ' ' + last).trim() || student.student_name || '';
    }

    function fetchStudents(classroomId) {
        const formData = new FormData();
        formData.append('action', 'gtp_get_students_for_classroom');
        formData.append('classroom_id', classroomId);

        fetch(gtp_ajax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderChecklist(data.data);
            }
        });
    }

    function addStudent(classroomId, firstName, lastName) {
        const formData = new FormData();
        formData.append('action', 'gtp_add_student_to_roster');
        formData.append('classroom_id', classroomId);
        formData.append('first_name', firstName);
        formData.append('last_name', lastName);

        fetch(gtp_ajax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                appendStudentToChecklist(data.data);
                hideAddStudentPanel();
            } else {
                alert(data.data || 'Could not add student.');
            }
        })
        .catch(function () {
            alert('Could not add student. Please try again.');
        });
    }

    function renderChecklist(students) {
        const checkedAttr = isNoShow() ? '' : ' checked';
        let html = '';
        students.forEach(student => {
            html += `<div><input type="checkbox" name="attendance[]" value="${student.id}"${checkedAttr}> ${displayName(student)}</div>`;
        });
        attendanceContainer.innerHTML = html;
    }

    function appendStudentToChecklist(student) {
        const checkedAttr = isNoShow() ? '' : ' checked';
        const div = document.createElement('div');
        div.innerHTML = `<input type="checkbox" name="attendance[]" value="${student.id}"${checkedAttr}> ${displayName(student)}`;
        attendanceContainer.appendChild(div);
    }
});
