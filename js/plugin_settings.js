window.GFConstantContactSettings = null;

(function ($) {
    GFConstantContactSettings = function () {
        var self = this;

        this.init = function () {
            this.pageURL = gform_constantcontact_pluginsettings_strings.settings_url;

            this.bindDeauthorize();

            this.bindCustomAppAuthorize();
        }

        this.bindCustomAppAuthorize = function () {
            $('#gform_constantcontact_custom_auth_button').on('click', function (e) {
                e.preventDefault();
                self.customAppAuthorize(e)
            });
            $('#custom_app_key, #custom_app_secret').on('keydown', function (e) {
                if (e.keyCode === 13) {
                    e.preventDefault();
                    self.customAppAuthorize(e);
                }
            });
        }

        this.customAppAuthorize = function () {
            var appKey = $('#custom_app_key').val(),
                appSecret = $('#custom_app_secret').val();
            if (appKey !== '' && appSecret !== '') {
                $.ajax({
                    url: ajaxurl,
                    data: {
                        action: 'gfconstantcontact_get_auth_url',
                        nonce: gform_constantcontact_pluginsettings_strings.ajax_nonce,
                        custom_app_key: appKey,
                        custom_app_secret: appSecret
                    },
                    success: function( response ) {
                        if ( response.success ) {
                            window.location.href = response.data;
                        }
                        else {
                            window.location.href = self.pageURL +
                                '&auth_error=true';
                        }
                    },
                });
            }
        }

        this.bindDeauthorize = function () {
            // De-Authorize Constant Contact.
            $('#gform_constantcontact_deauth_button').on('click', function (e) {
                e.preventDefault();

                // Get button.
                var $button = $('#gform_constantcontact_deauth_button');

                // Confirm deletion.
                if (!confirm(gform_constantcontact_pluginsettings_strings.disconnect)) {
                    return false;
                }

                // Set disabled state.
                $button.attr('disabled', 'disabled');

                // De-Authorize.
                $.ajax({
                    async: false,
                    url: ajaxurl,
                    dataType: 'json',
                    data: {
                        action: 'gfconstantcontact_deauthorize',
                        nonce: gform_constantcontact_pluginsettings_strings.ajax_nonce
                    },
                    success: function (response) {
                        if (response.success) {
                            window.location.href = self.pageURL;
                        } else {
                            alert(response.data.message);
                        }

                        $button.removeAttr('disabled');
                    }
                });

            });
        }

        this.init();
    }

    $(document).ready(GFConstantContactSettings);
})(jQuery);
