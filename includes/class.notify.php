<?php

/*
   ---------------------------------------------------
   | This script contains the notification functions |
   ---------------------------------------------------
*/

/**
 * Notifications
 *
 * @package
 * @version $Id$
 * @copyright 2006 Flyspray.org
 * @notes: This is a mess and should be replaced for 1.0. Yes, I understand now and will definitely rewrite this for 1.1.
 */

class Notifications {

   // {{{ Wrapper function for all others
   function Create ($type, $task_id, $info = null, $to = null, $ntype = NOTIFY_BOTH)
   {
      if (is_null($to)) {
          $to = $this->Address($task_id, $type);
      }

      if(!is_array($to)) {
          settype($to, 'array');
      }

      if (!count($to)) {
        return false;
      }
      
      $languages = array();
      $emails = array();
      $jabbers = array();
      $onlines = array();
      foreach ($to as $recipient) {
          // echo "<pre>".var_dump($recipient)."</pre>";

          if (isset($recipient[0])) {
              $lang = $recipient[0]['lang'];
              if ($lang == 'j') echo "<pre>Error 1!</pre>";
              $emails[$lang][] = $recipient[0]['recipient'];
              if (!in_array($lang, $languages)) {
                  $languages[] = $lang;
              }
          }
          if (isset($recipient[1])) {
              $lang = $recipient[1]['lang'];
              if ($lang == 'j') echo "<pre>Error 2!</pre>";
              $jabbers[$lang][] = $recipient[1]['recipient'];
              if (!in_array($lang, $languages)) {
                  $languages[] = $lang;
              }
          }
          if (isset($recipient[2])) {
              $lang = $recipient[2]['lang'];
              if ($lang == 'j') echo "<pre>Error 3!</pre>";
              $onlines[$lang][] = $recipient[2]['recipient'];
              if (!in_array($lang, $languages)) {
                  $languages[] = $lang;
              }
          }
      }

      foreach ($languages as $lang) {
          echo "<pre>$lang</pre>";
      }
     echo "<pre>".var_dump($emails)."</pre>";
     echo "<pre>".var_dump($jabbers)."</pre>";
     echo "<pre>".var_dump($onlines)."</pre>";
     $msg = $this->GenerateMsg($type, $task_id, $info);
      $result = true;
      if ($ntype == NOTIFY_EMAIL || $ntype == NOTIFY_BOTH) {
          if(!$this->SendEmail((is_array($to[0]) ? $to[0] : $to), $msg[0], $msg[1], $task_id)) {
              $result = false;
          }
      }
      if ($ntype == NOTIFY_JABBER || $ntype == NOTIFY_BOTH) {
          if(!$this->StoreJabber((is_array($to[1]) ? $to[1] : $to), $msg[0], $msg[1])) {
              $result = false;
          }
      }

      // Get rid of undefined offset 2 when notify type is explicitly set,
      // in these cases caller really has not set offset 2. Track down the
      // callers later.
      if ($ntype != NOTIFY_EMAIL && $ntype != NOTIFY_JABBER) {
          if (!$this->StoreOnline((is_array($to[2]) ? $to[2] : $to), $msg[2], $msg[3], $task_id)) {
              $result = false;
          }
      }

      return $result;

   // End of Create() function
   } // }}}

   function StoreOnline($to, $short, $long, $task_id = null) {
      global $db, $fs;

      // echo "<pre>";
      // echo var_dump($to);
      // echo "</pre>";
      if (!count($to)) {
        return false;
      }

      $date = time();

      // store notification in table
      $db->Query("INSERT INTO {notification_messages}
                  (message_subject, message_body, time_created)
                  VALUES (?, ?, ?)",
                  array($short, $long, $date)
                );

      // grab notification id
      $result = $db->Query("SELECT message_id FROM {notification_messages}
                            WHERE time_created = ? ORDER BY message_id DESC",
                            array($date), 1);

      $row = $db->FetchRow($result);
      $message_id = $row['message_id'];

      // If message could not be inserted for whatever reason...
      if (!$message_id) {
          return false;
      }

      // echo "<pre>";
      // echo var_dump($to);
      // echo "</pre>";

      settype($to, 'array');

      $duplicates = array();
      foreach ($to as $jid)
      {
          // make sure every recipient is only added once
          if (in_array($jid['recipient'], $duplicates)) {
              continue;
          }
          $duplicates[] = $jid['recipient'];
          $db->Query("INSERT INTO {notification_recipients}
                     (notify_method, message_id, notify_address)
                     VALUES (?, ?, ?)",
                     array('o', $message_id, $jid['recipient'])
                    );
      }

      return true;
   }

   static function GetUnreadNotifications($user_id) {
      global $db, $fs;

      $notifications = $db->Query('SELECT r.recipient_id, m.message_subject,
                                          m.message_body
                                     FROM {notification_recipients} r
                                     JOIN {notification_messages} m ON r.message_id = m.message_id
                                    WHERE r.notify_method = ? AND notify_address = ?',
              array('o', $user_id));
      return $db->FetchAllArray($notifications);
   }

   static function NotificationsHaveBeenRead($ids) {
      global $db, $fs, $user;

      $readones = join(",", array_map('intval', $ids));
      // echo "<pre>";
      // echo var_dump($readones);
      // echo "</pre>";

      $db->Query("DELETE FROM {notification_recipients}
                        WHERE recipient_id IN ($readones)
                          AND notify_method = ?",
                 array('o'));
//                          AND notify_method = ? AND notify_address = ?",
//                 array('o', $user->infos['user_id']));

   }

