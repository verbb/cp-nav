// ==========================================================================

// CP Nav Plugin for Craft CMS
// Author: Verbb - https://verbb.io/

// ==========================================================================

$(function() {

if (typeof Craft.CpNav === typeof undefined) {
    Craft.CpNav = {};
}

// Store thse items so we can re-apply them later
Craft.CpNav.NewWindows = [];
Craft.CpNav.EmptyUrls = [];
Craft.CpNav.Dividers = [];

Craft.CpNav.ModifyItems = Garnish.Base.extend({
    prefix: '#global-sidebar #nav li#nav-',

    init: function() {
        Garnish.requestAnimationFrame($.proxy(function() {
            $.each(Craft.CpNav.NewWindows, $.proxy(function(index, element) {
                this.newWindow(this.prefix + element);
            }, this));

            $.each(Craft.CpNav.EmptyUrls, $.proxy(function(index, element) {
                this.emptyUrl(this.prefix + element);
            }, this));

            $.each(Craft.CpNav.Dividers, $.proxy(function(index, element) {
                this.divider(this.prefix + element);
            }, this));
        }, this));
    },

    newWindow: function(id) {
        $(id).addClass('cpn-new-window');
        $(id).find('a').attr('target', '_blank');
    },

    emptyUrl: function(id) {
        $(id).addClass('cpn-empty-url');
        $(id).find('a').removeAttr('href');
    },

    divider: function(id) {
        $(id).addClass('cpn-divider loaded');
        $(id).find('a').removeAttr('href');
    },
});

// Run immediately
new Craft.CpNav.ModifyItems();

});
