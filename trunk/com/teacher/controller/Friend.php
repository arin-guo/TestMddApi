<?php
namespace app\teacher\controller;
use app\teacher\controller\Base;
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
		$FriendCircle = model('FriendCircle');$FriendCircleComment = model('FriendCircleComment');$Teacher = model('Teachers');$FriendCircleUp = model('FriendCircleUp');
		$param = $this->param;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		$tinfo = $Teacher->where('id',$param['userId'])->field('realname,photo,school_id')->find();
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
		$where['teacher_id'] = array('in',array(0,$param['userId']));
		$where['school_id'] = $tinfo['school_id'];
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
				$headInfo = Db::name('Headmasters')->where('school_id',$tinfo['school_id'])->field('realname,photo')->find();
				$data[$key]['teacher_name'] = $headInfo['realname'];
				$data[$key]['teacher_photo'] = $headInfo['photo'];
			}else{
				
				$data[$key]['teacher_name'] = $tinfo['realname'];
				$data[$key]['teacher_photo'] = $tinfo['photo'];
			}
			//获取评论详情
			$field = 'id,content,from_id,from_type,from_name,to_name,create_time';
			$data[$key]['commentList'] = $FriendCircleComment->where('flag',1)->where('is_show',1)->where('friend_circle_id',$val['id'])->field($field)->order('create_time asc')->select();
			//判断自己是否有点赞
			$count = $FriendCircleUp->where('friend_circle_id',$val['id'])->where('type',2)->where('user_id',$param['userId'])->count();
			$data[$key]['isUp'] = $count == 0 ?false:true;
/*ji add*/if($val['teacher_id'] == $param['userId']) $data[$key]['isdel'] = 1;else $data[$key]['isdel'] = 2;
		}
		//获取朋友圈背景
		$newData['banner'] = Db::name('Classes')->where('flag',1)->where('id',$classes_id)->value('banner');
		$newData['data'] = $data;
		return $this->suc($newData,$nextStartId,config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 发表朋友圈
	 */
	public function createFriendCircle(){
		$Teacher = model('Teachers');$FriendCircle = model('FriendCircle');
		$param = $this->param;
		/*if(empty($param['content'])){
			return $this->err('内容不能为空！');
		}*/
		$info = $Teacher->where('id',$param['userId'])->where('flag',1)->find();
		if(empty($info)){
			return $this->err('参数错误！');
		}
		$classes_id = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->value('classes_id');
        $classes_idr = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->value('classes_id');
		if(empty($classes_id) && empty($classes_idr)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}else{
		    if(empty($classes_id)){
		        $classes_id = $classes_idr;
            }
        }
		$data['school_id'] = $info['school_id'];
		$data['class_id'] = $classes_id;
		$data['teacher_id'] = $param['userId'];
		$data['content'] = $param['content'];
/*ji add*/$arr = file("http://test.api.mengdd.net/static/mgck.txt");$arr1 = array();
foreach($arr as $k=>$v){
   $arr1["num".$k] = trim($v);
}$data['content'] = str_replace($arr1,"**",$data['content']);		
		$data['imgs'] = $param['imgs'];
		$data['up_num'] = 0;
		$result = $FriendCircle->isUpdate(false)->save($data);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err("系统繁忙！");
		}
	}

	/**ji add
	 * 删除朋友圈
	 */
	public function delFriendCircle(){
		$FriendCircle = model('FriendCircle');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		if($FriendCircle->where('id',$param['id'])->value('teacher_id') != $param['userId']) return $this->err('你不能删除该条');
		$info = $FriendCircle->where('id',$param['id'])->where('flag',1)->find();
		if(empty($info)){
			return $this->err('该内容无法找到或已被删除！');
		}
		$result = $FriendCircle->where('id',$param['id'])->setField('flag',2);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err("系统繁忙！");
		}
	}

	/**ji add
	 * 发表小视频
	 */
	public function createVideo(){
		$Teacher = model('Teachers');
		$param = $this->param;
		/*if(empty($param['content'])){
			return $this->err('参数错误！');
		}*/
		// $data['content'] = $param['content'];
		// $result = $FriendCircle->isUpdate(true)->save($data,['video_url' => $param['videoUrl']]);
		$FriendCircle = model('FriendCircle');
		$info = $Teacher->where('id',$param['userId'])->where('flag',1)->find();
		$classes_id = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->value('classes_id');
        $classes_idr = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->value('classes_id');
		if(empty($classes_id) && empty($classes_idr)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}else{
		    if(empty($classes_id)){
		        $classes_id = $classes_idr;
            }
        }
		$data['school_id'] = $info['school_id'];$data['class_id'] = $classes_id;$data['teacher_id'] = $param['userId'];
		$data['content'] = $param['content'];$data['video_url'] = $param['videoUrl'];
		$data['imgs'] = $param['imgUrl'];$data['type'] = 2;
		$data['up_num'] = 0;
		$result = $FriendCircle->isUpdate(false)->save($data);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err("系统繁忙！");
		}
	}
	
	/**
	 * 删除朋友圈
	 */
	/*public function delFriendCircle(){
		$FriendCircle = model('FriendCircle');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		$info = $FriendCircle->where('id',$param['id'])->where('teacher_id',$param['userId'])->where('flag',1)->find();
		if(empty($info)){
			return $this->err('该内容无法找到或已被删除！');
		}
		$result = $FriendCircle->where('id',$param['id'])->setField('flag',2);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err("系统繁忙！");
		}
	}*/
	
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
		$count = $FriendCircleUp->where('type',2)->where('user_id',$param['userId'])->where('friend_circle_id',$param['id'])->count();
		if($count != 0){
			return $this->err('您已点过赞，请勿重复操作！');
		}
		$data['friend_circle_id'] = $param['id'];
		$data['user_id'] = $param['userId'];
		$data['type'] = 2;
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
		$FriendCircleUp->where('friend_circle_id',$param['id'])->where('type',2)->where('user_id',$param['userId'])->delete();
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
		$data['from_type'] = 2;
		$data['from_name'] = Db::name('Teachers')->where('id',$param['userId'])->value('realname');
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
		$info = $FriendCircleComment->where('id',$param['id'])->where('from_id',$param['userId'])->where('from_type',2)->where('flag',1)->find();
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
		$FriendCircleComment = model('FriendCircleComment');$FriendCircle = model('FriendCircle');
		$param = $this->param;
		if(!empty($param['lastTime'])){
			$where['create_time'] = array('gt',intval($param['lastTime']));
		}
		//获取老师发的朋友圈ID集合
		$ids = $FriendCircle->where('teacher_id',$param['userId'])->where('flag',1)->value('GROUP_CONCAT(id)');
		$data['num'] = 0;
		if(!empty($ids)){
			$data['num'] = $FriendCircleComment->where($where)->where('friend_circle_id','in',$ids)->count();
		}
		return $this->suc($data);
	}
	
	/**
	 * 修改朋友圈背景
	 */
	public function editBanner(){
		$Class = model('Classes');
		$param = $this->param;
		if(empty($param['imgPath'])){
			return $this->err('参数错误！');
		}
		$classes_id = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->value('classes_id');
		if(empty($classes_id)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}
		$result = $Class->where('id',$classes_id)->setField('banner',$param['imgPath']);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err("系统繁忙！");
		}
	}
}