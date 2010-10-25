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


if (!isset($GLOBALS['vbulletin']->db))
{
  exit;
}


#
#   Define additional functions
#

if (!function_exists('can_administer_pm_log'))
{
  function can_administer_pm_log()
  {
    global $vbulletin;

    if ($vbulletin->userinfo['userid'] < 1)
    {
      // user is a guest - definitely not an administrator
      return false;
    }

    static $admin;

    require_once(DIR . '/includes/adminfunctions.php');

    $return_value = false;

    // use this check only for admins, but not superadmins
    if (can_administer() /*AND !can_administer('adminviewpmlog')*/)
    {
      if (!isset($admin))
      {
        // query specific admin permissions from the administrator
        // table and assign them to $adminperms
        $getperms = $vbulletin->db->query_first("
          SELECT `admin_view_pm_log`
          FROM `" . TABLE_PREFIX . "administrator`
          WHERE `userid` = " . $vbulletin->userinfo['userid']
        );

        $admin = $getperms;
      }

      $return_value = $admin['admin_view_pm_log'] ? true : false;
    }

    return $return_value;
  }
}


function create_private_message_url($user_id)
{
    global $vbulletin, $session;
    $admincpdir = $vbulletin->config['Misc']['admincpdir'];
    return $admincpdir . '/index.php?loc=rcd_pm_log.php%3F' . $session['sessionurl'] . 'search_context%3Duserid%26keywords%3D' . $user_id;
}