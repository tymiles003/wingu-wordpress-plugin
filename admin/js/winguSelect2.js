jQuery(document).ready(function($) {
    let $triggers = $('#wingu_post_triggers');
    let $content = $('#wingu_post_content');
    $triggers.select2({
        width: '100%',
        // wingu.api_url
    });
    $content.select2({
        width: '100%'
    });

    $content.parent().hide();
    $triggers.parent().hide();

    $("#wingu_post_choice").on("change", function() {
        let test = $(this).val();
        if (test === 'new-content') {
            $triggers.parent().show();
            $content.parent().hide();
        } else if (test === 'existing-content') {
            $triggers.parent().hide();
            $content.parent().show();
        } else {
            $triggers.parent().hide();
            $content.parent().hide();
        }
    });
});