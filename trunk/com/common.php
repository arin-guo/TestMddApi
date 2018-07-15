<?php 

use think\Cache;
use think\Db;
//设置报错级别
error_reporting(E_ERROR);
/**
 * 获取自定义字段
 */
function getFields($field){
	if(Cache::get('c_field_'.$field)){
		$value = Cache::get('c_field_'.$field);
	}else{
		$where['flag'] = 1;
		$where['field'] = $field;
		$value = Db::name('fields')->where($where)->value('value');
		Cache::set('c_field_'.$field, $value,3600);
	}
	return $value;
}

/**
 * 格式化金钱
 * @param unknown $price
 */
function formatPrice($price){
	return sprintf("%.2f", $price);
}

/**
 * 写入日志
 * Enter description here ...
 * @param unknown_type $title
 * @param unknown_type $msg
 */
function writeLog($msg){
	$path = "./log/".date('Ym')."/";
	if(!file_exists($path)){
		//检查是否有该文件夹，如果没有就创建，并给予最高权限
		mkdir($path, 0777, true);
	}
	$file = fopen($path.Date('d').".log", "a+");
	$msg .= "\r\n";
	fwrite($file, $msg);
	fclose($file);
}

/**
 * 隐藏手机号中间四位
 */
function getHidePhone($tel){
	return substr($tel,0,3)." *** ".substr($tel,-4);
}

function create_uuid($prefix = ""){    //可以指定前缀
	$str = md5(uniqid(mt_rand(), true));
	$uuid  = substr($str,0,8) . '-';
	$uuid .= substr($str,8,4) . '-';
	$uuid .= substr($str,12,4) . '-';
	$uuid .= substr($str,16,4) . '-';
	$uuid .= substr($str,20,12);
	return $prefix . $uuid;
}


/**
 * 模拟post进行url请求
 * @param string $url
 * @param string $param
 */
function request_post($url = '', $param = array()) {
	if (empty($url) || empty($param)) {
		return false;
	}
	$o = "";
	foreach ( $param as $k => $v ){
		$o.= "$k=" . $v . "&" ;
	}
	$param = substr($o,0,-1);
	$postUrl = $url;
	$curlPost = $param;
	$ch = curl_init();//初始化curl
	//设置请求头
	curl_setopt($ch, CURLOPT_URL,$postUrl);//抓取指定网页
	curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
	curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
	curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
	$data = curl_exec($ch);//运行curl
	curl_close($ch);
	return $data;
}

/**
 * 短信息发送接口（相同内容群发，可自定义流水号）
 * @param unknown $to 接收手机号，多个号码间以逗号分隔且最大不超过1000个号码
 * @param unknown $text 发送内容,标准内容不能超过70个汉字
 */
function sendMsssage($to,$text){
	$url = 'http://api01.monyun.cn:7901/sms/v2/std/single_send';
	if(empty(config('app_send_msg_apikey'))){
		return false;
	}
	$postData['apikey'] = config('app_send_msg_apikey');//账号
	$postData['mobile'] = $to;
	$postData['content'] = urlencode(iconv("UTF-8","GBK",$text));//转gbk明文
	$res = request_post($url, $postData);
	if($res.result == 0){
		return true;
	}else{
		return false;
	}
}

/**
 * 推送给个人
 * $isTs = 是否透析消息，透析消息无通知栏，直接透析给app解析
 */
