<?php
/**
 * Repositry API base class
 *
 * @package     EDD\ActivationHandler
 * @since       1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH'))
{
    exit;
}

/**
 * EDD Extension Activation Handler Class
 *
 * @since       1.0.0
 */
abstract class SCB_Repo_API
{

    public $options;
    protected $auth_end_point, $api_end_point, $text;

    /**
     * Setup the activation class
     *
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    public function __construct($id, $text, $auth_end_point, $api_end_point, $options)
    {
        $this->auth_end_point = rtrim($auth_end_point, "/");
        $this->api_end_point  = rtrim($api_end_point, "/");
        $this->text           = $text;
        $this->options        = $options;
        $this->id             = $id;

    }

    //get called to verify nonce token
    abstract public function nonce_check();

    // ad required setting fields
    //abstract public function setting_fields();

    // renders Authorize button
    abstract public function render_client_token();

    // get called after oauth get completed
    abstract public function after_oauth($response);

    // get called to check if the api response was failed due to not authorized yet
    abstract public function is_authorized_response($response);

    // get called to generate download url of repo tag
    abstract public function translate_short_repo_url($repo, $tag);

    // get called when repositry web hook received
    abstract public function download_update($json, $post);

    /**
     * Return Class options
     *
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    public function option($name, $default = false)
    {
        return isset($this->options[$name]) ? $this->options[$name] : $derfault;
    }

    /**
     * Return stored setting
     *
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    public function get_setting($name)
    {
        $name = $this->with_prefix($name);
        return edd_get_option($name, false);
    }

/**
 * Return Oauth data which we stored in db after oauth
 *
 * @access      public
 * @since       1.0.0
 * @return      void
 */
    public function get_oauth_data($name)
    {
        $oauth_data = @json_decode(edd_get_option($this->with_prefix('oauth_data'), false));

        if (is_array($oauth_data))
        {
            return isset($oauth_data[$name]) ? $oauth_data[$name] : false;
        }
        elseif (is_object($oauth_data))
        {
            return property_exists($oauth_data, $name) ? $oauth_data->$name : false;
        }
        return false;
    }

    protected function with_prefix($name)
    {
        return 'scb_' . $this->id . "_" . $name;
    }

