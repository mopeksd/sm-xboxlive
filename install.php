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