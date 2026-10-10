/*
 * RefreshGlobal — "All mailboxes" page. Loaded only on the module's pages (external file: FreeScout's CSP).
 * Refresh's own script (filters panel, select2 on .rf-multi, card/table layout, bulk bar) works on the page as is.
 */
(function ($) {
    'use strict';
    if (!$) {
        return;
    }
    $(function () {
        var page = $('body').attr('data-rg-page');
        if (page !== 'tickets') {
            return;
        }

        // FreeScout's column sorting reloads a FOLDER by ajax (main.js convListSortingInit → loadConversations):
        // here sorting is a server parameter of the page, so the headers become plain links.
        // Base address = current filters (also when they come from a saved view), without the page number.
        var state = $('#rg-state');
        var sortMap = { number: 'number', subject: 'subject', date: 'updated' };
        $('.table-conversations .conv-col-sort').off('click').each(function () {
            var el = $(this), key = sortMap[el.attr('data-sort-by')];
            if (!key || !window.URL) {
                return;
            }
            el.css('cursor', 'pointer').on('click', function (e) {
                e.preventDefault();
                var url = new URL(state.attr('data-base') || window.location.href, window.location.href);
                var same = state.attr('data-sort') === key;
                var order = same && state.attr('data-order') !== 'asc' ? 'asc' : 'desc';
                url.searchParams.delete('reset');
                url.searchParams.set('sort', key);
                url.searchParams.set('order', order);
                window.location.href = url.toString();
            });
        });

        // Native "Assigned To" header filter: same folder reload by ajax; the page has its own "Assigned to" filter
        $('.table-conversations th.conv-owner').removeAttr('data-trigger').removeAttr('data-remote')
            .removeClass('fs-trigger-modal').find('.glyphicon-filter').remove();

        // Stars (native function, normally called by viewMailboxInit which is not used here: it would also bind
        // the folder pager)
        if (typeof window.starConversationInit === 'function') {
            window.starConversationInit();
        }

        // Multi-selects: Refresh's script already handles .rf-multi; standard look otherwise
        if ($.fn.select2) {
            $('.rg-multi').not('.select2-hidden-accessible').each(function () {
                $(this).select2({ placeholder: $(this).attr('data-placeholder') || '', width: '100%', allowClear: false });
            });
        }

        // Native skin: filters toggle and sort selects
        var nativeFilters = $('.rg-filters-native');
        if (nativeFilters.length) {
            var hasFilters = /[?&](mb|status|assignee|q)(%5B%5D|\[\])?=/.test(window.location.search);
            if (!hasFilters) {
                nativeFilters.addClass('rg-collapsed');
            }
            $('.rg-toggle-filters').on('click', function (e) {
                e.preventDefault();
                nativeFilters.toggleClass('rg-collapsed');
            });
            nativeFilters.on('change', '.rg-sort-select, .rg-order-select', function () {
                nativeFilters.find('input[name=sort]').val($('.rg-sort-select').val());
                nativeFilters.find('input[name=order]').val($('.rg-order-select').val());
            });
        }

        // Language switch: saved as soon as a language is chosen
        $(document).on('change', '.rg-language-select', function () {
            $(this).closest('form').trigger('submit');
        });

        // Confirmation before deleting a saved view
        $(document).on('submit', 'form.rg-confirm', function (e) {
            var msg = $(this).attr('data-confirm');
            if (msg && !window.confirm(msg)) {
                e.preventDefault();
            }
        });

        if (state.attr('data-keep-position') === '1') {
            keepPosition();
        }
    });

    /*
     * Setting "Keep my place in the list after a deletion" (on by default).
     * Leaving the list (opening a ticket, or the reload done by FreeScout after a bulk / swipe deletion) records the
     * order of the tickets shown and where the ticket used as landmark was on screen (the opened ticket, otherwise
     * the first visible one). Back on the list after a deletion (#rg-deleted=ID added by the module's redirect, or a
     * reload) the landmark, or the ticket that followed it when it was deleted, is put back at the same place: no
     * scrolling needed. The list scrolls inside .rf-list-scroll on a computer (Refresh) and in the window on a phone.
     */
    function keepPosition() {
        var KEY = 'rg-list-position';
        var store = {
            get: function () { try { return JSON.parse(window.sessionStorage.getItem(KEY) || 'null'); } catch (e) { return null; } },
            set: function (v) { try { window.sessionStorage.setItem(KEY, JSON.stringify(v)); } catch (e) { /* private mode */ } },
            clear: function () { try { window.sessionStorage.removeItem(KEY); } catch (e) { /* private mode */ } }
        };
        var scroller = function () {
            var s = document.querySelector('.rf-list-scroll');
            return s && s.scrollHeight > s.clientHeight + 5 ? s : null; // null = the window scrolls
        };
        var viewTop = function (sc) { return sc ? sc.getBoundingClientRect().top : 0; };
        var rows = function () { return Array.prototype.slice.call(document.querySelectorAll('tr.conv-row[data-conversation_id]')); };
        var idOf = function (row) { return row.getAttribute('data-conversation_id'); };
        var here = window.location.pathname + window.location.search;
        var clickedAt = 0;

        var save = function (anchor) {
            var sc = scroller(), top = viewTop(sc), list = rows();
            if (!anchor) {
                anchor = list.filter(function (r) { return r.getBoundingClientRect().bottom > top + 1; })[0] || null;
            }
            store.set({
                path: window.location.pathname,
                key: here,
                ids: list.map(idOf),
                anchor: anchor ? idOf(anchor) : '',
                offset: anchor ? anchor.getBoundingClientRect().top - top : 0,
                scroll: sc ? sc.scrollTop : window.pageYOffset,
                at: Date.now()
            });
        };
        // opening a ticket: that ticket is the landmark (it is the one that may be deleted)
        document.addEventListener('click', function (e) {
            var row = e.target && e.target.closest ? e.target.closest('tr.conv-row[data-conversation_id]') : null;
            if (row && !(e.target.closest('input, label, .conv-checkbox, .conv-star'))) {
                save(row);
                clickedAt = Date.now();
            }
        }, true);
        // any other way of leaving (reload after a bulk / swipe deletion…): first visible ticket
        window.addEventListener('pagehide', function () {
            if (Date.now() - clickedAt > 3000) {
                save(null);
            }
        });

        var saved = store.get();
        var deleted = /(?:^|[#&])rg-deleted=(\d+)/.exec(window.location.hash || '');
        var nav = window.performance && performance.getEntriesByType ? (performance.getEntriesByType('navigation')[0] || {}).type : '';
        if (!saved || saved.path !== window.location.pathname || Date.now() - saved.at > 30 * 60 * 1000) {
            return;
        }
        // after the module's redirect (#rg-deleted), or the same list reloaded / reached with Back
        if (!deleted && !((nav === 'reload' || nav === 'back_forward') && saved.key === here)) {
            return;
        }
        if (deleted && window.history && history.replaceState) {
            history.replaceState(null, '', here); // the marker is not kept in the address
        }

        var restore = function () {
            var present = {};
            rows().forEach(function (r) { present[idOf(r)] = r; });
            var target = present[saved.anchor] || null;
            var i = saved.ids.indexOf(saved.anchor);
            // landmark deleted: the ticket that followed it, otherwise the one before
            for (var j = i + 1; !target && i >= 0 && j < saved.ids.length; j++) { target = present[saved.ids[j]] || null; }
            for (var k = i - 1; !target && k >= 0; k--) { target = present[saved.ids[k]] || null; }
            var sc = scroller();
            if (target) {
                var delta = (target.getBoundingClientRect().top - viewTop(sc)) - saved.offset;
                if (sc) { sc.scrollTop += delta; } else { window.scrollBy(0, delta); }
            } else if (sc) {
                sc.scrollTop = saved.scroll;
            } else {
                window.scrollTo(0, saved.scroll);
            }
        };
        // after Refresh's scripts have laid the list out (cards, phone version)
        setTimeout(restore, 150);
        setTimeout(restore, 700);
    }
})(window.jQuery);
