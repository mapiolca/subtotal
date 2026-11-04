/* Browser-only fixture. Native widgets and actual module JS; all API responses below are simulated. */
(function ($) {
    'use strict';
    var records = [], nextId = 26, writes = 0, failNext = false, lastInsert = '', rich = false, serverToken = 'fixture';
    for (var i = 1; i <= 25; i++) records.push({id: i, label: i === 1 ? 'Conditions de prestation' : 'Texte ' + i,
        content: '<p>Conditions <strong>initiales ' + i + '</strong></p>', active: 1, signature: 'revision' + i});
    function form(row) {
        row = row || {id: 0, label: '', content: '', active: 1, signature: ''};
        var template = document.createElement('template');
        template.innerHTML = rich ? fixtures.richForm : fixtures.form;
        ['id', 'label', 'active', 'signature'].forEach(function (key) { template.content.querySelector('[name=' + key + ']').setAttribute('value', row[key]); });
        template.content.querySelector('textarea').textContent = row.content;
        var control = template.content.querySelector('[role=switch]');
        control.setAttribute('aria-checked', row.active ? 'true' : 'false');
        control.querySelector('[id^=set_]').classList.toggle('hideobject', !!row.active);
        control.querySelector('[id^=del_]').classList.toggle('hideobject', !row.active);
        return template.innerHTML;
    }
    function list(data) {
        var found = records.filter(function (row) { return !data.search_label || row.label.indexOf(data.search_label) !== -1; });
        var template = document.createElement('template');
        template.innerHTML = found.length ? fixtures.pageList : fixtures.emptyList;
        var table = template.content.querySelector('table.noborder'), baseRow = table.querySelector('[data-id]');
        if (baseRow) {
            Array.prototype.forEach.call(table.querySelectorAll('[data-id]'), function (tr) { tr.remove(); });
            var start = Number(data.page || 0) * Number(data.limit || 20);
            found.slice(start, start + Number(data.limit || 20)).forEach(function (row) {
                var tr = baseRow.cloneNode(true);
                tr.setAttribute('data-id', row.id); tr.setAttribute('data-signature', row.signature);
                tr.children[0].textContent = row.label;
                var preview = document.createElement('template'); preview.innerHTML = row.content;
                tr.children[1].textContent = preview.content.textContent;
                tr.querySelector('[role=switch]').setAttribute('aria-checked', row.active ? 'true' : 'false');
                table.querySelector('tbody').appendChild(tr);
            });
        }
        template.content.querySelector('[name=search_label]').setAttribute('value', data.search_label || '');
        return template.innerHTML;
    }
    var nativeAjax = $.ajax;
    $.ajax = function (options) {
        if (options.url !== '/fixture-api' && options.url.indexOf('/subtotal/script/freetext.php') === -1) return nativeAjax.apply(this, arguments);
        var promise = $.Deferred(), data = options.data;
        setTimeout(function () {
            if (data.action && data.token !== serverToken) { promise.reject({responseJSON: {msg: 'Jeton invalide simulé', newToken: serverToken}}); return; }
            if (data.action && failNext) { failNext = false; serverToken = 'rotated' + writes; promise.reject({responseJSON: {msg: 'Erreur serveur simulée', newToken: serverToken}}); return; }
            var result, row = records.filter(function (item) { return item.id === Number(data.id); })[0];
            if (data.action) {
                writes++;
                if (data.action === 'insert') { lastInsert = data.content; result = {id: 99}; }
                else {
                    if (data.action !== 'create' && (!row || row.signature !== data.signature)) {
                        promise.reject({responseJSON: {msg: 'Conflit concurrent simulé'}}); return;
                    }
                    if (data.action === 'create') { row = {id: nextId++}; records.push(row); }
                    if (data.action === 'create' || data.action === 'update') $.extend(row, {label: data.label, content: data.content, active: Number(data.active)});
                    if (data.action === 'toggle') row.active = row.active ? 0 : 1;
                    if (data.action === 'delete') records = records.filter(function (item) { return item !== row; });
                    row.signature = 'revision' + row.id + '-' + writes;
                    result = {entry: row};
                }
            } else if (data.view === 'active') result = {rows: records.filter(function (item) { return item.active; })};
            else if (data.view === 'edit') result = {form: form(row)};
            else result = $.extend({list: list(data)}, data.view === 'manager' ? {form: form()} : {});
            promise.resolve({result: 1, data: result, newToken: serverToken});
        }, data.search_label === 'slow' ? 250 : 20);
        return promise.promise();
    };
    function open() {
        if ($('#dialog-prompt-subtotal').length) return;
        rich = $('#rich').prop('checked');
        var outer = $('<div id="dialog-prompt-subtotal">').appendTo('body');
        outer.html(fixtures.selectors + '<br><textarea id="sub-total-title" rows="8" style="width:100%"></textarea>');
        outer.dialog({modal: true, width: Math.min(1000, window.innerWidth * 0.95), maxHeight: window.innerHeight * 0.9,
            title: 'Saisissez votre description', buttons: {
                OK: function () { SubtotalFreeText.insert(outer, fixtures.config, {under_title: outer.find('[name=under_title]').val(), rank: 0})
                    .done(function () { $('#inserted').text(lastInsert); outer.dialog('close'); }); },
                Annuler: function () { outer.dialog('close'); }
            }});
        SubtotalFreeText.attach(outer, fixtures.config);
        outer.on('dialogclose.fixture', function () { outer.dialog('destroy').remove(); });
        if (rich) CKEDITOR.replace('sub-total-title', {customConfig: ckeditorConfig, toolbar: 'dolibarr_details', versionCheck: false, height: 200});
    }
    $('#open').on('click', open);
    function sleep(ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); }
    async function until(test) { for (var n = 0; n < 120; n++) { if (test()) return; await sleep(50); } throw new Error('Délai dépassé'); }
    function value(id, text) { var editor = CKEDITOR.instances[id]; if (editor) editor.setData(text); else $('#' + id).val(text); }
    function read(id) { var editor = CKEDITOR.instances[id]; return editor ? editor.getData() : $('#' + id).val(); }
    function button(dialog, text) { return dialog.dialog('widget').find('.ui-dialog-buttonpane button').filter(function () { return $(this).text() === text; }); }
    function answer(text) { $('.ui-dialog:visible').last().find('.ui-dialog-buttonpane button').filter(function () { return $(this).text() === text; }).trigger('click'); }
    $('#run').on('click', async function () {
        var output = $('#results').text(''), count = 0;
        function check(condition, title) { if (!condition) throw new Error(title); count++; output.append('OK ' + title + '\n'); }
        try {
            $('a[rel=add_free_text]').trigger('click');
            await until(function () { return $('#dialog-prompt-subtotal select.select2-hidden-accessible').length === 2; });
            check($('#dialog-prompt-subtotal #subtotal-manage-freetext').length === 1, 'Rendu réel du hook et bouton natif');
            $('#dialog-prompt-subtotal').dialog('close').dialog('destroy').remove();
            for (var mode = 0; mode < 2; mode++) {
                $('#rich').prop('checked', !!mode); open();
                var outer = $('#dialog-prompt-subtotal');
                await until(function () { return outer.find('[name=free_text] option').length > 2 && (!mode || CKEDITOR.instances['sub-total-title'] && CKEDITOR.instances['sub-total-title'].status === 'ready'); });
                await sleep(100);
                check(outer.find('select.select2-hidden-accessible').length === 2, 'Deux Select2 natifs indépendants — mode ' + mode);
                outer.find('[name=under_title]').val('1').trigger('change');
                value('sub-total-title', '<p>Description personnalisée</p>'); await sleep(80);
                outer.find('#subtotal-manage-freetext').trigger('click');
                var manager = $('.subtotal-freetext-manager');
                await until(function () { return manager.find('form').length === 2 && (!mode || CKEDITOR.instances.subtotal_freetext_content && CKEDITOR.instances.subtotal_freetext_content.status === 'ready'); });
                check(manager.find('#limit').closest('form').length === 1, 'Pagination dans son formulaire natif');
                check(!mode || Object.keys(CKEDITOR.instances).length === 2, 'Deux éditeurs indépendants');
                manager.find('[name=label]').val('Nouveau modèle ' + mode);
                value('subtotal_freetext_content', '<p>Modèle <strong>nouveau</strong></p>'); await sleep(80);
                var before = writes;
                manager.find('.subtotal-freetext-form').trigger('submit').trigger('submit');
                await until(function () { return Number(manager.find('[name=id]').val()) >= 26 && !manager.find('[name=label]').prop('disabled'); });
                check(writes === before + 1, 'Double soumission empêchée');
                check(manager.is(':visible'), 'Modale ouverte après enregistrement');
                if (mode) await until(function () { return CKEDITOR.instances.subtotal_freetext_content && CKEDITOR.instances.subtotal_freetext_content.status === 'ready'; });
                var savedSignature = manager.find('[name=signature]').val();
                manager.find('[name=label]').val('Modèle modifié ' + mode);
                manager.find('.subtotal-freetext-active').trigger('click');
                manager.find('.subtotal-freetext-form').trigger('submit');
                await until(function () { return manager.find('[name=signature]').val() !== savedSignature && !manager.find('[name=label]').prop('disabled'); });
                check(manager.find('[name=active]').val() === '0' && manager.find('[name=label]').val() === 'Modèle modifié ' + mode, 'Modification et désactivation dans le formulaire');
                if (mode) await until(function () { return CKEDITOR.instances.subtotal_freetext_content && CKEDITOR.instances.subtotal_freetext_content.status === 'ready'; });
                savedSignature = manager.find('[name=signature]').val();
                manager.find('.subtotal-freetext-active').trigger('click'); manager.find('.subtotal-freetext-form').trigger('submit');
                await until(function () { return manager.find('[name=signature]').val() !== savedSignature && !manager.find('[name=label]').prop('disabled'); });
                check(manager.find('[name=active]').val() === '1', 'Réactivation du modèle');
                if (mode) await until(function () { return CKEDITOR.instances.subtotal_freetext_content && CKEDITOR.instances.subtotal_freetext_content.status === 'ready'; });
                manager.dialog('close'); await sleep(100);
                check(!$('.subtotal-freetext-manager').length, 'Fermeture sans faux avertissement après sauvegarde');
                check(read('sub-total-title').indexOf('personnalisée') !== -1 && outer.find('[name=under_title]').val() === '1', 'Bloc et description conservés');
                check(!outer.find('[name=free_text]').val(), 'Aucun nouveau texte sélectionné automatiquement');
                outer.find('[name=free_text]').val(String(nextId - 1)).trigger('change'); await sleep(100);
                check(read('sub-total-title').indexOf('nouveau') !== -1, 'Sélection explicite charge le modèle');
                value('sub-total-title', '<p>Modification locale</p>'); await sleep(80);
                outer.find('[name=free_text]').select2('open');
                outer.find('[name=free_text]').parent().find('.select2-results__option[aria-selected=true]').trigger('mouseup');
                await sleep(100);
                check(read('sub-total-title').indexOf('nouveau') !== -1, 'Resélection explicite du même modèle');
                value('sub-total-title', '<p>HTML <strong>final personnalisé</strong></p>'); await sleep(80);
                failNext = true; button(outer, 'OK').trigger('click'); await sleep(100);
                check(outer.is(':visible') && read('sub-total-title').indexOf('final personnalisé') !== -1 && outer.find('.error').is(':visible'), 'Erreur serveur conserve la saisie');
                check(fixtures.config.token === serverToken, 'Jeton Ajax actualisé après erreur');
                button(outer, 'OK').trigger('click'); await until(function () { return !$('#dialog-prompt-subtotal').length; });
                check(lastInsert.indexOf('<strong>final personnalisé</strong>') !== -1, 'Seul le HTML final est inséré');
                check(!Object.keys(CKEDITOR.instances).length, 'Instances détruites à la fermeture');
            }
            $('#rich').prop('checked', false); open(); await sleep(100);
            var outer2 = $('#dialog-prompt-subtotal');
            outer2.find('[name=free_text]').val('1').trigger('change');
            value('sub-total-title', '<p>À conserver</p>');
            outer2.find('#subtotal-manage-freetext').trigger('click'); await sleep(100);
            var manager2 = $('.subtotal-freetext-manager');
            manager2.find('[name=label]').val('Non enregistré'); manager2.dialog('close'); await sleep(50);
            check($('.subtotal-freetext-manager').length === 1 && $('.ui-dialog:visible').length === 3, 'Confirmation d’abandon requise');
            answer(fixtures.config.labels.No); await sleep(50);
            check(manager2.find('[name=label]').val() === 'Non enregistré', 'Refus d’abandon conserve le formulaire');
            manager2.dialog('close'); await sleep(30); answer(fixtures.config.labels.Yes); await sleep(100);
            outer2.find('#subtotal-manage-freetext').trigger('click'); await sleep(100);
            manager2 = $('.subtotal-freetext-manager');
            manager2.find('[name=search_label]').val('slow'); manager2.find('.subtotal-freetext-search').trigger('submit');
            manager2.find('[name=search_label]').val('absent'); manager2.find('.subtotal-freetext-search').trigger('submit');
            await sleep(400);
            check(manager2.find('[name=search_label]').val() === 'absent' && !manager2.find('[data-id]').length, 'Réponse obsolète ignorée et état vide natif');
            manager2.find('.button_removefilter').trigger('click'); await sleep(100);
            check(manager2.find('[data-id]').length > 0, 'Réinitialisation native des filtres');
            manager2.find('.paginationnext').trigger('click'); await sleep(100);
            check(manager2.find('[data-id="21"]').length === 1 && !manager2.find('[data-id="2"]').length, 'Lien de pagination natif traité par Ajax');
            manager2.find('.button_removefilter').trigger('click'); await sleep(100);
            manager2.find('[data-id="1"] .subtotal-freetext-toggle').trigger('click'); await sleep(100);
            manager2.dialog('close'); await sleep(100);
            check(!outer2.find('[name=free_text]').val() && read('sub-total-title') === '<p>À conserver</p>', 'Modèle inactivé : choix vidé, description conservée');
            outer2.find('[name=free_text]').val('2').trigger('change'); value('sub-total-title', '<p>À conserver</p>');
            outer2.find('#subtotal-manage-freetext').trigger('click'); await sleep(100);
            manager2 = $('.subtotal-freetext-manager');
            before = writes;
            manager2.find('[data-id="2"] .subtotal-freetext-delete').trigger('click'); await sleep(40);
            check(writes === before, 'Aucune suppression avant confirmation');
            answer(fixtures.config.labels.Yes); await sleep(100);
            manager2.dialog('close'); await sleep(100);
            check(!outer2.find('[name=free_text]').val() && read('sub-total-title') === '<p>À conserver</p>', 'Modèle supprimé : choix vidé, description conservée');
            outer2.dialog('close');
            output.append('SUCCÈS : ' + count + ' contrôles sur composants natifs, Ajax simulé.');
        } catch (error) { output.append('ÉCHEC : ' + error.message); throw error; }
    });
})(jQuery);
