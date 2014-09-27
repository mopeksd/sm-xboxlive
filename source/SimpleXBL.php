<?php
/**
 * SimpleXBL
 *
 * @package     SMF
 * @author      Jason Clemons <jason@xboxleaders.com>
 * @file        SimpleXBL.php
 * @copyright   2014 Jason Clemons <https://github.com/zilladotexe/>
 * @license     MIT
 * @version     3.0.0
 */

if (!defined('SMF'))
{
    die('Hacking attempt...');
}

/**
 * App version
 */
$context['xbl_version'] = '3.0.0';

/**
 * The main function for SimpleXBL
 * Loads all necessary subactions and templates
 */
function SimpleXBL()
{
    global $sourcedir;

    require_once($sourcedir . '/Subs-SimpleXBL.php');

    loadTemplate('SimpleXBL');
    loadLanguage('SimpleXBL');

    $subActions = array(
        'main' => 'Leaderboard',
        'delete' => 'DeleteMember',
    );

    if (!isset($_REQUEST['sa']) || !isset($subActions[$_REQUEST['sa']]))
    {
        $subAction = $subActions['main'];
    }
    else
    {
        $subAction = $subActions[$_REQUEST['sa']];
    }

    $subAction();
}

/**
 * Modify SimpleXBL-related settings
 */
function ModifyBasicSXBLSettings($return_config = false)
{

    global $txt, $scripturl, $context, $modSettings, $sourcedir;

    require_once($sourcedir . '/ManageSettings.php');

    isAllowedTo('xbl_admin');

    $config_vars = array(
        array('check', 'xbl_enable'),
        array('int',   'xbl_items_page',     'subtext' => $txt['xbl_items_page_sub']),
        array('int',   'xbl_required_posts', 'subtext' => $txt['xbl_required_posts_sub']),
        array('int',   'xbl_user_timeout',   'subtext' => $txt['xbl_user_timeout_sub']),
        array('check', 'xbl_show_unranked',  'subtext' => $txt['xbl_show_unranked_sub']),
        array('int',   'xbl_stat_limit',     'subtext' => $txt['xbl_stat_limit_sub']),
    );

    if ($return_config)
    {
        return $config_vars;
    }

    $context['post_url'] = $scripturl . '?action=admin;area=simplexbl;save';
    $context['settings_title'] = $txt['mods_cat_modifications_misc'];

    if (empty($config_vars))
    {
        $context['settings_save_dont_show'] = true;
        $context['settings_message'] = '<div class="centertext">' . $txt['modification_no_misc_settings'] . '</div>';

        return prepareDBSettingContext($config_vars);
    }

    if (isset($_GET['save']))
    {
        checkSession();
        $save_vars = $config_vars;
        saveDBSettings($save_vars);
        redirectexit('action=admin;area=simplexbl');
    }

    prepareDBSettingContext($config_vars);
}

/**
 * Load up the main leaderboard
 */
