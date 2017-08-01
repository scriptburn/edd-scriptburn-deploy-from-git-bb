<?php
/**
 * Helper Functions
 *
 * @package     EDD\ScriptburnDeployFromGitBb\Functions
 * @since       1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH'))
{
    exit;
}

function edd_scb_html_callback_callback($args)
{
    if (isset($args['options']['callback']) && is_callable($args['options']['callback']))
    {

        call_user_func_array($args['options']['callback'], [$args]);
    }
}

function edd_scb_setting_page_url($args = null)
{

    $args = '?post_type=download&page=edd-settings&tab=extensions' . (is_array($args) ? http_build_query($args) : '') . "#scb_edd_oauth";
    return admin_url('edit.php') . $args;
}

function edd_scb_oauth_error($error)
{
    ?>
 <h3 style="color:red"><?php echo ($error); ?></h3>
                            <input type="button" value="Continue" onclick="document.location='<?php echo (edd_scb_setting_page_url()); ?>'"/>
                            <script>

                            </script>
<?php
wp_die();
}

function edd_scb_verify_bitbucket_token($token = false)
{
    try
    {
        $token = ($token ? $token : edd_get_option('scb_bitbucket_auth_token'));
        if (!$token)
        {
            throw new Exception('You must authorize Bitbucket access');
        }
        $response = wp_remote_get('https://api.bitbucket.org/2.0/user?access_token=' . ($token ? $token : edd_get_option('scb_bitbucket_auth_token')));
        if (is_wp_error($response))
        {

            throw new Exception("Unable to fetch " . $valid_repo[$_REQUEST['repo']]['text'] . " username. Error: $error_message");
        }
        elseif (empty($response['body']))
        {
            throw new Exception("Empty response received from " . $valid_repo[$_REQUEST['repo']]['text'] . " while trying to fetch " . $valid_repo[$_REQUEST['repo']]['text'] . " username");
        }
        elseif (!is_object($body = @json_decode($response['body'])))
        {
            throw new Exception("Invalid response received from " . $valid_repo[$_REQUEST['repo']]['text'] . " while trying to fetch " . $valid_repo[$_REQUEST['repo']]['text'] . " username");
        }
        else
        {
            return array(1, $body->username);
        }
    }
    catch (Exception $e)
    {
        return array(0, $e->getMessage());
    }

    edd_update_option('scb_bitbucket_auth_token', $body->access_token);
}

function scb_edd_verify_or_reset_bitbucket_token()
{
    $username = scb_edd_verify_bitbucket_token();
    if (!$username[0])
    {
        edd_update_option('scb_bitbucket_auth_token', '');
    }
}

function scb_edd_download_file($url)
{

    $img = tempnam(sys_get_temp_dir(), "scb-");
    $fp  = fopen($img, 'w+');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'scriptburn.com remote deploy');

    $result = curl_exec($ch);
    $info   = curl_getinfo($ch);

    curl_close($ch);

    fclose($fp);

    return $result ? $img : false;
}

function scb_edd_remote_get($url)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_USERAGENT, 'scriptburn.com remote deploy');

    $output = curl_exec($ch);
    $error  = curl_error($ch);
    if ($error)
    {
        return new WP_Error('scb', $error);
    }

    curl_close($ch);
    return array('body' => $output);
}

function scb_edd_zipData($source, $destination, $folder_name)
{
    p_l("$source, $destination, $folder_name");
    if (!extension_loaded('zip') || !file_exists($source))
    {
        return false;
    }

    $zip = new ZipArchive();
    @unlink($destination);
    if ( $zip->open($destination, ZipArchive::OVERWRITE|ZipArchive::CREATE)!==true)
    {
        return false;
    }

    $source = str_replace('\\', DIRECTORY_SEPARATOR, realpath($source));

    if (is_dir($source) === true)
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file)
        {
            if ($file->getFilename() == '.' || $file->getFilename() == '..')
            {
                continue;
            }
            $file = $file->getPathName();

            $file = str_replace('\\', DIRECTORY_SEPARATOR, $file);

            // Ignore "." and ".." folders
            if (in_array(substr($file, strrpos($file, DIRECTORY_SEPARATOR) + 1), array('.', '..')))
            {
                continue;
            }

            $file = realpath($file);

            if (is_dir($file) === true)
            {
                $zip->addEmptyDir(str_replace($source . DIRECTORY_SEPARATOR, $folder_name . DIRECTORY_SEPARATOR, $file . DIRECTORY_SEPARATOR));
            }
            else if (is_file($file) === true)
            {

                $zip->addFromString(str_replace($source . DIRECTORY_SEPARATOR, $folder_name . DIRECTORY_SEPARATOR, $file), file_get_contents($file));
            }
        }
    }
    else if (is_file($source) === true)
    {
        $zip->addFromString(basename($source), file_get_contents($source));
    }

    return $zip->close();

}

function scb_edd_deleteDir($dirPath)
{
    if (!is_dir($dirPath))
    {
        throw new InvalidArgumentException("$dirPath must be a directory");
    }
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/')
    {
        $dirPath .= '/';
    }
    $files = glob($dirPath . '{,.}[!.,!..]*', GLOB_MARK | GLOB_BRACE);
    foreach ($files as $file)
    {
        if (is_dir($file))
        {
            scb_edd_deleteDir($file);
        }
        else
        {
            unlink($file);
        }
    }
    rmdir($dirPath);
}

function edd_scb_download_list_html()
{
    ob_start();
    ?>
    <div style="float:right; text-align:left">
    <h4 style="color:green;">You can enter github or bitbucket repo url inside "File URL" text box in following format:</h4>
    <strong>For Github:</strong><br/>
    git://[REPOURL]/@[TAG NAME]<br/>

    So Repositry url https://github.com/scriptburn/test/releases/tag/v1.0 would be git://scriptburn/test/@v1.0
    <br/> <br/>
    <strong>For BitBucket:</strong><br/>
    bitbucket://[REPOURL]/@[TAG NAME]<br/>
    Ex: bitbucket://rajneeshojha/test@1.0
    </div>
    <div style="clear:both"></div>
<?php
return ob_get_clean();
}

function scb_edd_merge_repo_input($repo_url)
{
    $file = array();
    $repo = explode("://", $repo_url);

    if (count($repo) == 1)
    {
        return $file;
    }

    $protocol    = strtolower(trim($repo[0]));
    $repo        = explode('@', $repo[1]);
    $file['tag'] = isset($repo[1]) ? $repo[1] : '';

    $file['tag_num'] = scb_edd_clean_tag_input($file['tag']);

    //store repo url and version tag we want to fetch in our custom index to pass to filter
    $repo_name        = ltrim(untrailingslashit($repo[0]), "/");
    $repo             = explode("/", $repo_name);
    $file['repo']     = $repo_name;
    $file['package']  = isset($repo[1]) ? $repo[1] : $repo[0];
    $file['protocol'] = $protocol;
    $file['repo_url'] = $protocol . "://" . $file['repo'] . "/@" . $file['tag'];

    return $file;
}
function scb_edd_clean_tag_input($tag)
{
    if (substr(strtolower($tag), 0, 1) == 'v')
    {
        return substr($tag, 1);
    }
    elseif (substr(strtolower($tag), 0, 3) == 'ver')
    {
        return substr($tag, 3);
    }
    else
    {
        return $tag;
    }
}

function scb_edd_clear_temp_indexes($file)
{
    $temp_indexes = array('repo', 'tag', 'package', 'repo_url', 'protocol', 'tag_num', 'new_tag');
    foreach ($temp_indexes as $temp_index)
    {
        unset($file[$temp_index]);
    }
    return $file;
}

function scb_edd_html_email_filter()
{
    return "text/html";

}
function scb_edd_send_email($subject, $message)
{
    add_filter('wp_mail_content_type', 'scb_edd_html_email_filter');
    wp_mail(get_option('admin_email'), $subject, $message);
    remove_filter('wp_mail_content_type', 'scb_edd_html_email_filter');

}

function scb_edd_post($url, $data, $json = false)
{
    $ch       = curl_init();
    $data     = is_array($data) || is_object($data) ? ($json ? json_encode($data) : $data) : $data;
    $postvars = '';
    if ($json || !is_array($data))
    {
        $postvars = $data;
    }
    elseif (is_array($data))
    {
        foreach ($data as $key => $value)
        {
            $postvars .= $key . "=" . $value . "&";
        }
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1); //0 for a get request
    curl_setopt($ch, CURLOPT_POSTFIELDS, ($postvars));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERAGENT, 'scriptburn.com remote deploy');
    if ($json)
    {
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postvars))
        );
    }

    $output = curl_exec($ch);
    $error  = curl_error($ch);
    if ($error)
    {
        return new WP_Error('scb', $error);
    }

    curl_close($ch);
    return array('body' => $output);

}

function scb_edd_unique_filename_callback($dir, $name, $ext)
{
// p_d("$dir, $name, $ext");
    p_l("$dir, $name, $ext");
    $filename = "$name$ext";
    $number   = 0;
    while (file_exists($dir . "/$filename"))
    {
        $number++;
        $filename = $name . "_" . $number . $ext;
    }
    return $filename;
}
