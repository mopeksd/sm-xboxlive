<?php
/**********************************************************************************
* install.php                                                                     *
***********************************************************************************
* This file is a simplified installation script to modify an existing SMF         *
* database. It is used to add tables, rows or columns and also add variables to   *
* the smf_settings table. Simply follow the commented sections to find out how to *
* use this file.                                                                  *
**********************************************************************************/

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
{
   require_once(dirname(__FILE__) . '/SSI.php');
   db_extend('packages');
}
elseif(!defined('SMF'))
{
   die('<strong>Error:</strong> Cannot install - please verify you put this file in the same place as SMF\'s SSI.php.');
}

/**
 * Pre-Install Check
 */
pre_install_check();

/**
 * Install the integration hooks
 */
if (!empty($smcFunc['db_query']))
{
    $hooks = array(
        'integrate_preload_file' => 'SimpleXBL.php',
        'integrate_load_theme' => 'sxblLoadTheme',
        'integrate_admin_areas' => 'sxblAdminAreas',
        'integrate_menu_buttons' => 'sxblMenuButtons',
        'integrate_actions' => 'sxblActions',
    );

    foreach ($hooks as $key => $val)
    {
        add_integration_function($key, $val, true);
    }
}

function pre_install_check()
{
    global $modSettings, $txt;

    if (version_compare(PHP_VERSION, '5.2.0', '<'))
    {
        fatal_error('<strong>PHP 5.2 or geater is required to install SimpleXBL.  Please advise your host that PHP4 is no longer maintained and ask that they upgrade you to PHP5.</strong><br />');
    }

    $char_set = empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set'];

    if ($char_set != 'ISO-8859-1' && $char_set != 'UTF-8' && !function_exists('iconv') && !function_exists('mb_convert_encoding') && !function_exists('unicode_decode'))
    {
        fatal_error('<strong>You are currently using the ' . $char_set . ' character set and your server does not have functions available to convert to UTF-8.  In order to use this mod, you will either need to convert your board to UTF-8 or ask your host to recompile PHP with the iconv or Multibyte String extensions.</strong>');
    }
}

/**
 * Set up the default $modSettings values
 */
$sxbl_settings = array(
    'xbl_enable' => '1',
    'xbl_items_page' => '20',
    'xbl_required_posts' => '1',
    'xbl_user_timeout' => '30',
    'xbl_show_unranked' => '1',
    'xbl_stat_limit' => '5',
);
updateSettings($sxbl_settings);

/**
 * Create the xbox_leaders table and populate it with defaults
 */
$xbox_leaders_columns = array(
    array('name' => 'id_member', 'auto' => false, 'default' => 0, 'type' => 'int', 'size' => 11, 'null' => false),
    array('name' => 'account_status', 'auto' => false, 'default' => 'Silver', 'type' => 'varchar', 'size' => 30, 'null' => false),
    array('name' => 'gender', 'auto' => false, 'default' => '', 'type' => 'varchar', 'size' => 30, 'null' => false),
    array('name' => 'is_cheater', 'auto' => false, 'default' => 0, 'type' => 'int', 'size' => 11, 'null' => false),
    array('name' => 'link', 'auto' => false, 'default' => '', 'type' => 'varchar', 'size' => 255, 'null' => false),
    array('name' => 'gamertag', 'auto' => false, 'default' => '', 'type' => 'varchar', 'size' => 30, 'null' => false),
    array('name' => 'avatar', 'auto' => false, 'default' => '', 'type' => 'varchar', 'size' => 255, 'null' => false),
    array('name' => 'reputation', 'auto' => false, 'default' => 0, 'type' => 'tinyint', 'size' => 2, 'null' => false),
    array('name' => 'gamerscore', 'auto' => false, 'default' => 0, 'type' => 'int', 'size' => 11, 'null' => false),
    array('name' => 'location', 'auto' => false, 'default' => '', 'type' => 'varchar', 'size' => 255, 'null' => false),
    array('name' => 'motto', 'auto' => false, 'default' => '', 'type' => 'varchar', 'size' => 255, 'null' => false),
    array('name' => 'name', 'auto' => false, 'default' => '', 'type' => 'varchar', 'size' => 255, 'null' => false),
    array('name' => 'bio', 'auto' => false, 'default' => '', 'type' => 'text', 'size' => '', 'null' => false),
    array('name' => 'last_played', 'auto' => false, 'default' => '', 'type' => 'text', 'size' => '', 'null' => false),
    array('name' => 'updated', 'auto' => false, 'default' => 0, 'type' => 'int', 'size' => 11, 'null' => false)
);

