(function($) {

if (typeof Craft.CpNav === typeof undefined) {
    Craft.CpNav = {};
}


// ----------------------------------------
// FETCH NAVS FOR LAYOUT WHEN CHANGING SELECT
// ----------------------------------------

$(document).on('change', '.layout-select select', function() {
    $(this).addClass('loading');

    window.location.href = Craft.getUrl('cp-nav?layoutId=' + $(this).val());
});



// ----------------------------------------
// ADDING NEW MENU ITEM MODAL
// ----------------------------------------

$(document).on('click', '.add-new-menu-item', function(e) {
    var data = {
        layoutId: $('[name="layoutId"]').val(),
        type: $(this).data('type'),
    };

    // Always bind to the main button to prevent menu issues
    new Craft.CpNav.AddMenuItem($('.btn.submit.add-new-menu-item'), data);
});


// ----------------------------------------
// SIDEBAR NOTIFICATION
// ----------------------------------------

var $notification = $('.cp-nav-notice');

if ($notification.length) {
    $notification.remove();

    $notification.insertAfter('#global-sidebar #system-info').addClass('shown');
}



// ----------------------------------------
// NEW MENU ITEM MODAL
// ----------------------------------------

Craft.CpNav.AddMenuItem = Garnish.Base.extend({
    $element: null,
    navId: null,

    $form: null,
    $cancelBtn: null,
    $saveBtn: null,

    hud: null,

    init: function($element, data) {
        this.$element = $element;
        this.$element.addClass('loading');

        Craft.sendActionRequest('POST', 'cp-nav/navigation/get-hud-html', { data: data })
            .then((response) => {
                this.showHud(response);
            });
    },

    showHud: function(response) {
        this.$element.removeClass('loading');

        var $hudContents = $();

        this.$form = $('<div/>');
        $fieldsContainer = $('<div/>').appendTo(this.$form);

        $fieldsContainer.html(response.data.bodyHtml)
        Craft.initUiElements($fieldsContainer);

        var $footer = $('<div class="hud-footer"/>').appendTo(this.$form),
            $btnContainer = $('<div class="buttons right"/>').appendTo($footer);

        this.$cancelBtn = $('<button/>', {
            type: 'button',
            class: 'btn',
            text: Craft.t('app', 'Cancel'),
        }).appendTo($btnContainer);

        this.$saveBtn = Craft.ui.createSubmitButton({
            label: Craft.t('app', 'Save'),
            spinner: true,
        }).appendTo($btnContainer);

        $hudContents = $hudContents.add(this.$form);

        this.hud = new Garnish.HUD(this.$element, $hudContents, {
            bodyClass: 'body',
            closeOtherHUDs: false
        });

        this.hud.on('hide', $.proxy(this, 'closeHud'));

        new Craft.HandleGenerator('#currLabel', '#handle');

        Garnish.$bod.append(response.data.footHtml);

        this.$form.find('input:first').focus();

        this.addListener(this.$saveBtn, 'click', 'saveGroupField');
        this.addListener(this.$cancelBtn, 'click', 'closeHud');
    },

    saveGroupField: function(ev) {
        ev.preventDefault();

        this.$saveBtn.addClass('loading');

        var data = this.hud.$body.serialize();

        Craft.sendActionRequest('POST', 'cp-nav/navigation/new', { data })
            .then((response) => {
                this.closeHud();

                Craft.cp.displayNotice(response.data.message);

                this.addNavItem(response.data.navigation);

                updateAllNav(response.data.navHtml);
            })
            .catch(({response}) => {
                Garnish.shake(this.hud.$hud);

                if (response && response.data && response.data.message) {
                    Craft.cp.displayError(response.data.message);
                } else {
                    Craft.cp.displayError();
                }
            })
            .finally(() => {
                this.$saveBtn.removeClass('loading');
            });
    },

    closeHud: function() {
        this.hud.$shade.remove();
        this.hud.$hud.remove();
    },

    addNavItem: function(newMenuItem) {
        var $tr = $(`<tr class="cp-nav-item" data-id="${newMenuItem.id}" data-name="${newMenuItem.currLabel}" data-level="${newMenuItem.level}" tabindex="0">
            <td class="thin lightswitch-cell">
                <div class="field lightswitch-field">
                    <div class="input ltr">
                        <button type="button" class="lightswitch on small" role="checkbox" aria-checked="true">
                            <div class="lightswitch-container">
                                <div class="handle"></div>
                            </div>
                            <input type="hidden" name="navEnabled" value="1">
                        </button>
                    </div>
                </div>
            </td>

            <td data-title="${newMenuItem.currLabel}" data-titlecell style="padding-left: 0px;">
                <a class="move icon" title="${ Craft.t('app', 'Reorder') }" aria-label="${ Craft.t('app', 'Reorder') }" role="button"></a>
                <span class="element">
                    <a class="js-edit-nav">${ Craft.t('app', (newMenuItem.currLabel ? newMenuItem.currLabel : '[none]')) }</a>
                    <span>${ (newMenuItem.currLabel ? `(${ Craft.t('app', newMenuItem.currLabel) })` : '') }</span>
                </td>
            </td>
            
            <td>
                <span class="original-nav-link">${ (newMenuItem.url ? newMenuItem.url : '') }</span>
            </td>
            
            <td>
                <div class="nav-type">
                    <span class="nav-type-${newMenuItem.type}">${newMenuItem.type}</span>
                </div>
            </td>
            
            <td>
                <a class="delete icon" title="${ Craft.t('app', 'Delete') }" role="button"></a>
            </td>
        </tr>`);

        NavAdminTable.addItem($tr);

        Craft.initUiElements($tr);
    },
});



// ----------------------------------------
// WHEN TOGGLING A LIGHTSWITCH, TRIGGER REQUEST
// ----------------------------------------

$(document).on('change', '#cp-nav-items .lightswitch', function() {
    var row = $(this).parents('tr')
    var value = (!$(this).find('input:first').val()) ? 0 : 1;

    var data = {
        value: value,
        id: row.data('id'),
        layoutId: $('[name="layoutId"]').val(),
    }

    Craft.sendActionRequest('POST', 'cp-nav/navigation/toggle', { data })
        .then((response) => {
            Craft.cp.displayNotice(response.data.message);

            updateAllNav(response.data.navHtml);
        })
        .catch(({response}) => {
            if (response && response.data && response.data.message) {
                Craft.cp.displayError(response.data.message);
            } else {
                Craft.cp.displayError();
            }
        });
});



// ----------------------------------------
// WHEN CLICKING ON A MENU ITEM, ALLOW HUD TO EDIT
// ----------------------------------------

$(document).on('click', 'tr.cp-nav-item a.js-edit-nav', function(e) {
    new Craft.CpNav.EditNavItem($(this), $(this).parents('tr.cp-nav-item'));
});



// ----------------------------------------
// HUD FOR EDITING MENU
// ----------------------------------------

Craft.CpNav.EditNavItem = Garnish.Base.extend({
    $element: null,
    data: null,
    navId: null,

    $form: null,
    $cancelBtn: null,
    $saveBtn: null,

    hud: null,

    init: function($element, $data) {
        this.$element = $element;

        this.data = {
            id: $data.data('id'),
            currLabel: $data.data('currlabel'),
            layoutId: $('[name="layoutId"]').val(),
        }

        this.$spinner = $('<div class="spinner small" />');
        this.$element.parent().append(this.$spinner);

        Craft.sendActionRequest('POST', 'cp-nav/navigation/get-hud-html', { data: this.data })
            .then((response) => {
                this.showHud(response);
            });
    },

    showHud: function(response) {
        this.$element.removeClass('loading');

        var $hudContents = $();

        this.$spinner.remove();

        this.$form = $('<div/>');
        $fieldsContainer = $('<div/>').appendTo(this.$form);

        $fieldsContainer.html(response.data.bodyHtml)
        Craft.initUiElements($fieldsContainer);

        var $footer = $('<div class="hud-footer"/>').appendTo(this.$form),
            $btnContainer = $('<div class="buttons right"/>').appendTo($footer);

        this.$cancelBtn = $('<button/>', {
            type: 'button',
            class: 'btn',
            text: Craft.t('app', 'Cancel'),
        }).appendTo($btnContainer);

        this.$saveBtn = Craft.ui.createSubmitButton({
            label: Craft.t('app', 'Save'),
            spinner: true,
        }).appendTo($btnContainer);

        $hudContents = $hudContents.add(this.$form);

        this.hud = new Garnish.HUD(this.$element, $hudContents, {
            bodyClass: 'body',
            closeOtherHUDs: false
        });

        this.hud.on('hide', $.proxy(this, 'closeHud'));

        Garnish.$bod.append(response.data.footHtml);

        this.$form.find('input:first').focus();

        this.addListener(this.$saveBtn, 'click', 'saveGroupField');
        this.addListener(this.$cancelBtn, 'click', 'closeHud');
    },

    saveGroupField: function(ev) {
        ev.preventDefault();

        this.$saveBtn.addClass('loading');

        var data = this.hud.$body.serialize();

        Craft.sendActionRequest('POST', 'cp-nav/navigation/save', { data })
            .then((response) => {
                this.$element.html(response.data.navigation.currLabel);

                this.closeHud();
                Craft.cp.displayNotice(response.data.message);

                updateAllNav(response.data.navHtml);
            })
            .catch(({response}) => {
                Garnish.shake(this.hud.$hud);

                if (response && response.data && response.data.message) {
                    Craft.cp.displayError(response.data.message);
                } else {
                    Craft.cp.displayError();
                }
            })
            .finally(() => {
                this.$saveBtn.removeClass('loading');
            });
    },

    closeHud: function() {
        this.hud.$shade.remove();
        this.hud.$hud.remove();
    }
});



// ----------------------------------------
// WHEN DELETING A MENU ITEM
// ----------------------------------------

$(document).on('click', 'tr.cp-nav-item a.delete', function(e) {
    var $tr = $(this).parents('tr.cp-nav-item');

    var data = {
        id: $tr.data('id'),
        layoutId: $('[name="layoutId"]').val(),
    };

    var confirmDeleteMessage = Craft.t('app', 'Are you sure you want to delete “{name}”?', { name: $tr.data('name') });
    
    if (confirm(confirmDeleteMessage)) {
        Craft.sendActionRequest('POST', 'cp-nav/navigation/delete', { data })
            .then((response) => {
                Craft.cp.displayNotice(response.data.message);

                NavAdminTable.removeItem($tr);

                updateAllNav(response.data.navHtml);
            })
            .catch(({response}) => {
                if (response && response.data && response.data.message) {
                    Craft.cp.displayError(response.data.message);
                } else {
                    Craft.cp.displayError();
                }
            });
    }
});



// ----------------------------------------
// CREATE THE ADMIN TABLE
// ----------------------------------------

Craft.CpNav.NavAdminTable = Garnish.DragSort.extend({
    maxLevels: null,

    _basePadding: null,
    _helperMargin: null,

    _$firstRowCells: null,
    _$titleHelperCell: null,

    _titleHelperCellOuterWidth: null,

    _ancestors: null,
    _updateAncestorsFrame: null,

    _draggeeLevel: null,
    _draggeeLevelDelta: null,

    _targetLevel: null,
    _targetLevelBounds: null,

    _positionChanged: null,

    /**
     * Constructor
     */
    init: function(container) {
        this.$container = $(container);
        this.$table = this.$container.find('table:first');
        this.$elementContainer = this.$table.children('tbody:first');

        this.maxLevels = parseInt(this.$table.attr('data-max-levels'));

        this._basePadding = 12;
        this._helperMargin = 50;

        settings = $.extend({}, Craft.CpNav.NavAdminTable.defaults, {
            handle: '.move',
            collapseDraggees: true,
            singleHelper: true,
            helperSpacingY: 2,
            magnetStrength: 4,
            helper: this.getHelper.bind(this),
            helperLagBase: 1.5,
            axis: Garnish.Y_AXIS
        });

        this.base(this.getElements(), settings);
    },

    getElements: function() {
        return this.$elementContainer.children();
    },

    addItem: function($tr) {
        this.$elementContainer.append($tr);
        this.addItems($tr);
    },

    removeItem: function($tr) {
        var $nextRow = $tr.next();

        // Also check if we need to re-locate children
        while ($nextRow.length) {
            var nextRowLevel = $nextRow.data('level');

            if (nextRowLevel < 2) {
                break;
            }

            $nextRow.data('level', 1);
            $nextRow.find('[data-titlecell]').css('padding-' + Craft.left, 0);

            $nextRow = $nextRow.next();
        }

        $tr.remove();
        this.removeItems($tr);
    },

    /**
     * Returns the draggee rows (including any descendent rows).
     */
    findDraggee: function() {
        this._draggeeLevel = this._targetLevel = this.$targetItem.data('level');
        this._draggeeLevelDelta = 0;

        var $draggee = $(this.$targetItem),
            $nextRow = this.$targetItem.next();

        while ($nextRow.length) {
            // See if this row is a descendant of the draggee
            var nextRowLevel = $nextRow.data('level');

            if (nextRowLevel <= this._draggeeLevel) {
                break;
            }

            // Is this the deepest descendant we've seen so far?
            var nextRowLevelDelta = nextRowLevel - this._draggeeLevel;

            if (nextRowLevelDelta > this._draggeeLevelDelta) {
                this._draggeeLevelDelta = nextRowLevelDelta;
            }

            // Add it and prep the next row
            $draggee = $draggee.add($nextRow);
            $nextRow = $nextRow.next();
        }

        return $draggee;
    },

    /**
     * Returns the drag helper.
     */
    getHelper: function($helperRow) {
        var $container = $('<div class="datatablesorthelper"/>').appendTo(Garnish.$bod),
            $table = $('<table class="data"/>').appendTo($container),
            $tbody = $('<tbody/>').appendTo($table);

        $helperRow.appendTo($tbody);

        // Copy the column widths
        this._$firstRowCells = this.$elementContainer.children('tr:first').children();
        var $helperCells = $helperRow.children();

        for (var i = 0; i < $helperCells.length; i++) {
            var $helperCell = $($helperCells[i]);

            // Skip the checkbox cell
            if ($helperCell.hasClass('lightswitch-cell')) {
                $helperCell.remove();
                continue;
            }

            // Hard-set the cell widths
            var $firstRowCell = $(this._$firstRowCells[i]);
            var width = $firstRowCell[0].getBoundingClientRect().width;

            $firstRowCell.css('width', width + 'px');
            $helperCell.css('width', width + 'px');

            // Is this the title cell?
            if (Garnish.hasAttr($firstRowCell, 'data-titlecell')) {
                this._$titleHelperCell = $helperCell;

                var padding = parseInt($firstRowCell.css('padding-' + Craft.left));
                this._titleHelperCellOuterWidth = width;

                $helperCell.css('padding-' + Craft.left, this._basePadding);
            }
        }

        return $container;
    },

    /**
     * Returns whether the draggee can be inserted before a given item.
     */
    canInsertBefore: function($item) {
        return (this._getLevelBounds($item.prev(), $item) !== false);
    },

    /**
     * Returns whether the draggee can be inserted after a given item.
     */
    canInsertAfter: function($item) {
        return (this._getLevelBounds($item, $item.next()) !== false);
    },

    // Events
    // -------------------------------------------------------------------------

    /**
     * On Drag Start
     */
    onDragStart: function() {
        // Get the initial set of ancestors, before the item gets moved
        this._ancestors = this._getAncestors(this.$targetItem, this.$targetItem.data('level'));

        // Set the initial target level bounds
        this._setTargetLevelBounds();

        this.base();
    },

    /**
     * On Drag
     */
    onDrag: function() {
        this.base();
        this._updateIndent();
    },

    /**
     * On Insertion Point Change
     */
    onInsertionPointChange: function() {
        this._setTargetLevelBounds();
        this._updateAncestorsBeforeRepaint();
        this.base();
    },

    /**
     * On Drag Stop
     */
    onDragStop: function() {
        this._positionChanged = false;
        this.base();

        // Update the draggee's padding if the position just changed
        // ---------------------------------------------------------------------

        if (this._targetLevel != this._draggeeLevel) {
            var levelDiff = this._targetLevel - this._draggeeLevel;

            for (var i = 0; i < this.$draggee.length; i++) {
                var $draggee = $(this.$draggee[i]),
                    oldLevel = $draggee.data('level'),
                    newLevel = oldLevel + levelDiff,
                    padding = this._getLevelIndent(newLevel);

                $draggee.data('level', newLevel);
                $draggee.children('[data-titlecell]:first').css('padding-' + Craft.left, padding);
            }

            this._positionChanged = true;
        }

        // Keep in mind this could have also been set by onSortChange()
        if (this._positionChanged) {
            // Tell the server about the new position
            // -----------------------------------------------------------------

            var data = {
                layoutId: $('[name="layoutId"]').val(),
                items: [],
            };

            var $elements = this.getElements();

            // Outside of loop on purpose to allow us to reset the parent when kicking back a level
            var parentId = null;

            for (var i = 0; i < $elements.length; i++) {
                var $item = $($elements[i]);
                var $prevItem = $($elements[i-1]);

                if ($prevItem.length) {
                    if ($prevItem.data('level') < $item.data('level')) {
                        parentId = $prevItem.data('id');
                    }

                    if ($prevItem.data('level') > $item.data('level')) {
                        parentId = null;
                    }
                }

                data.items.push({
                    id: $item.data('id'),
                    level: $item.data('level'),
                    parentId: parentId,
                });
            }

            Craft.sendActionRequest('POST', 'cp-nav/navigation/reorder', { data })
                .then((response) => {
                    Craft.cp.displayNotice(response.data.message);

                    updateAllNav(response.data.navHtml);
                })
                .catch(({response}) => {
                    if (response && response.data && response.data.message) {
                        Craft.cp.displayError(response.data.message);
                    } else {
                        Craft.cp.displayError();
                    }
                });
        }
    },

    onSortChange: function() {
        this._positionChanged = true;
        this.base();
    },

    /**
     * Returns the min and max levels that the draggee could occupy between
     * two given rows, or false if it’s not going to work out.
     */
    _getLevelBounds: function($prevRow, $nextRow) {
        // Can't go any lower than the next row, if there is one
        if ($nextRow && $nextRow.length) {
            this._getLevelBounds._minLevel = $nextRow.data('level');
        } else {
            this._getLevelBounds._minLevel = 1;
        }

        // Can't go any higher than the previous row + 1
        if ($prevRow && $prevRow.length) {
            this._getLevelBounds._maxLevel = $prevRow.data('level') + 1;
        } else {
            this._getLevelBounds._maxLevel = 1;
        }

        // Does this structure have a max level?
        if (this.maxLevels) {
            // Make sure it's going to fit at all here
            if (
                this._getLevelBounds._minLevel != 1 &&
                this._getLevelBounds._minLevel + this._draggeeLevelDelta > this.maxLevels
            ) {
                return false;
            }

            // Limit the max level if we have to
            if (this._getLevelBounds._maxLevel + this._draggeeLevelDelta > this.maxLevels) {
                this._getLevelBounds._maxLevel = this.maxLevels - this._draggeeLevelDelta;

                if (this._getLevelBounds._maxLevel < this._getLevelBounds._minLevel) {
                    this._getLevelBounds._maxLevel = this._getLevelBounds._minLevel;
                }
            }
        }

        return {
            min: this._getLevelBounds._minLevel,
            max: this._getLevelBounds._maxLevel
        };
    },

    /**
     * Determines the min and max possible levels at the current draggee's position.
     */
    _setTargetLevelBounds: function() {
        this._targetLevelBounds = this._getLevelBounds(
            this.$draggee.first().prev(),
            this.$draggee.last().next()
        );
    },

    /**
     * Determines the target level based on the current mouse position.
     */
    _updateIndent: function(forcePositionChange) {
        // Figure out the target level
        // ---------------------------------------------------------------------

        // How far has the cursor moved?
        this._updateIndent._mouseDist = this.realMouseX - this.mousedownX;

        // Flip that if this is RTL
        if (Craft.orientation === 'rtl') {
            this._updateIndent._mouseDist *= -1;
        }

        // What is that in indentation levels?
        this._updateIndent._indentationDist = Math.round(this._updateIndent._mouseDist / Craft.CpNav.NavAdminTable.LEVEL_INDENT);

        // Combine with the original level to get the new target level
        this._updateIndent._targetLevel = this._draggeeLevel + this._updateIndent._indentationDist;

        // Contain it within our min/max levels
        if (this._updateIndent._targetLevel < this._targetLevelBounds.min) {
            this._updateIndent._indentationDist += (this._targetLevelBounds.min - this._updateIndent._targetLevel);
            this._updateIndent._targetLevel = this._targetLevelBounds.min;
        } else if (this._updateIndent._targetLevel > this._targetLevelBounds.max) {
            this._updateIndent._indentationDist -= (this._updateIndent._targetLevel - this._targetLevelBounds.max);
            this._updateIndent._targetLevel = this._targetLevelBounds.max;
        }

        // Has the target level changed?
        if (this._targetLevel !== (this._targetLevel = this._updateIndent._targetLevel)) {
            // Target level is changing, so update the ancestors
            this._updateAncestorsBeforeRepaint();
        }

        // Update the UI
        // ---------------------------------------------------------------------

        // How far away is the cursor from the exact target level distance?
        this._updateIndent._targetLevelMouseDiff = this._updateIndent._mouseDist - (this._updateIndent._indentationDist * Craft.CpNav.NavAdminTable.LEVEL_INDENT);

        // What's the magnet impact of that?
        this._updateIndent._magnetImpact = Math.round(this._updateIndent._targetLevelMouseDiff / 15);

        // Put it on a leash
        if (Math.abs(this._updateIndent._magnetImpact) > Craft.CpNav.NavAdminTable.MAX_GIVE) {
            this._updateIndent._magnetImpact = (this._updateIndent._magnetImpact > 0 ? 1 : -1) * Craft.CpNav.NavAdminTable.MAX_GIVE;
        }

        // Apply the new margin/width
        this._updateIndent._closestLevelMagnetIndent = this._getLevelIndent(this._targetLevel) + this._updateIndent._magnetImpact;
        this.helpers[0].css('margin-' + Craft.left, this._updateIndent._closestLevelMagnetIndent + this._helperMargin);
        this._$titleHelperCell.css('width', this._titleHelperCellOuterWidth - this._updateIndent._closestLevelMagnetIndent);
    },

    /**
     * Returns the indent size for a given level
     */
    _getLevelIndent: function(level) {
        return (level - 1) * Craft.CpNav.NavAdminTable.LEVEL_INDENT;
    },

    /**
     * Returns a row's ancestor rows
     */
    _getAncestors: function($row, targetLevel) {
        this._getAncestors._ancestors = [];

        if (targetLevel != 0) {
            this._getAncestors._level = targetLevel;
            this._getAncestors._$prevRow = $row.prev();

            while (this._getAncestors._$prevRow.length) {
                if (this._getAncestors._$prevRow.data('level') < this._getAncestors._level) {
                    this._getAncestors._ancestors.unshift(this._getAncestors._$prevRow);
                    this._getAncestors._level = this._getAncestors._$prevRow.data('level');

                    // Did we just reach the top?
                    if (this._getAncestors._level == 0) {
                        break;
                    }
                }

                this._getAncestors._$prevRow = this._getAncestors._$prevRow.prev();
            }
        }

        return this._getAncestors._ancestors;
    },

    /**
     * Prepares to have the ancestors updated before the screen is repainted.
     */
    _updateAncestorsBeforeRepaint: function() {
        if (this._updateAncestorsFrame) {
            Garnish.cancelAnimationFrame(this._updateAncestorsFrame);
        }

        this._updateAncestorsFrame = Garnish.requestAnimationFrame(this._updateAncestors.bind(this));
    },

    _updateAncestors: function() {
        this._updateAncestorsFrame = null;

        // Update the old ancestors
        // -----------------------------------------------------------------

        for (this._updateAncestors._i = 0; this._updateAncestors._i < this._ancestors.length; this._updateAncestors._i++) {
            this._updateAncestors._$ancestor = this._ancestors[this._updateAncestors._i];

            // One less descendant now
            this._updateAncestors._$ancestor.data('descendants', this._updateAncestors._$ancestor.data('descendants') - 1);
        }

        // Update the new ancestors
        // -----------------------------------------------------------------

        this._updateAncestors._newAncestors = this._getAncestors(this.$targetItem, this._targetLevel);

        for (this._updateAncestors._i = 0; this._updateAncestors._i < this._updateAncestors._newAncestors.length; this._updateAncestors._i++) {
            this._updateAncestors._$ancestor = this._updateAncestors._newAncestors[this._updateAncestors._i];

            // One more descendant now
            this._updateAncestors._$ancestor.data('descendants', this._updateAncestors._$ancestor.data('descendants') + 1);
        }

        this._ancestors = this._updateAncestors._newAncestors;

        delete this._updateAncestors._i;
        delete this._updateAncestors._$ancestor;
        delete this._updateAncestors._newAncestors;
    }
}, {
    HELPER_MARGIN: 0,
    LEVEL_INDENT: 22,
    MAX_GIVE: 22,
});

var NavAdminTable = new Craft.CpNav.NavAdminTable('#cp-nav-items');




// ----------------------------------------
// FUNCTIONS TO ASSIST WITH UPDATING THE CP NAV CLIENT-SIDE
// ----------------------------------------
// var badgeHandleIndex = {};
var updateAllNav = function(navHtml) {
    $('#global-sidebar nav#nav').html(navHtml);

    // Refresh any JS modifications
    new Craft.CpNav.InitMenuItems();
}


})(jQuery);
