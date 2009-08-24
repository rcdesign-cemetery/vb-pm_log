<?php
/*======================================================================*\
|| #################################################################### ||
|| # PM Log 2.2                                                       # ||
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
require_once(DIR . '/includes/class_bbcode.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!(can_administer('adminviewpmlog') OR can_administer_pm_log()))
{
  print_cp_no_permission();
}

// ############## START MAIN SCRIPT #######################################
$action = $_GET['do'];
$do = $_REQUEST['do'];
$id = $_GET['id'];

$this_script = 'rcd_pm_log';
$rcd_pm_log_ver = 2.1;

$usermenus = array();

// ############## SEARCH PARAMETERS #######################################

$context_options = array(
  'user' => $vbphrase['user'],
  'text' => $vbphrase['text'],
);

print_cp_header( $vbphrase['rcd_pm_log'] );

// ############## LIST PM MESSAGES ########################################
if (empty($do) || $do == 'search')
{
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
    'perpage'        => TYPE_INT,
    'pagenumber'     => TYPE_INT,
    'nextlimit'      => TYPE_INT,
    'prevlimit'      => TYPE_INT,
    'search_context' => TYPE_STR,
    'keywords'       => TYPE_STR,
    'page'           => TYPE_STR,
  ));

  if (!in_array($vbulletin->GPC['page'], array('next', 'prev', 'first', 'last')))
    $vbulletin->GPC['page'] = 'first';

  if ($vbulletin->GPC['perpage'] < 1)
    $vbulletin->GPC['perpage'] = $vbulletin->options['rcd_pm_log_rows_per_page'];

  if ($vbulletin->GPC['pagenumber'] < 1)
    $vbulletin->GPC['pagenumber'] = 1;

  $search_context  = $vbulletin->GPC['search_context'];
  $search_keywords = $vbulletin->GPC['keywords'];

  if ($vbulletin->GPC['search_context'] == 'userid')
  {
    $search_context  = 'user';
    $search_keywords = rcd_pm_get_name_by_uid($search_keywords);
  }


  $counter = null;

  if ($vbulletin->GPC['search_context'] != 'text')
  {
    $counter = rcd_pm_log_get(
      $vbulletin->GPC['perpage'],
      $vbulletin->GPC['pagenumber'],
      true
    );

    $totalpages = ceil($counter / $vbulletin->GPC['perpage']);

    $pms = $counter
      ? rcd_pm_log_get(
          $vbulletin->GPC['perpage'],
          $vbulletin->GPC['pagenumber'],
          false,
          $counter
        )
      : array();
  }
  else
  {
    $pms =
      rcd_pm_log_get(
        $vbulletin->GPC['perpage'],
        $vbulletin->GPC['pagenumber']
      );
  }

  //if (empty($pms)) print_stop_message($vbphrase['empty_folder']);

  // check for existing next page
  $next_page_exists = false;
  $next_page_limit  = 0;
  $prev_page_limit  = 0;

  if (!empty($pms) && $pms[count($pms) -1 ]['markid'] > 0)
  {
    $next_page_exists = true;
    array_pop($pms);
  }

  $next_page_limit = $pms[count($pms) -1 ]['logid'];
  $prev_page_limit = $pms[0]['logid'];

  if ($vbulletin->GPC['search_context'])
  {
    if ($counter === null || $counter > $vbulletin->GPC['perpage'])
    {
      if (   ($vbulletin->GPC['page'] == 'prev' && $next_page_exists)
          || (in_array($vbulletin->GPC['page'], array('next', 'last'))))
      {
        $firstpage =
            '<input type="submit" class="button" value="&laquo; ' . $vbphrase['first_page'] . '" '
          . 'tabindex="1" onclick="'
          . 'document.forms[\'paging_helper\'].page.value = \'first\';'
          . '" />';

        $prevpage =
            '<input type="submit" class="button" value="&laquo; ' . $vbphrase['prev_page'] . '" '
          . 'tabindex="1" onclick="'
          . 'document.forms[\'paging_helper\'].page.value = \'prev\';'
          . '" />';
      }

      if (   ($vbulletin->GPC['page'] == ''     && $next_page_exists)
          || ($vbulletin->GPC['page'] == 'next' && $next_page_exists)
          || (in_array($vbulletin->GPC['page'], array('prev', 'first'))))
      {
        $nextpage =
            '<input type="submit" class="button" value="' . $vbphrase['next_page'] . ' &raquo;" '
          . 'tabindex="1" onclick="'
          . 'document.forms[\'paging_helper\'].page.value = \'next\';'
          . '" />';

        $lastpage =
            '<input type="submit" class="button" value="' . $vbphrase['last_page'] . ' &raquo;" '
          . 'tabindex="1" onclick="'
          . 'document.forms[\'paging_helper\'].page.value = \'last\';'
          . '" />';
      }
    }
  }
  else
  {
    if ($counter && $vbulletin->GPC['pagenumber'] != 1)
    {
      $prv = $vbulletin->GPC['pagenumber'] - 1;

      $firstpage =
          '<input type="submit" class="button" value="&laquo; ' . $vbphrase['first_page'] . '" '
        . 'tabindex="1" onclick="'
        . 'document.forms[\'paging_helper\'].pagenumber.value = \'1\';'
        . '" />';

      $prevpage =
          '<input type="submit" class="button" value="&laquo; ' . $vbphrase['prev_page'] . '" '
        . 'tabindex="1" onclick="'
        . 'document.forms[\'paging_helper\'].pagenumber.value = \'' . $prv . '\';'
        . '" />';
    }

    if ($counter && $vbulletin->GPC['pagenumber'] != $totalpages)
    {
      $nxt = $vbulletin->GPC['pagenumber'] + 1;

      $nextpage =
          '<input type="submit" class="button" value="' . $vbphrase['next_page'] . ' &raquo;" '
        . 'tabindex="1" onclick="'
        . 'document.forms[\'paging_helper\'].pagenumber.value = \'' . $nxt . '\';'
        . '" />';

      $lastpage =
          '<input type="submit" class="button" value="' . $vbphrase['last_page'] . ' &raquo;" '
        . 'tabindex="1" onclick="'
        . 'document.forms[\'paging_helper\'].pagenumber.value = \'' . $totalpages . '\';'
        . '" />';
    }
  }

  // paging helper form
  // print pms list
  print_form_header( 'rcd_pm_log', 'search', false, true, 'paging_helper' );

  construct_hidden_code( "pagenumber"     , $vbulletin->GPC['pagenumber'] );
  construct_hidden_code( "perpage"        , $vbulletin->GPC['perpage']    );
  construct_hidden_code( "page"           , $vbulletin->GPC['page']       );
  construct_hidden_code( "nextlimit"      , $next_page_limit              );
  construct_hidden_code( "prevlimit"      , $prev_page_limit              );

  if (strlen($search_context))
  {
    construct_hidden_code( "search_context" , $search_context               );
    construct_hidden_code( "keywords"       , $search_keywords, false       );
  }

  $from_num =
    $counter
    ? (($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->GPC['perpage'] + 1)
    : 0;

  $to_num   =
    ($vbulletin->GPC['pagenumber'] - 1) * $vbulletin->GPC['perpage']
    + $vbulletin->GPC['perpage'];

  if ($counter !== null && $to_num > $counter) $to_num = $counter;

  $tablename =
    $vbphrase['private_messages']
    . ($counter !== null ? " (" . $from_num . "-" . $to_num . "/" . $counter . ")" : "");

  print_table_header($tablename, 4);

  // print table headers
  $header = array();
  $header[] = $vbphrase['dump_from'];
  $header[] = $vbphrase['subject'];
  $header[] = $vbphrase['dump_to'];
  $header[] = $vbphrase['date'];

  print_cells_row($header, true, false, -10);

  // print contents rows
  foreach ($pms AS $pm)
  {
    $row = array();

    $row[] = user_name_cell($pm, 'from');
    $row[] = "<a target=\"_blank\" href=\""
             . $vbulletin->options['bburl'] . "/misc.php?"
             . $vbulletin->session->vars['sessionurl']
             . "do=showpm&logid=" . $pm['logid'] . "\">"
             . $pm['title'] . "</a>";
    $row[] = user_name_cell($pm, 'to');
    $row[] = vbdate($vbulletin->options['logdateformat'], $pm['dateline']);

    print_cells_row($row, false, false, -10);
  }

  print_table_footer(4, "$firstpage $prevpage &nbsp; $nextpage $lastpage");


  // now print search form
  print_form_header('rcd_pm_log', 'search');
  print_table_header($vbphrase['search'], 2);

  print_radio_row(
      $vbphrase['search_context'],
      'search_context',
      $context_options,
      (($search_context == 'user' OR $search_context == 'text')
        ? $search_context
        : 'text'
      ),
      'smallfont'
    );


  print_input_row($vbphrase['keywords'], 'keywords', $search_keywords, false);

  print_submit_row($vbphrase['search'], '', 2);

  foreach ($usermenus AS $menu) { echo $menu; }
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
if ($do == 'showpm')
{
  $vbulletin->input->clean_array_gpc( 'r', array(
    'logid' => TYPE_UINT,
  ) );

  $logid = $vbulletin->GPC['logid'] ? $vbulletin->GPC['logid'] : 0;

  if ( !$logid ) { print_stop_message( $vbphrase['empty_folder'] ); }

  $pm = rcd_pm_log_get_message( $logid );

  if ( empty( $pm ) ) { print_stop_message( $vbphrase['empty_folder'] ); }

  // print pms list
  print_table_start();
  print_table_header( $vbphrase['view_message'], 2 );

  // show linked username only for existing users
  $ipline = $vbphrase['ip'] . ": <a target=\"_blank\" href=\"" . $admincpdir . "/usertools.php?" . $vbulletin->session->vars['sessionurl'] . "do=gethost&ip=" . $pm['fromuserip'] . "\">" . $pm['fromuserip'] . "</a>";
  $emailline = $vbphrase['email'] . ": " . $pm['fromuseremail'];

  if ( $pm['fromuserid_check'] == $pm['fromuserid'] ) {
    print_label_row( $vbphrase['dump_from'], "<a target=\"_blank\" href=\"" . $admincpdir . "/user.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&u=" . $pm['fromuserid'] . "\"><b>" . $pm['fromusername'] . "</b></a> (" . $emailline . ", " . $ipline . ")" );
  } else {
    print_label_row( $vbphrase['dump_from'], "<b>" . $pm['fromusername'] . " (" . $emailline . ", " . $ipline . ")</b>" );
  }

  // show linked username only for existing users
  $emailline = $vbphrase['email'] . ": " . $pm['touseremail'];
  if ( $pm['touserid_check'] == $pm['touserid'] ) {
    print_label_row( $vbphrase['dump_to'], "<a target=\"_blank\" href=\"" . $admincpdir . "/user.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&u=" . $pm['touserid'] . "\"><b>" . $pm['tousername'] . "</b></a> (" . $emailline . ")" );
  } else {
    print_label_row( $vbphrase['dump_to'], "<b>" . $pm['tousername'] . " (" . $emailline . ")</b>" );
  }


  print_label_row( $vbphrase['sent_date'], vbdate( $vbulletin->options['logdateformat'], $pm['dateline'] ) );
  print_label_row( $vbphrase['subject'], $pm['title'] );


  $bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());

  // force to not allow html and images
  $vbulletin->options['privallowhtml']        = false;
  $vbulletin->options['privallowbbimagecode'] = false;

  print_description_row(html_entity_decode($bbcode_parser->parse($pm['message'], 'privatemessage')), false, 2);

/*
  print_description_row( $pm['message'], true, 2 );
*/

  print_table_footer( 2 );
}

