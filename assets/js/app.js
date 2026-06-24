// ============================================================
// ZD Birthdays — Public App JS
// PHP injects: APP_BASE, MIDNIGHT_TS, CAPTIONS, BANNER_STATE, WISH_WINDOW
// ============================================================

// Base path of the app (e.g. "/ZD_Birthdays"). Falls back to "" so absolute
// API URLs still work if the app is served from the web root.
var API_BASE = (typeof APP_BASE !== 'undefined') ? APP_BASE : '';

document.addEventListener('DOMContentLoaded', function () {
    if (BANNER_STATE === 'birthday') {
        launchConfetti();
    }
    if (BANNER_STATE === 'countdown') {
        startCountdown();
        if (CAPTIONS && CAPTIONS.length > 1) rotateCaptions();
    }
    initWishForms();
    initWordCounts();
    initWishCarousels();
});

// ============================================================
// CONFETTI
// ============================================================
function launchConfetti() {
    if (typeof confetti === 'undefined') return;
    const colors = ['#F36D24', '#FF8F4D', '#FFFFFF', '#FA0117', '#FFE3D1', '#1A4BB5'];
    const end = Date.now() + 5500;

    (function frame() {
        confetti({
            particleCount: 3,
            angle: 60,
            spread: 55,
            origin: { x: 0, y: 0.5 },
            colors,
            zIndex: 998,
        });
        confetti({
            particleCount: 3,
            angle: 120,
            spread: 55,
            origin: { x: 1, y: 0.5 },
            colors,
            zIndex: 998,
        });
        if (Date.now() < end) requestAnimationFrame(frame);
    })();
}

// ============================================================
// COUNTDOWN TIMER
// ============================================================
function startCountdown() {
    var hEl = document.getElementById('ct-hours');
    var mEl = document.getElementById('ct-mins');
    var sEl = document.getElementById('ct-secs');
    if (!hEl) return;

    function tick() {
        var diff = Math.max(0, MIDNIGHT_TS - Date.now());
        var h = Math.floor(diff / 3600000);
        var m = Math.floor((diff % 3600000) / 60000);
        var s = Math.floor((diff % 60000) / 1000);

        setTimeUnit(hEl, pad2(h));
        setTimeUnit(mEl, pad2(m));
        setTimeUnit(sEl, pad2(s));

        if (diff <= 0) {
            clearInterval(timer);
            setTimeout(function () { window.location.reload(); }, 2500);
        }
    }

    tick();
    var timer = setInterval(tick, 1000);
}

function setTimeUnit(el, val) {
    if (el.textContent !== val) {
        el.textContent = val;
        el.style.animation = 'none';
        void el.offsetWidth;
        el.style.animation = 'countdownTick .3s ease both';
    }
}

function pad2(n) { return String(n).padStart(2, '0'); }

// ============================================================
// CAPTION ROTATION
// ============================================================
function rotateCaptions() {
    var el = document.getElementById('countdown-caption');
    if (!el || !CAPTIONS.length) return;
    var idx = 0;

    setInterval(function () {
        el.style.opacity = '0';
        setTimeout(function () {
            idx = (idx + 1) % CAPTIONS.length;
            el.textContent = CAPTIONS[idx];
            el.style.opacity = '1';
        }, 400);
    }, 6000);
}

// ============================================================
// WORD COUNT
// ============================================================
function initWordCounts() {
    document.querySelectorAll('.wish-form').forEach(function (form) {
        var ta   = form.querySelector('textarea[name="message"]');
        var ctr  = form.querySelector('.word-count');
        if (!ta || !ctr) return;
        ta.addEventListener('input', function () {
            var len = ta.value.length;
            ctr.textContent = len + ' / 500 characters';
            ctr.classList.toggle('near-limit', len > 450);
        });
    });
}

// ============================================================
// WISHES CAROUSEL — single line, endless loop
// Fits in one line   -> centered, static
// Overflows the line -> seamless infinite marquee (right->left) + < > arrows
// ============================================================
function initWishCarousels() {
    document.querySelectorAll('.wishes-carousel').forEach(setupCarousel);
}