    public function oauth_page_url($action = null, $args = null)
    {
        $local = 0;
        $args  = is_null($args) ? array() : $args;
        if (!is_null($action))
        {
            $args['scb_deploy_action'] = $action;
        }
        $args = array_merge(array('scb_deploy_callback_for' => $this->id), $args);
        return untrailingslashit($local ? "http://dunzzwoktg.localtunnel.me" : home_url()) . "/?" . http_build_query($args);
    }
    public function common_setting_fields()
    {

        $new_settings   = array();
        $texts          = $this->option('texts');
        $default_fields = $this->option('default_fields');
        // $options        = is_array($options) ? $options : array();
        $id = $this->id;
        $th = $this;

        $new_settings[] = array(
            'id'   => $this->with_prefix('withhook'),
            'name' => sprintf('Enable webhook ?', $this->text),
            'desc' => __(sprintf('If webhook is enabled every time you push a new tag or modify current tag, download get updated automatically', $this->text), 'edd-scriptburn-deploy-from-git-bb'),
            'type' => 'checkbox',

        );

        if (isset($default_fields['callback_url']))
        {
            $new_settings[] = array(
                'id'      => $this->with_prefix('callback_url'),
                'name'    => isset($texts['setting_name_callback_url']) ? $texts['setting_name_callback_url'] : sprintf('%1$s Authorization callback URL', $this->text),
                'desc'    => isset($texts['setting_desc_callback_url']) ? $texts['setting_desc_callback_url'] : __(sprintf('Enter this url in "Authorization callback URL" field when registering a new OAuth application in %1$s', $this->text), 'edd-scriptburn-deploy-from-git-bb'),
                'type'    => 'scb_html_callback',
                'options' => array('callback' => function () use ($th)
                {
                    echo ("<code><strong>" . $th->oauth_page_url() . "</strong></code>");
                }),
            );
        }
        if (isset($default_fields['client_id']))
        {
            $new_settings[] = array(
                'id'   => $this->with_prefix('client_id'),
                'name' => isset($texts['setting_name_client_id']) ? $texts['setting_name_client_id'] : sprintf('%1$s Client Id', $this->text),
                'type' => 'text',
                'desc' => isset($texts['setting_desc_client_id']) ? $texts['setting_desc_client_id'] :
                __(sprintf('Enter %1$s Client Id here ', $this->text), 'edd-scriptburn-deploy-from-git-bb'),
            );
        }

        if (isset($default_fields['client_secret']))
        {
            $new_settings[] = array(
                'id'   => $this->with_prefix('client_secret'),
                'name' => isset($texts['setting_name_client_secret']) ? $texts['setting_name_client_secret'] : sprintf('%1$s Client Secret', $this->text),
                'type' => 'password',
                'desc' => isset($texts['setting_desc_client_secret']) ? $texts['setting_desc_client_secret'] :
                __(sprintf('Enter %1$s Client Secret here ', $this->text), 'edd-scriptburn-deploy-from-git-bb'),
            );
        }
        /*
        if (isset($default_fields['auth_token']))
        {
        $new_settings[] = array(
        'id'   => $this->with_prefix('auth_token'),
        'name' => isset($texts['setting_name_auth_token']) ? $texts['setting_name_auth_token'] : sprintf('%1$s Authorization Token', $this->text),
        'desc' => isset($texts['setting_desc_auth_token']) ? $texts['setting_desc_auth_token'] :
        __(sprintf('Authorization Token received from %1$s', $this->text), 'edd-scriptburn-deploy-from-git-bb'),
        'type' => 'text',
        );
        }
         */
        if (isset($default_fields['client_token']))
        {
            $new_settings[] = array(
                'id'      => $this->with_prefix('client_token'),
                'name'    => '',
                'type'    => 'scb_html_callback',
                'options' => array('callback' => function () use ($th)
                {
                    $th->render_client_token();

                }),
            );
        }
        return $new_settings;
    }
    public function do_api()
    {

        $valid_repo = array_flip(array('gh', 'bb'));
        if (!isset($_GET['scb_deploy_callback_for']) || !isset($valid_repo[$_REQUEST['scb_deploy_callback_for']]))
        {
            return;
        }

        $func = false;
        if (isset($_GET['scb_deploy_action']) && $_GET['scb_deploy_action'] !== 'auth')
        {

            $func = 'api_' . $_GET['scb_deploy_action'];

        }
        else
        {
            $func = 'api_auth';
        }

        if ($func && method_exists($this, $func))
        {
            call_user_func_array(array($this, $func), array());

        }
    }
    /**
     * Process the webhook and updates the correct download
     *
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    public function do_download_update($protocol, $repo_to_update, $new_version)
    {
        try
        {
            global $wpdb;
            $repo  = $protocol . '://' . esc_sql($repo_to_update) . '/@';
            $sql   = "SELECT a.post_title,b.post_id,b.meta_value FROM $wpdb->posts a inner join $wpdb->postmeta b on a.ID=b.post_id WHERE b.meta_key = '_scb_edd_repo_url' AND  b.meta_value like '%$repo%' ";
            $posts = $wpdb->get_results($sql, ARRAY_A);

            p_l($posts);
            if (!is_array($posts) || !count($posts))
            {
                throw new Exception('nothing to process ');
            }
            $failed = array();
            $passed = array();
            foreach ($posts as $download)
            {
                try
                {
                    if (!isset($download['meta_value']))
                    {
                        throw new Exception('Download to update not found was looking for ' . $repo);
                    }
                    $repo_to_update_url = @unserialize($download['meta_value']);
                    if (!is_array($repo_to_update_url))
                    {
                        throw new Exception('Download to update was found but data is not valid.Received ' . $download['meta_value']);
                    }

                    $files = get_post_meta($download['post_id'], 'edd_download_files', true);
                    $found = false;
                    foreach ($files as $index => $file)
                    {
                        if (isset($repo_to_update_url[$file['attachment_id']]))
                        {
                            $found = $index;
                            break;
                        }
                    }
                    if ($found === false || empty($repo_to_update_url[$file['attachment_id']]['url']))
                    {
                        throw new Exception('Unable to find correct download to update ' . print_r($files, true) . "----" . print_r($repo_to_update_url, true));
                    }
                    $file = array_merge($files[$index], scb_edd_merge_repo_input($repo_to_update_url[$file['attachment_id']]['url']));

                    $file['new_tag'] = scb_edd_clean_tag_input($new_version);
                    p_l($file);
                    if (version_compare($file['new_tag'], $file['tag_num'], 'lt'))
                    {
                        p_l("lower version pushed skip");
                        throw new Exception('lower version pushed skipping ' . $file['new_tag'] . "<" . $file['tag_num']);

                        return;
                    }
                    elseif (version_compare($file['new_tag'], $file['tag_num'], 'gt'))
                    {
                        $file = array_merge($file, scb_edd_merge_repo_input('git://' . $repo_to_update . '/@' . $file['new_tag']));
                        //$file['repo_url'] = 'git://' . $repo_to_update . '/@' . $file['new_tag'];
                        //$file['tag']      = $file['new_tag'];
                    }
                    elseif (version_compare($file['new_tag'], $file['tag_num'], 'eq'))
                    {
                        $file = array_merge($file, scb_edd_merge_repo_input('git://' . $repo_to_update . '/@' . $file['new_tag']));

                    }
                    else
                    {
                        throw new Exception('Unable to get new version');
                    }
                    p_l($file);
                    $file = apply_filters('scb_repo_' . $protocol . '_save_edd_download_files', array($file), $download['post_id']);

                    $files[$index] = scb_edd_clear_temp_indexes($file[0]);
                    p_l($files);
                    update_post_meta($download['post_id'], 'edd_download_files', $files);
                    $passed[] = sprintf('Download %1$s updated succesfuly from %2$s respositry %3$s tag %4$s ', isset($download['post_title']) ? '"' . $download['post_title'] . '"' : '', $text, $repo_to_update, $new_version);

                }
                catch (Exception $e)
                {
                    $failed[] = "Failed to Update download :" . (isset($download['post_title']) ? '"' . $download['post_title'] . '" ' : '') . $e->getMessage();
                }
            }

            if (count($passed) && count($failed))
            {
                $msg = "Successfull:\n" . implode("\n", $passed) . "\n\n";
                $msg .= "Failed:\n" . implode("\n", $failed);
                p_l($msg);
                scb_edd_send_email('Download Deploy Result', $msg);
                return array(1, 'done but few failed');
            }
            elseif (count($failed))
            {
                $msg = implode("\n", $failed);
                p_l($msg);
                scb_edd_send_email('Download Failed to deploy from repo', $msg);
                return array(0, 'failed');
            }
            elseif (count($passed))
            {
                $msg = implode("\n", $passed);
                p_l($msg);
                scb_edd_send_email('Download Deployed from repo succesfuly', $msg);
                return array(1, 'All done');
            }

        }
        catch (Exception $e)
        {
            $msg = "Failed to Update download :" . (isset($download['post_title']) ? '"' . $download['post_title'] . '" ' : '') . $e->getMessage();
            p_l($msg);
            scb_edd_send_email('Download Failed to deploy from repo', $msg);

            return array(0, $msg);
        }

    }
    /**
     * Received repo webhooks
     *
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    public function api_hook($input = null)
    {
        define('SCB_IN_API_HOOK', 1);
        global $wpdb;
        $post = is_null($input) ? file_get_contents('php://input') : $input;
        $post = @json_decode($post) ? json_decode($post) : $post;

        //is this hook is from github
        //p_l((property_exists($post, 'repository') ? "1:1" : '1:0') . "-" . (property_exists($post->repository, 'html_url') ? "2:1" : '2:0') . "-" . (stripos($post->repository->html_url, '://github.com') === false ? "3:0" : '3:1'));

        // p_d($post->push->changes[0]->new->links->self->href);

        //https: //api.bitbucket.org/2.0/repositories/rajneeshojha/test/refs/tags/v1.0
        $ret = false;
        if (is_object($post) &&
            property_exists($post, 'repository') &&
            property_exists($post->repository, 'html_url') &&
            stripos($post->repository->html_url, '://github.com') !== false &&
            property_exists($post, 'ref') && stripos($post->ref, 'refs/tags/') !== false
        )
        {
            if (SCB_GitHub_Repo_API()->get_setting('withhook'))
            {
                $ret = SCB_GitHub_Repo_API()->download_update($post, $_POST);
            }
        }
        elseif (isset($post->push->changes[0]) && isset($post->push->changes[0]->new->links->self->href) &&
            stripos($post->push->changes[0]->new->links->self->href, '://api.bitbucket.org/') !== false &&
            stripos($post->push->changes[0]->new->links->self->href, '/refs/tags') !== false

        )
        {
            if (SCB_BitBucket_Repo_API()->get_setting('withhook'))
            {
                $ret = SCB_BitBucket_Repo_API()->download_update($post, $_POST);
            }
        }
        if (is_array($ret))
        {
            die(implode("-", $ret));
        }
        else
        {
            die($ret);
        }
    }
/**
 * Process Oauth
 *
 * @access      public
 * @since       1.0.0
 * @return      void
 */
    public function api_auth()
    {
        try
        {
            if (!isset($_REQUEST['code']))
            {
                throw new Exception('Invalid Parameters');
            }

            $repo          = $_REQUEST['scb_deploy_callback_for'] == 'gh' ? SCB_GitHub_Repo_API() : SCB_BitBucket_Repo_API();
            $texts         = $repo->option('texts');
            $client_id     = $repo->get_setting('client_id');
            $client_secret = $repo->get_setting('client_secret');

            if (!$this->nonce_check())
            {
                throw new Exception('Nonce verification failed1');
            }
            elseif (isset($_REQUEST['error_description']))
            {
                throw new Exception($_REQUEST['error_description']);
            }
            elseif (!$client_id)
            {
                throw new Exception(sprintf('Invalid  %1$s %2$s', $repo->text, isset($texts['client_id']) ? $texts['client_id'] : 'Client Id'));
            }
            elseif (!$client_secret)
            {
                throw new Exception(sprintf('Invalid  %1$s %2$s', $repo->text, isset($texts['client_secret']) ? $texts['client_secret'] : 'Client Secret'));
            }
            else
            {

                $args['body'] = array(
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'code'          => $_REQUEST['code'],
                    'redirect_uri'  => $repo->oauth_page_url('verify'),
                    'state'         => $_REQUEST['state'],
                );
                if ($_REQUEST['scb_deploy_callback_for'] == 'bb')
                {
                    $args['body']['grant_type'] = 'authorization_code';
                    unset($args['body']['redirect_uri']);

                }
                $args['headers'] = array('Accept' => 'application/json');

                $response = wp_remote_post($repo->auth_end_point . "/access_token", $args);
                if (is_wp_error($response))
                {
                    throw new Exception("Something went wrong: $error_message");
                }
                elseif (empty($response['body']))
                {
                    throw new Exception("Empty response received from " . $repo->text . " Oauth");
                }
                elseif (!is_object($body = @json_decode($response['body'])))
                {
                    throw new Exception("Invalid response received from " . $repo->text . " Oauth");
                }
                elseif (property_exists($body, 'message'))
                {
                    throw new Exception("Something went wrong while doing " . $repo->text . " Oauth:" . $body->message);
                }
                elseif (!property_exists($body, 'access_token') || empty($body->access_token))
                {
                    //p_n($body);
                    throw new Exception("No access token received from " . $repo->text . " Oauth");
                }
                else
                {

                    edd_update_option($repo->with_prefix('oauth_data'), $response['body']);
                    // p_n($repo->with_prefix('oauth_data'));

                    echo ("<script>\n");
                    echo ("document.location='" . edd_scb_setting_page_url() . "';\n");
                    echo ("</script>\n");
                }
            }
        }
        catch (Exception $e)
        {
            $this->oauth_error($e->getMessage());
        }
    }

