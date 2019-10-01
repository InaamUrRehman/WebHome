<?php
require 'includes/master.inc.php';
require_once 'includes/vlib/vlibTemplate.php';
$tmpl = new vlibTemplate('tmpl/common_messages.htm');

if (isset($_POST['id'])) {
  if($_POST["id"] != 1){
    $msg = "";
    $uid = $_POST["id"];
    $db = Database::getDatabase();
    $fill=$db->getRow($db->query("select id, username, firstname, lastname, gender, dob, city, state, country, mobile, sponsor, joindate from users where id='".$uid."'"));
    $username = $fill['username'];

    $html=read_template("profile.minimal.htm");

    $html=str_replace("#msg#",$msg,$html);
    $html=str_replace("#username#",$fill["username"],$html);
    $html=str_replace("#firstname#",$fill["firstname"],$html);
    $html=str_replace("#lastname#",$fill["lastname"],$html);

    if($fill["gender"] == "female"){
      $gender = '#FEMALE#';
    } else{
      $gender = '#MALE#';
    }
    $html=str_replace("#gender#",$gender,$html);

    $age = "";
    if($fill["dob"]){
      $dob = $fill["dob"];
      $yr1 = substr($dob,0,4);
      $yr2 = date('Y');
      $age = $yr2 - $yr1;
    }
    $html=str_replace("#age#",$age,$html);
    $html=str_replace("#city#",$fill["city"],$html);
    $html=str_replace("#state#",$fill["state"],$html);
    $html=str_replace("#country#",$fill["country"],$html);

    $fill2=mysql_fetch_array(mysql_query("select * from user_professional where userid='".$uid."'"));
    $html=str_replace("#occupation#",$fill2["occ_type"],$html);

    $res = mysql_query("select image from images where userid='$uid' and isdefault='1'");
    if($res){
      $row = mysql_fetch_array($res);
      if($row)
        $image = $row['image'];
    }
    if($image){
      $photo = "<a href='#' onclick=\"window.open('$Config->siteurl/m_photos/$image','','width=500, height=500, resizable=yes, left=100,top=100,menu=no, toolbar=no,resizable=yes,scrollbars=yes');return false;\">
        <img src='$Config->siteurl/m_photos/$image' width='150' height='150' style=\"padding:3px; margin-right:10xp;\"></a>";
    } else {
      $photo = "<img src='$Config->siteurl/m_photos/no_img.png' width='150' height='150' style=\"padding:3px; margin-right:10xp;\">";
    }
    $html=str_replace("#memberphoto#",$photo,$html);

    $downlinefill = "<table class=\"table1\" width=\"300\" cellpadding=\"0\" cellspacing=\"0\">
    <thead><th class=\"th1\">#DOWNLINE LEVEL#</th><th class=\"th1\">#REFERRAL COUNT#</th></thead><tbody>";

    $planinfo = get_plan_info(get_member_plan($uid));
    $depth = $planinfo['depth'];
    if($depth == 0)
      $depth = 10;//binary plan
	
	$level_cnts = get_refs_cnt_level_data($username,$depth);
      for($i=1;$i<=$depth;$i++) {
        $refs = $level_cnts[$i];
        if($i % 2) $class="rowbg0"; else $class="rowbg1";
        $downlinefill .= "<tr class=\"$class\"><td class=\"td1\">$i</td><td>".$refs."</td></tr>";
      }
      $downlinefill .= "</tbody></table>";

    $html=str_replace("#fill#",$downlinefill,$html);

    //localized text
			$tmploutput = str_replace("\#","HASH_HASH_HASH",$html);
			preg_match_all('/#[A-Z_ ]+#/', $tmploutput, $results);

			for($i=0;$i<count($results[0]);$i++)
			{
			 $str=str_replace('#','',$results[0][$i]);
			 $tmploutput=str_replace($results[0][$i],$tmpl->get_lstring($str),$tmploutput);
			}
			$tmploutput = str_replace("HASH_HASH_HASH","#",$tmploutput);
    //

    echo $tmploutput;
  }
}

include "treeview.class.php";

$oTv = new treeView('users', 'username');
$oTv->jFunct = "showContent(pNode)";
$oTv->sortOrder = 'joindate';

if (isset($_POST['parent'])) {
	echo $oTv->treeNode($_POST['parent'], $_POST['last']);
	exit;
}

?>
