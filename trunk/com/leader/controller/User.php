<?php
namespace app\leader\controller;
use app\leader\controller\Base;
use think\Db;
use think\Validate;
/**
 * 用户中心
 * @author chenlisong E-mail:chenlisong1021@163.com 
 * @version 创建时间：2017年8月7日 上午10:08:47 
 * 类说明
 */
class User extends Base{
	
	public function index(){
		return $this->err('非法访问！');
	}
	
	/**
	 * 修改登录密码
	 * 方法
	 */
	public function eidtPwd(){
		$User = model('Headmasters');$LoginSession = model('LoginSession');
		$param = $this->param;
		$userId = $param['userId'];
		if(empty($param['oldPassword']) || empty($param['newPassword'])){
			return $this->err('参数错误！');
		}
		if(!preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,12}$/', $param['newPassword'])){
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
		$info = $User->where("id",$userId)->find();
		$oldpassword = md5($param['oldPassword']);
		if($info['password'] != $oldpassword){
			return $this->err('原密码错误！');
		}
		if($info['password'] == md5($param['newPassword'])){
			return $this->err('修改的密码不能和原密码一样！');
		}
		$data['password'] = md5($param['newPassword']);
		$data['update_time'] = time();
		$result = $User->save($data,['id'=>$userId]);
		if($result !== false){
			cache('app_send_msg_'.$param['tel'],null);
			//删除session
			$where['user_id'] = $param['userId'];
			$where['type'] = 3;
			$LoginSession->where($where)->delete();
			return $this->suc();
		}else{
			return $this->err('修改失败！');
		}
	}
	
	/**
	 * 修改绑定手机号
	 * Enter description here ...
	 */
	public function editTel(){
		$User = model('Headmasters');$LoginSession = model('LoginSession');
		$param = $this->param;
		if(!preg_match("/1[34578]{1}\d{9}$/",$param['oldTel']) || !preg_match("/1[34578]{1}\d{9}$/",$param['newTel'])){
			return $this->err("错误的手机号！");
		}
		if($param['newTel'] == $param['oldTel']){
			return $this->err('新手机号不能与旧手机号相同！');
		}
		$send = cache('app_send_msg_'.$param['newTel']);
		if(empty($param['sendCode'])){
			return $this->err('验证码为空！');
		}
		if($param['newTel'] != $send['send_tel']){
			return $this->err("验证码失效！");
		}
		if($param['sendCode'] != $send['send_code']){
			return $this->err("验证码错误！");
		}
		$info = $User->where("id",$param['userId'])->find();
		if($info['tel'] != $param['oldTel']){//验证原手机号是否正确
			return $this->err('原手机号填写错误！');
		}
		//判断新手机号是否已经被注册
		$where['flag'] = 1;
		$where['tel'] = $param['newTel'];
		$count = $User->where($where)->count();
		if($count != 0){
			return $this->err("该手机号已被注册！");
		}
		$data['tel'] = $param['newTel'];
		$data['update_time'] = time();
		$result = $User->isUpdate(true)->save($data,['id'=>$param['userId']]);
		if($result){
			//删除手机端session
			cache('app_send_msg_'.$param['newTel'],null);
			$LoginSession->where('user_id',$param['userId'])->where('type',3)->delete();
			return $this->suc();
		}else{
			return $this->err("修改失败！");
		}
	}
	
	/**
	 * 提交意见
	 */
	public function addSuggestion(){
		$param = $this->param;
		if(empty($param['content'])){
			return $this->err('至少说点什么吧？');
		}
		$data['user_id'] = $param['userId'];
		$data['tel'] = $param['tel'];
		$data['type'] = 3;
		$data['status'] = 1;
		$data['create_time'] = time();
		$data['content'] = $param['content'];
		$data['school_id'] = 0;
		$result = Db::name('Suggestion')->insert($data);
		if($result){
			return $this->suc();
		}else{
			return $this->err("新增失败！");
		}
	}
	