    public function oauth_error($error)
    {
        $html = '<h3 style="color:red">%1$s</h3><input type="button" value="Continue" onclick="document.location=%2$s"/>';
        printf($html, $error, "'" . edd_scb_setting_page_url() . "'");
        wp_die();
    }

    protected function send_api_cmd($url, $data = null, $method = 'get', $json = false)
    {

        try
        {
            $token = $this->get_oauth_data('access_token');
            if (!$token)
            {
                throw new Exception('You must authorize ' . $this->text . " Access ");
            }
            // p_d($this->get_oauth_data('access_token'));
            $url = add_query_arg(array('access_token' => $this->get_oauth_data('access_token') . "=", 'rand' => microtime(true)), $url);
            if ($method === 'get')
            {
                $response = scb_edd_remote_get($url);
            }
            else
            {
                $response = scb_edd_post($url, $data, $json);
            }
            if ($response instanceof WP_Error)
            {
                throw new Exception($response->get_error_message());
            }
            elseif (empty($response['body']))
            {
                throw new Exception('Invalid response from ' . $this->text . '(1)');
            }
            elseif (!($api_response = @json_decode($response['body'])))
            {

                throw new Exception('Invalid response  from ' . $this->text . '(2)');
            }

            $authd = $this->is_authorized_response($api_response);
            if (!is_null($authd))
            {
                return $authd;
            }

            return array(1, $api_response, $response['body']);
        }
        catch (Exception $e)
        {
            return array(0, $e->getMessage());
        }
    }
    public function save_remote_tag_url($attachment, $url, $post)
    {
        $post_id=$post->ID;
        $last_attachment = '';
        try
        {

            if (empty($_POST['ID']))
            {
                //throw new Exception('No parent post found');
            }
            if (!function_exists('media_handle_upload'))
            {
                require_once ABSPATH . "wp-admin" . '/includes/image.php';
                require_once ABSPATH . "wp-admin" . '/includes/file.php';
                require_once ABSPATH . "wp-admin" . '/includes/media.php';
            }
            $url = add_query_arg(array('access_token' => $this->get_oauth_data('access_token')), $url);
            $tmp = scb_edd_download_file($url, 100);
            if (is_wp_error($tmp))
            {
                throw new Exception($tmp->get_error_message());
            }
            p_l(" $url - $tmp-" . size_format(filesize($tmp)));

            $repacked = $this->repack($tmp, $attachment,$post);

            if (!$repacked[0])
            {
                throw new Exception($repacked[1]);
            }
            $tmp       = $repacked[1];
            $file_size = @filesize($tmp);

            p_l(" repacked-" . size_format($file_size));
            $file_array = array(
                'name'     => $attachment['package'] . ".v" . $attachment['tag_num'] . ".zip", // ex: wp-header-logo.png
                'tmp_name' => $tmp,
            );
            p_l($file_array);
            p_l($attachment);
            $result = "";
            if ($attachment['attachment_id'])
            {
                $result = $this->update_download($post_id, $attachment, $file_array);

            }
            else
            {
                $result = $this->insert_download($post_id, $attachment, $file_array);

            }
            p_l($result);
            if (isset($result['tmp_attachment']))
            {
                p_l("deleting " . $result['tmp_attachment']);
                @unlink($result['tmp_attachment']);
            }

            if (!empty($repacked['2']))
            {
                p_l("in  repacked");
                if (!class_exists('Automattic_Readme') && defined('EDD_SL_PLUGIN_DIR') && file_exists(EDD_SL_PLUGIN_DIR . 'includes/parse-readme.php'))
                {
                    include_once EDD_SL_PLUGIN_DIR . 'includes/parse-readme.php';
                }

                if (class_exists('Automattic_Readme') && function_exists('_edd_sl_readme_get_transient_key'))
                {
                    $Automattic_Readme = new Automattic_Readme();
                    $readme            = $Automattic_Readme->parse_readme_contents($repacked['2']);
                    if ($readme)
                    {
                        set_transient(_edd_sl_readme_get_transient_key($post_id), $readme, HOUR_IN_SECONDS * 6);

                        $this->update_download_specs($post_id,
                            !empty($readme['stable_tag']) ? $readme['stable_tag'] : '',
                            !empty($readme['sections']['changelog']) ? $readme['sections']['changelog'] : '',
                            $file_size,
                            !empty($readme['requires_at_least']) ? $readme['requires_at_least'] : '');
                    }
                }
                else
                {
                    $this->update_download_specs($post_id,
                        $attachment['tag_num'],
                        '',
                        $file_size,
                        '');
                }

            }
            else
            {
                $this->update_download_specs($post_id,
                    $attachment['tag_num'],
                    '',
                    $file_size,
                    '');
            }
            // @unlink($tmp);
            return $result; //array('attachment_id' => $id, 'file' => wp_get_attachment_url($id));
        }
        catch (Exception $e)
        {

            @unlink($tmp);
            if (isset($result['tmp_attachment']))
            {
                p_l("restoring " . $result['tmp_attachment'] . ',' . $result['orig_attachment']);
                rename($result['tmp_attachment'], $result['orig_attachment']);
            }
            throw new Exception($e->getMessage());
        }
    }

