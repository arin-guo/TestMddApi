<?php
use think\Request;
return [
		// 视图输出字符串内容替换
		'view_replace_str'       => [
				'__ROOT__' => '/',
				'__ADMROOT__' => 'http://test.admin.mengdd.net/',
				'__IMGROOT__' => 'http://test.upload.mengdd.net/',//'http://upload.mdd.chenls.me/',//Request::instance()->domain(),
		],
		//极光推送
		'jpush_app_key'=>'170c723622564c570d996cdf',
		'jpush_app_secret'=>'f77a4487b0cc823a18509c2b',
		//教师端极光推送
		'jpush_teacher_key'=>'edf94df96ae30f511d72e686',
		'jpush_teacher_secret'=>'f6fe12337588012f62f0ed09',
		//园长端极光推送
		'jpush_leader_key'=>'03eedf4c2edce01e842cb9db',
		'jpush_leader_secret'=>'787f6e76f59e832c70842052',
		//短信账号
		'app_sendmsg_key'=>'396e8fb2e774b3a77b37c25546ab7f58',
		'app_sendmsg_secret'=>'eeace1133c86',
		//心知天气
		'app_xinzhi_weather_key'=>'fd8piufqlaybvmkk',
		//上传图片的路径
		'app_upload_path' => '/var/www/mdd_test',//'/var/www/mdd',
		'app_jpush_ios_apns_production'=>false, //极光推送IOS环境，false为开发坏境，true为正式环境

		//oss配置 
		"OSS_ACCESS_ID" => 'LTAIM3xzDY9pnkID', 
		"OSS_ACCESS_KEY"=> 'vqJVMWz4nzuSdORdMVSRGppEmtNf6E', 
		"OSS_ENDPOINT" => 'oss-cn-hangzhou.aliyuncs.com', 
		"OSS_TEST_BUCKET" => 'hseye', 
		"OSS_WEB_SITE" =>'hseye.oss-cn-hangzhou.aliyuncs.com', 

		//oss文件上传配置 
		'oss_maxSize'=> 134217728, //128M
		'oss_exts' =>[ 
			'image/jpg', 
			'image/gif', 
			'image/png', 
			'image/jpeg', 
			'application/octet-stream',
			'application/msword',
			'application/vnd.ms-excel',
			'application/pdf',
			'video/mp4',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'audio/mpeg',
			'audio/x-wav',
            'text/plain'
		],
];