function setupCarousel(carousel) {
    var viewport = carousel.querySelector('.wishes-viewport');
    var track    = carousel.querySelector('.wishes-track');
    var prev     = carousel.querySelector('.wish-arrow--prev');
    var next     = carousel.querySelector('.wish-arrow--next');
    if (!viewport || !track) return;

    var raf       = null;
    var offset    = 0;       // current px the track is shifted left
    var loopWidth = 0;       // width of one full set of real cards
    var speed     = 0.4;     // px per frame (~24px/s) — slow drift
    var paused    = false;   // paused on hover
    var animating = false;   // mid arrow-step animation

    function realCards() { return track.querySelectorAll('.wish-card:not(.clone)'); }
    function clearClones() {
        track.querySelectorAll('.wish-card.clone').forEach(function (c) { c.remove(); });
    }

    function wrap(x) {
        if (loopWidth <= 0) return x;
        return ((x % loopWidth) + loopWidth) % loopWidth;
    }
    function render() { track.style.transform = 'translateX(' + (-offset) + 'px)'; }

    // Natural width of one line of real cards (immune to justify-content)
    function naturalWidth() {
        var cards = realCards();
        if (!cards.length) return 0;
        var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
        var total = 0;
        cards.forEach(function (c) { total += c.offsetWidth; });
        return total + gap * (cards.length - 1);
    }

    // Duplicate the real cards (twice) so the loop has content both ways
    function buildClones() {
        var cards = Array.prototype.slice.call(realCards());
        for (var copy = 0; copy < 2; copy++) {
            cards.forEach(function (card) {
                var clone = card.cloneNode(true);
                clone.classList.add('clone');
                clone.setAttribute('aria-hidden', 'true');
                clone.removeAttribute('id');
                clone.querySelectorAll('[id]').forEach(function (el) { el.removeAttribute('id'); });
                clone.querySelectorAll('.wish-actions, script').forEach(function (el) { el.remove(); });
                track.appendChild(clone);
            });
        }
        var all = track.querySelectorAll('.wish-card');
        loopWidth = all[cards.length].offsetLeft - all[0].offsetLeft;
    }

    function frame() {
        if (!paused && !animating) { offset = wrap(offset + speed); render(); }
        raf = requestAnimationFrame(frame);
    }
    function startLoop() { stopLoop(); offset = 0; render(); raf = requestAnimationFrame(frame); }
    function stopLoop()  { if (raf) { cancelAnimationFrame(raf); raf = null; } }

    // Arrow navigation — animates one viewport-ish step, then re-wraps seamlessly
    function step(dir) {
        if (animating || loopWidth <= 0) return;
        animating = true;
        var amount = Math.min(loopWidth, Math.max(260, viewport.clientWidth * 0.8));
        // Going back from near the start: jump forward one cycle first (seamless)
        if (dir < 0) { offset = wrap(offset) + loopWidth; render(); void track.offsetWidth; }
        offset += dir * amount;
        track.style.transition = 'transform .45s ease';
        render();
        window.setTimeout(function () {
            track.style.transition = '';
            offset = wrap(offset);
            render();
            animating = false;
        }, 470);
    }

    function enable() {
        carousel.classList.add('is-carousel'); // justify-start before measuring
        buildClones();
        startLoop();
    }
    function disable() {
        carousel.classList.remove('is-carousel');
        stopLoop();
        clearClones();
        track.style.transform = '';
        track.style.transition = '';
        offset = 0;
    }

    function update() {
        // Reset to a clean state to measure the natural single-line width
        stopLoop();
        clearClones();
        track.style.transform = '';
        track.style.transition = '';
        carousel.classList.remove('is-carousel');

        var overflow = naturalWidth() - viewport.clientWidth > 8;
        if (overflow && realCards().length) enable();
        else disable();
    }

    if (prev) prev.addEventListener('click', function () { step(-1); });
    if (next) next.addEventListener('click', function () { step(1); });

    carousel.addEventListener('mouseenter', function () { paused = true; });
    carousel.addEventListener('mouseleave', function () { paused = false; });

    carousel._updateCarousel = update;
    window.addEventListener('resize', debounce(update, 200));
    update();
}

function debounce(fn, ms) {
    var t;
    return function () { clearTimeout(t); t = setTimeout(fn, ms); };
}

// ============================================================
// WISH FORM — SUBMIT
// ============================================================
function initWishForms() {
    document.querySelectorAll('.wish-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            submitWish(form);
        });
    });
}

function submitWish(form) {
    var empId = form.dataset.employeeId;
    var errEl = document.getElementById('wish-err-' + empId);
    var btn   = form.querySelector('.btn-wish');

    var origBtnHTML = btn.innerHTML;
    btn.disabled    = true;
    btn.textContent = 'Sending…';
    if (errEl) errEl.classList.add('hidden');

    var data = new FormData(form);
    data.append('employee_id', empId);

    fetch(API_BASE + '/api/wishes.php', { method: 'POST', body: data })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success) {
                showWishError(errEl, res.error);
                btn.disabled  = false;
                btn.innerHTML = origBtnHTML;
                return;
            }

            // Remove "no wishes" placeholder
            var noWish = document.getElementById('no-wishes-' + empId);
            if (noWish) noWish.remove();

            // Inject new wish card
            var list = document.getElementById('wishes-list-' + empId);
            if (list) {
                list.insertAdjacentHTML('beforeend', buildWishCard(
                    res.wish_id,
                    res.author_name,
                    res.message,
                    'just now',
                    true,
                    res.edit_window
                ));
                startWishCountdown(res.wish_id, res.edit_window);

                // Re-evaluate carousel now that a card was added
                var carousel = list.closest('.wishes-carousel');
                if (carousel && carousel._updateCarousel) carousel._updateCarousel();
            }

            // Replace form with confirmation
            var wrap = document.getElementById('wish-form-' + empId);
            if (wrap) {
                wrap.innerHTML = '<p class="wish-submitted"><svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-circle" viewBox="0 0 16 16"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="m10.97 4.97-.02.022-3.473 4.425-2.093-2.094a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05"/></svg> Your birthday wish has been sent!</p>';
            }
        })
        .catch(function () {
            showWishError(errEl, 'Something went wrong. Please try again.');
            btn.disabled  = false;
            btn.innerHTML = origBtnHTML;
        });
}

