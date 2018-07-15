<?php
namespace app\teacher\controller;
use app\teacher\controller\Base;
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
		$User = model('Teachers');
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
		if($info['is_job'] == 2){
			return $this->err("您已离职，如有疑问，请联系园长！");
		}
		//所在班级，如未分班，不允许登录
		$classes_id = Db::name('TeacherClass')->where('teacher_id',$info['id'])->where('teacher_type',1)->where('flag',1)->value('classes_id');
        $classes_idr = Db::name('TeacherClass')->where('teacher_id',$info['id'])->where('teacher_type',3)->where('flag',1)->value('classes_id');
		if(empty($classes_id) && empty($classes_idr)){
			return $this->err("您没有被分配为班主任或者任课老师，请联系园长！");
		}else{
		    if(empty($classes_id)){
		        $classes_id = $classes_idr;
            }
        }
        //获取班级id
		$classInfo = Db::view('Classes','name')
            ->view('Subtype','subtype_name','Classes.cats_code = Subtype.subtype_code')
            ->view('Type','id as tid','Type.id = Subtype.parent_id')
            ->where('Classes.id',$classes_id)
            ->where('Type.type_name',10002)
            ->where('Type.reserve',$info['school_id'])
            ->find();
		//session管理
		$LoginSession = model('LoginSession');
		$sessionData['user_id'] = $info["id"];
		$sessionId = strtolower(md5(md5(microtime())));
		$sessionData['session_id'] = $sessionId;
		$sessionData['overdue_time'] = time()+(60*24*3600);//60天后过期
		$sessionData['create_time'] = time();
		$sessionData['type'] = 2;
		$is_ex = $LoginSession->where('user_id',$info["id"])->where('type',2)->count();
		if($is_ex > 0){
			$LoginSession->isUpdate(true)->save($sessionData,['user_id'=>$info["id"],'type'=>2]);
		}else{
			$LoginSession->isUpdate(false)->save($sessionData);
		}
		//记录日志
		Db::name('LoginLog')->insert(['user_id'=>$info['id'],'login_time'=>time(),'login_ip'=>request()->ip(),'type'=>2]);
		//准备返回数据
		$backData['sessionId'] = $sessionId;
		$backData['userId'] =  $info['id'];
		$backData['tel'] =  $info['tel'];
		$backData['realname'] =  $info['realname'];
		$backData['schoolId'] =  $info['school_id'];
		$backData['photo'] =  $info['photo'];
		$backData['jobNum'] =  $info['job_num'];//工号
		$backData['className'] = $classInfo['name'];
		$backData['classType'] = $classInfo['subtype_name'];
		$backData['schoolName'] = Db::name('Schools')->where('id',$info['school_id'])->value('name');
		$backData['leaderTel '] = Db::name('Headmasters')->where('school_id',$info['school_id'])->where('flag',1)->value('tel');
		$User->where($where)->setField('jpush_id',$param['jpushId']);//设置极光推送ID
		return $this->suc($backData,'',config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 退出
	 */
	public function logout(){
		$LoginSession = model('LoginSession');$User = model('Teachers');
		$param = $this->param;
		if(!empty($param['userId']) && !empty($param['sessionId'])){
			$where['session_id'] = $param['sessionId'];
			$where['user_id'] = $param['userId'];
			$where['type'] = 2;
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
		$User = model('Teachers');
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
}