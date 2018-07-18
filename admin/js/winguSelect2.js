jQuery(document).ready(function($) {
    let $triggers = $('#wingu_post_triggers');
    let $contents = $('#wingu_post_contents');
    $triggers.select2({
        width: '100%'
    });
    $contents.select2({
        width: '100%'
    });
    $contents.parent().hide();
    $("input[name=wingu_post_choice]").on("change", function() {
        let test = $(this).val();
        if (test === 'new-content') {
            $contents.parent().hide();
            $triggers.parent().show();
        } else if (test === 'existing-content') {
            $triggers.parent().hide();
            $contents.parent().show();
        }
    });
});