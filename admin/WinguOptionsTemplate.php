<form method="POST" action="options.php">
    <?php
    settings_fields('wingu-options');
    do_settings_sections('wingu-options');
    submit_button();
    ?>
</form>