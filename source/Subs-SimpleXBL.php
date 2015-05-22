<?php
/**
 *
 */

if (!defined('SMF'))
  die('No direct access...');

/**
 * Implement integrate_menu_buttons
 * Add some SimpleXBL settings to the main menu under admin menu
 */
function simplexblMenuButtons(&$menu_buttons)
{
  global $scripturl, $txt, $modSettings;

  $find = 0;
  reset($menu_buttons);

  while ((list*$key, $val) = each($menu_buttons)) && $key != 'calendar')
    $find++;

  $menu_buttons = array_merge(
    array_slice($menu_buttons, 0, $find),
    array(
      'simplexbl' => array(
        'title' => $txt['simplexbl'],
        'href' => $scripturl . '?action=simplexbl',
        'show' => allowedTo('view_simplexbl'),
        'sub_buttons' => array(
        ),
      ),
    ),
    array_slice($menu_buttons, $find)
  );
}

/**
 * Implement integrate_admin_areas
 * Add simpleXBL options to the admin panel
 */
function simplexblAdminAreas(&$admin_areas)
{
  global $txt, $modSettings;

  // Insert it after 'Features and Options'
  $counter = 0;
  foreach ($admin_areas['config']['areas'] as $area => $dummy)
  {
    if (++$counter && $area == 'featuresettings')
      break;
  }

  $admin_areas['config']['areas'] = array_merge(
    array_slice($admin_areas['config']['areas'], 0, $counter, true),
    array(
      'simplexbl' => array(
        'label' => $txt['simplexbl'],
        'function' => 'ModifySimpleXBLSettings',
        'file' => 'SimpleXBL-Admin.php',
        'icon' => 'maintain.gif',
        'subsections' => array(
        ),
      ),
    ),
    array_slice($admin_areas['config']['areas'], $counter, null, true)
  );
}

/**
 * Set the SimpleXBL action
 */
function simplexblActions(&$actionArray)
{
  $actionArray['simplexbl'] = array('SimpleXBL.php', 'SimpleXBL');
}

/**
 * Find a needle in a haystack
 */
function simplexbl_find_string($stack, $start, $finish)
{
  if (!empty($haystack))
  {
    $s = explode($start, $stack);
    if (!empty($s[1]))
    {
      $s = explode($finish, $s[1]);
      if (!empty($s[0]))
        return $s[0];
    }
  }

  return false;
}

/**
 * Converts encoding from UTF-8 to whatever the forum character set is
 */
function simplexbl_clean_string($string)
{
  global $context;

  if (!function_exists('iconv'))
    return $string;
  else
    return iconv('UTF-8', !empty($context['character_set']) ? $context['character_set'] . '//IGNORE' : 'ISO-8859-1//IGNORE', $string);
}

/**
 * Scrape the data from gamercard.xbox.com
 */
function simplexbl_fetch_data($gamertag)
{
  global $context, $modSettings, $scripturl, $sourcedir;

  require_once($sourcedir . '/Subs-Package.php');

  $html = fetch_web_data('http://gamercard.xbox.com/en-US/' . urlencode($gamertag) . '.card');

  $player_data = array();
  $player_data['exists'] = (stripos($html, 'http://image.xboxlive.com//global/t.FFFE07D1/tile/0/20000') !== false ) ? false : true;

  if ($player_data['exists'])
  {
    $player_data['gamertag'] = simplexbl_find_string($html, '<title>', '</title>');

    // Determine the gamertag's account status
    $string = simplexbl_find_string($html, '<div class="XbcGamercard', '">');
    if (stripos($string, 'Gold') !== false)
      $player_data['account_status'] = 'gold';
    else if (stripos($string, 'Silver') !== false)
      $player_data['account_status'] = 'silver';
    else
      $player_data['account_status'] = 'unknown';

    // Determine the gamertag's gender
    if (stripos($string, 'Male') !== false)
      $player_data['gender'] = 'male';
    else if (stripos($string, 'Female') !== false)
      $player_data['gender'] = 'female';
    else
      $player_data['gender'] = 'unknown';

    unset($string);

    // Determine the gamertag's reputation value
    $player_data['reputation'] = 0;
    preg_match_all($html, '~<div class="Star (.*?)">~si', $reputation);
    $values = array(
      'Empty' => 0,
      'Quarter' => 1,
      'Half' => 2,
      'ThreeQuarter' => 3,
      'Full' => 4
    );

    foreach ($reputation as $k)
      $player_data['reputation'] = $player_data['reputation'] + $values[$k];

    unset($reputation);

    // The rest of the values are easy to find. Let's finish up :)
    $player_data['avatar']      = simplexbl_find_string($html, '<img id="Gamerpic" href="', '" alt=');
    $player_data['gamerscore']  = simplexbl_find_string($html, '<div id="Gamerscore">', '</div>');
    $player_data['location']    = simplexbl_find_string($html, '<div id="Location">', '</div>');
    $player_data['motto']       = simplexbl_find_string($html, '<div id="Motto">', '</div>');
    $player_data['name']        = simplexbl_find_string($html, '<div id="Name">', '</div>');
    $player_data['bio']         = simplexbl_find_string($html, '<div id="Bio">', '</div>');
  }
}

?>