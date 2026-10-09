/*
 * RefreshGlobal — entry points in Refresh's interface, on every page (loaded through FreeScout's "javascripts" filter,
 * like Refresh's own scripts). Settings come from <meta name="refreshglobal"> written by the module (layout.head).
 *
 * - Phone (Refresh's tab bar, Modules/Refresh/Public/js/mobile.js:127-140): adds an "All mailboxes" tab next to
 *   Refresh's "Tickets" tab.
 * - Option "replace Refresh's Tickets entry": Refresh's "Tickets" rail link (desktop) and tab (phone) are HIDDEN (not
 *   removed: Refresh's scripts still read their address) and "All mailboxes" takes their place.
 * - Confirmation of the module's destructive forms (data-rg-confirm), with or without Refresh.
 * Nothing of Refresh is modified; without Refresh (no .rf-rail / .rf-m-tabs) the Refresh parts do nothing.
 */
(function ($) {
    'use strict';
    if (!$) {
        return;
    }
    // Forms of the module that destroy data ("Empty the trash"): confirmation first, on any page (also FreeScout's
    // settings page, where the module's page script is not loaded)
    $(document).on('submit', 'form[data-rg-confirm]', function (e) {
        var msg = this.getAttribute('data-rg-confirm');
        if (msg && !window.confirm(msg)) {
            e.preventDefault();
        }
    });

    var config = function () {
        try {
            return JSON.parse(document.querySelector('meta[name="refreshglobal"]').getAttribute('content'));
        } catch (e) {
            return null;
        }
    };
    var icon = function (url) {
        var i = $('<i class="rf-i"></i>');
        // CSSOM (allowed by FreeScout's CSP), same technique as Refresh's rail for module icons
        i[0].style.setProperty('--rf-i', 'url("' + String(url).replace(/"/g, '%22') + '")');
        return i;
    };

    $(function () {
        var c = config();
        if (!c || !c.url) {
            return;
        }

        // Desktop: Refresh's left bar (built before this handler runs)
        var links = $('.rf-rail .rf-rail-links');
        if (links.length && c.replace) {
            var ours = links.find('.rf-rail-link').filter(function () { return this.getAttribute('href') === c.url; }).first();
            var theirs = links.find('.rf-rail-link').has('.rf-i-fd-all-tickets').first();
            if (ours.length && theirs.length) {
                ours.insertAfter(theirs);
                theirs.hide().addClass('rg-replaced');
            }
        }

        // Phone: Refresh's tab bar (built by mobile.js on the same "ready" event, maybe a little later)
        var tries = 0;
        var addTab = function () {
            var tabs = $('.rf-m-tabs');
            if (!tabs.length) {
                if (++tries < 30) { setTimeout(addTab, 100); }
                return;
            }
            if (tabs.find('.rg-m-tab').length) {
                return;
            }
            var tab = $('<a class="rf-m-tab rg-m-tab"></a>').attr({ href: c.url, 'aria-label': c.label, title: c.label })
                .toggleClass('active', !!c.active).append(icon(c.icon));
            var theirs = tabs.find('.rf-m-tab-tickets').first();
            if (theirs.length) {
                tab.insertAfter(theirs);
                if (c.replace) { theirs.hide().addClass('rg-replaced'); }
            } else {
                tabs.prepend(tab);
            }
            if (c.active) {
                tabs.find('.rf-m-tab').not(tab).removeClass('active');
            }
        };
        if (window.matchMedia && window.matchMedia('(max-width: 767px)').matches) {
            addTab();
        }
    });
})(window.jQuery);