   // {{{ Store Jabber messages for sending later
   function StoreJabber( $to, $subject, $body )
   {
      global $db, $fs;

      if (empty($fs->prefs['jabber_server'])
          || empty($fs->prefs['jabber_port'])
          || empty($fs->prefs['jabber_username'])
          || empty($fs->prefs['jabber_password'])) {
            return false;
      }

      if (empty($to)) {
         return false;
      }

      $date = time();

      // store notification in table
      $db->Query("INSERT INTO {notification_messages}
                  (message_subject, message_body, time_created)
                  VALUES (?, ?, ?)",
                  array($subject, $body, $date)
                );

      // grab notification id
      $result = $db->Query("SELECT message_id FROM {notification_messages}
                            WHERE time_created = ? ORDER BY message_id DESC",
                            array($date), 1);

      $row = $db->FetchRow($result);
      $message_id = $row['message_id'];

      // If message could not be inserted for whatever reason...
      if (!$message_id) {
          return false;
      }

      settype($to, 'array');

      $duplicates = array();
      foreach ($to as $jid)
      {
          // make sure every recipient is only added once
          if (in_array($jid['recipient'], $duplicates)) {
              continue;
          }
          $duplicates[] = $jid['recipient'];
          // store each recipient in table
          $db->Query("INSERT INTO {notification_recipients}
                     (notify_method, message_id, notify_address)
                     VALUES (?, ?, ?)",
                     array('j', $message_id, $jid['recipient'])
                    );

      }

      return true;
   } // }}}
   static function JabberRequestAuth($email)
   {
        global $fs;

        include_once BASEDIR . '/includes/class.jabber2.php';

        if (empty($fs->prefs['jabber_server'])
            || empty($fs->prefs['jabber_port'])
            || empty($fs->prefs['jabber_username'])
            || empty($fs->prefs['jabber_password'])) {
            return false;
        }

        $JABBER = new Jabber($fs->prefs['jabber_username'] . '@' . $fs->prefs['jabber_server'],
                   $fs->prefs['jabber_password'],
                   $fs->prefs['jabber_ssl'],
                   $fs->prefs['jabber_port']);
        $JABBER->login();
        $JABBER->send("<presence to='" . Jabber::jspecialchars($email) . "' type='subscribe'/>");
        $JABBER->disconnect();
   }

