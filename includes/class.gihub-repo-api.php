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
class SCB_GitHub_Repo_API extends SCB_Repo_API
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
            self::$instance = new SCB_GitHub_Repo_API();
        }

        return self::$instance;
    }
    public function __construct()
    {
        parent::__construct('gh', 'GitHub', 'https://github.com/login/oauth/', 'https://api.github.com/', array
            (
                'default_fields' => array
                (
                    'callback_url'  => true,
                    'client_id'     => true,
                    'client_secret' => true,
                    'auth_token'    => true,
                    'client_token'  => true,
                ),
                'texts'          => array(

                    'setting_desc_client_id'     => __('You must Register a new OAuth application from <a href="https://github.com/settings/applications/new">here</a> and enter Client ID Here', 'edd-scriptburn-deploy-from-git-bb'),
                    'setting_desc_client_secret' => __('You must Register a new OAuth application <a href="https://github.com/settings/applications/new">here</a> and enter Client Secret here', 'edd-scriptburn-deploy-from-git-bb'),
                ),
            )
        );
    }

    public function nonce_check()
    {
        return true; //wp_verify_nonce('auth', isset($_REQUEST['state'])?$_REQUEST['state']:'');
    }
    public function setting_fields()
    {

        return parent::common_setting_fields();
    }

    public function render_client_token()
    {
        $access_token = $this->get_oauth_data('access_token');
        $anchor       = '<a class="button" href="%1$s/authorize?client_id=%2$s&redirect_uri=%3$s&scope=%4$s&state=%5$s" target="_blank" onclick="javascript:if(1==%6$s){return confirm(%7$s)}else{return true}" >%8$s</a>';
        printf($anchor,
            $this->auth_end_point,
            $this->get_setting('client_id'),
            urlencode($this->oauth_page_url('auth')),
            'repo,read:repo_hook',
            wp_create_nonce('auth'),
            $access_token ? 1 : 0,
            "'Please confirm to Re-authorize'",
            (!$access_token ? 'Authorize GitHub Access' : 'Re-authorize GitHub Access') );
    }

    public function after_oauth($response)
    {

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

            $url          = $this->api_end_point . '/repos/' . @$repo['repo'] . '/hooks?access_token=' . $this->get_oauth_data('access_token');
            $redirect_url = $this->oauth_page_url('hook', array('scb_deploy_callback_for' => 'gh'));
            $data         = array(
                'name'   => 'web',
                'active' => true,
                'events' => (array('push')),
                'config' => (array("url" => $redirect_url, "content_type" => 'json')),
            );
            if ($this->hook_exists($repo['repo'] ))
            {
                return array(1, "Hook already exists");
            }
            $ret = $this->send_api_cmd($url, $data, 'post', true);
            if (!$ret[0])
            {
                if (isset($ret[2]) && is_object($ret[2]))
                {
                    if (is_array($ret[2]->errors))
                    {
                        foreach ($ret[2]->errors as $error)
                        {
                            if (stripos($error->message, 'Hook already exists on this repository') !== false)
                            {
                                return array(1, "Hook already exists");
                            }
                        }
                    }
                }
                throw new Exception($ret[1]);
            }
            $response = $ret[1];

            if ($response->id)
            {
                //$ret=$this->mark_as_hooked($post_id, $attachment_id, $response->id);
                //p_l($ret);
                return array(1, "", $response->id);
            }
            else
            {
                throw new Exception('Unable to hook Repositry "' . @$repo['repo'] . '" ');
            }

        }
        catch (Exception $e)
        {
            return array(0, $e->getMessage());
        }
    }
    public function translate_short_repo_url($repo, $tagName)
    {

        //return array(1, array('url' => 'http://wphidepost.loc/xmlrpc.php.zip', 'ext' => 'zip'));

        try
        {
            if (!$tagName)
            {
                throw new Exception('You must provide a tag name to fetch');
            }
            $url = 'https://api.github.com/repos/' . $repo . "/tags";

            $response = $this->send_api_cmd($url);
            if (!$response[0])
            {
                throw new Exception($response[1]);

            }
            $response = $response[1];
            if (!is_array($response) || !count($response))
            {
                throw new Exception('No tags found');

            }
            else
            {
                 p_l($response);
                $found = false;
                foreach ($response as $tag)
                {
                   p_l((is_object($tag) ? 'object' : 'no obj') . "--" . (property_exists($tag, 'name') ? 'exists' : 'no exists') . "--" . $tag->name . "==" . $tagName);
                    if (is_object($tag) && property_exists($tag, 'name') && ($tag->name == $tagName || $tag->name == "v" . $tagName))
                    {
                        $found = $tag->zipball_url;
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

    public function is_authorized_response($api_response)
    {
        if (is_object($api_response) && property_exists($api_response, 'message'))
        {
            if (stripos($api_response->message, 'Bad credentials') !== false)
            {
                return array(0, 'You must authorize ' . $this->text . " Access ");
            }
            else
            {

                return array(0, 'Error from ' . $this->text . ' :' . $api_response->message, $api_response);
            }
        }
        return null;
    }

    public function download_update($json, $post)
    {
        $new_tag = explode("/", $json->ref);

        if (!isset($new_tag[2]))
        {
            return;
        }
        return $this->do_download_update('git', $json->repository->full_name, $new_tag[2], $this->text);
    }

    public function hook_exists($repo )
    {
        $hook_url = $this->oauth_page_url('hook', array('scb_deploy_callback_for' => 'gh'));

        $url = $this->api_end_point . '/repos/' . $repo . '/hooks';
        $ret = $this->send_api_cmd($url);
        if (!$ret[0] || !is_array($ret[1]) || !count($ret[1]))
        {
            return false;
        }
        foreach ($ret[1] as $item)
        {
            //p_n($item->config->url ."==". $hook_url ."-".stripos($item->url, $this->api_end_point . "/repos/" . $repo));
            if ($item->config->url == $hook_url && stripos($item->url, $this->api_end_point . "/repos/" . $repo) !== false)
            {
                return true;
            }
        }
        return false;
    }
    public function test_api_data_gh()
    {
        $str = '{"ref":"refs/tags/v1.1","before":"46fd8d762ea026cc98d661ccde773d9fcabde0d6","after":"ed68c40a41f247d6fa88526b0c5a93d8e53f84a4","created":false,"deleted":false,"forced":true,"base_ref":null,"compare":"https://github.com/scriptburn/test/compare/46fd8d762ea0...ed68c40a41f2","commits":[],"head_commit":{"id":"aacdc41f17453ae5c9d2b8fa66146db8411b9fc7","tree_id":"86e01a8bbf29f9e949832a16597c835d308809f9","distinct":true,"message":"ee","timestamp":"2016-08-26T01:39:02+05:30","url":"https://github.com/scriptburn/test/commit/aacdc41f17453ae5c9d2b8fa66146db8411b9fc7","author":{"name":"rajneesh ojha","email":"rajneesh_ojha@yahoo.com","username":"rajneeshojha"},"committer":{"name":"rajneesh ojha","email":"rajneesh_ojha@yahoo.com","username":"rajneeshojha"},"added":[],"removed":[],"modified":["example.php"]},"repository":{"id":65928494,"name":"test","full_name":"scriptburn/test","owner":{"name":"scriptburn","email":"contact@scriptburn.com"},"private":false,"html_url":"https://github.com/scriptburn/test","description":null,"fork":false,"url":"https://github.com/scriptburn/test","forks_url":"https://api.github.com/repos/scriptburn/test/forks","keys_url":"https://api.github.com/repos/scriptburn/test/keys{/key_id}","collaborators_url":"https://api.github.com/repos/scriptburn/test/collaborators{/collaborator}","teams_url":"https://api.github.com/repos/scriptburn/test/teams","hooks_url":"https://api.github.com/repos/scriptburn/test/hooks","issue_events_url":"https://api.github.com/repos/scriptburn/test/issues/events{/number}","events_url":"https://api.github.com/repos/scriptburn/test/events","assignees_url":"https://api.github.com/repos/scriptburn/test/assignees{/user}","branches_url":"https://api.github.com/repos/scriptburn/test/branches{/branch}","tags_url":"https://api.github.com/repos/scriptburn/test/tags","blobs_url":"https://api.github.com/repos/scriptburn/test/git/blobs{/sha}","git_tags_url":"https://api.github.com/repos/scriptburn/test/git/tags{/sha}","git_refs_url":"https://api.github.com/repos/scriptburn/test/git/refs{/sha}","trees_url":"https://api.github.com/repos/scriptburn/test/git/trees{/sha}","statuses_url":"https://api.github.com/repos/scriptburn/test/statuses/{sha}","languages_url":"https://api.github.com/repos/scriptburn/test/languages","stargazers_url":"https://api.github.com/repos/scriptburn/test/stargazers","contributors_url":"https://api.github.com/repos/scriptburn/test/contributors","subscribers_url":"https://api.github.com/repos/scriptburn/test/subscribers","subscription_url":"https://api.github.com/repos/scriptburn/test/subscription","commits_url":"https://api.github.com/repos/scriptburn/test/commits{/sha}","git_commits_url":"https://api.github.com/repos/scriptburn/test/git/commits{/sha}","comments_url":"https://api.github.com/repos/scriptburn/test/comments{/number}","issue_comment_url":"https://api.github.com/repos/scriptburn/test/issues/comments{/number}","contents_url":"https://api.github.com/repos/scriptburn/test/contents/{+path}","compare_url":"https://api.github.com/repos/scriptburn/test/compare/{base}...{head}","merges_url":"https://api.github.com/repos/scriptburn/test/merges","archive_url":"https://api.github.com/repos/scriptburn/test/{archive_format}{/ref}","downloads_url":"https://api.github.com/repos/scriptburn/test/downloads","issues_url":"https://api.github.com/repos/scriptburn/test/issues{/number}","pulls_url":"https://api.github.com/repos/scriptburn/test/pulls{/number}","milestones_url":"https://api.github.com/repos/scriptburn/test/milestones{/number}","notifications_url":"https://api.github.com/repos/scriptburn/test/notifications{?since,all,participating}","labels_url":"https://api.github.com/repos/scriptburn/test/labels{/name}","releases_url":"https://api.github.com/repos/scriptburn/test/releases{/id}","deployments_url":"https://api.github.com/repos/scriptburn/test/deployments","created_at":1471455221,"updated_at":"2016-08-17T17:33:41Z","pushed_at":1472155902,"git_url":"git://github.com/scriptburn/test.git","ssh_url":"git@github.com:scriptburn/test.git","clone_url":"https://github.com/scriptburn/test.git","svn_url":"https://github.com/scriptburn/test","homepage":null,"size":5,"stargazers_count":0,"watchers_count":0,"language":null,"has_issues":true,"has_downloads":true,"has_wiki":true,"has_pages":false,"forks_count":0,"mirror_url":null,"open_issues_count":0,"forks":0,"open_issues":0,"watchers":0,"default_branch":"master","stargazers":0,"master_branch":"master"},"pusher":{"name":"scriptburn","email":"contact@scriptburn.com"},"sender":{"login":"scriptburn","id":20643070,"avatar_url":"https://avatars.githubusercontent.com/u/20643070?v=3","gravatar_id":"","url":"https://api.github.com/users/scriptburn","html_url":"https://github.com/scriptburn","followers_url":"https://api.github.com/users/scriptburn/followers","following_url":"https://api.github.com/users/scriptburn/following{/other_user}","gists_url":"https://api.github.com/users/scriptburn/gists{/gist_id}","starred_url":"https://api.github.com/users/scriptburn/starred{/owner}{/repo}","subscriptions_url":"https://api.github.com/users/scriptburn/subscriptions","organizations_url":"https://api.github.com/users/scriptburn/orgs","repos_url":"https://api.github.com/users/scriptburn/repos","events_url":"https://api.github.com/users/scriptburn/events{/privacy}","received_events_url":"https://api.github.com/users/scriptburn/received_events","type":"User","site_admin":false}}';

        return $str;
    }

}
function SCB_GitHub_Repo_API()
{
    return SCB_GitHub_Repo_API::instance();
}