print_cp_footer();

// ############## SOME FUNCTIONS ##########################################

function user_name_cell ($pm, $term = 'from')
{
  global $vbulletin, $usermenus, $vbphrase;

  $out = '';

  $usergroupid = $pm[$term.'usergroupid'];
  $username    = stripslashes($pm[$term.'opentag']) . $pm[$term.'username'] . stripslashes($pm[$term.'closetag']);

  $elid = rand() . '_' . rand() . '_';

  if ( $pm[$term.'userid'] > 0 )
  {
    $out .= "<span id=\"usermenu_uid_" . $elid . $pm[$term.'userid'] . "\" class=\"vbmenu_control\">"
         .  "<script type=\"text/javascript\">vbmenu_register(\"usermenu_uid_" . $elid . $pm[$term.'userid'] . "\" ); </script>"
         .  "</span>&nbsp;";
  }

  // show linked username only for existing users
  if ( $pm[$term.'userid'] > 0 && $pm[$term.'userid_check'] == $pm[$term.'userid'] ) {
    $out .= "<a target=\"_blank\" href=\"" . $vbulletin->options['bburl'] . "/member.php?" . $vbulletin->session->vars['sessionurl'] . "u=" . $pm[$term.'userid'] . "\"><b>" . $username . "</b></a>";
  } else {
    $out .= "<b>" . $username . "</b>";
  }

  if ( $pm[$term.'userid'] > 0 )
  {
    $usermenus[$elid.$pm[$term.'userid']] =
        "<div class=\"vbmenu_popup\" id=\"usermenu_uid_" . $elid . $pm[$term.'userid'] . "_menu\" style=\"display:none\">"
      . "<table cellpadding=\"4\" cellspacing=\"1\" border=\"0\">"
      . "<tr>"
      . "  <td class=\"vbmenu_option\"><a href=\"?" . $vbulletin->session->vars['sessionurl'] . "search_context=userid&keywords=" . urlencode( $pm[$term.'userid'] ) . "\">" . $vbphrase['private_messages'] . " " . $pm[$term.'username'] . "</a></td>"
      . "</tr>";

    if ($pm[$term.'userid_check'] == $pm[$term.'userid'])
    {
      $usermenus[$elid.$pm[$term.'userid']] .=
          "<tr>"
        . "  <td class=\"vbmenu_option\"><a target=\"_blank\" href=\"user.php?" . $vbulletin->session->vars['sessionurl'] . "do=edit&u="   . $pm[$term.'userid'] . "\">" . $vbphrase['edit_user_profile'] . "</a></td>"
        . "</tr>";
    }

    $usermenus[$elid.$pm[$term.'userid']] .=
        "</table>"
      . "</div>";
  }

  return $out;
}