function Leaderboard()
{
    global $context, $txt, $scripturl, $sourcedir, $settings, $modSettings;

    if (!allowedTo('xbl_access_lb'))
    {
        fatal_lang_error('no_access');
    }

    // Stats
    $context['xbl_stats_basic'] = sxbl_stats_basic();
    $context['xbl_stats_avatars'] = sxbl_stats_top_avatars();
    $context['xbl_stats_players'] = sxbl_stats_top_players();
    $context['xbl_stats_games'] = sxbl_stats_top_games();

    $listOptions = array(
        'id' => 'xbl_leaders',
        'title' => $txt['xbl_leaders_title'],
        'base_href' => $scripturl . '?action=simplexbl',
        'items_per_page' => !empty($modSettings['xbl_items_page']) ? $modSettings['xbl_items_page'] : 20,
        'default_sort_col' => 'gamerscore',
        'default_sort_dir' => 'desc',
        'no_items_label' => $txt['xbl_no_data'],
        'no_items_align' => 'center',
        'get_items' => array(
            'function' => 'list_getGamertags',
        ),
        'get_count' => array(
            'function' => 'list_getNumGamertags',
        ),
        'columns' => array(
            'member' => array(
                'header' => array(
                    'value' => $txt['xbl_header_member'],
                ),
                'data' => array(
                    'sprintf' => array(
                        'format' => '<a href="' . strtr($scripturl, array('%' => '%%')) . '?action=profile;u=%1$d">%2$s</a>',
                        'params' => array(
                            'id_member' => false,
                            'real_name' => false,
                        ),
                    ),
                    'style' => 'text-align: center',
                ),
                'sort' => array(
                    'default' => 'real_name',
                    'reverse' => 'real_name DESC',
                ),
            ),
            'gamertag' => array(
                'header' => array(
                    'value' => $txt['xbl_header_gamertag'],
                ),
                'data' => array(
                    'function' => create_function('$rowData', '
                        $gamertag = \'<a href="http://live.xbox.com/en-US/Profile?Gamertag=\' . str_replace(\' \', \'%20\', $rowData[\'gamertag\']) . \'">\' . $rowData[\'gamertag\'] . \'</a>\';
                        return $gamertag;
                    '),
                    'style' => 'text-align: center',
                ),
                'sort' => array(
                    'default' => 'gamertag',
                    'reverse' => 'gamertag DESC',
                ),
            ),
            'avatar' => array(
                'header' => array(
                    'value' => $txt['xbl_header_avatar'],
                ),
                'data' => array(
                    'sprintf' => array(
                        'format' => '<a href="#"><img height="32" width="32" src="%1$s" alt="" /></a>',
                        'params' => array(
                            'avatar' => false,
                            'gamertag' => false,
                        ),
                    ),
                    'style' => 'text-align: center',
                ),
                'sort' => array(
                    'default' => 'avatar',
                    'reverse' => 'avatar DESC',
                ),
            ),
            'gamerscore' => array(
                'header' => array(
                    'value' => $txt['xbl_header_gamerscore'],
                ),
                'data' => array(
                    'function' => create_function('$rowData', '
                        global $settings;
                        $gamerscore = \'<img src="\' . $settings[\'images_url\'] . \'/xbl/gs.png" height="10" width="10" alt="" /> \' . comma_format($rowData[\'gamerscore\']);
                        return $gamerscore;
                    '),
                    'style' => 'text-align: center',
                ),
                'sort' => array(
                    'default' => 'gamerscore',
                    'reverse' => 'gamerscore DESC',
                ),
            ),
            'reputation' => array(
                'header' => array(
                    'value' => $txt['xbl_header_reputation'],
                ),
                'data' => array(
                    'sprintf' => array(
                        'format' => '<img src="' . $settings['images_url'] . '/xbl/%1$s.png" alt="" title="' . $txt['xbl_header_reputation'] . ': %1$s" />',
                        'params' => array(
                            'reputation' => false,
                        ),
                    ),
                    'style' => 'text-align: center',
                ),
                'sort' => array(
                    'default' => 'reputation',
                    'reverse' => 'reputation DESC',
                ),
            ),
            'last_played' => array(
                'header' => array(
                    'value' => $txt['xbl_header_lastplayed'],
                ),
                'data' => array(
                    'function' => create_function('$rowData', '
                        global $txt;

                        $player = unserialize($rowData[\'last_played\']);
                        $games = \'\';
                        if (empty($player))
                        {
                            $games .= $txt[\'xbl_privacy_settings\'];
                        }
                        else
                        {
                            foreach ($player as $game)
                                $games .= \'<a target="_blank" href="\' . $game[\'link\'] . \'"><img height="32" width="32" style="border: 1px black solid;" src="\' . $game[\'image\'] . \'" alt="" title="\' . $game[\'title\'] . \'" /></a> \';
                        }
                        return $games;
                    '),
                    'style' => 'text-align: center',
                ),
            ),
            'contact' => array(
                'header' => array(
                    'value' => $txt['xbl_header_contact'],
                ),
                'data' => array(
                    'function' => create_function('$rowData', '
                        global $scripturl, $settings, $user_info, $txt, $context;

                        $buttons = \'<a target="_blank" href="http://live.xbox.com/Profile?Gamertag=\' . $rowData[\'gamertag\'] . \'" title="\' . $txt[\'xbl_view_profile\'] . \'">
                                <img src="\' . $settings[\'images_url\'] . \'/xbl/user.png" alt="" />
                            </a> <a target="_blank" href="http://live.xbox.com/Messages/Compose?gamertag=\' . $rowData[\'gamertag\'] . \'" title="\' . $txt[\'xbl_send_msg\'] . \'">
                                <img src="\' . $settings[\'images_url\'] . \'/xbl/message.png" alt="" />
                            </a> \' . ($user_info[\'is_admin\'] ? \'<a href="\' . $scripturl . \'?action=simplexbl;sa=delete;id=\' . $rowData[\'id_member\'] . \';\' . $context[\'session_var\'] . \'=\' . $context[\'session_id\'] . \'" title="\' . $txt[\'xbl_delete\'] . \'">
                                <img src="\' . $settings[\'images_url\'] . \'/xbl/delete.png" alt="" />
                            </a>\' : \'\');

                        return $buttons;
                    '),
                    'style' => 'text-align: center',
                ),
            ),
        ),
        'additional_rows' => array(
            array(
                'position' => 'above_column_headers',
                'value' => '<a href="' . $scripturl . '?action=profile;area=forumprofile" title="' . $txt['xbl_add_gamertag'] . '">
                    <img src="' . $settings['images_url'] . '/xbl/add.png" alt="" /> <strong>' . $txt['xbl_add_gamertag'] . '</strong></a>',
            ),
        ),
    );

    // Make the list!
    require_once($sourcedir . '/Subs-List.php');
    createList($listOptions);

    $context['page_title'] = $txt['simplexbl'];
    $context['sub_template'] = 'leaderboard';
}

/**
 * Remove a member from the leaderboard and entire app
 */
function DeleteMember()
{
    global $sourcedir;

    checkSession('request');

    if (isset($_REQUEST['id']))
    {
        sxbl_delete_member((int) $_REQUEST['id']);
    }

    redirectexit('action=simplexbl;deleted=true');
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

?>