(function($) {

if (typeof Craft.CpNav === typeof undefined) {
    Craft.CpNav = {};
}



// ----------------------------------------
// FETCH NAVS FOR LAYOUT WHEN CHANGING SELECT
// ----------------------------------------

$(document).on('change', 'select#layoutId', function() {
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

        this.data = {
            layoutId: $('select#layoutId').val(),
        };

        this.$element.addClass('loading');

        Craft.postActionRequest('cp-nav/navigation/get-hud-html', this.data, $.proxy(this, 'showHud'));
    },

    showHud: function(response, textStatus) {
        this.$element.removeClass('loading');

        if (textStatus === 'success') {
            var $hudContents = $();

            this.$form = $('<div/>');
            $fieldsContainer = $('<div class="fields"/>').appendTo(this.$form);

            $fieldsContainer.html(response.html)
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

            Garnish.$bod.append(response.footerJs);

            this.addListener(this.$saveBtn, 'click', 'saveGroupField');
            this.addListener(this.$cancelBtn, 'click', 'closeHud');
        }
    },

    saveGroupField: function(ev) {
        ev.preventDefault();

        this.$spinner.removeClass('hidden');

        var data = this.hud.$body.serialize();

        Craft.postActionRequest('cp-nav/navigation/new', data, $.proxy(function(response, textStatus) {
            this.$spinner.addClass('hidden');

            if (textStatus === 'success' && response.success) {
                Craft.cp.displayNotice(Craft.t('app', 'Menu saved.'));

                updateAllNav(response.navs);

                // The newly added menu item will always be at the end...
                var newMenuItem = response.navs[response.navs.length - 1];

                var $tr = AdminTable.addRow('<tr class="nav-item" data-id="'+newMenuItem.id+'" data-currlabel="'+newMenuItem.currLabel+'" data-name="'+newMenuItem.currLabel+'">' + 
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

                    '<td data-title="'+newMenuItem.currLabel+'">' + 
                        '<a class="move icon" title="' + Craft.t('app', 'Reorder') + '" role="button"></a>' + 
                        '<a class="edit-nav">'+newMenuItem.currLabel+'</a>' + 
                        '<span class="original-nav">('+newMenuItem.currLabel+')</span>' + 
                    '</td>' + 
                    
                    '<td data-title="'+newMenuItem.currLabel+'">' + 
                        '<span class="original-nav-link">'+newMenuItem.url+'</span>' + 
                    '</td>' + 
                    
                    '<td class="thin">' + 
                        '<a class="delete icon" title="' + Craft.t('app', 'Delete') + '" role="button"></a>' + 
                    '</td>' + 
                '</tr>');

                Craft.initUiElements($tr);

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
    }
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
        layoutId: $('select#layoutId').val(),
    }

    Craft.postActionRequest('cp-nav/navigation/toggle', data, $.proxy(function(response, textStatus) {
        if (textStatus === 'success' && response.success) {
            Craft.cp.displayNotice(Craft.t('app', 'Status saved.'));

            updateAllNav(response.navs);
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
            layoutId: $('select#layoutId').val(),
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

            $fieldsContainer.html(response.html)
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

            Garnish.$bod.append(response.footerJs);

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

                updateAllNav(response.navs);
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
            layoutId: $('select#layoutId').val(),
        };

        Craft.postActionRequest(this.settings.reorderAction, data, $.proxy(function(response, textStatus) {
            if (textStatus === 'success') {
                if (response.success) {
                    this.onReorderItems(ids);
                    Craft.cp.displayNotice(Craft.t('app', this.settings.reorderSuccessMessage));

                    updateAllNav(response.navs);
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
            layoutId: $('select#layoutId').val(),
        };

        Craft.postActionRequest(this.settings.deleteAction, data, $.proxy(function(response, textStatus) {
            if (textStatus === 'success') {
                this.handleDeleteItemResponse(response, $row);

                updateAllNav(response.navs);
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
var badgeHandleIndex = {};
var updateAllNav = function(navs) {
    var navList = $('#global-sidebar nav#nav ul');
    
    navList.find('.badge').each(function () {
        badgeHandleIndex[$(this).closest('li').attr('id').substr(4)] = $(this)[0].outerHTML;
    });

    navList.empty();

    var navItems = '';
    $.each(navs, function(index, nav) {
        if (nav.enabled == '1') {
            var url = Craft.getUrl(nav.parsedUrl);

            var iconHtml = '<span class="icon icon-mask">' +
                '<svg version="1.1" baseProfile="full" width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">' +
                    '<circle cx="10" cy="10" r="10" fill="#000" fill-opacity="0.35"></circle>' +
                    '<text x="10" y="15" font-size="15" font-family="sans-serif" font-weight="bold" text-anchor="middle" fill="#000">'+nav.currLabel.substring(0, 1).toUpperCase()+'</text>' +
                '</svg>' +
            '</span>';

            if (nav.craftIcon) {
                iconHtml = '<span class="icon icon-mask"><span data-icon="'+nav.craftIcon+'"></span></span>';
            }

            if (nav.pluginIcon) {
                iconHtml = '<span class="icon icon-mask">'+nav.pluginIcon+'</span>';
            }

            var target = 'target="_self"';

            if (nav.newWindow == 1) {
                target = 'target="_blank"';
            }

            var badgeHtml = nav.handle in badgeHandleIndex ? badgeHandleIndex[nav.handle] : '';

            navItems += '<li id="nav-'+nav.handle+'">' +
                '<a href="'+url+'" '+target+'>' +
                    iconHtml +
                    '<span class="label">'+nav.currLabel+'</span>' +
                    badgeHtml +
                '</a>' +
            '</li>';
        }
    });

    $('#global-sidebar nav#nav ul').append(navItems);
}


})(jQuery);


