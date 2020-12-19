<?php

if (false === apply_filters('nsl-pro', false)) {
    $current = time();
    if ($current <= mktime(0, 0, 0, 9, 2, 2020)) {
        if (get_transient('nsl_summer_2020') != '1') {

            add_action('admin_enqueue_scripts', function () {
                wp_enqueue_script('jquery');
            });

            add_action('admin_notices', function () {
                ?>
                <div class="notice notice-info is-dismissible" data-nsldismissable="">
                    <h3>Nextend Social Login - Summer Sale</h3>
                    <p>
                        Get your <b>Pro Addon</b> with <b>30% OFF</b>! Limited time offer expires on September 1.</p>
                    <p>
                        <a class="button button-primary" href="https://nextendweb.com/social-login/?coupon=SUMMER2020NSL&utm_source=wpfree&utm_medium=wp&utm_campaign=summer2020nsl#pricing" target="_blank">Buy
                            Now!</a>
                        <a class="button button-dismiss" href="#">Dismiss</a>
                    </p>
                </div>
                <?php
            });

            add_action('admin_footer', function () {
                ?>
                <script type="text/javascript">
                    (function ($) {
                        $(function () {
                            setTimeout(function () {
                                $('div[data-nsldismissable] .notice-dismiss, div[data-nsldismissable] .button-dismiss')
                                    .on('click', function (e) {
                                        e.preventDefault();
                                        $.post(ajaxurl, {
                                            'action': 'nsl_dismiss_admin_notice',
                                            'nonce': <?php echo json_encode(wp_create_nonce('nsl-dismissible-notice')); ?>
                                        });
                                        $(e.target).closest('.is-dismissible').remove();
                                    });
                            }, 1000);
                        });
                    })(jQuery);
                </script>
                <?php
            });

            add_action('wp_ajax_nsl_dismiss_admin_notice', function () {
                check_ajax_referer('nsl-dismissible-notice', 'nonce');

                set_transient('nsl_summer_2020', '1', YEAR_IN_SECONDS);
                wp_die();
            });
        }
    }
}
