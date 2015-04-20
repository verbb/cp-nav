$(function() {

// ----------------------------------------
// RIGHT-HAND UTIL BUTTON FOR ADDING MENU ITEM
// ----------------------------------------

$('#header-actions .add-new-menu-item').on('click', function(e) {
	e.preventDefault();

	new Craft.AddMenuItemHUD($(this), $(this).data('id'));
});



Craft.AddMenuItemHUD = Garnish.Base.extend({
    $element: null,
    data: null,
    navId: null,
    layoutId: null,

    $form: null,
    $spinner: null,

    hud: null,

    init: function($element, layoutId) {
        this.$element = $element;

        this.layoutId = layoutId;

        this.data = {
        	template: 'cpnav/settings/_editorQuick',
        	layoutId: layoutId,
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

            new Craft.HandleGenerator($fieldsContainer.find('#settings-currLabelQuickAdd')[0], $fieldsContainer.find('#settings-handleQuickAdd')[0]);

            var $buttonsOuterContainer = $('<div class="footer"/>').appendTo(this.$form);

            this.$spinner = $('<div class="spinner hidden"/>').appendTo($buttonsOuterContainer);

            var $buttonsContainer = $('<div class="buttons right"/>').appendTo($buttonsOuterContainer);
            $cancelBtn = $('<div class="btn">'+Craft.t('Cancel')+'</div>').appendTo($buttonsContainer);
            $saveBtn = $('<input class="btn submit" type="submit" value="'+Craft.t('Save')+'"/>').appendTo($buttonsContainer);

            $hudContents = $hudContents.add(this.$form);

            this.hud = new Garnish.HUD(this.$element, $hudContents, {
                bodyClass: 'body elementeditor addnewmenuitem',
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

        var data = this.$form.serialize();

        Craft.postActionRequest('cpNav/new', data, $.proxy(function(response, textStatus) {
            this.$spinner.addClass('hidden');

            if (textStatus == 'success' && response.success) {
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


var updateNav = function(nav) {
	var navItem = '<li id="nav-'+nav.handle+'"><a href="'+nav.url+'">'+nav.currLabel+'</a></li>';
	$('header#header nav ul#nav').append(navItem);
}


});


