<?php

  /*********************************************************\
  | View online notifications                               |
  | ~~~~~~~~~~~~~~~~~~~~                                    |
  \*********************************************************/

if (!defined('IN_FS')) {
    die('Do not access this file directly.');
}

$notifications = Notifications::GetUnreadNotifications(Get::num('id'));

$page->assign('notifications', $notifications);
$page->setTitle($fs->prefs['page_title'] . L('notifications'));
$page->pushTpl('common.notifications.tpl');

?>
