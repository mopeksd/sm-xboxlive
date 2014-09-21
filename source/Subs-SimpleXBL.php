<?php
/**
 * SimpleXBL
 *
 * @package     SMF
 * @author      Jason Clemons <jason@xboxleaders.com>
 * @file        Subs-SimpleXBL.php
 * @copyright   2014 Jason Clemons <https://github.com/zilladotexe/>
 * @license     MIT
 * @version     3.0.0
 */

if (!defined('SMF'))
{
    die('Hacking attempt...');
}

/**
 * Implement integrate_menu_buttons
 * Add some SimpleXBL settings to the main menu under the admin menu
 */
function sxblMenuButtons(&$menu_buttons)
{
    global $scripturl, $txt, $modSettings;

    $find = 0;
    reset($menu_buttons);

    while((list($key, $val) = each($menu_buttons)) && $key != 'calendar')
    {
        $find++;
    }

    $menu_buttons = array_merge(
        array_slice($menu_buttons, 0, $find),
        array(
            'simplexbl' => array(
                'title' => $txt['simplexbl'],
                'href' => $scripturl . '?action=simplexbl',
                'show' => true,
                'sub_buttons' => array(
                ),
            ),
        ),
        array_slice($menu_buttons, $find)
    );
}

/**
 * Implement integrate_admin_areas
 * Add SimpleXBL options to the admin panel
 */
function sxblAdminAreas(&$admin_areas)
{
    global $txt, $modSettings;

    // We insert it after Features and Options
    $counter = 0;
    foreach ($admin_areas['config']['areas'] as $area => $dummy)
    {
        if (++$counter && $area == 'featuresettings')
            break;
    }

    $admin_areas['config']['areas'] = array_merge(
        array_slice($admin_areas['config']['areas'], 0, $counter, true),
        array('simplexbl' => array(
            'label' => $txt['simplexbl'],
            'function' => 'ModifySimpleXBLSettings',
            'icon' => 'maintain.gif',
            'subsections' => array(
            ),
        )),
        array_slice($admin_areas['config']['areas'], $counter, null, true)
    );
}

/**
 * Set the simplexbl action
 */
function sxblActions(&$actionArray)
{
    $actionArray['simplexbl'] = array('SimpleXBL.php', 'SimpleXBL');
}

/**
 * Find a needle in a haystack
 */
function sxbl_find_string($haystack, $start, $finish)
{
    if (!empty($haystack)) {
        $s = explode($start, $haystack);

        if (!empty($s[1])) {
            $s = explode($finish, $s[1]);
            if (!empty($s[0])) {
                return $s[0];
            }
        }
    }

    return false;
}

/**
 * Makes an HTTP request using cURL or file_get_contents()
 */
function sxbl_http_get($url, $parameters = array())
{
    global $sourcedir;

    // No cURL? No problem.
    if (!function_exists('curl_init'))
    {
        require_once($sourcedir . '/Subs-Package.php');

        $url = $url . '?' . http_build_query($parameters, null, '&');

        return fetch_web_data($url);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0)');
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if (is_array($parameters) && !empty($parameters))
    {
        $url = $url . '?' . http_build_query($parameters, null, '&');
    }

    curl_setopt($ch, CURLOPT_URL, $url);

    $data = curl_exec($ch);
    curl_close($ch);

    return $data;
}

/**
 * Converts non-UTF strings to UTF-8
 */
function sxbl_clean_string($string)
{
    global $context;

    return iconv('UTF-8', !empty($context['character_set']) ? $context['character_set'] . '//IGNORE' : 'ISO-8859-1//IGNORE', $string);
}

/**
 * Retrieves data from the API, then parses it into
 * a useable array
 */
