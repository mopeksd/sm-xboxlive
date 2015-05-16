<?php
/**
 *
 */

if (!defined('SMF'))
  die('No direct access...');

/**
 * Modify SimpleXBL settings
 */
function ModifySimpleXBLSettings($return_config = false)
{
  global $txt, $scripturl, $context, $modSettings, $sourcedir;

  require_once($sourcedir . '/ManageSettings.php');

  isAllowedTo('xbl_admin');

  $config_vars = array(
    array('check',  'xbl_enable'),
    array('int',    'simplexbl_items_per_page',   'subtext' => $txt['simplexbl_items_per_page_subtext']),
    array('int',    'simplexbl_required_posts',   'subtext' => $txt['simplexbl_required_posts_subtext']),
    array('int',    'simplexbl_user_timeout',     'subtext' => $txt['simplexbl_user_timeout_subtext']),
    array('check',  'simplexbl_show_unranked',    'subtext' => $txt['simplexbl_show_unranked_subtext']),
    array('int',    'simplexbl_stat_limit',       'subtext' => $txt['simplexbl_stat_limit_subtext'])
  );

  if ($return_config)
    return $config_vars;

  $context['post_url'] = $scripturl . '?action=admin;area=simplexbl;save';
  $context['settings_title'] = $txt['simplexbl_settings_title'];

  if (isset($_GET['save']))
  {
    checkSession();
    $save_vars = $config_vars;
    saveDBSettings($save_vars);
    redirectexit('action=admin;area=simplexbl;saved');
  }

  prepareDBSettingContext($config_vars);
}

?>