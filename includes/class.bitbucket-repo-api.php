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
class SCB_BitBucket_Repo_API extends SCB_Repo_API
{
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
            self::$instance = new SCB_BitBucket_Repo_API();
        }

        return self::$instance;
    }
    public function __construct()
    {
        parent::__construct('bb', 'BitBucket', 'https://bitbucket.org/site/oauth2', 'https://api.bitbucket.org/2.0', array
            (
                'default_fields' => array
                (
                    'callback_url'  => true,
                    'client_id'     => true,
                    'client_secret' => true,
                    'auth_token'    => true,
                    'client_token'  => true,
                ),
                'texts'          => array
                (
                    'client_id'                  => 'Key',
                    'client_secret'              => 'Secret',
                    'setting_name_client_id'     => 'BitBucket Key',
                    'setting_name_client_secret' => 'BitBucket Secret',
                    'setting_desc_client_id'     => __('You must Register a new OAuth consumer from here <strong>https://bitbucket.org/account/user/&lt;YOUR USERRNAME&gt;/oauth-consumers/new </strong>. You must enter callback url as displayed above and make sure to provide permission Repositories =Read and Webhooks = Read and write and enter Key here', 'edd-scriptburn-deploy-from-git-bb'),
                    'setting_desc_client_secret' => __('You must Register a new OAuth consumer from here <strong>https://bitbucket.org/account/user/&lt;YOUR USERRNAME&gt;/oauth-consumers/new </strong>. You must enter callback url displayed above and make sure to provide permission Repositories =Read and Webhooks = Read and write and enter Secret here', 'edd-scriptburn-deploy-from-git-bb'),

                ),
            )
        );
    }

    public function nonce_check()
    {
        return true;
    }
    public function setting_fields()
    {

        $ret = parent::common_setting_fields();
        $ret = parent::common_setting_fields();
        $url = $ret[0];
        unset($ret[0]);

        array_unshift($ret, $url, array(
            'id'   => $this->with_prefix('user'),
            'name' => __('User Name'),
            'type' => 'text',
            'desc' => __('Enter Your bitbuket username ', 'edd-scriptburn-deploy-from-git-bb'),
        ), array(
            'id'   => $this->with_prefix('pass'),
            'name' => __('Password'),
            'type' => 'password',
            'desc' => __('Enter Your bitbuket Password ', 'edd-scriptburn-deploy-from-git-bb'),
        ));
        return $ret;
    }

    public function render_client_token()
    {

        $access_token = $this->get_oauth_data('access_token');
        $anchor       = '<a class="button" href="%1$s/authorize?client_id=%2$s&response_type=code" target="_blank" onclick="javascript:if(1==%3$s){return confirm(%4$s)}else{return true}" >%5$s</a>';
        printf($anchor,
            $this->auth_end_point,
            $this->get_setting('client_id'),
            $access_token ? 1 : 0,
            "'Please confirm to Re-authorize'",
            !$access_token ? 'Authorize BitBucket Access' : 'Re-authorize BitBucket Access'

        );
    }

    public function after_oauth($response)
    {
        return;
    }
    public function is_authorized_response($api_response)
    {

        if (is_object($api_response) && property_exists($api_response, 'error') && property_exists($api_response->error, 'message'))
        {
            if (stripos($api_response->error->message, 'Access token expired') !== false)
            {
                if ($this->get_oauth_data('refresh_token'))
                {
                    return array(0, 'You must re-authorize ' . $this->text . " Access ", $this->get_oauth_data('refresh_token'));
                }
                else
                {
                    return array(0, 'You must authorize ' . $this->text . " Access ");
                }
            }
            else
            {

                return array(0, 'Error from ' . $this->text . ' :' . $api_response->error->message);
            }
        }
        return null;
    }

    public function reauth($refresh_token)
    {

        $args         = array();
        $args['body'] = array(
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refresh_token,
            'client_id'     => $this->get_setting('client_id'),
            'client_secret' => $this->get_setting('client_secret'),

        );

        $args['headers'] = array('Accept' => 'application/json');

        $response = wp_remote_post($this->auth_end_point . "/access_token", $args);

        if (is_wp_error($response))
        {
            throw new Exception("Refresh Toen failed: $error_message");
        }
        elseif (empty($response['body']))
        {
            throw new Exception("Refresh Toen failed:Empty response received from " . $repo->text);
        }
        elseif (!is_object($body = @json_decode($response['body'])))
        {
            throw new Exception("Refresh Toen failed:Invalid response received from " . $repo->text);
        }
        elseif (property_exists($body, 'message'))
        {
            throw new Exception("Refresh Toen failed for " . $repo->text . " :" . $body->message);
        }
        edd_update_option($this->with_prefix('oauth_data'), @json_encode($body));

    }
    public function translate_short_repo_url($repo, $tagName)
    {

        //return array(1, array('url' => 'http://wphidepost.loc/xmlrpc.php.zip', 'ext' => 'zip'));
        //https://api.bitbucket.org/2.0/repositories/rajneeshojha/wpmovies/refs/tags/?access_token=ZPZ5SRkjKqYCr8-npHJeKZ0-xF-TOGnJ-N5L-5zu_xNws728gvuYYxuaEJOy4dOzeaVrclRkWL0fkiNMQ1A=1
        try
        {
            if (!$this->get_setting('user') || !$this->get_setting('pass'))
            {
                throw new Exception('Please enter your bitbucket username and password in setting section');
            }

            if (!$tagName)
            {
                throw new Exception('You must provide a tag name to fetch');
            }
            $url = 'https://api.bitbucket.org/2.0/repositories/' . $repo . "/refs/tags";

            $response = $this->send_api_cmd($url);
            if (!$response[0])
            {
                throw new Exception($response[1]);
            }
            $response = $response[1];
            if (!is_object($response) || !property_exists($response, values) || !is_array($response->values))
            {

                throw new Exception('No tags found');

            }
            else
            {

                $found = false;
                foreach ($response->values as $tag)
                {
                    //p_n($tag);
                    //p_n((is_object($tag) ? 'object' : 'no obj') . "--" . (property_exists($tag, 'name') ? 'exists' : 'no exists') . "--" . $tag->name . "==" . $tagName);
                    if (is_object($tag) && property_exists($tag, 'name') && ($tag->name == $tagName || $tag->name == "v" . $tagName))
                    {
                        $found = "https://" . urlencode($this->get_setting('user')) . ":" . $this->get_setting('pass') . "@bitbucket.org/" . $repo . "/get/" . $tag->name . ".zip";
                        break;
                    }
                }
                if (!$found)
                {
                    throw new Exception("Requested tag - $tagName does not exists");

                }
                return $found;
            }
        }
        catch (Exception $e)
        {

            if (stripos($e->getMessage(), 'Not Found') !== false)
            {
                throw new Exception('Repositry does not exists');
            }
            else
            {
                throw new Exception('Error from GitHub :' . $e->getMessage());
            }
        }
    }
    public function download_update($json, $post)
    {
        //https: //api.bitbucket.org/2.0/repositories/rajneeshojha/test/refs/tags/v1.0
        $repo = explode("/repositories/", $json->push->changes[0]->new->links->self->href);

        if (!isset($repo[1]))
        {
            return;
        }
        $repo = explode('/refs/tags/', $repo[1]);
        if (count($repo) != 2)
        {
            return;
        }
        return $this->do_download_update('bitbucket', $repo[0], $repo[1], $this->text);
    }
    public function create_push_hook($repo)
    {
        try
        {

            $repo = scb_edd_merge_repo_input($repo);
            if (empty($repo['repo']))
            {
                throw new Exception('Download does not has any repositiry assigned');
            }
            $redirect_url = $this->oauth_page_url('hook', array('scb_deploy_callback_for' => 'bb'));

            $data = array(
                'description' => @$_SERVER['HTTP_HOST'] . " " . $repo['repo'] . " hook",
                'active'      => true,
                'events'      => array('repo:push'),
                'url'         => $redirect_url,
            );

            $url = $this->api_end_point . '/repositories/' . @$repo['repo'] . '/hooks';

            if ($this->hook_exists($repo['repo']))
            {
                return array(1, "Hook already exists");
            }

            //p_d('x');

            //$ret      = scb_edd_post($url, $data, true);
            $ret = $this->send_api_cmd($url, $data, 'post', true);

            if (!$ret[0])
            {
                throw new Exception($ret[1]);
            }
            $ret = $ret[1];

            if (!is_object($ret))
            {
                throw new Exception('Invalid response');
            }
            elseif (!($ret->subject->full_name == $repo['repo'] && $ret->url == $data['url']))
            {
                throw new Exception('Unable to hook Repositry "' . @$repo['repo'] . '" ');
            }
            else
            {
                return array(1, "");
            }

        }
        catch (Exception $e)
        {
            return array(0, $e->getMessage());
        }
    }

    public function send_api_cmd($url, $data = null, $method = 'get', $json = false)
    {
        try
        {
            $response = parent::send_api_cmd($url, $data, $method, $json);
            // p_n($response);
            //api command failed
            if (!$response[0])
            {
                // do we need to reauth
                if (isset($response[2]))
                {
                    //p_n("reauth");
                    //perform reauth
                    $this->reauth($response[2]);

                    //send same api command again
                    $response = parent::send_api_cmd($url, $data, $method, $json);

                    //if api failed again throw erro and exit
                    if (!$response[0])
                    {
                        throw new Exception($response[1]);
                    }
                }
                else
                {
                    throw new Exception($response[1]);
                }
            }
            return $response;
        }
        catch (Exception $e)
        {
            return array(0, $e->getMessage());
        }
    }
    public function hook_exists($repo)
    {
        $hook_url = $this->oauth_page_url('hook', array('scb_deploy_callback_for' => 'bb'));
        $url      = $this->api_end_point . '/repositories/' . $repo . '/hooks';
        $ret      = $this->send_api_cmd($url);

        if (!$ret[0] || !is_array($ret[1]->values) || !count($ret[1]->values))
        {
            return false;
        }
        foreach ($ret[1]->values as $value)
        {
            // p_l($value->subject->full_name . '==' . $repo . "-" . $value->url . '==' . $hook_url);
            if ($value->subject->full_name == $repo && $value->url == $hook_url)
            {
                return true;
            }
        }
        return false;
    }
    public function test_api_data()
    {
        $str = '{"repository": {"uuid": "{33caefc5-01a0-46ca-a264-3288e765ed53}", "owner": {"username": "rajneeshojha", "type": "user", "display_name": "rajneesh ojha", "links": {"self": {"href": "https://api.bitbucket.org/2.0/users/rajneeshojha"}, "avatar": {"href": "https://bitbucket.org/account/rajneeshojha/avatar/32/"}, "html": {"href": "https://bitbucket.org/rajneeshojha/"}}, "uuid": "{45c77d63-24e8-4406-ac9f-9b5691222ddc}"}, "name": "test", "scm": "git", "type": "repository", "links": {"self": {"href": "https://api.bitbucket.org/2.0/repositories/rajneeshojha/test"}, "avatar": {"href": "https://bitbucket.org/rajneeshojha/test/avatar/32/"}, "html": {"href": "https://bitbucket.org/rajneeshojha/test"}}, "website": "", "full_name": "rajneeshojha/test", "is_private": true}, "push": {"changes": [{"new": {"type": "tag", "target": {"message": "ddd\n", "author": {"raw": "rajneesh ojha <rajneesh_ojha@yahoo.com>", "user": {"username": "rajneeshojha", "type": "user", "display_name": "rajneesh ojha", "links": {"self": {"href": "https://api.bitbucket.org/2.0/users/rajneeshojha"}, "avatar": {"href": "https://bitbucket.org/account/rajneeshojha/avatar/32/"}, "html": {"href": "https://bitbucket.org/rajneeshojha/"}}, "uuid": "{45c77d63-24e8-4406-ac9f-9b5691222ddc}"}}, "hash": "e7bdfd1c5dce563d09e7a21ca841e1dcab234ba0", "date": "2016-08-26T16:57:45+00:00", "type": "commit", "parents": [{"type": "commit", "hash": "87338c2ee818b4f39e7e3fc2dab55561581f2456", "links": {"self": {"href": "https://api.bitbucket.org/2.0/repositories/rajneeshojha/test/commit/87338c2ee818b4f39e7e3fc2dab55561581f2456"}, "html": {"href": "https://bitbucket.org/rajneeshojha/test/commits/87338c2ee818b4f39e7e3fc2dab55561581f2456"}}}], "links": {"self": {"href": "https://api.bitbucket.org/2.0/repositories/rajneeshojha/test/commit/e7bdfd1c5dce563d09e7a21ca841e1dcab234ba0"}, "html": {"href": "https://bitbucket.org/rajneeshojha/test/commits/e7bdfd1c5dce563d09e7a21ca841e1dcab234ba0"}}}, "links": {"self": {"href": "https://api.bitbucket.org/2.0/repositories/rajneeshojha/test/refs/tags/v1.0"}, "html": {"href": "https://bitbucket.org/rajneeshojha/test/commits/tag/v1.0"}, "commits": {"href": "https://api.bitbucket.org/2.0/repositories/rajneeshojha/test/commits/v1.0"}}, "name": "v1.0"}, "created": false, "old": {"type": "tag", "target": {"message": "eee\n", "author": {"raw": "rajneesh ojha <rajneesh_ojha@yahoo.com>", "user": {"username": "rajneeshojha", "type": "user", "display_name": "rajneesh ojha", "links": {"self": {"href": "https://api.bitbucket.org/2.0/users/rajneeshojha"}, "avatar": {"href": "https://bitbucket.org/account/rajneeshojha/avatar/32/"}, "html": {"href": "https://bitbucket.org/rajneeshojha/"}}, "uuid": "{45c77d63-24e8-4406-ac9f-9b5691222ddc}"}}, "hash": "87338c2ee818b4f39e7e3fc2dab55561581f2456", "date": "2016-08-20T21:42:07+00:00", "type": "commit", "parents": [{"type": "commit", "hash": "bdf36c0281e661288d47c9240718bd4110656453", "links": {"self": {"href": "https://api.bitbucket.org/2.0/repositories/rajneeshojha/test/commit/bdf36c0281e661288d47c9240718bd4110656453"}, "html": {"href": "https://bitbucket.org/rajneeshojha/test/commits/bdf36c0281e661288d47c9240718bd4110656453"}}}], "links": {"self": {"href": "https://api.bitbucket.org/2.0/repositories/rajneeshojha/test/commit/87338c2ee818b4f39e7e3fc2dab55561581f2456"}, "html": {"href": "https://bitbucket.org/rajneeshojha/test/commits/87338c2ee818b4f39e7e3fc2dab55561581f2456"}}}, "links": {"self": {"href": "https://api.bitbucket.org/2.0/repositories/rajneeshojha/test/refs/tags/v1.0"}, "html": {"href": "https://bitbucket.org/rajneeshojha/test/commits/tag/v1.0"}, "commits": {"href": "https://api.bitbucket.org/2.0/repositories/rajneeshojha/test/commits/v1.0"}}, "name": "v1.0"}, "truncated": false, "closed": false, "forced": false, "links": {"html": {"href": "https://bitbucket.org/rajneeshojha/test/branches/compare/e7bdfd1c5dce563d09e7a21ca841e1dcab234ba0..87338c2ee818b4f39e7e3fc2dab55561581f2456"}, "diff": {"href": "https://api.bitbucket.org/2.0/repositories/rajneeshojha/test/diff/e7bdfd1c5dce563d09e7a21ca841e1dcab234ba0..87338c2ee818b4f39e7e3fc2dab55561581f2456"}, "commits": {"href": "https://api.bitbucket.org/2.0/repositories/rajneeshojha/test/commits?include=e7bdfd1c5dce563d09e7a21ca841e1dcab234ba0&exclude=87338c2ee818b4f39e7e3fc2dab55561581f2456"}}}]}, "actor": {"username": "rajneeshojha", "type": "user", "display_name": "rajneesh ojha", "links": {"self": {"href": "https://api.bitbucket.org/2.0/users/rajneeshojha"}, "avatar": {"href": "https://bitbucket.org/account/rajneeshojha/avatar/32/"}, "html": {"href": "https://bitbucket.org/rajneeshojha/"}}, "uuid": "{45c77d63-24e8-4406-ac9f-9b5691222ddc}"}}';

        return $str;
    }
}
function SCB_BitBucket_Repo_API()
{
    return SCB_BitBucket_Repo_API::instance();
}
