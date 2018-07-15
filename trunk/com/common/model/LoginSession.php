<?php
namespace app\common\model;
use app\common\model\Base;
/**
 * session管理
 * @author chenlisong E-mail:chenlisong1021@163.com 
 * @version 创建时间：2017年8月7日 上午9:54:38 
 * 类说明
 */
class LoginSession extends Base{
	//自动写入时间戳
	protected $autoWriteTimestamp = false;
}