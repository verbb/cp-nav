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
 * Optional decoration hooks on Craft's native `#nav`.
 * Divider styles key off server-rendered `id="nav-divider-*"` in CSS (no FOUT).
 * JS only adds convenience markers + inert click handling.
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

    // Craft's root URL matching runs after the nav event. Restore native selection
    // when the current destination now lives beneath a different sidebar item.
    const current = new URL(window.location.href);
    const routePath = (url) => (url.searchParams.get('p') || url.pathname).replace(/^\/+|\/+$/g, '');
    const currentPath = routePath(current);
    let selected = null;
    let longestPath = -1;
    $nav.find('a[href]').each(function() {
        const url = new URL(this.href, current);
        if (url.origin !== current.origin || this.target === '_blank' || this.getAttribute('href').startsWith('#')) {
            return;
        }
        const path = routePath(url);
        if (!path || (currentPath !== path && !currentPath.startsWith(path + '/'))) {
            return;
        }
        if ([...url.searchParams].some(([key, value]) => key !== 'p' && current.searchParams.get(key) !== value)) {
            return;
        }
        if (path.length > longestPath) {
            selected = this;
            longestPath = path.length;
        }
    });
    if (selected?.hasAttribute('data-cpnav-relocated')) {
        $nav.find('.sel').removeClass('sel');
        $nav.find('a[aria-current]').removeAttr('aria-current');
        selected.setAttribute('aria-current', 'page');
        const $row = $(selected).closest('li');
        $row.children('.nav-item').addClass('sel');
        const $parent = $row.parent('ul').closest('li');
        $parent.children('.nav-item').addClass('sel');
        $parent.find('craft-disclosure').first().attr('state', 'expanded');
    }
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
