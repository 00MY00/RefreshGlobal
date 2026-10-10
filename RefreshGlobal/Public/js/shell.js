/*
 * RefreshGlobal — entry points in Refresh's interface, on every page (loaded through FreeScout's "javascripts" filter,
 * like Refresh's own scripts). Settings come from <meta name="refreshglobal"> written by the module (layout.head).
 *
 * - Phone (Refresh's tab bar, Modules/Refresh/Public/js/mobile.js:127-140): adds an "All mailboxes" tab next to
 *   Refresh's "Tickets" tab.
 * - Option "replace Refresh's Tickets entry": Refresh's "Tickets" rail link (desktop) and tab (phone) are HIDDEN (not
 *   removed: Refresh's scripts still read their address) and "All mailboxes" takes their place.
 * - Confirmation of the module's destructive forms (data-rg-confirm), with or without Refresh.
 * - Phone: deletion in Refresh's "Ticket actions" sheet for tickets in the trash ("Delete Forever").
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

    // Phone, ticket page: Refresh's "Ticket actions" sheet (Modules/Refresh/Public/js/mobile.js:814-846) offers
    // "Delete" only through its toolbar copy of FreeScout's .conv-delete (:838). A ticket in the trash has FreeScout's
    // .conv-delete-forever ("Delete Forever") instead, so the sheet had no deletion at all: it is added, with
    // Refresh's item markup (.rf-m-opt, mobile.js:93-101), and it clicks FreeScout's own button (FreeScout's
    // confirmation and rights). Nothing is added when the sheet already has a deletion or the user may not delete.
    var completeActionsSheet = function (sheet) {
        var list = sheet.find('.rf-m-sheet-list');
        if (!list.length || list.find('.rf-i-fd-trash, .rg-m-delete').length) {
            return;
        }
        var natives = $('.conv-delete-forever, .conv-delete').not('#conversations-bulk-actions *');
        if (!natives.length) {
            return;
        }
        var label = '';
        natives.each(function () {
            label = label || $.trim($(this).text()).replace(/\s+/g, ' ') || $.trim(this.getAttribute('title') || '');
        });
        var item = $('<button type="button" class="rf-m-opt rg-m-delete"></button>')
            .append('<i class="rf-i rf-i-fd-trash"></i>')
            .append($('<span class="rf-m-opt-label"></span>').text(label || 'Delete'));
        item.on('click', function () {
            // same as Refresh's sheet: close it, then the action
            $('.rf-m-sheet').remove();
            $('body').removeClass('rf-m-overlay rf-m-drawer rf-m-pop-open');
            natives.first().trigger('click');
        });
        // before "Follow" / "Forward" when present, like Refresh's own "Delete"
        var after = list.find('.rf-m-opt').has('.rf-i-fd-ban, .rf-i-fd-merge').last();
        if (after.length) {
            item.insertAfter(after);
        } else {
            list.append(item);
        }
    };
    if (window.MutationObserver && window.matchMedia && window.matchMedia('(max-width: 767px)').matches) {
        $(function () {
            new MutationObserver(function (records) {
                records.forEach(function (r) {
                    $(r.addedNodes).filter('.rf-m-sheet-actions').each(function () {
                        completeActionsSheet($(this));
                    });
                });
            }).observe(document.body, { childList: true });
        });
    }

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
