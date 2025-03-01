<?php
/**
 *
 * @package    mahara
 * @subpackage core
 * @author     Catalyst IT Limited <mahara@catalyst.net.nz>
 * @license    https://www.gnu.org/licenses/gpl-3.0.html GNU GPL version 3 or later
 * @copyright  For copyright information on Mahara, please see the README file distributed with this software.
 *
 */

define('INTERNAL', 1);
define('INSTITUTIONALADMIN', 1);
define('MENUITEM', 'manageinstitutions/share');

require(dirname(dirname(__FILE__)) . '/init.php');
require_once(get_config('libroot') . 'view.php');
require_once(get_config('libroot') . 'institution.php');

$institution = param_alphanum('institution', false);

if ($institution == 'mahara') {
    redirect('/admin/site/shareviews.php');
}

$s = institution_selector_for_page($institution, get_config('wwwroot') . 'view/institutionshare.php');

$institution = $s['institution'];

define('TITLE', get_string('share', 'view'));

if ($institution === false) {
    $smarty = smarty();
    setpageicon($smarty, 'icon-share-alt');
    $smarty->display('admin/users/noinstitutions.tpl');
    exit;
}

$accesslists = View::get_accesslists(null, null, $institution);

$smarty = smarty();
setpageicon($smarty, 'icon-share-alt');

$smarty->assign('institutionselector', $s['institutionselector']);
$smarty->assign('INLINEJAVASCRIPT', $s['institutionselectorjs']);
$smarty->assign('accesslists', $accesslists);
$smarty->assign('institution', $institution);

$smarty->display('view/share.tpl');
