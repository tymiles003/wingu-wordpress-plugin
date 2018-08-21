jQuery(document).ready(function($) {
    let $submit = $('#wingu_settings_submit');
    let $apikey = $("#wingu_setting_api_key");
    $submit.hide();
    $("#api_key_checker").click(function() {
        $.get(ajaxurl, {
            action: "check_api_key",
            apikey: $apikey.val()
        }, function(data) {
            if (data === 'Invalid') {
                $submit.hide();
                $submit.prop('disabled', true);
                $apikey.css('border-color', 'red');
            } else {
                $submit.show();
                $submit.prop('disabled', false);
                $apikey.css('border-color', 'green');
            }
        });
    });
});