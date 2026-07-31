(function () {
    'use strict';

    var errorMap = [
        [/invalid username|invalid password|invalid user|wrong password|user .*not found/i, 'اسم المستخدم أو كلمة المرور غير صحيحة.'],
        [/user .*disabled|account .*disabled|user disabled/i, 'الحساب موقوف. تواصل مع الدعم.'],
        [/expired|uptime limit reached|session timeout/i, 'انتهت مدة الاشتراك أو الجلسة.'],
        [/traffic limit|bytes limit|quota/i, 'انتهت الحصة المتاحة للاشتراك.'],
        [/already logged in|simultaneous|logged in from another/i, 'الحساب مستخدم من جهاز آخر.'],
        [/no more sessions|session limit|too many sessions/i, 'تم تجاوز عدد الجلسات المسموح بها.'],
        [/radius.*not responding|radius timeout|server.*not responding|temporarily unavailable/i, 'الخدمة غير متاحة مؤقتًا. حاول بعد قليل.']
    ];

    function clean(value) {
        var text = String(value || '').trim();
        return !text || text.indexOf('$(') === 0 ? '' : text;
    }

    function friendlyError(raw) {
        var value = clean(raw);
        if (!value) {
            return '';
        }
        for (var i = 0; i < errorMap.length; i += 1) {
            if (errorMap[i][0].test(value)) {
                return errorMap[i][1];
            }
        }
        return 'تعذر تسجيل الدخول. تحقق من البيانات أو تواصل مع الدعم.';
    }

    function setupError() {
        var rawNode = document.getElementById('router-error-raw');
        if (!rawNode) {
            return;
        }
        var raw = clean(rawNode.textContent);
        var message = friendlyError(raw);
        var box = document.getElementById('login-error');
        var main = document.getElementById('login-error-message') || document.getElementById('fatal-error-message');
        if (main && message) {
            main.textContent = message;
        }
        if (box && message) {
            box.hidden = false;
        }
        if (!raw) {
            var details = rawNode.closest('details');
            if (details) {
                details.hidden = true;
            }
        }
    }

    function setupPortalLinks() {
        var config = window.GreenNetHotspotConfig || {};
        var url = clean(config.subscriberPortalUrl);
        var links = document.querySelectorAll('[data-subscriber-portal]');
        for (var i = 0; i < links.length; i += 1) {
            if (/^https?:\/\//i.test(url) && !/localhost|127\.0\.0\.1/i.test(url)) {
                links[i].href = url;
                links[i].hidden = false;
            } else {
                links[i].hidden = true;
            }
        }
    }

    function setupUnavailableValues() {
        var values = document.querySelectorAll('[data-hotspot-value]');
        for (var i = 0; i < values.length; i += 1) {
            if (!clean(values[i].textContent)) {
                values[i].textContent = 'غير متاح';
                values[i].classList.add('is-unavailable');
            }
        }
        var updated = document.getElementById('last-updated');
        if (updated) {
            updated.textContent = new Date().toLocaleTimeString('ar', {hour: '2-digit', minute: '2-digit'});
        }
    }

    function setupPasswordToggle() {
        var input = document.getElementById('password');
        var toggle = document.getElementById('password-toggle');
        if (!input || !toggle) {
            return;
        }
        toggle.addEventListener('click', function () {
            var visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            toggle.textContent = visible ? 'إظهار' : 'إخفاء';
            toggle.setAttribute('aria-pressed', visible ? 'false' : 'true');
            input.focus();
        });
    }

    function setupLogin() {
        var form = document.getElementById('login-form');
        var submit = document.getElementById('login-submit');
        if (!form || !submit) {
            return;
        }
        form.addEventListener('submit', function (event) {
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }
            if (!form.checkValidity()) {
                return;
            }

            var chap = window.greennetChap;
            if (chap && document.sendin && typeof window.hexMD5 === 'function') {
                event.preventDefault();
                document.sendin.username.value = form.username.value;
                document.sendin.password.value = window.hexMD5(chap.id + form.password.value + chap.challenge);
                document.sendin.dst.value = form.dst.value;
                form.password.value = '';
                form.dataset.submitting = 'true';
                submit.disabled = true;
                submit.querySelector('.button-label').hidden = true;
                submit.querySelector('.button-loading').hidden = false;
                document.sendin.submit();
                return;
            }

            form.dataset.submitting = 'true';
            submit.disabled = true;
            submit.querySelector('.button-label').hidden = true;
            submit.querySelector('.button-loading').hidden = false;
        });
    }

    setupError();
    setupPortalLinks();
    setupUnavailableValues();
    setupPasswordToggle();
    setupLogin();
}());
