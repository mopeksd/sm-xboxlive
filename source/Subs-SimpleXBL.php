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

    // Determine if Xbox has flagged this gamertag as a cheater
    if (stripos($string, 'Cheater') !== false)
      $player_data['cheater'] = true;
    else
      $player_data['cheater'] = false;

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

    // Let's get the first 5 recently played games since that's all we can get...
    $player_data['recent'] = array();

    // If there's no games, just return what we have
    if (stripos($html, 'class="NoGames"') !== false)
      return $player_data;

    // Now, grab all the game data
    preg_match($html, '~<ol id="PlayedGames.*?>(.*?)<\/ol>~si', $temp_games);
    preg_match_all($temp_games[1], '~<li.*?>(.*?)<\/li>~si', $temp_data);

    $i = 0;
    foreach ($temp_data[1] as $data)
    {
      $player_data['recent'][$i]['tid']                 = simplexbl_find_string($data, 'titleId=', '&');
      $player_data['recent'][$i]['url']                 = simplexbl_find_string($data, '<a href="', '"');
      $player_data['recent'][$i]['image']               = simplexbl_find_string($data, '<img src="', '" alt=');
      $player_data['recent'][$i]['title']               = simplexbl_find_string($data, '<span class="Title">', '</span>');
      $player_data['recent'][$i]['last_played']         = simplexbl_find_string($data, '<span class="LastPlayed">', '</span>');
      $player_data['recent'][$i]['gamerscore']          = simplexbl_find_string($data, '<span class="EarnedGamerscore">', '</span>');
      $player_data['recent'][$i]['total_gamerscore']    = simplexbl_find_string($data, '<span class="AvailableGamerscore">', '</span>');
      $player_data['recent'][$i]['achievements']        = simplexbl_find_string($data, '<span class="EarnedAchievements">', '</span>');
      $player_data['recent'][$i]['total_achievements']  = simplexbl_find_string($data, '<span class="AvailableAchievements">', '</span>');
      $player_data['recent'][$i]['percent_complete']    = simplexbl_find_string($data, '<span class="PercentageComplete">', '</span>');
    }

    unset($temp_data);
    unset($temp_games);

    $player_data['recent'] = serialize($player_data['recent']);
  }

  return $player_data;
}

/**
 * Update the database data for a gamertag
 */
function simplexbl_update_member($player)
{
  global $context, $smcFunc;

  // Make sure the member exists
  if ($player['exists'])
  {
    $smcFunc['db_query']('', '
      UPDATE {db_prefix}xbox_leaders
      SET gamertag = {string:gamertag}, account_status = {int:account_status}, gender = {string:gender}, cheater = {int:cheater},
        avatar = {string:avatar}, reputation = {int:reputation}, gamerscore = {int:gamerscore}, location = {string:location},
        motto = {string:motto}, name = {string:name}, bio = {string:bio}, updated = {int:updated}
      WHERE id_member = {int:id_member}',
      array(
        'member' => $player['id'], 'gamertag' => $player['gamertag'], 'account_status' => $player['account_status'],
        'gender' => $player['gender'], 'cheater' => $player['cheater'], 'avatar' => $player['avatar'], 'reputation' => $player['reputation'],
        'gamerscore' => $player['gamerscore'], 'location' => $player['location'], 'motto' => $player['motto'], 'name' => $player['name'],
        'bio' => $player['bio'], 'updated' => time(),
      ),
    );

    // If there are games to insert, do it here
    if (!empty($player['recent']))
    {
      $smcFunc['db_query']('', '
        UPDATE {db_prefix}xbox_leaders
        SET recent = {string:recent}
        WHERE id_member = {int:id_member}',
        array(
          'recent' => $player['recent'],
          'id_member' => $player['id'],
        ),
      );

      // Update the games list
      $games = unserialize($player['recent']);
      foreach ($games as $key => $game)
      {
        $game['title'] = simplexbl_clean_string($game['title']);

        $smcFunc['db_insert']('ignore',
          '{db_prefix}xbox_games',
          array(
            'id_member' => 'int', 'position' => 'int', 'title' => 'string',
            'link' => 'string', 'image' => 'string', 'updated' => 'int',
          ),
          array(
            $player['id'], $key, $game['title'],
            $game['link'], $game['image'], time(),
          ),
        );

        // Update the full game archive
        $smcFunc['db_insert']('ignore',
          '{db_prefix}xbox_games_list',
          array(
            'tid' => 'string', 'title' =>'string', 'image' => 'string',
          ),
          array(
            $game['tid'], $game['title'], $game['image'],
          ),
          array('tid'),
        );
      }
    }
  }
}



?>