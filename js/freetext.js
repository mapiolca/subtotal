/* SPDX-License-Identifier: GPL-3.0-or-later */
(function ($) {
    'use strict';
    if (window.SubtotalFreeText) return;

    function editorValue(id) {
        var editor = window.CKEDITOR && CKEDITOR.instances[id];
        return editor ? editor.getData() : $('#' + id).val() || '';
    }
    function destroyEditor(id) {
        var editor = window.CKEDITOR && CKEDITOR.instances[id];
        if (editor) editor.destroy(true);
    }
    function message(root, text) {
        root.find('.subtotal-freetext-error').first().text(text || '').prop('hidden', false).toggle(!!text);
    }
    function request(config, data, write) {
        var payload = $.extend({}, data, {element: config.element, elementid: config.elementid});
        if (write) {
            payload.token = config.token;
            var random = new Uint32Array(4);
            window.crypto.getRandomValues(random);
            payload.request_id = Array.prototype.join.call(random, '');
        }
        return $.ajax({url: config.url, method: write ? 'POST' : 'GET', data: payload, dataType: 'json'}).then(function (response) {
            if (response.newToken) config.token = response.newToken;
            if (!response.result) return $.Deferred().reject({responseJSON: response}).promise();
            return response.data;
        }, function (error) {
            if (error.responseJSON && error.responseJSON.newToken) config.token = error.responseJSON.newToken;
            return $.Deferred().reject(error).promise();
        });
    }
    function errorText(error, config) {
        return error && error.responseJSON && error.responseJSON.msg || config.labels.SubtotalFreeTextLoadFailed;
    }
    function confirmAction(config, text, accept) {
        var confirmation = $('<div>').text(text).appendTo('body');
        var buttons = {};
        buttons[config.labels.Yes] = function () { confirmation.dialog('close'); accept(); };
        buttons[config.labels.No] = function () { confirmation.dialog('close'); };
        confirmation.dialog({modal: true, title: config.labels.Confirm, buttons: buttons,
            close: function () { confirmation.dialog('destroy').remove(); }});
    }

    function attach(dialog, config) {
        var activeSequence = 0, disposed = false;
        var select = dialog.find('#subtotal_free_text_dialog');
        function resize() {
            if (disposed || !select.length) return;
            dialog.dialog('option', {width: Math.min(1000, Math.floor(window.innerWidth * 0.95)), maxHeight: Math.floor(window.innerHeight * 0.9), height: 'auto',
                position: {my: 'center', at: 'center', of: window, collision: 'fit'}});
        }
        function editorReady(event) { if (event.editor.name === 'sub-total-title') resize(); }
        if (window.CKEDITOR) CKEDITOR.on('instanceReady', editorReady);
        $(window).on('resize.subtotalFreeTextInsertion', resize);
        dialog.prepend($('<div class="error subtotal-freetext-error" role="alert">').hide());
        function loadSelected() {
            var content = select.find('option:selected').attr('data-content');
            if (content === undefined) return;
            var editor = window.CKEDITOR && CKEDITOR.instances['sub-total-title'];
            if (editor) editor.setData(content);
            else dialog.find('#sub-total-title').val(content);
        }
        select.off('.subtotalFreeText').on('change.subtotalFreeText', loadSelected);
        // Select2 closes without a change/select event when the already-selected item is chosen.
        select.on('select2:open.subtotalFreeText', function () {
            var wrapper = select.parent(), results = wrapper.find('.select2-results__options');
            results.off('.subtotalFreeText').on('mouseup.subtotalFreeText', '.select2-results__option[aria-selected=true]', loadSelected);
            wrapper.find('.select2-search__field').off('keydown.subtotalFreeText').on('keydown.subtotalFreeText', function (event) {
                if (event.key === 'Enter' && results.find('.select2-results__option--highlighted[aria-selected=true]').length) loadSelected();
            });
        });
        function refresh() {
            var sequence = ++activeSequence;
            request(config, {view: 'active'}).done(function (data) {
                if (disposed || sequence !== activeSequence) return;
                var selected = select.val();
                select.empty().append(new Option('', ''));
                data.rows.forEach(function (row) {
                    $('<option>').val(row.id).text(row.label).attr('data-content', row.content).appendTo(select);
                });
                select.val(selected);
                if (select.val() === null) select.val('');
                // Refresh Select2 only: never replace the user's draft editor content here.
                select.trigger('change.select2');
            }).fail(function (error) { if (!disposed && sequence === activeSequence) message(dialog, errorText(error, config)); });
        }
        dialog.on('dialogclose.subtotalFreeText', function () {
            disposed = true;
            activeSequence++;
            $(window).off('resize.subtotalFreeTextInsertion', resize);
            if (window.CKEDITOR) CKEDITOR.removeListener('instanceReady', editorReady);
            destroyEditor('sub-total-title');
            dialog.find('select.select2-hidden-accessible').each(function () { $(this).select2('destroy'); });
        });
        dialog.on('dialogbeforeclose.subtotalFreeText', function () {
            if (dialog.data('subtotal-inserting')) return false;
            var manager = dialog.data('subtotal-manager');
            if (manager) { manager.dialog('close'); return false; }
        });
        dialog.on('click.subtotalFreeText', '#subtotal-manage-freetext', function (event) {
            event.preventDefault();
            if (config.canManage && !dialog.data('subtotal-manager')) openManager(dialog, config, refresh);
        });
        if (select.length) refresh();
    }

    function openManager(parent, config, refreshed) {
        var manager = $('<div class="subtotal-freetext-manager">').appendTo('body');
        manager.append('<div class="error subtotal-freetext-error" role="alert" hidden></div><div class="subtotal-freetext-editor"></div><div class="subtotal-freetext-list"></div>');
        parent.data('subtotal-manager', manager);
        var state = {busy: false, disposed: false, baseline: null, allowClose: false, sequences: {form: 0, list: 0}, query: {view: 'manager'}};
        function resize() {
            if (state.disposed) return;
            // Native themes cap dialogs at 70%; this dictionary needs the requested wide viewport.
            manager.dialog('widget').css('max-width', '95vw');
            manager.dialog('option', {width: Math.floor(document.documentElement.clientWidth * 0.95), maxHeight: Math.floor(window.innerHeight * 0.9), height: 'auto',
                position: {my: 'center', at: 'center', of: window, collision: 'fit'}});
        }
        function snapshot() {
            var form = manager.find('.subtotal-freetext-form');
            return {label: form.find('[name=label]').val() || '', active: form.find('[name=active]').val(), content: editorValue('subtotal_freetext_content')};
        }
        function dirty() { return state.baseline && JSON.stringify(snapshot()) !== JSON.stringify(state.baseline); }
        function abandoning(callback) {
            if (state.busy) return;
            if (dirty()) confirmAction(config, config.labels.SubtotalFreeTextDiscard, callback);
            else callback();
        }
        function setForm(html) {
            destroyEditor('subtotal_freetext_content');
            manager.find('.subtotal-freetext-editor').html(html);
            state.baseline = snapshot();
        }
        function ready(event) {
            if (!state.disposed && state.baseline && event.editor.name === 'subtotal_freetext_content') { state.baseline.content = event.editor.getData(); resize(); }
        }
        if (window.CKEDITOR) CKEDITOR.on('instanceReady', ready);
        function load(query, edit) {
            var channel = edit ? 'form' : 'list', sequence = ++state.sequences[channel];
            return request(config, query).done(function (data) {
                if (state.disposed || sequence !== state.sequences[channel]) return;
                if (data.form) setForm(data.form);
                if (data.list) {
                    manager.find('.subtotal-freetext-list select.select2-hidden-accessible').each(function () { $(this).select2('destroy'); });
                    manager.find('.subtotal-freetext-list').html(data.list);
                }
                if (!edit) state.query = $.extend({}, query, {view: 'list'});
                message(manager, '');
                resize();
            }).fail(function (error) { if (!state.disposed && sequence === state.sequences[channel]) message(manager, errorText(error, config)); });
        }
        function write(data, updateForm) {
            if (state.busy) return;
            state.busy = true;
            manager.find(':input').prop('disabled', true);
            request(config, data, true).done(function (result) {
                if (state.disposed) return;
                var formUpdate = updateForm ? load({view: 'edit', id: result.entry.id}, true) : $.Deferred().resolve();
                $.when(formUpdate, load(state.query, false)).always(unlock);
            }).fail(function (error) {
                message(manager, errorText(error, config));
                if (error.responseJSON && error.responseJSON.data && error.responseJSON.data.fieldErrors && error.responseJSON.data.fieldErrors.label) {
                    manager.find('[name=label]').attr('aria-invalid', 'true').addClass('fielderror');
                }
            }).fail(unlock);
            function unlock() { state.busy = false; manager.find(':input').prop('disabled', false); }
        }
        manager.on('submit.subtotalFreeText', '.subtotal-freetext-form', function (event) {
            event.preventDefault();
            var form = $(this), data = snapshot();
            data.id = form.find('[name=id]').val();
            data.signature = form.find('[name=signature]').val();
            data.action = Number(data.id) > 0 ? 'update' : 'create';
            write(data, true);
        });
        manager.on('click.subtotalFreeText', '.subtotal-freetext-active', function (event) {
            event.preventDefault();
            if (state.busy) return;
            var control = $(this), value = control.siblings('[name=active]');
            value.val(value.val() === '1' ? '0' : '1');
            control.attr('aria-checked', value.val() === '1' ? 'true' : 'false');
            control.find('[id^=set_]').toggleClass('hideobject', value.val() === '1');
            control.find('[id^=del_]').toggleClass('hideobject', value.val() !== '1');
        });
        manager.on('click.subtotalFreeText', '#subtotal-freetext-new,.subtotal-freetext-reset,.subtotal-freetext-edit', function (event) {
            event.preventDefault();
            var id = $(this).hasClass('subtotal-freetext-edit') ? $(this).closest('tr').attr('data-id') : 0;
            abandoning(function () { load({view: 'edit', id: id}, true); });
        });
        manager.on('click.subtotalFreeText', '.subtotal-freetext-delete,.subtotal-freetext-toggle', function (event) {
            event.preventDefault();
            var row = $(this).closest('tr'), deleting = $(this).hasClass('subtotal-freetext-delete');
            var data = {id: row.attr('data-id'), signature: row.attr('data-signature'), action: deleting ? 'delete' : 'toggle'};
            function run() { write(data, false); }
            if (deleting) confirmAction(config, config.labels.SubtotalFreeTextDeleteConfirm, run);
            else run();
        });
        manager.on('keydown.subtotalFreeText', '[role=switch]', function (event) {
            if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); $(this).trigger('click'); }
        });
        manager.on('submit.subtotalFreeText', '.subtotal-freetext-search', function (event) {
            event.preventDefault();
            var query = {view: 'list', page: 0};
            $(this).serializeArray().forEach(function (field) { query[field.name] = field.value; });
            if ($(this).data('reset')) { query.search_label = ''; $(this).data('reset', false); }
            load(query, false);
        });
        manager.on('click.subtotalFreeText', '.button_removefilter', function () { $(this).closest('form').data('reset', true); });
        manager.on('click.subtotalFreeText', '.subtotal-freetext-search a:not(#subtotal-freetext-new):not([class^=subtotal-freetext])', function (event) {
            var href = $(this).attr('href');
            if (!href || href === '#') return;
            event.preventDefault();
            var query = {view: 'list'}, url = new URL(href, window.location.href);
            url.searchParams.forEach(function (value, key) { query[key] = value; });
            load(query, false);
        });
        var buttons = {};
        buttons[config.labels.CloseWindow] = function () { manager.dialog('close'); };
        manager.dialog({modal: true, width: Math.floor(document.documentElement.clientWidth * 0.95), maxHeight: Math.floor(window.innerHeight * 0.9),
            title: config.labels.SubtotalFreeTextManage, buttons: buttons,
            beforeClose: function () {
                if (state.busy) return false;
                if (!state.allowClose && dirty()) {
                    confirmAction(config, config.labels.SubtotalFreeTextDiscard, function () { state.allowClose = true; manager.dialog('close'); });
                    return false;
                }
                return true;
            },
            close: function () {
                state.disposed = true;
                if (window.CKEDITOR) CKEDITOR.removeListener('instanceReady', ready);
                destroyEditor('subtotal_freetext_content');
                manager.find('select.select2-hidden-accessible').each(function () { $(this).select2('destroy'); });
                $(window).off('resize.subtotalFreeTextManager', resize);
                manager.dialog('destroy').remove();
                parent.removeData('subtotal-manager');
                refreshed();
            }});
        $(window).on('resize.subtotalFreeTextManager', resize);
        load(state.query, false);
    }

    function insert(dialog, config, params) {
        if (dialog.data('subtotal-inserting')) return $.Deferred().reject().promise();
        dialog.data('subtotal-inserting', true);
        var buttons = dialog.dialog('widget').find('.ui-dialog-buttonpane button').prop('disabled', true);
        return request(config, {action: 'insert', content: editorValue('sub-total-title'), under_title: params.under_title, rank: params.rank}, true)
            .fail(function (error) { message(dialog, errorText(error, config)); })
            .always(function () { dialog.data('subtotal-inserting', false); buttons.prop('disabled', false); });
    }
    window.SubtotalFreeText = {attach: attach, insert: insert};
})(jQuery);
