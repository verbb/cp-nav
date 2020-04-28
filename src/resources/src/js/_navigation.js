(function($) {

if (typeof Craft.CpNav === typeof undefined) {
    Craft.CpNav = {};
}



// ----------------------------------------
// FETCH NAVS FOR LAYOUT WHEN CHANGING SELECT
// ----------------------------------------

$(document).on('change', '.layout-select select', function() {
    $(this).addClass('loading');

    window.location.href = Craft.getUrl('cp-nav?layoutId=' + $(this).val());
});



// ----------------------------------------
// ADDING NEW MENU ITEM MODAL
// ----------------------------------------

$(document).on('click', '.add-new-menu-item', function(e) {
    new Craft.CpNav.AddMenuItem($(this));
});


// ----------------------------------------
// SIDEBAR NOTIFICATION
// ----------------------------------------

var $notification = $('.cp-nav-notice');

if ($notification.length) {
    $notification.remove();

    $notification.insertAfter('#global-sidebar #system-info').addClass('shown');
}



// ----------------------------------------
// NEW MENU ITEM MODAL
// ----------------------------------------

Craft.CpNav.AddMenuItem = Garnish.Base.extend({
    $element: null,
    data: null,
    navId: null,

    $form: null,
    $cancelBtn: null,
    $saveBtn: null,
    $spinner: null,

    hud: null,

    init: function($element, $data) {
        this.$element = $element;

        var type = $element.data('type');

        this.data = {
            layoutId: $('.layout-select select').val(),
        };

        this.$element.addClass('loading');

        if (type == 'divider') {
            this.addDivider();
        } else {
            Craft.postActionRequest('cp-nav/navigation/get-hud-html', this.data, $.proxy(this, 'showHud'));
        }
    },

    showHud: function(response, textStatus) {
        this.$element.removeClass('loading');

        if (textStatus === 'success') {
            var $hudContents = $();

            this.$form = $('<div/>');
            $fieldsContainer = $('<div class="fields"/>').appendTo(this.$form);

            $fieldsContainer.html(response.bodyHtml)
            Craft.initUiElements($fieldsContainer);

            var $footer = $('<div class="hud-footer"/>').appendTo(this.$form),
                $buttonsContainer = $('<div class="buttons right"/>').appendTo($footer);
            this.$cancelBtn = $('<div class="btn">'+Craft.t('app', 'Cancel')+'</div>').appendTo($buttonsContainer);
            this.$saveBtn = $('<input class="btn submit" type="submit" value="'+Craft.t('app', 'Save')+'"/>').appendTo($buttonsContainer);
            this.$spinner = $('<div class="spinner hidden"/>').appendTo($buttonsContainer);

            $hudContents = $hudContents.add(this.$form);

            this.hud = new Garnish.HUD(this.$element, $hudContents, {
                bodyClass: 'body',
                closeOtherHUDs: false
            });

            this.hud.on('hide', $.proxy(this, 'closeHud'));

            new Craft.HandleGenerator('#currLabel', '#handle');

            Garnish.$bod.append(response.footHtml);

            this.addListener(this.$saveBtn, 'click', 'saveGroupField');
            this.addListener(this.$cancelBtn, 'click', 'closeHud');
        }
    },

    addDivider: function() {
        this.data.type = 'divider';
        this.data.handle = 'divider';
        this.data.currLabel = Craft.t('cp-nav', 'Divider');

        Craft.postActionRequest('cp-nav/navigation/new', this.data, $.proxy(function(response, textStatus) {
            if (textStatus === 'success' && response.success) {
                Craft.cp.displayNotice(Craft.t('cp-nav', 'Menu saved.'));

                updateAllNav(response.navHtml);
                this.addNavItem(response.nav);
            } else {
                Craft.cp.displayError(response.error);
            }
        }, this));
    },

    saveGroupField: function(ev) {
        ev.preventDefault();

        this.$spinner.removeClass('hidden');

        var data = this.hud.$body.serialize();

        Craft.postActionRequest('cp-nav/navigation/new', data, $.proxy(function(response, textStatus) {
            this.$spinner.addClass('hidden');

            if (textStatus === 'success' && response.success) {
                Craft.cp.displayNotice(Craft.t('cp-nav', 'Menu saved.'));

                updateAllNav(response.navHtml);
                this.addNavItem(response.nav);

                this.closeHud();
            } else {
                Craft.cp.displayError(response.error);
                Garnish.shake(this.hud.$hud);
            }
        }, this));
    },

    closeHud: function() {
        this.hud.$shade.remove();
        this.hud.$hud.remove();
    },

    addNavItem: function(newMenuItem) {
        var $tr = AdminTable.addRow('<tr class="nav-item" data-id="' + newMenuItem.id + '" data-currlabel="' + newMenuItem.currLabel + '" data-name="' + newMenuItem.currLabel + '">' + 
            '<td class="thin">' + 
                '<div class="field">' + 
                    '<div class="input ltr">' + 
                        '<div class="lightswitch on" tabindex="0">' + 
                            '<div class="lightswitch-container">' + 
                                '<div class="label on"></div>' + 
                                '<div class="handle"></div>' + 
                                '<div class="label off"></div>' + 
                            '</div>' + 
                            '<input type="hidden" name="navEnabled" value="1">' + 
                        '</div>' + 
                    '</div>' + 
                '</div>' + 
            '</td>' +

            '<td data-title="' + newMenuItem.currLabel + '">' + 
                '<a class="move icon" title="' + Craft.t('app', 'Reorder') + '" role="button"></a>' + 
                '<a class="edit-nav">' + newMenuItem.currLabel + '</a>' + 
                '<span class="original-nav">(' + newMenuItem.currLabel + ')</span>' + 
            '</td>' + 
            
            '<td data-title="' + newMenuItem.currLabel + '">' + 
                '<span class="original-nav-link">' + ((newMenuItem.url) ? newMenuItem.url : '') + '</span>' + 
            '</td>' + 
            
            '<td>' + 
                '<div class="nav-type">' + 
                    '<span class="nav-type-' + newMenuItem.type + '">' + newMenuItem.type + '</span>' + 
                '</div>' + 
            '</td>' + 
            
            '<td class="thin">' + 
                '<a class="delete icon" title="' + Craft.t('app', 'Delete') + '" role="button"></a>' + 
            '</td>' + 
        '</tr>');

        Craft.initUiElements($tr);
    },
});



// ----------------------------------------
// WHEN TOGGLING A LIGHTSWITCH, TRIGGER REQUEST
// ----------------------------------------

$(document).on('change', '#navItems .lightswitch', function() {
    var row = $(this).parents('tr.nav-item')
    var val = $(this).find('input:first').val();
    val = (!val) ? 0 : 1;

    var data = {
        value: val,
        id: row.data('id'),
        layoutId: $('.layout-select select').val(),
    }

    Craft.postActionRequest('cp-nav/navigation/toggle', data, $.proxy(function(response, textStatus) {
        if (textStatus === 'success' && response.success) {
            Craft.cp.displayNotice(Craft.t('app', 'Status saved.'));

            updateAllNav(response.navHtml);
        }
    }));
});



// ----------------------------------------
// WHEN CLICKING ON A MENU ITEM, ALLOW HUD TO EDIT
// ----------------------------------------

$(document).on('click', 'tr.nav-item a.edit-nav', function(e) {
    new Craft.CpNav.EditNavItem($(this), $(this).parents('tr.nav-item'));
});



// ----------------------------------------
// HUD FOR EDITING MENU
// ----------------------------------------

Craft.CpNav.EditNavItem = Garnish.Base.extend({
    $element: null,
    data: null,
    navId: null,

    $form: null,
    $cancelBtn: null,
    $saveBtn: null,
    $spinner: null,

    hud: null,

    init: function($element, $data) {
        this.$element = $element;

        this.data = {
            id: $data.data('id'),
            currLabel: $data.data('currlabel'),
            layoutId: $('.layout-select select').val(),
        }

        this.$element.addClass('loading');

        Craft.postActionRequest('cp-nav/navigation/get-hud-html', this.data, $.proxy(this, 'showHud'));
    },

    showHud: function(response, textStatus) {
        this.$element.removeClass('loading');

        if (textStatus === 'success') {
            var $hudContents = $();

            this.$form = $('<div/>');
            $fieldsContainer = $('<div class="fields"/>').appendTo(this.$form);

            $fieldsContainer.html(response.bodyHtml)
            Craft.initUiElements($fieldsContainer);

            var $footer = $('<div class="hud-footer"/>').appendTo(this.$form),
                $buttonsContainer = $('<div class="buttons right"/>').appendTo($footer);
            this.$cancelBtn = $('<div class="btn">'+Craft.t('app', 'Cancel')+'</div>').appendTo($buttonsContainer);
            this.$saveBtn = $('<input class="btn submit" type="submit" value="'+Craft.t('app', 'Save')+'"/>').appendTo($buttonsContainer);
            this.$spinner = $('<div class="spinner hidden"/>').appendTo($buttonsContainer);

            $hudContents = $hudContents.add(this.$form);

            this.hud = new Garnish.HUD(this.$element, $hudContents, {
                bodyClass: 'body',
                closeOtherHUDs: false
            });

            this.hud.on('hide', $.proxy(this, 'closeHud'));

            Garnish.$bod.append(response.footHtml);

            this.addListener(this.$saveBtn, 'click', 'saveGroupField');
            this.addListener(this.$cancelBtn, 'click', 'closeHud');
        }
    },

    saveGroupField: function(ev) {
        ev.preventDefault();

        this.$spinner.removeClass('hidden');

        var data = this.hud.$body.serialize();

        Craft.postActionRequest('cp-nav/navigation/save', data, $.proxy(function(response, textStatus) {
            this.$spinner.addClass('hidden');

            if (textStatus === 'success' && response.success) {
                this.$element.html(response.nav.currLabel);
                this.$element.parents('tr.nav-item').find('.original-nav-link').html(response.nav.url);

                Craft.cp.displayNotice(Craft.t('app', 'Menu saved.'));

                this.closeHud();

                updateAllNav(response.navHtml);
            } else {
                Craft.cp.displayError(response.error);
                Garnish.shake(this.hud.$hud);
            }
        }, this));
    },

    closeHud: function() {
        this.hud.$shade.remove();
        this.hud.$hud.remove();
    }
});



// ----------------------------------------
// EXTEND BUILT-IN ADMINTABLE TO ALLOW US TO HOOK INTO REORDEROBJECTS
// ----------------------------------------

// Kinda annoying, but there's no other way to hook into the success of the re-ordering
Craft.CpNav.AlternateAdminTable = Craft.AdminTable.extend({

    // Override the default reorderItems to update navs from the response
    reorderItems: function() {
        if (!this.settings.sortable) {
            return;
        }

        // Get the new field order
        var ids = [];

        for (var i = 0; i < this.sorter.$items.length; i++) {
            var id = $(this.sorter.$items[i]).attr(this.settings.idAttribute);
            ids.push(id);
        }

        // Send it to the server
        var data = {
            ids: JSON.stringify(ids),
            layoutId: $('.layout-select select').val(),
        };

        Craft.postActionRequest(this.settings.reorderAction, data, $.proxy(function(response, textStatus) {
            if (textStatus === 'success') {
                if (response.success) {
                    this.onReorderItems(ids);
                    Craft.cp.displayNotice(Craft.t('app', this.settings.reorderSuccessMessage));

                    updateAllNav(response.navHtml);
                } else {
                    Craft.cp.displayError(Craft.t('app', this.settings.reorderFailMessage));
                }
            }

        }, this));
    },

    // Override the default deleteItem to supply our LayoutId
    deleteItem: function($row) {
        var data = {
            id: this.getItemId($row),
            layoutId: $('.layout-select select').val(),
        };

        Craft.postActionRequest(this.settings.deleteAction, data, $.proxy(function(response, textStatus) {
            if (textStatus === 'success') {
                this.handleDeleteItemResponse(response, $row);

                updateAllNav(response.navHtml);
            }
        }, this));
    },

});




// ----------------------------------------
// CREATE THE ADMIN TABLE
// ----------------------------------------

var AdminTable = new Craft.CpNav.AlternateAdminTable({
    tableSelector: '#navItems',
    newObjectBtnSelector: '#newNavItem',
    sortable: true,
    reorderAction: 'cp-nav/navigation/reorder',
    deleteAction: 'cp-nav/navigation/delete',
});




// ----------------------------------------
// FUNCTIONS TO ASSIST WITH UPDATING THE CP NAV CLIENT-SIDE
// ----------------------------------------
// var badgeHandleIndex = {};
var updateAllNav = function(navHtml) {
    $('#global-sidebar nav#nav ul').html(navHtml);

    // Refresh any JS modifications
    new Craft.CpNav.ModifyItems();
}


})(jQuery);


