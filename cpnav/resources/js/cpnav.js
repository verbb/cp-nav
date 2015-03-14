$(function() {

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

	$(document).on('change', '.lightswitch', function() {
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
	});



    // ----------------------------------------
    // WHEN CLICKING ON A MENU ITEM, ALLOW HUD TO EDIT
    // ----------------------------------------

	$('tr.nav-item .edit-nav').on('click', function(e) {
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


});


