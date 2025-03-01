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
define('SECTION_PLUGINTYPE', 'core');
define('SECTION_PLUGINNAME', 'view');
define('SECTION_PAGE', 'urls');

require(dirname(dirname(__FILE__)) . '/init.php');
require_once('pieforms/pieform/elements/calendar.php');
require_once(get_config('libroot') . 'view.php');
require_once(get_config('libroot') . 'collection.php');
require_once(get_config('libroot') . 'antispam.php');

$view = new View(param_integer('id'));
$collection = $view->get_collection();
$title = $collection ? $collection->get('name') : $view->get('title');

define('TITLE', get_string('secreturls', 'view') . ': ' . $title);
define('SUBSECTIONHEADING', get_string('share'));
$group = $view->get('group');
$institution = $view->get('institution');
View::set_nav($group, $institution, true);

if (!$USER->can_edit_view($view)) {
    throw new AccessDeniedException();
}
if ($group && !group_within_edit_window($group)) {
    throw new AccessDeniedException();
}

if ($group) {
    $groupobj = get_group_by_id($group);
    if (group_deny_access($groupobj, 'member')) {
        throw new AccessDeniedException();
    }
}

$newform = array(
    'name'     => 'newurl',
    'autofocus'     => false,
    'elements' => array(
        'submit' => array(
            'type'        => 'button',
            'usebuttontag' => true,
            'class'       => 'btn-secondary',
            'elementtitle' => get_string('generatesecreturl', 'view', hsc($title)),
            'value'       =>  '<span class="icon icon-plus left" role="presentation" aria-hidden="true"></span> ' .get_string('newsecreturl', 'view'),
        ),
    ),
);

$editurls = array();

$allowcomments = $view->get('allowcomments');

$records = get_records_select_array(
    'view_access',
    'view = ? AND visible = 1 AND NOT token IS NULL',
    array($view->get('id')),
    'token'
);

if (!$records) {
    $records = array();
}

$tokens = array();
$js = '';

for ($i = 0; $i < count($records); $i++) {
    $r =& $records[$i];
    $tokens[$r->token] = $r->token;
    $elements = array(
        'token'     => array(
            'type'         => 'hidden',
            'value'        => $r->token,
        ),
        'startdate' => array(
            'type'         => 'calendar',
            'title'        => get_string('From') . ':',
            'class'        => 'form-inline',
            'defaultvalue' => !empty($r->startdate) ? strtotime($r->startdate) : null,
            'caloptions'   => array(
                'showsTime'      => true,
            ),
        ),
        'stopdate'  => array(
            'type'         => 'calendar',
            'title'        => get_string('To') . ':',
            'class'        => 'form-inline',
            'defaultvalue' => !empty($r->stopdate) ? strtotime($r->stopdate) : null,
            'caloptions'   => array(
                'showsTime'      => true,
            ),
        ),
    );
    if (!$allowcomments) {
        $elements['allowcomments'] = array(
            'type'         => 'switchbox',
            'title'        => get_string('allowcomments', 'artefact.comment'),
            'defaultvalue' => $r->allowcomments,
        );
        $elements['approvecomments'] = array(
            'type'         => 'switchbox',
            'title'        => get_string('moderatecomments', 'artefact.comment'),
            'defaultvalue' => $r->approvecomments,
        );
    }
    $elements['submit'] = array(
        'type'  => 'submit',
        'class' => 'btn-primary',
        'value' => get_string('save'),
    );
    $editurls[$i] = array(
        'id'         => $i,
        'url'        => get_config('wwwroot') . 'view/view.php?t=' . $r->token,
        'editform'   => pieform(array(
            'name'             => 'editurl_' . $i,
            'successcallback'  => 'editurl_submit',
            'validatecallback' => 'editurl_validate',
            'jsform'           => true,
            'elements'         => $elements,
        )),
        'deleteform' => pieform(array(
            'name'             => 'deleteurl_' . $i,
            'successcallback'  => 'deleteurl_submit',
            'renderer'         => 'div',
            'class'            => 'form-as-button btn-group form-inline float-start last',
            'renderelementsonly' => true,
            'elements'         => array(
                'token'  => array(
                    'type'         => 'hidden',
                    'value'        => $r->token,
                ),
                'submit' => array(
                    'type'         => 'button',
                    'usebuttontag' => true,
                    'class'        => 'btn-secondary btn-sm',
                    'elementtitle' => get_string('delete'),
                    'confirm'      => get_string('reallydeletesecreturl', 'view'),
                    'value'        => '<span class="icon icon-trash-alt text-danger" role="presentation" aria-hidden="true"></span><span class="visually-hidden">' . get_string('delete') . '</span>',
                ),
            ),
        )),
    );
}

