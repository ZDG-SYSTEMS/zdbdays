// ============================================================
// ZD Birthdays — Admin JS
// ============================================================

document.addEventListener('DOMContentLoaded', function () {
    initLockSystem();
    initImagePreview();
    initUploadCompression();
    initToasts();
    initBdayPickers();
});

// ============================================================
// UPLOAD COMPRESSION
// Downscales a chosen photo in the browser BEFORE it is uploaded.
// A full-resolution phone photo (several MB) is the main reason saving
// an employee felt slow — shrinking it client-side means far less data
// travels over the wire, so the save returns quickly. The server still
// re-optimises as a safety net (see storeOptimizedImage in functions.php).
// ============================================================
var UPLOAD_MAX_DIM = 1000;   // px on the long edge — keep in step with IMAGE_MAX_DIM
var UPLOAD_QUALITY = 0.82;
var UPLOAD_SKIP_BYTES = 300 * 1024; // already small enough — don't bother

function initUploadCompression() {
    document.querySelectorAll('form input[type="file"][name="image"]').forEach(function (input) {
        var form = input.form;
        if (!form) return;

        form.addEventListener('submit', function (e) {
            if (form.dataset.imgReady === '1') return; // compressed pass — let it through

            var file = input.files && input.files[0];
            // Only lossy raster types; leave GIFs (animation) and tiny files alone.
            if (!file || !/^image\/(jpeg|png|webp)$/.test(file.type) || file.size < UPLOAD_SKIP_BYTES) {
                return;
            }

            e.preventDefault();
            compressImage(file, UPLOAD_MAX_DIM, UPLOAD_QUALITY).then(function (blob) {
                if (blob && blob.size < file.size) {
                    var dt = new DataTransfer();
                    dt.items.add(new File([blob], file.name, { type: blob.type }));
                    input.files = dt.files;
                }
            }).catch(function () {
                /* fall back to the original file */
            }).finally(function () {
                form.dataset.imgReady = '1';
                form.submit();
            });
        });
    });
}

// Resolve to a downscaled Blob of the same image type (transparency preserved
// for PNG/WebP). Rejects if the image can't be decoded.
function compressImage(file, maxDim, quality) {
    return new Promise(function (resolve, reject) {
        var url = URL.createObjectURL(file);
        var img = new Image();
        img.onload = function () {
            var w = img.naturalWidth, h = img.naturalHeight;
            var scale = Math.min(1, maxDim / Math.max(w, h));
            var canvas = document.createElement('canvas');
            canvas.width  = Math.max(1, Math.round(w * scale));
            canvas.height = Math.max(1, Math.round(h * scale));
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(url);
            var type = (file.type === 'image/png' || file.type === 'image/webp') ? file.type : 'image/jpeg';
            canvas.toBlob(function (blob) { resolve(blob); }, type, quality);
        };
        img.onerror = function () { URL.revokeObjectURL(url); reject(); };
        img.src = url;
    });
}

// ============================================================
// BIRTHDAY PICKER — calendar-style month/day picker (no year)
// Markup: <div class="bday-picker" data-value="MM-DD">
//           <input type="hidden" name="birthdate">
//           <button class="bday-display"><span class="bday-display-text"></span>…</button>
//         </div>
// ============================================================
var BDAY_MONTHS = ['January','February','March','April','May','June',
                   'July','August','September','October','November','December'];
