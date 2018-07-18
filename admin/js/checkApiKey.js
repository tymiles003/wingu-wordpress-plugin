jQuery(document).ready(function($) {
    let $submit = $('#submit');
    $submit.hide();
    $("#api_key_checker").click(function() {
        $.get(ajaxurl, {
            action: "check_api_key",
            apikey: $("#wingu_setting_api_key").val()
        }, function(data) {
            if (data === 'Invalid') {
                $submit.prop('disabled', true);
                $("#wingu_setting_api_key").css('border-color', 'red');
            } else {
                $submit.show();
                $submit.prop('disabled', false);
                $("#wingu_setting_api_key").css('border-color', 'green');
            }
        });
    });
});