<?php
namespace app\leader\controller;
use app\leader\controller\Base;
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
		$FriendCircle = model('FriendCircle');$FriendCircleComment = model('FriendCircleComment');$Teacher = model('Teachers');$Leader = model('Headmasters');$FriendCircleUp = model('FriendCircleUp');
		$param = $this->param;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
		$where['flag'] = 1;
		$where['school_id'] = $info['school_id'];
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
				$data[$key]['teacher_name'] = $info['realname'];
				$data[$key]['teacher_photo'] = $info['photo'];
			}else{
				$tinfo = $Teacher->where('id',$val['teacher_id'])->field('realname,photo')->find();
				$data[$key]['teacher_name'] = $tinfo['realname'];
				$data[$key]['teacher_photo'] = $tinfo['photo'];
			}
			//获取评论详情
			$field = 'id,content,from_id,from_type,from_name,to_name,create_time';
			$data[$key]['commentList'] = $FriendCircleComment->where('flag',1)->where('is_show',1)->where('friend_circle_id',$val['id'])->field($field)->order('create_time asc')->select();
			//判断自己是否有点赞
			$count = $FriendCircleUp->where('friend_circle_id',$val['id'])->where('type',3)->where('user_id',$param['userId'])->count();
			$data[$key]['isUp'] = $count == 0 ?false:true;/*ji add*/$data[$key]['isdel'] = 1;
		}
		//获取朋友圈背景
		$newData['banner'] = Db::name('Schools')->where('flag',1)->where('id',$info['school_id'])->value('banner');
		$newData['data'] = $data;
		return $this->suc($newData,$nextStartId,config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 发表朋友圈
	 */
	public function createFriendCircle(){
		$Headmasters = model('Headmasters');$FriendCircle = model('FriendCircle');
		$param = $this->param;
		/*if(empty($param['content'])){
			return $this->err('参数错误！');
		}*/
		$info = $Headmasters->where('id',$param['userId'])->where('flag',1)->find();
		$data['school_id'] = $info['school_id'];
		$data['class_id'] = 0;
		$data['teacher_id'] = 0;
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
		$FriendCircle = model('FriendCircle');
		$param = $this->param;
		/*if(empty($param['content'])){
			return $this->err('参数错误！');
		}*/
		// $data['content'] = $param['content'];
		// $result = $FriendCircle->isUpdate(true)->save($data,['video_url' => $param['videoUrl']]);
		$Headmasters = model('Headmasters');$FriendCircle = model('FriendCircle');
		$info = $Headmasters->where('id',$param['userId'])->where('flag',1)->find();
		$data['school_id'] = $info['school_id'];$data['class_id'] = 0;$data['teacher_id'] = 0;
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
		$count = $FriendCircleUp->where('type',3)->where('user_id',$param['userId'])->where('friend_circle_id',$param['id'])->count();
		if($count != 0){
			return $this->err('您已点过赞，请勿重复操作！');
		}
		$data['friend_circle_id'] = $param['id'];
		$data['user_id'] = $param['userId'];
		$data['type'] = 3;
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
		$FriendCircleUp->where('friend_circle_id',$param['id'])->where('type',3)->where('user_id',$param['userId'])->delete();
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
		$data['from_type'] = 3;
		$data['from_name'] = Db::name('Headmasters')->where('id',$param['userId'])->value('realname');
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
		$info = $FriendCircleComment->where('id',$param['id'])->where('from_id',$param['userId'])->where('from_type',3)->where('flag',1)->find();
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
		$where['to_type'] = 3;
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
		$School = model('Schools');$Leader = model('Headmasters');
		$param = $this->param;
		if(empty($param['imgPath'])){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
		$result = $School->where('id',$info['school_id'])->setField('banner',$param['imgPath']);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err("系统繁忙！");
		}
	}
	
	/**
	 * 动态监控
	 */
	public function getFriendCircleByKeyword(){
		$Leader = model('Headmasters');$FriendCircle = model('FriendCircle');$FriendCircleComment = model('FriendCircleComment');$Keyword = model('FriendCircleKeyword');$Teacher = model('Teachers');
		$param = $this->param;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
		$list = $Keyword->where('school_id',$info['school_id'])->order('create_time asc')->field('keyword')->select();
		$query = $FriendCircleComment->where('flag',1)->where('school_id',$info['school_id']);
		foreach ($list as $key=>$val){
			if($key == 0){
				$query = $query->where('content','like','%'.$val['keyword'].'%');
			}else{
				$query = $query->whereOr('content','like','%'.$val['keyword'].'%');
			}
		}
		//获取出现敏感词汇的朋友圈ID
		$ids = $query->order('create_time desc')->value('GROUP_CONCAT(DISTINCT friend_circle_id)');
		if(!empty($ids)){
			$where['flag'] = 1;
			$where['id'] = array('in',$ids);
			$count = $FriendCircle->where($where)->count();
			$field = 'id,content,imgs,up_num,create_time,teacher_id';
			if($count < 10){
				$nextStartId = -1;
				$data = $FriendCircle->where($where)->field($field)->order('FIELD(id,'.$ids.')')->select();
			}else{
				$nextStartId = $param['nextStartId'];
				$data = $FriendCircle->where($where)->field($field)->limit($nextStartId,10)->order('FIELD(id,'.$ids.')')->select();
				$nextStartId = $nextStartId + 10;
				if($nextStartId >= $count || count($data) == 0){
					$nextStartId = -1;
				}
			}
			//获取发送老师的头像与名字
			foreach ($data as $key=>$val){
				$tinfo = $Teacher->where('id',$val['teacher_id'])->field('realname,photo')->find();
				$data[$key]['teacher_name'] = $tinfo['realname'];
				$data[$key]['teacher_photo'] = $tinfo['photo'];
				//获取评论详情
				$field = 'id,content,from_id,from_type,from_name,to_name,create_time,is_show';
				$data[$key]['commentList'] = $FriendCircleComment->where('flag',1)->where('friend_circle_id',$val['id'])->field($field)->order('create_time asc')->select();
			}
			//获取朋友圈背景
			$newData['banner'] = Db::name('Schools')->where('flag',1)->where('id',$info['school_id'])->value('banner');
			$newData['data'] = $data;
			return $this->suc($newData,$nextStartId,config('view_replace_str.__IMGROOT__'));
		}
		
	}
	
	/**
	 * 屏蔽朋友圈
	 */
	public function hideFriendCircleComment(){
		$FriendCircleComment = model('FriendCircleComment');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		$info = $FriendCircleComment->where('id',$param['id'])->where('flag',1)->find();
		if(empty($info)){
			return $this->err('该内容无法找到或已被删除！');
		}
		$result = $FriendCircleComment->where('id',$param['id'])->setField('is_show',2);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err("系统繁忙！");
		}
	}
	
	/**
	 * 获取关键字列表
	 */
	public function getKeywordList(){
		$Keyword = model('FriendCircleKeyword');$Leader = model('Headmasters');
		$param = $this->param;
		$info = $Leader->where('id',$param['userId'])->find();
		$data = $Keyword->where('school_id',$info['school_id'])->order('create_time asc')->field('id,keyword')->select();
		return $this->suc($data);
	}
	
	/**
	 * 新增关键字
	 */
	public function addKeyword(){
		$Keyword = model('FriendCircleKeyword');$Leader = model('Headmasters');
		$param = $this->param;
		if(empty($param['keyword'])){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
		//最多添加9个关键字
		if($Keyword->where('school_id',$info['school_id'])->count() == 9){
			return $this->err('最多添加9个关键字！');
		}
		$data['school_id'] = $info['school_id'];
		$data['keyword'] = $param['keyword'];
		$data['create_time'] = time();
		$result = $Keyword->isUpdate(false)->save($data);
		if($result){
			return $this->suc();
		}else{
			return $this->err();
		}
	}
	
	/**
	 * 删除关键字
	 */
	public function delKeyword(){
		$Keyword = model('FriendCircleKeyword');$Leader = model('Headmasters');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
		$result = $Keyword->where('school_id',$info['school_id'])->where('id',$param['id'])->delete();
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err();
		}
	}
}