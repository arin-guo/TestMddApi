<?php
namespace app\parents\controller;
use app\parents\controller\Base;
use think\Db;
use think\Validate;
use sendsms\Sendsms;
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
		$User = model('Parents');$LoginSession = model('LoginSession');
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
			$where['type'] = 1;
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
		$User = model('Parents');$LoginSession = model('LoginSession');
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
			cache('app_send_msg_'.$param['newTel'],null);
			//删除手机端session
			$LoginSession->where('user_id',$param['userId'])->where('type',1)->delete();
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
		$data['type'] = 1;
		$data['status'] = 1;
		$data['create_time'] = time();
		$data['content'] = $param['content'];
		//如果有传schoool_id代表园长信箱
		if(!empty($param['schoolId']) && $param['type'] == 1){//匿名
			
		}else{
			$data['user_id'] = $param['userId'];
			$data['tel'] = Db::name('Parents')->where('id',$param['userId'])->value('realname');
		}
		$data['school_id'] = empty($param['schoolId'])?0:$param['schoolId'];
		$result = Db::name('Suggestion')->insert($data);
		if($result){
			//推送给园长
			if(!empty($param['schoolId'])){
				$hinfo = Db::name('Headmasters')->where('flag',1)->where('school_id',$param['schoolId'])->field('id,jpush_id,system_switch,disturb_switch')->find();
				if(!empty($hinfo['jpush_id']) && $hinfo['system_switch'] == 2 && $hinfo['disturb_switch'] == 2){
					$message = '您的园长信箱有新消息，请查看！';
					$extra = array('viewCode'=>80011);
					jpushToId($hinfo['jpush_id'],$message,3,$extra);
				}
			}
			return $this->suc();
		}else{
			return $this->err("新增失败！");
		}
	}
	
	/**
	 * 获取账号信息
	 */
	public function getBaseInfo(){
		$Parent = model('Parents');
		$param = $this->param;
		$data = $Parent->where('id',$param['userId'])->field('id,photo,realname,sex,tel,address,id_card')->find();
		$data['childList'] = Db::view('ParentChild','child_id as id')->view('Childs','realname,status','ParentChild.child_id = Childs.id')
							->where('ParentChild.parent_id',$param['userId'])->where('ParentChild.flag',1)->where('Childs.flag',1)->select();
		return $this->suc($data);
	}
	
	/**
	 * 修改自身资料
	 */
	public function editBaseInfo(){
		$Parent = model('Parents');
		$param = $this->param;
		$rule = [
				'realname' =>'require',
				'sex' =>'number|max:2',
		];
		$msg = [
				'realname.require' => '真实姓名不能为空！',
				'sex.number' => '请选择性别！',
		];
		$validate = new Validate($rule,$msg);
		if (!$validate->check($param)) {
			return $this->err($validate->getError());
		}
		$data['id_card'] = empty($param['idCard'])?"":$param['idCard'];
		$data['address'] = $param['address'];
		$data['sex'] = $param['sex'];
		$data['photo'] = $param['photo'];
		$data['realname'] = $param['realname'];
		$result = $Parent->allowField(true)->isUpdate(true)->save($data,['id'=>$param['userId']]);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');			
		}
	}
	
	/**
	 * 获取孩子信息
	 */
	public function getChildInfo(){
		$Child = model('Childs');
		$param = $this->param;
		if(empty($param['childId'])){
			return $this->err('参数错误！');
		}
		$field = 'realname,photo,en_name,sex,birthday,age,household,ethnicity,hobby,body_situation,allergy_situation,status,remark,status';
		$info = Db::view('ParentChild','child_id as id,relation')->view('Childs',$field,'ParentChild.child_id = Childs.id')
				->where('ParentChild.parent_id',$param['userId'])->where('ParentChild.child_id',$param['childId'])->where('ParentChild.flag',1)->where('Childs.flag',1)->find();
		if(empty($info)){
			return $this->err('参数错误！');
		}
		if($info['status'] < 0){
			return $this->err('该小孩已毕业或转校，无法操作！');
		}
		return $this->suc($info);
	}
	
	/**
	 * 修改孩子资料
	 * 注意当为从家属时无权修改孩子的信息
	 */
	public function editChildInfo(){
		$Parent = model('Parents');$Child = model('Childs');
		$param = $this->param;
		if(empty($param['childId'])){
			return $this->err('参数错误！');
		}
		$info = $Parent->where('id',$param['userId'])->find();
		if(empty($info) || $info['type'] != 1){
			return $this->err('从属家长无权限执行此操作！');
		}
		$rule = [
				'birthday'=>'require|date',
				'realname' =>'require',
				'relation' =>'require',
				'sex' =>'number|max:2',
				'age'   => 'number|between:1,100',
		];
		$msg = [
		    'realname.require' => '真实姓名不能为空！',
		    'age.number' => '请输入正确的年龄！',
		    'birthday.date' => '请输入正确的出生年月！',
		];
		$validate = new Validate($rule,$msg);
		if (!$validate->check($param)) {
			return $this->err($validate->getError());
		}
		if(request()->has('enName')){
			$param['en_name'] = $param['enName'];
			unset($param['enName']);
		}
		if(request()->has('bodySituation')){
			$param['body_situation'] = $param['bodySituation'];
			unset($param['bodySituation']);
		}
		if(request()->has('allergySituation')){
			$param['allergy_situation'] = $param['allergySituation'];
			unset($param['allergySituation']);
		}
		Db::startTrans();
		try{
			Db::name('ParentChild')->where('parent_id',$param['userId'])->where('child_id',$param['childId'])->where('flag',1)->setField('relation',$param['relation']);
			$Child->allowField(true)->isUpdate(true)->save($param,['id'=>$param['childId']]);
			Db::commit();
			return $this->suc();
		}catch (\Exception $e){
			Db::rollback();
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 获取从属家长列表
	 */
	public function getSubParentList(){
		$Parent = model('Parents');
		$param = $this->param;
		$info = $Parent->where('id',$param['userId'])->find();
		if(empty($info) || $info['type'] != 1){
			return $this->err('从属家长无权限执行此操作！');
		}
		$data = $Parent->where('flag',1)->where('parent_id',$param['userId'])->field('id,realname,tel,photo,id_card,is_main_pick')->order('create_time asc')->select();
		foreach ($data as $key=>$val){
			//从属家长与孩子的关系全一致，所以只需指定一个
			$relation = Db::name('ParentChild')->where('parent_id',$val['id'])->where('flag',1)->value('relation');
			$val['relation'] = $relation;
			$data[$key] = $val;
		}
		return $this->suc($data);
		
	}
	
	/**
	 * 添加从属家长
	 * 只能主家长添加账号
	 */
	public function addSubParent(){
		$Parent = model('Parents');$Child = model('Childs');
		$param = $this->param;
		$info = $Parent->where('id',$param['userId'])->find();
		if(empty($info) || $info['type'] != 1){
			return $this->err('从属家长无权限执行此操作！');
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
		//判断从属家长最多三个
		$count = $Parent->where('parent_id',$param['userId'])->where('flag',1)->count();
		if($count >= 2){
			return $this->err("亲，再多服务不过来了！");
		}
		$rule = [
				'tel|手机号'=>'require|/1[34578]{1}\d{9}$/',
				'password|密码'  => 'require|length:6,20',
				'realname|真实姓名' =>'require|chs',
				'relation|关系' =>'require|chs',
		];
		$msg = [
				'tel./1[34578]{1}\d{9}$/' => '请输入正确的手机号！',
		];
		$validate = new Validate($rule,$msg);
		if (!$validate->check($param)) {
			return $this->err($validate->getError());
		}
		//判断身份证唯一
		if(!empty($param['idCard'])){
			$count = $Parent->where('flag',1)->where('id_card',$param['idCard'])->count();
			if($count != 0){
				return $this->err('该身份证已被绑定，如有疑问，请联系客服！');
			}
		}
		$count2 = $Parent->where('flag',1)->where('tel',$param['tel'])->count();
		if($count2 != 0){
			return $this->err('该手机号已被绑定，如有疑问，请联系客服！');
		}
		Db::startTrans();
		try {
			$data['unique_code'] = $info['unique_code'];
			$data['school_id'] = $info['school_id'];
			$data['username'] = '家长'.substr($param['tel'], -4);
			$data['realname'] = $param['realname'];
			$data['tel'] = $param['tel'];
			$data['password'] = md5($param['password']);
			$data['id_card'] = $param['idCard'];
			$data['type'] = 2;//从属家属
			$data['status'] = 1;
			$data['parent_id'] = $info['id'];
			$data['is_main_pick'] = 2;
			$subInfo = $Parent->isUpdate(false)->save($data);
			//与小孩绑定关系
			$list = Db::name('ParentChild')->where('parent_id',$param['userId'])->where('flag',1)->select();
			foreach($list as $key=>$val){
				$subData[$key]['parent_id'] = $Parent->id;
				$subData[$key]['child_id'] = $val['child_id'];
				$subData[$key]['relation'] = $param['relation'];
				$subData[$key]['create_time'] = time();
			}
			Db::name('ParentChild')->insertAll($subData);
			Db::commit();
			cache('app_send_msg_'.$param['tel'],null);
			return $this->suc();
		} catch (\Exception $e) {
			Db::rollback();
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 删除从属家长
	 */
	public function delSubParent(){
		$Parent = model('Parents');
		$param = $this->param;
		if(empty($param['subParentId'])){
			return $this->err('参数错误！');
		}
		$info = $Parent->where('id',$param['userId'])->find();
		if(empty($info) || $info['type'] != 1){
			return $this->err('从属家长无权限执行此操作！');
		}
		Db::startTrans();
		try {
			//删除所有孩子的关系
			Db::name('ParentChild')->where('parent_id',$param['subParentId'])->where('flag',1)->setField('flag',2);
			//删除账号
			$Parent->where('flag',1)->where('parent_id',$param['userId'])->where('id',$param['subParentId'])->setField('flag',2);
			$is_main_pick = $Parent->where('flag',1)->where('parent_id',$param['userId'])->where('id',$param['subParentId'])->value("is_main_pick");
			if($is_main_pick == 1){
				$Parent->where('flag',1)->where('id',$param['userId'])->setField('is_main_pick',1);
			}
			Db::commit();
			return $this->suc();
		} catch (\Exception $e) {
			Db::rollback();
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 删除孩子
	 */
	public function delChild(){
		$Parent = model('Parents');$Child = model('Childs');
		$param = $this->param;
		if(empty($param['childId'])){
			return $this->err('参数错误！');
		}
		$info = $Parent->where('id',$param['userId'])->find();
		if(empty($info) || $info['type'] != 1){
			return $this->err('从属家长无权限执行此操作！');
		}
		//判断孩子是否为无班级状态
		$cinfo = $Child->where('flag',1)->where('id',$param['childId'])->find();
		if(empty($cinfo)){
			return $this->err('未找到孩子信息！');
		}
		if(!empty($cinfo['classes_id'])){
			return $this->err('请先解除孩子的班级状态再尝试删除！');
		}
		//删除所有孩子的关系
		$Child->where('id',$param['childId'])->setField('flag',2);
		$result = Db::name('ParentChild')->where('child_id',$param['childId'])->where('flag',1)->setField('flag',2);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 获取临时接送
	 */
	public function getTempTakeList(){
		$Temp = model('TempTakes');
		$param = $this->param;
		if(is_null($param['nextStartId']) || !in_array($param['status'], array(1,2))){
			return $this->err('参数错误！');
		}
		$where['flag'] = 1;
		$where['user_id'] = $param['userId'];
		$where['status'] = $param['status'];
		if($param['status'] == 2){//需要包含已接送成功的
			$where['status'] = array('in',array(2,3));
		}
		$count = $Temp->where($where)->count();
		$field = 'id,child_photo,child_realname,create_time,take_realname,take_type,take_time,take_relation,take_id_card,take_tel';
		if($count < 10){
			$nextStartId = -1;
			$data = $Temp->where($where)->field($field)->order('create_time desc')->select();
		}else{
			$nextStartId = $param['nextStartId'];
			$data = $Temp->where($where)->field($field)->limit($nextStartId,10)->order('create_time desc')->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		return $this->suc($data,$nextStartId);
	}
	
	/**
	 * 申请临时接送
	 * 从属家属无权访问
	 */
	public function applyTempTakes(){
		$Temp = model('TempTakes');$Child = model('Childs');$Parent = model('Parents');
		$param = $this->param;
		if(!in_array($param['takeType'], array(1,2)) || empty($param['childId'])){
			return $this->err('参数错误！');
		}
		$rule = [
				'takeRealname|接送人姓名'=>'require',
				'takeIdCard|接送人身份证' => 'length:18',
				'takeTel|接送人电话' => 'require|number',
				'takeRelation|接送人关系' =>'require|chs',
				'takeTime|接送时间'  => 'require|dateFormat:Y-m-d H:i:s',
		];
		$msg = [];
		$validate = new Validate($rule,$msg);
		if (!$validate->check($param)) {
			return $this->err($validate->getError());
		}
		//判断是否是从属家长
		$info = $Parent->where('id',$param['userId'])->find();
		if($info['type'] != 1){
			return $this->err('从属家长无权限操作！');
		}
		$cinfo = $Child->where('flag',1)->where('id',$param['childId'])->find();
		if(empty($cinfo)){
			return $this->err('未找到孩子信息！');
		}
		if($cinfo['status'] != 1){
			return $this->err('孩子已毕业或离校！');
		}
		$data['user_id'] = $param['userId'];
		$data['child_id'] = $param['childId'];
		$data['school_id'] = $cinfo['school_id'];
		$data['class_id'] = $cinfo['classes_id'];
		$data['take_realname'] = $param['takeRealname'];
		$data['take_id_card'] = $param['takeIdCard'];
		$data['take_tel'] = $param['takeTel'];
		$data['take_relation'] = $param['takeRelation'];
		$data['take_time'] = $param['takeTime'];
		$data['take_type'] = $param['takeType'];
		$data['child_photo'] = $cinfo['photo'];
		$data['child_realname'] = $cinfo['realname'];
		$data['status'] = 1;
		$takeCode = buildTakeUniqueCode($cinfo['school_id']);
		$data['take_unique_code'] = $takeCode;
		$result = $Temp->isUpdate(false)->save($data);
		if($result){
			//推送给老师
			$tid = Db::name('TeacherClass')->where('flag',1)->where('classes_id',$cinfo['classes_id'])->where('teacher_type',1)->value('teacher_id');
			if(!empty($tid)){
				$jpushId = Db::name('Teachers')->where('is_job',1)->where('flag',1)->where('id',$tid)->value('jpush_id');
				if(!empty($jpushId)){
					$message = '您的班级有一条临时接送信息！';
					$extra = array('viewCode'=>80009);
					jpushToId($jpushId,$message,2,$extra);
				}
			}
			//发送一条短信到临时接送人手机
			$sendSms = new Sendsms(config('app_sendmsg_key'), config('app_sendmsg_secret'));
			$sendSms->sendSMSTemplate('3143235',array($param['takeTel']),array($takeCode));
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 查看课堂直播
	 */
	public function getClassLiveInfo(){
		$Child = model('Childs');$ClassLive = model('ClassLive');
		$param = $this->param;
		if(empty($param['childId'])){
			return $this->err('参数错误！');
		}
		$cinfo = $Child->where('id',$param['childId'])->where('flag',1)->find();
		if(empty($cinfo)){
			return $this->err('参数错误！');
		}
		if(empty($cinfo['classes_id'])){
			return $this->err('孩子暂未分班，无法查看！');
		}
		$field = 'title,device_id,live_photo,live_hls,live_token,is_on,is_voice,open_time,close_time';
		$linfo = $ClassLive->where('class_id',$cinfo['classes_id'])->where('is_on',1)->where('flag',1)->field($field)->find();
		if(empty($linfo)){
			return $this->err('该班级暂未启用直播设备！');
		}
		//判断设备是否在直播时间段内
		$open_time = strtotime(date('Y-m-d ').$linfo['open_time']);
		$close_time = strtotime(date('Y-m-d ').$linfo['close_time']);
		if(time() < $open_time || time() > $close_time){
			return $this->err('现在还不在直播时段内！');
		}
		unset($linfo['is_on']);
		unset($linfo['open_time']);
		unset($linfo['close_time']);
		return $this->suc($linfo);
	}
	
	/**
	 * 获取请假列表
	 */
	public function getAskLeaveList(){
		$AskLeave = model('AskLeave');
		$param = $this->param;
		if(is_null($param['nextStartId']) || !in_array($param['type'], array(1,2,3)) || empty($param['childId'])){
			return $this->err('参数错误！');
		}
		$where['flag'] = 1;
		$where['type'] = 1;
		$where['user_id'] = $param['childId'];
		if($param['type'] == 1){
			$where['status'] = 0;$where['end_time'] = array('GT',date('Y-m-d H:i:s',time()));//待审核
		}elseif($param['type'] == 3){/*ji add*/
			$where['end_time'] = array('LT',date('Y-m-d H:i:s',time()));/*$map['status'] = array('eq',-1);*/
		}else{
			$where['status'] = array('neq',0);$where['end_time'] = array('GT',date('Y-m-d H:i:s',time()));//审核通过或拒绝
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
		$info = $AskLeave->where('id',$param['id'])->where('flag',1)->where('type',1)->field($field)->find();
		if(empty($info)){
			return $this->err('没有该条请假信息！');
		}
		if($info['end_time'] < date('Y-m-d H:i:s',time())) $info['status'] = -2;/*ji add*/
		return $this->suc($info);
	}
	
	/**
	 * 申请请假
	 */
	public function applyAskLeave(){
		$AskLeave = model('AskLeave');$Child = model('Childs');$Parent = model('Parents');
		$param = $this->param;
		if(empty($param['childId']) || empty($param['leaveNum']) || empty($param['beginTime']) || empty($param['endTime']) || empty($param['reson'])){
			return $this->err('参数错误！');
		}
		//判断时间是否则正确
		if($param['beginTime'] >= $param['endTime']){
			return $this->err('时间格式错误！');
		}
		//判断是否是从属家长
		$info = $Parent->where('id',$param['userId'])->find();
		if($info['type'] != 1){
			return $this->err('从属家长无权限操作！');
		}
		$cinfo = $Child->where('id',$param['childId'])->where('flag',1)->where('status',1)->find();
		if(empty($cinfo)){
			return $this->err('未找到相应孩子的信息！');
		}
		if(empty($cinfo['classes_id'])){
			return $this->err('该孩子所在的班级暂未分班！');
		}
		//找到小孩班级的班主任
		$operate_id = Db::name('TeacherClass')->where('flag',1)->where('classes_id',$cinfo['classes_id'])->where('teacher_type',1)->value('teacher_id');
		if(empty($operate_id)){
			return $this->err('该班级还未分配老师！');
		}
		Db::startTrans();
		try {
			$askData['type'] = 1;
			$askData['user_id'] = $param['childId'];
			$askData['operate_id'] = $operate_id;
			$askData['realname'] = $cinfo['realname'];
			$askData['photo'] = $cinfo['photo'];
			$askData['school_id'] = $cinfo['school_id'];
			$askData['begin_time'] = date('Y-m-d H:i:s',$param['beginTime']);
			$askData['end_time'] =date('Y-m-d H:i:s',$param['endTime']);
			$askData['leave_num'] = $param['leaveNum'];//请假时长
			$askData['reson'] = $param['reson'];
			$askData['status'] = 0;
			$AskLeave->isUpdate(false)->save($askData);
			Db::commit();
			//推送给老师
			$jpushId = Db::name('Teachers')->where('is_job',1)->where('flag',1)->where('id',$operate_id)->value('jpush_id');
			if(!empty($jpushId)){
				$message = '您有一条请假消息待处理！';
				$extra = array('viewCode'=>80010);
				jpushToId($jpushId,$message,2,$extra);
			}
			return $this->suc();
		} catch (Exception $e) {
			Db::rollback();
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 获取出入园列表
	 */
	public function getTimeCardList(){
		$TimeCard = model('TimeCard');
		$param = $this->param;
		if(empty($param['childId'])){
			return $this->err('没有孩子信息！');
		}
		$time = time();
		for ($i = 0;$i < 30;$i++){
			$day = date('Y-m-d',($time - ($i*3600*24)));
			$data[$i]['day'] = $day;
			$allRecord = $TimeCard->where('flag',1)->where('day_time',$day)->where('user_id',$param['childId'])->where('type','in','1,3,4')->field('id,realname,record_time,face_img')->select();
			if(empty($allRecord)){
                $timecardRecord = Null;
            }else{
                foreach ($allRecord as $key=>$value){
                    $recordTime[$key] = explode('|',$value['record_time']);
                    $faceImg[$key] = explode('|',$value['face_img']);
                    $timecardRecord['id'] = $value['id'];
                    $timecardRecord['parentName'] = $value['realname'];
                }
                foreach ($recordTime as $k=>$v){
                    if(count($v) == 1){
                        $recordTimeIn[$k] = $v[0];
                    }else{
                        $recordTimeIn[$k] = $v[0];
                        $recordTimeOut[$k] = $v[1];
                    }
                }
                foreach ($faceImg as $k=>$v){
                    if(count($v) == 1){
                        $faceImgIn[$k] = $v[0];
                    }else{
                        $faceImgIn[$k] = $v[0];
                        $faceImgOut[$k] = $v[1];
                    }
                }
                $keyIn = array_keys($recordTimeIn,max($recordTimeIn));
                if(empty($recordTimeOut)){
                    $timecardRecord['record_time'] = $recordTimeIn[$keyIn[0]];
                    $timecardRecord['face_img'] = $faceImgIn[$keyIn[0]];
                }else{
                    $keyOut = array_keys($recordTimeOut,max($recordTimeOut));
                    $recordTimeFinal = [$recordTimeIn[$keyIn[0]],$recordTimeOut[$keyOut[0]]];
                    $faceImgFinal = [$faceImgIn[$keyIn[0]],$faceImgOut[$keyOut[0]]];
                    $timecardRecord['record_time'] = implode('|',$recordTimeFinal);
                    $timecardRecord['face_img'] = implode('|',$faceImgFinal);
                }
            }
			$data[$i]['recordList'] = $timecardRecord;
			$timecardRecord = [];
            $recordTime = [];
            $faceImg = [];
            $recordTimeIn = [];
            $recordTimeOut = [];
            $faceImgIn = [];
            $faceImgOut = [];
		}
		return $this->suc($data,'',config('view_replace_str.__IMGROOT__'));
		
	}
	
	/**
	 * 个人出入园统计
	 * ————————————————————————————————
	 * 接口已作废
	 */
	public function getTimeCardStatistics(){
		$TimeCard = model('TimeCard');
		$param = $this->param;
		if(empty($param['month'])){
			return $this->err('参数错误！');
		}
		$day = date('t',strtotime($param['month'].'-01'));//这个月有多少天
		$begin = $param['month']."-01";
		$end = $param['month']."-".$day;
		//出勤天数
		$data['onschoolList'] = $TimeCard->where('flag',1)->where('type',1)->whereTime('day_time', 'between', [$begin, $end])
							->where('user_id',$param['childId'])->where('record_time','neq','')->column('day_time');
		//休息天数，排除未来的时间(包含今天)
		if(strtotime($end) > time()){
			$offend = date('Y-m-d',time()-86400);
		}else{
			$offend = $end;
		}
		$dayList = getCompareDateList($begin,$offend);
		foreach ($dayList as $key=>$val){
			if(!in_array($val, $data['onschoolList'])){
				$data['offschoolList'][] = $val;
			}
		}
		//迟到
		$data['lateschoolList'] = $TimeCard->where('flag',1)->where('type',1)->whereTime('day_time', 'between', [$begin, $end])
								->where('user_id',$param['childId'])->where('in_status',-1)->where('record_time','neq','')->column('day_time');
		//早退
		$data['earlyschoolList'] = $TimeCard->where('flag',1)->where('type',1)->whereTime('day_time', 'between', [$begin, $end])
								->where('user_id',$param['childId'])->where('out_status',-1)->where('record_time','neq','')->column('day_time');
		//请假
		$data['askLeaveList'] = $TimeCard->where('flag',1)->where('type',1)->whereTime('day_time', 'between', [$begin, $end])
								->where('user_id',$param['childId'])->where('in_status',2)->column('day_time');
		return $this->suc($data);
	}
	
	/**
	 * 获取课表
	 */
	public function getCourseList(){
		$Child = model('Childs');
		$param = $this->param;
		if(empty($param['childId'])){
			return $this->err('参数错误！');
		}
		$cinfo = $Child->where('id',$param['childId'])->where('flag',1)->find();
		if(empty($cinfo)){
			return $this->err('参数错误！');
		}
		if(empty($cinfo['classes_id'])){
			return $this->err('孩子暂未分班，无法查看！');
		}
		$data = Db::view('CourseTimeClass','id')->view('Course','name','CourseTimeClass.course_id = Course.id')->view('CourseTime','title,weeks,begin_time,end_time','CourseTimeClass.course_time_id = CourseTime.id')->where('Course.flag',1)
		->where('CourseTime.flag',1)->where('CourseTimeClass.class_id',$cinfo['classes_id'])->order('weeks asc')->select();
		return $this->suc($data);
	}
	
	/**
	 * 获取某天的菜谱
	 */
	public function getCookByDay(){
		$Child = model('Childs');
		$param = $this->param;
		if(empty($param['day']) || empty($param['childId'])){
			return $this->err('参数错误！');
		}
		$info = $Child->where('id',$param['childId'])->find();
		$data = Db::view('CookbookDate','id,type')->view('Cookbook','id as cookId,name,img','CookbookDate.cookbook_id = Cookbook.id')
		->where('CookbookDate.school_id',$info['school_id'])->where('Cookbook.school_id',$info['school_id'])
		->where('CookbookDate.flag',1)->where('Cookbook.flag',1)->where('CookbookDate.day_time',$param['day'])
		->select();
		$newData = [
				['type'=>1,'typeName'=>'早餐'],
				['type'=>2,'typeName'=>'早茶'],
				['type'=>3,'typeName'=>'中餐'],
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
	 * 获取作业列表
	 */
	public function getHomeworkList(){
		$Happy = model('Happy');
		$param = $this->param;
		if(is_null($param['nextStartId']) || empty($param['childId']) || !in_array($param['type'], array(1,2))){
			return $this->err('参数错误！');
		}
		$where['Homework.flag'] = 1;
		$where['HomeworkChild.flag'] = 1;
		$where['HomeworkChild.child_id'] = $param['childId'];
		if($param['type'] == 1){
			$where['HomeworkChild.status'] = 0;
		}else{
			$where['HomeworkChild.status'] = array('neq',0);
		}
		$count = Db::view('Homework','title,create_time')->view('HomeworkChild','id,child_id,eval_star,status','Homework.id = HomeworkChild.homework_id','right')
				->where($where)->order('Homework.create_time asc,HomeworkChild.update_time asc')->count();
		if($count < 10){
			$nextStartId = -1;
			$data = Db::view('Homework','title,create_time')->view('HomeworkChild','id,child_id,eval_star,status','Homework.id = HomeworkChild.homework_id','right')
					->where($where)->order('Homework.create_time asc,HomeworkChild.update_time asc')->select();
		}else{
			$nextStartId = $param['nextStartId'];
			$data = Db::view('Homework','title,create_time')->view('HomeworkChild','id,child_id,eval_star,status','Homework.id = HomeworkChild.homework_id','right')
					->where($where)->limit($nextStartId,10)->order('Homework.create_time asc,HomeworkChild.update_time asc')->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		//判断自己是否有收藏
		foreach ($data as $key=>$val){
			$count2 = $Happy->where('child_id',$param['childId'])->where('type',4)->where('from_id',$val['id'])->where('flag',1)->count();
			$data[$key]['isCollect'] = $count2 == 0 ?false:true;
		}
		return $this->suc($data,$nextStartId);
	}
	
	/**
	 * 作业详情
	 */
	public function getHomeworkInfo(){
		$param = $this->param;
		if(empty($param['id']) || empty($param['childId'])){
			return $this->err('参数错误！');
		}
		$where['Homework.flag'] = 1;
		$where['HomeworkChild.child_id'] = $param['childId'];
		$where['HomeworkChild.flag'] = 1;
		$where['HomeworkChild.id'] = $param['id'];
		$info = Db::view('Homework','title,content,img')->view('HomeworkChild','id,child_id,task_content,task_img,eval_star,eval,status,update_time','Homework.id = HomeworkChild.homework_id','right')
				->where($where)->field($field)->order('Homework.create_time asc,HomeworkChild.update_time asc')->find();
		if(empty($info)){
			return $this->err('未找到相关数据！');
		}
		return $this->suc($info,'',config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 提交作业
	 */
	public function submitHomework(){
		$HomeworkChild = model('HomeworkChild');$Child = model('Childs');
		$param = $this->param;
		if(empty($param['childId']) || empty($param['id']) || empty($param['taskContent'])){
			return $this->err('参数错误！');
		}
		$cinfo = $Child->where('flag',1)->where('id',$param['childId'])->find();
		if(empty($cinfo)){
			return $this->err('未找到孩子信息！');
		}
		$info = $HomeworkChild->where('id',$param['id'])->where('child_id',$param['childId'])->where('flag',1)->find();
		if(empty($info)){
			return $this->err('参数错误！');
		}
		//可反复提交，只要未批改
		if($info['status'] == 2){
			return $this->err('作业已批改，无法继续提交！');
		}
		$data['task_content'] = $param['taskContent'];
		$data['task_img'] = $param['taskImg'];
		$data['status'] = 1;
		$result = $HomeworkChild->isUpdate(true)->save($data,['id'=>$param['id']]);
		if($result != false){
			//推送给老师
			//找到小孩班级的班主任
			$tid = Db::name('TeacherClass')->where('flag',1)->where('classes_id',$cinfo['classes_id'])->where('teacher_type',1)->value('teacher_id');
			if(!empty($tid)){
				$jpushId = Db::name('Teachers')->where('is_job',1)->where('flag',1)->where('id',$tid)->value('jpush_id');
				if(!empty($jpushId)){
					$extra = array('viewCode'=>80016);
					jpushToId($jpushId,"请假消息",2,$extra,true);
				}
			}
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 设置某个家长为主接送人
	 */
	public function setMainPick(){
		$Parent = model('Parents');
		$param = $this->param;
		if(empty($param['parentId'])){
			return $this->err('参数错误！');
		}
		//判断是否是从属家长
		$info = $Parent->where('id',$param['userId'])->find();
		if($info['type'] != 1){
			return $this->err('从属家长无权限操作！');
		}
		//判断是否是 主家长的从属家长
		if($param['userId'] != $param['parentId']){
			$count = $Parent->where('parent_id',$param['userId'])->where('id',$param['parentId'])->count();
			if($count == 0){
				return $this->err('未找到相应的从属家长！');
			}
		}
		Db::startTrans();
		try {
			$Parent->where('id',$param['userId'])->whereOr('parent_id',$param['userId'])->setField('is_main_pick',2);
			$Parent->where('id',$param['parentId'])->setField('is_main_pick',1);
			Db::commit();
			return $this->suc();
		} catch (\Exception $e) {
			Db::rollback();
			return $this->err('系统繁忙！');
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
        $parent = model('Parents');
        $info = $parent->where('flag',1)->where('id',$param['userId'])->where('tel',$param['tel'])->value('id');
        if (empty($info)){
            return $this->err('未找到该手机号绑定的账户');
        }else{
            $backData['parentId'] = $info;
            return $this->suc($backData);
        }
    }
    /**
     * 修改绑定手机号
     */
    public function editBindTel(){
        $param = $this->param;
        if (empty($param['tel']) || empty($param['sendCode']) || empty($param['parentId'])){
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
        $parent = model('Parents');
        $oldinfo = $parent->where('flag',1)->where('tel',$param['tel'])->value('id');
        if (empty($oldinfo)){
            $data['tel'] = $param['tel'];
            $where['id'] = $param['parentId'];
            $result = $parent->allowField(true)->save($data,$where);
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