function jpushToId($regId,$message,$type = 1,$extras = "",$isTs = false){
	$result = [];
	if(empty($regId) || empty($message)){
		return $result;
	}
	$regId = explode(',', $regId);
	switch ($type){
		case 1://家长端
			$jpush_app_key = config('jpush_app_key');$jpush_app_secret = config('jpush_app_secret');
			break;
		case 2://教师端
			$jpush_app_key = config('jpush_teacher_key');$jpush_app_secret = config('jpush_teacher_secret');
			break;
		case 3://园长端
			$jpush_app_key = config('jpush_leader_key');$jpush_app_secret = config('jpush_leader_secret');
			break;
	}
	$client = new \JPush\Client($jpush_app_key,$jpush_app_secret);
	$ios_notification = array(
					'sound' => 'sound.caf',
					'badge' => '+1',
	);
	$android_notification = array(
			'title' => '萌点点智慧幼教'
	);
	if(!empty($extras)){
		$ios_notification = array(
				'sound' => 'sound.caf',
				'badge' => '+1',
				'content-available'=>true,//推送唤醒
                //'mutable-content'=>true,//通知扩展
				'extras' => $extras
		);
		$android_notification = array(
				'title' => '萌点点智慧幼教',
				'extras' => $extras
		);
	}
	//推送开始
	$push = $client->push()->setPlatform('all')->addRegistrationId($regId);
	if($isTs){//透析消息
		$push= $push->message($message,[
					  'title' => '',
					  'content_type' => '',
					  'extras' => $extras
					]
			);
	}else{
		$push= $push->setNotificationAlert($message)
			->iosNotification($message, $ios_notification)
			->androidNotification($message, $android_notification);
	}
	$result = $push->options(array('apns_production'=>config('app_jpush_ios_apns_production')))//false为开发坏境，true为正式环境
			->send();
	return $result;
}

/**
 * 全体推送
 */
function jpushToAll($message,$type){
	$result = [];
	if(empty($message)){
		return $result;
	}
	switch ($type){
		case 1://家长端
			$jpush_app_key = config('jpush_app_key');$jpush_app_secret = config('jpush_app_secret');
			break;
		case 2://教师端
			$jpush_app_key = config('jpush_teacher_key');$jpush_app_secret = config('jpush_teacher_secret');
			break;
		case 3://园长端
			$jpush_app_key = config('jpush_leader_key');$jpush_app_secret = config('jpush_leader_secret');
			break;
	}
	$client = new \JPush\Client($jpush_app_key,$jpush_app_secret);
	$cid = $client->push()->getCid();
	$result = $client->push()->setCid($cid)
				->setPlatform('all')->setAudience('all')
				->setNotificationAlert($message)
				->iosNotification($message, array(
						'sound' => 'sound.caf',
						'badge' => '+1'
						// 'content-available' => true,
						// 'mutable-content' => true,
				))
				->androidNotification($message, array(
						'title' => '萌点点智慧幼教'
				))
				->options(array('apns_production'=>config('app_jpush_ios_apns_production')))//false为开发坏境，true为正式环境
				->send();
	return $result;
}

/**
 * 获取两个日期之间的所有日期
 * @param unknown $start
 * @param unknown $end
 * @return multitype:string
 */
function getCompareDateList($start,$end){
	$dt_start = strtotime($start);
	$dt_end = strtotime($end);
	$bak = [];$i = 0;
	while ($dt_start<=$dt_end){
		$bak[$i] = date('Y-m-d',$dt_start);
		$dt_start = strtotime('+1 day',$dt_start);
		$i++;
	}
	return $bak;
}

/**
 * 获取某个月的所有日期
 * @param string $month
 * @param string $format
 * @return multitype:string
 */
function getMonthDays($month = "this month", $format = "Y-m-d") {
	$start = strtotime("first day of $month");
	$end = strtotime("last day of $month");
	$days = array();
	for($i=$start;$i<=$end;$i+=24*3600) $days[] = date($format, $i);
	return $days;
}

/**
 * [移动端访问自动切换主题模板]
 * @return boolen [是否为手机访问]
 */
function ismobile() {
	// 如果有HTTP_X_WAP_PROFILE则一定是移动设备
	if (isset ($_SERVER['HTTP_X_WAP_PROFILE']))
		return true;

	//此条摘自TPM智能切换模板引擎，判断是否为客户端
	if(isset ($_SERVER['HTTP_CLIENT']) &&'PhoneClient'==$_SERVER['HTTP_CLIENT'])
		return true;
	//如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
	if (isset ($_SERVER['HTTP_VIA']))
		//找不到为flase,否则为true
		return stristr($_SERVER['HTTP_VIA'], 'wap') ? true : false;
	//判断手机发送的客户端标志,兼容性有待提高
	if (isset ($_SERVER['HTTP_USER_AGENT'])) {
		$clientkeywords = array(
				'nokia','sony','ericsson','mot','samsung','htc','sgh','lg','sharp','sie-','philips','panasonic','alcatel','lenovo','iphone','ipod','blackberry','meizu','android','netfront','symbian','ucweb','windowsce','palm','operamini','operamobi','openwave','nexusone','cldc','midp','wap','mobile'
		);
		//从HTTP_USER_AGENT中查找手机浏览器的关键字
		if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
			return true;
		}
	}
	//协议法，因为有可能不准确，放到最后判断
	if (isset ($_SERVER['HTTP_ACCEPT'])) {
		// 如果只支持wml并且不支持html那一定是移动设备
		// 如果支持wml和html但是wml在html之前则是移动设备
		if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
			return true;
		}
	}
	return false;
}

