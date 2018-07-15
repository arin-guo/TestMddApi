<?php
namespace app\leader\controller;
use app\leader\controller\Base;
use think\Db;
/**
 * 登录注册
 * @author chenlisong E-mail:chenlisong1021@163.com 
 * @version 创建时间：2017年8月6日 上午10:09:32 
 * 类说明
 */
class Login extends Base{
	
	public function index(){
		return $this->err("非法操作！");
	}
	
	/**
	 * 登录接口
	 */
	public function login(){
		$User = model('Headmasters');
		$param = $this->param;
		if(empty($param['password']) || empty($param['tel'])){
			return $this->err("参数错误！");
		}
		$where['tel'] = $param['tel'];
		$where['flag'] = 1;
		$info = $User->where($where)->find();
		if(count($info) == 0){
			return $this->err("未找到该用户！");
		}
		if($info['password'] != md5($param['password'])){
			return $this->err("用户名或密码不正确！");
		}
		if($info['is_lock'] == 2){
			return $this->err("该账户已被锁定，请联系管理员！");
		}
		//session管理
		$LoginSession = model('LoginSession');
		$sessionData['user_id'] = $info["id"];
		$sessionId = strtolower(md5(md5(microtime())));
		$sessionData['session_id'] = $sessionId;
		$sessionData['overdue_time'] = time()+(60*24*3600);//60天后过期
		$sessionData['create_time'] = time();
		$sessionData['type'] = 3;
		$is_ex = $LoginSession->where('user_id',$info["id"])->where('type',3)->count();
		if($is_ex > 0){
			$LoginSession->isUpdate(true)->save($sessionData,['user_id'=>$info["id"],'type'=>3]);
		}else{
			$LoginSession->isUpdate(false)->save($sessionData);
		}
		//记录日志
		Db::name('LoginLog')->insert(['user_id'=>$info['id'],'login_time'=>time(),'login_ip'=>request()->ip(),'type'=>3]);
		//准备返回数据
		$backData['inviteCode'] = model('Schools')->where('id',$info['school_id'])->find()['unique_code'];/*ji add*/
		$backData['inviteSwitch'] = model('Schools')->where('id',$info['school_id'])->find()['is_open'];
		$backData['sessionId'] = $sessionId;
		$backData['userId'] =  $info['id'];
		$backData['tel'] =  $info['tel'];
		$backData['realname'] =  $info['realname'];
		$backData['schoolId'] =  $info['school_id'];
		$backData['photo'] =  $info['photo'];
		$backData['sex'] =  $info['sex'];
		$backData['signature'] =  $info['signature'];
		$backData['approvalSwitch'] =  $info['approval_switch'];
		$backData['systemSwitch'] =  $info['system_switch'];
		$backData['chatSwitch'] =  $info['chat_switch'];
		$backData['disturbSwitch'] =  $info['disturb_switch'];
		$backData['schoolName'] = Db::name('Schools')->where('id',$info['school_id'])->value('name');
		$backData['schoolUniqueCode'] = Db::name('Schools')->where('id',$info['school_id'])->value('unique_code');
		$backData['schoolIsDevice'] = Db::name('Schools')->where('id',$info['school_id'])->value('is_device');
		$User->where($where)->setField('jpush_id',$param['jpushId']);//设置极光推送ID
		return $this->suc($backData,'',config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 退出
	 */
	public function logout(){
		$LoginSession = model('LoginSession');$User = model('Headmasters');
		$param = $this->param;
		if(!empty($param['userId']) && !empty($param['sessionId'])){
			$where['session_id'] = $param['sessionId'];
			$where['user_id'] = $param['userId'];
			$where['type'] = 3;
			$LoginSession->where($where)->delete();
			//清空极光推送ID
			$User->where('id',$param['userId'])->setField('jpush_id','');
		}
		return $this->suc();
	}
	
	/**
	 * 找回密码
	 */
	public function findPwd(){
		$User = model('Headmasters');
		$param = $this->param;
		if(!preg_match("/1[34578]{1}\d{9}$/",$param['tel'])){
			return $this->err("错误的手机格式！");
		}
		if(!preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,12}$/', $param['password'])){
			return $this->err("请输入6-12位数字和英文组合的密码！");
		}
		$send = cache('app_send_msg_'.$param['tel']);
		if(empty($param['sendCode'])){
			return $this->err('验证码为空！');
		}
		if($param['tel'] != $send['send_tel']){
			return $this->err("验证码失效！");
		}
		if($param['sendCode'] != $send['send_code']){
			return $this->err("验证码错误！");
		}
		$where['flag'] = 1;
		$where['tel'] = $param['tel'];
		$info = $User->where($where)->find();
		if(empty($info)){
			return $this->err("未找到该用户！");
		}
		$data['password'] = md5($param['password']);
		$data['update_time'] = time();
		$result = $User->save($data,['tel'=>$param['tel'],'flag'=>1]);
		if($result){
			cache("app_send_msg_".$param['tel'],null);
			return $this->suc();
		}else {
			return $this->err("系统错误！");
		}
	}
	
	/**
	 * 验证密码
	 */
	public function checkPassword(){
		$User = model('Headmasters');
		$param = $this->param;
		if(empty($param['password']) || empty($param['tel'])){
			return $this->err("参数错误！");
		}
		$where['tel'] = $param['tel'];
		$where['flag'] = 1;
		$where['password'] = md5($param['password']);
		$info = $User->where($where)->find();
		if(count($info) != 0){
			return $this->suc();
		}else{
			return $this->err("验证失败！");
		}
	}
}