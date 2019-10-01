<?php

require 'includes/master.inc.php';
require_once 'includes/vlib/vlibTemplate.php';
$Auth->requireUser('view_msg.php');
$tmpl = new vlibTemplate('tmpl/user_mail_reply.htm');
$uid = $_SESSION['m_user']['id'];
$username = $_SESSION['m_user']['username'];
$tmpl->setvar('siteurl', $Config->siteurl);
$tmpl->setvar('page_title', $Config->sitename . ":" . $tmpl->get_lstring('INBOX'));
$tmpl->setvar('title', $tmpl->get_lstring('MESSAGES'));
$options = '<li class="active"><a href="#">#VIEW#</a></li>
    <li><a href="messages.php">#INBOX#</a></li>
            <li><a href="compose.php">#COMPOSE#</a></li>';
$tmpl->setvar('options', $options);
$db = Database::getDatabase();
if (isset($_GET['view'])) {
    $mid = $_GET['view'];
    $db->query("update message set isread='1' where id='" . $mid . "'");
    $res = $db->getRow("select * from message where id='" . $mid . "'");
    $sid = $res['sender'];
    $u_name = get_member_username($sid);
    $tmpl->setvar('subject', $res['subject']);
    $tmpl->setvar('from', $u_name);
    $tmpl->setvar('message', html_entity_decode(stripslashes($res['text'])));
}

if (isset($_POST['submit1'])) {
    $frmadd = $_POST['from'];
    $msg = addslashes($_POST['message']);
    $sbj = addslashes($_POST['subject']);
    $recid = get_member_id($frmadd);
    $tmpl->setVar('confirmation_msg', '');
    $tmpl->setVar('err_msg', '');
    $db->query("insert into message(sent_time,subject,text,receiver,sender) values('" . time() . "','" . $sbj . "','" . $msg . "','" . $recid . "','" . $uid . "')");
    $user_msg .='  <ul class="messages">
<li class="success-msg">
<ul>
<li><span>#REPLY SENT TO# '.$frmadd.' </span></li>
</ul>
</li>
</ul>';
    $tmpl->setvar('confirmation_msg', $user_msg);
}


$tmpl->pparse();
?>

