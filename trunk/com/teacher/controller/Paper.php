<?php
namespace app\teacher\controller;
use app\teacher\controller\Base;
use think\Db;
/**
 * 资讯管理
 * @author ji
 * @version 创建时间：2018.4.4
 * 类说明
 */
class Paper extends Base{
	
	/**
	 * 获取列表
	 * 
	 */
	public function getReportList(){
		$report = model('Report');
		$param = $this->param;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		$count = $report->count();
		$field = 'id,title,photo,source,visit_num,up_num,"" as detail_url';
		if($count < 10){
			$nextStartId = -1;
			$data = $report->field($field)->order('create_time desc')->select();
		}else{
			$nextStartId = $param['nextStartId'];
			$data = $report->field($field)->limit($nextStartId,10)->order('create_time desc')->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		foreach($data as $key=>$val)
		{
			$data[$key]['detail_url'] = config('view_replace_str.__ADMROOT__') . "index/Spa/getSpa#/newsdetail/id/" . $val['id'] . "/uid/" . $param['userId'] . "/type/2";
			$data[$key]['up_num'] = model('FriendCircleUp')->where('friend_circle_id',$val['id'])->where('type',101)->count() + model('FriendCircleUp')->where('friend_circle_id',$val['id'])->where('type',102)->count() + model('FriendCircleUp')->where('friend_circle_id',$val['id'])->where('type',103)->count();
		}
		return $this->suc($data,$nextStartId,config('view_replace_str.__IMGROOT__'));
	}

	/**
	 * 获取新闻详情
	 * 
	 */
	public function reportDetail(){
		$report = model('Report');
		if($_POST)$id = $_POST['id'];else $id = $_GET['id'];
		$field = 'id,title,photo,source,visit_num,up_num,content';
		$data = $report->field($field)->find();
		return $this->suc($data);
	}
	
	/**
	 * 新增联系人
	 */
	public function addMailtel(){
		$Mailtel = model('Mailtel');$Leader = model('Headmasters');
		$param = $this->param;
		if(empty($param['name']) || !preg_match("/1[34578]{1}\d{9}$/",$param['tel'])){
			return $this->err("参数错误！");
		}
		$info = $Leader->where('id',$param['userId'])->find();
		$data['school_id'] = $info['school_id'];
		$data['user_id'] = $param['userId'];
		$data['type'] = 3;
		$data['photo'] = $param['photo'];
		$data['name'] = $param['name'];
		$data['tel'] = $param['tel'];
		$data['identity'] = $param['identity'];
		$data['remark'] = $param['remark'];
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
		$count = $Mailtel->where('id',$param['id'])->where('user_id',$param['userId'])->where('flag',1)->where('type',3)->count();
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
		$Notice = model('Notice');$Leader = model('Headmasters');
		$param = $this->param;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
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
	 * 创建公告
	 */
	public function createNotice(){
		/*$Notice = model('Notice');$Leader = model('Headmasters');
		$param = $this->param;
		if(empty($param['title']) || empty($param['content'])){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
		$data['school_id'] = $info['school_id'];
		$data['author'] = $info['realname'];
		$data['title'] = $param['title'];
		$data['content'] = $param['content'];
		$result = $Notice->isUpdate(false)->save($data);
		if($result){
			//统计本校家长端与教师端
			Db::name('Parents')->where('flag',1)->where('school_id',$info['school_id'])->where('status',1)->where('jpush_id','neq','')->chunk(100,function($models){
				foreach ($models as $mod){
					if(!empty($mod['jpush_id'])){
						$message = '您有一条园所通知待查看';
						$extra = ['viewCode'=>80002];
						jpushToId($mod['jpush_id'], $message,1,$extra);
					}
				}
			});
			Db::name('Teachers')->where('flag',1)->where('school_id',$info['school_id'])->where('is_job',1)->where('jpush_id','neq','')->chunk(100,function($models){
				foreach ($models as $mod){
					if(!empty($mod['jpush_id'])){
						$message = '您有一条园所通知待查看';
						$extra = ['viewCode'=>80002];
						jpushToId($mod['jpush_id'], $message,2,$extra);
					}
				}
			});
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}*/
/*ji change*/
		$Notice = model('Notice');$Leader = model('Headmasters');
		$param = $this->param;
		if(empty($param['title']) || empty($param['content'])){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
		$data['school_id'] = $info['school_id'];
		$data['author'] = $info['realname'];
		$data['title'] = $param['title'];
		$data['content'] = $param['content'];
		// $data['is_public'] = $param['isPublic'];
		$data['allow_persons'] = $param['allowPersons'];
		$result = $Notice->isUpdate(false)->save($data);
		if($result){
			//统计本校家长端与教师端
			/*if($param['isPublic'] == 1){Db::name('Parents')->where('flag',1)->where('school_id',$info['school_id'])->where('status',1)->where('jpush_id','neq','')->chunk(100,function($models){
				foreach ($models as $mod){
					if(!empty($mod['jpush_id'])){
						$message = '您有一条园所通知待查看';
						$extra = ['viewCode'=>80002];
						jpushToId($mod['jpush_id'], $message,1,$extra);
					}
				}
			});
			Db::name('Teachers')->where('flag',1)->where('school_id',$info['school_id'])->where('is_job',1)->where('jpush_id','neq','')->chunk(100,function($models){
				foreach ($models as $mod){
					if(!empty($mod['jpush_id'])){
						$message = '您有一条园所通知待查看';
						$extra = ['viewCode'=>80002];
						jpushToId($mod['jpush_id'], $message,2,$extra);
					}
				}
			});}
			else{*/Db::name('Parents')->where('flag',1)->where('school_id',$info['school_id'])->where('status',1)->where('tel','IN',[$param['allowPersons']])->where('jpush_id','neq','')->chunk(100,function($models){
				foreach ($models as $mod){
					if(!empty($mod['jpush_id'])){
						$message = '您有一条园所通知待查看';
						$extra = ['viewCode'=>80002];
						jpushToId($mod['jpush_id'], $message,1,$extra);
					}
				}
			});
			Db::name('Teachers')->where('flag',1)->where('school_id',$info['school_id'])->where('is_job',1)->where('tel','IN',[$param['allowPersons']])->where('jpush_id','neq','')->chunk(100,function($models){
				foreach ($models as $mod){
					if(!empty($mod['jpush_id'])){
						$message = '您有一条园所通知待查看';
						$extra = ['viewCode'=>80002];
						jpushToId($mod['jpush_id'], $message,2,$extra);
					}
				}
			});/*}*/
			//发送短信
			$sendSms = new Sendsms(config('app_sendmsg_key'), config('app_sendmsg_secret'));
			//3077101为短信模版
			if($param['isMessage'] == 1){foreach(explode(',',$param['allowPersons']) as $val){$numArr[] = $val;}
			$resultn = $sendSms->sendSMSTemplate('3077101',$numArr,array($param['title']));}
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 删除公告
	 */
	public function delNotice(){
		$Notice = model('Notice');$Leader = model('Headmasters');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err("参数错误！");
		}
		$info = $Leader->where('id',$param['userId'])->find();
		$result = $Mailtel->where('id',$param['id'])->where('school_id',$info['school_id'])->setField('flag',2);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 园所风采
	 */
	public function getNewsList(){
		$News = model('News');$Leader = model('Headmasters');
		$param = $this->param;
		if(is_null($param['nextStartId']) || !in_array($param['type'], array(1,2))){
			return $this->err('参数错误！');
		}
		$info = $Leader->where('id',$param['userId'])->find();
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

	/**
	 * 获取老师列表
	 * 
	 */
	public function getTeacherList(){
		$Teacher = model('Teachers');$Leader = model('Headmasters');
		$param = $this->param;
		$info = $Leader->where('id',$param['userId'])->find();
		$field = 'id,photo,realname as name,tel,"" as identity,"" as remark';
		$data = $Teacher->where('flag',1)->where('school_id',$info['school_id'])->where('is_job',1)->field($field)->select();
		return $this->suc($data,'',config('view_replace_str.__IMGROOT__'));
	}

	/**
	 * 获取家长列表
	 * 
	 */
	public function getParentsList(){
		$Parent = model('Parents');$Leader = model('Headmasters');
		$param = $this->param;
		$info = $Leader->where('id',$param['userId'])->find();
		$field = 'id,photo,realname as name,tel,"" as identity,"" as remark';
		$data = $Parent->where('flag',1)->where('school_id',$info['school_id'])->where('status',1)->field($field)->select();
		foreach($data as $key=>$val)
		{
			if(empty($val['name'])) $data[$key]['name'] = '未命名';
		}
		return $this->suc($data,'',config('view_replace_str.__IMGROOT__'));
	}
}