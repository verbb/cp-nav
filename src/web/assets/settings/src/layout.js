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




// Guard every HUD dismissal path, including Garnish's Escape and shade handlers.
Craft.CpNav.LayoutHUD = Garnish.HUD.extend({
    hide: function() {
        if (!this.settings.isSaving()) {
            this.base();
        }
    },
});

// Keep the same compact move control accessible without a pointer.
$(document).on('keydown', '#layoutItems .move', function(event) {
    if (!['ArrowUp', 'ArrowDown'].includes(event.key)) {
        return;
    }
    event.preventDefault();
    const $row = $(this).closest('tr');
    const $sibling = event.key === 'ArrowUp' ? $row.prev('tr') : $row.next('tr');
    if (!$sibling.length || LayoutAdminTable.keyboardReordering) {
        return;
    }
    const previousRows = LayoutAdminTable.$tbody.children('tr').toArray();
    LayoutAdminTable.keyboardReordering = true;
    LayoutAdminTable.sorter?.disable();
    if (event.key === 'ArrowUp') {
        $row.insertBefore($sibling);
    } else {
        $row.insertAfter($sibling);
    }
    this.focus();
    const ids = LayoutAdminTable.getRowOrder();
    Craft.sendActionRequest('POST', 'cp-nav/layout/reorder', {data: {ids: JSON.stringify(ids)}})
        .then(() => {
            LayoutAdminTable.onReorderItems(ids);
            Craft.cp.displaySuccess(Craft.t('app', LayoutAdminTable.settings.reorderSuccessMessage));
        })
        .catch(() => {
            previousRows.filter((row) => row.isConnected).forEach((row) => LayoutAdminTable.$tbody.append(row));
            this.focus();
            Craft.cp.displayError(Craft.t('app', LayoutAdminTable.settings.reorderFailMessage));
        })
        .finally(() => {
            LayoutAdminTable.keyboardReordering = false;
            LayoutAdminTable.sorter?.enable();
        });
});

// ----------------------------------------
// WHEN CLICKING ON A LAYOUT ITEM, ALLOW HUD TO EDIT
// ----------------------------------------

$(document).on('click', 'tr.layout-item button.edit-layout', function(e) {
    e.preventDefault();
    if ($(this).hasClass('loading')) {
        return;
    }
    new Craft.CpNav.EditLayoutItem($(this), $(this).parents('tr.layout-item'));
});

// ----------------------------------------
// DUPLICATE LAYOUT
// ----------------------------------------

$(document).on('click', 'tr.layout-item .duplicate', function(e) {
    e.preventDefault();

    const $button = $(this);
    if ($button.prop('disabled')) {
        return;
    }
    $button.prop('disabled', true).addClass('loading');

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
                moveCell = '<td class="thin layout-sort-cell">' +
                    '<button type="button" class="move icon layout-control" aria-label="' + Craft.t('app', 'Reorder') + '" title="' + Craft.t('cp-nav', 'Reorder (Up/Down arrow keys)') + '"></button>' +
                    '</td>';
            }

            LayoutAdminTable.addRow('<tr class="layout-item" data-id="' + layout.id + '" data-name="' + Craft.escapeHtml(layout.name) + '">' +
                '<td>' +
                    '<button type="button" class="edit-layout"><strong>' + Craft.escapeHtml(layout.name) + '</strong></button>' +
                '</td>' +
                moveCell +
                '<td class="thin layout-actions-cell"><div class="layout-actions">' +
                    '<button type="button" class="duplicate icon layout-control" data-icon="files" title="' + Craft.escapeHtml(Craft.t('app', 'Duplicate')) + '" aria-label="' + Craft.escapeHtml(Craft.t('app', 'Duplicate')) + '"></button>' +
                    '<button type="button" class="delete icon layout-control" title="' + Craft.t('app', 'Delete') + '" aria-label="' + Craft.t('app', 'Delete') + '"></button>' +
                '</div>' +
                '</td>' +
            '</tr>');
        })
        .catch(({response}) => {
            if (response && response.data && response.data.message) {
                Craft.cp.displayError(response.data.message);
            } else {
                Craft.cp.displayError();
            }
        })
        .finally(() => {
            $button.prop('disabled', false).removeClass('loading');
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
    saving: false,

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
            })
            .catch((error) => {
                Craft.cp.displayError(error.response?.data?.message);
            })
            .finally(() => {
                this.$element.removeClass('loading');
                this.$spinner.remove();
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

        this.hud = new Craft.CpNav.LayoutHUD(this.$element, $hudContents, {
            bodyClass: 'body',
            closeOtherHUDs: false,
            isSaving: () => this.saving
        });

        this.hud.on('hide', () => {
            this.hud.destroy();
            this.destroy();
        });

        Garnish.$bod.append(response.data.footerJs);

        this.$form.find('input:first').focus();

        this.addListener(this.$saveBtn, 'click', 'save');
        this.addListener(this.$cancelBtn, 'click', 'closeHud');
    },

    save: function(ev) {
        ev.preventDefault();

        // A second click must not submit a competing save while the first is pending.
        if (this.saving) {
            return;
        }
        this.saving = true;
        this.$saveBtn.prop('disabled', true).addClass('loading');
        this.$cancelBtn.prop('disabled', true);

        var data = this.hud.$body.serialize();

        Craft.sendActionRequest('POST', 'cp-nav/layout/save', { data })
            .then((response) => {
                var name = response.data.layout.name;
                this.$element.empty().append($('<strong/>').text(name));
                this.$element.closest('tr.layout-item').attr('data-name', name).data('name', name);

                this.saving = false;
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
                this.saving = false;
                this.$saveBtn.prop('disabled', false).removeClass('loading');
                this.$cancelBtn.prop('disabled', false);
            });
    },

    closeHud: function() {
        this.hud.hide();
    }
});





