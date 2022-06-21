(function($) {

if (typeof Craft.CpNav === typeof undefined) {
    Craft.CpNav = {};
}

Craft.CpNav.ToggleMenuItem = Garnish.Base.extend({
    init: function($element) {
        this.$element = $element;
        this.$a = $element.find('a:first');
        this.$subnav = $element.find('.subnav');
        this.$toggle = $('<span class="cp-nav-toggle toggle" />');
        this.stateKey = 'cp-nav-toggle-' + $element.attr('id');

        this.$toggle.appendTo(this.$a);

        this.addListener(this.$a, 'click', 'clickAnchor');
        this.addListener(this.$toggle, 'click', 'clickToggle');

        // Get the saved state (if any)
        if (localStorage.getItem(this.stateKey)) {
            this.$toggle.trigger('click');
        }

        // If the parent or child item is active, always show the menu (but don't save state)
        if (this.$a.hasClass('is-active')) {
            this.$toggle.addClass('expanded');
            this.$subnav.removeClass('hidden');
        }
    },

    clickToggle: function(e) {
        e.preventDefault();

        this.$toggle.toggleClass('expanded');
        this.$subnav.toggleClass('hidden');

        // Save the state in local storage
        if (this.$toggle.hasClass('expanded')) {
            localStorage.setItem(this.stateKey, true);
        } else {
            localStorage.removeItem(this.stateKey);
        }
    },

    clickAnchor: function(e) {
        if ($(e.target).hasClass('cp-nav-toggle')) {
            e.preventDefault();
        }
    },
});


Craft.CpNav.InitMenuItems = Garnish.Base.extend({
    init: function() {
        // Wait until our custom menu has be loaded in - `waitForElm()` will already be loaded
        if (typeof waitForElm !== 'undefined') {
            waitForElm('#global-sidebar #nav.cp-nav-menu').then((sidebarNav) => {
                $('.cp-nav-menu [data-subnav-behaviour="openToggle"]').each(function(index, element) {
                    new Craft.CpNav.ToggleMenuItem($(element));
                });
            });
        } else {
            console.log('Unable to find function `waitForElm()`.');
        }
    },
});

// Initialize on-load, but available as a function to re-trigger for previews
new Craft.CpNav.InitMenuItems();

})(jQuery);