function sxbl_get_data($member)
{
    global $context, $modSettings, $scripturl;

    $html = sxbl_http_get('http://gamercard.xbox.com/' . $member['gamertag'] . '.card');

    $player_data = array();
    $player_data['valid'] = (stripos($html, 'http://image.xboxlive.com//global/t.FFFE07D1/tile/0/20000') !== false) ? false : true;

    if ($player_data['valid'])
    {
        $player_data['gamertag'] = sxbl_find_string($html, '<title>', '</title>');

        // Find the account status and gender
        $temp_string = sxbl_find_string($html, '<div class="XbcGamercard', '">');
        $player_data['account_gold'] = stripos($temp_string, 'Gold') !== false ? true : false;
        if (stripos($temp_string, 'Female') !== false)
        {
            $player_data['gender'] = 'female';
        }
        else if (stripos($temp_string, 'Male') !== false)
        {
            $player_data['gender'] = 'male';
        }
        unset($temp_string);

        // Find the total reputation value
        $player_data['reputation'] = 0;
        preg_match_all($html, '~<div class="Star (.*?)">~si', $temp_data);
        $values = array(
            'Empty' => 0,
            'Quarter' => 1,
            'Half' => 2,
            'ThreeQuarter' => 3,
            'Full' => 4
        );
        foreach ($temp_data[1] as $k)
        {
            $player_data['reputation'] = $player_data['reputation'] + $values[$k];
        }
        unset($temp_data);

        // Easy values to find
        $player_data['avatar']      = sxbl_find_string($html, '<img id="Gamerpic" href="', '" alt=');
        $player_data['gamerscore']  = sxbl_find_string($html, '<div id="Gamerscore">', '</div>');
        $player_data['location']    = sxbl_find_string($html, '<div id="Location">', '</div>');
        $player_data['motto']       = sxbl_find_string($html, '<div id="Name">', '</div>');
        $player_data['name']        = sxbl_find_string($html, '<div id="Name">', '</div>');
        $player_data['bio']         = sxbl_find_string($html, '<div id="Bio">', '</div>');

        // Recent games played, up to 5
        $player_data['recent']      = array();
        if (stripos($data, 'class="NoGames"') !== false)
        {
            return $player_data;
        }
        preg_match($html, '~<ol id="PlayedGames.*?>(.*?)<\/ol>~si', $temp_games);
        preg_match_all($temp_games[1], '~<li.*?>(.*?)<\/li>~si', $temp_data);

        $i = 0;
        foreach ($temp_data[1] as $data)
        {
            $player_data['recent'][$i]['tid']                   = sxbl_find_string($data, 'titleId=', '&');
            $player_data['recent'][$i]['link']                  = sxbl_find_string($data, '<a href="', '"');
            $player_data['recent'][$i]['image']                 = sxbl_find_string($data, '<img src="', '" alt=');
            $player_data['recent'][$i]['title']                 = sxbl_find_string($data, '<span class="Title">', '</span>');
            $player_data['recent'][$i]['last_played']           = sxbl_find_string($data, '<span class="LastPlayed">', '</span>');
            $player_data['recent'][$i]['gamerscore']            = sxbl_find_string($data, '<span class="EarnedGamerscore">', '</span>');
            $player_data['recent'][$i]['total_gamerscore']      = sxbl_find_string($data, '<span class="AvailableGamerscore">', '</span>');
            $player_data['recent'][$i]['achievements']          = sxbl_find_string($data, '<span class="EarnedAchievements">', '</span>');
            $player_data['recent'][$i]['total_achievements']    = sxbl_find_string($data, '<span class="AvailableAchievements">', '</span>');
            $player_data['recent'][$i]['percent_complete']      = sxbl_find_string($data, '<span class="PercentageComplete">', '</span>');
        }
        unset($temp_data);

        $player_data['recent'] = serialize($player_data['recent']);
    }

    return $player_data;
}

/**
 * Updates a member's data for the leaderboard
 */