// ----------------------------------------
// ALLOW HUD TO ADD LAYOUT
// ----------------------------------------

$(document).on('click', '.add-new-layout', function(e) {
    e.preventDefault();
    if ($(this).hasClass('loading')) {
        return;
    }
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
    saving: false,

    init: function($element) {
        this.$element = $element;

        this.$element.addClass('loading');

        Craft.sendActionRequest('POST', 'cp-nav/layout/get-hud-html', { })
            .then((response) => {
                this.showHud(response);
            })
            .catch((error) => {
                Craft.cp.displayError(error.response?.data?.message);
            })
            .finally(() => {
                this.$element.removeClass('loading');
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

        this.hud = new Craft.CpNav.LayoutHUD(this.$element, $hudContents, {
            bodyClass: 'body',
            closeOtherHUDs: false,
            isSaving: () => this.saving
        });

        this.hud.on('hide', () => {
            this.hud.destroy();
            this.destroy();
        });

        Garnish.$bod.append(response.data.footerJs);

        this.$form.find('input:first').focus();

        this.addListener(this.$saveBtn, 'click', 'save');
        this.addListener(this.$cancelBtn, 'click', 'closeHud');
    },

    save: function(ev) {
        ev.preventDefault();

        // Creation has no saved ID to deduplicate repeated requests on the server.
        if (this.saving) {
            return;
        }
        this.saving = true;
        this.$saveBtn.prop('disabled', true).addClass('loading');
        this.$cancelBtn.prop('disabled', true);

        var data = this.hud.$body.serialize();

        Craft.sendActionRequest('POST', 'cp-nav/layout/new', { data })
            .then((response) => {
                Craft.cp.displayNotice(response.data.message);

                var newLayout = response.data.layout;

                LayoutAdminTable.addRow('<tr class="layout-item" data-id="' + newLayout.id + '" data-name="' + Craft.escapeHtml(newLayout.name) + '">' +
                    '<td>' +
                        '<button type="button" class="edit-layout"><strong>' + Craft.escapeHtml(newLayout.name) + '</strong></button>' +
                    '</td>' +
                    '<td class="thin layout-sort-cell">' +
                        '<button type="button" class="move icon layout-control" aria-label="' + Craft.t('app', 'Reorder') + '" title="' + Craft.t('cp-nav', 'Reorder (Up/Down arrow keys)') + '"></button>' +
                    '</td>' +
                    '<td class="thin layout-actions-cell"><div class="layout-actions">' +
                        '<button type="button" class="duplicate icon layout-control" data-icon="files" title="' + Craft.escapeHtml(Craft.t('app', 'Duplicate')) + '" aria-label="' + Craft.escapeHtml(Craft.t('app', 'Duplicate')) + '"></button>' +
                        '<button type="button" class="delete icon layout-control" title="' + Craft.t('app', 'Delete') + '" aria-label="' + Craft.t('app', 'Delete') + '"></button>' +
                    '</div>' +
                    '</td>' +
                '</tr>');

                this.saving = false;
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
                this.saving = false;
                this.$saveBtn.prop('disabled', false).removeClass('loading');
                this.$cancelBtn.prop('disabled', false);
            });
    },

    closeHud: function() {
        this.hud.hide();
    }
});


})(jQuery);
