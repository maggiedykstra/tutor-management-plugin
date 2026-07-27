document.addEventListener('DOMContentLoaded', function () {
    initClassInfoEditors();
    initRosterModal();
});

function gtpPostAction(action, data) {
    const formData = new FormData();
    formData.append('action', action);
    Object.keys(data).forEach(function (key) {
        formData.append(key, data[key]);
    });
    return fetch(gtp_ajax.ajax_url, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    }).then(function (response) {
        return response.json();
    });
}

function gtpDisplayOrBlank(value) {
    return String(value || '').trim();
}

function initClassInfoEditors() {
    document.querySelectorAll('.gtp-class-card').forEach(function (card) {
        const editBtn = card.querySelector('.gtp-edit-class-info-btn');
        const viewPanel = card.querySelector('.gtp-class-info-view');
        const editPanel = card.querySelector('.gtp-class-info-edit');
        const cancelBtn = card.querySelector('.gtp-cancel-class-info-btn');
        const saveBtn = card.querySelector('.gtp-save-class-info-btn');
        const messageEl = card.querySelector('.gtp-class-info-message');

        if (!editBtn || !viewPanel || !editPanel) {
            return;
        }

        const fields = {
            school: editPanel.querySelector('.gtp-edit-school'),
            teacherFirst: editPanel.querySelector('.gtp-edit-teacher-first'),
            teacherLast: editPanel.querySelector('.gtp-edit-teacher-last'),
            teacherEmail: editPanel.querySelector('.gtp-edit-teacher-email'),
            teacherPhone: editPanel.querySelector('.gtp-edit-teacher-phone'),
            startTime: editPanel.querySelector('.gtp-edit-start-time'),
            endTime: editPanel.querySelector('.gtp-edit-end-time'),
            zoomLink: editPanel.querySelector('.gtp-edit-zoom-link')
        };

        let messageTimer = null;

        function setMessage(text, isError) {
            if (!messageEl) {
                return;
            }
            if (messageTimer) {
                clearTimeout(messageTimer);
                messageTimer = null;
            }
            messageEl.textContent = text || '';
            messageEl.style.color = isError ? '#a00' : 'green';
            if (text && !isError) {
                messageTimer = setTimeout(function () {
                    messageEl.textContent = '';
                    messageTimer = null;
                }, 1000);
            }
        }

        function fillFieldsFromCard() {
            fields.school.value = card.getAttribute('data-school') || '';
            fields.teacherFirst.value = card.getAttribute('data-teacher-first-name') || '';
            fields.teacherLast.value = card.getAttribute('data-teacher-last-name') || '';
            fields.teacherEmail.value = card.getAttribute('data-teacher-email') || '';
            fields.teacherPhone.value = card.getAttribute('data-teacher-phone') || '';
            fields.startTime.value = card.getAttribute('data-start-time') || '';
            fields.endTime.value = card.getAttribute('data-end-time') || '';
            fields.zoomLink.value = card.getAttribute('data-zoom-link') || '';
        }

        function enterEditMode() {
            fillFieldsFromCard();
            viewPanel.hidden = true;
            editPanel.hidden = false;
            editBtn.hidden = true;
            setMessage('', false);
            fields.school.focus();
        }

        function exitEditMode() {
            editPanel.hidden = true;
            viewPanel.hidden = false;
            editBtn.hidden = false;
            setMessage('', false);
        }

        function updateView(data) {
            const teacherName = (data.teacher_first_name + ' ' + data.teacher_last_name).trim();
            card.querySelector('.gtp-info-school').textContent = gtpDisplayOrBlank(data.school);
            card.querySelector('.gtp-info-teacher').textContent = gtpDisplayOrBlank(teacherName);
            card.querySelector('.gtp-info-teacher-email').textContent = gtpDisplayOrBlank(data.teacher_email);
            card.querySelector('.gtp-info-teacher-phone').textContent = gtpDisplayOrBlank(data.teacher_phone);
            card.querySelector('.gtp-info-time').textContent = gtpDisplayOrBlank(data.time_display);

            const zoomEl = card.querySelector('.gtp-info-zoom');
            if (data.zoom_link) {
                zoomEl.innerHTML = '';
                const link = document.createElement('a');
                link.href = data.zoom_link;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = data.zoom_link;
                zoomEl.appendChild(link);
            } else {
                zoomEl.textContent = '';
            }

            card.setAttribute('data-school', data.school || '');
            card.setAttribute('data-teacher-first-name', data.teacher_first_name || '');
            card.setAttribute('data-teacher-last-name', data.teacher_last_name || '');
            card.setAttribute('data-teacher-email', data.teacher_email || '');
            card.setAttribute('data-teacher-phone', data.teacher_phone || '');
            card.setAttribute('data-start-time', data.start_time_input || '');
            card.setAttribute('data-end-time', data.end_time_input || '');
            card.setAttribute('data-zoom-link', data.zoom_link || '');
        }

        editBtn.addEventListener('click', enterEditMode);

        cancelBtn.addEventListener('click', function () {
            fillFieldsFromCard();
            exitEditMode();
        });

        saveBtn.addEventListener('click', function () {
            saveBtn.disabled = true;
            setMessage('Saving...', false);

            gtpPostAction('gtp_update_classroom_info', {
                classroom_id: card.getAttribute('data-classroom-id'),
                school: fields.school.value.trim(),
                teacher_first_name: fields.teacherFirst.value.trim(),
                teacher_last_name: fields.teacherLast.value.trim(),
                teacher_email: fields.teacherEmail.value.trim(),
                teacher_phone: fields.teacherPhone.value.trim(),
                start_time: fields.startTime.value,
                end_time: fields.endTime.value,
                zoom_link: fields.zoomLink.value.trim()
            }).then(function (data) {
                saveBtn.disabled = false;
                if (!data.success) {
                    setMessage(data.data || 'Could not save class information.', true);
                    return;
                }
                updateView(data.data);
                exitEditMode();
            }).catch(function () {
                saveBtn.disabled = false;
                setMessage('Could not save class information.', true);
            });
        });
    });
}