function sxbl_update_member($player)
{
    global $context, $smcFunc;

    // OK, so he exists. Now what?
    if ($player['valid'] === true)
    {
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}xbox_leaders
            SET
                account_status = {int:account_status}, gender = {string:gender}, is_cheater = {int:is_cheater}, link = {string:link},
                gamertag = {string:gamertag}, avatar = {string:avatar}, reputation = {string:reputation}, gamerscore = {string:gamerscore},
                location = {string:location}, motto = {string:motto}, name = {string:name}, bio = {string:bio}, updated = {int:updated}
            WHERE id_member = {int:member}',
            array(
                'member'            => $player['id'],
                'account_status'    => $player['account_status'],
                'gender'            => $player['gender'],
                'is_cheater'        => $player['is_cheater'],
                'link'              => $player['link'],
                'gamertag'          => $player['gamertag'],
                'avatar'            => $player['avatar'],
                'reputation'        => $player['reputation'],
                'gamerscore'        => $player['gamerscore'],
                'location'          => $player['location'],
                'motto'             => $player['motto'],
                'name'              => $player['name'],
                'bio'               => $player['bio'],
                'updated'           => time()
            )
        );

        // If there are games to insert, do it
        if ($player['games'] && $player['lastplayed'])
        {
            $smcFunc['db_query']('', '
                UPDATE {db_prefix}xbox_leaders
                SET last_played = {string:lastplayed}
                WHERE id_member = {int:member}',
                array(
                    'lastplayed'	=> $player['lastplayed'],
                    'member'		=> $player['id'],
                )
            );

            // Remove the games before we update it
            @$smcFunc['db_query']('', '
                DELETE FROM {db_prefix}xbox_games
                WHERE id_member = {int:id_member}',
                array(
                    'id_member' => $player['id'],
                )
            );

            // Update the games list too!
            foreach ($player['games'] as $key => $game)
            {
                $game['title'] = sxbl_clean_string($game['title']);

                $smcFunc['db_insert']('ignore',
                    '{db_prefix}xbox_games',
                    array(
                        'id_member' => 'int',
                        'position'  => 'int',
                        'title'     => 'string',
                        'link'      => 'string',
                        'image'     => 'string',
                        'updated'   => 'int'
                    ),
                    array(
                        $player['id'],
                        $key,
                        $game['title'],
                        $game['link'],
                        $game['image'],
                        time()
                    ),
                    array()
                );

                // Might as well update the archive
                $smcFunc['db_insert']('ignore',
                    '{db_prefix}xbox_games_list',
                    array(
                        'tid'   => 'string',
                        'title' => 'string',
                        'image' => 'string',
                    ),
                    array(
                        $game['tid'],
                        $game['title'],
                        $game['image'],
                    ),
                    array('tid')
                );
            }
        }
    }

    return true;
}

/**
 * Removes a member completely from the app
 */
function sxbl_delete_member($member)
{
    global $smcFunc;

    if (is_numeric($member))
    {
        // Remove them from the leaders table
        $smcFunc['db_query']('', '
            DELETE FROM {db_prefix}xbox_leaders
            WHERE id_member = {int:member}',
            array(
                'member' => $member,
            )
        );

        // Also remove them from the games table
        $smcFunc['db_query']('', '
            DELETE FROM {db_prefix}xbox_games
            WHERE id_member = {int:member}',
            array(
                'member' => $member,
            )
        );

        // Might as well remove from the members table too
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}members
            SET gamertag = \'\'
            WHERE id_member = {int:member}',
            array(
                'member' => $member,
            )
        );
    }
    else
    {
        return false;
    }

    return true;
}

/**
 * Loads all data pertaining to a given gamer
 */
function sxbl_load_gamer_data($mid)
{
    global $smcFunc, $modSettings, $settings, $txt;

    $request = $smcFunc['db_query']('', '
        SELECT xbl.*, xbg.*
        FROM {db_prefix}xbox_leaders AS xbl
            LEFT JOIN {db_prefix}xbox_games AS xbg ON (xbg.id_member = xbl.id_member)
        WHERE xbl.id_member = {int:id_member}
        ORDER BY xbg.last_played DESC',
        array(
            'id_member' => $mid
        )
    );
    $gamer_data = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $gamer_data[] = array(
            'id' => $row['id_member'],
            'gamertag' => array(
                'raw' => $row['gamertag'],
                'href' => '<a href="http://live.xbox.com/member/' . sxbl_convert_gamertag($row['gamertag']) . '">' . $row['gamertag'] . '</a>',
            ),
            'gamerscore' => $row['gamerscore'],
            'reputation' => array(
                'raw' => $row['reputation'],
                'img' => '<img src="' . $settings['images_url'] . '/xbl/' . $row['reputation'] . '.png" alt="' . $row['reputation'] . '" title="' . $row['reputation'] . '" />',
            ),
            'account_status' => $row['account_status'],
            'zone' => 'N/A',
            'avatar' => array(
                'raw' => $row['avatar'],
                'img' => '<img src="' . $row['avatar'] . '" width="32px" height="32px" alt="" />',
            ),
            'location' => $row['location'],
            'motto' => $row['motto'],
            'name' => $row['name'],
            'bio' => $row['bio'],
        );

        $gamer_data['games'][$row['tid']] = array(
            'tid' => $row['tid'],
            'title' => $row['title'],
            'tile' => $row['image'],
            'egscore' => $row['earned_gamerscore'],
            'agscore' => $row['available_gamerscore'],
            'echeevo' => $row['earned_achievements'],
            'acheevo' => $row['available_achievements'],
            'per_com' => $row['percentage_complete'],
            'last_played' => array(
                'raw' => $row['last_played'],
                'date' => date('F j, Y', $row['last_played']),
            ),
        );
    }
    $smcFunc['db_free_result']($request);

    return $gamer_data;
}

