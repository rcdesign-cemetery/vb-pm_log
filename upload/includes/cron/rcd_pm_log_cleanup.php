<?php
/*======================================================================*\
|| #################################################################### ||
|| # PM Log 1.4                                                       # ||
|| # ---------------------------------------------------------------- # ||
|| # Copyright © 2009 Dmitry Titov, Vitaly Puzrin.                    # ||
|| # All Rights Reserved.                                             # ||
|| # This file may not be redistributed in whole or significant part. # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
if (!is_object($vbulletin->db))
{
	exit;
}

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('ONEDAY', 86400);

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

// Delete too old log records
$vbulletin->db->query_write("
	DELETE FROM `" . TABLE_PREFIX . "rcd_log_pm`
	WHERE `dateline` < " . intval( TIMENOW - $vbulletin->options['rcd_pm_log_keep_time'] * ONEDAY )
);

log_cron_action('', $nextitem, 1);

?>
