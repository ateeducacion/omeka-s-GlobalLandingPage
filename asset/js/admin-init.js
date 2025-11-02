document.addEventListener('DOMContentLoaded', function () {
    var selectNodes = document.querySelectorAll('.select2-field');
    if (!selectNodes.length) {
        return;
    }

    if (!window.jQuery || typeof window.jQuery.fn.select2 !== 'function') {
        return;
    }

    var $ = window.jQuery;
    Array.prototype.forEach.call(selectNodes, function (element) {
        var $element = $(element);
        var placeholder = $element.data('placeholder') || 'Select featured sites';

        $element.select2({
            width: '100%',
            placeholder: placeholder,
            allowClear: true,
        });
    });
});
