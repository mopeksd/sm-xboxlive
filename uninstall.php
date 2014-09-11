<?php
/**********************************************************************************
* uninstall.php                                                                   *
***********************************************************************************
* This file is a simplified uninstallation script to modify an existing SMF       *
* database. It is used to remove tables, rows or columns and also add variables   *
* to the smf_settings table. Simply follow the commented sections to find out how *
* to use this file.                                                               *
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
 * Remove the xbox_leaders, xbox_games, and xbox_games_list tables
 */
$smcFunc['db_query']('', 'DROP TABLE IF EXISTS {db_prefix}xbox_leaders', array());
$smcFunc['db_query']('', 'DROP TABLE IF EXISTS {db_prefix}xbox_games', array());
$smcFunc['db_query']('', 'DROP TABLE IF EXISTS {db_prefix}xbox_games_list', array());

/**
 * Remove any scheduled tasks that were created
 */
//!!!

/**
 * Remove any extra columns we may have created in other tables
 */
//!!!

/**
 * Are we done? If so, exit out.
 */
if (SMF == 'SSI')
{
   echo 'Database changes are complete!';
}

?>