<?php
/**
 * Plugin Name:     Deploy Directly From GitHub and BitBucket
 * Plugin URI:      http://scriptburn.com/downloads/edd-scriptburn-deploy-from-git-bb
 * Description:     Use this plugin to deploy directly from github and bitbucket hosted code in your shop
 * Version:         1.3.0
 * Author:          Rajneesh ojha
 * Author URI:      http://scriptburn.com
 * Text Domain:     edd-scriptburn-deploy-from-git-bb
 *
 * @package         EDD\ScriptburnDeployFromGitBb
 * @author          Rajneesh ojha
 * @copyright       Copyright (c) Rajneesh ojha
 *
 */

// Exit if accessed directly
if (!defined('ABSPATH'))
{
    exit;
}

if (!class_exists('EDD_Scriptburn_Deploy_From_GIT_BB'))
{

    /**
     * Main EDD_Scriptburn_Deploy_From_GIT_BB class
     *
     * @since       1.0.0
     */
    class EDD_Scriptburn_Deploy_From_GIT_BB
    {

        /**
         * @var         EDD_Scriptburn_Deploy_From_GIT_BB $instance The one true EDD_Scriptburn_Deploy_From_GIT_BB
         * @since       1.0.0
         */
        private static $instance;

        /**
         * Get active instance
         *
         * @access      public
         * @since       1.0.0
         * @return      object self::$instance The one true EDD_Scriptburn_Deploy_From_GIT_BB
         */
        public static function instance()
        {
            if (!self::$instance)
            {
                self::$instance = new EDD_Scriptburn_Deploy_From_GIT_BB();
                self::$instance->setup_constants();
                self::$instance->includes();
                self::$instance->load_textdomain();
                self::$instance->hooks();
            }

            return self::$instance;
        }

        /**
         * Setup plugin constants
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function setup_constants()
        {
            // Plugin version
            define('EDD_DEPLOY_FROM_GITHUB_AND_BITBUCKET_VER', '1.0.0');

            // Plugin path
            define('EDD_SCRIPTBURN_DEPLOY_FROM_GITHUB_AND_BITBUCKET_DIR', plugin_dir_path(__FILE__));

            // Plugin URL
            define('EDD_DEPLOY_FROM_GITHUB_AND_BITBUCKET_URL', plugin_dir_url(__FILE__));
        }

        /**
         * Include necessary files
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         */
        private function includes()
        {
            // Include scripts
            require_once EDD_SCRIPTBURN_DEPLOY_FROM_GITHUB_AND_BITBUCKET_DIR . 'includes/scripts.php';
            require_once EDD_SCRIPTBURN_DEPLOY_FROM_GITHUB_AND_BITBUCKET_DIR . 'includes/functions.php';
            require_once EDD_SCRIPTBURN_DEPLOY_FROM_GITHUB_AND_BITBUCKET_DIR . 'includes/class.repo-api.php';

            require_once EDD_SCRIPTBURN_DEPLOY_FROM_GITHUB_AND_BITBUCKET_DIR . 'includes/class.gihub-repo-api.php';
            require_once EDD_SCRIPTBURN_DEPLOY_FROM_GITHUB_AND_BITBUCKET_DIR . 'includes/class.bitbucket-repo-api.php';

            // require_once EDD_SCRIPTBURN_DEPLOY_FROM_GITHUB_AND_BITBUCKET_DIR . 'includes/shortcodes.php';
            // require_once EDD_SCRIPTBURN_DEPLOY_FROM_GITHUB_AND_BITBUCKET_DIR . 'includes/widgets.php';
        }

        /**
         * Run action and filter hooks
         *
         * @access      private
         * @since       1.0.0
         * @return      void
         *
         */
        private function hooks()
        {
            // Register settings
            add_filter('edd_settings_extensions', array($this, 'settings'), 1);

            //hook to edd filter when saving download details
            add_filter('edd_metabox_save_edd_download_files', array($this, 'setup_repo_deploy_filters'));

            //hook to process Oauth

            if (isset($_REQUEST['debug']))
            {
                add_Action('admin_init', function ()
                {
                    /* edd_update_option('scb_bb_oauth_data','{"access_token": "woR6DyRqLlSU-52iHw4hhglhRZp6dM_I0P9xddTh8Pra3hv-7IWxhgePE77BUfVmGBJLdDWzGNTwZZcMAfE=", "scopes": "webhook issue repository account", "expires_in": 3600, "refresh_token": "78KNermXFbQHsfkwHR", "token_type": "bearer"}');
                    //die('x');
                     */
                    $str            = 'a:1:{i:0;a:4:{s:13:"attachment_id";s:1:"0";s:4:"name";s:5:"dsads";s:4:"file";s:25:"git://scriptburn/test@1.0";s:9:"condition";s:3:"all";}}';
                    $str            = unserialize($str);
                    $str[0]['file'] = 'bitbucket://rajneeshojha/test@1.0';

                    p_d($this->setup_repo_deploy_filters(($str)));
                    // scb_get_bitbucket_tag_download_url('rajneeshojha/wpmovies', '11.0', edd_get_option('scb_bitbucket_auth_token'));
                    // die('x');

                });
            }
            /*
            // only for testing purpose
            add_filter('http_request_host_is_external', function ()
            {
            return true;
            });
             */
            //if (!isset($_REQUEST['debug']))
            {
                // Handles urls associated with github
                add_filter('scb_repo_git_save_edd_download_files', array(SCB_GitHub_Repo_API(), 'fetch_from_repo'), 10, 2);
                add_filter('scb_repo_bitbucket_save_edd_download_files', array(SCB_BitBucket_Repo_API(), 'fetch_from_repo'), 10, 2);

            }
            add_action('init', array(SCB_GitHub_Repo_API(), 'do_api'));
            if (isset($_REQUEST['debug']))
            {

                //p_d(SCB_BitBucket_Repo_API()->api_hook(SCB_BitBucket_Repo_API()->test_api_data()));
            }
            add_action('admin_notices', array($this, 'admin_notice'));
            // Handle licensing
            if (class_exists('EDD_License'))
            {
                $license = new EDD_License(__FILE__, 'EDD Deploy From GitHub and BitBucket', EDD_DEPLOY_FROM_GITHUB_AND_BITBUCKET_VER, 'Rajneesh ojha');
            }
        }

        /**
         * Prepare download file array and call our filter acording to user entred repository url
         *
         * @access      public
         * @since       1.0.0
         * @return      void
         */
        public function setup_repo_deploy_filters($files)
        {
            $protocols = array();
            $new_files = array();

            $temp_indexes = array('repo', 'tag', 'package', 'protocol', 'tag_num');
            foreach ($files as $id => $file)
            {
                $protocol = null;
                if (!empty($files[$id]['file']))
                {
                    $file     = array_merge($file, scb_edd_merge_repo_input($files[$id]['file']));
                    $protocol = $file['protocol'];
                }
                // store all files with same protocol in same array index .so we can pass them to apprpriate filters at once
                $protocols[$protocol][] = $file;
            }
            p_l($protocols);
            foreach ($protocols as $protocol => $files)
            {

                $protocols[$protocol] = apply_filters('scb_repo_' . $protocol . '_save_edd_download_files', $files, $_REQUEST["post_ID"]);
                if (is_array($protocols[$protocol]))
                {

                    foreach ($protocols[$protocol] as $index => $file)
                    {
                        //remove our added custom index

                        $protocols[$protocol][$index] = scb_edd_clear_temp_indexes($protocols[$protocol][$index]);
                        // if the protocol in unprocesed remove name of file
                        if (substr($protocols[$protocol][$index]['file'], 0, strlen($protocol . "://")) == $protocol . "://" && !in_array($protocol, array('http', 'https')))
                        {
                            // $protocols[$protocol][$index]['file'] = '';
                        }
                    }

                    $new_files = array_merge($new_files, $protocols[$protocol]);
                }
            }
            return $new_files;
        }

        /**
         * Display the status of repository fetch opreation
         *
         * @access      public
         * @since       1.0.0
         * @return      void
         */
        public function admin_notice()
        {
            if (!isset($_REQUEST['post']))
            {
                return;
            }
            $error_trans = "scb_edd_repo_0_" . $_REQUEST['post'] . "_" . get_current_user_id();
            $pass_trans  = "scb_edd_repo_1_" . $_REQUEST['post'] . "_" . get_current_user_id();

            if ($error = get_transient($error_trans))
            {
                ?>
        <div class="error info">
            <p><?php echo $error; ?></p>
        </div><?php

            }
            if ($pass = get_transient($pass_trans))
            {
                ?>
        <div class="updated notice">
            <p><?php echo $pass; ?></p>
        </div><?php

            }
            delete_transient($pass_trans);
            delete_transient($error_trans);
        }
        /**
         * Internationalization
         *
         * @access      public
         * @since       1.0.0
         * @return      void
         */
        public function load_textdomain()
        {
            // Set filter for language directory
            $lang_dir = EDD_SCRIPTBURN_DEPLOY_FROM_GITHUB_AND_BITBUCKET_DIR . '/languages/';
            $lang_dir = apply_filters('edd_scriptburn_deploy_from_git_bb_languages_directory', $lang_dir);

            // Traditional WordPress plugin locale filter
            $locale = apply_filters('plugin_locale', get_locale(), 'edd-scriptburn-deploy-from-git-bb');
            $mofile = sprintf('%1$s-%2$s.mo', 'edd-scriptburn-deploy-from-git-bb', $locale);

            // Setup paths to current locale file
            $mofile_local  = $lang_dir . $mofile;
            $mofile_global = WP_LANG_DIR . '/edd-scriptburn-deploy-from-git-bb/' . $mofile;

            if (file_exists($mofile_global))
            {
                // Look in global /wp-content/languages/edd-scriptburn-deploy-from-git-bb/ folder
                load_textdomain('edd-scriptburn-deploy-from-git-bb', $mofile_global);
            }
            elseif (file_exists($mofile_local))
            {
                // Look in local /wp-content/plugins/edd-scriptburn-deploy-from-git-bb/languages/ folder
                load_textdomain('edd-scriptburn-deploy-from-git-bb', $mofile_local);
            }
            else
            {
                // Load the default language files
                load_plugin_textdomain('edd-scriptburn-deploy-from-git-bb', false, $lang_dir);
            }
        }

        /**
         * Add settings
         *
         * @access      public
         * @since       1.0.0
         * @param       array $settings The existing EDD settings array
         * @return      array The modified EDD settings array
         */
        public function settings($settings)
        {
            $new_settings = array(
                array(
                    'id'      => 'edd_scriptburn_deploy_from_git_bb_settings',
                    'name'    => '<strong>' . __('Deploy From GitHub and BitBucket Settings', 'edd-scriptburn-deploy-from-git-bb') . '</strong>',
                    'desc'    => __('Configure Deploy From GitHub and BitBucket Settings', 'edd-scriptburn-deploy-from-git-bb'),
                    'type'    => 'scb_html_callback',
                    'options' => array(
                        'callback' => function ($args)
                        {
                            echo ("<div id='scb_edd_oauth'>");
                            if (function_exists('edd_header_callback'))
                            {
                                edd_header_callback($args);
                            }
                            else
                            {
                                echo '<hr/>';
                            }
                            echo ("</div>");

                        }),
                ),
            );
            return array_merge($settings, $new_settings, SCB_GitHub_Repo_API()->setting_fields(), SCB_Bitbucket_Repo_API()->setting_fields());

        }
    }
} // End if class_exists check

