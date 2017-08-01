<?php
/**
 * Scripts
 *
 * @package     EDD\ScriptburnDeployFromGitBb\Scripts
 * @since       1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH'))
{
    exit;
}

/**
 * Load admin scripts
 *
 * @since       1.0.0
 * @global      array $edd_settings_page The slug for the EDD settings page
 * @global      string $post_type The type of post that we are editing
 * @return      void
 */
function edd_scriptburn_deploy_from_git_bb_admin_scripts($hook)
{
    global $edd_settings_page, $post_type, $screen;
    $screen = get_current_Screen();
    // Use minified libraries if SCRIPT_DEBUG is turned off
    $suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';

    /**
     * @todo        This block loads styles or scripts explicitly on the
     *                EDD settings page.
     */
    if ($hook == $edd_settings_page)
    {
        wp_enqueue_script('edd_scriptburn_deploy_from_git_bb_admin_js', EDD_DEPLOY_FROM_GITHUB_AND_BITBUCKET_URL . '/assets/js/admin' . $suffix . '.js', array('jquery'));
        wp_enqueue_style('edd_scriptburn_deploy_from_git_bb_admin_css', EDD_DEPLOY_FROM_GITHUB_AND_BITBUCKET_URL . '/assets/css/admin' . $suffix . '.css');
    }
    if (is_object($screen))
    {
        //p_d(get_the_id());

        if ($screen->id == 'download')
        {
            $hooks    = array();
            $repo_url = get_post_meta(get_the_id(), '_scb_edd_repo_url', true);
            $files    = get_post_meta(get_the_id(), 'edd_download_files', true);
            if (is_array($files) && count($files))
            {
                foreach ($files as $file)
                {
                    if (!isset($repo_url[$file['attachment_id']]))
                    {
                        continue;
                    }
                    $repo = scb_edd_merge_repo_input($repo_url[$file['attachment_id']]['url']);

                    if (!isset($repo['repo']) || !isset($repo['protocol']))
                    {
                        continue;
                    }
                    if ($repo['protocol'] == 'git' || $repo['protocol'] == 'bitbucket')
                    {

                        $p1 = "";
                        $p2 = "";
                        if ($repo['protocol'] == 'git')
                        {
                            $p1       = "G";
                            $p2       = "H";
                            $repo_obj = SCB_GitHub_Repo_API();
                        }
                        else
                        {
                            $p1       = "B";
                            $p2       = "B";
                            $repo_obj = SCB_BitBucket_Repo_API();
                        }
                        //if ($repo_obj->get_setting('withhook'))
                        {
                            
                            $html = '<div class="edd-file-name" style="width:2%5$s;margin-right:0px">
    <span class="edd-repeatable-row-setting-label">
    </span>
    <span id="edd-edd_download_files0name-wrap" title="%1$s" ><span class="dashicons dashicons-admin-plugins" style="color:%2$s"></span><div><span style="color:red">%3$s</span><span style="color:green">%4$s</span>
    </span>
</div>';
                            $hooks[$file['attachment_id']] = array('html' => sprintf($html, $repo['repo_url'],$repo_obj->get_setting('withhook')?'green':'red', $p1, $p2,'%'), 'repo' => $repo['repo_url'],'hook_error'=>@$repo_url[$file['attachment_id']]['hook_error']

                                );
                        }
                    }
                }
            }

            $translation_array = array(
                'html'  => edd_scb_download_list_html(),
                'hooks' => $hooks,
            );
            wp_register_script('edd_scriptburn_deploy_from_git_bb_download_js', EDD_DEPLOY_FROM_GITHUB_AND_BITBUCKET_URL . '/assets/js/download' . $suffix . '.js', array('jquery'));

            wp_localize_script('edd_scriptburn_deploy_from_git_bb_download_js', 'scb_download_deploy', $translation_array);

            wp_enqueue_script('edd_scriptburn_deploy_from_git_bb_download_js');

        }
    }
}
add_action('admin_enqueue_scripts', 'edd_scriptburn_deploy_from_git_bb_admin_scripts', 100);

/**
 * Load frontend scripts
 *
 * @since       1.0.0
 * @return      void
 */
function edd_scriptburn_deploy_from_git_bb_scripts($hook)
{
    // Use minified libraries if SCRIPT_DEBUG is turned off
    $suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';

    wp_enqueue_script('edd_scriptburn_deploy_from_git_bb_js', EDD_DEPLOY_FROM_GITHUB_AND_BITBUCKET_URL . '/assets/js/scripts' . $suffix . '.js', array('jquery'));
    wp_enqueue_style('edd_scriptburn_deploy_from_git_bb_css', EDD_DEPLOY_FROM_GITHUB_AND_BITBUCKET_URL . '/assets/css/styles' . $suffix . '.css');
}
add_action('wp_enqueue_scripts', 'edd_scriptburn_deploy_from_git_bb_scripts');
