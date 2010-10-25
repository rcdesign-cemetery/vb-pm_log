<?php
/*======================================================================*\
|| #################################################################### ||
|| # PM Log 3.0                                                       # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright © 2009 Dmitry Titov, Vitaly Puzrin.                    # ||
|| # All Rights Reserved.                                             # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| #################################################################### ||
\*======================================================================*/

// ############## SET PHP ENVIRONMENT #####################################
error_reporting(E_ALL & ~E_NOTICE);
@set_time_limit(0);

// #################### DEFINE IMPORTANT CONSTANTS #######################
if (!defined('THIS_SCRIPT'))
    define('THIS_SCRIPT', 'rcd_pm_log');

define('MOVE_FIRST', 10);
define('MOVE_LAST', 20);
define('MOVE_PREV', 30);
define('MOVE_NEXT', 40);

// ############## PRE-CACHE TEMPLATES AND DATA ############################
$phrasegroups = array(
    'style',
    'pm',
    'user',
    'fronthelp',
    'attachment_image',
    'posting',
);

$actiontemplates = array();


// ############## REQUIRE BACK-END ########################################
require_once('./global.php');
require_once(DIR . '/includes/adminfunctions.php');
require_once(DIR . '/includes/adminfunctions_template.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!(can_administer('adminviewpmlog') OR can_administer_pm_log()))
{
    print_cp_no_permission();
}

// ############## START MAIN SCRIPT #######################################

$usermenus = array();

// ############## LIST PM MESSAGES ########################################
if (empty($_REQUEST['do']))
{
    $_REQUEST['do'] = 'search';
}
if ($_REQUEST['do'] == 'search')
{
    print_cp_header($vbphrase['rcd_pm_log_acp_menu']);

?>

    <style type="text/css" id="vbulletin_css">
        .tborder
        {
            background: #D1D1E1;
            color: #000000;
            border: 1px solid #0B198C;
        }
        .vbmenu_popup
        {
            background: #FFFFFF;
            color: #000000;
            border: 1px solid #0B198C;
        }
        .vbmenu_option
        {
            background: #BBC7CE;
            color: #000000;
            font: 11px verdana, geneva, lucida, 'lucida grande', arial, helvetica, sans-serif;
            white-space: nowrap;
            cursor: pointer;
        }
        .vbmenu_option a:link, .vbmenu_option_alink
        {
            color: #22229C;
            text-decoration: none;
        }
        .vbmenu_option a:visited, .vbmenu_option_avisited
        {
            color: #22229C;
            text-decoration: none;
        }
        .vbmenu_option a:hover, .vbmenu_option a:active, .vbmenu_option_ahover
        {
            color: #FFFFFF;
            text-decoration: none;
        }
    </style>

    <script type="text/javascript">
        <!--
        var SESSIONURL = "<?php echo $vbulletin->session->vars['sessionurl'] ?>";
        var SECURITYTOKEN = "<?php echo $vbulletin->userinfo['securitytoken'] ?>";
        var IMGDIR_MISC = "<?php echo $vbulletin->options['bburl'] ?>/images/misc";
        var vb_disable_ajax = parseInt("0", 10);
        // -->
    </script>
    <script type="text/javascript" src="<?php echo $vbulletin->options['bburl'] ?>/clientscript/vbulletin_menu.js"></script>
    <script type="text/javascript" src="<?php echo $vbulletin->options['bburl'] ?>/clientscript/vbulletin_ajax_namesugg.js"></script>

<?php
    $vbulletin->input->clean_array_gpc('r', array(
        'firstlogid' => TYPE_INT,
        'startlogid' => TYPE_INT,
        'endlogid' => TYPE_INT,
        'username' => TYPE_STR,
        'userid' => TYPE_STR,
        'keywords' => TYPE_STR,
        'move' => TYPE_INT,
        'page_num' => TYPE_INT,
        'toral_count' => TYPE_INT,
    ));

    if (!$vbulletin->GPC_exists['move'] OR 0 == $vbulletin->GPC['move'])
    {
        $vbulletin->GPC['move'] = MOVE_NEXT;
    }
    $move = $vbulletin->GPC['move'];

    if ($vbulletin->GPC_exists['startlogid'] OR 0 < $vbulletin->GPC['startlogid'])
    {
        $startlogid = $vbulletin->GPC['startlogid'];
    }

    if ($vbulletin->GPC_exists['endlogid'] OR 0 < $vbulletin->GPC['endlogid'])
    {
        $endlogid = $vbulletin->GPC['endlogid'];
    }

    if ($vbulletin->GPC['perpage'] < 1)
    {
        $vbulletin->GPC['perpage'] = $vbulletin->options['rcd_pm_log_rows_per_page'];
    }
    $perpage = $vbulletin->GPC['perpage'];

    $search_keywords = trim($vbulletin->GPC['keywords']);

    if ($vbulletin->GPC_exists['userid'])
    {
        $userinfo = verify_id('user', $vbulletin->GPC['userid'], false, true);
        if (!$userinfo)
        {
            print_stop_message('invalidid', $vbphrase["$idname"], $vbulletin->options['contactuslink']);
        }
        $vbulletin->GPC['username'] = $userinfo['username'];
    }

    if ($vbulletin->GPC['username'])
    {
        $user_name = $vbulletin->GPC['username'];
    }

    if (!$vbulletin->GPC['total_count'])
    {
        $vbulletin->GPC['total_count'] = rcd_pm_get_count_total_count($user_name, $search_keywords);
    }

    $total_count = $vbulletin->GPC['total_count'];
    if ((!$endlogid AND MOVE_LAST == $move) OR
        (!$startlogid AND MOVE_PREV == $move) OR
        (!$startlogid AND MOVE_FIRST == $move))
    {
        print_stop_message('rcd_pm_log_not_found');
    }

    $sql_draft = 'SELECT
                        pm.logid, pm.fromuserid, pm.fromusername, pm.touserid, pm.tousername, pm.title, pm.dateline
                    FROM
                        ' . TABLE_PREFIX . 'rcd_log_pm AS pm';

    $order = 'ASC';

    $limit = ($perpage + 1);
    switch ($move)
    {
        case MOVE_LAST:
            $order = 'DESC';
            $limit = (int) fmod($total_count, $perpage);
            if (0 == $limit)
            {
                $limit = $perpage;
            }
            $vbulletin->GPC['page_num'] = ceil($total_count / $perpage);
            break;
        case MOVE_NEXT:
            if ($endlogid)
            {
                $conditions[] = 'pm.logid >= ' . $endlogid;
            }
            $vbulletin->GPC['page_num']++;
            break;
        case MOVE_PREV:
            $order = 'DESC';
            $conditions[] = 'pm.logid <' . $startlogid;
            $vbulletin->GPC['page_num']--;
            break;
        case MOVE_FIRST:
        default:
            $vbulletin->GPC['page_num'] = 1;
    }

    if (!empty($search_keywords))
    {
        $conditions[] = rcd_pm_get_kewords_condition($search_keywords);
    }
    $order = ' ORDER BY logid ' . $order;
    $limit = ' LIMIT ' . $limit;

    if (!empty($user_name))
    {
        $sql_draft .= ' WHERE ';
        $sql = '(' . $sql_draft .
            ' fromusername = \'' . $user_name . '\' ' .
            (!empty($conditions) ? ' AND ' . implode(' AND ', $conditions) : '') .
            $order . $limit .
            ') UNION (' .
            $sql_draft . ' tousername = \'' . $user_name . '\' ' .
            (!empty($conditions) ? ' AND ' . implode(' AND ', $conditions) : '') .
            $order . $limit . ')';
    }
    else
    {
        $sql = $sql_draft;
        if (!empty($conditions))
        {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= $order . $limit;
    }

    $res = $db->query_read($sql);

    $results = array();
    while ($row = $db->fetch_array($res))
    {
        $results[$row['logid']] = $row;
    }
    if (!count($results))
    {
        print_stop_message('rcd_pm_log_not_found');
    }

    ksort($results);
    $results = array_slice($results, 0, $perpage + 1);

    if (count($results) > $perpage)
    {
        $last_row = array_pop($results);
        $endlogid = $last_row['logid'];
        $nextpage = rcd_pm_construct_button(MOVE_NEXT);
        $lastpage = rcd_pm_construct_button(MOVE_LAST);
    }

    $startlogid = $results[0]['logid'];
    if (empty($vbulletin->GPC['firstlogid']))
    {
        $vbulletin->GPC['firstlogid'] = $startlogid;
    }
    if ($vbulletin->GPC['firstlogid'] < $startlogid)
    {
        $firstpage = rcd_pm_construct_button(MOVE_FIRST);
        $prevpage = rcd_pm_construct_button(MOVE_PREV);
    }

    print_form_header('rcd_pm_log', 'search', false, true, 'paging_helper');

    construct_hidden_code("page_num", $vbulletin->GPC['page_num']);
    construct_hidden_code("move", '');

    construct_hidden_code('firstlogid', $vbulletin->GPC['firstlogid']);
    if (isset($startlogid))
    {
        construct_hidden_code('startlogid', $startlogid);
    }
    if (isset($endlogid))
    {
        construct_hidden_code('endlogid', $endlogid);
    }
    if (isset($user_name))
    {
        construct_hidden_code('username', $user_name);
    }
    construct_hidden_code('total_count', $total_count);

    if (strlen($search_keywords))
    {
        construct_hidden_code("keywords", $search_keywords);
    }

    $from_num = ($vbulletin->GPC['page_num'] - 1) * $perpage + 1;

    $to_num = ($vbulletin->GPC['page_num'] - 1) * $perpage + $perpage;

    if ($counter !== null && $to_num > $counter)
        $to_num = $counter;

    $tablename =
        $vbphrase['private_messages']
        . ' (' . $from_num . '-' . ($to_num < $total_count ? $to_num : $total_count) . '/' . $total_count . ')';

    print_table_header($tablename, 4);

    // print table headers
    $header = array();
    $header[] = $vbphrase['rcd_pm_log_dump_from'];
    $header[] = $vbphrase['subject'];
    $header[] = $vbphrase['rcd_pm_log_dump_to'];
    $header[] = $vbphrase['date'];

    print_cells_row($header, true, false, -10);

    // print contents rows
    foreach ($results AS $pm)
    {
        $row = array();

        $row[] = user_name_cell($pm['fromusername'], $pm['fromuserid']);
        $row[] = "<a target=\"_blank\" href=\""
            . $vbulletin->options['bburl'] . "/misc.php?"
            . $vbulletin->session->vars['sessionurl']
            . "do=showpm&logid=" . $pm['logid'] . "\" >"
            . $pm['title'] . "</a>";
        $row[] = user_name_cell($pm['tousername'], $pm['touserid']);
        $row[] = vbdate($vbulletin->options['logdateformat'], $pm['dateline']);

        print_cells_row($row, false, false, -10);
    }

    print_table_footer(4, "$firstpage $prevpage &nbsp; $nextpage $lastpage");


// now print search form
    print_form_header('rcd_pm_log', 'search');
    print_table_header($vbphrase['search'], 2);

    print_input_row($vbphrase['username'], 'username', $user_name, false);
    print_input_row($vbphrase['keywords'], 'keywords', $search_keywords, false);

    print_submit_row($vbphrase['search'], '', 2);

    foreach ($usermenus AS $menu)
    {
        echo $menu;
    }
?>

    <script type="text/javascript">
        <!--
        // Main vBulletin Javascript Initialization
        vBulletin_init();
        //-->
    </script>

<?php
}

// ############## PRINT MESSAGE ###########################################
if ($_REQUEST['do'] == 'showpm')
{
    if ($vbulletin->options['storecssasfile'])
    {
        $vbcsspath = 'clientscript/vbulletin_css/style' . str_pad($style['styleid'], 5, '0', STR_PAD_LEFT) . $vbulletin->stylevars['textdirection']['string'][0] . '/';
        $head_insert = '<link rel="stylesheet" type="text/css" href="' . $vbcsspath . 'bbcode.css' . '?d=\' . $style[\'dateline\'] . \'" />';
    }
    else
    {
        // textdirection var added to prevent cache if admin modified language text_direction. See bug #32640
        $vbcsspath = 'css.php?styleid=' . $style['styleid'] . '&amp;langid=' . LANGUAGEID . '&amp;d=' . $style['dateline'] . '&amp;td=' . $vbulletin->stylevars['textdirection']['string'] . '&amp;sheet=';
        $head_insert = '<link rel="stylesheet" type="text/css" href="' . $vbcsspath . 'bbcode.css" />';
    }

    print_cp_header($vbphrase['rcd_pm_log_acp_menu'], '', $head_insert);

    // Hack. Fix for hardcoded paths in "print_cp_header" functions. Just remove ../ , because we are in upper folder
    $content = ob_get_clean();
    ob_start();
    echo str_replace('"../', '"', $content);


    $vbulletin->input->clean_array_gpc('r', array(
        'logid' => TYPE_UINT,
    ));

    $logid = $vbulletin->GPC['logid'] ? $vbulletin->GPC['logid'] : 0;

    if (!$logid)
    {
        print_stop_message('rcd_pm_log_not_found');
    }

    $sql = 'SELECT
                pm.*
            FROM
                ' . TABLE_PREFIX . 'rcd_log_pm AS pm
            WHERE
                pm.`logid` = ' . $logid . '
            LIMIT 1';

    $pm = $db->query_first($sql);
    if (empty($pm))
    {
        print_stop_message('rcd_pm_log_not_found');
    }


    // print pms list
    print_table_start();
    print_table_header($vbphrase['rcd_pm_log_view_message'], 2);

    // show linked username only for existing users
    $ipline = $vbphrase['rcd_pm_log_ip'] . ": <a target=\"_blank\" href=\"" . $admincpdir . "/usertools.php?" . $vbulletin->session->vars['sessionurl'] . "do=gethost&ip=" . $pm['fromuserip'] . "\">" . $pm['fromuserip'] . "</a>";
    $emailline = $vbphrase['email'] . ": " . $pm['fromuseremail'];

    if (verify_id('user', $pm['fromuserid'], false))
    {
        print_label_row($vbphrase['rcd_pm_log_dump_from'], "<a target=\"_blank\" href=\"" . $admincpdir . "/user.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&u=" . $pm['fromuserid'] . "\"><b>" . $pm['fromusername'] . "</b></a> (" . $emailline . ", " . $ipline . ")");
    }
    else
    {
        print_label_row($vbphrase['rcd_pm_log_dump_from'], "<b>" . $pm['fromusername'] . " (" . $emailline . ", " . $ipline . ")</b>");
    }

    // show linked username only for existing users
    $emailline = $vbphrase['email'] . ": " . $pm['touseremail'];
    if (verify_id('user', $pm['touserid'], false))
    {
        print_label_row($vbphrase['rcd_pm_log_dump_to'], "<a target=\"_blank\" href=\"" . $admincpdir . "/user.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&u=" . $pm['touserid'] . "\"><b>" . $pm['tousername'] . "</b></a> (" . $emailline . ")");
    }
    else
    {
        print_label_row($vbphrase['rcd_pm_log_dump_to'], "<b>" . $pm['tousername'] . " (" . $emailline . ")</b>");
    }


    print_label_row($vbphrase['rcd_pm_log_sent_date'], vbdate($vbulletin->options['logdateformat'], $pm['dateline']));
    print_label_row($vbphrase['subject'], $pm['title']);

    require_once(DIR . '/includes/class_bbcode.php');
    $bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());
    // force to not allow html and images

    print_description_row(html_entity_decode($bbcode_parser->parse($pm['message'], 'privatemessage')), false, 2);

    /*
      print_description_row( $pm['message'], true, 2 );
     */

    print_table_footer(2);
}

print_cp_footer();

// ############## SOME FUNCTIONS ##########################################

function user_name_cell($user_name, $user_id = 0)
{
    global $vbulletin, $usermenus, $vbphrase;

    static $users;
    if (empty($users) OR (is_array($users) AND !array_key_exists($user_name, $users)))
    {
        $users[$user_name] = verify_id('user', $user_id, false);
    }

    // show linked username only for existing users
    if (!$users[$user_name] OR 0 > $user_id)
    {
        return "&nbsp;<b>" . $user_name . "</b>";
    }
    $elid = rand() . '_' . rand() . '_' . $user_id;

    $out = "<span id=\"usermenu_uid_" . $elid . "\" class=\"vbmenu_control\">"
        . "<script type=\"text/javascript\">vbmenu_register(\"usermenu_uid_" . $elid . "\" ); </script>"
        . "</span>&nbsp;"
        . "<a target=\"_blank\" href=\"" . $vbulletin->options['bburl'] . "/member.php?" . $vbulletin->session->vars['sessionurl'] . "u=" . $user_id . "\"><b>" . $user_name . "</b></a>";

    $usermenus[$elid] =
        "<div class=\"vbmenu_popup\" id=\"usermenu_uid_" . $elid . "_menu\" style=\"display:none\">"
        . "<table cellpadding=\"4\" cellspacing=\"1\" border=\"0\">"
        . "<tr>"
        . "  <td class=\"vbmenu_option\"><a href=\"?" . $vbulletin->session->vars['sessionurl'] . "userid=" . urlencode($user_id) . "\">" . $vbphrase['private_messages'] . " " . $user_name . "</a></td>"
        . "</tr>"
        . "<tr>"
        . "  <td class=\"vbmenu_option\"><a target=\"_blank\" href=\"user.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&u=" . $user_id . "\">" . $vbphrase['edit_user_profile'] . "</a></td>"
        . "</tr>"
        . "</table>"
        . "</div>";

    return $out;
}

/* * ******************************************* */

function rcd_pm_get_count_total_count($user_name = '', $keywords = '')
{
    global $db;
    $sql_draft = 'SELECT
                        COUNT(pm.logid) AS count
                    FROM
                        ' . TABLE_PREFIX . 'rcd_log_pm AS pm
                    WHERE
                ';
    $keywords_condition = '';
    if ($keywords)
    {
        $keywords_condition = rcd_pm_get_kewords_condition($keywords);
    }
    if (!empty($user_name))
    {
        $sql = 'SELECT SUM(cr.count) AS count
                FROM((' .
            $sql_draft .
            ' fromusername = \'' . $user_name . '\' ' .
            ($keywords_condition ? ' AND ' . $keywords_condition : '') .
            ') UNION (' .
            $sql_draft . ' tousername = \'' . $user_name . '\' ' .
            ($keywords_condition ? ' AND ' . $keywords_condition : '') .
            ')) AS cr';
    }
    else
    {
        $sql = 'SELECT
                    COUNT(pm.logid) AS count
                FROM
                    ' . TABLE_PREFIX . 'rcd_log_pm AS pm';
        if ($keywords_condition)
        {
            $sql .= ' WHERE ' . $keywords_condition;
        }
    }
    $pm = $db->query_first($sql);

    return (int) $pm['count'];
}

function rcd_pm_get_kewords_condition($keywords)
{
    global $db;
    $condition = '';
    if (!empty($keywords))
    {
        $keywords = $db->escape_string($keywords);
        $condition = "(pm.`title` LIKE '%" . $keywords . "%'
                OR pm.`message` LIKE '%" . $keywords . "%')";
    }
    return $condition;
}

function rcd_pm_construct_button($type)
{
    global $vbphrase;
    switch ($type)
    {
        case MOVE_FIRST: $button_text = '&laquo; ' . $vbphrase['first_page'];
            break;
        case MOVE_PREV: $button_text = '&laquo; ' . $vbphrase['prev_page'];
            break;
        case MOVE_NEXT: $button_text = $vbphrase['next_page'] . ' &raquo;';
            break;
        case MOVE_LAST: $button_text = $vbphrase['last_page'] . ' &raquo;';
            break;
    }
    $str = '<input type="submit" class="button" value="' . $button_text . '" '
        . 'tabindex="1" onclick="'
        . 'document.forms[\'paging_helper\'].move.value = \'' . $type . '\';'
        . '" />';
    return $str;
}