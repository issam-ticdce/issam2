// Menu mobile, fenêtre de contact, galerie d'images, filtres automatiques.
(function () {
    var toggle = document.querySelector('[data-menu-toggle]');
    var nav = document.getElementById('site-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    var dialog = document.getElementById('inquiry-dialog');
    function openInquiry(type) {
        if (!dialog) return;
        dialog.querySelector('[data-inquiry-type]').value = type;
        var title = dialog.querySelector('#inquiry-title');
        title.textContent = title.dataset['title' + type.charAt(0).toUpperCase() + type.slice(1)] || title.textContent;
        dialog.querySelectorAll('[data-only]').forEach(function (el) {
            el.hidden = el.dataset.only !== type;
        });
        dialog.showModal();
        var first = dialog.querySelector('input:not([type=hidden])');
        if (first) first.focus();
    }
    document.querySelectorAll('[data-inquiry]').forEach(function (btn) {
        btn.addEventListener('click', function () { openInquiry(btn.dataset.inquiry); });
    });
    document.querySelectorAll('[data-dialog-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { btn.closest('dialog').close(); });
    });
    if (dialog) {
        dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
    }
    var reopen = document.querySelector('[data-open-inquiry]');
    if (reopen) openInquiry(reopen.dataset.openInquiry);

    document.querySelectorAll('[data-gallery]').forEach(function (gallery) {
        var main = gallery.querySelector('[data-gallery-main]');
        gallery.querySelectorAll('[data-gallery-thumb]').forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                main.src = thumb.dataset.galleryThumb;
                gallery.querySelectorAll('[data-gallery-thumb]').forEach(function (t) { t.classList.remove('active'); });
                thumb.classList.add('active');
            });
        });
    });

    document.querySelectorAll('[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () { select.form.submit(); });
    });
})();