    public function update_download_specs($post_id, $version, $changelog, $size, $requires)
    {
        //p_l("$version, $changelog, $size, $requires");
        if ($version)
        {
            $_POST['edd_sl_version'] = $version;
            update_post_meta($post_id, '_edd_sl_version', $_POST['edd_sl_version']);
        }
        if ($changelog)
        {
            $_POST['edd_sl_changelog'] = $changelog;
            // p_l($_POST['edd_sl_changelog']);
            update_post_meta($post_id, '_edd_sl_changelog', $_POST['edd_sl_changelog']);
        }
        if (class_exists('EDD_Software_Specs'))
        {
            $prefix                        = '_smartest_';
            $_POST[$prefix . 'lastupdate'] = defined('SCB_IN_API_HOOK') ? time() : date("m/d/Y", time());

            update_post_meta($post_id, $prefix . 'lastupdate', $_POST[$prefix . 'lastupdate']);
            if ($version)
            {
                $_POST[$prefix . 'currentversion'] = $version;
                update_post_meta($post_id, $prefix . 'currentversion', $_POST[$prefix . 'currentversion']);
            }

            $_POST[$prefix . 'filetype'] = '.zip';
            update_post_meta($post_id, $prefix . 'filetype', $_POST[$prefix . 'filetype']);
            if ($size)
            {
                $_POST[$prefix . 'filesize'] = size_format($size);
                update_post_meta($post_id, $prefix . 'filesize', $_POST[$prefix . 'filesize']);
            }
            if ($requires)
            {
                $_POST[$prefix . 'requirements'] = 'WordPress ' . $requires;
                update_post_meta($post_id, $prefix . 'requirements', $_POST[$prefix . 'requirements']);
            }
            $_POST[$prefix . 'pricecurrency'] = 'USD';
            update_post_meta($post_id, $prefix . 'pricecurrency', $_POST[$prefix . 'pricecurrency']);

        }

    }
    public function update_download($post_id, $attachment, $file_array)
    {
        $result['attachment_id'] = $attachment['attachment_id'];
        $last_attachment_path    = get_attached_file($attachment['attachment_id']);
        $last_attachment         = pathinfo($last_attachment_path);
        p_l($last_attachment);

        $override_default_dir = apply_filters('override_default_fes_dir', false);
        if (function_exists('edd_set_upload_dir') && !$override_default_dir)
        {
            add_filter('upload_dir', 'edd_set_upload_dir');
        }
        else if ($override_default_dir)
        {
            add_filter('upload_dir', 'fes_set_custom_upload_dir');
        }

        $upload_dir = wp_upload_dir();
        p_l($upload_dir);

        if (!isset($upload_dir['basedir']))
        {
            throw new Exception('Unable to copy download(1)');
        }
        if (file_exists($last_attachment_path))
        {
            $tmpname = $last_attachment['basename'] . "_" . time();
            p_l("renaming $last_attachment_path," . $last_attachment['dirname'] . "/" . $tmpname);
            if (rename($last_attachment_path, $last_attachment['dirname'] . "/" . $tmpname))
            {
                $result['tmp_attachment']  = $last_attachment['dirname'] . "/" . $tmpname;
                $result['orig_attachment'] = $last_attachment_path;
            }
            else
            {
                throw new Exception('Unable to remove old download');
            }
        }

        //if ($last_attachment['basename'] == $file_array['name']) // another file with same name ?
        {
            //@unlink($last_attachment_path);
            $file_array['name'] = wp_unique_filename($last_attachment['dirname'], $file_array['name'], 'scb_edd_unique_filename_callback');

        }
        //else
        {
            // $file_array['name'] = wp_unique_filename($last_attachment['dirname'], $file_array['name'], 'scb_edd_unique_filename_callback');
            /*
        if (file_exists($last_attachment_path))
        {
        $tmpname = $last_attachment['basename'] . "_" . time();
        p_l("renaming $last_attachment_path," . $last_attachment['dirname'] . "/" . $tmpname);
        if (rename($last_attachment_path, $last_attachment['dirname'] . "/" . $tmpname))
        {
        $result['tmp_attachment']  = $last_attachment['dirname'] . "/" . $tmpname;
        $result['orig_attachment'] = $last_attachment_path;
        }
        else
        {
        throw new Exception('Unable to remove old download');
        }
        }
         */

        }
        p_l($file_array);
        $result['file'] = $upload_dir['url'] . '/' . $file_array['name'];
        $result['id']   = $attachment['attachment_id'];
        p_l("copy  " . $file_array['tmp_name'] . "," . $upload_dir['path'] . "/" . $file_array['name']);
        if (!copy($file_array['tmp_name'], $upload_dir['path'] . "/" . $file_array['name']))
        {
            throw new Exception('Unable to copy download');
        }

        update_attached_file($attachment['attachment_id'], $upload_dir['path'] . "/" . $file_array['name']);
        $post_data = array
            (
            'ID'         => $attachment['attachment_id'],
            'post_title' => $attachment['name'],
            'post_name'  => $attachment['name'],
        );
        p_l($post_data);
        wp_update_post($post_data);
        $attach_data = wp_generate_attachment_metadata($attachment['attachment_id'], $upload_dir['path'] . "/" . $file_array['name']);
        wp_update_attachment_metadata($attachment['attachment_id'], $attach_data);

        return $result;

    }
    public function insert_download($post_id, $attachment, $file_array)
    {
        $result = array();

        if (function_exists('edd_set_upload_dir'))
        {
            add_filter('upload_dir', 'edd_set_upload_dir');
        }
        $result['attachment_id'] = media_handle_sideload($file_array, $post_id, $attachment['name']);
        if (is_wp_error($result['attachment_id']))
        {
            throw new Exception($result['attachment_id']->get_error_message());
        }

        $result['file'] = wp_get_attachment_url($result['attachment_id']);
        return $result;
    }