/**
 * The main function responsible for returning the one true EDD_Scriptburn_Deploy_From_GIT_BB
 * instance to functions everywhere
 *
 * @since       1.0.0
 * @return      \EDD_Scriptburn_Deploy_From_GIT_BB The one true EDD_Scriptburn_Deploy_From_GIT_BB
 *
 */
function EDD_Scriptburn_Deploy_From_GIT_BB_load()
{
    if (!class_exists('Easy_Digital_Downloads'))
    {
        if (!class_exists('EDD_Extension_Activation'))
        {
            require_once 'includes/class.extension-activation.php';
        }

        $activation = new EDD_SCB_Deploy_Activation(plugin_dir_path(__FILE__), basename(__FILE__));
        $activation = $activation->run();
    }
    else
    {
        return EDD_Scriptburn_Deploy_From_GIT_BB::instance();
    }
}
add_action('plugins_loaded', 'EDD_Scriptburn_Deploy_From_GIT_BB_load');

/**
 * The activation hook is called outside of the singleton because WordPress doesn't
 * register the call from within the class, since we are preferring the plugins_loaded
 * hook for compatibility, we also can't reference a function inside the plugin class
 * for the activation function. If you need an activation function, put it here.
 *
 * @since       1.0.0
 * @return      void
 */
function edd_scriptburn_deploy_from_git_bb_activation()
{
    /* Activation functions here */
}
register_activation_hook(__FILE__, 'edd_scriptburn_deploy_from_git_bb_activation');
