<?php
namespace app\index\controller;
use think\Db;
use think\Controller;
/**
 * 首页
 * @author chenlisong E-mail:chenlisong1021@163.com
 * @version 创建时间：2017年6月22日 下午2:01:58
 * 类说明
 */
class Index extends Controller{
	
	/**
	 * 首页
	 */
    public function index(){
//     	$extras['code'] = 10000;
//     	$extras['name'] = "测试";
//     	$reback = jpushToId("13065ffa4e0d707ffce","我是透析消息",2,$extras);
    	return $reback;
    	
    }
}
 