/**
 * Counts up some basic stats for the leaderboard
 */
function sxbl_stats_basic()
{
    global $smcFunc, $modSettings;

    // Overall
    $request = $smcFunc['db_query']('', '
        SELECT COUNT(mem.gamertag) AS usercount,
            SUM(xbl.gamerscore) AS gamerscore,
            SUM(xbl.reputation) AS reputation,
            SUM(xbl.account_status) AS gold
        FROM {db_prefix}members AS mem
            LEFT JOIN {db_prefix}xbox_leaders AS xbl ON (mem.id_member = xbl.id_member)
        WHERE mem.gamertag != \'\'
            AND mem.posts >= {int:required_posts}
            AND mem.last_login >= {int:user_timeout}
            AND xbl.gamerscore >= {int:show_unranked}',
        array(
            'required_posts' => !empty($modSettings['xbl_required_posts']) ? $modSettings['xbl_required_posts'] : 0,
            'user_timeout' => time() - ($modSettings['xbl_user_timeout'] * 86400),
            'show_unranked' => !empty($modSettings['xbl_show_unranked']) ? 0 : 1,
        )
    );
    $count = array();
    $row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);

    $count['members'] 			= comma_format($row['usercount']);
    $count['score'] 			= comma_format($row['gamerscore']);
    $count['reputation'] 		= $row['reputation'] != 0 ? ceil($row['reputation'] / $row['usercount']) : 0;
    $count['silver'] 			= comma_format($row['usercount'] - $row['gold']);
    $count['gold'] 				= comma_format($row['gold']);

    // Games
    $request = $smcFunc['db_query']('', '
        SELECT COUNT(DISTINCT title) AS gamescount
        FROM {db_prefix}xbox_games AS xbg
            LEFT JOIN {db_prefix}xbox_leaders AS xbl ON (xbg.id_member = xbl.id_member)',
        array()
    );
    $row = $smcFunc['db_fetch_assoc']($request);
    $smcFunc['db_free_result']($request);

    $count['gamescount'] = comma_format($row['gamescount']);

    return $count;
}

/**
 * Returns a list of top avatars for the leaderboard
 */
function sxbl_stats_top_avatars()
{
    global $smcFunc, $modSettings;

    $filtered_avatars = array(
        'http://tiles.xbox.com/tiles/8y/ov/0Wdsb2JhbC9EClZWVEoAGAFdL3RpbGUvMC8yMDAwMAAAAAAAAAD+ACrT.jpg',
        '/xweb/lib/images/QuestionMark64x64.jpg',
        'http://image.xboxlive.com//global/t.FFFE07D1/tile/0/20000'
    );

    $request = $smcFunc['db_query']('', '
        SELECT
            mem.id_member, mem.real_name, mem.posts, mem.last_login,
            xbl.avatar, xbl.gamerscore, COUNT(*) AS count
        FROM {db_prefix}members AS mem
            LEFT JOIN {db_prefix}xbox_leaders AS xbl ON (mem.id_member = xbl.id_member)
        WHERE mem.gamertag != \'\'
            AND mem.posts >= {int:required_posts}
            AND mem.last_login >= {int:user_timeout}
            AND xbl.gamerscore >= {int:show_unranked}
            AND xbl.avatar NOT IN ({string:exclude})
        GROUP BY xbl.avatar
        ORDER BY
            count DESC,
            xbl.gamerscore ASC
        LIMIT {int:limit}',
        array(
            'required_posts' => !empty($modSettings['xbl_required_posts']) ? $modSettings['xbl_required_posts'] : 0,
            'user_timeout' => time() - ($modSettings['xbl_user_timeout'] * 86400),
            'show_unranked' => !empty($modSettings['xbl_show_unranked']) ? 0 : 1,
            'exclude' => implode('\', \'', array_values($filtered_avatars)),
            'limit' => !empty($modSettings['xbl_stats_limit']) ? $modSettings['xbl_stats_limit'] : 5,
        )
    );
    $avatars = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $avatars[] = $row;
    }
    $smcFunc['db_free_result']($request);

    return $avatars;
}

