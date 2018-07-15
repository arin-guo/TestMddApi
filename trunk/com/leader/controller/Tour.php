<?php
namespace app\leader\controller;
use app\leader\controller\Base;
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
		/*$tour = model('ParentChildTour');
		$param = $this->param;
		$where['flag'] = 1;$where['status'] = 1;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		if($param['type'] == 1)
		{}
		elseif($param['type'] == 2)
		{
			$where['is_hot'] = 1;
		}
		else
		{
			$where['is_ready'] = 1;
		}
		$count = $tour->where($where)->count();
		$field = 'id,title,photo,price,intro,is_ready,status,"" as detail_url';
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
			if(model('TourSignUp')->where('tour_id',$val['id'])->find() && model('Tour_school_choose')->where('school_id',model('Headmasters')->where('id',$param['userId'])->find()['school_id'])->where('tour_id',$val['id'])->find()['status'] == 1) $data[$key]['ifSign'] = 1;
			else $data[$key]['ifSign'] = 2;
			$data[$key]['detail_url'] = config('view_replace_str.__ADMROOT__') . "index/Spa/getSpa#/familytripdetail/id/" . $val['id'] . "/uid/" . $param['userId'] . "/type/3";
		}
		return $this->suc($data,$nextStartId,config('view_replace_str.__IMGROOT__'));*/
		$tour = model('ParentChildTour');
		$param = $this->param;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		// $where['is_ready'] = 2;
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
			$where['is_attention'] = 1;
		}
		$field = 'id,title,photo,price,intro,status,is_ready,"" as detail_url';
		if($where['is_attention'] == 1)
		{
			$map['is_attention']=array('like','%3-'.$param['userId'].'y%');
			$data = model('ParentChildTour')->field('id,title,photo,price,intro,status,is_ready')->where($map)->where('status',1)->where('flag',1)->order('update_time desc')->select();
		}
		else
		{
			$data = $tour->where($where)->field($field)->order('update_time desc')->select();
		}
		foreach($data as $key=>$val)
		{
			if($val['is_ready'] == 1) $data[$key]['statue'] = 3;
			if(model('TourSchoolChoose')->where('school_id',model('Headmasters')->where('id',$param['userId'])->find()['school_id'])->where('flag',1)->where('status',1)->where('is_pass',1)->where('tour_id',$val['id'])->find()) {$data[$key]['if_res'] = 1;$data[$key]['statue'] = 1;}else{$data[$key]['if_res'] = 2;}
			if(model('TourSchoolChoose')->where('school_id',model('Headmasters')->where('id',$param['userId'])->find()['school_id'])->where('flag',1)->where('status',1)->where('is_pass',2)->where('tour_id',$val['id'])->find()) {$data[$key]['if_sure'] = 1;$data[$key]['statue'] = 2;}else{$data[$key]['if_sure'] = 2;}
			if(!model('TourSchoolChoose')->where('school_id',model('Headmasters')->where('id',$param['userId'])->find()['school_id'])->where('flag',1)->where('status',1)->where('tour_id',$val['id'])->find() && $val['is_ready'] == 2){$data[$key]['statue'] = 0;}
			$data[$key]['begin_time'] = substr($val['begin_time'],0,-3);
			$data[$key]['end_time'] = substr($val['end_time'],0,-3);
			$data[$key]['detail_url'] = config('view_replace_str.__ADMROOT__') . "index/Spa/getSpa#/familytripdetail/id/" . $val['id'] . "/uid/" . $param['userId'] . "/type/3";
			unset($data[$key]['status']);
		}
		foreach($data as $k=>$v)
		{
			$res[$k] = $v['if_res'];
			$sure[$k] = $v['if_sure'];
			$ready[$k] = $v['is_ready'];
		}
		array_multisort($res,SORT_ASC,$sure,SORT_ASC,$ready,SORT_DESC,$data);
		if(count($data) < 10){
			$nextStartId = -1;
			$datas = $data;
		}else{
			$nextStartId = $param['nextStartId'];
			$datas = array_slice($data,$nextStartId,10);
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= count($data) || count($datas) == 0){
				$nextStartId = -1;
			}
		}
		return $this->suc($datas,$nextStartId,config('view_replace_str.__IMGROOT__'));
	}

	/**
	 * 获取活动管理列表
	 * 
	 */
	public function getManagerList(){
		$sign = model('TourSignUp');$Leader = model('Headmasters');
		$param = $this->param;
		$field = 'tour_id AS id,title,intro,photo,price,SUM(totle_num) as sign_num,"" AS detail_url';
		$data = $sign->where('school_id',$Leader->where('id',$param['userId'])->find()['school_id'])->field($field)->join('cc_parent_child_tour t','cc_tour_sign_up.tour_id = t.id')->where('t.status',1)->where('t.flag',1)->where('cc_tour_sign_up.flag',1)->where('cc_tour_sign_up.status',1)->group('title')->order('t.update_time DESC')->select();
		$cArr = model('Tour_school_choose')->field('tour_id AS id,title,intro,photo,price,"" as sign_num,is_pass,leader_begin_time,leader_end_time')->join('cc_parent_child_tour t','cc_tour_school_choose.tour_id = t.id')->where('school_id',$Leader->where('id',$param['userId'])->find()['school_id'])->where('cc_tour_school_choose.status',1)->where('t.flag',1)->where('cc_tour_school_choose.flag',1)->order('t.update_time DESC')->select();$count = 0;
		if($data[0]['title'])
		{
			foreach($data as $key=>$val)
			{
				foreach($cArr as $m=>$n)
				{
					if($n['id'] == $val['id']) unset($cArr[$m]);
				}
				$data[$key]['ifok'] = 1;
				$data[$key]['leader_end_time'] = model('Tour_school_choose')->where('school_id',$Leader->where('id',$param['userId'])->find()['school_id'])->where('tour_id',$val['id'])->find()['leader_end_time'];
				$data[$key]['leader_begin_time'] = model('Tour_school_choose')->where('school_id',$Leader->where('id',$param['userId'])->find()['school_id'])->where('tour_id',$val['id'])->find()['leader_begin_time'];
				if(model('TourSchoolChoose')->where('school_id',$Leader->where('id',$param['userId'])->find()['school_id'])->where('tour_id',$val['id'])->value('flag') == 2) unset($data[$key]);
			}
			$count = count($data);
		}
		foreach($cArr as $a=>$b)
		{
			$data[$count + $a] = $b;
			if($b['is_pass'] == 1) $data[$count + $a]['ifok'] = 1;else $data[$count + $a]['ifok'] = 2;
			if(strtotime($b['leader_end_time']) < time()) $data[$count + $a]['ifok'] = -1; 
		}
		foreach($data as $k=>$v)
		{
			$data[$k]['detail_url'] = config('view_replace_str.__ADMROOT__') . "index/Spa/getSpa#/familytripdetail/id/" . $v['id'] . "/uid/" . $param['userId'] . "/type/3/from/2";
			$data[$k]['sign_num'] = empty($data[$k]['sign_num']) ? 0 : $data[$k]['sign_num'];
			$temp = $v['leader_end_time'];
			$data[$k]['leader_begin_time'] = date('Y.m.d H:i',strtotime($v['leader_begin_time']));
			$data[$k]['leader_end_time'] = date('m.d H:i',strtotime($v['leader_end_time']));
			$data[$k]['unit'] = '起';
			$data[$k]['way'] = '游玩时间';
			unset($data[$k]['is_pass']);
			if(model('Tour_school_choose')->where('school_id',$Leader->where('id',$param['userId'])->find()['school_id'])->where('status',2)->where('tour_id',$v['id'])->find()){unset($data[$k]);continue;}
			if(time() > strtotime($temp)) $datas['down'][] = $data[$k];else $datas['up'][] = $data[$k];
		}
		$alchoose = model('AlbumSchoolChoose')->where('album_id',model('ChildSchoolAlbum')->value('id'))->where('school_id',$Leader->where('id',$param['userId'])->find()['school_id'])->where('status',1)->where('flag',1)->find();
		if(!empty($alchoose) && strtotime($alchoose['leader_end_time']) < time())
		{
			$nex = count($datas['down']) ? count($datas['down']) : 0;
			$datas['down'][$nex]['id'] = model('ChildSchoolAlbum')->value('id');
			$datas['down'][$nex]['title'] = '毕业照';
			$datas['down'][$nex]['intro'] = model('ChildSchoolAlbum')->value('intro');
			$datas['down'][$nex]['photo'] = model('ChildSchoolAlbum')->value('photo');
			$datas['down'][$nex]['leader_begin_time'] = date('Y.m.d H:i',strtotime($alchoose['leader_begin_time']));
			$datas['down'][$nex]['leader_end_time'] = date('m.d H:i',strtotime($alchoose['leader_end_time']));
			$datas['down'][$nex]['unit'] = '人';
			$datas['down'][$nex]['ifok'] = 0;
			$datas['down'][$nex]['way'] = '拍摄时间';
			$datas['down'][$nex]['price'] = $alchoose['pid'] == 1 ? 150 : ($alchoose['pid'] == 2 ? 260 : 360);
			$datas['down'][$nex]['sign_num'] = model('AlbumSignUp')->where('album_id',$alchoose['album_id'])->where('status',1)->where('school_id',$Leader->where('id',$param['userId'])->find()['school_id'])->SUM('totle_num') ? model('AlbumSignUp')->where('album_id',$alchoose['album_id'])->where('status',1)->where('school_id',$Leader->where('id',$param['userId'])->find()['school_id'])->SUM('totle_num') : 0;
			$datas['down'][$nex]['detail_url'] = config('view_replace_str.__ADMROOT__') . 'index/Spa/getSpa#/graduation/detail/id/'.model('ChildSchoolAlbum')->value('id').'/uid/'.$param['userId'].'/type/3/from/1';
		}
		elseif(!empty($alchoose) && strtotime($alchoose['leader_end_time']) > time())
		{
			$pre = count($datas['up']) ? count($datas['up']) : 0;
			$datas['up'][$pre]['id'] = model('ChildSchoolAlbum')->value('id');
			$datas['up'][$pre]['title'] = '毕业照';
			$datas['up'][$pre]['intro'] = model('ChildSchoolAlbum')->value('intro');
			$datas['up'][$pre]['photo'] = model('ChildSchoolAlbum')->value('photo');
			$datas['up'][$pre]['leader_begin_time'] = date('Y.m.d H:i',strtotime($alchoose['leader_begin_time']));
			$datas['up'][$pre]['leader_end_time'] = date('m.d H:i',strtotime($alchoose['leader_end_time']));
			$datas['up'][$pre]['unit'] = '人';
			$datas['up'][$pre]['ifok'] = 0;
			$datas['up'][$pre]['way'] = '拍摄时间';
			$datas['up'][$pre]['price'] = $alchoose['pid'] == 1 ? 150 : ($alchoose['pid'] == 2 ? 260 : 360);
			$datas['up'][$pre]['sign_num'] = model('AlbumSignUp')->where('album_id',$alchoose['album_id'])->where('status',1)->where('school_id',$Leader->where('id',$param['userId'])->find()['school_id'])->SUM('totle_num') ? model('AlbumSignUp')->where('album_id',$alchoose['album_id'])->where('status',1)->where('school_id',$Leader->where('id',$param['userId'])->find()['school_id'])->SUM('totle_num') : 0;
			$datas['up'][$pre]['detail_url'] = config('view_replace_str.__ADMROOT__') . 'index/Spa/getSpa#/graduation/detail/id/'.model('ChildSchoolAlbum')->value('id').'/uid/'.$param['userId'].'/type/3/from/1';
		}$datas['up'] = $datas['up'][0]['id'] ? $datas['up'] : array();$datas['down'] = $datas['down'][0]['id'] ? $datas['down'] : array();
		return $this->suc($datas,'',config('view_replace_str.__IMGROOT__'));
	}

	/**
	 * 获取已报名列表
	 * 
	 */
	public function getSignList(){
		$sign = model('TourSignUp');$Leader = model('Headmasters');
		$param = $this->param;
		if(is_null($param['tid'])){
			return $this->err('参数错误！');
		}
		if($param['tid'] == 1)
		{
			$field = 'SUM(totle_num) AS num,GROUP_CONCAT(Childs.realname) AS namearr,GROUP_CONCAT(link_tel) AS telarr,GROUP_CONCAT(totle_num) AS numarr';
			$data = Db::view('Album_sign_up s','id,school_id,link_tel AS tel')->field($field)->view('Child_school_album t','title','t.id = s.album_id')->view('Classes','name','Classes.id = s.class_id')->view('Childs','realname','Childs.id = s.child_id')->where('school_id',$Leader->where('id',$param['userId'])->find()['school_id'])->where('album_id',$param['tid'])->where('s.status',1)->where('s.flag',1)->where('t.status',1)->where('t.flag',1)->group('name')->order('s.update_time DESC')->select();
			$datas = Db::view('Album_sign_up s','id,school_id,totle_num,link_tel AS tel')->field('SUM(totle_num) AS num')->view('Child_school_album t','title','t.id = s.album_id')->view('Childs','realname','Childs.id = s.child_id')->where('school_id',$Leader->where('id',$param['userId'])->find()['school_id'])->where('album_id',$param['tid'])->where('s.class_id',0)->where('s.status',1)->where('s.flag',1)->where('t.status',1)->where('t.flag',1)->order('s.update_time DESC')->select();
			foreach($data as $key=>$val)
			{
				if($val['namearr'] && $val['telarr'] && $val['numarr'])
				{
					foreach(explode(',', $val['namearr']) as $k=>$v)
					{
						$data[$key]['child_num_arr'][$k]['chlid_name'] = $v;
						$data[$key]['child_num_arr'][$k]['tel'] = explode(',', $val['telarr'])[$k];
						$data[$key]['child_num_arr'][$k]['family_num'] = explode(',', $val['numarr'])[$k];
					}
					unset($data[$key]['namearr']);unset($data[$key]['telarr']);unset($data[$key]['numarr']);
				}
				unset($data[$key]['realname']);unset($data[$key]['tel']);unset($data[$key]['title']);
				$totalNum += $val['num'];
			}$cou = count($data);
			if($datas[0]['num'])
			{
				foreach($datas as $k=>$v)
				{
					$data[$cou]['id'] = $v['id'];
					$data[$cou]['school_id'] = $v['school_id'];
					$data[$cou]['name'] = '未分班';
					$data[$cou]['num'] = $v['num'];
					$data[$cou]['child_num_arr'][$k]['chlid_name'] = $v['realname'];
					$data[$cou]['child_num_arr'][$k]['tel'] = $v['tel'];
					$data[$cou]['child_num_arr'][$k]['family_num'] = $v['totle_num'];
				}$totalNum += $data[$cou]['num'];
			}
		}
		else
		{
			$field = 'SUM(totle_num) AS num,GROUP_CONCAT(Childs.realname) AS namearr,GROUP_CONCAT(link_tel) AS telarr,GROUP_CONCAT(totle_num) AS numarr';
			$data = Db::view('Tour_sign_up s','id,school_id,link_tel AS tel')->field($field)->view('Parent_child_tour t','title','t.id = s.tour_id')->view('Classes','name','Classes.id = s.class_id')->view('Childs','realname','Childs.id = s.child_id')->where('school_id',$Leader->where('id',$param['userId'])->find()['school_id'])->where('tour_id',$param['tid'])->where('s.status',1)->where('s.flag',1)->where('t.status',1)->where('t.flag',1)->group('name')->order('s.update_time DESC')->select();
			$datas = Db::view('Tour_sign_up s','id,school_id,totle_num,link_tel AS tel')->field('SUM(totle_num) AS num')->view('Parent_child_tour t','title','t.id = s.tour_id')->view('Childs','realname','Childs.id = s.child_id')->where('school_id',$Leader->where('id',$param['userId'])->find()['school_id'])->where('tour_id',$param['tid'])->where('s.class_id',0)->where('s.status',1)->where('s.flag',1)->where('t.status',1)->where('t.flag',1)->order('s.update_time DESC')->select();
			foreach($data as $key=>$val)
			{
				if($val['namearr'] && $val['telarr'] && $val['numarr'])
				{
					foreach(explode(',', $val['namearr']) as $k=>$v)
					{
						$data[$key]['child_num_arr'][$k]['chlid_name'] = $v;
						$data[$key]['child_num_arr'][$k]['tel'] = explode(',', $val['telarr'])[$k];
						$data[$key]['child_num_arr'][$k]['family_num'] = explode(',', $val['numarr'])[$k];
					}
					unset($data[$key]['namearr']);unset($data[$key]['telarr']);unset($data[$key]['numarr']);
				}
				unset($data[$key]['realname']);unset($data[$key]['tel']);unset($data[$key]['title']);
				$totalNum += $val['num'];
			}$cou = count($data);
			if($datas[0]['num'])
			{
				foreach($datas as $k=>$v)
				{
					$data[$cou]['id'] = $v['id'];
					$data[$cou]['school_id'] = $v['school_id'];
					$data[$cou]['name'] = '未分班';
					$data[$cou]['num'] = $v['num'];
					$data[$cou]['child_num_arr'][$k]['chlid_name'] = $v['realname'];
					$data[$cou]['child_num_arr'][$k]['tel'] = $v['tel'];
					$data[$cou]['child_num_arr'][$k]['family_num'] = $v['totle_num'];
				}$totalNum += $data[$cou]['num'];
			}
		}
		if(empty($totalNum)) $totalNum = 0;
		return $this->sucs($data,'','',$totalNum);
	}

	/**
	 * 取消亲子游活动推送
	 * 
	 */
	public function cancelRelease(){
		$choose = model('TourSchoolChoose');$Leader = model('Headmasters');
		$param = $this->param;
		if(is_null($param['tid'])){
			return $this->err('参数错误！');
		}
		if($param['tid'] == 1 && model('AlbumSchoolChoose')->where('album_id',1)->where('school_id',$Leader->where('id',$param['userId'])->value('school_id'))->find()['status'] == 1)
		{
			$result = model('AlbumSchoolChoose')->isUpdate(true)->save(array('status'=>2),['album_id'=>1,'school_id'=>$Leader->where('id',$param['userId'])->value('school_id'),'status'=>1]);
			if($signarr = model('AlbumSignUp')->where('album_id',$param['tid'])->where('school_id',$Leader->where('id',$param['userId'])->value('school_id'))->select()){foreach($signarr as $k=>$v){model('AlbumSignUp')->isUpdate(true)->save(array('status'=>2),['id'=>$v['id']]);}}
			model('Banners')->isUpdate(true)->save(array('flag'=>2),['is_on'=>3,'school_id'=>$Leader->where('id',$param['userId'])->value('school_id')]);
		}
		else
		{
			$data['status'] = 2;
			$result = $choose->isUpdate(true)->save($data,['tour_id'=>$param['tid'],'school_id'=>$Leader->where('id',$param['userId'])->find()['school_id']]);
		}
		if($result)
		{
			return $this->suc();
		}
		else
		{
			return $this->err('失败');
		}
	}

	/**
	 * 全部删除
	 * 
	 */
	public function deleteAll(){
		$choose = model('TourSchoolChoose');$Leader = model('Headmasters');
		$param = $this->param;
		if(is_null($param['tids'])){
			return $this->err('参数错误！');
		}
		$data['flag'] = 2;
		$result = $choose->isUpdate(true)->save($data,['school_id'=>$Leader->where('id',$param['userId'])->find()['school_id'],'tour_id'=>['IN',$param['tids']]]);
		if($result)
		{
			return $this->suc();
		}
		else
		{
			return $this->err('失败');
		}
	}

	/**
	 * 关注
	 * 
	 */
	public function tourAttention(){
		$tour = model('ParentChildTour');$Leader = model('Headmasters');
		$param = $this->param;
		if(is_null($param['id'])){
			return $this->err('参数错误！');
		}
		$data['is_attention'] = $tour->where('id',$param['id'])->value('is_attention').'3-'.$param['userId'].'y,';
		$where['is_attention']=array('like','%3-'.$param['userId'].'y%');
		$isin = $tour->where('id',$param['id'])->where($where)->find();
		if($isin) $data['is_attention'] = str_replace('3-'.$param['userId'].'y,', '', $isin['is_attention']);
		$result = $tour->isUpdate(true)->save($data,['id'=>$param['id']]);
		if($result)
		{
			return $this->suc();
		}
		else
		{
			return $this->err('失败');
		}
	}

	/**
	 * 定位获取
	 * 
	 */
	public function locationList(){
		$tour = model('ParentChildTour');$Leader = model('Headmasters');
		$param = $this->param;
		/*if(is_null($param['id'])){
			return $this->err('参数错误！');
		}*/
		$data['is_attention'] = $tour->where('id',$param['id'])->value('is_attention').'3-'.$param['userId'].'y,';
		$where['is_attention']=array('like','%3-'.$param['userId'].'y%');
		$isin = $tour->where('id',$param['id'])->where($where)->find();
		if($isin) $data['is_attention'] = str_replace('3-'.$param['userId'].'y,', '', $isin['is_attention']);
		$result = $tour->isUpdate(true)->save($data,['id'=>$param['id']]);
		return $this->suc(array(0=>array('id'=>1,'city'=>'台州','current'=>0),1=>array('id'=>2,'city'=>'杭州','current'=>1),2=>array('id'=>3,'city'=>'金华','current'=>0),2=>array('id'=>4,'city'=>'温州','current'=>0),3=>array('id'=>5,'city'=>'宁波','current'=>0),));
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
		$map['is_collect']=array('like','%3-'.$param['userId'].'y%');
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
		return /*$this->suc($info);
		*/array(
				'result' => 'y',
				'data' => array('id'=>$info['id'],'cole'=>$info['cole'],'photo'=>$info['photo'],'list'=>$data),
				'ambulance' => config('view_replace_str.__IMGROOT__')
		);
	}

	/**
	 * 读取小故事(废弃)
	 * 
	 */
	public function readStory(){
		$sto = model('Story');
		$param = $this->param;
		if(empty($param['id'])){
			return $this->err('参数错误！');
		}
		$field = 'id,photo,audio,title,content';
		$data = $sto->where('id',$param['sid'])->field($field)->find();
		$info = $sto->where('type',$data['type'])->order('update_time desc')->select();
		foreach($info as $k=>$v)
		{
			if($v['id'] == $param['sid'])
			{
				$data['next'][] = $info[$k+1]['id'];
				$data['next'][] = $info[$k+1]['title'];
				$data['pre'][] = $info[$k-1]['id'];
				$data['pre'][] = $info[$k-1]['title'];
			}
		}
		return $this->suc($data);
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
		$data['is_collect'] = $story->where('id',$param['sid'])->value('is_collect').'3-'.$id.'y,';
		if($story->where('type',$param['sid'])->find())
		{
			$datas['is_collect'] = $story->where('type',$param['sid'])->value('is_collect').'3-'.$id.'y,';
			$wheres['is_collect']=array('like','%3-'.$id.'y%');
			$isins = $story->where('type',$param['sid'])->where($wheres)->find();
			if($isins)
			{
				$datas['is_collect'] = str_replace('3-'.$id.'y,', '', $isins['is_collect']);
				$results = $story->isUpdate(true)->save($datas,['id'=>$story->where('type',$param['sid'])->where($wheres)->value('id')]);
			}
			else
			{
				$results = $story->isUpdate(true)->save($datas,['id'=>$story->where('type',$param['sid'])->value('id')]);
			}
		}
		$where['is_collect']=array('like','%3-'.$id.'y%');
		$isin = $story->where('id',$param['sid'])->where($where)->find();
		if($isin) $data['is_collect'] = str_replace('3-'.$id.'y,', '', $isin['is_collect']);
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
		$map['is_collect']=array('like','%3-'.$param['userId'].'y%');
		$map['flag']=1;
		$data = $sto->where($map)->field('id,type,intro,photo,title,visit_num')->order('update_time desc')->select();
		foreach($data as $k=>$v)
		{
			if($v['type'] == -1) $datas['list'][] = $v;
			else $datas['listgs'][] = model('StorySeries')->field('id,photo,intro,title,visit_num')->where('id',$v['type'])->find();
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