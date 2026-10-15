(function () {
    'use strict';
    document.querySelectorAll('[data-profile-picture-form]').forEach(function (form) {
        var input = form.querySelector('input[type="file"]');
        var preview = form.querySelector('[data-profile-picture-preview]');
        var initials = form.querySelector('[data-profile-picture-initials]');
        var status = form.querySelector('[role="status"]');
        var save = form.querySelector('button[type="submit"]');
        var reset = form.querySelector('[data-profile-picture-reset]');
        var selection = form.querySelector('[data-profile-picture-selection]');
        var initialSource = preview.getAttribute('src');
        var initiallyHidden = preview.hidden;
        var previewUrl = null;
        save.disabled = true;
        function resetPreview() {
            if (previewUrl !== null) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
            preview.src = initialSource;
            preview.hidden = initiallyHidden;
            initials.hidden = !initiallyHidden;
            status.textContent = '';
            input.setCustomValidity('');
            save.disabled = true;
            reset.hidden = true;
            selection.textContent = 'No new picture selected';
        }
        reset.addEventListener('click', function () {
            input.value = '';
            resetPreview();
            input.focus();
        });
        input.addEventListener('change', function () {
            resetPreview();
            var file = input.files[0];
            if (!file) { return; }
            reset.hidden = false;
            selection.textContent = file.name;
            var error = '';
            if (file.size > 2097152) { error = 'Choose a picture no larger than 2 MB.'; }
            else if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) { error = 'Choose a JPG, PNG or WebP picture.'; }
            if (error) {
                status.textContent = error;
                input.setCustomValidity(error);
                save.disabled = true;
                return;
            }
            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
            preview.hidden = false;
            initials.hidden = true;
            save.disabled = false;
        });
    });
}());