// Leap year so 29 Feb is always selectable
var BDAY_DAYS   = [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

function initBdayPickers() {
    document.querySelectorAll('.bday-picker').forEach(setupBdayPicker);
}

function setupBdayPicker(picker) {
    var hidden  = picker.querySelector('input[type="hidden"]');
    var display = picker.querySelector('.bday-display-text');
    var trigger = picker.querySelector('.bday-display');
    if (!hidden || !trigger) return;

    // Parse initial MM-DD value
    var initial = (picker.dataset.value || '').match(/^(\d{2})-(\d{2})$/);
    var selMonth = initial ? parseInt(initial[1], 10) - 1 : null; // 0-based
    var selDay   = initial ? parseInt(initial[2], 10) : null;
    var viewMonth = selMonth !== null ? selMonth : new Date().getMonth();

    // Calendar popover
    var cal = document.createElement('div');
    cal.className = 'bday-cal hidden';
    picker.appendChild(cal);

    function refreshDisplay() {
        if (selMonth !== null && selDay !== null) {
            display.textContent = BDAY_MONTHS[selMonth] + ' ' + selDay;
            picker.classList.add('has-value');
        } else {
            display.textContent = 'Select month and day';
            picker.classList.remove('has-value');
        }
    }

    function render() {
        var html = '<div class="bday-cal-head">' +
            '<button type="button" class="bday-nav" data-dir="-1" aria-label="Previous month">&#8249;</button>' +
            '<span class="bday-cal-month">' + BDAY_MONTHS[viewMonth] + '</span>' +
            '<button type="button" class="bday-nav" data-dir="1" aria-label="Next month">&#8250;</button>' +
            '</div><div class="bday-cal-grid">';
        for (var d = 1; d <= BDAY_DAYS[viewMonth]; d++) {
            var isSel = (viewMonth === selMonth && d === selDay);
            html += '<button type="button" class="bday-day' + (isSel ? ' is-selected' : '') +
                    '" data-day="' + d + '">' + d + '</button>';
        }
        html += '</div>';
        cal.innerHTML = html;
    }

    function open()  { render(); cal.classList.remove('hidden'); picker.classList.add('open'); document.addEventListener('click', onOutside, true); }
    function close() { cal.classList.add('hidden'); picker.classList.remove('open'); document.removeEventListener('click', onOutside, true); }
    function onOutside(e) { if (!picker.contains(e.target)) close(); }

    trigger.addEventListener('click', function () {
        cal.classList.contains('hidden') ? open() : close();
    });

    cal.addEventListener('click', function (e) {
        var nav = e.target.closest('.bday-nav');
        if (nav) {
            viewMonth = (viewMonth + parseInt(nav.dataset.dir, 10) + 12) % 12;
            render();
            return;
        }
        var dayBtn = e.target.closest('.bday-day');
        if (dayBtn) {
            selMonth = viewMonth;
            selDay   = parseInt(dayBtn.dataset.day, 10);
            hidden.value = pad2(selMonth + 1) + '-' + pad2(selDay);
            refreshDisplay();
            close();
        }
    });

    refreshDisplay();
}

function pad2(n) { return String(n).padStart(2, '0'); }

// ============================================================
// TOASTS — auto-dismiss success flashes after 5s
// ============================================================
function initToasts() {
    document.querySelectorAll('.toast').forEach(function (toast) {
        var timer = setTimeout(function () { dismissToast(toast); }, 5000);
        var btn = toast.querySelector('.toast-close');
        if (btn) btn.addEventListener('click', function () {
            clearTimeout(timer);
            dismissToast(toast);
        });
    });
}

function dismissToast(toast) {
    toast.classList.add('toast-hide');
    toast.addEventListener('animationend', function () { toast.remove(); });
}

// ============================================================
// LOCK SYSTEM
// — Reads data-lock-id and data-lock-timeout from <body>
// — Only active on the edit employee page
// ============================================================
var _lastActivity  = Date.now();
var _lockId        = 0;
var _lockTimeout   = 600000; // 10 min ms
var _heartbeatInterval = null;
var _saving        = false;  // true once the user submits the edit form

function initLockSystem() {
    var body = document.body;
    _lockId      = parseInt(body.dataset.lockId,      10) || 0;
    _lockTimeout = parseInt(body.dataset.lockTimeout, 10) * 1000 || 600000;
    if (!_lockId) return;

    // Track activity
    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(function (ev) {
        document.addEventListener(ev, function () { _lastActivity = Date.now(); }, { passive: true });
    });

    // Heartbeat every 60 s
    _heartbeatInterval = setInterval(function () {
        var inactiveMs = Date.now() - _lastActivity;

        if (inactiveMs < _lockTimeout) {
            // Still active — refresh lock
            sendLockAction('heartbeat', _lockId);
            hideInactivityWarning();
        } else {
            // Inactive — warn only, don't heartbeat (lock will expire server-side)
            showInactivityWarning(inactiveMs);
        }
    }, 60000);

    // When the user saves, the save POST clears the lock server-side. Mark that
    // we're saving so the unload handler below does NOT also fire a release
    // beacon — that beacon used to race the save and intermittently fail it
    // with "edit session expired".
    var lockForm = document.getElementById('edit-form');
    if (lockForm) {
        lockForm.addEventListener('submit', function () { _saving = true; });
    }

    // Release lock on tab close / navigation away (but not when saving).
    window.addEventListener('beforeunload', function () {
        if (_saving) return;
        sendLockRelease(_lockId);
    });

    // Also release when page becomes hidden (mobile tab switch)
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            sendLockAction('heartbeat', _lockId); // keep alive on hide
        }
    });
}

