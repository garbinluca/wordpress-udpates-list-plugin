<?php
/**
 * Plugin Name: WP Updates List
 * Plugin URI: https://www.lucagarbin.it
 * Description: Get wordpress installation updates list.
 * Version: 1.0
 * Author: Luca Garbin
 * Author URI: https://www.lucagarbin.it
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (!class_exists('WPUpdatesList')) :

    class WPUpdatesList
    {

        public $version = '1.0.0';

        public function initialize()
        {

            if (!$this->isRegisteredKey()) {
                add_action('admin_notices', array($this, 'showKeyAlert'));
            } else {
                add_action('init', array($this, 'registerRoute'));
            }

        }

        public function showKeyAlert()
        {

            echo '
            <div class="notice notice-warning is-dismissible">
                <p><strong>WP Updates List</strong>: <i>WP_UPDATES_LIST_KEY</i> must be defined on wp-config.php.</p>
            </div>
        ';

        }

        private function isRegisteredKey()
        {
            return !(!defined('WP_UPDATES_LIST_KEY') || !WP_UPDATES_LIST_KEY || trim(WP_UPDATES_LIST_KEY) == '');
        }

        public function registerRoute()
        {

            add_action('rest_api_init', function () {
                register_rest_route('wp-updates-list/v1', '/list', array(
                    'methods' => 'GET',
                    'callback' => array($this, 'getUpdates'),
                ));
            });

        }

        public function getUpdates(WP_REST_Request $request)
        {

            $param = $request['key'];
            $isInvalid = is_null($param) || trim($param) == '' || $param != WP_UPDATES_LIST_KEY;
            if ($isInvalid) {
                return new WP_REST_Response([
                    'message' => 'Invalid key'
                ], 401);
            }

            $data = [];
            $data['core'] = $this->getCoreUpdates();
            $data['plugins'] = $this->getPluinUpdates();


            return new WP_REST_Response($data, 200);
        }

        private function getCoreUpdates()
        {
            $options = array_merge(
                array(
                    'available' => true,
                    'dismissed' => false,
                ),
                []
            );
            $dismissed = get_site_option('dismissed_update_core');

            if (!is_array($dismissed)) {
                $dismissed = array();
            }

            $from_api = get_site_transient('update_core');

            if (!isset($from_api->updates) || !is_array($from_api->updates)) {
                return false;
            }

            $updates = $from_api->updates;
            $result = array();
            foreach ($updates as $update) {
                if ('autoupdate' === $update->response) {
                    continue;
                }

                if (array_key_exists($update->current . '|' . $update->locale, $dismissed)) {
                    if ($options['dismissed']) {
                        $update->dismissed = true;
                        $result[] = $update;
                    }
                } else {
                    if ($options['available']) {
                        $update->dismissed = false;
                        $result[] = $update;
                    }
                }
            }

            return $result;

        }

        private function getPluinUpdates() {
            $all_plugins     = get_plugins();
            $upgrade_plugins = array();
            $current         = get_site_transient( 'update_plugins' );
            foreach ( (array) $all_plugins as $plugin_file => $plugin_data ) {
                if ( isset( $current->response[ $plugin_file ] ) ) {
                    $upgrade_plugins[ $plugin_file ]         = (object) $plugin_data;
                    $upgrade_plugins[ $plugin_file ]->update = $current->response[ $plugin_file ];
                }
            }
            return $upgrade_plugins;
        }

    }

    function WPUpdatesListInstanceInit()
    {
        global $WPUpdatesListInstanceInit;

        if (!isset($WPUpdatesListInstanceInit)) {
            $WPUpdatesListInstanceInit = new WPUpdatesList();
            $WPUpdatesListInstanceInit->initialize();
        }
        return $WPUpdatesListInstanceInit;
    }

    WPUpdatesListInstanceInit();

endif;