/* 
*$fFiles:文件域 
*$n：上传的路径目录 
*$ossClient 
*$bucketName 
*$web:oss访问地址 
*$isThumb:是否缩略图 
*/
function ossUpPic($fFiles,$n,$ossClient,$bucketName,$web,$isThumb=0/*,$content=''*/){ 
    $fType = $fFiles['type'];
    $back = array( 
        'code'=>0, 
        'msg'=>'', 
    ); 
    if(!in_array($fType, config('oss_exts'))){ 
        $back['msg'] = '文件格式不正确'; 
        return $back; 
        exit; 
    } 
    $fSize = $fFiles['size']; 
    if($fSize > config('oss_maxSize')){ 
        $back['msg'] = '文件超过了50M'; 
        return $back; 
        exit; 
    } 
     
    $fname = $fFiles['name']; 
    $ext = substr($fname,stripos($fname,'.')); 
     
    $fup_n = $fup_bak = $fFiles['tmp_name']; 
    /*if($content != ''){
        $image = new \Think\Image();
        $image->open($fup_n)->thumb(800, 600)->save("./thumb.png");
        $Qrcode = getQrcode($content);
        $image->open("./ground.png")->water($Qrcode, \Think\Image::IMAGE_WATER_CENTER, 100 )->save("./QRcode.png"); 
        $data = $image->open("./thumb.png")->water("./QRcode.png", \Think\Image::IMAGE_WATER_SOUTHEAST, 70 )->save("./water.png");
        $fup_n = "./water.png";
        // exit();
    }*/

    $file_n = time() . '_' . rand(100,999); 
    $object = $n . "/" . $file_n . $ext;//目标文件名 
    $object_bak = $n . "/" . $file_n . '_bak' . $ext;//目标文件名

    if (is_null($ossClient)) exit(1);     
    $ossClient->uploadFile($bucketName, $object, $fup_n);
    /*if($content != ''){
        $ossClient->uploadFile($bucketName, $object_bak, $fup_bak);
        $back['bak'] = 'http://' . $web . '/' . $object_bak; 
    }*/
    if($isThumb == 1){ 
        // 图片缩放，参考https://help.aliyun.com/document_detail/44688.html?spm=5176.doc32174.6.481.RScf0S  
        $back['thumb'] = 'http://' . $web . '/' . $object . "?x-oss-process=image/resize,h_300,w_300"; 
    }     
    $back['code'] = 1; 
    $back['msg'] = 'http://' . $web . '/' . $object;
    return $back; 
    exit;     
}
/*
*$fFiles:文件域
*$n：上传的路径目录
*$ossClient
*$bucketName
*$web:oss访问地址
*$isThumb:是否缩略图
*/
function ossUpLog($fFiles,$n,$ossClient,$bucketName,$web,$isThumb=0){
    $back = array(
        'code'=>0,
        'msg'=>'',
    );
    $fSize = $fFiles['size'];
    if($fSize > config('oss_maxSize')){
        $back['msg'] = '文件超过了50M';
        return $back;
        exit;
    }
    $fname = $fFiles['name'];
    $fup_n = $fFiles['tmp_name'];
    $object = $n."/".$fname;//目标文件名
    if (is_null($ossClient)) exit(1);
    $ossClient->uploadFile($bucketName, $object, $fup_n);
    $back['code'] = 1;
    $back['msg'] = 'http://' . $web . '/' . $object;
    return $back;
    exit;
}
?>