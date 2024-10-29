(function ($) {
    'use strict';

    // Document ready
    $(function () {
        if (typeof wpcf7 !== 'undefined') {
            wpcf7.cached = 0;
        }
    });

    // On window load
    $(window).on('load', function () {
        // $( 'table.wc_gateways tr[ data-gateway_id=apple]' ).addClass( 'hidden' );
        // $( 'table.wc_gateways tr[ data-gateway_id=acquired]' ).addClass( 'hidden' );
        // $( 'table.wc_gateways tr[ data-gateway_id=google]' ).addClass( 'hidden' );

    });
})(jQuery);
