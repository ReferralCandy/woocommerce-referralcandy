<div class="wrap">
    <h2>WP Refferal Candy</h2>
    <form method="post" action="options.php"> 
        <?php @settings_fields('wp_referralcandy-group'); ?>
        <?php @do_settings_fields('wp_referralcandy-group'); ?>

        <?php do_settings_sections('wp_referralcandy'); ?>

        <?php @submit_button(); ?>
    </form>
</div>