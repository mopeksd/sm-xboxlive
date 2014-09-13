<?php
/**
 * SimpleXBL
 *
 * @package     SMF
 * @author      Jason Clemons <jason@xboxleaders.com>
 * @file        Subs-SimpleXBL.php
 * @copyright   2014 Jason Clemons <https://github.com/jasonclemons>
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
        array_slice($admin_areas['config']['areas'], 0, $counter, TRUE),
        array('simplexbl' => array(
            'label' => $txt['simplexbl'],
            'function' => create_function(NULL, 'ModifySimpleXBLSettings();'),
            'icon' => 'maintain.gif',
            'subsections' => array(
            ),
        )),
        array_slice($admin_areas['config']['areas'], $counter, NULL, TRUE)
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
 * Direct the admin to the proper page of settings for SimpleXBL
 */
function ModifySimpleXBLSettings()
{
    global $txt, $context, $sourcedir;

    require_once($sourcedir . '/ManageSettings.php');

    $context['page_title'] = $txt['simplexbl'];

    $subActions = array(
        'basic' => 'ModifyBasicSXBLSettings',
    );

    loadGeneralSettingParameters($subActions, 'basic');

    // Load up all the tabs...
    $context[$context['admin_menu_name']]['tab_data'] = array(
        'title' => $txt['simplexbl'],
        'description' => $txt['simplexbl_desc'],
        'tabs' => array(
            'basic' => array(
            ),
        ),
    );

    $subActions[$_REQUEST['sa']]();
}

/**
 * Makes an HTTP request using cURL or file_get_contents()
 */
