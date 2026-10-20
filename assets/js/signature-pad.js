(function () {
    'use strict';

    document.querySelectorAll('[data-signature-form]').forEach(function (form) {
        var canvas = form.querySelector('canvas');
        var context = canvas.getContext('2d');
        var drawing = form.querySelector('[data-signature-drawing]');
        var status = form.querySelector('[data-signature-status]');
        var save = form.querySelector('[data-signature-save]');
        var undo = form.querySelector('[data-signature-undo]');
        var clear = form.querySelector('[data-signature-clear]');
        var strokes = [];
        var activePointer = null;
        var pointCount = 0;

        if (!context || !window.PointerEvent) {
            status.textContent = 'Use a browser that supports drawing to save your signature.';
            return;
        }

        function position(event) {
            var bounds = canvas.getBoundingClientRect();
            return [
                Math.round(Math.max(0, Math.min(1, (event.clientX - bounds.left) / bounds.width)) * 10000) / 10000,
                Math.round(Math.max(0, Math.min(1, (event.clientY - bounds.top) / bounds.height)) * 10000) / 10000
            ];
        }

        function distance() {
            var total = 0;
            strokes.forEach(function (stroke) {
                for (var i = 1; i < stroke.length; i++) {
                    total += Math.hypot((stroke[i][0] - stroke[i - 1][0]) * 900, (stroke[i][1] - stroke[i - 1][1]) * 240);
                }
            });
            return total;
        }

        function update() {
            save.disabled = activePointer !== null || distance() < 12;
            undo.disabled = strokes.length === 0 || activePointer !== null;
            clear.disabled = strokes.length === 0 || activePointer !== null;
            drawing.value = '';
        }

        function render() {
            var bounds = canvas.getBoundingClientRect();
            var ratio = Math.min(window.devicePixelRatio || 1, 3);
            canvas.width = Math.max(1, Math.round(bounds.width * ratio));
            canvas.height = Math.max(1, Math.round(bounds.height * ratio));
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            context.strokeStyle = '#123568';
            context.fillStyle = '#123568';
            context.lineWidth = 2;
            context.lineCap = 'round';
            context.lineJoin = 'round';
            strokes.forEach(function (stroke) {
                context.beginPath();
                context.moveTo(stroke[0][0] * bounds.width, stroke[0][1] * bounds.height);
                if (stroke.length === 1) {
                    context.arc(stroke[0][0] * bounds.width, stroke[0][1] * bounds.height, 1, 0, Math.PI * 2);
                    context.fill();
                } else {
                    stroke.slice(1).forEach(function (point) {
                        context.lineTo(point[0] * bounds.width, point[1] * bounds.height);
                    });
                    context.stroke();
                }
            });
        }

        canvas.addEventListener('pointerdown', function (event) {
            if (activePointer !== null || event.isPrimary === false || event.button !== 0) { return; }
            if (strokes.length >= 200 || pointCount >= 12000) {
                status.textContent = 'The drawing is too long. Clear the box and sign again.';
                return;
            }
            event.preventDefault();
            activePointer = event.pointerId;
            canvas.setPointerCapture(activePointer);
            strokes.push([position(event)]);
            pointCount++;
            status.textContent = '';
            render();
            update();
        });

        canvas.addEventListener('pointermove', function (event) {
            if (event.pointerId !== activePointer) { return; }
            event.preventDefault();
            if (pointCount >= 12000) {
                status.textContent = 'The drawing is too long. Clear the box and sign again.';
                return;
            }
            var point = position(event);
            var stroke = strokes[strokes.length - 1];
            var previous = stroke[stroke.length - 1];
            if (point[0] === previous[0] && point[1] === previous[1]) { return; }
            stroke.push(point);
            pointCount++;
            render();
        });

        function finish(event) {
            if (event.pointerId !== activePointer) { return; }
            activePointer = null;
            update();
            status.textContent = save.disabled ? 'Draw your signature before saving.' : 'Signature ready to save.';
        }
        canvas.addEventListener('pointerup', finish);
        canvas.addEventListener('pointercancel', finish);
        canvas.addEventListener('lostpointercapture', finish);
        undo.addEventListener('click', function () {
            var removed = strokes.pop();
            if (removed) { pointCount -= removed.length; }
            render();
            update();
            status.textContent = strokes.length ? 'Last stroke removed.' : 'Signing box cleared.';
        });
        clear.addEventListener('click', function () {
            strokes = [];
            pointCount = 0;
            render();
            update();
            status.textContent = 'Signing box cleared.';
        });
        form.addEventListener('submit', function (event) {
            if (activePointer !== null || distance() < 12) {
                event.preventDefault();
                status.textContent = 'Please draw your signature before saving.';
                return;
            }
            var bounds = canvas.getBoundingClientRect();
            drawing.value = JSON.stringify({strokes: strokes, aspect_ratio: bounds.width / bounds.height});
        });

        if (window.ResizeObserver) { new ResizeObserver(render).observe(canvas); }
        else { window.addEventListener('resize', render); }
        render();
        update();
    });
}());