// ============================================================
// WISH CARD BUILDER
// ============================================================
function buildWishCard(id, author, message, time, isOwn, editWindow) {
    var actions = '';
    if (isOwn && editWindow > 0) {
        actions = '<div class="wish-actions" id="wish-act-' + id + '">' +
            '<button class="wish-btn edit-btn" onclick="openWishEdit(' + id + ')">Edit</button>' +
            '<button class="wish-btn delete-btn" onclick="deleteWish(' + id + ')">Delete</button>' +
            '<span class="wish-countdown" id="wc-' + id + '">(' + editWindow + 's)</span>' +
            '</div>';
    }

    return '<div class="wish-card" id="wish-card-' + id + '">' +
        '<div class="wish-bubble">' +
            '<span class="wish-frame" aria-hidden="true"></span>' +
            '<div class="wish-message" id="wish-msg-' + id + '">' + message + '</div>' +
            '<div class="wish-attribution">' +
                '<span class="wish-author">' + escHtml(author) + '</span>' +
            '</div>' +
        '</div>' +
        actions +
        '</div>';
}

// ============================================================
// WISH EDIT / DELETE
// ============================================================
window.openWishEdit = function (id) {
    var msgEl = document.getElementById('wish-msg-' + id);
    if (!msgEl) return;
    if (document.getElementById('wish-edit-' + id)) return; // already open

    var raw = msgEl.innerHTML
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<[^>]+>/g, '');

    var html = '<div class="wish-edit-area" id="wish-edit-' + id + '">' +
        '<textarea rows="3">' + escHtml(raw.trim()) + '</textarea>' +
        '<div class="wish-edit-actions">' +
            '<button class="btn btn-sm btn-outline" onclick="cancelWishEdit(' + id + ')">Cancel</button>' +
            '<button class="btn btn-sm btn-primary" onclick="saveWishEdit(' + id + ')">Save</button>' +
        '</div></div>';

    msgEl.insertAdjacentHTML('afterend', html);
    msgEl.style.display = 'none';
    document.querySelector('#wish-edit-' + id + ' textarea').focus();
};

window.cancelWishEdit = function (id) {
    var area  = document.getElementById('wish-edit-' + id);
    var msgEl = document.getElementById('wish-msg-' + id);
    if (area)  area.remove();
    if (msgEl) msgEl.style.display = '';
};

window.saveWishEdit = function (id) {
    var area    = document.getElementById('wish-edit-' + id);
    var textarea = area && area.querySelector('textarea');
    var message  = textarea ? textarea.value.trim() : '';
    if (!message) return;

    var data = new FormData();
    data.append('message', message);

    fetch(API_BASE + '/api/wishes.php?action=edit&id=' + id, { method: 'POST', body: data })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success) { alert(res.error || 'Edit failed.'); return; }
            var msgEl = document.getElementById('wish-msg-' + id);
            if (msgEl) { msgEl.innerHTML = res.message; msgEl.style.display = ''; }
            if (area) area.remove();
        })
        .catch(function () { alert('Could not save changes.'); });
};

window.deleteWish = function (id) {
    showConfirm('Delete your birthday wish?', function () {
        var data = new FormData();
        fetch(API_BASE + '/api/wishes.php?action=delete&id=' + id, { method: 'POST', body: data })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) { alert(res.error || 'Delete failed.'); return; }
                var card = document.getElementById('wish-card-' + id);
                if (card) card.remove();
            });
    }, 'Delete wish');
};

// ============================================================
// CONFIRM MODAL — replaces the native confirm() popup
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
    function onOk()        { cleanup(); onConfirm(); }
    function onBackdrop(e) { if (e.target === modal) cleanup(); }
    function onKey(e)      { if (e.key === 'Escape') cleanup(); }

    ok.addEventListener('click', onOk);
    cancel.addEventListener('click', cleanup);
    modal.addEventListener('click', onBackdrop);
    document.addEventListener('keydown', onKey);

    modal.classList.remove('hidden');
};

// ============================================================
// WISH EDIT/DELETE COUNTDOWN TIMER
// ============================================================
window.startWishCountdown = function (id, seconds) {
    var el = document.getElementById('wc-' + id);
    if (!el || seconds <= 0) return;
    var remaining = seconds;

    var t = setInterval(function () {
        remaining--;
        if (remaining <= 0) {
            clearInterval(t);
            var act = document.getElementById('wish-act-' + id);
            if (act) act.remove();
            return;
        }
        if (el) el.textContent = '(' + remaining + 's)';
    }, 1000);
};

// ============================================================
// UTILS
// ============================================================
function showWishError(el, msg) {
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('hidden');
}

function escHtml(str) {
    var d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
