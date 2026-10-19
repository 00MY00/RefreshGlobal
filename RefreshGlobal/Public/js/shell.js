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
 * - Automatic refresh of the "All mailboxes" list and of the dashboard when their tickets change.
 * - Loading indicator on the module's buttons while an action runs.
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

    // Loading indicator while an action of the module runs (same as the Workflow Packs module): the clicked button gets
    // FreeScout's turning icon (glyphicon-spin, public/css/style.css) and the buttons are disabled once the submission
    // has started (a disabled button would not be sent), so nothing is sent twice. Only the module's forms: its own
    // addresses (/refresh-global/…) and its section of Manage › Settings. Checked after every other handler: a
    // cancelled confirmation shows nothing.
    var SPINNER = '<i class="glyphicon glyphicon-refresh glyphicon-spin rg-spinner" aria-hidden="true"></i> ';
    var moduleForm = function (form) {
        var action = form.getAttribute('action') || '';
        return action.indexOf('/refresh-global/') >= 0 || /\/app-settings\/refreshglobal\/?$/.test(window.location.pathname);
    };
    var busy = function (button) {
        if (!button || button.querySelector('.rg-spinner')) {
            return;
        }
        // the spinner takes the place of the button's own icon (put back by "pageshow" below)
        var icon = button.querySelector('.glyphicon, .rf-i');
        if (icon) {
            icon.style.display = 'none';
            icon.classList.add('rg-hidden-icon');
        }
        button.insertAdjacentHTML('afterbegin', SPINNER);
    };
    window.rgBusy = busy;
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.getAttribute || form.getAttribute('data-rg-busy') === 'off' || !moduleForm(form)) {
            return;
        }
        setTimeout(function () {
            if (e.defaultPrevented) {
                return;
            }
            var button = e.submitter || form.querySelector('[type=submit]');
            busy(button);
            form.setAttribute('aria-busy', 'true');
            Array.prototype.forEach.call(form.querySelectorAll('[type=submit]'), function (b) { b.disabled = true; });
            // a button outside the form (form="…" attribute)
            if (button) {
                button.disabled = true;
            }
        }, 0);
    });
    // links of the module that only reload the page (e.g. "Run the checks again")
    document.addEventListener('click', function (e) {
        var a = e.target && e.target.closest ? e.target.closest('a[data-rg-busy]') : null;
        if (a && !e.defaultPrevented && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
            busy(a);
        }
    });
    // back to the page from the browser cache: clean
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) {
            return;
        }
        Array.prototype.forEach.call(document.querySelectorAll('.rg-spinner'), function (s) { s.parentNode.removeChild(s); });
        Array.prototype.forEach.call(document.querySelectorAll('.rg-hidden-icon'), function (i) { i.style.display = ''; i.classList.remove('rg-hidden-icon'); });
        Array.prototype.forEach.call(document.querySelectorAll('form[aria-busy]'), function (f) {
            f.removeAttribute('aria-busy');
            Array.prototype.forEach.call(f.querySelectorAll('[type=submit]'), function (b) { b.disabled = false; });
        });
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

    // Automatic refresh of the "All mailboxes" list (#rg-state) and of the dashboard for all mailboxes (.rg-dash):
    // every N seconds (setting, 0 = off) the page asks the module for the fingerprint of what it shows
    // (GET /refresh-global/state, same filters and rights). When it changed, the page reloads itself, but only while
    // the user is not doing something: no ticket ticked, no text being typed, no menu / dialog / phone sheet open,
    // page visible; otherwise it waits for the next check. "Keep my place in the list" restores the position.
    $(function () {
        var el = document.querySelector('#rg-state[data-state-url], .rg-dash[data-state-url]');
        var every = el ? parseInt(el.getAttribute('data-refresh'), 10) || 0 : 0;
        if (!el || every < 10 || !window.fetch) {
            return;
        }
        var url = el.getAttribute('data-state-url');
        var known = el.getAttribute('data-fp') || null;
        var changed = false, busy = false;
        var idle = function () {
            if (document.hidden) {
                return false;
            }
            if (document.querySelector('.conv-checkbox:checked')) {
                return false;
            }
            var a = document.activeElement;
            if (a && (a.isContentEditable || /^(TEXTAREA|SELECT)$/.test(a.tagName) || (a.tagName === 'INPUT' && !/^(checkbox|radio|button|submit)$/.test(a.type)))) {
                return false;
            }
            if (document.querySelector('.dropdown.open, .btn-group.open, .modal.in, .rf-m-sheet, .select2-container--open, .rg-filters-open')) {
                return false;
            }
            return !document.body.classList.contains('rf-m-overlay');
        };
        var check = function () {
            if (busy || document.hidden) {
                return;
            }
            busy = true;
            window.fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    busy = false;
                    if (!d || !d.fp) {
                        return;
                    }
                    if (known === null) {
                        known = d.fp;
                        return;
                    }
                    if (d.fp !== known) {
                        changed = true;
                    }
                    if (changed && idle()) {
                        window.location.reload();
                    }
                })
                .catch(function () { busy = false; });
        };
        setInterval(check, every * 1000);
        // back on the tab: check at once
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                check();
            }
        });
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

        backToList(c);
    });

    /*
     * Back to "All mailboxes" from a ticket opened there. Refresh's breadcrumb (and its phone back arrow, which reads
     * it: mobile.js:674) points to the last REFRESH view (cookie rf_last_view, only /mailbox/N/tickets/… addresses) or
     * to "All tickets" of the ticket's mailbox. Here, per browser tab (sessionStorage):
     *  - the "All mailboxes" page records the list to come back to (#rg-state: without the automatic folder);
     *  - a ticket page opened from it gets that list as the first item of the breadcrumb and of the phone back arrow
     *    (with the marker #rg-back=<ticket>: the list opens at that ticket);
     *  - every ticket page and every Refresh view of ONE mailbox gets a landmark at the top of Refresh's views panel
     *    (and of the phone drawer): "← All mailboxes (N)", to that list (or the whole list), and the mailbox the
     *    panel's counters belong to;
     *  - after another list (a Refresh view, a mailbox folder, a search, the dashboard), Refresh's breadcrumb is left as
     *    it is for the tickets opened from it (the landmark stays).
     */
    function backToList(c) {
        var KEY = 'rg-back';
        var store = {
            get: function () { try { return JSON.parse(window.sessionStorage.getItem(KEY) || 'null'); } catch (e) { return null; } },
            set: function (v) { try { window.sessionStorage.setItem(KEY, JSON.stringify(v)); } catch (e) { /* private mode */ } },
            clear: function () { try { window.sessionStorage.removeItem(KEY); } catch (e) { /* private mode */ } }
        };
        var state = document.getElementById('rg-state');
        if (document.body.getAttribute('data-rg-page') === 'tickets' && state) {
            store.set({
                url: state.getAttribute('data-back-url') || c.url,
                label: state.getAttribute('data-back-label') || c.label,
                count: state.getAttribute('data-back-count') || '',
                fromList: true,
                at: Date.now()
            });
            return;
        }
        // another list of tickets than ours (a Refresh view, a mailbox folder, a search, the dashboard): Refresh's own
        // breadcrumb applies again for the tickets opened from it; the list is kept for the landmark below
        var back = store.get();
        if (back && (!back.url || Date.now() - back.at > 12 * 3600 * 1000)) {
            back = null;
        }
        if (back && !c.conversation && document.querySelector('.table-conversations')) {
            back.fromList = false;
            store.set(back);
        }
        if (!c.conversation && !c.mailboxView) {
            return;
        }
        // landmark at the top of Refresh's views panel (and of the phone drawer): the way back to "All mailboxes",
        // to the list last shown there if any, and the mailbox the panel's counters belong to
        var target = back ? back.url.split('#')[0] + (c.conversation ? '#rg-back=' + c.conversation : '') : c.url;
        var label = back ? back.label : c.label;
        var count = back && back.count !== '' ? back.count : (c.total !== undefined && c.total !== '' ? String(c.total) : '');
        // ticket opened from "All mailboxes": Refresh's breadcrumb and phone back arrow lead there too
        var crumbs = !!(c.conversation && back && back.fromList !== false);
        // ...and when FreeScout leaves the ticket after an action for the ticket's mailbox folder (closing the last
        // active ticket of the folder: urlNext() shows the folder, app/Conversation.php:953-964; "after send: folder";
        // moved…), the agent goes back to that list instead: the redirect_url of the answer of conversations.ajax is
        // changed before FreeScout's or Refresh's callback opens it. Another ticket (next one) is opened as before.
        if (crumbs && $.ajaxPrefilter) {
            $.ajaxPrefilter(function (options) {
                if (!/\/conversation\/ajax(?:[?#]|$)/.test(options.url || '')) {
                    return;
                }
                var parse = (options.converters && options.converters['text json']) || JSON.parse;
                options.converters = $.extend({}, options.converters, {
                    'text json': function (text) {
                        var r = parse(text);
                        if (r && r.status === 'success' && typeof r.redirect_url === 'string' && r.redirect_url !== ''
                            && !/\/conversation\/\d+|\/refresh-global\//.test(r.redirect_url)) {
                            r.redirect_url = target;
                        }
                        return r;
                    }
                });
            });
        }
        var tries = 0;
        var apply = function () {
            var crumb = $('.rf-crumb a').first();
            var phone = $('a.rf-m-back').first();
            var views = $('.sidebar-2col .rf-views').first();
            if (crumbs && crumb.length && !crumb.hasClass('rg-back')) {
                crumb.attr('href', target).text(label).addClass('rg-back');
            }
            if (crumbs && phone.length && !phone.hasClass('rg-back')) {
                phone.attr('href', target).addClass('rg-back');
            }
            if (views.length && !views.find('.rg-conv-landmark').length) {
                var mark = $('<div class="rg-conv-landmark"></div>');
                var link = $('<a class="rf-v rg-conv-back"></a>').attr('href', target)
                    .append('<i class="rf-i rf-i-chevron-left rf-i-sm"></i>')
                    .append($('<span class="rf-v-label"></span>').text(label));
                if (count !== '') {
                    link.append($('<span class="rf-v-count"></span>').text(count));
                }
                mark.append(link);
                if (c.mailbox) {
                    mark.append($('<div class="rg-conv-mailbox"></div>').text(c.mailbox));
                }
                var search = views.children('.rf-views-search').first();
                if (search.length) {
                    mark.insertAfter(search);
                } else {
                    views.prepend(mark);
                }
                // the entry at the bottom of the panel (mailbox.after_sidebar_buttons) is no longer needed
                views.find('.rg-views-entry').hide();
            }
            // Refresh builds the breadcrumb, the panel and the phone bar on the same "ready" event, maybe a little later
            if (++tries < 30 && ((crumbs && !crumb.length) || !views.length || (crumbs && window.innerWidth < 768 && !phone.length))) {
                setTimeout(apply, 100);
            }
        };
        apply();
    }
    /*
     * "Send an e-mail" / "New ticket" (FreeScout's create form, rebuilt by Refresh's new.js): the address to send from,
     * among ALL the addresses allowed to the user (<meta name="refreshglobal-from">, Services/SendFrom.php): the address
     * of each of his mailboxes and, for an e-mail, the aliases of these mailboxes.
     *  - an address of the form's mailbox: applied at once (alias = FreeScout's "from_alias", added to the form when the
     *    mailbox shows no "From" field of its own);
     *  - an address of another mailbox: the form of that mailbox opens, with what was already typed (recipients,
     *    subject, message, contact, type, priority) carried over through sessionStorage.
     * Default when the form opens: the mailbox "All mailboxes" was filtered on, else the last address used (this
     * browser), else the form's own mailbox; opened from a mailbox or a ticket, that mailbox is kept.
     */
    /*
     * Labels of FreeScout's own forms that point to nothing a label can point to (browser warning "Incorrect use of
     * <label for=FORM_ELEMENT>"): "After Sending" -> a hidden input (resources/views/conversations/editor_bottom_toolbar.blade.php:34,63),
     * "To" -> a field not always printed (conversations/view.blade.php:267, create.blade.php:153). The "for" is
     * removed from these labels only; their text stays.
     */
    $(function () {
        var labelable = /^(INPUT|SELECT|TEXTAREA|BUTTON|METER|OUTPUT|PROGRESS)$/;
        $('label[for]').each(function () {
            var id = this.getAttribute('for');
            var el = id ? document.getElementById(id) : null;
            if (!el || !labelable.test(el.tagName) || (el.tagName === 'INPUT' && el.type === 'hidden')) {
                this.removeAttribute('for');
            }
        });
    });

    var rgStore = function (kind) {
        var s = null;
        try { s = window[kind]; } catch (e) { s = null; }
        return {
            get: function (k) { try { return JSON.parse(s.getItem(k) || 'null'); } catch (e) { return null; } },
            set: function (k, v) { try { s.setItem(k, JSON.stringify(v)); } catch (e) { /* private mode */ } },
            del: function (k) { try { s.removeItem(k); } catch (e) { /* private mode */ } }
        };
    };

    $(function () {
        // "All mailboxes": the mailbox the list is filtered on, for the next "Send an e-mail" / "New ticket"
        var state = document.getElementById('rg-state');
        if (document.body.getAttribute('data-rg-page') === 'tickets' && state) {
            rgStore('sessionStorage').set('rg-new-context', { mailbox: parseInt(state.getAttribute('data-mailbox') || '0', 10) || 0, at: Date.now() });
        }
        sendFrom();
    });

    function sendFrom() {
        var meta = document.querySelector('meta[name="refreshglobal-from"]');
        var form = $('#form-create');
        if (!meta || !form.length || !window.URL) {
            return;
        }
        var cfg;
        try { cfg = JSON.parse(meta.getAttribute('content')); } catch (e) { return; }
        var local = rgStore('localStorage');
        var session = rgStore('sessionStorage');
        var here = new URL(window.location.href);
        var ticket = here.searchParams.get('rf_mode') === 'ticket';
        var current = parseInt(cfg.mailbox, 10);
        var list = $.grep(cfg.addresses || [], function (a) { return !ticket || !a.alias; });
        var low = function (s) { return String(s || '').toLowerCase(); };
        var find = function (mailbox, email) {
            return $.grep(list, function (a) {
                return a.mailbox === mailbox && (email ? low(a.email) === low(email) : !a.alias);
            })[0] || null;
        };
        var open = function (addr, replace) {
            var url = new URL(cfg.urls[addr.mailbox], window.location.href);
            here.searchParams.forEach(function (v, k) {
                if (k !== 'rg_from') { url.searchParams.set(k, v); }
            });
            url.searchParams.set('rg_from', addr.email);
            if (replace) { window.location.replace(url.toString()); } else { window.location.href = url.toString(); }
        };

        // 1. default address when the form opens (not after a choice in the field)
        var chosen = here.searchParams.get('rg_from');
        if (!chosen) {
            var ref = '';
            try { ref = document.referrer ? new URL(document.referrer).pathname : ''; } catch (e) { ref = ''; }
            var target = null;
            var fromList = /\/refresh-global\//.test(ref);
            if (fromList) {
                var ctx = session.get('rg-new-context');
                if (ctx && ctx.mailbox) { target = find(ctx.mailbox); }
            }
            var keep = !fromList && /\/(mailbox|conversation)\/\d+/.test(ref);
            if (!target && !keep) {
                var last = local.get('rg-last-from');
                if (last) { target = find(parseInt(last.mailbox, 10), last.email) || find(parseInt(last.mailbox, 10)); }
            }
            if (target && target.mailbox !== current) {
                open(target, true);
                return;
            }
            chosen = target ? target.email : '';
        }
        var selected = find(current, chosen) || find(current);
        if (!selected) {
            return;
        }

        // 2. the field
        var nativeSel = form.find('select[name="from_alias"]').first();
        var apply = function (addr) {
            var value = addr.alias ? addr.email : '';
            if (nativeSel.length) {
                if (!nativeSel.find('option').filter(function () { return this.value === value; }).length) {
                    nativeSel.append($('<option></option>').val(value).text(addr.email));
                }
                nativeSel.val(value);
            } else if (!ticket) {
                var hidden = form.find('input[name="from_alias"]');
                if (!hidden.length) { hidden = $('<input type="hidden" name="from_alias">').appendTo(form); }
                hidden.val(value);
            }
        };
        var remember = function (addr) { local.set('rg-last-from', { mailbox: addr.mailbox, email: addr.email }); };
        var refresh = $('body').hasClass('rf-new-page') || !!window.rfNewData;
        var select = $('<select class="rg-from-select"></select>').addClass(refresh ? 'rf-select' : 'form-control')
            .attr({ title: cfg.labels.help, 'aria-label': ticket ? cfg.labels.mailbox : cfg.labels.from });
        var groups = {};
        $.each(list, function (i, a) {
            var text = ticket ? a.name + ' (' + a.email + ')' : a.email + (a.alias && a.label ? ' (' + a.label + ')' : '');
            var opt = $('<option></option>').val(String(i)).text(text).prop('selected', a === selected);
            if (ticket) {
                select.append(opt);
            } else {
                if (!groups[a.mailbox]) { groups[a.mailbox] = $('<optgroup></optgroup>').attr('label', a.name).appendTo(select); }
                groups[a.mailbox].append(opt);
            }
        });
        select.on('change', function () {
            var addr = list[parseInt(this.value, 10)];
            if (!addr) { return; }
            remember(addr);
            if (addr.mailbox === current) {
                selected = addr;
                apply(addr);
                return;
            }
            saveDraft();
            select.prop('disabled', true).after($('<span class="rg-from-switching"></span>').text(' ' + cfg.labels.switching));
            open(addr, false);
        });
        apply(selected);
        // the address really used: remembered when the form is sent
        document.addEventListener('click', function (e) {
            if (e.target && e.target.closest && e.target.closest('#form-create .rf-nw-submit, #form-create .btn-send-text, #form-create .btn-send-menu, #form-create button[type="submit"]')) {
                remember(selected);
            }
        }, true);

        var tries = 0;
        var place = function () {
            var nw = form.find('.rf-nw').first();
            // phone with Refresh: FreeScout's form kept, in app style (Modules/Refresh/Public/js/mobile.js initNew())
            var phoneLayout = $('body').hasClass('rf-m-new');
            if (refresh && !nw.length && !phoneLayout && ++tries < 40) {
                setTimeout(place, 100);
                return;
            }
            if (nw.length) {
                // Refresh: its "From" field (e-mail), or a new first field (ticket)
                var own = nw.children('.rf-nf').filter(function () { return $(this).find('select[name="from_alias"], .rf-nw-from').length > 0; }).first();
                if (own.length) {
                    own.addClass('rg-nf-from').find('.rf-nf-input').prepend(select);
                    own.find('select[name="from_alias"], .rf-nw-from').hide();
                } else {
                    $('<div class="rf-nf rg-nf-from"></div>')
                        .append($('<label class="rf-nf-label"></label>').text(ticket ? cfg.labels.mailbox : cfg.labels.from))
                        .append($('<div class="rf-nf-input"></div>').append(select))
                        .insertBefore(nw.children('.rf-nf').first());
                }
            } else if (phoneLayout && !ticket && form.find('.rf-m-nfrom').length) {
                // its read-only "From" line becomes the choice
                var line = form.find('.rf-m-nfrom').first().addClass('rg-from-group');
                line.find('.rf-m-nfromval').hide().after(select);
                nativeSel.closest('.form-group').hide();
            } else {
                // FreeScout's own form (and the phone layout of a ticket, which has no "From" line)
                nativeSel.closest('.form-group').hide();
                $('<div class="form-group rg-from-group"></div>')
                    .append($('<label class="col-sm-2 control-label"></label>').text(ticket ? cfg.labels.mailbox : cfg.labels.from))
                    .append($('<div class="col-sm-9"></div>').append(select))
                    .insertBefore(form.find('#field-to').first());
            }
            restoreDraft();
        };
        place();

        function recipients(sel) {
            return $(sel).find('option:selected').map(function () { return { v: this.value, t: $(this).text() }; }).get();
        }
        function bodyHtml() {
            var b = $('#body');
            try { if (b.next('.note-editor').length && $.fn.summernote) { return b.summernote('code'); } } catch (e) { /* plain textarea */ }
            return b.val();
        }
        function saveDraft() {
            var d = {
                at: Date.now(), subject: $('#subject').val(), body: bodyHtml(),
                to: recipients('#to'), cc: recipients('#cc'), bcc: recipients('#bcc'), fields: {}
            };
            $.each(['rf_contact_name', 'rf_contact_email', 'rf_contact_phone', 'rf_type', 'rf_priority', 'status', 'user_id'], function (i, n) {
                var el = form.find('[name="' + n + '"]').first();
                if (el.length && el.val() !== null && el.val() !== '') { d.fields[n] = el.val(); }
            });
            session.set('rg-new-draft', d);
        }
        function restoreDraft() {
            var d = session.get('rg-new-draft');
            if (!d || Date.now() - d.at > 10 * 60 * 1000) {
                session.del('rg-new-draft');
                return;
            }
            var wait = 0;
            var fill = function () {
                // after FreeScout's editor and recipient fields are ready
                if (!$('#body').next('.note-editor').length && ++wait < 50) {
                    setTimeout(fill, 100);
                    return;
                }
                session.del('rg-new-draft');
                if (d.subject) { $('#subject').val(d.subject); }
                if (d.body) {
                    try { $('#body').summernote('code', d.body); } catch (e) { $('#body').val(d.body); }
                }
                $.each({ '#to': d.to, '#cc': d.cc, '#bcc': d.bcc }, function (sel, items) {
                    var s = $(sel);
                    if (!s.length || !items || !items.length) { return; }
                    $.each(items, function (i, it) {
                        if (!s.find('option').filter(function () { return this.value === it.v; }).length) {
                            s.append(new window.Option(it.t, it.v, true, true));
                        }
                    });
                    s.val($.map(items, function (it) { return it.v; })).trigger('change');
                    if (sel !== '#to') { s.closest('.form-group, .rf-nf').show(); }
                });
                $.each(d.fields || {}, function (n, v) { form.find('[name="' + n + '"]').first().val(v).trigger('change'); });
            };
            fill();
        }
    }
    /*
     * Ticket page: "Details / raw text" of each message (Http/Controllers/ThreadDetailsController.php). Refresh hides
     * FreeScout's message menu (and its "Show original"): a button is added to Refresh's tools of each message
     * (.rf-th-tools, built by Refresh's provider script); without Refresh the entry is in FreeScout's own menu
     * ("thread.menu.append"). The window: Details / Raw text tabs, copy, and FreeScout's own original source.
     */
    $(function () {
        var meta = document.querySelector('meta[name="refreshglobal-thread"]');
        if (!meta) {
            return;
        }
        var cfg;
        try { cfg = JSON.parse(meta.getAttribute('content')); } catch (e) { return; }
        var urlOf = function (id) { return cfg.url.replace('999999999', String(id)); };

        // Refresh's tools of each message (built after "ready", and again when messages are added)
        var addButtons = function () {
            $('#conv-layout-main .thread').each(function () {
                var th = $(this);
                var tools = th.children('.rf-th-tools');
                var id = th.attr('data-thread_id');
                if (!tools.length || !id || th.hasClass('thread-type-note') || tools.find('.rg-th-details').length) {
                    return;
                }
                tools.append($('<button type="button" class="rf-th-tool rg-th-details rg-td-open"><span class="rg-td-i" aria-hidden="true">i</span></button>')
                    .attr({ title: cfg.label, 'aria-label': cfg.label, 'data-url': urlOf(id) }));
            });
        };
        var tries = 0;
        var poll = function () {
            addButtons();
            if (++tries < 20) { setTimeout(poll, 300); }
        };
        poll();
        if (window.MutationObserver) {
            var main = document.getElementById('conv-layout-main');
            if (main) { new MutationObserver(function () { addButtons(); }).observe(main, { childList: true, subtree: true }); }
        }

        var box = null;
        var close = function () {
            if (box) { box.remove(); box = null; }
            $(document).off('keydown.rgtd');
        };
        var open = function (url) {
            close();
            box = $('<div class="rg-td-modal" role="dialog" aria-modal="true"></div>').attr('aria-label', cfg.label);
            var dlg = $('<div class="rg-td-dialog"></div>');
            var head = $('<div class="rg-td-head"></div>').append($('<span class="rg-td-title"></span>').text(cfg.label),
                $('<button type="button" class="rg-td-close">&times;</button>').attr({ title: cfg.close, 'aria-label': cfg.close }));
            var bodyEl = $('<div class="rg-td-body"><i class="glyphicon glyphicon-refresh glyphicon-spin"></i></div>');
            box.append(dlg.append(head, bodyEl)).appendTo('body');
            box.on('click', function (e) { if (e.target === box[0]) { close(); } });
            head.find('.rg-td-close').on('click', close);
            $(document).on('keydown.rgtd', function (e) { if (e.key === 'Escape' || e.keyCode === 27) { close(); } });
            $.ajax({ url: url, dataType: 'html' }).done(function (html) {
                bodyEl.html(html);
            }).fail(function () {
                bodyEl.empty().append($('<p class="text-danger"></p>').text(cfg.error));
            });
        };
        $(document).on('click', '.rg-td-open', function (e) {
            e.preventDefault();
            open($(this).attr('data-url'));
        });
        $(document).on('click', '.rg-td-tab', function () {
            var t = $(this);
            var root = t.closest('.rg-td');
            root.find('.rg-td-tab').removeClass('active');
            t.addClass('active');
            root.find('.rg-td-pane').removeClass('active').filter('[data-pane="' + t.attr('data-tab') + '"]').addClass('active');
        });
        $(document).on('click', '.rg-td-copy', function () {
            var btn = $(this);
            var text = btn.closest('.rg-td-pane').find('.rg-td-raw').text();
            var done = function () { btn.text(cfg.copied); };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done, function () {});
            } else {
                var ta = $('<textarea></textarea>').val(text).appendTo('body').select();
                try { document.execCommand('copy'); done(); } catch (er) { /* no clipboard */ }
                ta.remove();
            }
        });
        // FreeScout's own original source (its window's HTML, with its tabs), in the same window
        $(document).on('click', '.rg-td-original-link', function (e) {
            e.preventDefault();
            var target = $(this).closest('.rg-td-body');
            target.html('<i class="glyphicon glyphicon-refresh glyphicon-spin"></i>');
            $.ajax({ url: $(this).attr('data-url'), dataType: 'html' }).done(function (html) {
                target.html(html);
            }).fail(function () {
                target.empty().append($('<p class="text-danger"></p>').text(cfg.error));
            });
        });
    });

    /*
     * Remote images of the e-mails (Services/RemoteImages.php, mode "ask"): "Show images" loads the images of that
     * message only (their addresses were kept in data-rg-* attributes).
     */
    $(document).on('click', '.rg-ri-show', function () {
        var box = $(this).closest('.rg-remote-images');
        var scope = box.parent();
        scope.find('[data-rg-remote]').each(function () {
            var el = $(this);
            $.each(['src', 'srcset', 'background', 'style'], function (i, name) {
                var v = el.attr('data-rg-' + name);
                if (v !== undefined) {
                    el.attr(name, v).removeAttr('data-rg-' + name);
                }
            });
            el.removeAttr('data-rg-remote');
        });
        box.remove();
    });

    /*
     * "Move to a folder" of the mail server (Services/MailMove.php, Http/Controllers/MailMoveController.php): the
     * e-mails of the ticket(s) go to an existing folder of their mailbox on the server; the ticket itself does not
     * change. Entry points: ticket page (Refresh's toolbar, FreeScout's toolbar, Refresh's phone "Ticket actions"
     * sheet), selection bar of the lists (FreeScout's bulk actions, "bulk_actions.before_delete"), and on touch screens
     * a swipe to the LEFT on a ticket of a list (Refresh's own swipe is to the right). After a move, a message offers
     * "Undo" for a few seconds.
     */
    var moveCfg = function () {
        try {
            return JSON.parse(document.querySelector('meta[name="refreshglobal-move"]').getAttribute('content'));
        } catch (e) {
            return null;
        }
    };
    var csrf = function () { return $('meta[name="csrf-token"]').attr('content'); };

    var toast = function (text, undo) {
        $('.rg-toast').remove();
        var t = $('<div class="rg-toast" role="status"></div>').append($('<span class="rg-toast-text"></span>').text(text));
        var timer = null;
        var close = function () { clearTimeout(timer); t.addClass('rg-toast-out'); setTimeout(function () { t.remove(); }, 250); };
        if (undo) {
            t.append($('<button type="button" class="rg-toast-undo"></button>').text(undo.label).on('click', function () { close(); undo.fn(); }));
        }
        t.append($('<button type="button" class="rg-toast-x" aria-label="×">&times;</button>').on('click', close));
        $('body').append(t);
        timer = setTimeout(close, undo ? 8000 : 4000);
    };

    var moveDialog = function (ids, onMoved) {
        var M = moveCfg();
        if (!M || !ids.length) {
            return;
        }
        $('.rg-move').remove();
        var box = $('<div class="rg-move" role="dialog" aria-modal="true"></div>');
        var panel = $('<div class="rg-move-panel"></div>');
        var head = $('<div class="rg-move-head"></div>').append($('<strong></strong>').text(ids.length > 1 ? M.t.title_many.replace(':count', ids.length) : M.t.title));
        var x = $('<button type="button" class="rg-move-x" aria-label="×">&times;</button>');
        head.append(x);
        var search = $('<input type="search" class="form-control input-sm rg-move-search" autocomplete="off">').attr('placeholder', M.t.search);
        var list = $('<div class="rg-move-list"><p class="rg-muted"><i class="glyphicon glyphicon-refresh glyphicon-spin"></i> ' + '</p></div>');
        var note = $('<p class="rg-muted rg-move-note"></p>').text(M.t.note);
        panel.append(head, search, list, note);
        box.append(panel);
        var close = function () { box.remove(); $(document).off('keydown.rgmove'); };
        x.on('click', close);
        box.on('click', function (e) { if (e.target === box[0]) { close(); } });
        $(document).on('keydown.rgmove', function (e) { if (e.key === 'Escape') { close(); } });
        $('body').append(box);
        $.ajax({ url: M.folders, data: { conversation_id: ids[0] }, dataType: 'json' }).done(function (r) {
            list.empty();
            if (!r || r.status !== 'success') {
                list.append($('<p class="text-danger"></p>').text((r && r.message) || M.t.error));
                return;
            }
            var current = (r.current || []).map(function (c) { return String(c).toLowerCase(); });
            $.each(r.folders || [], function (i, f) {
                var here = current.indexOf(String(f.path).toLowerCase()) >= 0;
                var b = $('<button type="button" class="rg-move-folder"></button>').attr('data-path', f.path).attr('data-label', String(f.label).toLowerCase())
                    .append('<i class="glyphicon glyphicon-folder-close" aria-hidden="true"></i> ', $('<span></span>').text(f.label));
                if (here) {
                    b.addClass('rg-move-here').prop('disabled', ids.length === 1).append($('<small></small>').text(M.t.current));
                }
                list.append(b);
            });
            search.trigger('focus');
        }).fail(function (xhr) {
            list.empty().append($('<p class="text-danger"></p>').text((xhr.responseJSON && xhr.responseJSON.message) || M.t.error));
        });
        search.on('input', function () {
            var q = $.trim(search.val()).toLowerCase();
            list.find('.rg-move-folder').each(function () { $(this).toggle(!q || $(this).attr('data-label').indexOf(q) >= 0); });
        });
        list.on('click', '.rg-move-folder', function () {
            var path = $(this).attr('data-path');
            list.find('.rg-move-folder').prop('disabled', true);
            $(this).append(' <i class="glyphicon glyphicon-refresh glyphicon-spin"></i>');
            $.ajax({ url: M.move, method: 'POST', dataType: 'json', data: { _token: csrf(), folder: path, conversation_ids: ids } }).done(function (r) {
                close();
                if (!r || r.status !== 'success') {
                    toast((r && r.message) || M.t.error);
                    return;
                }
                var undoable = $.grep(r.tickets || [], function (t) { return t.moved > 0; });
                if (onMoved) { onMoved(r); }
                toast(r.message + (r.errors && r.errors.length ? ' — ' + r.errors.join(' — ') : ''), undoable.length ? { label: M.t.undo, fn: function () {
                    $.ajax({ url: M.undo, method: 'POST', dataType: 'json', data: { _token: csrf(), tickets: JSON.stringify(undoable) } }).done(function (u) {
                        toast((u && u.message) || M.t.error);
                        if (onMoved) { onMoved(null); }
                    }).fail(function () { toast(M.t.error); });
                } } : null);
            }).fail(function (xhr) {
                close();
                toast((xhr.responseJSON && xhr.responseJSON.message) || M.t.error);
            });
        });
    };
    window.rgMoveDialog = moveDialog;

    $(function () {
        var M = moveCfg();
        if (!M) {
            return;
        }
        var convId = function () {
            var id = window.getGlobalAttr ? window.getGlobalAttr('conversation_id') : null;
            return id ? parseInt(id, 10) : 0;
        };
        // a row of a list moved: discreet mark under its subject (removed by "Undo")
        var markRow = function (ids) {
            return function (r) {
                $.each(ids, function (i, id) {
                    var row = $('.conv-checkbox[value="' + id + '"]').closest('tr');
                    row.find('.rg-moved').remove();
                    if (r) {
                        row.find('td.conv-subject').first().append($('<span class="rg-moved"></span>').text('→ ' + r.label));
                    }
                });
            };
        };

        // ticket page, computer: Refresh's toolbar (next to "Close"), or FreeScout's own toolbar
        if (convId() && M.ticket) {
            var button = function (cls) {
                return $('<button type="button"></button>').addClass(cls + ' rg-move-btn').attr('title', M.t.title)
                    .append('<i class="glyphicon glyphicon-folder-open" aria-hidden="true"></i>', $('<span class="rg-move-btn-text"></span>').text(' ' + M.t.button));
            };
            var tries = 0;
            var place = function () {
                if ($('.rg-move-btn').length) {
                    return;
                }
                var close = $('.rf-tb-close').first();
                if (close.length) {
                    button('rf-btn').insertAfter(close);
                } else if ($('#conv-toolbar .conv-actions').length && !$('body').hasClass('rf-conv')) {
                    button('btn btn-default btn-sm').appendTo($('#conv-toolbar .conv-actions').first());
                } else if (++tries < 30) {
                    setTimeout(place, 100);
                }
            };
            place();
            $(document).on('click', '.rg-move-btn', function () { moveDialog([convId()]); });
        }

        // phone, ticket page: Refresh's "Ticket actions" sheet
        if (convId() && M.ticket && window.MutationObserver && window.matchMedia && window.matchMedia('(max-width: 767px)').matches) {
            new MutationObserver(function (records) {
                records.forEach(function (r) {
                    $(r.addedNodes).filter('.rf-m-sheet-actions').each(function () {
                        var list = $(this).find('.rf-m-sheet-list');
                        if (!list.length || list.find('.rg-m-move').length) {
                            return;
                        }
                        $('<button type="button" class="rf-m-opt rg-m-move"></button>')
                            .append('<i class="glyphicon glyphicon-folder-open rg-m-move-icon" aria-hidden="true"></i>')
                            .append($('<span class="rf-m-opt-label"></span>').text(M.t.title))
                            .on('click', function () {
                                $('.rf-m-sheet').remove();
                                $('body').removeClass('rf-m-overlay rf-m-drawer rf-m-pop-open');
                                moveDialog([convId()]);
                            })
                            .prependTo(list);
                    });
                });
            }).observe(document.body, { childList: true });
        }

        // lists: selection bar (button printed by the server in FreeScout's bulk actions)
        $(document).on('click', '.rg-move-bulk', function () {
            var ids = $('.conv-checkbox:checked').map(function () { return parseInt(this.value, 10); }).get().filter(function (v) { return v > 0; });
            moveDialog(ids, markRow(ids));
        });

        // lists, touch screens: swipe a ticket to the LEFT -> "Move to a folder"
        if (!('ontouchstart' in window) || !$('.table-conversations').length) {
            return;
        }
        var sw = null;
        var hint = null;
        var clearHint = function () { if (hint) { hint.remove(); hint = null; } };
        $(document).on('touchstart', 'tr.conv-row', function (e) {
            if ($('body').hasClass('rf-m-sel') || e.originalEvent.touches.length !== 1) { sw = null; return; }
            var t = e.originalEvent.touches[0];
            sw = { tr: $(this), x: t.clientX, y: t.clientY, dir: '', dx: 0 };
        });
        $(document).on('touchmove', 'tr.conv-row', function (e) {
            if (!sw) { return; }
            var t = e.originalEvent.touches[0];
            var dx = t.clientX - sw.x, dy = t.clientY - sw.y;
            if (!sw.dir && (Math.abs(dx) > 10 || Math.abs(dy) > 10)) { sw.dir = Math.abs(dx) > Math.abs(dy) && dx < 0 ? 'left' : 'other'; }
            if (sw.dir !== 'left') { clearHint(); return; }
            sw.dx = dx;
            var r = sw.tr[0].getBoundingClientRect();
            if (!hint) {
                hint = $('<div class="rg-swipe-move" aria-hidden="true"></div>').append('<i class="glyphicon glyphicon-folder-open"></i>', $('<span></span>').text(M.t.button));
                $('body').append(hint);
            }
            var w = Math.min(Math.max(-dx, 0), 160);
            hint.css({ top: r.top + window.pageYOffset, left: r.right - w + window.pageXOffset, width: w, height: r.height })
                .toggleClass('rg-swipe-ready', -dx > 90);
        });
        $(document).on('touchend touchcancel', 'tr.conv-row', function (e) {
            if (!sw) { return; }
            var go = sw.dir === 'left' && sw.dx < -90 && e.type === 'touchend';
            var id = parseInt(sw.tr.find('.conv-checkbox').val(), 10);
            clearHint();
            sw = null;
            if (go && id) {
                // the release must not open the ticket (only a click on the list's rows is stopped, never the dialog's)
                var stop = function (ev) {
                    if (!$(ev.target).closest('tr.conv-row').length) {
                        return;
                    }
                    ev.preventDefault();
                    ev.stopPropagation();
                    document.removeEventListener('click', stop, true);
                };
                document.addEventListener('click', stop, true);
                setTimeout(function () { document.removeEventListener('click', stop, true); }, 600);
                moveDialog([id], markRow([id]));
            }
        });
    });
})(window.jQuery);
