(function () {
    'use strict';

    var invalidClasses = ['form-input-invalid', 'form-select-invalid', 'form-textarea-invalid', 'form-choice-invalid'];

    function validationControls(form) {
        return Array.prototype.slice.call(form.querySelectorAll('input, select, textarea')).filter(function (control) {
            var type = String(control.type || '').toLowerCase();
            return !control.disabled && ['hidden', 'button', 'submit', 'reset'].indexOf(type) === -1;
        });
    }

    function invalidClass(control) {
        if (control.tagName === 'SELECT') {
            return 'form-select-invalid';
        }
        if (control.tagName === 'TEXTAREA') {
            return 'form-textarea-invalid';
        }
        if (control.type === 'checkbox' || control.type === 'radio') {
            return 'form-choice-invalid';
        }
        return 'form-input-invalid';
    }

    function fieldRoot(control) {
        return control.closest('.member-skin-basic-field') || control.closest('p') || control.parentElement;
    }

    function noteId(control) {
        var existing = control.getAttribute('data-validation-error-id');
        if (existing) {
            return existing;
        }

        var base = control.id || control.name || 'field';
        var id = 'member_validation_error_' + base.replace(/[^A-Za-z0-9_-]/g, '_');
        control.setAttribute('data-validation-error-id', id);
        return id;
    }

    function setDescription(control, id, add) {
        var ids = (control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        var index = ids.indexOf(id);
        if (add && index === -1) {
            ids.push(id);
        }
        if (!add && index !== -1) {
            ids.splice(index, 1);
        }
        if (ids.length > 0) {
            control.setAttribute('aria-describedby', ids.join(' '));
        } else {
            control.removeAttribute('aria-describedby');
        }
    }

    function message(control) {
        var custom = control.getAttribute('data-validation-message') || '';
        if (custom !== '') {
            return custom;
        }
        if (control.validity && control.validity.valueMissing) {
            return '필수 항목입니다.';
        }
        if (control.validity && control.validity.typeMismatch) {
            return '입력 형식을 확인해 주세요.';
        }
        if (control.validity && control.validity.patternMismatch) {
            return '입력 형식을 확인해 주세요.';
        }
        if (control.validity && control.validity.tooShort) {
            return '입력값이 너무 짧습니다.';
        }
        if (control.validity && control.validity.tooLong) {
            return '입력값이 너무 깁니다.';
        }
        return control.validationMessage || '입력값을 확인해 주세요.';
    }

    function clear(control) {
        var id = control.getAttribute('data-validation-error-id');
        invalidClasses.forEach(function (className) {
            control.classList.remove(className);
        });
        control.removeAttribute('aria-invalid');
        if (!id) {
            return;
        }
        setDescription(control, id, false);
        var note = document.getElementById(id);
        if (note) {
            note.remove();
        }
    }

    function mark(control) {
        var id = noteId(control);
        var root = fieldRoot(control);
        control.classList.add(invalidClass(control));
        control.setAttribute('aria-invalid', 'true');
        setDescription(control, id, true);

        if (!root) {
            return;
        }
        root.classList.add('validation-field');

        var note = document.getElementById(id);
        if (!note) {
            note = document.createElement('p');
            note.id = id;
            note.className = 'validation-error-note member-skin-basic-validation-note';
            note.setAttribute('role', 'alert');
            root.appendChild(note);
        }
        note.textContent = message(control);
    }

    function validateForm(form, focusFirstInvalid) {
        var controls = validationControls(form);
        var invalid = controls.filter(function (control) {
            return !(control.validity && control.validity.valid);
        });
        controls.forEach(function (control) {
            if (invalid.indexOf(control) === -1) {
                clear(control);
            } else {
                mark(control);
            }
        });
        if (focusFirstInvalid && invalid.length > 0 && typeof invalid[0].focus === 'function') {
            invalid[0].focus({ preventScroll: false });
        }
        return invalid.length === 0;
    }

    function refreshControl(control) {
        if (!control.closest('[data-sr-validate-form]')) {
            return;
        }
        if (control.validity && control.validity.valid) {
            clear(control);
        } else if (control.getAttribute('aria-invalid') === 'true') {
            mark(control);
        }
    }

    document.addEventListener('submit', function (event) {
        var form = event.target && event.target.closest ? event.target.closest('[data-sr-validate-form]') : null;
        if (!form) {
            return;
        }
        var valid = validateForm(form, true);
        if (!form.checkValidity() || !valid) {
            event.preventDefault();
        }
    }, true);

    Array.prototype.slice.call(document.querySelectorAll('[data-sr-validate-form]')).forEach(function (form) {
        form.setAttribute('novalidate', 'novalidate');
    });

    document.addEventListener('input', function (event) {
        var control = event.target && event.target.closest ? event.target.closest('input, select, textarea') : null;
        if (control) {
            refreshControl(control);
        }
    });

    document.addEventListener('change', function (event) {
        var control = event.target && event.target.closest ? event.target.closest('input, select, textarea') : null;
        if (control) {
            refreshControl(control);
        }
    });

    var toastStack = document.querySelector('[data-member-toast-stack]');
    if (!toastStack) {
        return;
    }

    function closeToast(toast) {
        if (!toast) {
            return;
        }
        toast.classList.add('is-hiding');
        window.setTimeout(function () {
            toast.remove();
            if (toastStack.children.length === 0) {
                toastStack.remove();
            }
        }, 180);
    }

    toastStack.addEventListener('click', function (event) {
        var closeButton = event.target && event.target.closest ? event.target.closest('[data-member-toast-close]') : null;
        if (closeButton) {
            closeToast(closeButton.closest('[data-member-toast]'));
        }
    });

    Array.prototype.slice.call(toastStack.querySelectorAll('[data-member-toast]')).forEach(function (toast) {
        window.setTimeout(function () {
            closeToast(toast);
        }, 6500);
    });
}());
