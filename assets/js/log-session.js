document.addEventListener('DOMContentLoaded', function () {
    const classroomSelect = document.getElementById('gtp-classroom-select');
    const attendanceContainer = document.getElementById('attendance-checklist-container');
    const addStudentButton = document.getElementById('add-student-button');
    const newStudentInput = document.getElementById('new-student-name');

    if (classroomSelect) {
        classroomSelect.addEventListener('change', function () {
            const classroomId = this.value;
            if (classroomId) {
                fetchStudents(classroomId);
            } else {
                attendanceContainer.innerHTML = '';
            }
        });
    }

    if (addStudentButton) {
        addStudentButton.addEventListener('click', function () {
            const studentName = newStudentInput.value.trim();
            const classroomId = classroomSelect.value;
            if (studentName && classroomId) {
                addStudent(classroomId, studentName);
            }
        });
    }

    function fetchStudents(classroomId) {
        const formData = new FormData();
        formData.append('action', 'gtp_get_students_for_classroom');
        formData.append('classroom_id', classroomId);

        fetch(gtp_ajax.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderChecklist(data.data);
            }
        });
    }

    function addStudent(classroomId, studentName) {
        const formData = new FormData();
        formData.append('action', 'gtp_add_student_to_roster');
        formData.append('classroom_id', classroomId);
        formData.append('student_name', studentName);

        fetch(gtp_ajax.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                appendStudentToChecklist(data.data);
                newStudentInput.value = '';
            } else {
                alert(data.data);
            }
        });
    }

    function renderChecklist(students) {
        let html = '';
        students.forEach(student => {
            html += `<div><input type="checkbox" name="attendance[]" value="${student.id}" checked> ${student.student_name}</div>`;
        });
        attendanceContainer.innerHTML = html;
    }

    function appendStudentToChecklist(student) {
        const div = document.createElement('div');
        div.innerHTML = `<input type="checkbox" name="attendance[]" value="${student.id}" checked> ${student.student_name}`;
        attendanceContainer.appendChild(div);
    }
});