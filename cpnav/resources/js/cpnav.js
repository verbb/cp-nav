$(function() {

	$(document).on('keyup', '#settings-url', function() {
		var pattern = new RegExp("^(https?)");

		if (pattern.test($(this).val())) {
			$('.eg-url').hide();
		} else {
			$('.eg-url').show();
		}

		$('.example-url').html($(this).val());
	});

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
            }
		}));
	});



	$('tr.nav-item .edit-nav').on('click', function(e) {
    	new Craft.EditNavItem($(this), $(this).parents('tr.nav-item'));
    });


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

	            	Craft.cp.displayNotice('Menu saved.');

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


});