function initRosterModal() {
    const modal = document.getElementById('gtp-roster-modal');
    if (!modal) {
        return;
    }

    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    const modalList = document.getElementById('gtp-modal-student-list');
    const editPanel = document.getElementById('gtp-modal-edit-panel');
    const addPanel = document.getElementById('gtp-modal-add-panel');
    const editStudentId = document.getElementById('gtp-edit-student-id');
    const editFirstName = document.getElementById('gtp-edit-first-name');
    const editLastName = document.getElementById('gtp-edit-last-name');
    const addFirstName = document.getElementById('gtp-add-first-name');
    const addLastName = document.getElementById('gtp-add-last-name');
    const messageEl = document.getElementById('gtp-roster-modal-message');
    const modalTitle = document.getElementById('gtp-roster-modal-title');
    const showAddBtn = document.getElementById('gtp-show-add-student-btn');
    const cancelAddBtn = document.getElementById('gtp-cancel-add-student-btn');
    const saveBtn = document.getElementById('gtp-save-student-btn');

    let activeClassroom = null;
    let draftStudents = [];
    let originalStudents = [];
    let removedIds = [];
    let tempIdCounter = 0;
    let messageTimer = null;
    let isSaving = false;

    function displayName(first, last) {
        return (String(first || '').trim() + ' ' + String(last || '').trim()).trim();
    }

    function setMessage(text, isError) {
        if (messageTimer) {
            clearTimeout(messageTimer);
            messageTimer = null;
        }
        messageEl.textContent = text || '';
        messageEl.style.color = isError ? '#a00' : 'green';
        if (text && !isError) {
            messageTimer = setTimeout(function () {
                messageEl.textContent = '';
                messageTimer = null;
            }, 1000);
        }
    }

    function postAction(action, data) {
        return gtpPostAction(action, data);
    }

    function getStudentsFromCard(card) {
        return Array.from(card.querySelectorAll('.gtp-roster-name')).map(function (item) {
            return {
                id: String(item.getAttribute('data-student-id')),
                first_name: item.getAttribute('data-first-name') || '',
                last_name: item.getAttribute('data-last-name') || ''
            };
        });
    }

    function cloneStudents(students) {
        return students.map(function (student) {
            return {
                id: String(student.id),
                first_name: student.first_name || '',
                last_name: student.last_name || '',
                isNew: !!student.isNew
            };
        });
    }

    function updateRosterCount(card, count) {
        const countEl = card.querySelector('.gtp-roster-count');
        if (countEl) {
            countEl.textContent = '(' + count + ')';
        }
    }

    function renderCardRoster(card, students) {
        const list = card.querySelector('.gtp-roster-list');
        list.innerHTML = '';

        if (!students.length) {
            const empty = document.createElement('li');
            empty.className = 'gtp-roster-empty';
            empty.textContent = 'No students in this roster yet.';
            list.appendChild(empty);
            updateRosterCount(card, 0);
            return;
        }

        students
            .slice()
            .sort(function (a, b) {
                const aKey = ((a.last_name || '') + ' ' + (a.first_name || '')).toLowerCase();
                const bKey = ((b.last_name || '') + ' ' + (b.first_name || '')).toLowerCase();
                return aKey.localeCompare(bKey);
            })
            .forEach(function (student) {
                const li = document.createElement('li');
                li.className = 'gtp-roster-name';
                li.setAttribute('data-student-id', student.id);
                li.setAttribute('data-first-name', student.first_name || '');
                li.setAttribute('data-last-name', student.last_name || '');
                li.textContent = displayName(student.first_name, student.last_name);
                list.appendChild(li);
            });

        updateRosterCount(card, students.length);
    }

    function renderModalList(selectedId) {
        modalList.innerHTML = '';

        if (!draftStudents.length) {
            const empty = document.createElement('div');
            empty.className = 'gtp-modal-empty';
            empty.textContent = 'No students yet. Tap + to add one.';
            modalList.appendChild(empty);
            return;
        }

        draftStudents
            .slice()
            .sort(function (a, b) {
                const aKey = ((a.last_name || '') + ' ' + (a.first_name || '')).toLowerCase();
                const bKey = ((b.last_name || '') + ' ' + (b.first_name || '')).toLowerCase();
                return aKey.localeCompare(bKey);
            })
            .forEach(function (student) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'gtp-modal-student-item' + (String(student.id) === String(selectedId) ? ' is-selected' : '');
                btn.setAttribute('data-student-id', student.id);
                btn.textContent = displayName(student.first_name, student.last_name);
                modalList.appendChild(btn);
            });
    }

    function hideAddPanel() {
        addPanel.hidden = true;
        addFirstName.value = '';
        addLastName.value = '';
    }

    function clearEditPanel() {
        editPanel.hidden = true;
        editStudentId.value = '';
        editFirstName.value = '';
        editLastName.value = '';
    }

    function flushEditPanelToDraft() {
        const studentId = editStudentId.value;
        if (!studentId || editPanel.hidden) {
            return true;
        }

        const firstName = editFirstName.value.trim();
        const lastName = editLastName.value.trim();

        if (!firstName) {
            setMessage('Student first name cannot be empty.', true);
            return false;
        }

        const student = draftStudents.find(function (item) {
            return String(item.id) === String(studentId);
        });

        if (student) {
            student.first_name = firstName;
            student.last_name = lastName;
        }

        return true;
    }

    function showAddPanel() {
        if (!flushEditPanelToDraft()) {
            return;
        }
        clearEditPanel();
        renderModalList();
        addPanel.hidden = false;
        addFirstName.value = '';
        addLastName.value = '';
        setMessage('', false);
        addFirstName.focus();
    }

    function openModal(card) {
        activeClassroom = card;
        const subject = card.querySelector('h2');
        modalTitle.textContent = 'Edit/Add Students' + (subject ? ' — ' + subject.textContent.trim() : '');
        originalStudents = getStudentsFromCard(card);
        draftStudents = cloneStudents(originalStudents);
        removedIds = [];
        tempIdCounter = 0;
        isSaving = false;
        saveBtn.disabled = false;
        setMessage('', false);
        clearEditPanel();
        hideAddPanel();
        renderModalList();
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        activeClassroom = null;
        draftStudents = [];
        originalStudents = [];
        removedIds = [];
        clearEditPanel();
        hideAddPanel();
        setMessage('', false);
        isSaving = false;
        saveBtn.disabled = false;
    }

    document.querySelectorAll('.gtp-open-roster-modal').forEach(function (button) {
        button.addEventListener('click', function () {
            const card = button.closest('[data-classroom-id]');
            if (card) {
                openModal(card);
            }
        });
    });

    modal.querySelectorAll('[data-close-modal]').forEach(function (el) {
        el.addEventListener('click', function () {
            closeModal();
        });
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });

    showAddBtn.addEventListener('click', showAddPanel);

    cancelAddBtn.addEventListener('click', function () {
        hideAddPanel();
        setMessage('', false);
    });

    modalList.addEventListener('click', function (event) {
        const item = event.target.closest('.gtp-modal-student-item');
        if (!item) {
            return;
        }

        if (!flushEditPanelToDraft()) {
            return;
        }

        hideAddPanel();
        const studentId = item.getAttribute('data-student-id');
        const student = draftStudents.find(function (entry) {
            return String(entry.id) === String(studentId);
        });
        if (!student) {
            return;
        }

        editStudentId.value = student.id;
        editFirstName.value = student.first_name || '';
        editLastName.value = student.last_name || '';
        editPanel.hidden = false;
        setMessage('', false);
        renderModalList(student.id);
        editFirstName.focus();
    });

    function syncSelectedEditFields() {
        const studentId = editStudentId.value;
        if (!studentId || editPanel.hidden) {
            return;
        }
        const student = draftStudents.find(function (entry) {
            return String(entry.id) === String(studentId);
        });
        if (!student) {
            return;
        }
        student.first_name = editFirstName.value.trim();
        student.last_name = editLastName.value.trim();
    }

    editFirstName.addEventListener('input', syncSelectedEditFields);
    editLastName.addEventListener('input', syncSelectedEditFields);

    document.getElementById('gtp-remove-student-btn').addEventListener('click', function () {
        const studentId = editStudentId.value;
        if (!studentId) {
            setMessage('Please select a student to remove first.', true);
            return;
        }

        if (!confirm('Remove this student from the class roster? Click Save Changes to confirm.')) {
            return;
        }

        const student = draftStudents.find(function (entry) {
            return String(entry.id) === String(studentId);
        });

        if (student && !student.isNew) {
            removedIds.push(String(student.id));
        }

        draftStudents = draftStudents.filter(function (entry) {
            return String(entry.id) !== String(studentId);
        });

        clearEditPanel();
        renderModalList();
        setMessage('Student marked for removal. Click Save Changes to confirm.', false);
    });

    document.getElementById('gtp-add-student-btn').addEventListener('click', function () {
        const firstName = addFirstName.value.trim();
        const lastName = addLastName.value.trim();

        if (!firstName) {
            setMessage('Student first name cannot be empty.', true);
            return;
        }

        tempIdCounter += 1;
        const newStudent = {
            id: 'new-' + tempIdCounter,
            first_name: firstName,
            last_name: lastName,
            isNew: true
        };

        draftStudents.push(newStudent);
        hideAddPanel();
        renderModalList(newStudent.id);
        editStudentId.value = newStudent.id;
        editFirstName.value = firstName;
        editLastName.value = lastName;
        editPanel.hidden = false;
        setMessage('Student added to list. Click Save Changes to keep it.', false);
    });

    saveBtn.addEventListener('click', function () {
        if (!activeClassroom || isSaving) {
            return;
        }

        if (!flushEditPanelToDraft()) {
            return;
        }

        for (let i = 0; i < draftStudents.length; i += 1) {
            if (!draftStudents[i].first_name.trim()) {
                setMessage('Every student needs a first name before saving.', true);
                return;
            }
        }

        const classroomId = activeClassroom.getAttribute('data-classroom-id');
        const card = activeClassroom;
        const originalById = {};
        originalStudents.forEach(function (student) {
            originalById[String(student.id)] = student;
        });

        const updates = [];
        const additions = [];

        draftStudents.forEach(function (student) {
            if (student.isNew) {
                additions.push(student);
                return;
            }
            const original = originalById[String(student.id)];
            if (
                original &&
                (original.first_name !== student.first_name || original.last_name !== student.last_name)
            ) {
                updates.push(student);
            }
        });

        const removals = removedIds.slice();

        isSaving = true;
        saveBtn.disabled = true;
        setMessage('Saving...', false);

        let chain = Promise.resolve();

        removals.forEach(function (studentId) {
            chain = chain.then(function () {
                return postAction('gtp_remove_student_from_class', {
                    classroom_id: classroomId,
                    student_id: studentId
                }).then(function (data) {
                    if (!data.success) {
                        throw new Error(data.data || 'Could not remove a student.');
                    }
                });
            });
        });

        updates.forEach(function (student) {
            chain = chain.then(function () {
                return postAction('gtp_update_student_name', {
                    classroom_id: classroomId,
                    student_id: student.id,
                    first_name: student.first_name,
                    last_name: student.last_name
                }).then(function (data) {
                    if (!data.success) {
                        throw new Error(data.data || 'Could not update a student.');
                    }
                });
            });
        });

        additions.forEach(function (student) {
            chain = chain.then(function () {
                return postAction('gtp_add_student_to_roster', {
                    classroom_id: classroomId,
                    first_name: student.first_name,
                    last_name: student.last_name
                }).then(function (data) {
                    if (!data.success) {
                        throw new Error(data.data || 'Could not add a student.');
                    }
                    student.id = String(data.data.id);
                    student.isNew = false;
                });
            });
        });

        chain
            .then(function () {
                const finalStudents = draftStudents.map(function (student) {
                    return {
                        id: String(student.id),
                        first_name: student.first_name,
                        last_name: student.last_name
                    };
                });
                renderCardRoster(card, finalStudents);
                closeModal();
            })
            .catch(function (error) {
                isSaving = false;
                saveBtn.disabled = false;
                setMessage(error.message || 'Could not save changes.', true);
            });
    });
}
