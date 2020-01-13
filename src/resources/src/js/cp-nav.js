// ==========================================================================

// CP Nav Plugin for Craft CMS
// Author: Verbb - https://verbb.io/

// ==========================================================================

$(function() {

if (typeof Craft.CpNav === typeof undefined) {
    Craft.CpNav = {};
}

Craft.CpNav.NewWindow = Garnish.Base.extend({
    init: function(id) {
        $(id).attr('target', '_blank');
    },
});

Craft.CpNav.EmptyUrl = Garnish.Base.extend({
    init: function(id) {
        $(id).addClass('cpn-empty-url');
        $(id).removeAttr('href');
    },
});

});
