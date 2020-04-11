<?php

namespace Moneris\Checkout;

class Template
{
    public function display_template( $file, $params = null )
    {
        ob_start( );
        include MONERIS_WC_PLUGIN_DIR . '/templates/' . $file;
        $content = ob_get_contents( );
        ob_end_clean( );

        return $content;
    }
}
