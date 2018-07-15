<?php
namespace app\parents\controller;
use app\parents\controller\Base;
use think\Db;
/**
 * 社交类接口
 * @author chenlisong E-mail:chenlisong1021@163.com 
 * @version 创建时间：2017年10月19日 下午4:08:29 
 * 类说明
 */
class Friend extends Base{
	
	/**
	 * 朋友圈
	 */
	public function friendCircle(){
		$Child = model('Childs');$FriendCircle = model('FriendCircle');$FriendCircleComment = model('FriendCircleComment');
		$Teacher = model('Teachers');$FriendCircleUp = model('FriendCircleUp');$Happy = model('Happy');
		$param = $this->param;
		if(is_null($param['nextStartId']) || empty($param['childId'])){
			return $this->err('参数错误！');
		}
		$cinfo = $Child->where('id',$param['childId'])->where('flag',1)->find();
		if(empty($cinfo)){
			return $this->err('参数错误！');
		}
		if(empty($cinfo['classes_id'])){
			return $this->err('孩子暂未分班，无法查看！');
		}
		$where['flag'] = 1;
		$where['class_id'] = array('in',array(0,$cinfo['classes_id']));
		$where['school_id'] = $cinfo['school_id'];
		$count = $FriendCircle->where($where)->count();
		$field = 'id,content,imgs,up_num,create_time,teacher_id,type,video_url as videoUrl';/*ji change*/
		if($count < 10){
			$nextStartId = -1;
			$data = $FriendCircle->where($where)->field($field)->order('create_time desc')->select();
		}else{
			$nextStartId = $param['nextStartId'];
			$data = $FriendCircle->where($where)->field($field)->limit($nextStartId,10)->order('create_time desc')->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		//获取发送老师的头像与名字
		foreach ($data as $key=>$val){
			if($val['teacher_id'] == 0){//园长
				$headInfo = Db::name('Headmasters')->where('school_id',$cinfo['school_id'])->field('realname,photo')->find();
				$data[$key]['teacher_name'] = $headInfo['realname'];
				$data[$key]['teacher_photo'] = $headInfo['photo'];
			}else{
				$tinfo = $Teacher->where('id',$val['teacher_id'])->field('realname,photo')->find();
				$data[$key]['teacher_name'] = $tinfo['realname'];
				$data[$key]['teacher_photo'] = $tinfo['photo'];
			}
			//获取评论详情
			$field = 'id,content,from_id,from_type,from_name,to_name,create_time';
			$data[$key]['commentList'] = $FriendCircleComment->where('flag',1)->where('is_show',1)->where('friend_circle_id',$val['id'])->field($field)->order('create_time asc')->select();
			//判断自己是否有点赞
			$count = $FriendCircleUp->where('friend_circle_id',$val['id'])->where('type',1)->where('user_id',$param['userId'])->count();
			$data[$key]['isUp'] = $count == 0 ?false:true;
			//判断自己是否有收藏
			$count2 = $Happy->where('child_id',$param['childId'])->where('type',3)->where('from_id',$val['id'])->where('flag',1)->count();
			$data[$key]['isCollect'] = $count2 == 0 ?false:true;/*ji add*/$data[$key]['isdel'] = 2;
		}
		//获取朋友圈背景
		$newData['banner'] = Db::name('Parents')->where('flag',1)->where('id',$param['userId'])->value('banner');
		$newData['data'] = $data;
		return $this->suc($newData,$nextStartId,config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 朋友圈点赞
	 * 发送一条推送，记录不留底
	 */
	public function upFriendCircle(){
		$FriendCircle = model('FriendCircle');$FriendCircleUp = model('FriendCircleUp');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		$info = $FriendCircle->where('id',$param['id'])->where('flag',1)->find();
		if(empty($info)){
			return $this->err('该内容无法找到或已被删除！');
		}
		$count = $FriendCircleUp->where('type',1)->where('user_id',$param['userId'])->where('friend_circle_id',$param['id'])->count();
		if($count != 0){
			return $this->err('您已点过赞，请勿重复操作！');
		}
		$data['friend_circle_id'] = $param['id'];
		$data['user_id'] = $param['userId'];
		$data['type'] = 1;
		$result = $FriendCircleUp->isUpdate(false)->save($data);
		if($result){
			$FriendCircle->where('id',$param['id'])->setInc('up_num');
			return $this->suc();
		}else{
			return $this->err("系统繁忙！");
		}
	}
	
	/**
	 * 取消朋友圈点赞
	 */
	public function cancelUpFriendCircle(){
		$FriendCircle = model('FriendCircle');$FriendCircleUp = model('FriendCircleUp');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		$info = $FriendCircle->where('id',$param['id'])->where('flag',1)->find();
		if(empty($info)){
			return $this->err('该内容无法找到或已被删除！');
		}
		$FriendCircleUp->where('friend_circle_id',$param['id'])->where('type',1)->where('user_id',$param['userId'])->delete();
		$result = $FriendCircle->where('id',$param['id'])->setDec('up_num');
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err("系统繁忙！");
		}
	}
	
	/**
	 * 评论朋友圈
	 */
	public function commentFriendCircle(){
		$FriendCircle = model('FriendCircle');$FriendCircleComment = model('FriendCircleComment');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		$info = $FriendCircle->where('id',$param['id'])->where('flag',1)->find();
		if(empty($info)){
			return $this->err('该内容无法找到或已被删除！');
		}
		$data['friend_circle_id'] = $info['id'];
		$data['content'] = $param['content'];
/*ji add*/$arr = file("http://test.api.mengdd.net/static/mgck.txt");$arr1 = array();
foreach($arr as $k=>$v){
   $arr1["num".$k] = trim($v);
}$data['content'] = str_replace($arr1,"**",$data['content']);		
		$data['from_id'] = $param['userId'];
		$data['from_type'] = 1;
		$data['from_name'] = Db::name('Parents')->where('id',$param['userId'])->value('realname');
		//如果有引用评价
		if(!empty($param['toId'])){
			$data['to_id'] = $param['toId'];
			$data['to_type'] = $param['toType'];
			$data['to_name'] = $param['toName'];
		}
		$result = $FriendCircleComment->isUpdate(false)->save($data);
		if($result !== false){
			return $this->suc(array('id'=>Db::name('FriendCircleComment')->getLastInsID(),'content'=>$data['content']));
		}else{
			return $this->err("系统繁忙！");
		}
	}
	
	/**
	 * 删除朋友圈评论
	 */
	public function delFriendCircleComment(){
		$FriendCircleComment = model('FriendCircleComment');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		$info = $FriendCircleComment->where('id',$param['id'])->where('from_id',$param['userId'])->where('from_type',1)->where('flag',1)->find();
		if(empty($info)){
			return $this->err('该内容无法找到或已被删除！');
		}
		$result = $FriendCircleComment->where('id',$param['id'])->setField('flag',2);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err("系统繁忙！");
		}
	}
	
	/**
	 * 获取列表上显示的数量
	 */
	public function getFriendCircleNum(){
		$FriendCircleComment = model('FriendCircleComment');
		$param = $this->param;
		$where['flag'] = 1;
		$where['to_id'] = $param['userId'];
		$where['to_type'] = 1;
		if(!empty($param['lastTime'])){
			$where['create_time'] = array('gt',intval($param['lastTime']));
		}
		$data['num'] = $FriendCircleComment->where($where)->count();
		return $this->suc($data);
	}
	
	/**
	 * 修改朋友圈背景
	 */
	public function editBanner(){
		$Parents = model('Parents');
		$param = $this->param;
		if(empty($param['imgPath'])){
			return $this->err('参数错误！');
		}
		$result = $Parents->where('id',$param['userId'])->setField('banner',$param['imgPath']);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err("系统繁忙！");
		}
	}
}