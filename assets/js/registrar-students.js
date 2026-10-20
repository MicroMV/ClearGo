(function () {
    'use strict';

    document.querySelectorAll('.student-action-form').forEach(function (actionForm) {
        actionForm.addEventListener('submit', function (event) {
            var message = event.submitter && event.submitter.dataset.confirm;
            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    var modal = document.getElementById('student-edit-modal');
    if (!modal) return;

    var form = modal.querySelector('[data-student-edit-form]');
    var title = document.getElementById('student-edit-title');
    var opener = null;

    function openEditor(link) {
        var record = JSON.parse(link.dataset.studentRecord);
        form.reset();
        Object.keys(record).forEach(function (name) {
            form.elements.namedItem(name).value = record[name];
        });
        title.textContent = 'Edit ' + record.student_number;
        opener = link;
        modal.showModal();
        document.body.classList.add('student-editor-open');
    }

    document.querySelectorAll('[data-student-edit]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            openEditor(link);
        });
    });

    modal.querySelectorAll('[data-close-student-edit]').forEach(function (button) {
        button.addEventListener('click', function () { modal.close(); });
    });
    modal.addEventListener('keydown', function (event) {
        if (event.key !== 'Tab') return;
        var controls = modal.querySelectorAll('input:not([type="hidden"]):not([disabled]), select:not([disabled]), button:not([disabled])');
        var first = controls[0];
        var last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
    modal.addEventListener('click', function (event) {
        if (event.target !== modal) return;
        var bounds = modal.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
            modal.close();
        }
    });
    modal.addEventListener('close', function () {
        document.body.classList.remove('student-editor-open');
        var url = new URL(window.location.href);
        if (url.searchParams.has('edit')) {
            url.searchParams.delete('edit');
            window.history.replaceState(window.history.state, '', url);
        }
        if (opener) opener.focus({ preventScroll: true });
    });

    if (modal.dataset.initialEdit === 'true') {
        var selectedId = form.elements.namedItem('student_id').value;
        opener = Array.from(document.querySelectorAll('[data-student-edit]')).find(function (link) {
            return String(JSON.parse(link.dataset.studentRecord).student_id) === selectedId;
        }) || null;
        modal.showModal();
        document.body.classList.add('student-editor-open');
    }
}());
