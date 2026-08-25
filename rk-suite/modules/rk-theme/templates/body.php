<?php
/** RK Theme body template — renders a matched RK template as the page body. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
echo '<div class="rk-theme-body rk-theme-body--' . esc_attr( RK_Theme_Body::current_type() ) . '">';
RK_Theme_Body::render_content();
echo '</div>';
get_footer();
