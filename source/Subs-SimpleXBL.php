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

?>