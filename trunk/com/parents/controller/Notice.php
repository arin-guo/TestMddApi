<?php
namespace app\parents\controller;
use app\parents\controller\Base;
use think\Db;
/**
 * 消息管理
 * @author chenlisong E-mail:chenlisong1021@163.com 
 * @version 创建时间：2017年10月27日 下午2:53:19 
 * 类说明
 */
class Notice extends Base{
	
	/**
	 * 获取通讯录列表
	 * 家长默认获得孩子班主任的电话
	 */
	public function getMailtelList(){
		$Mailtel = model('Mailtel');$Child = model('Childs');$Teacher = model('Teachers');
		$param = $this->param;
		if(empty($param['childId'])){
			return $this->err('参数错误！');
		}
		$info = $Child->where('id',$param['childId'])->where('flag',1)->find();
		if(empty($info)){
			return $this->err('参数错误！');
		}
		//获取班主任电话
		$data['teacherList'] = [];
		if(!empty($info['classes_id'])){
			$ids = Db::name('TeacherClass')->where('flag',1)->where('classes_id',$info['classes_id'])->value('GROUP_CONCAT(teacher_id)');
			$data['teacherList'] = $Teacher->where('flag',1)->where('school_id',$info['school_id'])->where('is_job',1)->where('id','in',$ids)->field('0 as id,realname as name,tel')->select();
		}
		//获取自定义的电话号码
		$data['parentsList'] = $Mailtel->where('flag',1)->where('school_id',$info['school_id'])->where('user_id',$param['userId'])->where('type',1)->field('id,name,tel')->select();
		return $this->suc($data,'',config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 新增联系人
	 */
	public function addMailtel(){
		$Mailtel = model('Mailtel');$Child = model('Childs');
		$param = $this->param;
		if(empty($param['name']) || !preg_match("/1[34578]{1}\d{9}$/",$param['tel']) || empty($param['childId'])){
			return $this->err("参数错误！");
		}
		$cinfo = $Child->where('id',$param['childId'])->where('flag',1)->find();
		if(empty($cinfo)){
			return $this->err('参数错误！');
		}
		$data['school_id'] = $cinfo['school_id'];
		$data['user_id'] = $param['userId'];
		$data['type'] = 1;
		$data['photo'] = $param['photo'];
		$data['name'] = $param['name'];
		$data['tel'] = $param['tel'];
		$result = $Mailtel->isUpdate(false)->save($data);
		if($result){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 删除联系人
	 */
	public function delMailtel(){
		$Mailtel = model('Mailtel');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err("参数错误！");
		}
		$count = $Mailtel->where('id',$param['id'])->where('user_id',$param['userId'])->where('flag',1)->where('type',1)->count();
		if($count == 0){
			return $this->err("记录不存在或已被删除！");
		}
		$result = $Mailtel->where('id',$param['id'])->setField('flag',2);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 获取公告列表
	 */
	public function getNoticeList(){
		$Notice = model('Notice');$Child = model('Childs');
		$param = $this->param;
		if(is_null($param['nextStartId']) || empty($param['childId'])){
			return $this->err('参数错误！');
		}
		$info = $Child->where('id',$param['childId'])->where('flag',1)->find();
		if(empty($info)){
			return $this->err('参数错误！');
		}
		$where['flag'] = 1;
		$where['school_id'] = $info['school_id'];
		$count = $Notice->where($where)->count();
		$field = 'id,author,title,content,create_time';
		if($count < 10){
			$nextStartId = -1;
			$data = $Notice->where($where)->field($field)->order('create_time desc')->select();
		}else{
			$nextStartId = $param['nextStartId'];
			$data = $Notice->where($where)->field($field)->limit($nextStartId,10)->order('create_time desc')->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		return $this->suc($data,$nextStartId);
	}
	
	/**
	 * 园所风采
	 */
	public function getNewsList(){
		$News = model('News');$Parent = model('Parents');
		$param = $this->param;
		if(is_null($param['nextStartId']) || !in_array($param['type'], array(1,2))){
			return $this->err('参数错误！');
		}
		$info = $Parent->where('id',$param['userId'])->find();
		$where['flag'] = 1;
		$where['school_id'] = $info['school_id'];
		$where['type'] = $param['type'];
		$count = $News->where($where)->count();
		$field = 'id,photo,intro,type,title,visit_num,create_time';
		if($count < 10){
			$nextStartId = -1;
			$data['list'] = $News->where($where)->field($field)->order('create_time desc')->select();
		}else{
			$nextStartId = $param['nextStartId'];
			$data['list'] = $News->where($where)->field($field)->limit($nextStartId,10)->order('create_time desc')->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		//获取新闻详情的地址
		$url = request()->domain();
		$data['url'] = str_replace("api","admin",$url)."//index/News/getNewInfo?id=";
		return $this->suc($data,$nextStartId,config('view_replace_str.__IMGROOT__'));
	}
}