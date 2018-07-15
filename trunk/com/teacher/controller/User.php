<?php
namespace app\teacher\controller;
use app\teacher\controller\Base;
use think\Db;
use think\Validate;
use think\helper\Time;
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
		$User = model('Teachers');$LoginSession = model('LoginSession');
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
			$where['type'] = 2;
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
		$User = model('Teachers');$LoginSession = model('LoginSession');
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
			$LoginSession->where('user_id',$param['userId'])->where('type',2)->delete();
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
			return $this->err('没有填写内容！');
		}
		$data['type'] = 2;
		$data['status'] = 1;
		$data['create_time'] = time();
		$data['content'] = $param['content'];
		//如果有传schoool_id代表园长信箱
		if(!empty($param['schoolId']) && $param['type'] == 1){//匿名
			
		}else{
			$data['user_id'] = $param['userId'];
			$data['tel'] = Db::name('Teachers')->where('id',$param['userId'])->value('realname');
		}
		$data['school_id'] = empty($param['schoolId'])?0:$param['schoolId'];
		$result = Db::name('Suggestion')->insert($data);
		if($result){
			return $this->suc();
		}else{
			return $this->err("新增失败！");
		}
	}
	
	/**
	 * 查看课堂直播
	 */
	public function getClassLiveInfo(){
		$Child = model('Teachers');$ClassLive = model('ClassLive');
		$param = $this->param;
		$classes_id = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->value('classes_id');
        $classes_idr = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->value('classes_id');
		if(empty($classes_id) && empty($classes_idr)){
			return $this->err('您还未被分配班级，请联系园长！');
		}else{
		    if(empty($classes_id)){
		        $classes_id = $classes_idr;
            }
        }
		$field = 'title,device_id,live_photo,live_hls,live_token,is_on,is_voice,open_time,close_time';
		$linfo = $ClassLive->where('class_id',$classes_id)->where('is_on',1)->where('flag',1)->field($field)->find();
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
	 * 获取临时接送
	 */
	public function getTempTakeList(){
		$Temp = model('TempTakes');
		$param = $this->param;
		if(is_null($param['nextStartId']) || !in_array($param['status'], array(1,2))){
			return $this->err('参数错误！');
		}
		//获取老师所在的班级
		$classes_id = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->value('classes_id');
        $classes_idr = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->value('classes_id');
		if(empty($classes_id) && empty($classes_idr)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}else{
            if(empty($classes_id)){
                $classes_id = $classes_idr;
            }
        }
		$where['flag'] = 1;
		$where['class_id'] = $classes_id;
		$where['status'] = $param['status'];
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
	public function doneTempTakes(){
		$Temp = model('TempTakes');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		//获取老师所在的班级
		$classes_id = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->value('classes_id');
        $classes_idr = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->value('classes_id');
		if(empty($classes_id) && empty($classes_idr)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}else{
            if(empty($classes_id)){
                $classes_id = $classes_idr;
            }
        }
		$where['flag'] = 1;
		$where['id'] = $param['id'];
		$where['class_id'] = $classes_id;
		$where['status'] = 1;
		$info = $Temp->where($where)->find();
		if(empty($info)){
			return $this->err('未找到操作的数据！');
		}
		$data['status'] = 2;
		$data['update_time'] = time();
		$result = $Temp->isUpdate(false)->save($data,['id'=>$param['id']]);
		if($result){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}

	/**ji add
	 * 批量临时接送
	 * 从属家属无权访问
	 */
	public function batchTempTakes(){
		$Temp = model('TempTakes');
		$param = $this->param;
		if(empty($param['ids'])){
			return $this->err('参数错误！');
		}
		//获取老师所在的班级
		$classes_id = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->value('classes_id');
        $classes_idr = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->value('classes_id');
		if(empty($classes_id) && empty($classes_idr)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}else{
            if(empty($classes_id)){
                $classes_id = $classes_idr;
            }
        }
		/*$where['flag'] = 1;
		$where['id'] = $param['id'];
		$where['class_id'] = $classes_id;
		$where['status'] = 1;
		$info = $Temp->where($where)->find();
		if(empty($info)){
			return $this->err('未找到操作的数据！');
		}*/
		$data['status'] = 2;
		$data['update_time'] = time();
		$result = $Temp->isUpdate(false)->/*where(array('status'=>1,'class_id'=>$classes_id,'id'=>array('IN',$param['ids'])))->*/save($data,['status'=>1,'class_id'=>$classes_id,'id'=>['IN',$param['ids']]]);
		if($result){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 获取请假列表（学生）
	 */
	public function getAskLeaveList(){
		$AskLeave = model('AskLeave');
		$param = $this->param;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		$where['flag'] = 1;
		$where['type'] = 1;
		$where['operate_id'] = $param['userId'];
		if($param['type'] == 2){
			$where['status'] = array('neq',0);$where['end_time'] = array('GT',date('Y-m-d H:i:s',time()));
		}elseif($param['type'] == 3){/*ji add*/
			$where['end_time'] = array('LT',date('Y-m-d H:i:s',time()));
		}else{
			$where['status'] = 0;$where['end_time'] = array('GT',date('Y-m-d H:i:s',time()));
		}
		$count = $AskLeave->where($where)->count();
		$field = 'id,realname,reson,status,begin_time,end_time';
		if($count < 10){
			$nextStartId = -1;
			$data = $AskLeave->where($where)->field($field)->order('create_time desc')->select();
		}else{
			$nextStartId = $param['nextStartId'];
			$data = $AskLeave->where($where)->field($field)->limit($nextStartId,10)->order('create_time desc')->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		return $this->suc($data,$nextStartId);
	}

	/**ji add 另一
	 * 获取请假列表（学生）
	 */
	public function anotherGetAskLeaveList(){
		$AskLeave = model('AskLeave');
		$param = $this->param;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		$where['flag'] = 1;
		$where['type'] = 1;
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
		$info = $AskLeave->where('id',$param['id'])->where('flag',1)->where('type',1)->field($field)->find();
		if(empty($info)){
			return $this->err('参数错误！');
		}
		if($info['end_time'] < date('Y-m-d H:i:s',time())) $info['status'] = -2;/*ji add*/
		return $this->suc($info);
	}
	/**
	 * 同意请假
	 * 同意后需要插入考勤表
	 */
	public function agreeAskLeave(){
		$AskLeave = model('AskLeave');$TimeCard = model('TimeCard');$Child = model('Childs');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		//获取老师所在的班级
		$where['flag'] = 1;
		$where['id'] = $param['id'];
		$where['status'] = 0;
		$info = $AskLeave->where($where)->find();
		if(empty($info)){
			return $this->err('未找到操作的数据！');
		}
		//往考勤表插入数据
		Db::startTrans();
		try {
			$cinfo = $Child->where('id',$info['user_id'])->where('flag',1)->where('status',1)->find();
			//当请假很多天
			$dateList = getCompareDateList($info['begin_time'], $info['end_time']);
			foreach ($dateList as $key=>$val){
				//需要在打卡记录表添加请假记录
				$tinfo = $TimeCard->where('type',1)->where('flag',1)->where('school_id',$info['school_id'])->where('user_id',$info['user_id'])
						->where('day_time', $val)->find();
				if(empty($tinfo)){
					$data['type'] = 1;
					$data['school_id'] = $cinfo['school_id'];
					$data['user_id'] = $info['user_id'];//插入的小孩ID
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
		$AskLeave = model('AskLeave');
		$param = $this->param;
		if(empty($param['id']) || empty($param['backReson'])){
			return $this->err('参数错误！');
		}
		//获取老师所在的班级
		$where['flag'] = 1;
		$where['id'] = $param['id'];
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
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 获取出入园记录
	 */
	public function getTimeCardList(){
		$TimeCard = model('TimeCard');$Child = model('Childs');
		$param = $this->param;
		if(empty($param['day']) || !in_array($param['type'],array(1,2,3,4))){
			return $this->err('参数错误！');
		}
		//获取老师所在的班级
		$tinfo = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->field('classes_id,school_id')->find();
        $tinfor = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->field('classes_id,school_id')->find();
		if(empty($tinfo) && empty($tinfor)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}else{
		    if(empty($tinfo)){
		        $tinfo = $tinfor;
            }
        }
		switch ($param['type']){
			case 1://未入园
				//获取该班级所有学生
				$data = $Child->where('classes_id',$tinfo['classes_id'])->where('status',1)->where('flag',1)->field('id,realname')->select();
				//排除已经打卡的学生
				$ids = $TimeCard->where('type',1)->where('day_time',$param['day'])->where('flag',1)->where('school_id',$tinfo['school_id'])->value('GROUP_CONCAT(user_id)');
				$newData = array();$i = 0;
				foreach($data as $key=>$val){
					if(!in_array($val['id'], explode(',', $ids))){
						$newData[$i] = $val;
						$i++;
					}
				}
				break;
			case 2://在园中，迟到的也算在园中
				$ids = $TimeCard->where('day_time',$param['day'])->where('flag',1)->where('is_in',1)->where('in_status','neq',2)->where('school_id',$tinfo['school_id'])->where('type',1)->value('GROUP_CONCAT(user_id)');
				$newData = $Child->where('classes_id',$tinfo['classes_id'])->where('status',1)->where('flag',1)->where('id','in',$ids)->field('id,realname')->select();
				break;
			case 3://已出园，早退的也算已出园
				$ids = $TimeCard->where('day_time',$param['day'])->where('flag',1)->where('is_in',2)->where('in_status','neq',2)->where('school_id',$tinfo['school_id'])->where('type',1)->value('GROUP_CONCAT(user_id)');
				$newData = $Child->where('classes_id',$tinfo['classes_id'])->where('status',1)->where('flag',1)->where('id','in',$ids)->field('id,realname')->select();
				break;
			case 4://已请假
				$ids = $TimeCard->where('day_time',$param['day'])->where('flag',1)->where('in_status',2)->where('school_id',$tinfo['school_id'])->where('type',1)->value('GROUP_CONCAT(user_id)');
				$newData = $Child->where('classes_id',$tinfo['classes_id'])->where('status',1)->where('flag',1)->where('id','in',$ids)->field('id,realname')->select();
				break;
		}
		
		return $this->suc($newData);
	}
	
	/**
	 * 出入园月统计
	 * ————————————————————————————————
	 * 接口已作废
	 */
	public function getMonthTimeCardStatistics(){
		$TimeCard = model('TimeCard');$Child = model('Childs');
		$param = $this->param;
		//获取老师所在的班级
		$tinfo = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->field('classes_id,school_id')->find();
		if(empty($tinfo)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}
		//获取该班级所有学生
		$data = $Child->where('classes_id',$tinfo['classes_id'])->where('status',1)->where('flag',1)->field('id,realname')->select();
		foreach ($data as $key=>$val){
			//入园次数
			$data[$key]['outInNum'] = $TimeCard->where('flag',1)->where('type',1)->whereTime('record_time','m')->where('user_id',$val['id'])->where('record_time','neq','')->group('user_id')->count();
			//请假次数
			$data[$key]['askLeaveNum'] = $TimeCard->where('flag',1)->where('type',1)->whereTime('record_time','m')->where('user_id',$val['id'])->where('in_status',2)->group('user_id')->count();
			//打卡次数
			$data[$key]['recordNum'] = $TimeCard->where('flag',1)->where('type',1)->whereTime('record_time','m')->where('user_id',$val['id'])->where('record_time','neq','')->group('user_id')->value('num');
		}
		return $this->suc($data);
	}
	
	/**
	 * 获取出入园识别次数统计
	 */
	public function getMonthTimeCardNumStatistics(){
		$TimeCard = model('TimeCard');$Child = model('Childs');
		$param = $this->param;
		if(empty($param['month'])){
			return $this->err('参数错误！');
		}
		$day = date('t',strtotime($param['month']));//这个月有多少天
		$begin = $param['month']."-01";
		$end = $param['month']."-".$day;
		//获取老师所在的班级
		$tinfo = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->field('classes_id,school_id')->find();
        $tinfor = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->field('classes_id,school_id')->find();
		if(empty($tinfo) && empty($tinfor)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}else{
		    if(empty($tinfo)){
		        $tinfo = $tinfor;
            }
        }
		//获取该班级所有学生
		$data = $Child->where('classes_id',$tinfo['classes_id'])->where('status',1)->where('flag',1)->field('id,realname')->select();
		foreach ($data as $key=>$val){
			//入园识别次数
			$data[$key]['inNum'] = $TimeCard->where('flag',1)->where('type',1)->whereTime('day_time', 'between', [$begin, $end])->where('user_id',$val['id'])->sum('in_num');
			//出园识别次数
			$data[$key]['outNum'] = $TimeCard->where('flag',1)->where('type',1)->whereTime('day_time', 'between', [$begin, $end])->where('user_id',$val['id'])->sum('out_num');
		}
		return $this->suc($data);
	}
	
	/**
	 * 根据孩子ID获取主从家长电话
	 */
	public function getPhoneByChildId(){
		$param = $this->param;
		if(empty($param['childId'])){
			return $this->err('参数错误！');
		}
		$data = Db::view('ParentChild','relation')->view('Parents','realname,tel','ParentChild.parent_id = Parents.id')->where('ParentChild.flag',1)->where('Parents.flag',1)->where('ParentChild.child_id',$param['childId'])->select();
		return $this->suc($data);
	}
	
	/**
	 * 获取教师请假列表
	 */
	public function getTeacherAskLeaveList(){
		$AskLeave = model('AskLeave');
		$param = $this->param;
		if(is_null($param['nextStartId']) /*|| !in_array($param['status'],array(0,1,-1))*/){
			return $this->err('参数错误！');
		}
		$where['flag'] = 1;
		$where['type'] = 2;
		$where['user_id'] = $param['userId'];
		if($param['type'] == 2){
			$where['status'] = array('neq',0);$where['end_time'] = array('GT',date('Y-m-d H:i:s',time()));
		}elseif($param['type'] == 3){/*ji add*/
			$where['end_time'] = array('LT',date('Y-m-d H:i:s',time()));
		}else{
			$where['status'] = 0;$where['end_time'] = array('GT',date('Y-m-d H:i:s',time()));
		}
		$count = $AskLeave->where($where)->count();
		$field = 'id,realname,reson,status,begin_time,end_time';
		if($count < 10){
			$nextStartId = -1;
			$data = $AskLeave->where($where)->field($field)->order('create_time desc')->select();
		}else{
			$nextStartId = $param['nextStartId'];
			$data = $AskLeave->where($where)->field($field)->limit($nextStartId,10)->order('create_time desc')->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		return $this->suc($data,$nextStartId);
	}

	/**ji add另一
	 * 获取教师请假列表
	 */
	public function anotherGetTeacherAskLeaveList(){
		$AskLeave = model('AskLeave');
		$param = $this->param;
		if(is_null($param['nextStartId'])/* || !in_array($param['status'],array(0,1,-1))*/){
			return $this->err('参数错误！');
		}
		$where['flag'] = 1;
		$where['type'] = 2;
		$where['user_id'] = $param['userId'];
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
	 * 申请请假
	 */
	public function applyTeacherAskLeave(){
		$AskLeave = model('AskLeave');$Teacher = model('Teachers');
		$param = $this->param;
		if(empty($param['leaveNum']) || empty($param['beginTime']) || empty($param['endTime']) || empty($param['reson'])){
			return $this->err('参数错误！');
		}
		//判断时间是否则正确
		if($param['beginTime'] >= $param['endTime']){
			return $this->err('申请的时间小于当前时间！');
		}
		//找到老师所在学校的园长
		$info = $Teacher->where('id',$param['userId'])->find();
		$hinfo = Db::name('Headmasters')->where('flag',1)->where('school_id',$info['school_id'])->field('id,jpush_id,approval_switch,disturb_switch')->find();
		if(empty($hinfo)){
			return $this->err('该幼儿园的园长账户未找到！');
		}
		Db::startTrans();
		try {
			$askData['type'] = 2;
			$askData['user_id'] = $param['userId'];
			$askData['operate_id'] = $hinfo['id'];
			$askData['realname'] = $info['realname'];
			$askData['photo'] = $info['photo'];
			$askData['school_id'] = $info['school_id'];
			$askData['begin_time'] = date('Y-m-d H:i:s',$param['beginTime']);
			$askData['end_time'] = date('Y-m-d H:i:s',$param['endTime']);
			$askData['leave_num'] = $param['leaveNum'];//请假时长
			$askData['reson'] = $param['reson'];
			$askData['status'] = 0;
			$AskLeave->isUpdate(false)->save($askData);
			Db::commit();
			if(!empty($hinfo['jpush_id']) && $hinfo['approval_switch'] == 2){//勿扰模式不推送
				if($hinfo['disturb_switch'] == 1 && (time() < (strtotime(date('Y-m-d')) + 10*3600) || time() > (strtotime(date('Y-m-d')) + 22*3600))){
					
				}else{
					$message = '您有一条请假信息待处理！';
					$extra = array('viewCode'=>80004);
					jpushToId($hinfo['jpush_id'],$message,3,$extra);
				}
			}
			return $this->suc();
		} catch (Exception $e) {
			Db::rollback();
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 获取请假详情
	 */
	public function getTeacherAskLeaveInfo(){
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
	
	/**
	 * 获取教师每日打卡信息
	 */
	public function getTeacherTimeCardByDay(){
		$TimeCard = model('TimeCard');
		$param = $this->param;
		if(empty($param['day'])){
			return $this->err('参数错误！');
		}
		$where['flag'] = 1;
		$where['type'] = 2;
		$where['day_time'] = $param['day'];
		$where['user_id'] = $param['userId'];
		$data = $TimeCard->where($where)->field('id,realname,photo,day_time,record_time,in_status,out_status,face_img')->find();
		return $this->suc($data);
	}
	
	/**
	 * 获取教师端请假月统计
	 */
	public function getTeacherMonthTimeCardStatistics(){
		$TimeCard = model('TimeCard');
		$param = $this->param;
		if(empty($param['month'])){
			return $this->err('参数错误！');
		}
		$day = date('t',strtotime($param['month']));//这个月有多少天
		$begin = $param['month']."-01";
		$end = $param['month']."-".$day;
		//出勤天数
		$data['onworkList'] = $TimeCard->where('flag',1)->where('type',2)->whereTime('day_time', 'between', [$begin, $end])->where('user_id',$param['userId'])->where('record_time','neq','')->column('day_time');
		//休息天数
		$days = getMonthDays($param['month']);
		foreach ($days as $key=>$val){
			if(!in_array($val, $data['onworkList'])){
				$data['offworkList'][] = $val;
			}
		}
		//早退次数
		$data['lateList'] = $TimeCard->where('flag',1)->where('type',2)->whereTime('day_time', 'between', [$begin, $end])->where('user_id',$param['userId'])->where('in_status','-1')->column('day_time');
		//迟到次数
		$data['earlyList'] = $TimeCard->where('flag',1)->where('type',2)->whereTime('day_time', 'between', [$begin, $end])->where('user_id',$param['userId'])->where('out_status','-1')->column('day_time');
		//请假统计
		$data['leaveList'] = $TimeCard->where('flag',1)->where('type',2)->whereTime('day_time', 'between', [$begin, $end])->where('user_id',$param['userId'])->where('in_status','2')->column('day_time');
		return $this->suc($data);
	}
	
	/**
	 * 获取课表
	 */
	public function getCourseList(){
		$param = $this->param;
		//获取老师所在的班级
		$classes_id = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->value('classes_id');
        $classes_idr = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->value('classes_id');
		if(empty($classes_id) && empty($classes_idr)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}else{
		    if(empty($classes_id)){
		        $classes_id = $classes_idr;
            }
        }
		$data = Db::view('CourseTimeClass','id')->view('Course','name','CourseTimeClass.course_id = Course.id')->view('CourseTime','title,weeks,begin_time,end_time','CourseTimeClass.course_time_id = CourseTime.id')->where('Course.flag',1)
				->where('CourseTime.flag',1)->where('CourseTimeClass.class_id',$classes_id)->order('weeks asc')->select();
		return $this->suc($data);
	}
	
	/**
	 * 获取班级详情
	 */
	public function getClassInfo(){
		$Subtype = model('Subtype');$Type = model('Type');
		$param = $this->param;
		//获取老师所在的班级
		$info = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->field('classes_id,school_id')->find();
        $infor = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->field('classes_id,school_id')->find();
		if(empty($info) && empty($infor)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}else{
		    if(empty($info)){
		        $info = $infor;
            }
        }
		//获取将升班的cats_code
		$tId = $Type->where('type_name',10002)->where('reserve',$info['school_id'])->where('flag',1)->value('id');
		$data['catsList'] = $Subtype->where('parent_id',$tId)->where('flag',1)->field('subtype_code as catsCode,subtype_name as catsName')->select();
		//男女人数与总人数
		$data['info']['boyNum'] = 0;
		$data['info']['girlNum'] = 0;
		$data['info']['totalNum'] = 0;
		$childList = Db::name('Childs')->where('flag',1)->where('classes_id',$info['classes_id'])->where('status',1)->field('id,realname,code,sex,birthday,age,id_card,code')->select();
		foreach ($childList as $key=>$val){
			if($val['sex'] == 1){
				$data['info']['boyNum'] ++;
			}else{
				$data['info']['girlNum'] ++;
			}
			$data['info']['totalNum'] ++;
		}
		$data['childList'] = $childList;
		return $this->suc($data);
	}
	
	/**
	 * 升班或退班操作
	 */
	public function addClassNotice(){
		$ClassNotice = model('ClassNotice');$Classes = model('Classes');$Child = model('Childs');
		$param = $this->param;
		if(!in_array($param['type'], array(1,2)) || empty($param['reserve'])){
			return $this->err('参数错误！');
		}
		//获取老师所在的班级
		$info = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->field('classes_id,school_id')->find();
        $infor = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->field('classes_id,school_id')->find();
		if(empty($info) && empty($infor)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}else{
		    if(empty($info)){
		        $info = $infor;
            }
        }
		//升班操作
		if($param['type'] == 1){
			//找到班级信息
			$classInfo = $Classes->where("id",$info['classes_id'])->find();
			//判断班号是否重复
			$map['name'] = $classInfo['name'];
			$map['school_id'] = $info['school_id'];
			$map['cats_code'] = $param['reserve'];
			$map['flag'] = 1;
			$count = $Classes->where($map)->count();
			if($count != 0){
				return $this->err('班号已重复，无法执行此操作！');
			}
			$data['type'] = 1;
			$data['class_id'] = $info['classes_id'];
			$data['school_id'] = $info['school_id'];
			$data['reserve'] = $param['reserve'];
			$data['status'] = 0;
			$result = $ClassNotice->isUpdate(false)->save($data);
			if($result){
				$hinfo = Db::name('Headmasters')->where('flag',1)->where('school_id',$info['school_id'])->field('id,jpush_id,approval_switch,disturb_switch')->find();
				if(!empty($hinfo['jpush_id']) && $hinfo['approval_switch'] == 2){//勿扰模式不推送
					if($hinfo['disturb_switch'] == 1 && (time() < (strtotime(date('Y-m-d')) + 10*3600) || time() > (strtotime(date('Y-m-d')) + 22*3600))){
							
					}else{
						$message = '您有一项升班请求待处理！';
						$extra = array('viewCode'=>80005);
						jpushToId($hinfo['jpush_id'],$message,3,$extra);
					}
				}
				return $this->suc();
			}else{
				return $this->err('系统繁忙！');
			}
		}else{
			//判断是否找到对应学生
			$count = $Child->where('id',$param['reserve'])->where('classes_id',$info['classes_id'])->where('school_id',$info['school_id'])->where('flag',1)->where('status',1)->count();
			if($count == 0){
				return $this->err('未找到对应的学生！');
			}
			$result = $Child->where('id',$param['reserve'])->setField('classes_id',0);
			if($result !== false){
				return $this->suc();
			}else{
				return $this->err('系统繁忙！');
			}
		}
	}
	
	/**
	 * 修改学号
	 */
	public function editChildCode(){
		$Child = model('Childs');
		$param = $this->param;
		//获取老师所在的班级
		$classes_id = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->value('classes_id');
        $classes_idr = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->value('classes_id');
		if(empty($classes_id) && empty($classes_idr)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}else{
		    if(empty($classes_id)){
		        $classes_id = $classes_idr;
            }
        }
		if(empty($param['id']) || empty($param['code'])){
			return $this->err('参数错误！');
		}
		$count = $Child->where('flag',1)->where('id',$param['id'])->where('classes_id',$classes_id)->count();
		if($count == 0){
			return $this->err('未找到相应的学生信息！');
		}
		//判断学号是否被占用
		$count = $Child->where('flag',1)->where('code',$param['code'])->count();
		if($count != 0){
			return $this->err('学号已存在！');
		}
		$result = $Child->where('id',$param['id'])->setField('code',$param['code']);
		if($result != false){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 获取某天的菜谱
	 */
	public function getCookByDay(){
		$Teachers = model('Teachers');
		$param = $this->param;
		if(empty($param['day'])){
			return $this->err('参数错误！');
		}
		$info = $Teachers->where('id',$param['userId'])->find();
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
		$Homework = model('Homework');$HomeworkChild = model('HomeworkChild');
		$param = $this->param;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		//获取老师所在的班级
		$classes_id = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->value('classes_id');
        $classes_idr = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->value('classes_id');
		if(empty($classes_id) && empty($classes_idr)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}else{
		    if(empty($classes_id)){
		        $classes_id = $classes_idr;
            }
        }
        $where['school_id'] = Db::name('Teachers')->where('id',$param['userId'])->value('school_id');
		$where['flag'] = 1;
		$where['class_id'] = $classes_id;
		$count = $Homework->where($where)->count();
		$field = 'id,title,create_time';
		if($count < 10){
			$nextStartId = -1;
			$data = $Homework->where($where)->field($field)->order('create_time desc')->select();
		}else{
			$nextStartId = $param['nextStartId'];
			$data = $Homework->where($where)->field($field)->limit($nextStartId,10)->order('create_time desc')->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		//获取班级总人数，已提交人数，已批阅人数
		foreach ($data as $key=>$val){
			$data[$key]['totalNum'] = $HomeworkChild->where('homework_id',$val['id'])->where('flag',1)->count();
			$data[$key]['submitNum'] = $HomeworkChild->where('homework_id',$val['id'])->where('flag',1)->where('status',1)->count();
			$data[$key]['evalNum'] = $HomeworkChild->where('homework_id',$val['id'])->where('flag',1)->where('status',2)->count();
		}
		return $this->suc($data,$nextStartId);
	}
	
	/**
	 * 获取作业详情
	 */
	public function getHomeworkInfo(){
		$Homework = model('Homework');$HomeworkChild = model('HomeworkChild');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		//获取老师所在的班级
		$classes_id = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->value('classes_id');
        $classes_idr = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->value('classes_id');
		if(empty($classes_id) && empty($classes_idr)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}else{
		    if(empty($classes_id)){
                $classes_id = $classes_idr;
            }
        }
		$field = 'id,title,create_time,img,content';
		$info = $Homework->where('id',$param['id'])->where('flag',1)->field($field)->find();
		if(empty($info)){
			return $this->err('参数错误！');
		}
		//获取班级总人数，已提交人数，已批阅人数
		$info['totalNum'] = $HomeworkChild->where('homework_id',$info['id'])->where('flag',1)->count();
		$info['submitNum'] = $HomeworkChild->where('homework_id',$info['id'])->where('flag',1)->where('status',1)->count();
		$info['evalNum'] = $HomeworkChild->where('homework_id',$info['id'])->where('flag',1)->where('status',2)->count();
		$info['avgScore'] = (float) $HomeworkChild->where('homework_id',$info['id'])->where('flag',1)->where('status',2)->avg('eval_star');
		return $this->suc($info,'',config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 发布作业
	 */
	public function createHomework(){
		$Homework = model('Homework');$Child = model('Childs');$HomeworkChild = model('HomeworkChild');
		$param = $this->param;
		if(empty($param['title']) || empty($param['content'])){
			return $this->err('参数错误！');
		}
		//获取老师所在的班级
		$classes_id = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->value('classes_id');
        $classes_idr = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->value('classes_id');
		if(empty($classes_id) && empty($classes_idr)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}else{
		    if(empty($classes_id)){
		        $classes_id = $classes_idr;
            }
        }
        //检查班级有无学生
        $ids = $Child->where('flag',1)->where('status',1)->where('classes_id',$classes_id)->value('GROUP_CONCAT(id)');
        if($ids != 0){
            Db::startTrans();
            try {
                $data['school_id'] = Db::name('Teachers')->where('id',$param['userId'])->value('school_id');
                $data['title'] = $param['title'];
                $data['content'] = $param['content'];
                $data['img'] = $param['img'];
                $data['class_id'] = $classes_id;
                $data['teacher_id'] = $param['userId'];
                $Homework->isUpdate(false)->save($data);
                //获取到班级所有学生，然后批量新增待提交数据
                $ids = explode(',', $ids);
                foreach ($ids as $key=>$val){
                    $cData[$key]['homework_id'] = $Homework->id;
                    $cData[$key]['child_id'] = $val;
                    $cData[$key]['status'] = 0;
                }
                $HomeworkChild->saveAll($cData);
                //推送给该班级所有学生的家长
                $ids = $Child->where('flag',1)->where('status',1)->where('classes_id',$classes_id)->value('GROUP_CONCAT(id)');
                if(!empty($ids)){
                    //获取所有从属家长
                    $pids = Db::name('ParentChild')->where('flag',1)->where('child_id','in',$ids)->value('GROUP_CONCAT(parent_id)');
                    if(!empty($pids)){
                        $jpushIds = Db::name('Parents')->where('flag',1)->where('status',1)->where('jpush_id','neq','')->where('id','in',$pids)->value('GROUP_CONCAT(jpush_id)');
                        if(!empty($jpushIds)){
                            $message = '老师发布了新作业，记得及时完成哦！';
                            $extra = array('viewCode'=>80006);
                            jpushToId($jpushIds,$message,1,$extra);
                        }
                    }
                }
                Db::commit();
                return $this->suc();
            } catch (Exception $e) {
                Db::rollback();
                return $this->err('系统繁忙！');
            }
        }else{
            return $this->err('您的班级暂无学生！');
        }

	}
	
	/**
	 * 获取审阅作业列表
	 */
	public function getAuditHomeworkList(){
		$Homework = model('Homework');$HomeworkChild = model('HomeworkChild');
		$param = $this->param;
		if(is_null($param['nextStartId']) || !in_array($param['type'], array(1,2,3,4))){
			return $this->err('参数错误！');
		}
        //获取老师所在的班级
        $classes_id = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->value('classes_id');
        $classes_idr = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->value('classes_id');
        if(empty($classes_id) && empty($classes_idr)){
            return $this->err('您还未被分配班级，如有疑问，请联系园长！');
        }else{
            if(empty($classes_id)){
                $classes_id = $classes_idr;
            }
        }
        $where['Homework.class_id'] = $classes_id;
        $where['Homework.school_id'] = Db::name('Teachers')->where('id',$param['userId'])->value('school_id');
		$where['Homework.flag'] = 1;
		$where['HomeworkChild.flag'] = 1;
		//1为待批改，2为待提交，3为已批改，4为历史
		switch ($param['type']){
			case 1:
				$where['HomeworkChild.create_time'] = array('gt',time() - 7*86400);//七天内
				$where['status'] = 1;
				break;
			case 2:
				$where['HomeworkChild.create_time'] = array('gt',time() - 7*86400);//七天内
				$where['status'] = 0;
				break;
			case 3:
				$where['HomeworkChild.create_time'] = array('gt',time() - 7*86400);//七天内
				$where['status'] = 2;
				break;
			case 4:
				$where['HomeworkChild.create_time'] = array('lt',time() - 7*86400);//七天以前
				break;
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
		$backData['total'] = $count;
		foreach ($data as $key=>$val){
			$data[$key]['childName'] = Db::name('Childs')->where('id',$val['child_id'])->value('realname');
		}
		$backData['homeworkList'] = $data;
		return $this->suc($backData,$nextStartId);
	}
	
	/**
	 * 获取批改的作业详情
	 */
	public function getAuditHomeworkInfo(){
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		$where['Homework.flag'] = 1;
		$where['HomeworkChild.flag'] = 1;
		$where['HomeworkChild.id'] = $param['id'];
		$info = Db::view('Homework','title,content,img')->view('HomeworkChild','id,child_id,task_content,task_img,eval_star,eval,status,update_time','Homework.id = HomeworkChild.homework_id','right')
				->where($where)->field($field)->order('Homework.create_time asc,HomeworkChild.update_time asc')->find();
		if(empty($info)){
			return $this->err('参数错误！');
		}
		$info['childName'] = Db::name('Childs')->where('id',$info['child_id'])->value('realname');
		return $this->suc($info,'',config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 批改作业
	 */
	public function auditHomework(){
		$HomeworkChild = model('HomeworkChild');
		$param = $this->param;
		if(empty($param['id']) || empty($param['eval']) || !in_array($param['evalStar'], array(1,2,3,4,5))){
			return $this->err('参数错误！');
		}
		$info = $HomeworkChild->where('id',$param['id'])->find();
		if(empty($info)){
			return $this->err('参数错误！');
		}
		if($info['status'] != 1){
			return $this->err('您已批改过该作业，请勿重复提交！');
		}
		$data['status'] = 2;
		$data['eval_star'] = $param['evalStar'];
		$data['eval'] = $param['eval'];
		$result = $HomeworkChild->where('id',$param['id'])->update($data);
		if($result != false){
			//推送给孩子的从属家长
			$pids = Db::name('ParentChild')->where('flag',1)->where('child_id',$info['child_id'])->value('GROUP_CONCAT(parent_id)');
			if(!empty($pids)){
				$jpushIds = Db::name('Parents')->where('flag',1)->where('status',1)->where('jpush_id','neq','')->where('id','in',$pids)->value('GROUP_CONCAT(jpush_id)');
				if(!empty($jpushIds)){
					$message = '老师批复了您孩子的作业，赶快查看吧！';
					$extra = array('viewCode'=>80007);
					jpushToId($jpushIds,$message,1,$extra);
				}
			}
			return $this->suc();
		}else{
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
        $teacher = model('Teachers');
        $info = $teacher->where('flag',1)->where('id',$param['userId'])->where('tel',$param['tel'])->value('id');
        if (empty($info)){
            return $this->err('未找到该手机号绑定的账户');
        }else{
            $backData['teacherId'] = $info;
            return $this->suc($backData);
        }
    }
    /**
     * 修改绑定手机号
     */
    public function editBindTel(){
        $param = $this->param;
        if (empty($param['tel']) || empty($param['sendCode']) || empty($param['teacherId'])){
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
        $teacher = model('Teachers');
        $oldinfo = $teacher->where('flag',1)->where('tel',$param['tel'])->value('id');
        if (empty($oldinfo)){
            $data['tel'] = $param['tel'];
            $where['id'] = $param['teacherId'];
            $result = $teacher->allowField(true)->save($data,$where);
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