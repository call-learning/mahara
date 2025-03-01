<?php
/**
 * Share tab on groups.
 *
 * @package    mahara
 * @subpackage core
 * @author     Catalyst IT Limited <mahara@catalyst.net.nz>
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 *
 */

define('INTERNAL', 1);
require(dirname(dirname(__FILE__)) . '/init.php');
require_once(get_config('libroot') . 'view.php');
require_once(get_config('libroot') . 'group.php');
define('TITLE', get_string('share', 'view'));
define('SUBSECTIONHEADING', TITLE);
define('MENUITEM', 'engage/index');
define('MENUITEM_SUBPAGE', 'share');

define('GROUP', param_integer('group'));
$group = group_current_group();

if (group_deny_access($group, 'member')) {
  throw new AccessDeniedException();
}


$accesslists = View::get_accesslists(null, $group->id);

$smarty = smarty();
setpageicon($smarty, 'icon-share-alt');
$smarty->assign('heading', $group->name);
$smarty->assign('headingclass', 'page-header');
$smarty->assign('accesslists', $accesslists);
$smarty->display('view/share.tpl');
