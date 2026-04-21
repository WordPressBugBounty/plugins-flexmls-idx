var locationControlView = elementor.modules.controls.BaseData.extend({
    onReady: function () {
        var self = this,
            input_val = jQuery(this.ui.input[0]),
            $wrap = this.$el.find('.elementor-control-field-location').first(),
            apply = input_val.closest('.elementor-control-field-location').find('.flexmls_connect__location_button_apply');

        if (typeof window.AdminLocationSearch === 'function' && $wrap.length) {
            this.fmcAdminLocationSearch = new window.AdminLocationSearch($wrap);
        }

        jQuery(apply).on('click.flexmlsLocationCtrl', function () {
            self.saveValue();
        });
    },

    saveValue: function () {
        this.setValue(this.ui.input[0].value);
    },

    onBeforeDestroy: function () {
        this.saveValue();
        if (this.fmcAdminLocationSearch && typeof this.fmcAdminLocationSearch.destroy === 'function') {
            this.fmcAdminLocationSearch.destroy();
        }
    }
});

elementor.addControlView('location_control', locationControlView);