<?php
namespace app\parents\controller;
use app\parents\controller\Base;
use think\Db;
/**
 * 快乐成长
 * @author chenlisong E-mail:chenlisong1021@163.com 
 * @version 创建时间：2017年10月24日 下午3:26:04 
 * 类说明
 */
class Happy extends Base{
	
	/**
	 * 获得快乐成长列表
	 */
	public function getHappyList(){
		$Child = model('Childs');$Happy = model('Happy');
		$param = $this->param;
		if(is_null($param['nextStartId']) || empty($param['catsCode']) || empty($param['childId'])){
			return $this->err('参数错误！');
		}
		$cinfo = $Child->where('id',$param['childId'])->where('flag',1)->find();
		if(empty($cinfo)){
			return $this->err('参数错误！');
		}
		if(empty($cinfo['classes_id'])){
			return $this->err('孩子暂未分班，无法查看！');
		}
		$data['childInfo'] = ['photo'=>$cinfo['photo'],'realname'=>$cinfo['realname'],'age'=>$cinfo['age']];
		$where['flag'] = 1;
		$where['school_id'] = $cinfo['school_id'];
		$where['child_id'] = $param['childId'];
		$where['class_catscode'] = $param['catsCode'];
		$count = $Happy->where($where)->count();
		$field = 'id,type,user_name,content,reserve,imgs,day_time';
		if($count < 10){
			$nextStartId = -1;
			$data['happyList'] = $Happy->where($where)->field($field)->order('create_time desc')->select();
		}else{
			$nextStartId = $param['nextStartId'];
			$data['happyList'] = $Happy->where($where)->field($field)->limit($nextStartId,10)->order('create_time desc')->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		return $this->suc($data,$nextStartId);
	}
	
	/**
	 * 获取年段集合
	 */
	public function getClassCatscode(){
		$Child = model('Childs');$Type = model('Type');$Subtype = model('Subtype');
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
		$cats_code = Db::name('Classes')->where('id',$cinfo['classes_id'])->value('cats_code');
		$tId = $Type->where('type_name',10002)->where('reserve',$cinfo['school_id'])->where('flag',1)->value('id');
		$data['catsList'] = $Subtype->where('parent_id',$tId)->where('flag',1)->where('subtype_code','<=',$cats_code)->field('subtype_code as catsCode,subtype_name as catsName')->select();
		return $this->suc($data);
	}
	/**
	 * 收藏朋友圈到快乐成长
	 */
	public function collectFriendCircle(){
		$FriendCircle = model('FriendCircle');$Child = model('Childs');$Happy = model('Happy');
		$param = $this->param;
		if(empty($param['id']) || empty($param['childId']) || empty($param['content'])){
			return $this->err('参数错误！');
		}
		$info = $FriendCircle->where('id',$param['id'])->where('flag',1)->find();
		if(empty($info)){
			return $this->err('该内容无法找到或已被删除！');
		}
		$cinfo = $Child->where('id',$param['childId'])->where('flag',1)->find();
		if(empty($cinfo)){
			return $this->err('参数错误！');
		}
		if(empty($cinfo['classes_id'])){
			return $this->err('孩子暂未分班，无法查看！');
		}
		//判断快乐成长每年发布的次数不超过70，含所有类型
		$count = $Happy->where('school_id',$cinfo['school_id'])->where('user_id',$param['userId'])->where('flag',1)->whereTime('create_time','y')->count();
		if($count >= 70){
			return $this->err('每年最多只能发布/收藏70篇快乐成长！');
		}
		$data['school_id'] = $cinfo['school_id'];
		$data['user_id'] = $param['userId'];
		$data['user_name'] = Db::name('Parents')->where('id',$param['userId'])->value('realname');
		$data['child_id'] = $param['childId'];
		$data['type'] = 3;
		$data['content'] = $param['content'];
		$data['imgs'] = $param['imgs'];
		$data['day_time'] = date('Y-m-d');
		$data['from_id'] = $param['id'];
		$data['class_catscode'] = Db::name('Classes')->where('id',$cinfo['classes_id'])->value('cats_code');
		$result = $Happy->isUpdate(false)->save($data);
		if($result){
			//记录收藏信息
			$reback['coutNum'] = 70-$count;
			return $this->suc($reback);
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 收藏作业到快乐成长
	 */
	public function collectHomework(){
		$HomeworkChild = model('HomeworkChild');$Child = model('Childs');$Happy = model('Happy');
		$param = $this->param;
		if(empty($param['id']) || empty($param['childId'])){
			return $this->err('参数错误！');
		}
		$info = $HomeworkChild->where('id',$param['id'])->where('flag',1)->find();
		if(empty($info)){
			return $this->err('未找到相关数据！');
		}
		//批改的作业才可以被收藏
		if($info['status'] != 2){
			return $this->err('作业还未批改，无法收藏！');
		}
		$cinfo = $Child->where('id',$param['childId'])->where('flag',1)->find();
		if(empty($cinfo)){
			return $this->err('参数错误！');
		}
		if(empty($cinfo['classes_id'])){
			return $this->err('孩子暂未分班，无法查看！');
		}
		//判断快乐成长每年发布的次数不超过70，含所有类型
		$count = $Happy->where('school_id',$cinfo['school_id'])->where('user_id',$param['userId'])->where('flag',1)->whereTime('create_time','y')->count();
		if($count >= 70){
			return $this->err('每年最多只能发布/收藏70篇快乐成长！');
		}
		$data['school_id'] = $cinfo['school_id'];
		$data['user_id'] = $param['userId'];
		$data['user_name'] = Db::name('Parents')->where('id',$param['userId'])->value('realname');
		$data['child_id'] = $param['childId'];
		$data['type'] = 4;
		$data['content'] = $info['eval'];
		$data['imgs'] = $info['task_img'];
		$data['reserve'] = $info['eval_star'];
		$data['day_time'] = date('Y-m-d');
		$data['from_id'] = $param['id'];
		$data['class_catscode'] = Db::name('Classes')->where('id',$cinfo['classes_id'])->value('cats_code');
		$result = $Happy->isUpdate(false)->save($data);
		if($result){
			$reback['coutNum'] = 70-$count;
			return $this->suc($reback);
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 发布快乐成长
	 * 日记
	 */
	public function createHappyDayLog(){
		$Child = model('Childs');$Happy = model('Happy');
		$param = $this->param;
		if(empty($param['childId']) || empty($param['content']) || empty($param['dayTime'])){
			return $this->err('参数错误！');
		}
		$cinfo = $Child->where('id',$param['childId'])->where('flag',1)->find();
		if(empty($cinfo)){
			return $this->err('参数错误！');
		}
		if(empty($cinfo['classes_id'])){
			return $this->err('孩子暂未分班，无法查看！');
		}
		//判断快乐成长每年发布的次数不超过70，含所有类型
		$count = $Happy->where('school_id',$cinfo['school_id'])->where('user_id',$param['userId'])->where('flag',1)->whereTime('create_time','y')->count();
		if($count >= 70){
			return $this->err('每年最多只能发布/收藏70篇快乐成长！');
		}
		$data['school_id'] = $cinfo['school_id'];
		$data['user_id'] = $param['userId'];
		$data['user_name'] = Db::name('Parents')->where('id',$param['userId'])->value('realname');
		$data['child_id'] = $param['childId'];
		$data['type'] = 1;
		$data['content'] = $param['content'];
		$data['imgs'] = $param['imgs'];
		$data['day_time'] = $param['dayTime'];
		$data['class_catscode'] = Db::name('Classes')->where('id',$cinfo['classes_id'])->value('cats_code');
		$result = $Happy->isUpdate(false)->save($data);
		if($result){
			$reback['coutNum'] = 70-$count;
			return $this->suc($reback);
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 发布快乐成长
	 * 身高体重
	 */
	public function createHappyBodyLog(){
		$Child = model('Childs');$Happy = model('Happy');
		$param = $this->param;
		if(empty($param['childId']) || empty($param['hgt']) || empty($param['wgt']) || empty($param['dayTime'])){
			return $this->err('参数错误！');
		}
		$cinfo = $Child->where('id',$param['childId'])->where('flag',1)->find();
		if(empty($cinfo)){
			return $this->err('参数错误！');
		}
		if(empty($cinfo['classes_id'])){
			return $this->err('孩子暂未分班，无法查看！');
		}
		//判断快乐成长每年发布的次数不超过70，含所有类型
		$count = $Happy->where('school_id',$cinfo['school_id'])->where('user_id',$param['userId'])->where('flag',1)->whereTime('create_time','y')->count();
		if($count >= 70){
			return $this->err('每年最多只能发布/收藏70篇快乐成长！');
		}
		$data['school_id'] = $cinfo['school_id'];
		$data['user_id'] = $param['userId'];
		$data['user_name'] = Db::name('Parents')->where('id',$param['userId'])->value('realname');
		$data['child_id'] = $param['childId'];
		$data['type'] = 2;
		$data['content'] = $param['hgt'];
		$data['reserve'] = $param['wgt'];
		$data['day_time'] = $param['dayTime'];
		$data['class_catscode'] = Db::name('Classes')->where('id',$cinfo['classes_id'])->value('cats_code');
		$result = $Happy->isUpdate(false)->save($data);
		if($result){
			$reback['coutNum'] = 70-$count;
			return $this->suc($reback);
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 删除快乐成长
	 */
	public function delHappy(){
		$Happy = model('Happy');
		$param = $this->param;
		if(empty($param['id']) || empty($param['childId'])){
			return $this->err('参数错误！');
		}
		$info = $Happy->where('id',$param['id'])->where('child_id',$param['childId'])->where('user_id',$param['userId'])->where('flag',1)->find();
		if(empty($info)){
			return $this->err('该内容无法找到或已被删除！');
		}
		$result = $Happy->where('id',$param['id'])->setField('flag',2);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err("系统繁忙！");
		}
	}
}
