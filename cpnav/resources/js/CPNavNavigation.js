$(function() {



    // ----------------------------------------
    // FETCH NAVS FOR LAYOUT WHEN CHANGING SELECT
    // ----------------------------------------

    $(document).on('change', 'select#layoutId', function() {
        $(this).addClass('loading');

        window.location.href = Craft.getUrl('cpnav?layoutId=' + $(this).val());
    });



    // ----------------------------------------
    // ADDING NEW MENU ITEM MODAL
    // ----------------------------------------

    $(document).on('click', '.add-new-menu-item', function(e) {
        new Craft.AddMenuItem($(this));
    });



    // ----------------------------------------
    // NEW MENU ITEM MODAL
    // ----------------------------------------
    
    Craft.AddMenuItem = Garnish.Base.extend({
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

            Craft.postActionRequest('cpNav/nav/getHudHtml', this.data, $.proxy(this, 'showHud'));
        },

        showHud: function(response, textStatus) {
            this.$element.removeClass('loading');

            if (textStatus == 'success') {
                var $hudContents = $();

                this.$form = $('<div/>');
                $fieldsContainer = $('<div class="fields"/>').appendTo(this.$form);

                $fieldsContainer.html(response.html)
                Craft.initUiElements($fieldsContainer);

                var $footer = $('<div class="hud-footer"/>').appendTo(this.$form),
                    $buttonsContainer = $('<div class="buttons right"/>').appendTo($footer);
                this.$cancelBtn = $('<div class="btn">'+Craft.t('Cancel')+'</div>').appendTo($buttonsContainer);
                this.$saveBtn = $('<input class="btn submit" type="submit" value="'+Craft.t('Save')+'"/>').appendTo($buttonsContainer);
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

            Craft.postActionRequest('cpNav/nav/new', data, $.proxy(function(response, textStatus) {
                this.$spinner.addClass('hidden');

                if (textStatus == 'success' && response.success) {
                    Craft.cp.displayNotice('Menu saved.');

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
                            '<a class="move icon" title="Reorder" role="button"></a>' + 
                            '<a class="edit-nav">'+newMenuItem.currLabel+'</a>' + 
                            '<span class="original-nav">('+newMenuItem.currLabel+')</span>' + 
                        '</td>' + 
                        
                        '<td data-title="'+newMenuItem.currLabel+'">' + 
                            '<span class="original-nav-link">'+newMenuItem.url+'</span>' + 
                        '</td>' + 
                        
                        '<td class="thin">' + 
                            '<a class="delete icon" title="Delete" role="button"></a>' + 
                        '</td>' + 
                    '</tr>');

                    Craft.initUiElements($tr);

                    this.closeHud();
                } else {
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

    var handleNavLightswitch = function() {
        var row = $(this).parents('tr.nav-item')
        var val = $(this).find('input:first').val();
        val = (!val) ? 0 : 1;

        var data = {
            value: val,
            id: row.data('id'),
            layoutId: $('select#layoutId').val(),
        }

        Craft.postActionRequest('cpNav/nav/toggle', data, $.proxy(function(response, textStatus) {
            if (textStatus == 'success' && response.success) {
                Craft.cp.displayNotice('Status saved.');

                updateAllNav(response.navs);
            }
        }));
    }

    $(document).on('change', '#navItems .lightswitch', handleNavLightswitch);



    // ----------------------------------------
    // WHEN CLICKING ON A MENU ITEM, ALLOW HUD TO EDIT
    // ----------------------------------------

    $(document).on('click', 'tr.nav-item a.edit-nav', function(e) {
        new Craft.EditNavItem($(this), $(this).parents('tr.nav-item'));
    });



    // ----------------------------------------
    // HUD FOR EDITING MENU
    // ----------------------------------------

    Craft.EditNavItem = Garnish.Base.extend({
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

            Craft.postActionRequest('cpNav/nav/getHudHtml', this.data, $.proxy(this, 'showHud'));
        },

        showHud: function(response, textStatus) {
            this.$element.removeClass('loading');

            if (textStatus == 'success') {
                var $hudContents = $();

                this.$form = $('<div/>');
                $fieldsContainer = $('<div class="fields"/>').appendTo(this.$form);

                $fieldsContainer.html(response.html)
                Craft.initUiElements($fieldsContainer);

                var $footer = $('<div class="hud-footer"/>').appendTo(this.$form),
                    $buttonsContainer = $('<div class="buttons right"/>').appendTo($footer);
                this.$cancelBtn = $('<div class="btn">'+Craft.t('Cancel')+'</div>').appendTo($buttonsContainer);
                this.$saveBtn = $('<input class="btn submit" type="submit" value="'+Craft.t('Save')+'"/>').appendTo($buttonsContainer);
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

            Craft.postActionRequest('cpNav/nav/save', data, $.proxy(function(response, textStatus) {
                this.$spinner.addClass('hidden');

                if (textStatus == 'success' && response.success) {
                    this.$element.html(response.nav.currLabel);
                    this.$element.parents('tr.nav-item').find('.original-nav-link').html(response.nav.url);

                    Craft.cp.displayNotice('Menu saved.');

                    updateNav(response.nav);

                    this.closeHud();
                } else {
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
    Craft.AlternateAdminTable = Craft.AdminTable.extend({

        // override the default reorderObjects function so we can update cp nav
        reorderObjects: function() {
            var ids = [];

            for (var i = 0; i < this.sorter.$items.length; i++) {
                var id = $(this.sorter.$items[i]).attr(this.settings.idAttribute);
                ids.push(id);
            }

            var data = {
                ids: JSON.stringify(ids),
                layoutId: $('select#layoutId').val(),
            };

            Craft.postActionRequest('cpNav/nav/reorder', data, $.proxy(function(response, textStatus) {
                if (textStatus == 'success') {
                    if (response.success) {
                        Craft.cp.displayNotice(Craft.t(this.settings.reorderSuccessMessage));

                        updateAllNav(response.navs);
                    } else {
                        Craft.cp.displayError(Craft.t(this.settings.reorderFailMessage));
                    }
                }
            }, this));
        },

        // override the default handleDeleteObjectResponse function so we can update cp nav
        handleDeleteObjectResponse: function(response, $row) {
            var id = this.getObjectId($row),
                name = this.getObjectName($row);

            if (response.success) {
                if (this.sorter) {
                    this.sorter.removeItems($row);
                }

                $row.remove();
                this.totalObjects--;
                this.updateUI();
                this.onDeleteObject(id);

                Craft.cp.displayNotice(Craft.t(this.settings.deleteSuccessMessage, { name: name }));

                updateAllNav(response.navs);
            } else {
                Craft.cp.displayError(Craft.t(this.settings.deleteFailMessage, { name: name }));
            }
        },

    });




    // ----------------------------------------
    // CREATE THE ADMIN TABLE
    // ----------------------------------------

    var AdminTable = new Craft.AlternateAdminTable({
        tableSelector: '#navItems',
        newObjectBtnSelector: '#newNavItem',
        sortable: true,
        deleteAction: 'cpNav/nav/delete?layoutId' + $('select#layoutId').val(),
        confirmDeleteMessage: 'Are you sure you want to delete “{name}”?',
    });




    // ----------------------------------------
    // FUNCTIONS TO ASSIST WITH UPDATING THE CP NAV CLIENT-SIDE
    // ----------------------------------------

    var updateNav = function(nav) {
        var $navItem = $('#global-sidebar nav ul#nav li[id="nav-'+nav.handle+'"]');

        var url = Craft.getUrl(nav.url);

        var iconHtml = '<span class="icon">' +
            '<svg version="1.1" baseProfile="full" width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">' +
                '<circle cx="10" cy="10" r="10" fill="#000" fill-opacity="0.35"></circle>' +
                '<text x="10" y="15" font-size="15" font-family="sans-serif" font-weight="bold" text-anchor="middle" fill="#000">'+nav.currLabel.substring(0, 1).toUpperCase()+'</text>' +
            '</svg>' +
        '</span>';

        if (nav.craftIcon) {
            var iconHtml = '<span class="icon"><span data-icon="'+nav.craftIcon+'"></span></span>';
        }

        if (nav.pluginIcon) {
            var iconHtml = '<span class="icon">'+nav.pluginIcon+'</span>';
        }

        if (nav.newWindow == 1) {
            var target = 'target="_blank"';
        } else {
            var target = 'target="_self"';
        }

        $navItem.html('<a href="'+url+'" '+target+'>' +
            iconHtml +
            '<span class="label">'+nav.currLabel+'</span>' +
        '</a>');
    }

    var updateAllNav = function(navs) {
        $('#global-sidebar nav ul#nav').empty();

        var navItems = '';
        $.each(navs, function(index, nav) {
            if (nav.enabled == '1') {
                var url = Craft.getUrl(nav.url);

                console.log(nav);

                var iconHtml = '<span class="icon">' +
                    '<svg version="1.1" baseProfile="full" width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">' +
                        '<circle cx="10" cy="10" r="10" fill="#000" fill-opacity="0.35"></circle>' +
                        '<text x="10" y="15" font-size="15" font-family="sans-serif" font-weight="bold" text-anchor="middle" fill="#000">'+nav.currLabel.substring(0, 1).toUpperCase()+'</text>' +
                    '</svg>' +
                '</span>';

                if (nav.craftIcon) {
                    var iconHtml = '<span class="icon"><span data-icon="'+nav.craftIcon+'"></span></span>';
                }

                if (nav.pluginIcon) {
                    var iconHtml = '<span class="icon">'+nav.pluginIcon+'</span>';
                }

                if (nav.newWindow == 1) {
                    var target = 'target="_blank"';
                } else {
                    var target = 'target="_self"';
                }

                navItems += '<li id="nav-'+nav.handle+'">' +
                    '<a href="'+url+'" '+target+'>' +
                        iconHtml +
                        '<span class="label">'+nav.currLabel+'</span>' +
                    '</a>' +
                '</li>';
            }
        });

        $('#global-sidebar nav ul#nav').append(navItems);
    }

});


