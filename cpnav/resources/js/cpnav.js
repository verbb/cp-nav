$(function() {



    // ----------------------------------------
    // FETCH NAVS FOR LAYOUT WHEN CHANGING SELECT
    // ----------------------------------------

	$(document).on('change', '#settings-navigation #settings-layoutId', function() {
		var data = {
			layoutId: $(this).val(),
		};

		var $spinner = $('<div class="spinner"/>').appendTo($(this).parents('.input'));

		Craft.postActionRequest('cpNav/getNavsForLayout', data, $.proxy(function(response, textStatus) {
			$spinner.remove();

            if (textStatus == 'success') {
            	$('table#settings-navItems').replaceWith(response.html);
            	
            	Craft.initUiElements($('table#settings-navItems'));

			    new Craft.AlternateAdminTable(AdminTable.settings);
			}
		}));
	});



    // ----------------------------------------
    // UPDATE URL NOTE WHEN TYPING IN URL INPUT
    // ----------------------------------------

	$(document).on('keyup', '#settings-url', function() {
		var pattern = new RegExp("^(https?)");

		if (pattern.test($(this).val())) {
			$('.eg-url').hide();
		} else {
			$('.eg-url').show();
		}

		$('.example-url').html($(this).val());
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
			id: row.data('id')
		}

		Craft.postActionRequest('cpNav/toggleNav', data, $.proxy(function(response, textStatus) {
            if (textStatus == 'success' && response.success) {
            	Craft.cp.displayNotice('Status saved.');

            	updateAllNav(response.navs);
            }
		}));
	}

	$(document).on('change', '#settings-navItems .lightswitch', handleNavLightswitch);



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
	    $spinner: null,

	    hud: null,

	    init: function($element, $data) {
	        this.$element = $element;

	        this.data = {
	        	id: $data.data('id'),
	        	currLabel: $data.data('currlabel'),
	        }

	        this.$element.addClass('loading');

	        Craft.postActionRequest('cpNav/getNavHtml', this.data, $.proxy(this, 'showHud'));
	    },

	    showHud: function(response, textStatus) {
	        this.$element.removeClass('loading');

	        if (textStatus == 'success') {
	            var $hudContents = $();

	            this.$form = $('<form/>');
	            $fieldsContainer = $('<div class="fields"/>').appendTo(this.$form);

	            $fieldsContainer.html(response.html)
	            Craft.initUiElements($fieldsContainer);

	            var $buttonsOuterContainer = $('<div class="footer"/>').appendTo(this.$form);

	            this.$spinner = $('<div class="spinner hidden"/>').appendTo($buttonsOuterContainer);

	            var $buttonsContainer = $('<div class="buttons right"/>').appendTo($buttonsOuterContainer);
	            $cancelBtn = $('<div class="btn">'+Craft.t('Cancel')+'</div>').appendTo($buttonsContainer);
	            $saveBtn = $('<input class="btn submit" type="submit" value="'+Craft.t('Save')+'"/>').appendTo($buttonsContainer);

	            $hudContents = $hudContents.add(this.$form);

	            this.hud = new Garnish.HUD(this.$element, $hudContents, {
	                bodyClass: 'body elementeditor',
	                closeOtherHUDs: false
	            });

	            this.hud.on('hide', $.proxy(function() {
	                delete this.hud;
	            }, this));

	            this.addListener(this.$form, 'submit', 'saveGroupField');
	            this.addListener($cancelBtn, 'click', function() {
	                this.hud.hide()
	            });
	        }
	    },

	    saveGroupField: function(ev) {
	        ev.preventDefault();

	        this.$spinner.removeClass('hidden');

	        var data = this.$form.serialize()

	        Craft.postActionRequest('cpNav/saveNav', data, $.proxy(function(response, textStatus) {
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
	        this.hud.hide();
	        delete this.hud;
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
				ids: JSON.stringify(ids)
			};

			Craft.postActionRequest('cpNav/reorderNav', data, $.proxy(function(response, textStatus) {
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
		handleDeleteObjectResponse: function(response, $row)
		{
			var id = this.getObjectId($row),
				name = this.getObjectName($row);

			if (response.success) {
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
        tableSelector: '#settings-navItems',
        newObjectBtnSelector: '#newNavItem',
        sortable: true,
        deleteAction: 'cpNav/deleteNav',
        confirmDeleteMessage: 'Are you sure you want to delete “{name}”?',
    });




    // ----------------------------------------
    // FUNCTIONS TO ASSIST WITH UPDATING THE CP NAV CLIENT-SIDE
    // ----------------------------------------

	var updateNav = function(nav) {
		var $navItem = $('header#header nav ul#nav li[id="nav-'+nav.handle+'"] a');

		$navItem.html(nav.currLabel);
		$navItem.attr('href', nav.url);
	}

	var updateAllNav = function(navs) {
		$('header#header nav ul#nav').empty();

		var navItems = '';
		$.each(navs, function(index, nav) {
			if (nav.enabled == '1') {
				var url = Craft.getUrl(nav.url);

				navItems += '<li id="nav-'+nav.handle+'"><a href="'+url+'">'+nav.currLabel+'</a></li>';
			}
		});

		$('header#header nav ul#nav').append(navItems);
	}






    // ----------------------------------------
    // LAYOUTS
    // ----------------------------------------

    var LayoutAdminTable = new Craft.AdminTable({
        tableSelector: '#settings-layoutItems',
        sortable: false,
        deleteAction: 'cpNav/layout/deleteLayout',
        confirmDeleteMessage: 'Are you sure you want to permanently delete this layout and all its settings? This cannot be undone.',
    });






    // ----------------------------------------
    // WHEN CLICKING ON A LAYOUT ITEM, ALLOW HUD TO EDIT
    // ----------------------------------------

	$(document).on('click', 'tr.layout-item a.edit-layout', function(e) {
    	new Craft.EditLayoutItem($(this), $(this).parents('tr.layout-item'));
    });

    // ----------------------------------------
    // HUD FOR EDITING LAYOUT
    // ----------------------------------------

	Craft.EditLayoutItem = Garnish.Base.extend({
	    $element: null,
	    data: null,
	    layoutId: null,

	    $form: null,
	    $spinner: null,

	    hud: null,

	    init: function($element, $data) {
	        this.$element = $element;

	        this.data = {
	        	id: $data.data('id'),
	        }

	        this.$element.addClass('loading');

	        Craft.postActionRequest('cpNav/layout/getLayoutHtml', this.data, $.proxy(this, 'showHud'));
	    },

	    showHud: function(response, textStatus) {
	        this.$element.removeClass('loading');

	        if (textStatus == 'success') {
	            var $hudContents = $();

	            this.$form = $('<form/>');
	            $fieldsContainer = $('<div class="fields"/>').appendTo(this.$form);

	            $fieldsContainer.html(response.html)
	            Craft.initUiElements($fieldsContainer);

	            var $buttonsOuterContainer = $('<div class="footer"/>').appendTo(this.$form);

	            this.$spinner = $('<div class="spinner hidden"/>').appendTo($buttonsOuterContainer);

	            var $buttonsContainer = $('<div class="buttons right"/>').appendTo($buttonsOuterContainer);
	            $cancelBtn = $('<div class="btn">'+Craft.t('Cancel')+'</div>').appendTo($buttonsContainer);
	            $saveBtn = $('<input class="btn submit" type="submit" value="'+Craft.t('Save')+'"/>').appendTo($buttonsContainer);

	            $hudContents = $hudContents.add(this.$form);

	            this.hud = new Garnish.HUD(this.$element, $hudContents, {
	                bodyClass: 'body elementeditor',
	                closeOtherHUDs: false
	            });

	            this.hud.on('hide', $.proxy(function() {
	                delete this.hud;
	            }, this));

	            this.addListener(this.$form, 'submit', 'save');
	            this.addListener($cancelBtn, 'click', 'closeHud');
	        }
	    },

	    save: function(ev) {
	        ev.preventDefault();

	        this.$spinner.removeClass('hidden');

	        var data = this.$form.serialize()

	        Craft.postActionRequest('cpNav/layout/saveLayout', data, $.proxy(function(response, textStatus) {
	            this.$spinner.addClass('hidden');

                if (textStatus == 'success' && response.success) {
                	this.$element.html(response.layout.name);

	            	Craft.cp.displayNotice('Layout saved.');

                    this.closeHud();
                } else {
                    Garnish.shake(this.hud.$hud);
                }
	        }, this));
	    },

	    closeHud: function() {
	        this.hud.hide();
	        delete this.hud;
	    }
	});





    // ----------------------------------------
    // ALLOW HUD TO ADD LAYOUT
    // ----------------------------------------

	$(document).on('click', '#settings-layouts .buttons .btn.add.submit', function(e) {
		e.preventDefault();
    	new Craft.CreateLayoutItem($(this));
    });

    // ----------------------------------------
    // HUD FOR EDITING LAYOUT
    // ----------------------------------------

	Craft.CreateLayoutItem = Garnish.Base.extend({
	    $element: null,
	    data: null,
	    layoutId: null,

	    $form: null,
	    $spinner: null,

	    hud: null,

	    init: function($element) {
	        this.$element = $element;

	        this.$element.addClass('loading');

	        Craft.postActionRequest('cpNav/layout/getLayoutHtml', {}, $.proxy(this, 'showHud'));
	    },

	    showHud: function(response, textStatus) {
	        this.$element.removeClass('loading');

	        if (textStatus == 'success') {
	            var $hudContents = $();

	            this.$form = $('<form/>');
	            $fieldsContainer = $('<div class="fields"/>').appendTo(this.$form);

	            $fieldsContainer.html(response.html)
	            Craft.initUiElements($fieldsContainer);

	            var $buttonsOuterContainer = $('<div class="footer"/>').appendTo(this.$form);

	            this.$spinner = $('<div class="spinner hidden"/>').appendTo($buttonsOuterContainer);

	            var $buttonsContainer = $('<div class="buttons right"/>').appendTo($buttonsOuterContainer);
	            $cancelBtn = $('<div class="btn">'+Craft.t('Cancel')+'</div>').appendTo($buttonsContainer);
	            $saveBtn = $('<input class="btn submit" type="submit" value="'+Craft.t('Create')+'"/>').appendTo($buttonsContainer);

	            $hudContents = $hudContents.add(this.$form);

	            this.hud = new Garnish.HUD(this.$element, $hudContents, {
	                bodyClass: 'body elementeditor',
	                closeOtherHUDs: false
	            });

	            this.hud.on('hide', $.proxy(function() {
	                delete this.hud;
	            }, this));

	            this.addListener(this.$form, 'submit', 'save');
	            this.addListener($cancelBtn, 'click', 'closeHud');
	        }
	    },

	    save: function(ev) {
	        ev.preventDefault();

	        this.$spinner.removeClass('hidden');

	        var data = this.$form.serialize()

	        Craft.postActionRequest('cpNav/layout/newLayout', data, $.proxy(function(response, textStatus) {
	            this.$spinner.addClass('hidden');

                if (textStatus == 'success' && response.success) {
	            	//Craft.cp.displayNotice('Layout created.');

	            	location.href = Craft.getUrl('settings/plugins/cpnav');

                    this.closeHud();
                } else {
                    Garnish.shake(this.hud.$hud);
                }
	        }, this));
	    },

	    closeHud: function() {
	        this.hud.hide();
	        delete this.hud;
	    }
	});








    // ----------------------------------------
    // BINDINGS FOR FIELDTYPE
    // ----------------------------------------

    var FieldTypeAdminTable = new Craft.AlternateAdminTable({
        tableSelector: '#fields-navItems',
        sortable: true,
    });

	$(document).on('change', '#fields-navItems .lightswitch', handleNavLightswitch);







});