function http($url, $method = 'GET', $parameters = array())
{
    // No cURL? No problem.
    if (!function_exists('curl_init'))
    {
        if ($method !== 'POST')
        {
            log_error('critical', 'http() - You cannot make POST requests without cURL.');
            return;
        }

        $url = $url . '?' . http_build_query($parameters, null, '&');
        $data = file_get_contents($url);
        return $data;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0)');
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    //!!! This could be better, but it works for now...
    switch ($method)
    {
        case 'GET':
            $url = $url . '?' . http_build_query($parameters, null, '&');
        break;
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
        break;
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
function sxbl_get_data($data, $member)
{
    global $sourcedir, $context, $modSettings, $scripturl;

    $player['id'] = $member;

    if (!is_array($data) || $data === false)
    {
        return false;
    }
    else
    {
        $player['is_valid']			= $data['status']['is_valid'] === 'yes' ? 1 : 0;
        $player['account_status']	= $data['status']['tier'] === 'gold' ? 1 : 0;
        $player['gender']			= $data['profile']['gender'];
        $player['is_cheater']		= $data['status']['is_cheater'] === 'yes' ? 1 : 0;
        $player['link']				= $data['profile']['url'];
        $player['gamertag']			= $data['profile']['gamertag'];
        $player['avatar']			= $data['profile']['avatar_small'];
        $player['reputation']		= $data['profile']['reputation'];
        $player['gamerscore']		= $data['profile']['gamerscore'];
        $player['location']			= $data['profile']['location'];
        $player['motto']			= $data['profile']['motto'];
        $player['name']				= $data['profile']['name'];
        $player['bio']				= $data['profile']['bio'];

        if (!empty($data['recent_games']))
        {
            $player['games'] = array();

            foreach ($data['recent_games'] as $key => $val)
            {
                $val['last_played']									= strtotime($val['last_played']);

                $player['games'][$key]['tid']						= $val['tid'];
                $player['games'][$key]['link']						= $val['marketplace'];
                $player['games'][$key]['image']						= $val['tile'];
                $player['games'][$key]['title']						= $val['title'];
                $player['games'][$key]['last_played']				= $val['last_played'];
                $player['games'][$key]['earned_gamerscore']			= $val['gamerscore'];
                $player['games'][$key]['available_gamerscore']		= $val['max_gamerscore'];
                $player['games'][$key]['earned_achievements']		= $val['achievements'];
                $player['games'][$key]['available_achievements']	= $val['max_achievements'];
                $player['games'][$key]['percentage_complete']		= $val['percent_completed'];
            }

            $player['lastplayed'] = serialize($player['games']);
        }
        else
        {
            $player['games'] = false;
            $player['lastplayed'] = false;
        }

        return $player;
    }
}

/**
 * Updates a member's data for the leaderboard
 */
function sxbl_update_member($player)
{
    global $context, $smcFunc;

    // Make sure we have a valid gamertag
    $player_exists = $player['is_valid'] === 1 ? true : false;

    // OK, so he exists. Now what?
    if ($player_exists === true)
    {
        $smcFunc['db_query']('', '
            UPDATE {db_prefix}xbox_leaders
            SET
                account_status = {int:account_status}, gender = {string:gender},
                is_cheater = {int:is_cheater}, link = {string:link},
                gamertag = {string:gamertag}, avatar = {string:avatar},
                reputation = {string:reputation}, gamerscore = {string:gamerscore},
                location = {string:location}, motto = {string:motto},
                name = {string:name}, bio = {string:bio},
                updated = {int:updated}
            WHERE id_member = {int:member}',
            array(
                'member' => $player['id'], 'account_status' => $player['account_status'],
                'gender' => $player['gender'], 'is_cheater' => $player['is_cheater'],
                'link' => $player['link'], 'gamertag' => $player['gamertag'],
                'avatar' => $player['avatar'], 'reputation' => $player['reputation'],
                'gamerscore' => $player['gamerscore'], 'location' => $player['location'],
                'motto' => $player['motto'], 'name' => $player['name'],
                'bio' => $player['bio'], 'updated' => time()
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
                        'id_member' => 'int', 'position' => 'int',
                        'title' => 'string', 'link' => 'string',
                        'image' => 'string', 'updated' => 'int'
                    ),
                    array(
                        $player['id'], $key,
                        $game['title'], $game['link'],
                        $game['image'], time()
                    ),
                    array()
                );

                // Might as well update the archive
                $smcFunc['db_insert']('ignore',
                    '{db_prefix}xbox_games_list',
                    array(
                        'tid' => 'string', 'title' => 'string',
                        'image' => 'string',
                    ),
                    array(
                        $game['tid'], $game['title'],
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
 * Grabs a game's unique tid from a URL
 */
function sxbl_get_tid($string)
{
    $tid = parse_url($string);
    $tid = explode('&', html_entity_decode($tid['query']));
    $tid = explode('=', $tid['0']);

    return $tid['1'];
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
 * Pagination function for the leaderboard
 */
function list_getGamertags($start, $items_per_page, $sort)
{
    global $smcFunc, $modSettings;

    $request = $smcFunc['db_query']('', '
        SELECT
            xbl.id_member, mem.id_member, mem.real_name, mem.posts,
            mem.last_login, xbl.account_status, xbl.gamertag, xbl.avatar,
            xbl.reputation, xbl.gamerscore, xbl.last_played, xbl.updated
        FROM {db_prefix}xbox_leaders AS xbl
            LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = xbl.id_member)
        WHERE mem.gamertag != \'\'
        ORDER BY {raw:sort}
        LIMIT {int:start}, {int:per_page}',
        array(
            'sort' => $sort,
            'start' => $start,
            'per_page' => $items_per_page,
        )
    );

    $members = array();
    while ($row = $smcFunc['db_fetch_assoc']($request))
    {
        $members[] = $row;
    }
    $smcFunc['db_free_result']($request);

    return $members;
}

/**
 * Pagination function for the leaderboard
 */
function list_getNumGamertags()
{
    global $smcFunc;

    $request = $smcFunc['db_query']('', '
        SELECT COUNT(*)
        FROM {db_prefix}xbox_leaders AS xbl
            LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = xbl.id_member)
        WHERE mem.gamertag != \'\'',
        array(
        )
    );
    list ($num_members) = $smcFunc['db_fetch_row']($request);
    $smcFunc['db_free_result']($request);

    return $num_members;
}

/**
 * Update the data for the entire app based on scheduled tasks
 */
function scheduled_update_gamertags()
{
    global $sourcedir, $smcFunc, $modSettings;

    require_once($sourcedir . '/Subs-Package.php');

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
        $api = 'https://www.xboxleaders.com/api/all.json?gamertag=' . rawurlencode(strtolower($row['gamertag']));
        $data = json_decode(http($api, true), true);
        $card = sxbl_get_data($data, $row['id_member']);

        if ($card !== false)
        {
            sxbl_update_member($card);
        }
    }
    $smcFunc['db_free_result']($request);

    return true;
}

?>