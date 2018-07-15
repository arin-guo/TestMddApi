<?php
namespace app\parents\controller;
use sendsms\Sendsms;

class Index
{
    public function index()
    {
//     	$sendSms = new Sendsms(config('app_sendmsg_key'), config('app_sendmsg_secret'));
//     	$result = $sendSms->sendSMSTemplate('3143235',array('18868192479'),array('1234'));
//     	dump($result);
    	return 'hi..';
    }
}