function rcd_pm_log_get ($rows, $page, $count = false, $counter = 0)
{
  global $db;
  global $vbulletin;

  $res    = null;
  $where  = null;
  $where1 = null;
  $where2 = null;
  $limit  = "";
  $order  = "";
  $plusrows = 0;

  ($hook = vBulletinHook::fetch_hook('pmlog_search_start')) ? eval($hook) : false;

  if ($where === null)
  {
    $where = " WHERE 1 ";

    // are there any search terms?
    if ($vbulletin->GPC['search_context'])
    {
      switch ($vbulletin->GPC['search_context'])
      {
        case "text":
          /*
          $where1 = " MATCH (PM.`title`  ) AGAINST ('" . $db->escape_string($vbulletin->GPC['keywords']) . "') ";
          $where2 = " MATCH (PM.`message`) AGAINST ('" . $db->escape_string($vbulletin->GPC['keywords']) . "') ";
          */
          if (strlen($vbulletin->GPC['keywords']))
          {
            $where1 = " PM.`title`   LIKE '%" . $db->escape_string($vbulletin->GPC['keywords']) . "%'";
            $where2 = " PM.`message` LIKE '%" . $db->escape_string($vbulletin->GPC['keywords']) . "%'";
          }
          /*
          else
          {
            $where1 = '1'; //" PM.`title`   = ''";
            $where2 = '1'; //" PM.`message` = ''";
          }
          */
          if (strlen($where1) && strlen($where2))
          {
            $where .= " AND (" . $where1 . " OR " . $where2 . ") ";
            $where1 = null;
            $where2 = null;
          }
          break;
        case "user":
          /*
          $userid = rcd_pm_get_uid_by_name($vbulletin->GPC['keywords']);
          $where1 = " PM.`fromuserid` = '" . intval($userid) . "' ";
          $where2 = " PM.`touserid`   = '" . intval($userid) . "' ";
          */
          if (strlen($vbulletin->GPC['keywords']))
          {
            $where1 = " PM.`fromusername` = '" . $db->escape_string(htmlspecialchars_uni($vbulletin->GPC['keywords'])) . "' ";
            $where2 = " PM.`tousername`   = '" . $db->escape_string(htmlspecialchars_uni($vbulletin->GPC['keywords'])) . "' ";
          }
          /*
          else
          {
            $where1 = " PM.`fromusername` = '' ";
            $where2 = " PM.`tousername`   = '' ";
          }
          */
          if (strlen($where1) && strlen($where2))
            $where .= " AND (" . $where1 . " OR " . $where2 . ") ";
          //unset($userid);
          break;
        case "userid":
          /*
          $where1 = " PM.`fromuserid` = '" . intval($vbulletin->GPC['keywords']) . "' ";
          $where2 = " PM.`touserid`   = '" . intval($vbulletin->GPC['keywords']) . "' ";
          */
          if (strlen($vbulletin->GPC['keywords']))
          {
            $username = rcd_pm_get_name_by_uid($vbulletin->GPC['keywords']);
            $where1 = " PM.`fromusername` = '" . $db->escape_string(htmlspecialchars_uni($username)) . "' ";
            $where2 = " PM.`tousername`   = '" . $db->escape_string(htmlspecialchars_uni($username)) . "' ";
            unset($username);
          }
          /*
          else
          {
            $where1 = '1'; //" PM.`fromusername` = '' ";
            $where2 = '1'; //" PM.`tousername`   = '' ";
          }
          */
          if (strlen($where1) && strlen($where2))
            $where .= " AND (" . $where1 . " OR " . $where2 . ") ";
          break;
      }

      switch ($vbulletin->GPC['page'])
      {
        case "last":
          $order  = " ORDER BY `logid` ASC ";
          $page   = 1;
          if (!$count) $vbulletin->GPC['pagenumber'] = ceil($counter / $rows);
          $rows1  = $counter % $rows;
          $rows   = $counter > 0
                      ? ($rows1 > 0 ? $rows1 : $rows)
                      : $rows;
          unset($rows1);
          break;
        case "next":
          $limit .= " AND PM.`logid` < " . intval($vbulletin->GPC['nextlimit']);
          $order  = " ORDER BY `logid` DESC ";
          $page   = 1;
          if (!$count) $vbulletin->GPC['pagenumber'] += 1;
          break;
        case "prev":
          $limit .= " AND PM.`logid` > " . intval($vbulletin->GPC['prevlimit']);
          $order  = " ORDER BY `logid` ASC ";
          $page   = 1;
          if (!$count) $vbulletin->GPC['pagenumber'] -= 1;
          break;
        case "first":
        case "default":
          $order  = " ORDER BY `logid` DESC ";
          $page   = 1;
          if (!$count) $vbulletin->GPC['pagenumber'] = 1;
          break;
      }

      $plusrows = 1;
    }
    else
    {
      $order  = " ORDER BY `logid` DESC ";
    }
  }

  ($hook = vBulletinHook::fetch_hook('pmlog_search_terms')) ? eval($hook) : false;

  if ($count)
  {
    ($hook = vBulletinHook::fetch_hook('pmlog_search_count')) ? eval($hook) : false;

    if ($res === null)
    {
      if ($where1 !== null && $where2 !== null)
      {
        $sql = "
          SELECT COUNT(*) AS `Count` FROM (
            (SELECT PM.`logid`
             FROM `" . TABLE_PREFIX . "rcd_log_pm` AS PM WHERE " . $where1 . ")
            UNION DISTINCT
            (SELECT PM.`logid`
             FROM `" . TABLE_PREFIX . "rcd_log_pm` AS PM WHERE " . $where2 . ")
          ) AS PMRes";
      }
      else
      {
        $sql = "
          SELECT
            COUNT( PM.`logid` ) AS `Count`
          FROM
            `" . TABLE_PREFIX . "rcd_log_pm` AS PM
          " . $where;
      }

      $res = $db->query_first($sql);
      $res = $res['Count'];
    }
  }
  else
  {
    $logids = null;

    # markid shows if next page exists
    $markid = null;

    ($hook = vBulletinHook::fetch_hook('pmlog_search_messages')) ? eval($hook) : false;

    if ($logids === null)
    {
      $logids = array();

      $reslimit = " LIMIT " . (($page - 1) * $rows) . ", " . ($rows + $plusrows);

      if ($where1 !== null && $where2 !== null)
      {
        $sql = "
          (SELECT PM.`logid`
           FROM `" . TABLE_PREFIX . "rcd_log_pm` AS PM WHERE " . $where1 . $limit . $order . $reslimit . ")
          UNION DISTINCT
          (SELECT PM.`logid`
           FROM `" . TABLE_PREFIX . "rcd_log_pm` AS PM WHERE " . $where2 . $limit . $order . $reslimit . ")
          " . $order . $reslimit;
      }
      else
      {
        $sql = "
          SELECT
            PM.`logid`
          FROM
            `" . TABLE_PREFIX . "rcd_log_pm` AS PM
          " . $where . " " . $limit . "
          " . $order . $reslimit;
      }

      $result = $db->query_read($sql);

      while($a = $db->fetch_array($result)) { $logids[] = $a['logid']; }

      if ($plusrows && count($logids) > $rows)
        $markid = array_pop($logids);

      unset($result);
    }

    if ($res === null AND count($logids))
    {
      $res = array();

      $sql = "
        SELECT
          PM.*,
          UserT.`userid`      AS `touserid_check`  ,
          UserT.`usergroupid` AS `tousergroupid`   ,
          UGrpT.`opentag`     AS `toopentag`       ,
          UGrpT.`closetag`    AS `toclosetag`      ,
          UserF.`userid`      AS `fromuserid_check`,
          UserF.`usergroupid` AS `fromusergroupid` ,
          UGrpF.`opentag`     AS `fromopentag`     ,
          UGrpF.`closetag`    AS `fromclosetag`
        FROM
                    `" . TABLE_PREFIX . "rcd_log_pm` AS PM
          LEFT JOIN `" . TABLE_PREFIX . "user`       AS UserT
            ON( PM.`touserid`       = UserT.`userid`      )
          LEFT JOIN `" . TABLE_PREFIX . "usergroup`  AS UGrpT
            ON( UserT.`usergroupid` = UGrpT.`usergroupid` )
          LEFT JOIN `" . TABLE_PREFIX . "user`       AS UserF
            ON( PM.`fromuserid`     = UserF.`userid`      )
          LEFT JOIN `" . TABLE_PREFIX . "usergroup`  AS UGrpF
            ON( UserF.`usergroupid` = UGrpF.`usergroupid` )
        WHERE
          PM.`logid` IN(" . implode(",", $logids) . ")
        ORDER BY
          PM.`logid` DESC 
      ";

      $result = $db->query_read($sql);

      while ($a = $db->fetch_array($result)) { $res[] = $a; }

      if ($markid != null)
        $res[] = array('markid' => $markid);
    }
  }

  return $res;
}