	/**
	 * 更改基本资料
	 */
	public function editBaseInfo(){
		$Headmasters = model('Headmasters');
		$param = $this->param;
		$rule = [
				'photo'  => 'require',
				'realname' =>'require',
				'sex' =>'number|max:2'
		];
		$msg = [
				'photo.require' => '头像不能为空！',
				'realname.require' => '真实姓名不能为空！',
				'sex.number' => '请选择性别！'
		];
		$validate = new Validate($rule,$msg);
		if (!$validate->check($param)) {
			return $this->err($validate->getError());
		}
		$result = $Headmasters->allowField(true)->isUpdate(true)->save($param,['id'=>$param['userId']]);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 设置推送开关
	 */
	public function editPushSwitch(){
		$Headmasters = model('Headmasters');
		$param = $this->param;
		if(!in_array($param['param'], array('approvalSwitch','systemSwitch','chatSwitch','disturbSwitch')) || !in_array($param['value'], array(1,2))){
			return $this->err('参数错误！');
		}
		switch ($param['param']){
			case 'approvalSwitch':$field = 'approval_switch';break;
			case 'systemSwitch':$field = 'system_switch';break;
			case 'chatSwitch':$field = 'chat_switch';break;
			case 'disturbSwitch':$field = 'disturb_switch';break;
		}
		$result = $Headmasters->where('id',$param['userId'])->setField($field,$param['value']);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 获取教师请假列表
	 */
	public function getAskLeaveList(){
		$AskLeave = model('AskLeave');
		$param = $this->param;
		if(is_null($param['nextStartId']) || !in_array($param['type'],array(1,2,3))){
			return $this->err('参数错误！');
		}
		$where['flag'] = 1;
		$where['type'] = 2;
		$where['operate_id'] = $param['userId'];
		if($param['type'] == 2){
			$where['status'] = array('neq',0);$where['end_time'] = array('GT',date('Y-m-d H:i:s',time()));
		}elseif($param['type'] == 3){/*ji add*/
			$where['end_time'] = array('LT',date('Y-m-d H:i:s',time()));/*$map['status'] = array('eq',-1);*/
		}else{
			$where['status'] = 0;$where['end_time'] = array('GT',date('Y-m-d H:i:s',time()));
		}
		$count = $AskLeave->where($where)->/*whereOr($map)->*/count();
		$field = 'id,realname,reson,status,begin_time,end_time';
		if($count < 10){
			$nextStartId = -1;
			$data = $AskLeave->where($where)->/*whereOr($map)->*/field($field)->order('create_time desc')->select();
		}else{
			$nextStartId = $param['nextStartId'];
			$data = $AskLeave->where($where)->/*whereOr($map)->*/field($field)->limit($nextStartId,10)->order('create_time desc')->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		foreach($data as $k=>$v)
		{
			if($v['end_time'] < date('Y-m-d H:i:s',time())) $data[$k]['status'] = -2;
		}
		return $this->suc($data,$nextStartId);
	}
	
	/**
	 * 获取请假详情
	 */
	public function getAskLeaveInfo(){
		$AskLeave = model('AskLeave');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		$field = 'id,realname,photo,reson,status,begin_time,end_time,create_time,leave_num,back_reson';
		$info = $AskLeave->where('id',$param['id'])->where('flag',1)->where('type',2)->field($field)->find();
		if(empty($info)){
			return $this->err('参数错误！');
		}
		if($info['end_time'] < date('Y-m-d H:i:s',time())) $info['status'] = -2;/*ji add*/
		return $this->suc($info);
	}

	/**ji add
	 * 关闭/开启邀请码
	 */
	public function switchInvite(){
		$User = model('Headmasters');
		$param = $this->param;
		$data['is_open'] = $param['inviteNum'];
		$result = model('Schools')->isUpdate(true)->save($data,['id'=>$User->where('id',$param['userId'])->find()['school_id']]);
		if($result){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 同意请假
	 * 同意后需要插入考勤表
	 */
	public function agreeAskLeave(){
		$AskLeave = model('AskLeave');$TimeCard = model('TimeCard');$Teacher = model('Teachers');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		$where['flag'] = 1;
		$where['id'] = $param['id'];
		$where['status'] = 0;
		$where['operate_id'] = $param['userId'];
		$info = $AskLeave->where($where)->find();
		if(empty($info)){
			return $this->err('未找到操作的数据！');
		}
		//往考勤表插入数据
		Db::startTrans();
		try {
			$cinfo = $Teacher->where('id',$info['user_id'])->where('flag',1)->where('is_job',1)->find();
			//当请假很多天
			$dateList = getCompareDateList($info['begin_time'], $info['end_time']);
			foreach ($dateList as $key=>$val){
				//需要在打卡记录表添加请假记录
				$tinfo = $TimeCard->where('type',2)->where('flag',1)->where('school_id',$info['school_id'])->where('user_id',$info['user_id'])
				->where('day_time', $val)->find();
				if(empty($tinfo)){
					$data['type'] = 2;
					$data['school_id'] = $cinfo['school_id'];
					$data['user_id'] = $info['user_id'];
					$data['realname'] = $cinfo['realname'];
					$data['photo'] = $cinfo['photo'];
					$data['day_time'] = $val;
					$data['record_time'] = "";
					$data['face_img'] = "";
					$data['in_status'] = 2;
					$data['out_status'] = 2;
					$result = $TimeCard->isUpdate(false)->create($data);
				}else{
					$data['in_status'] = 2;
					$data['out_status'] = 2;
					$TimeCard->where('id',$tinfo['id'])->update($data);
				}
			}
			$askData['status'] = 1;
			$askData['update_time'] = time();
			$result = $AskLeave->isUpdate(true)->save($askData,['id'=>$param['id']]);
			Db::commit();
			if(!empty($cinfo['jpush_id'])){
				$message = '园长同意了您的请假消息，请查看！';
				$extra = array('viewCode'=>80003);
				jpushToId($cinfo['jpush_id'],$message,2,$extra);
			}
			return $this->suc();
		} catch (\Exception $e) {
			Db::rollback();
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 拒绝请假
	 */
	public function refuseAskLeave(){
		$AskLeave = model('AskLeave');$Teacher = model('Teachers');
		$param = $this->param;
		if(empty($param['id']) || empty($param['backReson'])){
			return $this->err('参数错误！');
		}
		$where['flag'] = 1;
		$where['id'] = $param['id'];
		$where['operate_id'] = $param['userId'];
		$where['status'] = 0;
		$info = $AskLeave->where($where)->find();
		if(empty($info)){
			return $this->err('未找到操作的数据！');
		}
		$data['status'] = -1;
		$data['back_reson'] = $param['backReson'];
		$data['update_time'] = time();
		$result = $AskLeave->isUpdate(false)->save($data,['id'=>$param['id']]);
		if($result){
			$jpushId = $Teacher->where('id',$info['user_id'])->where('flag',1)->where('is_job',1)->value('jpush_id');
			if(!empty($jpushId)){
				$message = '园长拒绝了您的请假消息，请查看！';
				$extra = array('viewCode'=>80003);
				jpushToId($jpushId,$message,2,$extra);
			}
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 获取班级详情
	 */
	public function getClassList(){
		$Class = model('Classes');$Leader = model('Headmasters');$Child = model('Childs');
		$param = $this->param;
		$info = $Leader->where('id',$param['userId'])->find();
		//获得班级类型
		$Type = model('Type');$Subtype = model('Subtype');
		$tId = $Type->where('type_name',10002)->where('reserve',$info['school_id'])->where('flag',1)->value('id');
		$data['catsList'] = $Subtype->where('parent_id',$tId)->where('flag',1)->field('subtype_code as catsCode,subtype_name as catsName')->select();
		$classList = $Class->where('flag',1)->where('school_id',$info['school_id'])->field('id,name,cats_code')->order('cats_code desc')->select();
		foreach ($classList as $key=>$val){
			//获取班级老师与阿姨
			$classList[$key]['teacherList'] = Db::view('TeacherClass','teacher_type as type')->view('Teachers','id,realname,photo','TeacherClass.teacher_id = Teachers.id')
							->where('TeacherClass.flag',1)->where('TeacherClass.classes_id',$val['id'])->select();
			//获得班级的学生
			$classList[$key]['childList'] = $Child->where('flag',1)->where('classes_id',$val['id'])->where('status',1)->field('id,realname,photo')->select();
		}
		$data['classList'] = $classList;
		return $this->suc($data,"",config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 准备创建班级
	 */
	public function goCreateClass(){
		$Type = model('Type');$Subtype = model('Subtype');$TeacherClass = model('TeacherClass');$Teachers = model('Teachers');$Leader = model('Headmasters');
		$param = $this->param;
		$info = $Leader->where('id',$param['userId'])->find();
		$tId = $Type->where('type_name',10002)->where('reserve',$info['school_id'])->where('flag',1)->value('id');
		$data['catsList'] = $Subtype->where('parent_id',$tId)->where('flag',1)->field('subtype_code as catsCode,subtype_name as catsName')->order('seq asc')->select();
		//带班老师
		$ids = $TeacherClass->where('school_id',$info['school_id'])->where('flag',1)->value('GROUP_CONCAT(teacher_id)');
		$map['flag'] = 1;
		$map['is_job'] = 1;
		$map['school_id'] = $info['school_id'];
		//阿姨可以跟多个班级
		$map['cats_code'] = 10002;
		$data['auntList'] = $Teachers->where($map)->field('id,realname')->select();
		if(!empty($ids)){
			$map['id'] = array('not in',$ids);
		}
		$map['cats_code'] = 10001;
		$data['teacherList'] = $Teachers->where($map)->field('id,realname')->select();
		//任课老师
		$map['cats_code'] = 10003;
		$data['subTeacherList'] = $Teachers->where($map)->field('id,realname')->select();
		return $this->suc($data);
	}
	
	/**
	 * 创建班级
	 */
	public function createClass(){
		$Class = model('Classes');$TeacherClass = model('TeacherClass');$Leader = model('Headmasters');
		$param = $this->param;
		//获得学校ID
		$info = $Leader->where('id',$param['userId'])->find();
		if(empty($param['name']) || empty($param['catsCode']) || empty($param['teacherId'])){
			return $this->err('参数错误！');
		}
		//判断班级名称是否重复
		$map['name'] = $param['name'];
		$map['school_id'] = $info['school_id']; 
		$map['cats_code'] = $param['catsCode'];
		$map['flag'] = 1;
		$count = $Class->where($map)->count();
		if($count != 0){
			return $this->err('同年级下班级名称不可重复！');
		}
		//往考勤表插入数据
		Db::startTrans();
		try {
			$data['school_id'] = $info['school_id'];
			$data['cats_code'] = $param['catsCode'];
			$data['name'] = $param['name'];
			$result = $Class->isUpdate(false)->save($data);
			//把教师与跟班阿姨绑定班级
			$tdata[0]['classes_id'] = $Class->id;
			$tdata[0]['teacher_id'] = $param['teacherId'];
			$tdata[0]['school_id'] = $info['school_id'];
			$tdata[0]['teacher_type'] = 1;
			if(!empty($param['auntId'])){
				$tdata[1]['classes_id'] =  $Class->id;
				$tdata[1]['teacher_id'] = $param['auntId'];
				$tdata[1]['school_id'] = $info['school_id'];
				$tdata[1]['teacher_type'] = 2;
			}
			if(!empty($param['subTeacherId'])){
				$tdata[2]['classes_id'] =  $Class->id;
				$tdata[2]['teacher_id'] = $param['subTeacherId'];
				$tdata[2]['school_id'] = $info['school_id'];
				$tdata[2]['teacher_type'] = 3;
			}
			$TeacherClass->isUpdate(false)->saveAll($tdata);
			Db::commit();
			return $this->suc();
		} catch (\Exception $e) {
			Db::rollback();
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 准备修改班级
	 */
	public function goEditClass(){
		$TeacherClass = model('TeacherClass');$Teachers = model('Teachers');$Leader = model('Headmasters');
		$param = $this->param;
		if(empty($param['classId'])){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
		//带班老师
		$ids = $TeacherClass->where('school_id',$info['school_id'])->where('classes_id','neq',$param['classId'])->where('flag',1)->value('GROUP_CONCAT(teacher_id)');
		$map['flag'] = 1;
		$map['is_job'] = 1;
		$map['school_id'] = $info['school_id'];
		//一个阿姨可以绑定多个班级
		$map['cats_code'] = 10002;
		$data['auntList'] = $Teachers->where($map)->field('id,realname')->select();
		if(!empty($ids)){
			$map['id'] = array('not in',$ids);
		}
		$map['cats_code'] = 10001;
		$data['teacherList'] = $Teachers->where($map)->field('id,realname')->select();
		//任课老师
		$map['cats_code'] = 10003;
		$data['subTeacherList'] = $Teachers->where($map)->field('id,realname')->select();
		//获得班级选择的老师与阿姨
		$data['teacherIds'] = $TeacherClass->where('school_id',$info['school_id'])->where('classes_id',$param['classId'])->where('flag',1)->field('teacher_id,teacher_type as type')->select();
		return $this->suc($data);
	}
	
	/**
	 * 更新班级
	 */
	public function updateClass(){
		$Teachers = model('Teachers');$Leader = model('Headmasters');$TeacherClass = model('TeacherClass');
		$param = $this->param;
		if(empty($param['classId']) || empty($param['teacherId'])){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
		//删除本班全部的老师
		$TeacherClass->where('classes_id',$param['classId'])->delete();
		$data[0]['classes_id'] = $param['classId'];
		$data[0]['teacher_id'] = $param['teacherId'];
		$data[0]['school_id'] = $info['school_id'];
		$data[0]['teacher_type'] = 1;
		if(!empty($param['auntId'])){
			$data[1]['classes_id'] = $param['classId'];
			$data[1]['teacher_id'] = $param['auntId'];
			$data[1]['school_id'] = $info['school_id'];
			$data[1]['teacher_type'] = 2;
		}
		if(!empty($param['subTeacherId'])){
			$data[2]['classes_id'] = $param['classId'];
			$data[2]['teacher_id'] = $param['subTeacherId'];
			$data[2]['school_id'] = $info['school_id'];
			$data[2]['teacher_type'] = 3;
		}
		$result = $TeacherClass->isUpdate(false)->saveAll($data);
		if($result){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 获取班级消息
	 */
	public function getClassNotice(){
		$ClassNotice = model('ClassNotice');$Leader = model('Headmasters');$Classes = model('Classes');$Subtype = model('Subtype');
		$param = $this->param;
		if(empty($param['classId'])){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
		//获取班级详情
		$cinfo = $Classes->where('id',$param['classId'])->find();
		$data['info']['name'] = $cinfo['name'];
		$data['info']['cats_name'] = $Subtype->where('subtype_code',$cinfo['cats_code'])->value('subtype_name');
		//获取班主任与阿姨
		$data['info']['teacherName'] = Db::view('TeacherClass','teacher_type')->view('Teachers','realname','TeacherClass.teacher_id = Teachers.id')
								->where('TeacherClass.flag',1)->where('TeacherClass.teacher_type',1)->where('classes_id',$cinfo['id'])->value('realname');
		$data['info']['auntName'] = Db::view('TeacherClass','teacher_type')->view('Teachers','realname','TeacherClass.teacher_id = Teachers.id')
								->where('TeacherClass.flag',1)->where('TeacherClass.teacher_type',2)->where('classes_id',$cinfo['id'])->value('realname');
		$data['info']['subTeacherName'] = Db::view('TeacherClass','teacher_type')->view('Teachers','realname','TeacherClass.teacher_id = Teachers.id')
								->where('TeacherClass.flag',1)->where('TeacherClass.teacher_type',3)->where('classes_id',$cinfo['id'])->value('realname');
		//男女人数与总人数
		$data['info']['boyNum'] = 0;
		$data['info']['girlNum'] = 0;
		$data['info']['totalNum'] = 0;
		$childList = Db::name('Childs')->where('flag',1)->where('classes_id',$cinfo['id'])->where('status',1)->field('sex')->select();
		foreach ($childList as $key=>$val){
			if($val['sex'] == 1){
				$data['info']['boyNum'] ++;
			}else{
				$data['info']['girlNum'] ++;
			}
			$data['info']['totalNum'] ++;
		}
		
 		$data['noticeList'] = $ClassNotice->where('flag',1)->where('class_id',$param['classId'])->where('school_id',$info['school_id'])->field('id,type,reserve,status')->order('create_time desc')->select();
 		foreach ($data['noticeList'] as $key=>$val){
 			if($val['type'] == 1){
 				$data['noticeList'][$key]['info'] = $Subtype->where('subtype_code',$val['reserve'])->field('subtype_name as cats_name')->find();
 			}else{
 				$data['noticeList'][$key]['info'] = Db::name('Childs')->where('id',$val['reserve'])->field('realname,photo,sex,code')->find();
 			}
 		}
		return $this->suc($data,'',config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 同意升班/退班操作
	 */
	public function agreeClassNotice(){
		$ClassNotice = model('ClassNotice');$Leader = model('Headmasters');$Classes = model('Classes');$Child = model('Childs');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		//获得学校ID
		$info = $Leader->where('id',$param['userId'])->find();
		$ninfo = $ClassNotice->where('id',$param['id'])->where('school_id',$info['school_id'])->find();
		if(empty($ninfo)){
			return $this->err('未找到相应数据！');
		}
		if($ninfo['status'] != 0){
			return $this->err('该信息已处理，请勿重复操作！');
		}
		//升班操作
		if($ninfo['type'] == 1){
			//找到班级信息
			$classInfo = $Classes->where("id",$ninfo['class_id'])->find();
			//判断班号是否重复
			$map['name'] = $classInfo['name'];
			$map['school_id'] = $info['school_id'];
			$map['cats_code'] = $ninfo['reserve'];
			$map['flag'] = 1;
			$count = $Classes->where($map)->count();
			if($count != 0){
				return $this->err('班号已重复，无法执行此操作！');
			}
			$Classes->where('id',$ninfo['class_id'])->setField('cats_code',$ninfo['reserve']);
			$result = $ClassNotice->where('id',$param['id'])->update(['status'=>1,'update_time'=>time()]);
		}else{//退班操作
			$Child->where('id',$ninfo['reserve'])->setField('classes_id',0);
			$result = $ClassNotice->where('id',$param['id'])->update(['status'=>1,'update_time'=>time()]);
		}
		if($result != false){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 拒绝升班/退班操作
	 */
	public function refuseClassNotice(){
		$ClassNotice = model('ClassNotice');$Leader = model('Headmasters');$Classes = model('Classes');$Child = model('Childs');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		//获得学校ID
		$info = $Leader->where('id',$param['userId'])->find();
		$ninfo = $ClassNotice->where('id',$param['id'])->where('school_id',$info['school_id'])->find();
		if(empty($ninfo)){
			return $this->err('未找到相应数据！');
		}
		if($ninfo['status'] != 0){
			return $this->err('该信息已处理，请勿重复操作！');
		}
		//升班操作
		if($ninfo['type'] == 1){
			$result = $ClassNotice->where('id',$param['id'])->update(['status'=>-1,'update_time'=>time()]);
		}else{//退班操作
			$result = $ClassNotice->where('id',$param['id'])->update(['status'=>-1,'update_time'=>time()]);
		}
		if($result != false){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 获取课表
	 */
	public function getCourseList(){
		$param = $this->param;
		if(empty($param['classId'])){
			return $this->err('参数错误！');
		}
		$data = Db::view('CourseTimeClass','id')->view('Course','name','CourseTimeClass.course_id = Course.id')->view('CourseTime','title,weeks,begin_time,end_time','CourseTimeClass.course_time_id = CourseTime.id')->where('Course.flag',1)
		->where('CourseTime.flag',1)->where('CourseTimeClass.class_id',$param['classId'])->order('weeks asc')->select();
		return $this->suc($data);
	}
	
	/**
	 * 获取教师考勤
	 */
	public function getTimeCardStatistics(){
		$TimeCard = model('TimeCard');$Leader = model('Headmasters');$Teacher = model('Teachers');$TeacherClass = model('TeacherClass');
		$param = $this->param;
		if(empty($param['day'])){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
		//上班打卡
		$data['onList'] = $TimeCard->where('flag',1)->where('type',2)->where('school_id',$info['school_id'])->where('day_time',$param['day'])
								->where('in_status',1)->field('user_id as teacherId,realname,photo,record_time')->select();
		//未上班
		$list = $Teacher->where('is_job',1)->where('flag',1)->where('school_id',$info['school_id'])->field('id as teacherId,realname,photo')->select();
		$ids = $TimeCard->where('flag',1)->where('type',2)->where('school_id',$info['school_id'])->where('day_time',$param['day'])->value('GROUP_CONCAT(user_id)');
		$data['noinList'] = array();
		foreach ($list as $key=>$val){
			if(!in_array($val['teacherId'], explode(',', $ids))){
				$data['noinList'][] = $val;
			}
		}
		//迟到
		$data['lateList'] = $TimeCard->where('flag',1)->where('type',2)->where('school_id',$info['school_id'])->where('day_time',$param['day'])
								->where('in_status',-1)->field('user_id as teacherId,realname,photo,record_time')->select();
		//早退
		$data['earlyList'] = $TimeCard->where('flag',1)->where('type',2)->where('school_id',$info['school_id'])->where('day_time',$param['day'])
								->where('out_status',-1)->field('user_id as teacherId,realname,photo,record_time')->select();
		//下班未打卡
		$nooutList = $TimeCard->where('flag',1)->where('type',2)->where('school_id',$info['school_id'])->where('day_time',$param['day'])
				->where('record_time','not like',"%|%")->field('user_id as teacherId,realname,photo,record_time')->select();
		$data['nooutList'] = $data['noinList'];//上班未打卡，下班也算没打卡
		foreach ($nooutList as $key=>$val){
			$data['nooutList'][] = $val;
		}
		return $this->suc($data);
	}
	
	/**
	 * 获取月份教师考勤
	 */
	public function getTimeCardStatisticsByMonth(){
		$TimeCard = model('TimeCard');$Leader = model('Headmasters');$Teacher = model('Teachers');$TeacherClass = model('TeacherClass');
		$param = $this->param;
		if(empty($param['month'])){
			return $this->err('参数错误！');
		}
		$month_start = $param['month']."-01";//指定月份月初
		$month_end = date('Y-m-d', strtotime("$month_start +1 month -1 day"));;//指定月份月末
		$info = $Leader->where('id',$param['userId'])->find();
		$idsArray = array();
		//上班迟到
		$data['lateList'] = $TimeCard->where('flag',1)->where('type',2)->where('school_id',$info['school_id'])->whereTime('day_time','between',array($month_start,$month_end))
							->where('in_status',-1)->field('user_id as teacherId,realname,photo,day_time')->select();
		foreach ($data['lateList'] as $key1=>$val1){
			array_push($idsArray,$val1['teacherId']);
		}
		//请假
		$data['askLeaveList'] = $TimeCard->where('flag',1)->where('type',2)->where('school_id',$info['school_id'])->whereTime('day_time','between',array($month_start,$month_end))
							->where('in_status',2)->field('user_id as teacherId,realname,photo,day_time')->select();
		foreach ($data['askLeaveList'] as $key2=>$val2){
			array_push($idsArray,$val2['teacherId']);
		}
		//早退
		$data['earlyList'] = $TimeCard->where('flag',1)->where('type',2)->where('school_id',$info['school_id'])->whereTime('day_time','between',array($month_start,$month_end))
							->where('out_status',-1)->field('user_id as teacherId,realname,photo,day_time')->select();
		foreach ($data['earlyList'] as $key3=>$val3){
			array_push($idsArray,$val3['teacherId']);
		}
		//下班未打卡，排除了请假
		$data['nooutList'] = $TimeCard->where('flag',1)->where('type',2)->where('school_id',$info['school_id'])->whereTime('day_time','between',array($month_start,$month_end))
							->where('record_time','not like',"%|%")->where('in_status','neq',2)->field('user_id as teacherId,realname,photo,record_time')->select();
		foreach ($data['nooutList'] as $key4=>$val4){
			array_push($idsArray,$val4['teacherId']);
		}
		//全勤，默认无迟到，早退，请假或者没下班没打卡默认就是全勤
		$data['allinList'] = $Teacher->where('is_job',1)->where('id','not in',$idsArray)->where('flag',1)->where('school_id',$info['school_id'])->field('id as teacherId,realname,photo')->select();//获取所有老师
		return $this->suc($data);
	}
	
	/**
	 * 获取直播列表
	 */
	public function getClassLiveList(){
		$ClassLive = model('ClassLive');$Leader = model('Headmasters');$Type = model('Type');$Subtype = model('Subtype');
		$param = $this->param;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
		$where['flag'] = 1;
		$where['is_on'] = 1;
		$where['school_id'] = $info['school_id'];
		
		$count = $ClassLive->where($where)->count();
		$field = 'title,device_id,live_photo,live_hls,live_token,is_on,is_voice,open_time,close_time,class_id';
		if($count < 10){
			$nextStartId = -1;
			$data = $ClassLive->where($where)->field($field)->order('create_time desc')->select();
		}else{
			$nextStartId = $param['nextStartId'];
			$data = $ClassLive->where($where)->field($field)->limit($nextStartId,10)->order('create_time desc')->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		$tId = $Type->where('type_name',10002)->where('reserve',$info['school_id'])->where('flag',1)->value('id');
		foreach ($data as $key=>$val){
			$classInfo = Db::name('Classes')->where('id',$val['class_id'])->field('cats_code,name')->find();
			$cats_name = $Subtype->where('parent_id',$tId)->where('flag',1)->where('subtype_code',$classInfo['cats_code'])->value('subtype_name');
			unset($data[$key]['class_id']);
			$data[$key]['name'] = $classInfo['name'];
			$data[$key]['catsName'] = $cats_name;
		}
		return $this->suc($data,$nextStartId);
	}
	
	/**
	 * 获取园长信箱
	 */
	public function getSuggestList(){
		$Leader = model('Headmasters');
		$param = $this->param;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
		$where['flag'] = 1;
		$where['school_id'] = $info['school_id'];
		$count = Db::name('Suggestion')->where($where)->count();
		$field = 'tel,content,create_time';
		if($count < 10){
			$nextStartId = -1;
			$data = Db::name('Suggestion')->where($where)->field($field)->order('create_time desc')->select();
		}else{
			$nextStartId = $param['nextStartId'];
			$data = Db::name('Suggestion')->where($where)->field($field)->limit($nextStartId,10)->order('create_time desc')->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		return $this->suc($data,$nextStartId);
	}
	
	/**
	 * 获取菜谱列表
	 */
	public function getCookList(){
		$Leader = model('Headmasters');$Cook = model('Cookbook');
		$param = $this->param;
		$info = $Leader->where('id',$param['userId'])->find();
		$where['flag'] = 1;
		$where['school_id'] = $info['school_id'];
		$field = 'id AS cook_id,type,name,img';
		$data = $Cook->where($where)->field($field)->order('create_time desc')->select();
		$newData = [
				['type'=>1,'typeName'=>'荤菜'],
				['type'=>2,'typeName'=>'素菜'],
				['type'=>3,'typeName'=>'汤'],
				['type'=>4,'typeName'=>'点心'],
				['type'=>5,'typeName'=>'其他'],
				];
		foreach ($newData as $key=>$val){
			foreach ($data as $key2=>$val2){
				if($val['type'] == $val2['type']){
					$newData[$key]['cookList'][] = $val2;
				}
			}
		}
		return $this->suc($newData,"",config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 获取菜谱详情
	 */
	public function getCookInfo(){
		$Cook = model('Cookbook');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		$field = 'id,type,name,img,materials,desc';
		$info = $Cook->where('id',$param['id'])->where('flag',1)->field($field)->find();
		if(empty($info)){
			return $this->err('参数错误！');
		}
		/*ji add*/if($info['type'] == 1){$info['type_name'] = '荤菜';}elseif($info['type'] == 2){$info['type_name'] = '素材';}elseif($info['type'] == 3){$info['type_name'] = '汤';}elseif($info['type'] == 4){$info['type_name'] = '点心小食';}elseif($info['type'] == 5){$info['type_name'] = '主食';}else{$info['type_name'] = '其他';}
		return $this->suc($info,'',config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 获取某天的菜谱
	 */
	public function getCookByDay(){
		$Leader = model('Headmasters');
		$param = $this->param;
		if(empty($param['day'])){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
		$data = Db::view('CookbookDate','id,type')->view('Cookbook','id as cookId,name,img','CookbookDate.cookbook_id = Cookbook.id')
		->where('CookbookDate.school_id',$info['school_id'])->where('Cookbook.school_id',$info['school_id'])
		->where('CookbookDate.flag',1)->where('Cookbook.flag',1)->where('CookbookDate.day_time',$param['day'])
		->select();
		$newData = [
				['type'=>1,'typeName'=>'早餐'],
				['type'=>2,'typeName'=>'早茶'],
				['type'=>3,'typeName'=>'午餐'],
				['type'=>4,'typeName'=>'下午茶']
		];
		foreach ($newData as $key=>$val){
			foreach ($data as $key2=>$val2){
				if($val['type'] == $val2['type']){
					$newData[$key]['cookList'][] = $val2;
				}
			}
		}
		return $this->suc($newData,'',config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 新增菜谱
	 */
	public function addCook(){
		$Leader = model('Headmasters');$CookbookDate = model('CookbookDate');
		$param = $this->param;
		if(empty($param['ids']) || !in_array($param['type'], array(1,2,3,4,5)) || empty($param['day'])){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
		$ids = explode(',', $param['ids']);
		foreach ($ids as $key=>$val){
			$data[$key]['school_id'] = $info['school_id'];
			$data[$key]['cookbook_id'] = $val;
			$data[$key]['day_time'] = $param['day'];
			$data[$key]['type'] = $param['type'];
		}
		$result = $CookbookDate->saveAll($data);
		if($result){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 删除菜谱
	 */
	public function delCook(){
		$Leader = model('Headmasters');$CookbookDate = model('CookbookDate');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		$result = $CookbookDate->where('id',$param['id'])->setField('flag',2);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}

    /**
     * 修改当日菜谱-新增
     */
    public function editCookbooks(){
        $day = input('date');
        $ck = input('cookbook');
        $model = model('CookbookDate');
        if (empty($day) || empty($ck)){
            return $this->err('参数缺失!');
        }
        $ck = json_decode($ck,true);
        $update['flag'] = 2;
        $where['school_id'] = input('schoolId');
        $where['day_time'] = $day;
        $model->save($update,$where);
        $i = 0;
        foreach($ck as $key=>$val){
            if(!empty($val['id'])){
                foreach ($val['id'] as $k=>$v){
                    $list[$i]['cookbook_id'] = $v;
                    $list[$i]['type'] = $val['type'];
                    $list[$i]['school_id'] = $where['school_id'];
                    $list[$i]['day_time'] = $where['day_time'];
                    $list[$i]['flag'] = 1;
                    $i++;
                }
            }
        }
        //return var_dump($list);
        $res = $model->saveAll($list);
        return $this->suc();
    }
    /**
     * 复制菜谱到下一周
     */
    public function copyWeekCookbook(){
        $gettime = input('date');
        if(empty($gettime)){
            return $this->err('没有传入日期!');
        }
        $schoolId = input('schoolId');
        if(empty($schoolId)){
            return $this->err('没有传入学校id!');
        }
        $model = model('CookbookDate');
        $time = strtotime($gettime);
        $w = date('w',$time);
        $begin = $time - 3600*24*$w;
        $end = $begin + 3600*24*6;
        //获取选中周的菜谱信息
        $result = Db::view('CookbookDate','id,cookbook_id,day_time,type')
            ->view('Cookbook','id as cid','CookbookDate.cookbook_id = Cookbook.id')
            ->where('CookbookDate.school_id',$schoolId)
            ->where('CookbookDate.flag',1)
            ->where('Cookbook.school_id',$schoolId)
            ->where('Cookbook.flag',1)
            ->where('day_time','between time',[date('Y-m-d',$begin),date('Y-m-d',$end)])
            ->order('day_time asc')
            ->order('CookbookDate.type asc')
            ->select();

        foreach($result as $key=>$val){
            $dayTime =  strtotime($val['day_time']);
            $dayTime = $dayTime + 3600*24*7;
            $list[$key]['day_time'] = date('Y-m-d',$dayTime);
            $list[$key]['cookbook_id'] = $val['cookbook_id'];
            $list[$key]['type'] = $val['type'];
            $list[$key]['school_id'] = $schoolId;
            $list[$key]['flag'] = 1;
        }
        //return var_dump($list);
        $nextbegin = $begin + 3600*24*7;
        $nextend = $end + 3600*24*7;
        Db::name('CookbookDate')->where('day_time','between time',[date('Y-m-d',$nextbegin),date('Y-m-d',$nextend)])->where('school_id',$schoolId)->where('flag',1)->update(['flag'=>2]);
        $res = $model->saveAll($list);
        return $this->suc();
    }
    /**
     * 新增菜谱库
     */
    public function addCookStore(){
        $Cookbook = model('Cookbook');
        $param = $this->param;
        if(empty($param['schoolId']) || !in_array($param['type'], array(1,2,3,4,5)) || empty($param['name'])){
            return $this->err('参数错误！');
        }
        $data['school_id'] = $param['schoolId'];
        $data['type'] = $param['type'];
        $data['name'] = $param['name'];
        $data['img'] = $param['img'];
        $data['materials'] = $param['materials'];
        $data['desc'] = $param['desc'];
        $result = $Cookbook->isUpdate(false)->save($data);
        $cid = $Cookbook->id;
        $backData = $Cookbook->where('id',$cid)->field('id,type,name,img,materials,desc')->find();
        if($result){
            return $this->suc($backData);
        }else{
            return $this->err('添加失败！');
        }
    }
    /**
     * 修改菜谱库菜谱
     */
    public function editCookStore(){
        $Cookbook = model('Cookbook');
        $param = $this->param;
        if(empty($param['id'])||empty($param['schoolId']) || !in_array($param['type'], array(1,2,3,4,5)) || empty($param['name'])){
            return $this->err('参数错误！');
        }
        $data['id'] = $param['id'];
        $data['school_id'] = $param['schoolId'];
        $data['type'] = $param['type'];
        $data['name'] = $param['name'];
        $data['img'] = $param['img'];
        $data['materials'] = $param['materials'];
        $data['desc'] = $param['desc'];
        $result = $Cookbook->isUpdate(true)->save($data);
        if($result){
            return $this->suc();
        }else{
            return $this->err('添加失败！');
        }
    }
    /**
     * 删除菜谱库菜谱
     */
    public function delCookStore(){
        $Cookbook = model('Cookbook');
        $param = $this->param;
        if(empty($param['id'])){
            return $this->err('参数错误！');
        }
        $data['id'] = $param['id'];
        $data['flag'] = 2;
        $result = $Cookbook->isUpdate(true)->save($data);
        if($result){
            return $this->suc();
        }else{
            return $this->err('添加失败！');
        }
    }

    /**
     * 验证手机号
     */
    public function checkBindTel(){
        $param = $this->param;
        if (empty($param['tel']) || empty($param['sendCode'])){
            return $this->err('参数缺失!');
        }
        if(!preg_match("/1[3456789]{1}\d{9}$/",$param['tel'])){
            return $this->err("错误的手机格式！");
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
        $headmaster = model('Headmasters');
        $info = $headmaster->where('flag',1)->where('id',$param['userId'])->where('tel',$param['tel'])->value('id');
        if (empty($info)){
            return $this->err('未找到该手机号绑定的账户');
        }else{
            $backData['leaderId'] = $info;
            return $this->suc($backData);
        }
    }
    /**
     * 修改绑定手机号
     */
    public function editBindTel(){
        $param = $this->param;
        if (empty($param['tel']) || empty($param['sendCode']) || empty($param['leaderId'])){
            return $this->err('参数缺失!');
        }
        if(!preg_match("/1[3456789]{1}\d{9}$/",$param['tel'])){
            return $this->err("错误的手机格式！");
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
        $headmaster = model('Headmasters');
        $oldinfo = $headmaster->where('flag',1)->where('tel',$param['tel'])->value('id');
        if (empty($oldinfo)){
            $data['tel'] = $param['tel'];
            $where['id'] = $param['leaderId'];
            $result = $headmaster->allowField(true)->save($data,$where);
            if ($result){
                return $this->suc();
            }else{
                return $this->err('网络繁忙,修改失败!');
            }
        }else{
            return $this->err('该手机号已经被其他账号绑定!');
        }
    }
}