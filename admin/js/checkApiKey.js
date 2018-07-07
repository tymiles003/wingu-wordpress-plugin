jQuery(document).ready(function($) {
    $('#submit').hide();
    $("#api_key_checker").click(function() {
        $.get(ajaxurl, {
            // _ajax_nonce: my_ajax_obj.nonce,     //nonce
            action: "check_api_key",            //action
            apikey: $("#wingu_setting_api_key").val()                 //data
        }, function(data) {                    //callback
            if (data === 'Invalid') {
                $('#submit').prop('disabled', true);//insert server response
                // $("#triggers_tab").attr('href', '').css({'cursor': 'not-allowed'});
            } else {
                $('#submit').show();
                $('#submit').prop('disabled', false);
                // $("#triggers_tab").attr('href', '?page=wingu-options&tab=triggers').css({'cursor': 'default'});
            }
        });
    });
});