// Only add the call if there is any zclip setup to be done.
$count = count($records);
if ($count) {
    $js = <<<EOF
jQuery(function($) {
    for (i = 0; i < {$count}; i++) {
        var element = document.getElementById("copytoclipboard-" + i);
        try {
            var client = new ClipboardJS(element);
            client.on("error", function(e) {
                var element = document.getElementById("copytoclipboard-" + e.client.id);
                $(element).hide();
            });
        }
        catch(err) {
            $(element).hide();
        }
    }
});

EOF;
}

function editurl_validate(Pieform $form, $values) {
    if (empty($values['startdate'])) {
        $values['startdate'] = null;
    }
    if (empty($values['stopdate'])) {
        $values['stopdate'] = null;
    }
    if ($values['stopdate'] && time() > $values['stopdate']) {
        $form->set_error('stopdate', get_string('stopdatecannotbeinpast1', 'view'));
    }
    if ($values['startdate'] && $values['stopdate'] && $values['startdate'] > $values['stopdate']) {
        $form->set_error('startdate', get_string('startdatemustbebeforestopdate', 'view'));
    }
}

function editurl_submit(Pieform $form, $values) {
    global $tokens, $view, $collection, $SESSION;

    $viewid = $view->get('id');

    if ($collection) {
        $viewids = get_column('collection_view', 'view', 'collection', $collection->get('id'));
    }
    else {
        $viewids = array($viewid);
    }

    $access = (object) array(
        'token'     => $values['token'],
        'startdate' => db_format_timestamp($values['startdate']),
        'stopdate'  => db_format_timestamp($values['stopdate']),
    );
    if (!$view->get('allowcomments')) {
        if ($access->allowcomments = (int) $values['allowcomments']) {
            $access->approvecomments = (int) $values['approvecomments'];
        }
    }

    $whereobject = (object) array('token' => $values['token']);

    if (isset($tokens[$values['token']])) {
        foreach ($viewids as $id) {
            $access->view = $id;
            $whereobject->view = $id;
            update_record('view_access', $access, $whereobject);
        }
        $message = get_string('secreturlupdated', 'view');
        $form->reply(PIEFORM_OK, $message);
    }

    $form->reply(PIEFORM_ERR, get_string('formerror'));
}

function deleteurl_submit(Pieform $form, $values) {
    global $tokens, $view, $collection, $SESSION;

    $viewid = $view->get('id');

    if ($collection) {
        $viewids = get_column('collection_view', 'view', 'collection', $collection->get('id'));
    }
    else {
        $viewids = array($viewid);
    }

    if (isset($tokens[$values['token']])) {
        $select = 'token = ? AND view IN (' . join(',', $viewids) . ')';
        delete_records_select('view_access', $select, array($values['token']));
        $SESSION->add_ok_msg(get_string('secreturldeleted', 'view'));
    }

    redirect('/view/urls.php?id=' . $viewid);
}

function newurl_submit(Pieform $form, $values) {
    global $view, $collection;

    $viewid = $view->get('id');

    if ($collection) {
        $collection->new_token();
        $viewids = $collection->get_viewids();
        $viewid = reset($viewids);
    }
    else {
        View::new_token($viewid);
    }

    redirect('/view/urls.php?id=' . $viewid);
}

// Determine whether
$allownew = get_config('allowpublicviews') // Public view turned off sitewide
            && (!$view->get('owner') || $USER->institution_allows_public_views()); // The page belongs to a user in an institution without public views

// If the user would be allowed to create new views, check whether they should be prohibited because they're on probation
if ($allownew) {
    $onprobation = get_config('allowpublicviews') && is_probationary_user();
    $allownew = !$onprobation;
}
else {
    $onprobation = false;
}
$newform = $allownew ? pieform($newform) : null;

$js .= <<<EOF
jQuery(function($) {
    $('.url-open-editform').on("click", function(e) {
        e.preventDefault();
        $('#' + this.id).toggleClass('open');
        $('#' + this.id).toggleClass('closed');
        $('#' + this.id + '-form').toggleClass('js-hidden');
    });
});
EOF;

$smarty = smarty(
    array('js/clipboard/clipboard.min.js'),
    array(),
    array(),
    array('sidebars' => true)
);
setpageicon($smarty, 'icon-share-alt');
$smarty->assign('INLINEJAVASCRIPT', $js);
$smarty->assign('editurls', $editurls);
$smarty->assign('allownew', $allownew);
$smarty->assign('onprobation', $onprobation);
$smarty->assign('newform', $newform);
$smarty->display('view/urls.tpl');
