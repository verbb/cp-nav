(function($) {

if (typeof Craft.CpNav === typeof undefined) {
    Craft.CpNav = {};
}

// Wait for CP Nav menu markup (injected after initial page load).
function waitForElm(selector) {
    return new Promise((resolve) => {
        const existing = document.querySelector(selector);

        if (existing) {
            resolve(existing);
            return;
        }

        const observer = new MutationObserver(() => {
            const el = document.querySelector(selector);

            if (el) {
                observer.disconnect();
                resolve(el);
            }
        });

        observer.observe(document.documentElement, { childList: true, subtree: true });
    });
}

/**
 * Mark divider rows and restore chrome hooks on Craft's native `#nav`.
 * Dividers are emitted with id `nav-divider-{uuid}` from NavRenderer.
 */
Craft.CpNav.decorateNav = function($nav) {
    $nav.addClass('cp-nav-menu');

    $nav.find('li[id^="nav-divider-"]').each(function() {
        const $li = $(this);
        $li.attr('data-type', 'divider');

        // Craft still renders an <a>; keep it inert for keyboard/click.
        $li.find('a').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
    });
};

Craft.CpNav.InitMenuItems = Garnish.Base.extend({
    init: function() {
        waitForElm('#global-sidebar #nav').then((nav) => {
            Craft.CpNav.decorateNav($(nav));
        });
    },
});

// Initialize on-load, but available as a function to re-trigger for previews
new Craft.CpNav.InitMenuItems();

})(jQuery);
