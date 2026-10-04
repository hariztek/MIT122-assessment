/**
 * assets/js/validation.js
 *
 * Client-side pre-submit checks for every form that writes data:
 * register, login, profile details, skill add/edit, session request and
 * review. A form opts in with data-validate="<rule set name>".
 *
 * This is a convenience only. It gives instant feedback without a round
 * trip, but PHP repeats every check (includes/functions.php plus each
 * page), because JavaScript can be switched off or a request crafted by
 * hand. The rules and messages below mirror the server ones, so a
 * student sees the same wording whichever side catches the problem.
 */
(function () {
    'use strict';

    /* ---------------------------------------------------------------- */
    /* Rule builders. Each returns a function: (value, field, form) =>   */
    /* error message string, or '' when the value passes.                */
    /* ---------------------------------------------------------------- */

    /** Length in characters (not bytes), like PHP mb_strlen. */
    function charCount(value) {
        return Array.from(value.trim()).length;
    }

    function required(message) {
        return function (value) {
            return value.trim() === '' ? message : '';
        };
    }

    /** Min/max length, skipped when blank (optional fields). */
    function length(min, max, message) {
        return function (value) {
            if (value.trim() === '') {
                return min > 0 ? message : '';
            }
            var n = charCount(value);
            return n < min || n > max ? message : '';
        };
    }

    function email(message) {
        return function (value) {
            return value.trim() === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim()) ? '' : message;
        };
    }

    function matches(otherName, message) {
        return function (value, field, form) {
            var other = form.elements[otherName];
            return other && value !== other.value ? message : '';
        };
    }

    /** One radio in the group must be checked (value is '' when none is). */
    function chosen(message) {
        return function (value) {
            return value === '' ? message : '';
        };
    }

    /**
     * datetime-local value: real date, in the future, and no later than
     * the input's max attribute (the server sets it to now + 180 days).
     */
    function futureDateTime(invalidMsg, pastMsg, tooLateMsg) {
        return function (value, field) {
            if (value.trim() === '') {
                return '';
            }
            if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value)) {
                return invalidMsg;
            }
            var when = new Date(value);
            if (isNaN(when.getTime())) {
                return invalidMsg;
            }
            if (when <= new Date()) {
                return pastMsg;
            }
            if (field.max && value > field.max) {
                return tooLateMsg;
            }
            return '';
        };
    }

    /* ---------------------------------------------------------------- */
    /* Rule sets, keyed by data-validate, then by field name. Messages   */
    /* match pages/register.php, login.php, profile.php, dashboard.php.  */
    /* ---------------------------------------------------------------- */

    var nameRules = [
        required('Enter your name.'),
        length(2, 100, 'Name must be between 2 and 100 characters.')
    ];

    var RULES = {
        register: {
            name: nameRules,
            email: [required('Enter your email address.'), email('Enter a valid email address.')],
            password: [required('Choose a password.'), length(8, 72, 'Password must be between 8 and 72 characters.')],
            password_confirm: [required('Re-enter your password to confirm it.'), matches('password', 'Passwords do not match.')]
        },
        login: {
            email: [required('Enter your email address.'), email('Enter a valid email address.')],
            password: [required('Enter your password.')]
        },
        profile: {
            name: nameRules,
            campus: [length(0, 100, 'Campus must be at most 100 characters.')],
            bio: [length(0, 1000, 'Bio must be at most 1000 characters.')]
        },
        skill: {
            skill_id: [required('Choose a skill.')],
            availability: [length(0, 255, 'Availability must be at most 255 characters.')],
            description: [length(0, 1000, 'Description must be at most 1000 characters.')]
        },
        request: {
            goal: [
                required('Say what you want to get out of the session.'),
                length(10, 500, 'Keep your goal between 10 and 500 characters.')
            ],
            proposed_time: [
                required('Choose a proposed date and time.'),
                futureDateTime('Enter a valid date and time.', 'Choose a time in the future.', 'Choose a time within the next 180 days.')
            ]
        },
        review: {
            rating: [chosen('Choose a rating from 1 to 5 stars.')],
            comment: [length(0, 1000, 'Keep your comment to 1000 characters or fewer.')]
        }
    };

    /* ---------------------------------------------------------------- */
    /* Showing and clearing errors, using the same markup PHP renders:   */
    /* <p class="field-error" id="..."> inside the .field, plus          */
    /* aria-invalid and aria-describedby on the control.                 */
    /* ---------------------------------------------------------------- */

    /** The element that receives aria-invalid / focus for a field name. */
    function controlFor(form, name) {
        var el = form.elements[name];
        if (el && typeof el.length === 'number' && !el.tagName) {
            return el[0]; // RadioNodeList: first radio stands for the group
        }
        return el || null;
    }

    function valueOf(form, name) {
        var el = form.elements[name];
        if (!el) {
            return '';
        }
        return typeof el.value === 'string' ? el.value : '';
    }

    /** .field wrapper (or the fieldset for radio groups). */
    function wrapperFor(control) {
        return control.closest('fieldset') && control.type === 'radio'
            ? control.closest('fieldset')
            : control.closest('.field');
    }

    function errorId(form, name) {
        var control = controlFor(form, name);
        return (control.id || name) + '-error';
    }

    function setDescribedBy(el, id, add) {
        var ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        ids = ids.filter(function (x) { return x !== id; });
        if (add) {
            ids.push(id);
        }
        if (ids.length) {
            el.setAttribute('aria-describedby', ids.join(' '));
        } else {
            el.removeAttribute('aria-describedby');
        }
    }

    function showError(form, name, message) {
        var control = controlFor(form, name);
        var wrapper = wrapperFor(control);
        if (!wrapper) {
            return;
        }
        // Drop any server-rendered error for this field so only one shows.
        wrapper.querySelectorAll('.field-error').forEach(function (p) { p.remove(); });

        var id = errorId(form, name);
        var describedEl = control.type === 'radio' ? wrapper : control;

        if (message) {
            var p = document.createElement('p');
            p.className = 'field-error';
            p.id = id;
            p.textContent = message;
            wrapper.appendChild(p);
            control.setAttribute('aria-invalid', 'true');
            setDescribedBy(describedEl, id, true);
        } else {
            control.setAttribute('aria-invalid', 'false');
            setDescribedBy(describedEl, id, false);
        }
    }

    function checkField(form, rules, name) {
        var control = controlFor(form, name);
        if (!control) {
            return '';
        }
        var value = valueOf(form, name);
        for (var i = 0; i < rules[name].length; i++) {
            var message = rules[name][i](value, control, form);
            if (message) {
                return message;
            }
        }
        return '';
    }

    /** Summary box at the top of the form, reusing PHP's wording. */
    function setSummary(form, show) {
        var summary = form.querySelector('.form-error-summary[data-js]');
        if (!show) {
            if (summary) {
                summary.remove();
            }
            return;
        }
        // Replace any summary PHP rendered from the last submit (just
        // above the form, or inside it) so only one box shows.
        var prev = form.previousElementSibling;
        if (prev && prev.classList.contains('form-error-summary')) {
            prev.remove();
        }
        form.querySelectorAll('.form-error-summary:not([data-js])').forEach(function (box) { box.remove(); });
        if (!summary) {
            summary = document.createElement('div');
            summary.className = 'form-error-summary';
            summary.setAttribute('role', 'alert');
            summary.setAttribute('data-js', '');
            form.insertBefore(summary, form.firstElementChild);
        }
        summary.textContent = 'Please fix the highlighted fields and try again.';
    }

    /* ---------------------------------------------------------------- */
    /* Wiring                                                            */
    /* ---------------------------------------------------------------- */

    function attach(form) {
        var rules = RULES[form.getAttribute('data-validate')];
        if (!rules) {
            return;
        }
        var touched = {};

        form.addEventListener('submit', function (event) {
            var firstBad = null;
            Object.keys(rules).forEach(function (name) {
                if (!controlFor(form, name)) {
                    return;
                }
                var message = checkField(form, rules, name);
                touched[name] = true;
                showError(form, name, message);
                if (message && !firstBad) {
                    firstBad = controlFor(form, name);
                }
            });
            setSummary(form, firstBad !== null);
            if (firstBad) {
                event.preventDefault();
                // Stop here so main.js's data-confirm prompt never asks
                // about a form that is not going to be sent.
                event.stopImmediatePropagation();
                firstBad.focus();
            }
        });

        // Re-check a field when the student leaves it, and live once it
        // has been flagged, so the message clears as soon as it's fixed.
        Object.keys(rules).forEach(function (name) {
            var el = form.elements[name];
            if (!el) {
                return;
            }
            var targets = el.tagName ? [el] : Array.prototype.slice.call(el);
            targets.forEach(function (t) {
                var recheck = function () {
                    showError(form, name, checkField(form, rules, name));
                    // password_confirm depends on password, keep it in step.
                    if (name === 'password' && touched.password_confirm && rules.password_confirm) {
                        showError(form, 'password_confirm', checkField(form, rules, 'password_confirm'));
                    }
                    if (!form.querySelector('[aria-invalid="true"]')) {
                        setSummary(form, false);
                    }
                };
                // Radios only re-check on change: tabbing through the stars
                // shouldn't flag the rating before one is picked.
                t.addEventListener('blur', function () {
                    if (t.type === 'radio') {
                        return;
                    }
                    if (t.value.trim() !== '' || touched[name]) {
                        touched[name] = true;
                        recheck();
                    }
                });
                t.addEventListener(t.tagName === 'SELECT' || t.type === 'radio' ? 'change' : 'input', function () {
                    if (touched[name]) {
                        recheck();
                    }
                });
            });
        });
    }

    document.querySelectorAll('form[data-validate]').forEach(attach);
})();
