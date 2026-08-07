(function($) {

if (typeof Craft.CpNav === typeof undefined) {
    Craft.CpNav = {};
}

// Craft.AdminTable uses global `$`, not the IIFE parameter.
if (typeof window.jQuery === 'function') {
    window.$ = window.jQuery;
}


// ----------------------------------------
// LAYOUTS
// ----------------------------------------

var LayoutAdminTable = null;

if ($('#layoutItems').length) {
    LayoutAdminTable = new Craft.AdminTable({
        tableSelector: '#layoutItems',
        sortable: true,
        reorderAction: 'cp-nav/layout/reorder',
        deleteAction: 'cp-nav/layout/delete',
        confirmDeleteMessage: Craft.t('cp-nav', 'Are you sure you want to permanently delete this layout and all its settings? This cannot be undone.'),
    });
}



// ----------------------------------------
// WHEN CLICKING ON A LAYOUT ITEM, ALLOW HUD TO EDIT
// ----------------------------------------

$(document).on('click', 'tr.layout-item a.edit-layout', function(e) {
    new Craft.CpNav.EditLayoutItem($(this), $(this).parents('tr.layout-item'));
});

// ----------------------------------------
// DUPLICATE LAYOUT
// ----------------------------------------

$(document).on('click', 'tr.layout-item a.duplicate', function(e) {
    e.preventDefault();

    var $row = $(this).closest('tr.layout-item');
    var id = $row.data('id');
    var name = $row.data('name');

    Craft.sendActionRequest('POST', 'cp-nav/layout/duplicate', {
        data: {
            id: id,
            name: Craft.t('cp-nav', '{name} copy', { name: name }),
        },
    })
        .then((response) => {
            Craft.cp.displayNotice(response.data.message);

            var layout = response.data.layout;
            var moveCell = '';

            if ($('#layoutItems .move').length) {
                moveCell = '<td class="thin">' +
                    '<a class="move icon" title="' + Craft.t('app', 'Reorder') + '" role="button"></a>' +
                    '</td>';
            }

            LayoutAdminTable.addRow('<tr class="layout-item" data-id="' + layout.id + '" data-name="' + Craft.escapeHtml(layout.name) + '">' +
                '<td>' +
                    '<a class="edit-layout"><strong>' + Craft.escapeHtml(layout.name) + '</strong></a>' +
                '</td>' +
                moveCell +
                '<td class="thin">' +
                    '<a class="duplicate icon" title="' + Craft.t('app', 'Duplicate') + '" role="button"></a>' +
                    '<a class="delete icon" title="' + Craft.t('app', 'Delete') + '" role="button"></a>' +
                '</td>' +
            '</tr>');
        })
        .catch(({response}) => {
            if (response && response.data && response.data.message) {
                Craft.cp.displayError(response.data.message);
            } else {
                Craft.cp.displayError();
            }
        });
});

// ----------------------------------------
// HUD FOR EDITING LAYOUT
// ----------------------------------------

Craft.CpNav.EditLayoutItem = Garnish.Base.extend({
    $element: null,
    data: null,
    layoutId: null,

    $form: null,

    hud: null,

    init: function($element, $data) {
        this.$element = $element;

        this.data = {
            id: $data.data('id'),
        }

        this.$element.addClass('loading');

        this.$spinner = $('<div class="spinner small" />');
        this.$element.append(this.$spinner);

        Craft.sendActionRequest('POST', 'cp-nav/layout/get-hud-html', { data: this.data })
            .then((response) => {
                this.showHud(response);
            });
    },

    showHud: function(response) {
        this.$element.removeClass('loading');

        var $hudContents = $();

        this.$spinner.remove();

        this.$form = $('<div/>');
        var $fieldsContainer = $('<div class="fields"/>').appendTo(this.$form);

        $fieldsContainer.html(response.data.html)
        Craft.initUiElements($fieldsContainer);

        var $footer = $('<div class="hud-footer flex"/>').appendTo(this.$form);
        $('<div class="flex-grow"/>').appendTo($footer);
        
        this.$cancelBtn = $('<button/>', {
            type: 'button',
            class: 'btn',
            text: Craft.t('app', 'Cancel'),
        }).appendTo($footer);

        this.$saveBtn = Craft.ui.createSubmitButton({
            label: Craft.t('app', 'Save'),
            spinner: true,
        }).appendTo($footer);

        $hudContents = $hudContents.add(this.$form);

        this.hud = new Garnish.HUD(this.$element, $hudContents, {
            bodyClass: 'body',
            closeOtherHUDs: false
        });

        this.hud.on('hide', $.proxy(this, 'closeHud'));

        Garnish.$bod.append(response.data.footerJs);

        this.$form.find('input:first').focus();

        this.addListener(this.$saveBtn, 'click', 'save');
        this.addListener(this.$cancelBtn, 'click', 'closeHud');
    },

    save: function(ev) {
        ev.preventDefault();

        this.$saveBtn.addClass('loading');

        var data = this.hud.$body.serialize();

        Craft.sendActionRequest('POST', 'cp-nav/layout/save', { data })
            .then((response) => {
                this.$element.html('<strong>' + response.data.layout.name + '</strong>');

                this.closeHud();
                Craft.cp.displayNotice(response.data.message);
            })
            .catch(({response}) => {
                Garnish.shake(this.hud.$hud);

                if (response && response.data && response.data.message) {
                    Craft.cp.displayError(response.data.message);
                } else {
                    Craft.cp.displayError();
                }
            })
            .finally(() => {
                this.$saveBtn.removeClass('loading');
            });
    },

    closeHud: function() {
        this.hud.$shade.remove();
        this.hud.$hud.remove();
    }
});





// ----------------------------------------
// ALLOW HUD TO ADD LAYOUT
// ----------------------------------------

$(document).on('click', '.add-new-layout', function(e) {
    e.preventDefault();
    new Craft.CpNav.CreateLayoutItem($(this));
});

// ----------------------------------------
// HUD FOR EDITING LAYOUT
// ----------------------------------------

Craft.CpNav.CreateLayoutItem = Garnish.Base.extend({
    $element: null,
    data: null,
    layoutId: null,

    $form: null,

    hud: null,

    init: function($element) {
        this.$element = $element;

        this.$element.addClass('loading');

        Craft.sendActionRequest('POST', 'cp-nav/layout/get-hud-html', { })
            .then((response) => {
                this.showHud(response);
            });
    },

    showHud: function(response) {
        this.$element.removeClass('loading');

        var $hudContents = $();

        this.$form = $('<div/>');
        var $fieldsContainer = $('<div class="fields"/>').appendTo(this.$form);

        $fieldsContainer.html(response.data.html)
        Craft.initUiElements($fieldsContainer);

        var $footer = $('<div class="hud-footer flex"/>').appendTo(this.$form);
        $('<div class="flex-grow"/>').appendTo($footer);

        this.$cancelBtn = $('<button/>', {
            type: 'button',
            class: 'btn',
            text: Craft.t('app', 'Cancel'),
        }).appendTo($footer);

        this.$saveBtn = Craft.ui.createSubmitButton({
            label: Craft.t('app', 'Save'),
            spinner: true,
        }).appendTo($footer);

        $hudContents = $hudContents.add(this.$form);

        this.hud = new Garnish.HUD(this.$element, $hudContents, {
            bodyClass: 'body',
            closeOtherHUDs: false
        });

        this.hud.on('hide', $.proxy(this, 'closeHud'));

        Garnish.$bod.append(response.data.footerJs);

        this.$form.find('input:first').focus();

        this.addListener(this.$saveBtn, 'click', 'save');
        this.addListener(this.$cancelBtn, 'click', 'closeHud');
    },

    save: function(ev) {
        ev.preventDefault();

        this.$saveBtn.addClass('loading');

        var data = this.hud.$body.serialize();

        Craft.sendActionRequest('POST', 'cp-nav/layout/new', { data })
            .then((response) => {
                Craft.cp.displayNotice(response.data.message);

                var newLayout = response.data.layout;

                LayoutAdminTable.addRow('<tr class="layout-item" data-id="' + newLayout.id + '" data-name="' + Craft.escapeHtml(newLayout.name) + '">' +
                    '<td>' +
                        '<a class="edit-layout"><strong>' + Craft.escapeHtml(newLayout.name) + '</strong></a>' +
                    '</td>' +
                    '<td class="thin">' +
                        '<a class="move icon" title="' + Craft.t('app', 'Reorder') + '" role="button"></a>' +
                    '</td>' +
                    '<td class="thin">' +
                        '<a class="duplicate icon" title="' + Craft.t('app', 'Duplicate') + '" role="button"></a>' +
                        '<a class="delete icon" title="' + Craft.t('app', 'Delete') + '" role="button"></a>' +
                    '</td>' +
                '</tr>');

                this.closeHud();
            })
            .catch(({response}) => {
                Garnish.shake(this.hud.$hud);

                if (response && response.data && response.data.message) {
                    Craft.cp.displayError(response.data.message);
                } else {
                    Craft.cp.displayError();
                }
            })
            .finally(() => {
                this.$saveBtn.removeClass('loading');
            });
    },

    closeHud: function() {
        this.hud.$shade.remove();
        this.hud.$hud.remove();
    }
});


})(jQuery);
