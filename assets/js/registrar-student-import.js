(function () {
    'use strict';

    var input = document.querySelector('[data-import-file]');
    var fileName = document.querySelector('[data-import-file-name]');

    if (!input || !fileName) return;

    var fileBox = input.closest('.import-file-box');

    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        fileName.textContent = file ? file.name : 'Drag and drop an Excel or CSV file here';
        fileBox.classList.toggle('has-file', Boolean(file));
    });

    ['dragenter', 'dragover'].forEach(function (eventName) {
        input.addEventListener(eventName, function () { fileBox.classList.add('is-dragging'); });
    });

    ['dragleave', 'drop'].forEach(function (eventName) {
        input.addEventListener(eventName, function () { fileBox.classList.remove('is-dragging'); });
    });
}());
