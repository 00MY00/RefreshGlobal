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

        // Confirmation before deleting a saved view
        // Language switch: saved as soon as a language is chosen
        $(document).on('change', '.rg-language-select', function () {
            $(this).closest('form').trigger('submit');
        });

        $(document).on('submit', 'form.rg-confirm', function (e) {
            var msg = $(this).attr('data-confirm');
            if (msg && !window.confirm(msg)) {
                e.preventDefault();
            }
        });
    });
})(window.jQuery);
