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
        if (page === 'views') {
            // management page of the saved views (Resources/views/views.blade.php)
            viewForms();
            $(document).on('submit', 'form.rg-confirm', function (e) {
                var msg = $(this).attr('data-confirm');
                if (msg && !window.confirm(msg)) {
                    e.preventDefault();
                }
            });
            return;
        }
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
            var form = $(this).closest('form');
            // a submission started by script fires no "submit" event: loading indicator here
            if (!form.find('.rg-spinner').length) {
                $('<i class="glyphicon glyphicon-refresh glyphicon-spin rg-spinner" aria-hidden="true"></i>').insertAfter(form.find('.rg-language-label'));
            }
            form.attr('aria-busy', 'true');
            form[0].submit();
        });

        // Confirmation before deleting a saved view
        $(document).on('submit', 'form.rg-confirm', function (e) {
            var msg = $(this).attr('data-confirm');
            if (msg && !window.confirm(msg)) {
                e.preventDefault();
            }
        });

        viewForms();
        filtersPanel();
        newViewDialog();
        viewOrder();
        longPressGuard();

        if (state.attr('data-keep-position') === '1') {
            keepPosition();
        }

        $(document).on('click', '.rg-sync-btn', function () {
            syncNow($(this));
        });
    });

    /*
     * Phone: selecting tickets with a long-press. Refresh's cards (Modules/Refresh/Public/js/mobile.js:260-298) check the
     * ticket after 500 ms of pressing; other handlers can then toggle the same checkbox back:
     *  - Refresh itself, on the "click" sent when the finger is lifted, when it comes more than 800 ms after the
     *    selection (a deliberate press of more than ~1.3 s): handled as a tap in selection mode;
     *  - FreeScout's own long-press (taphold, 700 ms, public/js/main.js conversationsTableInit()), restarted by the mouse
     *    events some phones send after a touch;
     *  - any other click / tap the phone sends after a long-press.
     * Whatever the source: once a long-press has changed the checkbox of the pressed card, any other change of that
     * checkbox during the same press, or within 1.5 s after it (before the next touch), is undone, and the click that
     * follows the release is dropped before any other handler (capture phase). Short taps are left to Refresh (open the
     * ticket, or check / uncheck it in selection mode).
     * Diagnostic: ?rg_debug=touch shows on the page what the phone sends (?rg_debug=off to stop).
     */
    /*
     * Forms of the saved views (save, share, management page): the fields of a visibility are shown only when it is
     * chosen (data-rg-show-for), the list of users can be filtered by name or address, "may edit" only next to a chosen
     * user. Without this script every field stays visible and usable.
     */
    function viewForms() {
        var sync = function (fs) {
            var vis = fs.find('input[name="visibility"]:checked').val() || 'private';
            fs.find('[data-rg-show-for]').each(function () {
                $(this).toggle((' ' + $(this).attr('data-rg-show-for') + ' ').indexOf(' ' + vis + ' ') >= 0);
            });
            fs.find('.rg-user-item').each(function () {
                $(this).find('.rg-can-edit').toggle(!!$(this).find('input[name="users[]"]').prop('checked'));
            });
        };
        $('[data-rg-vis]').each(function () { sync($(this)); });
        $(document).on('change', '[data-rg-vis] input', function () { sync($(this).closest('[data-rg-vis]')); });
        $(document).on('input', '.rg-user-filter', function () {
            var q = $.trim($(this).val() || '').toLowerCase();
            $(this).siblings('.rg-user-list').find('.rg-user-item').each(function () {
                $(this).toggle(!q || ($(this).attr('data-label') || '').indexOf(q) >= 0);
            });
        });
    }

    /*
     * 1.22.0: "New view" dialog (Resources/views/partials/new_view_dialog.blade.php), opened by every .rg-new-view-open
     * ("+ New view" of "My views", "Save as a view" of the active filters bar, "Save as new" of an open view) and by the
     * address #rg-new-view (button of the management page). Escape, × or a click outside close it; the focus comes back
     * to the button. "Change the filters first" closes it and opens the filters panel.
     */
    function newViewDialog() {
        var dlg = $('#rg-new-view');
        if (!dlg.length) {
            return;
        }
        // at the root of the page: above Refresh's phone bars, whatever the containers of the content
        dlg.appendTo(document.body);
        var opener = null;
        var close = function () {
            if (dlg.prop('hidden')) {
                return;
            }
            dlg.prop('hidden', true);
            $('body').removeClass('rg-dialog-open');
            if (opener && opener.focus) { opener.focus(); }
            if (window.location.hash === '#rg-new-view' && window.history && window.history.replaceState) {
                window.history.replaceState(null, '', window.location.pathname + window.location.search);
            }
        };
        var open = function (from) {
            opener = from || document.activeElement;
            // phones: the views drawer of Refresh stays under the dialog; it is closed first when it is open
            if ($('body').hasClass('rf-m-drawer')) {
                $('body').removeClass('rf-m-overlay rf-m-drawer');
            }
            dlg.prop('hidden', false);
            $('body').addClass('rg-dialog-open');
            setTimeout(function () { dlg.find('#rg-view-name').trigger('focus').select(); }, 30);
        };
        $(document).on('click', '.rg-new-view-open', function (e) {
            e.preventDefault();
            open(this);
        });
        dlg.on('click', '[data-rg-dialog-close]', close);
        dlg.on('click', function (e) { if (e.target === dlg[0]) { close(); } });
        $(document).on('keydown', function (e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && !dlg.prop('hidden')) {
                close();
            }
            // focus kept inside the dialog (Tab / Shift+Tab)
            if (e.key === 'Tab' && !dlg.prop('hidden')) {
                var f = dlg.find('a[href], button, input, select, textarea, summary').filter(':visible').not(':disabled');
                if (!f.length) { return; }
                if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f.last().trigger('focus'); }
                else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f.first().trigger('focus'); }
            }
        });
        dlg.on('click', '.rg-new-view-edit', function () {
            close();
            // phone with Refresh: its own "Filter" button opens the filters sheet
            var m = $('.rf-m-filtbtn:visible').first();
            if (m.length) { m.trigger('click'); return; }
            var af = $('.rg-af-edit:visible').first();
            if (af.length) { af.trigger('click'); return; }
            var btn = $('.rf-toolbar .rf-toggle-filters, .rg-toggle-filters').filter(':visible').first();
            if (btn.length && ($('.rg-list-layout').hasClass('rf-filters-closed') || !btn.hasClass('rf-toggle-filters') || $('body').hasClass('rf-m'))) {
                btn.trigger('click');
            }
            setTimeout(function () { $('#rg-filters').find('input, select').filter(':visible').first().trigger('focus'); }, 250);
        });
        dlg.on('submit', 'form', function () {
            dlg.find('.rg-new-view-save').prop('disabled', true);
        });
        if (window.location.hash === '#rg-new-view') {
            open(null);
        }
    }

    /*
     * 1.22.0: "Reorder" of "My views" (Resources/views/partials/views_sections.blade.php). The user's own views become an
     * ordered list: drag them by the handle (mouse, pen or finger: pointer events, the list scrolls near its edges), or
     * use the arrows / the keyboard (arrow keys on the handle). Each change is saved at once
     * (POST refreshglobal.views.order, the server keeps only the user's own views); "Done" puts the panel back, in the
     * new order. A failed save puts the previous order back with a message.
     */
    function viewOrder() {
        $(document).on('click', '.rg-views-order', function (e) {
            e.preventDefault();
            var btn = $(this);
            var list = btn.closest('.rf-views-list, ul');
            if (!list.length || list.hasClass('rg-ordering')) {
                return;
            }
            start(btn, list);
        });

        var token = function () { return (document.querySelector('meta[name="csrf-token"]') || {}).content || ''; };
        var say = function (type, text) {
            if (typeof window.showFloatingAlert === 'function') { window.showFloatingAlert(type, text); } else if (type === 'error') { window.alert(text); }
        };

        function start(btn, list) {
            var t = function (k) { return btn.attr('data-t-' + k) || ''; };
            var items = list.children('[data-rg-view-id]');
            var box = $('<div class="rg-order" role="region"></div>').attr('aria-label', btn.text().trim());
            box.append($('<p class="rg-order-help"></p>').text(t('help')));
            var ol = $('<ol class="rg-order-list"></ol>');
            items.each(function () {
                var id = $(this).attr('data-rg-view-id'), name = $(this).attr('data-rg-view-name') || '';
                var li = $('<li class="rg-order-item"></li>').attr('data-id', id);
                li.append($('<span class="rg-order-handle" role="button" tabindex="0"></span>').attr('aria-label', t('handle').replace(':name', name))
                    .attr('title', t('handle').replace(':name', name)).text('⠿'));
                li.append($('<span class="rg-order-name"></span>').text(name));
                li.append($('<button type="button" class="rg-order-up"></button>').attr('aria-label', t('up') + ' : ' + name).attr('title', t('up')).text('↑'));
                li.append($('<button type="button" class="rg-order-down"></button>').attr('aria-label', t('down') + ' : ' + name).attr('title', t('down')).text('↓'));
                ol.append(li);
            });
            var done = $('<button type="button" class="btn btn-primary btn-sm rg-order-done"></button>').text(t('done'));
            var status = $('<span class="rg-order-status" aria-live="polite"></span>');
            box.append(ol, $('<div class="rg-order-foot"></div>').append(done, status));
            // the panel's own items stay in place, hidden (inline: Refresh's phone drawer forces its items visible);
            // the editable list goes at the top of "My views"
            list.addClass('rg-ordering').children().each(function () {
                this.style.setProperty('display', 'none', 'important');
                this.setAttribute('data-rg-hid', '1');
            });
            if (list.is('ul')) {
                box = $('<li class="rg-order-li"></li>').append(box);
            }
            list.prepend(box);
            ol.find('.rg-order-handle').first().trigger('focus');

            var saved = ids();
            var timer = null;
            function ids() { return ol.children().map(function () { return $(this).attr('data-id'); }).get(); }
            function refreshArrows() {
                var lis = ol.children();
                lis.find('.rg-order-up, .rg-order-down').prop('disabled', false);
                lis.first().find('.rg-order-up').prop('disabled', true);
                lis.last().find('.rg-order-down').prop('disabled', true);
            }
            function save() {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    var now = ids();
                    if (now.join(',') === saved.join(',')) { return; }
                    status.text('…');
                    $.ajax({ url: btn.attr('data-url'), method: 'POST', dataType: 'json', data: { _token: token(), ids: now } }).done(function (r) {
                        if (r && r.status === 'success') {
                            saved = now;
                            status.text(t('saved'));
                        } else { fail(r && r.message); }
                    }).fail(function (xhr) { fail(xhr.responseJSON && xhr.responseJSON.message); });
                }, 250);
            }
            function fail(message) {
                // back to the last order the server has
                $.each(saved, function (i, id) { ol.append(ol.children('[data-id="' + id + '"]')); });
                refreshArrows();
                status.text('');
                say('error', message || t('error'));
            }
            function moveBy(li, dir) {
                if (dir < 0 && li.prev().length) { li.insertBefore(li.prev()); }
                else if (dir > 0 && li.next().length) { li.insertAfter(li.next()); }
                else { return; }
                refreshArrows();
                save();
            }
            refreshArrows();

            ol.on('click', '.rg-order-up', function () { moveBy($(this).closest('li'), -1); $(this).closest('li').find('.rg-order-up:not(:disabled), .rg-order-handle').first().trigger('focus'); });
            ol.on('click', '.rg-order-down', function () { moveBy($(this).closest('li'), 1); $(this).closest('li').find('.rg-order-down:not(:disabled), .rg-order-handle').first().trigger('focus'); });
            ol.on('keydown', '.rg-order-handle', function (e) {
                if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    moveBy($(this).closest('li'), e.key === 'ArrowUp' ? -1 : 1);
                    $(this).trigger('focus');
                }
            });

            // drag and drop with pointer events: mouse, pen and touch alike
            var drag = null;
            var scroller = function (el) {
                for (var n = el.parentElement; n && n !== document.body; n = n.parentElement) {
                    var st = window.getComputedStyle(n);
                    if (/(auto|scroll)/.test(st.overflowY) && n.scrollHeight > n.clientHeight) { return n; }
                }
                return null;
            };
            ol.on('pointerdown', '.rg-order-handle', function (ev) {
                var e = ev.originalEvent || ev;
                if (e.button !== undefined && e.button !== 0) { return; }
                ev.preventDefault();
                var li = $(this).closest('li')[0];
                try { this.setPointerCapture(e.pointerId); } catch (x) { /* old browsers */ }
                drag = { li: li, handle: this, id: e.pointerId, startY: e.clientY, top: li.offsetTop, sc: scroller(li), y: e.clientY, raf: 0 };
                li.classList.add('rg-order-dragging');
                ol.addClass('rg-order-active');
                drag.raf = window.requestAnimationFrame(edgeScroll);
            });
            // moves and release followed on the document: moving items in the page may drop the pointer capture
            var onMove = function (ev) {
                var e = ev.originalEvent || ev;
                if (!drag || e.pointerId !== drag.id) { return; }
                ev.preventDefault();
                drag.y = e.clientY;
                place();
            };
            var stop = function (ev) {
                var e = ev && (ev.originalEvent || ev);
                if (!drag || (e && e.pointerId !== undefined && e.pointerId !== drag.id)) { return; }
                window.cancelAnimationFrame(drag.raf);
                drag.li.style.transform = '';
                drag.li.classList.remove('rg-order-dragging');
                ol.removeClass('rg-order-active');
                try { drag.handle.releasePointerCapture(drag.id); } catch (x) { /* already released */ }
                drag = null;
                $(document).off('pointermove.rgorder pointerup.rgorder pointercancel.rgorder');
                refreshArrows();
                save();
            };
            ol.on('pointerdown', '.rg-order-handle', function () {
                $(document).off('pointermove.rgorder pointerup.rgorder pointercancel.rgorder')
                    .on('pointermove.rgorder', onMove).on('pointerup.rgorder pointercancel.rgorder', stop);
            });
            function place() {
                var li = drag.li;
                // the dragged item is never taken out of the page (that would end the drag): its neighbours move
                // around it, until the pointer is between the middles of the items before and after it
                var prev = li.previousElementSibling, next = li.nextElementSibling, guard = 0;
                while (prev && guard++ < 200) {
                    var rp = prev.getBoundingClientRect();
                    if (drag.y >= rp.top + rp.height / 2) { break; }
                    ol[0].insertBefore(prev, li.nextSibling);
                    prev = li.previousElementSibling;
                }
                while (next && guard++ < 400) {
                    var rn = next.getBoundingClientRect();
                    if (drag.y <= rn.top + rn.height / 2) { break; }
                    ol[0].insertBefore(next, li);
                    next = li.nextElementSibling;
                }
                // follows the pointer between its new neighbours
                li.style.transform = 'translateY(' + ((drag.y - drag.startY) - (li.offsetTop - drag.top)) + 'px)';
            }
            function edgeScroll() {
                if (!drag) { return; }
                var sc = drag.sc;
                var r = sc ? sc.getBoundingClientRect() : { top: 0, bottom: window.innerHeight };
                var step = drag.y < r.top + 40 ? -8 : (drag.y > r.bottom - 40 ? 8 : 0);
                if (step) {
                    if (sc) { sc.scrollTop += step; } else { window.scrollBy(0, step); }
                    place();
                }
                drag.raf = window.requestAnimationFrame(edgeScroll);
            }

            done.on('click', function () {
                clearTimeout(timer);
                var go = function () {
                    // the panel's items in the new order (before "+ New view"), then the normal panel back
                    var tools = list.children('.rg-views-tools').first();
                    $.each(ids(), function (i, id) {
                        var item = list.children('[data-rg-view-id="' + id + '"]');
                        if (tools.length) { item.insertBefore(tools); } else { list.append(item); }
                    });
                    box.remove();
                    list.removeClass('rg-ordering').children('[data-rg-hid]').each(function () {
                        this.style.removeProperty('display');
                        this.removeAttribute('data-rg-hid');
                    });
                    btn.trigger('focus');
                };
                if (ids().join(',') !== saved.join(',')) {
                    // a change still to send: sent now, the panel comes back once it is saved
                    $.ajax({ url: btn.attr('data-url'), method: 'POST', dataType: 'json', data: { _token: token(), ids: ids() } })
                        .done(function (r) { if (r && r.status === 'success') { saved = ids(); go(); } else { fail(r && r.message); } })
                        .fail(function (xhr) { fail(xhr.responseJSON && xhr.responseJSON.message); });
                } else {
                    go();
                }
            });
        }
    }

    function longPressGuard() {
        if (!('ontouchstart' in window) || !document.addEventListener) {
            return;
        }
        var LOCK_MS = 1500;
        var press = null;   // finger down on a card: {row, first: checkbox state set by the long-press, or null}
        var lock = null;    // after the release: {row, state, until}
        var dropUntil = 0;
        var reverting = false;
        var log = touchDebug();
        var rowOf = function (target) {
            return target && target.closest ? target.closest('tr.conv-row') : null;
        };
        var idOf = function (row) {
            return row ? row.getAttribute('data-conversation_id') : '-';
        };
        var release = function (how) {
            if (press && press.first !== null) {
                lock = { row: press.row, state: press.first, until: Date.now() + LOCK_MS };
                dropUntil = lock.until;
            }
            log(how + ' #' + (press ? idOf(press.row) : '-') + (lock ? ' lock=' + lock.state : ''));
            press = null;
        };
        document.addEventListener('touchstart', function (e) {
            var row = rowOf(e.target);
            // a new touch: the selection of the previous long-press is final
            dropUntil = 0;
            lock = null;
            press = row && e.touches.length === 1 ? { row: row, first: null } : null;
            log('touchstart #' + idOf(row));
        }, { capture: true, passive: true });
        document.addEventListener('touchend', function () { release('touchend'); }, { capture: true, passive: true });
        document.addEventListener('touchcancel', function () { release('touchcancel'); }, { capture: true, passive: true });
        document.addEventListener('click', function (e) {
            var row = rowOf(e.target);
            if (dropUntil && Date.now() < dropUntil && row) {
                dropUntil = 0;
                e.preventDefault();
                e.stopImmediatePropagation();
                log('click #' + idOf(row) + ' DROPPED');
                return;
            }
            log('click #' + idOf(row) + (e.isTrusted ? '' : ' (script)'));
        }, true);
        var undo = function (cb, row, state, why) {
            if (cb.checked !== state) {
                reverting = true;
                cb.checked = state;
                $(cb).trigger('change');
                reverting = false;
                log('UNDONE ' + why + ' #' + idOf(row) + ' -> ' + state);
            }
            // the handler that toggled the checkbox may toggle the card's class right after
            setTimeout(function () { $(row).toggleClass('selected', cb.checked); }, 0);
        };
        // checkboxes are changed by jQuery (.prop + trigger("change")): a jQuery handler sees every change
        $(document).on('change', '.conv-checkbox', function () {
            if (reverting) {
                return;
            }
            var cb = this;
            var row = rowOf(cb);
            log('checkbox #' + idOf(row) + ' ' + (cb.checked ? 'checked' : 'unchecked') + (press ? ' (finger down)' : ''));
            if (press && press.row === row) {
                if (press.first === null) {
                    press.first = cb.checked; // the long-press selection
                } else {
                    undo(cb, row, press.first, 'second change during the press');
                }
                return;
            }
            if (lock && lock.row === row && Date.now() < lock.until) {
                undo(cb, row, lock.state, 'change after the long-press');
            }
        });
        if (log.on) {
            document.addEventListener('contextmenu', function (e) { log('contextmenu #' + idOf(rowOf(e.target)) + (e.defaultPrevented ? ' (prevented)' : '')); }, true);
            document.addEventListener('mousedown', function (e) { log('mousedown #' + idOf(rowOf(e.target))); }, true);
            document.addEventListener('mouseup', function (e) { log('mouseup #' + idOf(rowOf(e.target))); }, true);
            $(document).on('taphold', 'tr.conv-row', function () { log('taphold (FreeScout) #' + idOf(this)); });
        }
    }

    /*
     * ?rg_debug=touch: a box at the top of the page lists the touch events, clicks and checkbox changes (for a phone
     * where the selection misbehaves); kept for the tab (sessionStorage) until ?rg_debug=off. Returns log(text).
     */
    function touchDebug() {
        var KEY = 'rg-debug-touch';
        var m = /[?&]rg_debug=(touch|off)\b/.exec(window.location.search);
        try {
            if (m) {
                if (m[1] === 'off') { window.sessionStorage.removeItem(KEY); } else { window.sessionStorage.setItem(KEY, '1'); }
            }
        } catch (e) { /* private mode */ }
        var on = false;
        try { on = window.sessionStorage.getItem(KEY) === '1'; } catch (e) { on = !!(m && m[1] === 'touch'); }
        if (!on) {
            var none = function () {};
            none.on = false;
            return none;
        }
        var box = document.createElement('pre');
        box.className = 'rg-touch-debug';
        box.textContent = 'RefreshGlobal ' + (document.querySelector('meta[name="rg-version"]') || { content: '' }).content
            + ' — touch log (?rg_debug=off to stop)\n' + navigator.userAgent + '\n';
        document.body.appendChild(box);
        var t0 = Date.now();
        var log = function (text) {
            box.textContent += ((Date.now() - t0) / 1000).toFixed(2) + 's ' + text + '\n';
            var lines = box.textContent.split('\n');
            if (lines.length > 60) {
                box.textContent = lines.slice(0, 2).concat(lines.slice(-58)).join('\n');
            }
            box.scrollTop = box.scrollHeight;
        };
        log.on = true;
        return log;
    }


    /*
     * Filters panel (Refresh skin). Wide enough: docked on the right, folded / unfolded by Refresh's own "Filters"
     * button (its choice is remembered in its cookie rf_filters_closed). Not enough room (CSS container query in
     * refreshglobal.css: the panel is then "position: absolute"): it opens OVER the list and closes with ×, Escape,
     * or a click outside — nothing remembered, the list keeps its full width. Phones: Refresh's own phone filters.
     */
    function filtersPanel() {
        var layout = $('.rg-list-layout');
        var panel = layout.children('.rf-filters');
        if (!panel.length) {
            return;
        }
        var overlay = function () {
            return !$('body').hasClass('rf-m') && window.getComputedStyle(panel[0]).position === 'absolute';
        };
        var open = function (yes) {
            layout.toggleClass('rg-filters-open', yes);
            $('.rf-toolbar .rf-toggle-filters').attr('aria-expanded', yes ? 'true' : 'false');
            if (yes) {
                setTimeout(function () { panel.find('.rf-f-q').trigger('focus'); }, 230);
            }
        };
        // handler on the button itself: in overlay mode it stops Refresh's handler (delegated on the document),
        // which would fold the docked panel and remember it
        $('.rf-toolbar .rf-toggle-filters').on('click', function (e) {
            if (overlay()) {
                e.preventDefault();
                e.stopPropagation();
                open(!layout.hasClass('rg-filters-open'));
            }
        });
        // × of the panel, "Edit" of the active filters bar
        var toggle = function (show) {
            if (overlay()) {
                open(show);
            } else if (layout.hasClass('rf-filters-closed') === show) {
                $('.rf-toolbar .rf-toggle-filters').first().trigger('click');
            }
        };
        panel.on('click', '.rg-filters-close', function () { toggle(false); });
        $(document).on('click', '.rg-af-edit', function () { toggle(true); });
        // closed by a click outside (select2's lists are drawn at the end of the page) or by Escape
        $(document).on('mousedown touchstart', function (e) {
            if (layout.hasClass('rg-filters-open') && overlay()
                && !$(e.target).closest('.rf-filters, .rf-toggle-filters, .rg-af-edit, .select2-container, .select2-dropdown').length) {
                open(false);
            }
        });
        $(document).on('keydown', function (e) {
            if ((e.key === 'Escape' || e.keyCode === 27) && layout.hasClass('rg-filters-open') && !document.querySelector('.select2-container--open')) {
                open(false);
            }
        });
        // back to a docked panel (window enlarged): the overlay state is dropped
        $(window).on('resize', function () {
            if (layout.hasClass('rg-filters-open') && !overlay()) {
                layout.removeClass('rg-filters-open');
            }
        });
    }

    /*
     * "Fetch e-mails now" with the SyncNow module (Services/SyncNow.php): for each IMAP mailbox of the list, one after
     * the other, SyncNow's own calls — POST force, then GET status every 1.5 s until it is no longer "running"
     * (that call also records SyncNow's history and releases its lock). New e-mails: the list is reloaded (the
     * place in the list is kept); otherwise a short message.
     */
    function syncNow(btn) {
        if (btn.hasClass('rg-busy') || !window.fetch) {
            return;
        }
        var boxes = [];
        try { boxes = JSON.parse(btn.attr('data-mailboxes') || '[]'); } catch (e) { boxes = []; }
        if (!boxes.length) {
            return;
        }
        var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token };
        var all = $('.rg-sync-btn').addClass('rg-busy').prop('disabled', true);
        var label = btn.find('.rg-sync-label');
        var labelText = label.text();
        var titleText = btn.attr('title') || '';
        var fetched = 0, problems = [];
        var msg = function (key, vals) {
            var s = btn.attr('data-msg-' + key) || '';
            $.each(vals || {}, function (k, v) { s = s.split(':' + k).join(v); });
            return s;
        };
        var say = function (type, text) {
            if (typeof window.showFloatingAlert === 'function') { window.showFloatingAlert(type, text); } else { window.alert(text); }
        };
        var wait = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };
        var json = function (r) { return r.ok ? r.json() : { status: 'error', message: 'HTTP ' + r.status }; };

        var poll = function (box, syncToken, offset, tries) {
            return wait(1500).then(function () {
                var url = box.status + (box.status.indexOf('?') < 0 ? '?' : '&') + 'sync_token=' + encodeURIComponent(syncToken) + '&offset=' + offset;
                return window.fetch(url, { credentials: 'same-origin', headers: headers, cache: 'no-store' }).then(json);
            }).then(function (d) {
                if (d && d.status === 'running' && tries < 160) { // about 4 minutes at most
                    return poll(box, syncToken, d.offset || offset, tries + 1);
                }
                if (d && (d.status === 'success' || d.status === 'done')) {
                    fetched += parseInt(d.emails_fetched, 10) || 0;
                } else {
                    problems.push(msg('problem', { name: box.name, message: (d && d.message) || (d && d.status) || '?' }));
                }
            });
        };
        var one = function (box) {
            label.text(msg('running', { name: box.name }));
            btn.attr('title', msg('running', { name: box.name })); // icon-only button: progress in the tooltip
            return window.fetch(box.force, { method: 'POST', credentials: 'same-origin', headers: headers, body: new URLSearchParams({ _token: token }) })
                .then(json)
                .then(function (d) {
                    if (d && d.status === 'running' && d.sync_token) {
                        return poll(box, d.sync_token, 0, 0);
                    }
                    if (d && d.status === 'skipped') {
                        return null; // FreeScout's own fetch is handling this mailbox right now
                    }
                    if (d && d.status === 'cooldown') {
                        problems.push(msg('cooldown', { name: box.name, seconds: d.seconds_remaining || '?' }));
                        return null;
                    }
                    problems.push(msg('problem', { name: box.name, message: (d && d.message) || (d && d.status) || '?' }));
                    return null;
                })
                .catch(function (e) { problems.push(msg('problem', { name: box.name, message: e.message || 'network error' })); });
        };

        boxes.reduce(function (chain, box) { return chain.then(function () { return one(box); }); }, Promise.resolve())
            .then(function () {
                all.removeClass('rg-busy').prop('disabled', false);
                label.text(labelText);
                btn.attr('title', titleText);
                if (problems.length) {
                    say('warning', problems.join(' — '));
                }
                if (fetched > 0) {
                    say('success', msg('done', { count: fetched }));
                    setTimeout(function () { window.location.reload(); }, 800);
                } else if (!problems.length) {
                    say('success', msg('none'));
                }
            });
    }

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
        // #rg-deleted: back after deleting a ticket; #rg-back: back from a ticket with its breadcrumb (Public/js/shell.js)
        var deleted = /(?:^|[#&])rg-(?:deleted|back)=(\d+)/.exec(window.location.hash || '');
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
