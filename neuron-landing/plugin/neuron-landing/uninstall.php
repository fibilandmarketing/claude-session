<?php
/**
 * Removes the plugin's saved content when it is deleted from the Plugins screen.
 * (Deactivating keeps everything.)
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
delete_option( 'neuron_landing' );
delete_option( 'nrn_rev' );