    /**
     * Fetch correct version tag url from repo and save to local filesystem
     *
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    public function fetch_from_repo($files, $post_id)
    {

        $inloop = -1;
        try
        {
            $post=get_post($post_id);
            $trans = 'scb_edd_repo_%1$s_' . $post_id . "_" . get_current_user_id();
            $meta  = array();
            foreach ($files as $index => $file)
            {
                $inloop = $index;

                if (isset($file['repo']))
                {
                    $repo_url = $this->translate_short_repo_url($file['repo'], $file['tag']);
                    $ret      = $this->save_remote_tag_url($file, $repo_url,  $post);
                    p_l($ret);

                    $files[$index]['file']              = $ret['file'];
                    $files[$index]['attachment_id']     = $ret['attachment_id'];
                    $msg                                = 'Successfully fetched from repo:' . $file['repo'];
                    $meta[$ret['attachment_id']]['url'] = $file['repo_url'];

                    if ($this->get_setting('withhook'))
                    {
                        $ret_hook = $this->create_push_hook($meta[$ret['attachment_id']]['url']);
                        if (!$ret_hook[0])
                        {
                            $msg .= "Unable to Hook:" . $ret_hook[1];
                            $meta[$ret['attachment_id']]['hook_error'] = $ret_hook[1];
                        }
                        elseif (!$ret_hook[1])
                        {
                            $msg .= " and hooked";
                            $meta[$ret['attachment_id']]['hook_error'] = '';

                        }
                    }
                    p_l(sprintf($trans, 1), $msg);

                    set_transient(sprintf($trans, 1), $msg, 45);

                }
            }
            if (count($meta))
            {
                update_post_meta($post_id, '_scb_edd_repo_url', $meta);
            }
            return $files;
        }
        catch (Exception $e)
        {
            if (isset($files[$inloop]))
            {
                $msg = "Error while processing Repo(" . $files[$inloop]['repo'] . ") : " . $e->getMessage();
            }
            else
            {
                $msg = "Unable to fetch From Repo : " . $e->getMessage();
            }
            set_transient(sprintf($trans, 0), $msg, 45);

            unset($_POST['edd_download_meta_box_nonce']);
            wp_update_post(array("ID" => $_REQUEST["post_ID"], 'post_status' => 'draft'));
            return $files;

        }
    }

    public function repack($file, $attachment,$post)
    {
        $new_name = $attachment['package'];
        $new_tag  = $attachment['tag_num'];
        try
        {
            $tmp_dir = rtrim(sys_get_temp_dir(), "/") . "/";
            if (!file_exists($file))
            {
                throw new Exception('Zip file does not exists');
            }
            $zip = new ZipArchive;
            $res = $zip->open($file);
            if ($res === true)
            {
                $readme   = '';
                $repacked = false;
                $pathinfo = pathinfo($new_name);
                if (!empty($pathinfo['filename']))
                {
                    $new_folder = $tmp_dir . $new_name . "_folder";
                    $loop       = 0;
                    while (file_exists($new_folder))
                    {
                        $loop++;
                        $new_folder = $tmp_dir . $new_name . "_folder_" . $loop;
                    }

                    @unlink($tmp_dir . $new_name);
                    mkdir($new_folder);
                    p_l("extract to $new_folder");
                    p_l("zip file " . $tmp_dir . $new_name);
                    $zip->extractTo($new_folder);
                    $cur_dir = getcwd();
                    chdir($new_folder);
                    $glob = glob($new_folder . "/*");
                    p_l($glob);
                    $zip->close();

                    if (!is_array($glob) || (is_array($glob) && count($glob) == 1))
                    {
                        $glob_info = pathinfo($glob[0]);
                        p_l($glob_info);
                        //rename($glob[0], $glob_info['dirname'] . "/" . $new_name);
                        $hook_data=array('post'=>$post,'attachment'=>$attachment,'folder'=>$glob[0]);
                        p_l($hook_data);
                        do_action('scb_edd_before_repack',$hook_data );
                        $ret = scb_edd_zipData($glob[0], $tmp_dir . $new_name . "-v." . $new_tag . ".zip", $new_name);
                        if (!$ret)
                        {
                            p_l($ret);
                            throw new Exception('Unable to repack zip file');
                        }
                        $repacked = true;
                        if (file_exists($glob[0] . "/readme.txt"))
                        {
                            $readme = @file_get_contents($glob[0] . "/readme.txt");
                        }
                    }
                    chdir($cur_dir);
                }
                if (file_exists($new_folder))
                {
                    //scb_edd_deleteDir($new_folder);
                }
                return array(1, $repacked ? $tmp_dir . $new_name . "-v." . $new_tag . ".zip" : $file, $readme);
            }
            else
            {
                throw new Exception('Unable to open zip file');
            }
        }
        catch (Exception $e)
        {
            return array(0, $e->getMessage());
        }
    }

    public function mark_as_hooked($post_id, $attachment_id, $data = null)
    {
        try
        {
            $repo_url = get_post_meta($post_id, '_scb_edd_repo_url', true);
            if (isset($repo_url[$attachment_id]))
            {
                $repo_url[$attachment_id]['hooked'] = 1;
                if (!is_null($data))
                {
                    $repo_url[$attachment_id]['hook_id'] = $data;
                }

                update_post_meta($post_id, '_scb_edd_repo_url', $repo_url);
                return array(1, "");
            }
            else
            {
                throw new Exception('Download does not has any repositiry assigned');
            }
        }
        catch (Exception $e)
        {
            return array(0, $e->getMessage());
        }
    }

}
