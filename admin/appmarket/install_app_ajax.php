<?php
/*
 * @copyright   Leyun internet Technology(Shanghai)Co.,Ltd
 * @license     http://www.dzzoffice.com/licenses/license.txt
 * @package     DzzOffice
 * @link        http://www.dzzoffice.com
 * @author      qchlian 3580164@qq.com
 */
if (!defined('IN_DZZ') || !defined('IN_ADMIN')) {
    exit('Access Denied');
}
error_reporting(E_ALL);
@set_time_limit(0);
include_once DZZ_ROOT . './core/core_version.php';
include_once libfile('function/admin');
include_once libfile('function/cache'); 
$step = intval($_GET['step']);
$op = $_GET['op'];
$step = $step ? $step : 1;
$operation = $_GET['operation'] ? trim($_GET['operation']) : 'upgrade';
 header('Content-type:text/json');
$steplang = array('', lang('founder_upgrade_updatelist'), lang('founder_upgrade_download'), lang('founder_upgrade_compare'), lang('founder_upgrade_upgrading'), lang('founder_upgrade_complete'), 'dbupdate' => lang('founder_upgrade_dbupdate'));
if ($operation == 'check_install' ) {//根据appid检查app应用是否需要更新
    $baseinfo = $_GET["baseinfo"]; 
    $baseinfo = base64_decode($baseinfo);
    $baseinfo = unserialize($baseinfo); 
    //$map["identifier"]=$baseinfo["identifier"];
    //$map["app_path"]=$baseinfo["app_path"];
    $map["mid"]=$baseinfo["mid"];
    if( !$baseinfo ||  $baseinfo["identifier"]==""){
        $return["status"]=0;
        $return["msg"]= lang("app_upgrade_identifier_error");
        exit(json_encode($return));
    }
    //根据当前版本查询是否达到安装条件 
    $dbversion= helper_dbtool::dbversion(); 
    $dzzversion = CORE_VERSION;
    if(version_compare($baseinfo['dzzversion'], $dzzversion) > 0 ) {
        $return["status"]=0;
        $return["msg"]=lang('app_upgrade_dzzversion_error', array('version' => $baseinfo['dzzversion']));  
        exit(json_encode($return));//不需要安装
    }
    if( version_compare($baseinfo['phpversion'], PHP_VERSION) > 0 ) {
        $return["status"]=0;
        $return["msg"]=lang('app_upgrade_phpversion_error', array('version' => $baseinfo['phpversion'])); 
        exit(json_encode($return));//不需要安装
    }
    if( version_compare($baseinfo['mysqlversion'], $dbversion) > 0) {
        $return["status"]=0;
        $return["msg"]=lang('app_upgrade_mysqlversion_error', array('version' => $baseinfo['mysqlversion'])); 
        exit(json_encode($return));//不需要安装
    }

    $appinfo = C::tp_t('app_market')->where($map)->find();
    $time =dgmdate(TIMESTAMP,'Ymd');
    $return=array(
        "url"=>ADMINSCRIPT .'?mod='.MOD_NAME.'&op=install_app_ajax', 
        "status"=>1,
        "percent"=>5,
        "second"=>1, 
        "msg"=>lang("app_upgrade_check_is_install")
    );
    if( $appinfo ){
        $return["status"]=0;
        $return["msg"]= lang("app_upgrade_installed");
        /*if( $appinfo["mid"]==0 ){
            $return["msg"]= lang("app_upgrade_installed_local");
        }*/
        exit(json_encode($return));//不需要安装
    }
    exit(json_encode($return));//未安装
}
elseif($operation == 'upgrade' ){
    $baseinfo = $_GET["baseinfo"]; 
    $baseinfo = base64_decode($baseinfo);
    $baseinfo = unserialize($baseinfo); 
    
    $return=array(
        "url"=>ADMINSCRIPT .'?mod=appmarket&op=cloudappmarket',
        "status"=>1,
        "percent"=>10,
        "second"=>1, 
        "msg"=>"安装前准备..."
    );
    
    $release ="";
    $charset = str_replace('-', '', strtoupper($_G['config']['output']['charset']));
    $locale = ''; 
    if ($charset == 'BIG5') {
        $locale = 'TC';
    } elseif ($charset == 'GBK') {
        $locale = 'SC';
    } elseif ($charset == 'UTF8') {
        if ($_G['config']['output']['language'] == 'zh-cn' || $_G['config']['output']['language'] == 'zh_cn') {
            $locale = 'SC';
        } elseif ($_G['config']['output']['language'] == 'zh-tw' || $_G['config']['output']['language'] == 'zh_tw') {
            $locale = 'TC';
        }else{
            $locale = 'SC';
        }
    }
     
    $dbversion = helper_dbtool::dbversion();
    //判断是否升级mysql 或者php 
    $charset='UTF8';
    if(version_compare($baseinfo['phpversion'], PHP_VERSION) > 0 || version_compare($baseinfo['mysqlversion'], $dbversion) > 0) {
        $return["status"]=0;
    }

    $linkurl = ADMINSCRIPT . '?mod=appmarket&op=install_app_ajax&operation=patch&locale=' . $locale . '&charset=' . $charset;
    $return["url"]=$linkurl;
    exit(json_encode($return)); 
}
elseif($operation == 'cross' || $operation == 'patch'){ 
    $baseinfo = $_GET["baseinfo"]; 
    $baseinfo = base64_decode($baseinfo);
    $baseinfo = unserialize($baseinfo);
    $baseinfo["latestversion"] = $baseinfo["version"];
    $baseinfo["upgradeinfo"]=$baseinfo;
    $appinfo =$baseinfo;
    
    $return=array(
        "url"=>ADMINSCRIPT .'?mod=appmarket&op=install_app_ajax',
        "status"=>1,
        "percent"=>10,
        "second"=>1,
        "step"=>0,
        "msg"=>"应用文件列表获取中..."
    );
    
    if (0 && !$_G['setting']['bbclosed']) {//应用升级暂时可不关闭站点
        $msg = '<p style="margin:10px 0;color:red">' . lang('upgrade_close_site') . '</p>';
        $msg .= '<p style="margin:10px 0"><input type="button" class="btn btn-primary" onclick="window.location.reload();" value="' . lang('founder_upgrade_reset') . '" /></p>';
        $msg .= "<p style=\"margin:10px 0\"><script type=\"text/javascript\">";
        $msg .= "if(history.length > (BROWSER.ie ? 0 : 1)) document.write('<a href=\"javascript:history.go(-1);\" >" . lang('message_return') . "</a>');";
        $msg .= "</script></p>";
        $return["status"]=0;
        $return["msg"]=lang('upgrade_close_site') ;
        exit(json_encode($return));
        exit();
    }
    
    $step = intval($_REQUEST['step']);
    $step = $step ? $step : 1;
     
    $release = trim($_GET['release']);
    $locale = trim($_GET['locale']);
    $charset = trim($_GET['charset']);
    
    /*$upgradeinfo = $upgrade_step = array();
    
    $appid = $_GET["appid"]; 
    $appinfo = C::tp_t('app_market')->find( $appid );
    
    $upgrade_version = unserialize($appinfo["upgrade_version"]);
    $upgradeinfo = $upgrade_version[$operation]; 
    $appinfo["upgradeinfo"]=$upgradeinfo;*/
    
    $dzz_upgrade = new dzz_upgrade_app();
    if($step != 5) {
        $updatefilelist = $dzz_upgrade->fetch_installfile_list( $baseinfo );
         
        if(empty($updatefilelist)) {
            $return["status"]=0;
            $return["msg"]=  lang('app_upgrade_none', array('upgradeurl' => upgradeinformation_app(-1))); 
            exit(json_encode($return));  
        } 
        $updatemd5filelist = $updatefilelist['md5'];
        $updatefilelist = $updatefilelist['file']; 
        $theurl = ADMINSCRIPT . '?mod=appmarket&op=install_app_ajax&operation=' . $operation .'&locale=' . $locale . '&charset=' . $charset; 
    }
    
    if($step == 1) {
        $linkurl=$theurl. '&step=2';
        $updatefilelist=$updatefilelist ;
        $return["percent"]=15;
        $return["second"]=1;
        $return["url"]=$linkurl; 
        $return["msg"]="应用文件即将下载...";
        $return["step"]=2;
        exit(json_encode($return));  
        exit;
    }
     
    elseif($step == 2) {
        $return["msg"]=lang("app_upgrade_downloading");
        $return["step"]=2;
        $percent = 60;
        
        $fileseq = intval($_GET['fileseq']);
        $fileseq = $fileseq ? $fileseq : 1;
        //$position = intval($_GET['position']);
        //$position = $position ? $position : 0;
        //$offset = 1000 * 1024;
        
        if($fileseq > count($updatefilelist)) {
            $linkurl = $theurl.'&step=3';
            $percent = 100;
            $return["step"]=3; 
            $return["msg"]= lang('app_upgrade_download_complete', array('upgradeurl' => upgradeinformation_app(6)));  
        } else {
            $downloadstatus = $dzz_upgrade->download_file($baseinfo, $updatefilelist[$fileseq-1], '', $updatemd5filelist[$fileseq-1]);//, $position, $offset);
            if($downloadstatus == 1) {
                $linkurl = $theurl.'&step=2&fileseq='.$fileseq.'&position='.($position+$offset);
                $percent = 60+sprintf("%2d", 40 * $fileseq/count($updatefilelist));
                $file =  $updatefilelist[$fileseq-1]; 
            } elseif($downloadstatus == 2) {
                $linkurl = $theurl.'&step=2&fileseq='.($fileseq+1);
                $percent = 60+sprintf("%2d", 40 * $fileseq/count($updatefilelist));
                $file =  $updatefilelist[$fileseq-1]; 
            } else { 
                $return["status"]=0; 
                $return["msg"]= lang('app_upgrade_downloading_error', array('file' => $updatefilelist[$fileseq-1],  'upgradeurl'=>upgradeinformation_app(-3) )) ;
                exit(json_encode($return));  
                exit;
            }
            $msg = lang('upgrade_downloading_file', array('file' => $updatefilelist[$fileseq - 1], 'percent' =>$percent. '%' ,'upgradeurl'=>'')) ;
        } 
        $stepover= 1; 
        $return["url"]=$linkurl; 
        $return["percent"]=intval(50*$percent/100);
        $return["second"]=1;
        exit(json_encode($return));  
        exit; 
    }
    elseif($step == 3) {
        $return["percent"]=55;
        $return["second"]=1; 
        $return["msg"]= lang("app_upgrade_check_download_complete");
        $return["step"]=3;
        //此处应检查验证是否下载完毕所有更新文件
         
        $linkurl = $theurl.'&step=4';
        $return["url"]=$linkurl;
        exit(json_encode($return));  
        exit; 
    }
    elseif($step==4){
        $return["percent"]=80;
        $return["second"]=1; 
        $return["msg"]= lang("app_upgrade_installing");
        $return["step"]=4;
        
        $confirm = $_GET['confirm'];
        if (!$confirm) {
            $checkupdatefilelist = $updatefilelist; 
            if ($dzz_upgrade -> check_folder_perm($baseinfo,$checkupdatefilelist)) {
                $confirm = 'file';
            } else {
                $linkurl = $theurl . '&step=4';
                $return["status"]=0;
                $return["percent"]=55;
                $return["second"]=0;
                $return["msg"]=lang('app_upgrade_cannot_access_file', array('upgradeurl' => upgradeinformation_app(-4))); 
                $return["step"]=1;
                exit(json_encode($return));  
            } 
        } 
        $paraftp = '';
        if ($_GET['siteftp']) {
            foreach ($_GET['siteftp'] as $k => $v) {
                $paraftp .= '&siteftp[' . $k . ']=' . $v;
            }
        } 
        
        if(!$_GET['fileupgrade']) {
            foreach ($updatefilelist as $updatefile) {
                $srcfile = DZZ_ROOT . 'data/update/app/'.$baseinfo["app_path"].'/'.$baseinfo["identifier"].'/'.$baseinfo['version'].'/'.$updatefile;
                if ($confirm == 'ftp') {//待测试
                    $destfile = $updatefile;
                } else { 
                    $destfile = DZZ_ROOT .$baseinfo['app_path'].'/' . $baseinfo['identifier'].'/'.$updatefile;
                    if( isset( $baseinfo["new_identifier"])   &&  $baseinfo["new_identifier"] ){
                        $destfile = DZZ_ROOT .$baseinfo['app_path'].'/' . $baseinfo['new_identifier'].'/'.$updatefile;
                    }
                }
                if (!$dzz_upgrade -> copy_file($srcfile, $destfile, $confirm)) {
                    if ($confirm == 'ftp') {
                        $return["status"]=0;  
                        $return["msg"]= lang('app_upgrade_ftp_upload_error', array('file'=>$updatefile,'upgradeurl' => upgradeinformation_app(-6))); 
                        exit(json_encode($return));
                    } else { 
                        $return["status"]=0;
                        $return["file"]=array($srcfile,$destfile);
                        $return["msg"]=lang('app_upgrade_copy_error', array('file'=>$updatefile,'upgradeurl' => upgradeinformation_app(-7))); 
                        exit(json_encode($return));  
                    }
                }
            }
            //移动md5文件
            $srcfile = DZZ_ROOT . 'data/update/app/'.$baseinfo["app_path"].'/'.$baseinfo['identifier'].'/'.$baseinfo['version'].'/'.$baseinfo['identifier'].'.md5.tmp';
            if ($confirm == 'ftp') {
                $destfile = './'.$baseinfo['identifier'].'.md5';
            } else {
                $destfile = DZZ_ROOT .$baseinfo['app_path'].'/' . $baseinfo['identifier'].'/'.$baseinfo['identifier'].'.md5';
                if( isset( $baseinfo["new_identifier"])   &&  $baseinfo["new_identifier"] ){
                    $destfile = DZZ_ROOT .$baseinfo['app_path'].'/' . $baseinfo['new_identifier'].'/'.$baseinfo['new_identifier'].'.md5';
                }
            }
            if (!$dzz_upgrade -> copy_file($srcfile, $destfile, $confirm)) {
                if ($confirm == 'ftp') {
                    $return["status"]=0;  
                    $return["msg"]= lang('app_upgrade_copy_error', array('file'=>$baseinfo['identifier'].'.md5.tmp','upgradeurl' => upgradeinformation_app(-6))); 
                    exit(json_encode($return));   

                } else { 
                    $return["status"]=0;  
                    $return["msg"]=lang('app_upgrade_copy_error', array('file'=>$baseinfo['identifier'].'.md5.tmp','upgradeurl' => upgradeinformation_app(-7))); 
                    exit(json_encode($return));  
                }
            }
             
            $return["url"]= $theurl . '&step=4&fileupgrade=1&dodabase=1&confirm=' . $confirm;
            $return["percent"]=75;
            $return["second"]=1;
            $return["msg"]= lang('app_upgrade_move_success', array( 'upgradeurl' => upgradeinformation_app(4))); 
            exit(json_encode($return));  
            exit(); 
        }
        
        if($_GET['dodabase']){
            $finish = FALSE;
            $dir = $baseinfo['app_path'];
            $appname = $baseinfo['identifier']; 
            if( isset( $baseinfo["new_identifier"])   &&  $baseinfo["new_identifier"] ){
                $appname = $baseinfo['new_identifier'];
            } 
            $xmlfile = 'dzz_app_' . $appname . '.xml';
            $importfile = DZZ_ROOT . './'.$dir.'/' . $appname . '/' . $xmlfile;
            
            if (!file_exists($importfile)) {
                $importfile2 = DZZ_ROOT . './'.$dir.'/' . $appname . '/dzz_app_' . $baseinfo['identifier'] . '.xml';
                if(!file_exists($importfile2)){
                    $return["status"]=0;  
                    $return["msg"]=lang("app_upgrade_xmlfile_error" ,array('file'=>$xmlfile,'upgradeurl' => upgradeinformation_app(-8))); 
                    exit(json_encode($return));
                }else{
                    @rename($importfile2,$importfile);
                }
            } 
            $importtxt = @implode('', file($importfile)); 
            $apparray = getimportdata('Dzz! app');
            $filename = $apparray['app']['extra']['installfile']; 
            if (!empty($filename) && preg_match('/^[\w\.]+$/', $filename)) {
                $filename = DZZ_ROOT . './'.$dir.'/' . $appname . '/' . $filename;
                if (file_exists($filename)) {
                    @include_once $filename;
                } else {
                    $finish = TRUE;
                }
            } else {
                $finish = TRUE;
            }
            if ($finish) {
                //安装时保存云端mid
                $apparray["app"]["mid"]=$baseinfo["mid"];
                //保存对应的应用名及应用地址
                if( isset( $baseinfo["new_identifier"])   &&  $baseinfo["new_identifier"] ){ 
                    $apparray['app']['identifier']=$baseinfo['new_identifier'];
                    $apparray['app']['appurl']= str_replace("mod=".$baseinfo['identifier'],"mod=".$baseinfo['new_identifier'],$apparray['app']['appurl']);
                    $apparray['app']['appadminurl']= str_replace("mod=".$baseinfo['identifier'],"mod=".$baseinfo['new_identifier'],$apparray['app']['appadminurl']);
                    $apparray['app']['noticeurl']= str_replace("mod=".$baseinfo['identifier'],"mod=".$baseinfo['new_identifier'],$apparray['app']['noticeurl']);
                    $apparray['app']['identifier']=  $baseinfo['new_identifier'];
                }
                
                if ($app = importByarray($apparray, 1)) {
                    cron_create( $app );
                }
                writelog('otherlog', "安装应用 ".$apparray['app']['appname']); 
            } 
           
            $linkurl = ADMINSCRIPT . '?mod=appmarket&op=install_app_ajax&operation=' . $operation .'&step=5';
            $return["url"]=$linkurl;
            $return["percent"]=80;
            $return["second"]=1;
            $return["step"]=5;
            $return["msg"]= lang("app_upgrade_install_will_success"); 
            exit(json_encode($return));
            exit;
        }
        
        $linkurl = ADMINSCRIPT . '?mod=appmarket&op=install_app_ajax&operation=' . $operation . '&appid=' .$appid. '&step=5';
        $return["url"]=$linkurl;
        $return["percent"]=80;
        $return["second"]=1;
        $return["step"]=5;
        $return["msg"]=lang("app_upgrade_install_will_success"); 
        exit(json_encode($return));
        exit;
    }
    elseif($step==5){ 
        //判断如果是网址类型删除对应目录
        if( $baseinfo["atype"]==1 && $baseinfo['app_path'] && $baseinfo['app_path']=="link"){ //防止删除整个目录直接判断=link
            $dzz_upgrade->rmdirs( DZZ_ROOT .$baseinfo['app_path']."/" );
        }
        updatecache('setting'); 
         
        $return["url"] = ADMINSCRIPT . '?mod=appmarket&op=install_app_ajax&operation=check_install';
        $return["percent"]=100;
        $return["second"]=1;
        $return["step"]=5;
        $return["msg"]= lang("app_upgrade_install_success" ,array( 'upgradeurl' => upgradeinformation_app(1))); 
        exit(json_encode($return)); 
    }
}
?>