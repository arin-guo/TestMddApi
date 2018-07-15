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
class Tour extends Base{
	
	/**
	 * 获取列表
	 * 
	 */
	public function getTourList(){
		$tour = model('ParentChildTour');
		$param = $this->param;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		if($param['type'] == 1)
		{$where['flag'] = 1;$where['status'] = 1;}
		elseif($param['type'] == 2)
		{
			$where['is_hot'] = 1;
			$where['status'] = 1;
			$where['flag'] = 1;
		}
		else
		{
			$where['is_attention']=array('like','%2-'.$param['userId'].'y%');
			$where['status'] = 1;
			$where['flag'] = 1;
		}
		$count = $tour->where($where)->count();
		$field = 'id,title,photo,price,intro,status,is_ready,"" as detail_url';
		if($count < 10){
			$nextStartId = -1;
			$data = $tour->where($where)->field($field)->order('begin_time desc')->select();
		}else{
			$nextStartId = $param['nextStartId'];
			$data = $tour->where($where)->field($field)->limit($nextStartId,10)->order('begin_time desc')->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		foreach($data as $key=>$val)
		{
			if(model('TourSignUp')->where('tour_id',$val['id'])->find() && model('Tour_school_choose')->where('school_id',model('Teachers')->where('id',$param['userId'])->find()['school_id'])->where('tour_id',$val['id'])->find()['status'] == 1) $data[$key]['ifSign'] = 1;
			else $data[$key]['ifSign'] = 2;
			$data[$key]['detail_url'] = config('view_replace_str.__ADMROOT__') . "index/Spa/getSpa#/familytripdetail/id/" . $val['id'] . "/uid/" . $param['userId'] . "/type/2";
		}
		return $this->suc($data,$nextStartId,config('view_replace_str.__IMGROOT__'));
	}

	/**
	 * 获取小故事
	 * 
	 */
	public function story(){
		$Model = model('StorySeries');
		$param = $this->param;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		$where['flag'] = 1;
		$field = 'id,title,intro,photo,visit_num';
		if($param['type'] == 1)
		{
			$count = model('Story')->where($where)->where('type',-1)->count();
		}
		else
		{
			$count = $Model->where($where)->count();
		}
		/*Db::field($field)->table('cc_story_series')->union(['SELECT id,title,intro,photo,visit_num,type,update_time FROM cc_story WHERE type = -1','SELECT id,title,intro,photo,visit_num,type,update_time FROM cc_story_series WHERE flag = 1'])->where('type',-1)->select();
		$count = count($count);*/
		if($count < 10){
			$nextStartId = -1;
			if($param['type'] == 1)
			{
				$data = model('Story')->field($field)->where($where)->where('type',-1)->order('create_time DESC')->select();
			}
			else
			{
				$data = $Model->field($field)->where($where)->order('create_time DESC')->select();
			}
		}else{
			$nextStartId = $param['nextStartId'];
			if($param['type'] == 1)
			{
				$data = model('Story')->field($field)->where($where)->where('type',-1)->limit($nextStartId,10)->order('create_time DESC')->select();
			}
			else
			{
				$data = $Model->field($field)->where($where)->limit($nextStartId,10)->order('create_time DESC')->select();
			}
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		if($param['type'] == 1){foreach($data as $k=>$v){$data[$k]['id'] = $v['id'] + 100000;}}
		return $this->suc($data,$nextStartId,config('view_replace_str.__IMGROOT__'));
	}

	/**
	 * 获取小故事详情
	 * 
	 */
	public function storyDetail(){
		$stoser = model('StorySeries');
		$sto = model('Story');
		$param = $this->param;
		$map['is_collect']=array('like','%2-'.$param['userId'].'y%');
		if(empty($param['xid'])){
			return $this->err('参数错误！');
		}
		$field = 'id,photo';
		if($param['xid'] > 100000) $sto->where('id',$param['xid'] - 100000)->setInc('visit_num');
		else $stoser->where('id',$param['xid'])->setInc('visit_num');
		if($param['xid'] > 100000) $info = $sto->where('id',$param['xid'] - 100000)->where('flag',1)->field($field)->find();
		else
		{
			$info = $stoser->where('id',$param['xid'])->where('flag',1)->field($field)->find();
			$info['id'] = $sto->where('type',$info['id'])->where('flag',1)->value('id');
		}
		if(empty($info)){
			return $this->err('参数错误！');
		}
		if($param['xid'] > 100000 && $sto->where('id',$param['xid'] - 100000)->where($map)->find()) $info['cole'] = 1;else $info['cole'] = 2;
		if($param['xid'] < 100000 && $sto->where('type',$param['xid'])->where($map)->find()) $info['cole'] = 1;elseif($param['xid'] < 100000 && !$sto->where('type',$param['xid'])->where($map)->find()) $info['cole'] = 2;
		if($param['xid'] > 100000) $data = $sto->where('id',$param['xid'] - 100000)->where('flag',1)->field('id,audio,photo,title,content')->order('update_time')->select();
		else $data = $sto->where('type',$param['xid'])->field('id,audio,photo,title,content')->where('flag',1)->order('update_time')->select();
		// foreach($data as $k=>$v){$data[$k]['content'] = str_replace(array('<p>','</p>','&nbsp;'),"\n",strip_tags($v['content'],'<p>'));}
		$info['list'] = $data ? $data : '';
		return /*$this->suc($info);*/array(
				'result' => 'y',
				'data' => array('id'=>$info['id'],'cole'=>$info['cole'],'photo'=>$info['photo'],'list'=>$data),
				'ambulance' => config('view_replace_str.__IMGROOT__')
		);
	}

	/**
	 * 收藏取收
	 * 
	 */
	public function storyCollect(){
		$story = model('Story');
		$param = $this->param;
		if(is_null($id = $param['userId'])){
			return $this->err('参数错误！');
		}if($param['sid'] > 100000) $param['sid'] -= 100000;
		$data['is_collect'] = $story->where('id',$param['sid'])->value('is_collect').'2-'.$id.'y,';
		if($story->where('type',$param['sid'])->find())
		{
			$datas['is_collect'] = $story->where('type',$param['sid'])->value('is_collect').'2-'.$id.'y,';
			$wheres['is_collect']=array('like','%2-'.$id.'y%');
			$isins = $story->where('type',$param['sid'])->where($wheres)->find();
			if($isins)
			{
				$datas['is_collect'] = str_replace('2-'.$id.'y,', '', $isins['is_collect']);
				$results = $story->isUpdate(true)->save($datas,['id'=>$story->where('type',$param['sid'])->where($wheres)->value('id')]);
			}
			else
			{
				$results = $story->isUpdate(true)->save($datas,['id'=>$story->where('type',$param['sid'])->value('id')]);
			}
		}
		$where['is_collect']=array('like','%2-'.$id.'y%');
		$isin = $story->where('id',$param['sid'])->where($where)->find();
		if($isin) $data['is_collect'] = str_replace('2-'.$id.'y,', '', $isin['is_collect']);
		$result = $story->isUpdate(true)->save($data,['id'=>$param['sid']]);
		if($result || $results)
		{
			return $this->suc();
		}
		else
		{
			return $this->err('失败');
		}
	}

	/**
	 * 小故事我的收藏
	 * 
	 */
	public function collectList(){
		$sto = model('Story');
		$param = $this->param;
		$map['is_collect']=array('like','%2-'.$param['userId'].'y%');
		$map['flag']=1;
		$data = $sto->where($map)->field('id,type,intro,photo,title,visit_num')->order('update_time desc')->select();
		foreach($data as $k=>$v)
		{
			if($v['type'] == -1) $datas['list'][] = $v;
			else $datas['listgs'][] = model('StorySeries')->field('id,intro,photo,title,visit_num')->where('id',$v['type'])->find();
			unset($data[$k]['type']);
			// $data[$k]['content'] = strip_tags($v['content']);
		}foreach($datas['list'] as $key=>$val){$datas['list'][$key]['id'] = $val['id'] + 100000;}
		$datas['list'] = $datas['list'] ? $datas['list'] : [];
		$datas['listgs'] = $datas['listgs'] ? $datas['listgs'] : [];
		$datas = $param['type'] == 1 ? $datas['list'] : $datas['listgs'];
		return $this->sucs($datas,'-1',config('view_replace_str.__IMGROOT__'));
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