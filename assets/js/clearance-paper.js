(function () {
    'use strict';
    const viewport = document.querySelector('[data-paper-scroll]');
    const sheet = document.querySelector('[data-paper-sheet]');
    const output = document.querySelector('[data-paper-scale]');
    const baseWidth = 1123;
    let scale = 1;
    let fitting = !window.matchMedia('(max-width: 600px)').matches;
    function resize(value, center = true) {
        const oldWidth = sheet.getBoundingClientRect().width;
        const oldHeight = sheet.getBoundingClientRect().height;
        const x = (viewport.scrollLeft + viewport.clientWidth / 2) / Math.max(1, oldWidth);
        const y = (viewport.scrollTop + viewport.clientHeight / 2) / Math.max(1, oldHeight);
        scale = Math.max(.2, Math.min(3, value));
        sheet.style.width = Math.round(baseWidth * scale) + 'px';
        output.value = Math.round(scale * 100) + '%';
        document.querySelector('[data-paper-zoom="out"]').disabled = scale <= .2;
        document.querySelector('[data-paper-zoom="in"]').disabled = scale >= 3;
        if (center) {
            viewport.scrollLeft = x * sheet.getBoundingClientRect().width - viewport.clientWidth / 2;
            viewport.scrollTop = y * sheet.getBoundingClientRect().height - viewport.clientHeight / 2;
        } else { viewport.scrollLeft = 0; viewport.scrollTop = 0; }
    }
    function fit() { resize((viewport.clientWidth - 24) / baseWidth, false); }
    document.querySelectorAll('[data-paper-zoom]').forEach(button => button.addEventListener('click', () => {
        const action = button.dataset.paperZoom;
        fitting = action === 'fit';
        if (fitting) fit();
        else resize(action === 'read' ? 1 : scale + (action === 'in' ? .25 : -.25));
    }));
    new ResizeObserver(() => { if (fitting) fit(); }).observe(viewport);
    if (fitting) fit(); else resize(1, false);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && window.parent !== window) {
            window.parent.postMessage({type:'cleargo-close-clearance-preview'}, window.location.origin);
        }
    });
}());