/**
 * Returns a list of the top players for the leaderboard
 */
function sxbl_stats_top_players()
{
    global $smcFunc, $modSettings;

    $request = $smcFunc['db_query']('', '
        SELECT
            mem.id_member, mem.real_name, mem.posts, mem.last_login,
            xbl.gamertag, xbl.gamerscore, COUNT(*) AS count
        FROM {db_prefix}members AS mem
            LEFT JOIN {db_prefix}xbox_leaders AS xbl ON (mem.id_member = xbl.id_member)
        WHERE mem.gamertag != \'\'
            AND mem.posts >= {int:required_posts}
            AND mem.last_login >= {int:user_timeout}
            AND xbl.gamerscore >= {int:show_unranked}
        GROUP BY mem.id_member
        ORDER BY
            xbl.gamerscore DESC,
            xbl.gamertag ASC
        LIMIT {int:limit}',
        array(
            'required_posts' => !empty($modSettings['xbl_required_posts']) ? $modSettings['xbl_required_posts'] : 0,
            'user_timeout' => time() - ($modSettings['xbl_user_timeout'] * 86400),
            'show_unranked' => !empty($modSettings['xbl_show_unranked']) ? 0 : 1,
            'limit' => !empty($modSettings['xbl_stats_limit']) ? $modSettings['xbl_stats_limit'] : 5,
        )
    );
    $players = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $players[] = $row;
    }
    $smcFunc['db_free_result']($request);

    return $players;
}

/**
 * Returns a list of the top played games for the leaderboard
 */
function sxbl_stats_top_games()
{
    global $smcFunc, $modSettings;

    $request = $smcFunc['db_query']('', '
        SELECT xbg.title, xbg.link, xbg.image,
            COUNT(*) AS count
        FROM {db_prefix}xbox_games AS xbg
            LEFT JOIN {db_prefix}xbox_leaders AS xbl ON (xbg.id_member = xbl.id_member)
        WHERE xbl.gamerscore >= {int:show_unranked}
        GROUP BY title
        ORDER BY 
            count DESC,
            position ASC,
            title ASC
        LIMIT {int:limit}',
        array(
            'show_unranked' => !empty($modSettings['xbl_show_unranked']) ? 0 : 1,
            'limit' => !empty($modSettings['xbl_stats_limit']) ? $modSettings['xbl_stats_limit'] : 5,
        )
    );
    $games = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $games[] = array(
            'link' => $row['link'],
            'title' => $row['title'],
            'image' => $row['image'],
            'count' => $row['count'],
        );
    }
    $smcFunc['db_free_result']($request);

    return $games;
}

/**
 * Update the data for the entire app based on scheduled tasks
 */
function scheduled_update_gamertags()
{
    global $smcFunc, $modSettings;


    $time = time();

    $query = '
        SELECT mem.id_member, mem.posts, mem.last_login, mem.gamertag, xbl.*
        FROM {db_prefix}members AS mem
            LEFT JOIN {db_prefix}xbox_leaders AS xbl ON (xbl.id_member = mem.id_member)
        WHERE mem.gamertag != \'\'
            AND mem.posts >= {int:required_posts}
            AND mem.last_login >= {int:user_timeout}
            AND (xbl.updated IS NULL OR DATE_FORMAT(FROM_UNIXTIME(xbl.updated), \'%Y-%m-%d\') != \'' . date('Y-m-d', $time) . '\')
        ORDER BY
            xbl.updated ASC,
            mem.id_member ASC
        ';

    $params = array(
        'required_posts' => !empty($modSettings['xbl_required_posts']) ? $modSettings['xbl_required_posts'] : 0,
        'user_timeout' => $time - ($modSettings['xbl_user_timeout'] * 86400),
    );

    $full_result = $smcFunc['db_query']('', $query, $params);
    $query_limit = ceil($smcFunc['db_num_rows']($full_result) / ceil((strtotime(date('Y-m-d', strtotime('+1 day'))) - $time) / 60)) + 5;
    $smcFunc['db_free_result']($full_result);

    // Now make the final queries needed
    $request = $smcFunc['db_query']('', $query . 'LIMIT 0, ' . $query_limit, $params);

    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        if ($data = sxbl_get_data($row['id_member']))
        {
            sxbl_update_member($data);
        }
    }
    $smcFunc['db_free_result']($request);

    return true;
}

?>