// Base path of the app (e.g. "/ZD_Birthdays"), injected by nav.php.
// Falls back to "" so absolute paths still work at the web root.
var ADMIN_BASE = (typeof window.APP_BASE !== 'undefined') ? window.APP_BASE : '';

function sendLockAction(action, id) {
    navigator.sendBeacon(
        ADMIN_BASE + '/api/lock.php',
        JSON.stringify({ action: action, id: id })
    );
}

function sendLockRelease(id) {
    // sendBeacon is fire-and-forget — ideal for beforeunload
    navigator.sendBeacon(
        ADMIN_BASE + '/api/lock.php',
        JSON.stringify({ action: 'release', id: id })
    );
}

function showInactivityWarning(inactiveMs) {
    var warn    = document.getElementById('inactivity-warning');
    var secsEl  = document.getElementById('inactive-secs');
    var relEl   = document.getElementById('release-secs');
    if (!warn) return;

    warn.classList.remove('hidden');
    if (secsEl) secsEl.textContent = Math.floor(inactiveMs / 1000);
    if (relEl) {
        var remaining = Math.ceil((_lockTimeout - inactiveMs) / 1000);
        relEl.textContent = Math.max(0, remaining);
    }
}

function hideInactivityWarning() {
    var warn = document.getElementById('inactivity-warning');
    if (warn) warn.classList.add('hidden');
}

// ============================================================
// IMAGE PREVIEW (add/edit employee)
// ============================================================
function initImagePreview() { /* triggered by inline onchange */ }

window.previewImages = function (input) {
    var container = document.getElementById('image-previews');
    if (!container) return;
    container.innerHTML = '';

    var files = Array.from(input.files).slice(0, 1);
    files.forEach(function (file) {
        var reader = new FileReader();
        reader.onload = function (e) {
            var wrap  = document.createElement('div');
            wrap.className = 'preview-wrap';
            var img   = document.createElement('img');
            img.src   = e.target.result;
            img.alt   = 'Preview';
            wrap.appendChild(img);
            container.appendChild(wrap);
        };
        reader.readAsDataURL(file);
    });
};

// ============================================================
// CONFIRM MODAL — replaces the native confirm() popup
// Any <form class="js-confirm" data-confirm="message"> is intercepted
// on submit and only sent once the user confirms in the modal.
// ============================================================
window.showConfirm = function (message, onConfirm, title) {
    var modal = document.getElementById('confirm-modal');
    if (!modal) { if (window.confirm(message)) onConfirm(); return; } // fallback

    document.getElementById('confirm-message').textContent = message;
    document.getElementById('confirm-title').textContent   = title || 'Please confirm';

    var ok     = document.getElementById('confirm-ok');
    var cancel = document.getElementById('confirm-cancel');

    function cleanup() {
        modal.classList.add('hidden');
        ok.removeEventListener('click', onOk);
        cancel.removeEventListener('click', cleanup);
        modal.removeEventListener('click', onBackdrop);
        document.removeEventListener('keydown', onKey);
    }
    function onOk()       { cleanup(); onConfirm(); }
    function onBackdrop(e){ if (e.target === modal) cleanup(); }
    function onKey(e)     { if (e.key === 'Escape') cleanup(); }

    ok.addEventListener('click', onOk);
    cancel.addEventListener('click', cleanup);
    modal.addEventListener('click', onBackdrop);
    document.addEventListener('keydown', onKey);

    modal.classList.remove('hidden');
};

// Intercept any js-confirm form (works for HTMX-swapped content too)
document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.classList || !form.classList.contains('js-confirm')) return;
    if (form.dataset.confirmed === '1') return; // already confirmed — let it submit
    e.preventDefault();
    showConfirm(form.getAttribute('data-confirm') || 'Are you sure?', function () {
        form.dataset.confirmed = '1';
        form.submit();
    });
});

// ============================================================
// BRANCH FILTER (shared across add/edit — called inline)
// ============================================================
// filterBranches() is defined inline in add.php and edit.php
// because it needs access to the PHP-injected branchData variable.
