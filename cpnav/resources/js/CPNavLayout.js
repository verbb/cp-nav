$(function() {


    // ----------------------------------------
    // LAYOUTS
    // ----------------------------------------

    var LayoutAdminTable = new Craft.AdminTable({
        tableSelector: '#layoutItems',
        sortable: false,
        deleteAction: 'cpNav/layout/delete',
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

            Craft.postActionRequest('cpNav/layout/getHudHtml', this.data, $.proxy(this, 'showHud'));
        },

        showHud: function(response, textStatus) {
            this.$element.removeClass('loading');

            if (textStatus == 'success') {
                var $hudContents = $();

                this.$form = $('<div/>');
                $fieldsContainer = $('<div class="fields"/>').appendTo(this.$form);

                $fieldsContainer.html(response.html)
                Craft.initUiElements($fieldsContainer);

                var $footer = $('<div class="hud-footer"/>').appendTo(this.$form),
                    $buttonsContainer = $('<div class="buttons right"/>').appendTo($footer);
                this.$cancelBtn = $('<div class="btn">'+Craft.t('Cancel')+'</div>').appendTo($buttonsContainer);
                this.$saveBtn = $('<input class="btn submit" type="submit" value="'+Craft.t('Save')+'"/>').appendTo($buttonsContainer);
                this.$spinner = $('<div class="spinner hidden"/>').appendTo($buttonsContainer);

                $hudContents = $hudContents.add(this.$form);

                this.hud = new Garnish.HUD(this.$element, $hudContents, {
                    bodyClass: 'body',
                    closeOtherHUDs: false
                });

                this.hud.on('hide', $.proxy(this, 'closeHud'));

                Garnish.$bod.append(response.footerJs);

                this.addListener(this.$saveBtn, 'click', 'save');
                this.addListener(this.$cancelBtn, 'click', 'closeHud');
            }
        },

        save: function(ev) {
            ev.preventDefault();

            this.$spinner.removeClass('hidden');

            var data = this.hud.$body.serialize();

            Craft.postActionRequest('cpNav/layout/save', data, $.proxy(function(response, textStatus) {
                this.$spinner.addClass('hidden');

                if (textStatus == 'success' && response.success) {
                    this.$element.html('<strong>'+response.layout.name+'</strong>');

                    Craft.cp.displayNotice('Layout saved.');

                    this.closeHud();
                } else {
                    Garnish.shake(this.hud.$hud);
                }
            }, this));
        },

        closeHud: function() {
            this.hud.$shade.remove();
            this.hud.$hud.remove();
        }
    });





    // ----------------------------------------
    // ALLOW HUD TO ADD LAYOUT
    // ----------------------------------------

    $(document).on('click', '.add-new-layout', function(e) {
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

            Craft.postActionRequest('cpNav/layout/getHudHtml', {}, $.proxy(this, 'showHud'));
        },

        showHud: function(response, textStatus) {
            this.$element.removeClass('loading');

            if (textStatus == 'success') {
                var $hudContents = $();

                this.$form = $('<div/>');
                $fieldsContainer = $('<div class="fields"/>').appendTo(this.$form);

                $fieldsContainer.html(response.html)
                Craft.initUiElements($fieldsContainer);

                var $footer = $('<div class="hud-footer"/>').appendTo(this.$form),
                    $buttonsContainer = $('<div class="buttons right"/>').appendTo($footer);
                this.$cancelBtn = $('<div class="btn">'+Craft.t('Cancel')+'</div>').appendTo($buttonsContainer);
                this.$saveBtn = $('<input class="btn submit" type="submit" value="'+Craft.t('Save')+'"/>').appendTo($buttonsContainer);
                this.$spinner = $('<div class="spinner hidden"/>').appendTo($buttonsContainer);

                $hudContents = $hudContents.add(this.$form);

                this.hud = new Garnish.HUD(this.$element, $hudContents, {
                    bodyClass: 'body',
                    closeOtherHUDs: false
                });

                this.hud.on('hide', $.proxy(this, 'closeHud'));

                Garnish.$bod.append(response.footerJs);

                this.addListener(this.$saveBtn, 'click', 'save');
                this.addListener(this.$cancelBtn, 'click', 'closeHud');
            }
        },

        save: function(ev) {
            ev.preventDefault();

            this.$spinner.removeClass('hidden');

            var data = this.hud.$body.serialize();

            Craft.postActionRequest('cpNav/layout/new', data, $.proxy(function(response, textStatus) {
                this.$spinner.addClass('hidden');

                if (textStatus == 'success' && response.success) {
                    Craft.cp.displayNotice('Layout created.');

                    var newLayout = response.layouts;

                    var $tr = LayoutAdminTable.addRow('<tr class="layout-item" data-id="'+newLayout.id+'" data-name="'+newLayout.name+'">' +
                        '<td>' +
                            '<a class="edit-layout"><strong>'+newLayout.name+'</strong></a>' +
                        '</td>' +
                        '<td class="thin">' +
                            '<a class="delete icon" title="Delete" role="button"></a>' +
                        '</td>' +
                    '</tr>');

                    this.closeHud();
                } else {
                    Garnish.shake(this.hud.$hud);
                }
            }, this));
        },

        closeHud: function() {
            this.hud.$shade.remove();
            this.hud.$hud.remove();
        }
    });





});