// Set the table indexes
$xbox_leaders_indexes = array(array('columns' => array('id_member'), 'type' => 'primary'));

// Create the table
$smcFunc['db_create_table']('{db_prefix}xbox_leaders', $xbox_leaders_columns, $xbox_leaders_indexes, array(), 'update');

/**
 * Create the xbox_games table and populate it with defaults
 */
// If it already exists, drop it
$smcFunc['db_query']('', 'DROP TABLE IF EXISTS {db_prefix}xbox_games', array());

// Create the tables, and set their default values
$xbox_games_columns = array(
    array('name' => 'id_member', 'auto' => false, 'default' => 0, 'type' => 'int', 'size' => 11, 'null' => false),
    array('name' => 'position', 'auto' => false, 'default' => 0, 'type' => 'int', 'size' => 11, 'null' => false),
    array('name' => 'title', 'auto' => false, 'default' => '', 'type' => 'varchar', 'size' => 255, 'null' => false),
    array('name' => 'earned_gamerscore', 'auto' => false, 'default' => 0, 'type' => 'int', 'size' => 11, 'null' => false),
    array('name' => 'available_gamerscore', 'auto' => false, 'default' => 0, 'type' => 'int', 'size' => 11, 'null' => false),
    array('name' => 'earned_achievements', 'auto' => false, 'default' => 0, 'type' => 'int', 'size' => 11, 'null' => false),
    array('name' => 'available_achievements', 'auto' => false, 'default' => 0, 'type' => 'int', 'size' => 11, 'null' => false),
    array('name' => 'percentage_complete', 'auto' => false, 'default' => 0, 'type' => 'int', 'size' => 11, 'null' => false),
    array('name' => 'link', 'auto' => false, 'default' => '', 'type' => 'varchar', 'size' => 255, 'null' => false),
    array('name' => 'image', 'auto' => false, 'default' => '', 'type' => 'varchar', 'size' => 255, 'null' => false),
    array('name' => 'updated', 'auto' => false, 'default' => 0, 'type' => 'int', 'size' => 11, 'null' => false)
);

// Set the table indexes
$xbox_games_indexes[] = array('columns' => array('id_member', 'title'), 'type' => 'primary');

// Create the table
$smcFunc['db_create_table']('{db_prefix}xbox_games', $xbox_games_columns, $xbox_games_indexes, array(), 'update');

/**
 * Create the xbox_games_list table and populate it with defaults
 */
// If the table exists, drop it
$smcFunc['db_query']('', 'DROP TABLE IF EXISTS {db_prefix}xbox_games_list', array());

// Create the tables and set the default values
$xbox_games_list_columns = array(
    array('name' => 'tid', 'auto' => false, 'default' => 0, 'type' => 'int', 'size' => 10, 'null' => false),
    array('name' => 'title', 'auto' => false, 'default' => '', 'type' => 'varchar', 'size' => 255, 'null' => false),
    array('name' => 'image', 'auto' => false, 'default' => '', 'type' => 'varchar', 'size' => 255, 'null' => false)
);

// Set the table indexes
$xbox_games_list_indexes[] = array('columns' => array('tid'), 'type' => 'primary');

// Create the table
$smcFunc['db_create_table']('{db_prefix}xbox_games_list', $xbox_games_list_columns, $xbox_games_list_indexes, array(), 'update');

/**
 * Create the scheduled task to update all gamers
 */
$smcFunc['db_insert']('replace',
    '{db_prefix}scheduled_tasks',
    array('next_time' => 'int', 'time_offset' => 'int', 'time_regularity' => 'int', 'time_unit' => 'string', 'disabled' => 'int', 'task' => 'string'),
    array(time() + 15, 0, 15, 'm', 0, 'update_gamertags'),
    array('id_task')
);

/**
 * Add a row to the members table so they can add their gamertag
 */
$smcFunc['db_add_column']('{db_prefix}members', array('name' => 'gamertag', 'type' => 'varchar', 'size' => 30, 'null' => false, 'default' => ''));


/**
 * Are we done? If so, exit out.
 */
if (SMF == 'SSI')
{
   echo 'Database changes are complete!';
}

?>