function rcd_pm_get_uid_by_name ($username = '')
{
  global $db;

  if(empty($username))
    return 0;

  $search_arr = array(
    array('userid'    , 'user'      , 'username'    ),
    array('fromuserid', 'rcd_log_pm', 'fromusername'),
    array('touserid'  , 'rcd_log_pm', 'tousername'  ),
  );

  $userid = 0;

  foreach ($search_arr AS $search_cntx)
  {
    $sql = "
      SELECT
        `$search_cntx[0]`
      FROM
        `" . TABLE_PREFIX . "$search_cntx[1]`
      WHERE
        `$search_cntx[2]` = '"
          . $db->escape_string(htmlspecialchars_uni($username))
          . "'
      LIMIT
        1
    ";

    $user = $db->query_first($sql);

    if (is_array($user))
    {
      $userid = $user["$search_cntx[0]"];
      break;
    }
  }

  return $userid;
}



function rcd_pm_get_name_by_uid ($userid = 0)
{
  global $db;

  if (!intval($userid))
    return '';

  $search_arr = array(
    array('username'    , 'user'      , 'userid'    ),
    array('fromusername', 'rcd_log_pm', 'fromuserid'),
    array('tousername'  , 'rcd_log_pm', 'touserid'  ),
  );

  $username = '';

  foreach ($search_arr AS $search_cntx)
  {
    $sql = "
      SELECT
        `$search_cntx[0]`
      FROM
        `" . TABLE_PREFIX . "$search_cntx[1]`
      WHERE
        `$search_cntx[2]` = '" . intval($userid) . "'
      LIMIT
        1
    ";

    $user = $db->query_first($sql);

    if (is_array($user))
    {
      $username = $user["$search_cntx[0]"];
      break;
    }
  }

  return $username;
}


function rcd_pm_log_get_message ($logid)
{
  global $db;

  $sql = "
    SELECT
      PM.*,
      UserT.`userid` AS `touserid_check`,
      UserF.`userid` AS `fromuserid_check`
    FROM
                `" . TABLE_PREFIX . "rcd_log_pm` AS PM
      LEFT JOIN `" . TABLE_PREFIX . "user`       AS UserT
        ON( PM.`touserid`   = UserT.`userid` )
      LEFT JOIN `" . TABLE_PREFIX . "user`       AS UserF
        ON( PM.`fromuserid` = UserF.`userid` )
    WHERE
      PM.`logid` = " . $logid . "
    LIMIT
      1
  ";

  $pm = $db->query_first($sql);

  return $pm;
}