   // {{{ Send Jabber messages that were stored earlier
   function SendJabber()
   {
      global $db, $fs;

      include_once BASEDIR . '/includes/class.jabber2.php';


      if (empty($fs->prefs['jabber_server'])
          || empty($fs->prefs['jabber_port'])
          || empty($fs->prefs['jabber_username'])
          || empty($fs->prefs['jabber_password'])) {
            return false;
      }

      // get listing of all pending jabber notifications
      $result = $db->Query("SELECT DISTINCT message_id
                            FROM {notification_recipients}
                            WHERE notify_method='j'");

      if (!$db->CountRows($result))
      {
         return false;
      }

      $JABBER = new Jabber($fs->prefs['jabber_username'] . '@' . $fs->prefs['jabber_server'],
                   $fs->prefs['jabber_password'],
                   $fs->prefs['jabber_ssl'],
                   $fs->prefs['jabber_port']);
      $JABBER->login();


      // we have notifications to process - connect
      $JABBER->log("We have notifications to process...");
      $JABBER->log("Starting Jabber session:");

      $ids = array();

      while ( $row = $db->FetchRow($result) )
      {
         $ids[] = $row['message_id'];
      }

      $desired = join(",", array_map('intval', $ids));
      $JABBER->log("message ids to send = {" . $desired . "}");

      // removed array usage as it's messing up the select
      // I suspect this is due to the variable being comma separated
      // Jamin W. Collins 20050328
      $notifications = $db->Query("SELECT * FROM {notification_messages}
                                   WHERE message_id IN ($desired)
                                   ORDER BY time_created ASC"
                                 );

      $JABBER->log("number of notifications {" . $db->CountRows($notifications) . "}");

      // loop through notifications
      while ( $notification = $db->FetchRow($notifications) )
      {
         $subject = $notification['message_subject'];
         $body    = $notification['message_body'];

         $JABBER->log("Processing notification {" . $notification['message_id'] . "}");

            $recipients = $db->Query("SELECT * FROM {notification_recipients}
                                      WHERE message_id = ?
                                      AND notify_method = 'j'",
                                      array($notification['message_id'])
                                    );

            // loop through recipients
            while ($recipient = $db->FetchRow($recipients) )
            {
               $jid = $recipient['notify_address'];
               $JABBER->log("- attempting send to {" . $jid . "}");

               // send notification
               if ($JABBER->send_message($jid, $body, $subject, 'normal'))
               {
                   // delete entry from notification_recipients
                   $result = $db->Query("DELETE FROM {notification_recipients}
                                         WHERE message_id = ?
                                         AND notify_method = 'j'
                                         AND notify_address = ?",
                                         array($notification['message_id'], $jid)
                                       );
                   $JABBER->log("- notification sent");
                } else {
                   $JABBER->log("- notification not sent");
                }
            }
            // check to see if there are still recipients for this notification
            $result = $db->Query("SELECT * FROM {notification_recipients}
                                  WHERE message_id = ?",
                                  array($notification['message_id'])
                                );

            if ( $db->CountRows($result) == 0 )
            {
               $JABBER->log("No further recipients for message id {" . $notification['message_id'] . "}");
               // remove notification no more recipients
               $result = $db->Query("DELETE FROM {notification_messages}
                                     WHERE message_id = ?",
                                     array($notification['message_id'])
                                   );
               $JABBER->log("- Notification deleted");
            }
         }

         // disconnect from server
         $JABBER->disconnect();
         $JABBER->log("Disconnected from Jabber server");

      return true;
   } // }}}
    // {{{ Send email
    function SendEmail($to, $subject, $body, $task_id = null)
    {
        global $fs, $proj, $user;

        if (empty($to) || empty($to[0])) {
            return;
        }

        // Do we want to use a remote mail server?
        if (!empty($fs->prefs['smtp_server'])) {

          // connection... SSL, TLS or none
          if ($fs->prefs['email_tls']) {
              $swiftconn = Swift_SmtpTransport::newInstance($fs->prefs['smtp_server'], 587, 'tls');
          } else if ($fs->prefs['email_ssl']) {
              $swiftconn = Swift_SmtpTransport::newInstance($fs->prefs['smtp_server'], 465, 'ssl');
          } else {
              $swiftconn = Swift_SmtpTransport::newInstance($fs->prefs['smtp_server']);
          }

          if ($fs->prefs['smtp_user']) {
              $swiftconn->setUsername($fs->prefs['smtp_user']);
          }

          if ($fs->prefs['smtp_pass']){
              $swiftconn->setPassword($fs->prefs['smtp_pass']);
          }

          if(defined('FS_SMTP_TIMEOUT')) {
              $swiftconn->setTimeout(FS_SMTP_TIMEOUT);
          }
        // Use php's built-in mail() function
        } else {
            $swiftconn = Swift_MailTransport::newInstance();
        }

        if(defined( 'FS_MAIL_LOGFILE')) {
            $log = Swift_LogContainer::getLog();
            $log->setLogLevel(SWIFT_LOG_EVERYTHING);
        }

        // Make plaintext URLs into hyperlinks, but don't disturb existing ones!
        $body = preg_replace("/(?<!\")(https?:\/\/)([a-zA-Z0-9\-.]+\.[a-zA-Z0-9\-]+([\/]([a-zA-Z0-9_\/\-.?&%=+#])*)*)/", '<a href="$1$2">$2</a>', $body);

        // Make newlines into HTML line breaks
        $body = str_replace("\n","<br>",$body);

        $swift = Swift_Mailer::newInstance($swiftconn);

        $message = new Swift_Message($subject);
        if (isset($fs->prefs['emailNoHTML']) && $fs->prefs['emailNoHTML'] == '1'){
            $body=html_entity_decode(strip_tags($body));
        }
        $message->setBody($body);
        $type = $message->getHeaders()->get('Content-Type');
        $type->setValue('text/html');
        $type->setParameter('charset', 'utf-8');

        $message->getHeaders()->addTextHeader('Precedence', 'list');
        $message->getHeaders()->addTextHeader('X-Mailer', 'Flyspray');

        if ($proj->prefs['notify_reply']) {
            $message->setReplyTo($proj->prefs['notify_reply']);
        }

        if (isset($task_id)) {
            $hostdata = parse_url($GLOBALS['baseurl']);
            $inreplyto = sprintf('<FS%d@%s>', $task_id, $hostdata['host']);
        // see http://cr.yp.to/immhf/thread.html this does not seems to work though :(
            $message->getHeaders()->addTextHeader('In-Reply-To', $inreplyto);
            $message->getHeaders()->addTextHeader('References', $inreplyto);
        }

        // now accepts string , array or Swift_Address.
        echo "<pre>".var_dump($to)."</pre>";
        return true;
        $message->setTo($to['recipient']);
        $message->setFrom(array($fs->prefs['admin_email'] => $proj->prefs['project_title']));
        $swift->send($message);

        if(defined('FS_MAIL_LOGFILE')) {
            if(is_writable(dirname(FS_MAIL_LOGFILE))) {
                if($fh = fopen(FS_MAIL_LOGFILE, 'ab')) {
                    fwrite($fh, $log->dump(true));
                    fwrite($fh, php_uname());
                    fclose($fh);
                }
            }
        }

        return true;
    } //}}}
    // {{{ Create a message for any occasion
    function GenerateMsg($type, $task_id, $arg1='0')
    {
      global $db, $fs, $user, $proj;

      // Get the task details
      $task_details = Flyspray::getTaskDetails($task_id);
      if ($task_id) {
          $proj = new Project($task_details['project_id']);
      }

      // Set the due date correctly
      if ($task_details['due_date'] == '0') {
         $due_date = L('undecided');
      } else {
         $due_date = formatDate($task_details['due_date']);
      }

      // Set the due version correctly
      if ($task_details['closedby_version'] == '0') {
         $task_details['due_in_version_name'] = L('undecided');
      }

      // Get the string of modification
      $notify_type_msg = array(
      	0 => L('none'),
        NOTIFY_TASK_OPENED     => L('taskopened'),
        NOTIFY_TASK_CHANGED    => L('pm.taskchanged'),
        NOTIFY_TASK_CLOSED     => L('taskclosed'),
        NOTIFY_TASK_REOPENED   => L('pm.taskreopened'),
        NOTIFY_DEP_ADDED       => L('pm.depadded'),
        NOTIFY_DEP_REMOVED     => L('pm.depremoved'),
        NOTIFY_COMMENT_ADDED   => L('commentadded'),
        NOTIFY_ATT_ADDED       => L('attachmentadded'),
        NOTIFY_REL_ADDED       => L('relatedadded'),
        NOTIFY_OWNERSHIP       => L('ownershiptaken'),
        NOTIFY_PM_REQUEST      => L('pmrequest'),
        NOTIFY_PM_DENY_REQUEST => L('pmrequestdenied'),
        NOTIFY_NEW_ASSIGNEE    => L('newassignee'),
        NOTIFY_REV_DEP         => L('revdepadded'),
        NOTIFY_REV_DEP_REMOVED => L('revdepaddedremoved'),
        NOTIFY_ADDED_ASSIGNEES => L('assigneeadded'),
      );

      // Generate the nofication message
      if (isset($proj->prefs['notify_subject']) && !$proj->prefs['notify_subject']) {
          $proj->prefs['notify_subject'] = '[%p][#%t] %s';
      }
      if (!isset($proj->prefs['notify_subject']) || $type == NOTIFY_CONFIRMATION || $type == NOTIFY_ANON_TASK || $type == NOTIFY_PW_CHANGE || $type == NOTIFY_NEW_USER) {
          $subject = L('notifyfromfs');
      } else {
          $subject = strtr($proj->prefs['notify_subject'],
                          array('%p' => $proj->prefs['project_title'],
                                '%s' => $task_details['item_summary'],
                                '%t' => $task_id,
                                '%a' => $notify_type_msg[$type],
                                '%u' => $user->infos['user_name']));
      }

      $subject = strtr($subject, "\n", '');


      /* -------------------------------
         | List of notification types: |
         | 1. Task opened              |
         | 2. Task details changed     |
         | 3. Task closed              |
         | 4. Task re-opened           |
         | 5. Dependency added         |
         | 6. Dependency removed       |
         | 7. Comment added            |
         | 8. Attachment added         |
         | 9. Related task added       |
         |10. Taken ownership          |
         |11. Confirmation code        |
         |12. PM request               |
         |13. PM denied request        |
         |14. New assignee             |
         |15. Reversed dep             |
         |16. Reversed dep removed     |
         |17. Added to assignees list  |
         |18. Anon-task opened         |
         |19. Password change          |
         |20. New user                 |
         -------------------------------
      */

      $body = '';
      $onlineshort = '';
      $onlinelong = '';

      // {{{ New task opened
      if ($type == NOTIFY_TASK_OPENED)
      {
         $body .= L('newtaskopened') . " \n\n";
         $body .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ") \n\n";
         $body .= L('attachedtoproject') . ' - ' .  $task_details['project_title'] . "\n";
         $body .= L('summary') . ' - ' . $task_details['item_summary'] . "\n";
         $body .= L('tasktype') . ' - ' . $task_details['tasktype_name'] . "\n";
         $body .= L('category') . ' - ' . $task_details['category_name'] . "\n";
         $body .= L('status') . ' - ' . $task_details['status_name'] . "\n";
         $body .= L('assignedto') . ' - ' . implode(', ', $task_details['assigned_to_name']) . "\n";
         $body .= L('operatingsystem') . ' - ' . $task_details['os_name'] . "\n";
         $body .= L('severity') . ' - ' . $task_details['severity_name'] . "\n";
         $body .= L('priority') . ' - ' . $task_details['priority_name'] . "\n";
         $body .= L('reportedversion') . ' - ' . $task_details['reported_version_name'] . "\n";
         $body .= L('dueinversion') . ' - ' . $task_details['due_in_version_name'] . "\n";
         $body .= L('duedate') . ' - ' . $due_date . "\n";
         $body .= L('details') . ' - ' . $task_details['detailed_desc'] . "\n\n";

         if ($arg1 == 'files') {
            $body .= L('fileaddedtoo') . "\n\n";
            $subject .= ' (' . L('attachmentadded') . ')';
         }

         $body .= L('moreinfo') . "\n";

         $body .= CreateURL('details', $task_id) . "\n\n";

         $onlineshort .= L('newtaskopened') . ". ";
         $onlineshort .= L('attachedtoproject') . ' - ' .  Filters::noXSS($task_details['project_title']) . ". ";
         $onlineshort .= L('summary') . ' - ' .  Filters::noXSS($task_details['item_summary']);

         $onlinelong .= L('newtaskopened') . ': ' . tpl_tasklink($task_id) . '<br/>';
         $onlinelong .= L('attachedtoproject') . ' - ' .   Filters::noXSS($task_details['project_title']) . '<br/>';
         $onlinelong .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")<br/>";
      } // }}}
      // {{{ Task details changed
      if ($type == NOTIFY_TASK_CHANGED)
      {
         $translation = array('priority_name' => L('priority'),
                              'severity_name' => L('severity'),
                              'status_name'   => L('status'),
                              'assigned_to_name' => L('assignedto'),
                              'due_in_version_name' => L('dueinversion'),
                              'reported_version_name' => L('reportedversion'),
                              'tasktype_name' => L('tasktype'),
                              'os_name' => L('operatingsystem'),
                              'category_name' => L('category'),
                              'due_date' => L('duedate'),
                              'percent_complete' => L('percentcomplete'),
                              'mark_private' => L('visibility'),
                              'item_summary' => L('summary'),
                              'detailed_desc' => L('taskedited'),
                              'project_title' => L('attachedtoproject'));

         $body .= L('taskchanged') . "\n\n";
         $body .= 'FS#' . $task_id . ' - ' . $task_details['item_summary'] . "\n";
         $body .= L('userwho') . ': ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")\n";

         $onlineshort .= L('taskchangedshort') . ": ";
         $onlineshort .= 'FS#' . $task_id . ' - ' .  Filters::noXSS($task_details['item_summary']);

         $onlinelong .= L('taskchangedshort') . ': ' . tpl_tasklink($task_id) . '<br/>';
         $onlinelong .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")<br/>";

         $onlinelong .= '<ul>';
         foreach($arg1 as $change)
         {
            if($change[0] == 'assigned_to_name') {
                $change[1] = implode(', ', $change[1]);
                $change[2] = implode(', ', $change[2]);
            }

            if($change[0] == 'detailed_desc') {
                $body .= $translation[$change[0]] . ":\n-------\n" . $change[2] . "\n-------\n";
            } else {
                $body .= $translation[$change[0]] . ': ' . ( ($change[1]) ? $change[1] : '[-]' ) . ' -> ' . ( ($change[2]) ? $change[2] : '[-]' ) . "\n";
                $onlinelong .= '<li>' . $translation[$change[0]] . ': ' . ( ($change[1]) ? $change[1] : '[-]' ) . ' -> ' . ( ($change[2]) ? $change[2] : '[-]' ) . "</li>";
            }
         }
         $onlinelong .= '</ul>';
         $body .= "\n" . L('moreinfo') . "\n";
         $body .= CreateURL('details', $task_id) . "\n\n";
      } // }}}
      // {{{ Task closed
      if ($type == NOTIFY_TASK_CLOSED)
      {
         $body .=  L('notify.taskclosed') . "\n\n";
         $body .= 'FS#' . $task_id . ' - ' . $task_details['item_summary'] . "\n";
         $body .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")\n\n";
         $body .= L('reasonforclosing') . ' ' . $task_details['resolution_name'] . "\n";

         if (!empty($task_details['closure_comment']))
         {
            $body .= L('closurecomment') . ' ' . $task_details['closure_comment'] . "\n\n";
         }

         $body .= L('moreinfo') . "\n";
         $body .= CreateURL('details', $task_id) . "\n\n";

         $onlineshort .=  L('notify.taskclosed') . ". ";
         $onlineshort .= 'FS#' . $task_id . ' - ' .  Filters::noXSS($task_details['item_summary']) . ". ";

         $onlinelong .= L('notify.taskclosed') . ' ' . tpl_tasklink($task_id) . '<br/>';
         $onlinelong .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")<br/>";

      } // }}}
      // {{{ Task re-opened
      if ($type == NOTIFY_TASK_REOPENED)
      {
         $body .=  L('notify.taskreopened') . "\n\n";
         $body .= 'FS#' . $task_id . ' - ' . $task_details['item_summary'] . "\n";
         $body .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] .  ")\n\n";
         $body .= L('moreinfo') . "\n";
         $body .= CreateURL('details', $task_id) . "\n\n";

         $onlineshort .=  L('notify.taskreopened') . ". ";
         $onlineshort .= 'FS#' . $task_id . ' - ' .  Filters::noXSS($task_details['item_summary']) . ". ";

         $onlinelong .= L('notify.taskreopened') . ' ' . tpl_tasklink($task_id) . '<br/>';
         $onlinelong .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")<br/>";
      } // }}}
      // {{{ Dependency added
      if ($type == NOTIFY_DEP_ADDED)
      {
         $depend_task = Flyspray::getTaskDetails($arg1);

         $body .=  L('newdep') . "\n\n";
         $body .= 'FS#' . $task_id . ' - ' . $task_details['item_summary'] . "\n";
         $body .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")\n";
         $body .= CreateURL('details', $task_id) . "\n\n\n";
         $body .= L('newdepis') . ':' . "\n\n";
         $body .= 'FS#' . $depend_task['task_id'] . ' - ' .  $depend_task['item_summary'] . "\n";
         $body .= CreateURL('details', $depend_task['task_id']) . "\n\n";

         $onlineshort .=  L('newdep') . ". ";
         $onlineshort .= 'FS#' . $task_id . ' - ' .  Filters::noXSS($task_details['item_summary']) . ". ";

         $onlinelong .= L('newdep') . ' ' . tpl_tasklink($task_id) . '<br/>';
         $onlinelong .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")<br/>";
      } // }}}
      // {{{ Dependency removed
      if ($type == NOTIFY_DEP_REMOVED)
      {
         $depend_task = Flyspray::getTaskDetails($arg1);

         $body .= L('notify.depremoved') . "\n\n";
         $body .= 'FS#' . $task_id . ' - ' . $task_details['item_summary'] . "\n";
         $body .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")\n";
         $body .= CreateURL('details', $task_id) . "\n\n\n";
         $body .= L('removeddepis') . ':' . "\n\n";
         $body .= 'FS#' . $depend_task['task_id'] . ' - ' .  $depend_task['item_summary'] . "\n";
         $body .= CreateURL('details', $depend_task['task_id']) . "\n\n";

         $onlineshort .= L('notify.depremoved') . ". ";
         $onlineshort .= 'FS#' . $task_id . ' - ' .  Filters::noXSS($task_details['item_summary']) . ". ";

         $onlinelong .= L('notify.depremoved') . ' ' . tpl_tasklink($task_id) . '<br/>';
         $onlinelong .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")<br/>";
      } // }}}
      // {{{ Comment added
      if ($type == NOTIFY_COMMENT_ADDED)
      {
         // Get the comment information
         $result = $db->Query("SELECT comment_id, comment_text
                               FROM {comments}
                               WHERE user_id = ?
                               AND task_id = ?
                               ORDER BY comment_id DESC",
                               array($user->id, $task_id), '1');
         $comment = $db->FetchRow($result);

         $body .= L('notify.commentadded') . "\n\n";
         $body .= 'FS#' . $task_id . ' - ' . $task_details['item_summary'] . "\n";
         $body .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")\n\n";
         $body .= "----------\n";
         $body .= $comment['comment_text'] . "\n";
         $body .= "----------\n\n";

         if ($arg1 == 'files') {
            $body .= L('fileaddedtoo') . "\n\n";
            $subject .= ' (' . L('attachmentadded') . ')';
         }

         $body .= L('moreinfo') . "\n";
         $body .= CreateURL('details', $task_id) . '#comment' . $comment['comment_id'] . "\n\n";

         $onlineshort .= L('notify.commentadded') . ". ";
         $onlineshort .= 'FS#' . $task_id . ' - ' .  Filters::noXSS($task_details['item_summary']) . ". ";
         
         $onlinelong .= L('notify.commentadded') . ' <a href="';
         $onlinelong .= CreateURL('details', $task_id) . '#comment' . $comment['comment_id'] . '">';
         $onlinelong .= 'FS#' . $task_id . ' - ' .  Filters::noXSS($task_details['item_summary']) . "</a><br/>";
         $onlinelong .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")<br/>";
         // Problem here: don't know how comment_text is formatted!
         // $onlinelong .= $comment['comment_text'] . "<br/>";
      } // }}}
      // {{{ Attachment added
      if ($type == NOTIFY_ATT_ADDED)
      {
         $body .= L('newattachment') . "\n\n";
         $body .= 'FS#' . $task_id . ' - ' . $task_details['item_summary'] . "\n";
         $body .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")\n\n";
         $body .= L('moreinfo') . "\n";
         $body .= CreateURL('details', $task_id) . "\n\n";

         $onlineshort .= L('newattachment') . ". ";
         $onlineshort .= 'FS#' . $task_id . ' - ' .  Filters::noXSS($task_details['item_summary']) . ". ";

         $onlinelong .= L('newattachment') . ' ' . tpl_tasklink($task_id);
         $onlinelong .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")<br/>";
      } // }}}
      // {{{ Related task added
      if ($type == NOTIFY_REL_ADDED)
      {
         $related_task = Flyspray::getTaskDetails($arg1);

         $body .= L('notify.relatedadded') . "\n\n";
         $body .= 'FS#' . $task_id . ' - ' . $task_details['item_summary'] . "\n";
         $body .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")\n";
         $body .= CreateURL('details', $task_id) . "\n\n\n";
         $body .= L('relatedis') . ':' . "\n\n";
         $body .= 'FS#' . $related_task['task_id'] . ' - ' . $related_task['item_summary'] . "\n";
         $body .= CreateURL('details', $related_task['task_id']) . "\n\n";

         $onlineshort .= L('notify.relatedadded') . ". ";
         $onlineshort .= 'FS#' . $task_id . ' - ' .  Filters::noXSS($task_details['item_summary']) . ". ";

         $onlinelong .= L('notify.relatedadded') . ' ' . tpl_tasklink($task_id) . '<br/>';
         $onlinelong .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")<br/>";
      } // }}}
      // {{{ Ownership taken
      if ($type == NOTIFY_OWNERSHIP)
      {
         $body .= implode(', ', $task_details['assigned_to_name']) . ' ' . L('takenownership') . "\n\n";
         $body .= 'FS#' . $task_id . ' - ' . $task_details['item_summary'] . "\n\n";
         $body .= L('moreinfo') . "\n";
         $body .= CreateURL('details', $task_id) . "\n\n";

         $onlineshort .= implode(', ', $task_details['assigned_to_name']) . ' ' . L('takenownership') . ". ";
         $onlineshort .= 'FS#' . $task_id . ' - ' .  Filters::noXSS($task_details['item_summary']) . ".";

         $onlinelong .= implode(', ', $task_details['assigned_to_name']) . ' ' . L('takenownership') . ' ' . tpl_tasklink($task_id) . '<br/>';
      } // }}}
      // {{{ Confirmation code
      if ($type == NOTIFY_CONFIRMATION)
      {
         $body .= L('noticefrom') . " {$proj->prefs['project_title']}\n\n"
               . L('addressused') . "\n\n"
               . " {$arg1[0]}index.php?do=register&magic_url={$arg1[1]} \n\n"
                // In case that spaces in the username have been removed
               . L('username') . ': '. $arg1[2] . "\n"
               . L('confirmcodeis') . " $arg1[3] \n\n";

          $onlineshort = $body;
          $onlinelong = $body;
      } // }}}
      // {{{ Pending PM request
      if ($type == NOTIFY_PM_REQUEST)
      {
         $body .= L('requiresaction') . "\n\n";
         $body .= 'FS#' . $task_id . ' - ' . $task_details['item_summary'] . "\n";
         $body .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")\n\n";
         $body .= L('moreinfo') . "\n";
         $body .= CreateURL('details', $task_id) . "\n\n";

         $onlineshort .= L('requiresaction') . ". ";
         $onlineshort .= 'FS#' . $task_id . ' - ' .  Filters::noXSS($task_details['item_summary']) . ". ";
         // $online .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . "). ";
      } // }}}
      // {{{ PM request denied
      if ($type == NOTIFY_PM_DENY_REQUEST)
      {
         $body .= L('pmdeny') . "\n\n";
         $body .= 'FS#' . $task_id . ' - ' . $task_details['item_summary'] . "\n";
         $body .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")\n\n";
         $body .= L('denialreason') . ':' . "\n";
         $body .= $arg1 . "\n\n";
         $body .= L('moreinfo') . "\n";
         $body .= CreateURL('details', $task_id) . "\n\n";

         $onlineshort .= L('pmdeny') . ". ";
         $onlineshort .= 'FS#' . $task_id . ' - ' .  Filters::noXSS($task_details['item_summary']) . ". ";
         // $online .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . "). ";
      } // }}}
      // {{{ New assignee
      if ($type == NOTIFY_NEW_ASSIGNEE)
      {
         $body .= L('assignedtoyou') . "\n\n";
         $body .= 'FS#' . $task_id . ' - ' . $task_details['item_summary'] . "\n";
         $body .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")\n\n";
         $body .= L('moreinfo') . "\n";
         $body .= CreateURL('details', $task_id) . "\n\n";

         $onlineshort .= L('assignedtoyou') . ": ";
         $onlineshort .= 'FS#' . $task_id . ' - ' .  Filters::noXSS($task_details['item_summary']) . ". ";

         $onlinelong .= L('assignedtoyou') . ": " . tpl_tasklink($task_id);
      } // }}}
      // {{{ Reversed dep
      if ($type == NOTIFY_REV_DEP)
      {
         $depend_task = Flyspray::getTaskDetails($arg1);

         $body .= L('taskwatching') . "\n\n";
         $body .= 'FS#' . $task_id . ' - ' . $task_details['item_summary'] . "\n";
         $body .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")\n";
         $body .= CreateURL('details', $task_id) . "\n\n\n";
         $body .= L('isdepfor') . ':' . "\n\n";
         $body .= 'FS#' . $depend_task['task_id'] . ' - ' .  $depend_task['item_summary'] . "\n";
         $body .= CreateURL('details', $depend_task['task_id']) . "\n\n";

         $onlineshort .= L('taskwatching') . ". ";
         $onlineshort .= 'FS#' . $task_id . ' - ' .  Filters::noXSS($task_details['item_summary']) . ". ";

         $onlinelong .= L('taskwatching') . ' ' . tpl_tasklink($task_id) . '<br/>';
         $onlinelong .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")<br/>";
      } // }}}
      // {{{ Reversed dep - removed
      if ($type == NOTIFY_REV_DEP_REMOVED)
      {
         $depend_task = Flyspray::getTaskDetails($arg1);

         $body .= L('taskwatching') . "\n\n";
         $body .= 'FS#' . $task_id . ' - ' . $task_details['item_summary'] . "\n";
         $body .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")\n";
         $body .= CreateURL('details', $task_id) . "\n\n\n";
         $body .= L('isnodepfor') . ':' . "\n\n";
         $body .= 'FS#' . $depend_task['task_id'] . ' - ' .  $depend_task['item_summary'] . "\n";
         $body .= CreateURL('details', $depend_task['task_id']) . "\n\n";

         $onlineshort .= L('taskwatching') . ". ";
         $onlineshort .= 'FS#' . $task_id . ' - ' .  Filters::noXSS($task_details['item_summary']) . ". ";

         $onlinelong .= L('taskwatching') . ' ' . tpl_tasklink($task_id) . '<br/>';
         $onlinelong .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")<br/>";
      } // }}}
      // {{{ User added to assignees list
      if ($type == NOTIFY_ADDED_ASSIGNEES)
      {
         $body .= L('useraddedtoassignees') . "\n\n";
         $body .= 'FS#' . $task_id . ' - ' . $task_details['item_summary'] . "\n";
         $body .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")\n";
         $body .= CreateURL('details', $task_id) . "\n\n\n";

         $onlineshort .= L('useraddedtoassignees') . ". ";
         $onlineshort .= 'FS#' . $task_id . ' - ' .  Filters::noXSS($task_details['item_summary']) . ". ";

         $onlinelong .= L('useraddedtoassignees') . ' ' . tpl_tasklink($task_id) . '<br/>';
         $onlinelong .= L('userwho') . ' - ' . $user->infos['real_name'] . ' (' . $user->infos['user_name'] . ")<br/>";
      } // }}}
      // {{{ Anon-task has been opened
      if ($type == NOTIFY_ANON_TASK)
      {
         $body .= L('thankyouforbug') . "\n\n";
         $body .= CreateURL('details', $task_id, null, array('task_token' => $arg1)) . "\n\n";

         $onlineshort .= L('thankyouforbug') . "";
         $onlinelong .= L('thankyouforbug') . "";
      } // }}}
      // {{{ Password change
      if ($type == NOTIFY_PW_CHANGE)
      {
          $body =       L('magicurlmessage')." \n"
			. "{$arg1[0]}index.php?do=lostpw&magic_url=$arg1[1]\n\n"
			. L('messagefrom'). $arg1[0];
          $onlineshort = $body;
          $onlinelong = $body;

      } // } }}
      // {{{ New user
      if ($type == NOTIFY_NEW_USER)
      {
		$body =	L('newuserregistered')." \n\n"
                	. L('username') . ': ' . $arg1[1] . "\n" .
			L('realname') . ': ' . $arg1[2] . "\n";
                $onlineshort = $body;

		if ($arg1[6]) {
			$body .= L('password') . ': ' . $arg1[5] . "\n";
		}

		$body .= L('emailaddress') . ': ' . $arg1[3] . "\n";
		$body .= L('jabberid') . ':' . $arg1[4] . "\n\n";
		$body .= L('messagefrom'). $arg1[0];
      } // }}}

      $body = L('donotreply') . "\n\n" . $body;
      $body .= '. '.L('disclaimer');
      return array(Notifications::fixMsgData($subject), Notifications::fixMsgData($body), $onlineshort, $onlinelong);

   } // }}}
   // {{{ Create an address list for specific users
   function SpecificAddresses($users, $ignoretype = false)
   {
        global $db, $fs, $user;

        echo "<pre>SpecificAddresses</pre>";

        $jabber_users = array();
        $email_users = array();
        $online_users = array();

        if(!is_array($users)) {
            settype($users, 'array');
        }

        if (count($users) < 1) {
            return array();
        }

        $sql = $db->Query('SELECT user_id, notify_type, email_address, jabber_id, notify_online, lang_code
                             FROM {users}
                            WHERE' . substr(str_repeat(' user_id = ? OR ', count($users)), 0, -3),
                           array_values($users));

        while ($user_details = $db->FetchRow($sql))
        {
            if ($user_details['user_id'] == $user->id && !$user->infos['notify_own']) {
                continue;
            }

            if ( ($fs->prefs['user_notify'] == '1' && ($user_details['notify_type'] == NOTIFY_EMAIL || $user_details['notify_type'] == NOTIFY_BOTH) )
                || $fs->prefs['user_notify'] == '2' || $ignoretype)
            {
             if (isset($row['email_address']) && !empty($row['email_address'])) {
                array_push($email_users, array('recipient' => $user_details['email_address'], 'lang' => $user_details['lang_code']));
             }
            }

            if ( ($fs->prefs['user_notify'] == '1' && ($user_details['notify_type'] == NOTIFY_JABBER || $user_details['notify_type'] == NOTIFY_BOTH) )
                || $fs->prefs['user_notify'] == '3' || $ignoretype)
            {
             if (isset($row['jabber_id']) && !empty($row['jabber_id'])) {
                array_push($jabber_users, array('recipient' => $user_details['jabber_id'], 'lang' => $user_details['lang_code']));
             }
            }

            if ($fs->prefs['user_notify'] == '1' && $user_details['notify_online'])
            {
                array_push($online_users, array('recipient' => $user_details['user_id'], 'lang' => $user_details['lang_code']));
            }
        }

        return array($email_users, $jabber_users, $online_users);

   } // }}}
   // {{{ Create a standard address list of users (assignees, notif tab and proj addresses)
   function Address($task_id, $type)
   {
      global $db, $fs, $proj, $user;
      echo "<pre>Address</pre>";

      $users = array();

      $jabber_users = array();
      $email_users = array();
      $online_users = array();

      $task_details = Flyspray::GetTaskDetails($task_id);

      // Get list of users from the notification tab
      $get_users = $db->Query('SELECT *
                               FROM {notifications} n
                               LEFT JOIN {users} u ON n.user_id = u.user_id
                               WHERE n.task_id = ?',
                               array($task_id));

      while ($row = $db->FetchRow($get_users))
      {
         if ($row['user_id'] == $user->id && !$user->infos['notify_own']) {
            continue;
         }

         if ( ($fs->prefs['user_notify'] == '1' && ($row['notify_type'] == NOTIFY_EMAIL || $row['notify_type'] == NOTIFY_BOTH) )
             || $fs->prefs['user_notify'] == '2')
         {
             if (isset($row['email_address']) && !empty($row['email_address'])) {
               array_push($email_users, array('recipient' => $row['email_address'], 'lang' => $row['lang_code']));
             }
         }

         if ( ($fs->prefs['user_notify'] == '1' && ($row['notify_type'] == NOTIFY_JABBER || $row['notify_type'] == NOTIFY_BOTH) )
             || $fs->prefs['user_notify'] == '3')
         {
             if (isset($row['jabber_id']) && !empty($row['jabber_id'])) {
               array_push($jabber_users, array('recipient' => $row['jabber_id'], 'lang' => $row['lang_code']));
             }
         }

         // if ( ($fs->prefs['user_notify'] == '1' && ($row['notify_type'] == NOTIFY_ONLINE || $row['notify_type'] == NOTIFY_BOTH) )
         //    || $fs->prefs['user_notify'] == '4')
         if ($fs->prefs['user_notify'] == '1' && $row['notify_online'])
         {
               array_push($online_users, array('recipient' => $row['user_id'], 'lang' => $row['lang_code']));
         }
      }

      // Get list of assignees
      $get_users = $db->Query('SELECT *
                               FROM {assigned} a
                               LEFT JOIN {users} u ON a.user_id = u.user_id
                               WHERE a.task_id = ?',
                               array($task_id));

      while ($row = $db->FetchRow($get_users))
      {
         if ($row['user_id'] == $user->id && !$user->infos['notify_own']) {
            continue;
         }

         if ( ($fs->prefs['user_notify'] == '1' && ($row['notify_type'] == NOTIFY_EMAIL || $row['notify_type'] == NOTIFY_BOTH) )
             || $fs->prefs['user_notify'] == '2')
         {
             if (isset($row['email_address']) && !empty($row['email_address'])) {
               array_push($email_users, array('recipient' => $row['email_address'], 'lang' => $row['lang_code']));
             }
         }

         if ( ($fs->prefs['user_notify'] == '1' && ($row['notify_type'] == NOTIFY_JABBER || $row['notify_type'] == NOTIFY_BOTH) )
             || $fs->prefs['user_notify'] == '3')
         {
             if (isset($row['jabber_id']) && !empty($row['jabber_id'])) {
               array_push($jabber_users, array('recipient' => $row['jabber_id'], 'lang' => $row['lang_code']));
             }
         }

         // if ( ($fs->prefs['user_notify'] == '1' && ($row['notify_type'] == NOTIFY_ONLINE || $row['notify_type'] == NOTIFY_BOTH) )
         //    || $fs->prefs['user_notify'] == '4')
         if ($fs->prefs['user_notify'] == '1' && $row['notify_online'])
         {
               array_push($online_users, array('recipient' => $row['user_id'], 'lang' => $row['lang_code']));
         }
      }

      // Now, we add the project contact addresses...
      // ...but only if the task is public
      if ($task_details['mark_private'] != '1' && in_array($type, Flyspray::int_explode(' ', $proj->prefs['notify_types'])))
      {
         // FIXME!
         $proj_emails = preg_split('/[\s,;]+/', $proj->prefs['notify_email'], -1, PREG_SPLIT_NO_EMPTY);
         $proj_jids = explode(',', $proj->prefs['notify_jabber']);
         
         echo "<pre>";
         echo var_dump($proj_emails);
         echo var_dump($proj_jids);
         echo "</pre>";
         
         foreach ($proj_emails as $key => $val)
         {
            if (!empty($val) && !in_array($val, $email_users))
               array_push($email_users, $val);
         }

         foreach ($proj_jids as $key => $val)
         {
            if (!empty($val) && !in_array($val, $jabber_users))
               array_push($jabber_users, $val);
         }

         // No online notifications?
      // End of checking if a task is private
      }
      // Send back three arrays containing the notification addresses
      return array($email_users, $jabber_users, $online_users);

   } // }}}
    // {{{ Fix the message data
        /**
         * fixMsgData
         * a 0.9.9.x ONLY workaround for the "truncated email problem"
         * based on code Henri Sivonen (http://hsivonen.iki.fi)
         * @param mixed $data
         * @access public
         * @return void
         */
        function fixMsgData($data)
        {
            // at the first step, remove all NUL bytes
            //users with broken databases  encoding  can give us this :(
            $data = str_replace(chr(0), '', $data);

            //then remove all invalid utf8 secuences
                $UTF8_BAD =
                '([\x00-\x7F]'.                          # ASCII (including control chars)
                '|[\xC2-\xDF][\x80-\xBF]'.               # non-overlong 2-byte
                '|\xE0[\xA0-\xBF][\x80-\xBF]'.           # excluding overlongs
                '|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'.    # straight 3-byte
                '|\xED[\x80-\x9F][\x80-\xBF]'.           # excluding surrogates
                '|\xF0[\x90-\xBF][\x80-\xBF]{2}'.        # planes 1-3
                '|[\xF1-\xF3][\x80-\xBF]{3}'.            # planes 4-15
                '|\xF4[\x80-\x8F][\x80-\xBF]{2}'.        # plane 16
                '|(.{1}))';                              # invalid byte

            $valid_data = '';

            while (preg_match('/'.$UTF8_BAD.'/S', $data, $matches)) {
                if ( !isset($matches[2])) {
                    $valid_data .= $matches[0];
                } else {
                    $valid_data .= '?';
                }
                $data = substr($data, strlen($matches[0]));
            }
                return $valid_data;
        } //}}}

// End of Notify class
}
