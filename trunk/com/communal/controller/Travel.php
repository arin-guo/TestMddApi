<?php
namespace app\communal\controller;
use app\communal\controller\Base;
use think\Db;
use aop\AopClient;
use aop\AlipayTradeAppPayRequest;
/**
 * 资讯管理
 * @author ji
 * @version 创建时间：2018.4.4
 * 类说明
 */
class Travel extends Base{
	
	/*
	 * 类目列表
	 */
	public function getCategoryList(){
		$param = $this->param;
		$data = model('MallType')->field('id,name')->where('parent_id',0)->select();
		$data[] = array('id'=>0,'name'=>'全部景点');array_multisort($data);
		return $this->suc($data);
	}

	/*
	 * 类目列表
	 */
	public function getOrderList(){
		$param = $this->param;
		$data[] = array('id'=>0,'name'=>'综合排序');
		$data[] = array('id'=>1,'name'=>'价格排序');
		$data[] = array('id'=>2,'name'=>'距离排序');
		$data[] = array('id'=>3,'name'=>'评分排序');
		return $this->suc($data);
	}

	/**
	 * 获取所有亲子游列表
	 * 
	 */
	public function getAllTourList(){
		$tour = model('MallGoodsView');
		$param = $this->param;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		$where['status'] = 2;
		$where['flag'] = 1;
		if($param['type'] == 1)
		{
			$cres = model('MallType')->where('parent_id',$param['cid'])->value('GROUP_CONCAT(id)');
			$cstr = $cres . ',' . $param['cid'];
			if($param['cid'] != 0) $where['category'] = array('IN',$cstr);
			$where['type'] = 1;
		}
		elseif($param['type'] == 2)
		{
			$where['type'] = 2;
		}
		else
		{
			$where['is_attention'] = 1;
		}
		$field = 'id,title,cover_image AS photo,price_now_adult AS price_new_adult,price_old_adult,bespoke_time,extra_address AS address,status,coordinate AS location,"" AS tags,"" AS is_tops,"" AS is_hot,"" AS is_ready,"" as detail_url';
		/*if($where['is_attention'] == 1)
		{
			$map['is_attention']=array('like','%3-'.$param['userId'].'y%');
			$data = model('MallGoodsView')->field('id,title,cover_image AS photo,price_now_adult AS price,price_old_adult,bespoke_time,extra_address AS address,status,coordinate AS location,"" AS tags,"" AS is_tops,"" AS is_hot,"" AS is_ready,"" as detail_url')->where($map)->where('status',1)->where('flag',1)->order('update_time desc')->select();
		}
		else
		{*/
			$data = $tour->where($where)->field($field)->order('update_time desc')->select();
		/*}*/
		foreach($data as $key=>$val)
		{
			$radLat1 = deg2rad(explode(',',$val['location'])[1]);
			$radLat2 = deg2rad(explode(',',$param['location'])[1]);
			$radLng1 = deg2rad(explode(',',$val['location'])[0]);
			$radLng2 = deg2rad(explode(',',$param['location'])[0]);
			$a = $radLat1 - $radLat2;
			$b = $radLng1 - $radLng2;
			$data[$key]['range'] = round(2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6378.137,1);
			$data[$key]['tags'] = $val['tags'] ? $val['tags'] : '暂无';
			$data[$key]['photo'] = $val['photo'] ? 'http://img.mall.mengdd.net' . $val['photo'] : '';
			$data[$key]['bespoke_time'] = date('H:i',strtotime($data[$key]['bespoke_time']));
			$data[$key]['intro'] = model('MallGoodsDetail')->where('seq',0)->value('desc');
			$data[$key]['score'] = model('MallScore')->where('goods_id',$val['id'])->avg('score') ? model('MallScore')->where('goods_id',$val['id'])->avg('score') : 0;$data[$key]['statue'] = 0;
			if($tour->where('id',$val['id'])->where('is_attention','LIKE','%'.$param['useType'].'-'.$param['userId'].'y%')->find()) $data[$key]['isCollected'] = 1;else $data[$key]['isCollected'] = 2;
			if($val['is_tops'] == 1) $data[$key]['statue'] = 1;
			if($val['is_hot'] == 1) $data[$key]['statue'] = 2;
			if($val['is_ready'] == 1) $data[$key]['statue'] = 3;
			$data[$key]['detail_url'] = "http://test.admin.mengdd.net/index/Spa/getSpa#/grouptrip/detail/id/" . $val['id'] . "/uid/" . $param['userId'] . "/type/" . $param['useType'];
			unset($data[$key]['status']);unset($data[$key]['is_tops']);unset($data[$key]['is_hot']);unset($data[$key]['is_ready']);
		}
		if($param['oid'] == 1)
		{
			$data = $this->my_sort($data,'price',SORT_ASC,SORT_STRING);
		}
		elseif($param['oid'] == 2)
		{
			$data = $this->my_sort($data,'range',SORT_ASC,SORT_STRING);
		}
		elseif($param['oid'] == 3)
		{
			$data = $this->my_sort($data,'score',SORT_ASC,SORT_STRING);
		}
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
	public function my_sort($arrays,$sort_key,$sort_order=SORT_ASC,$sort_type=SORT_NUMERIC ){ foreach($arrays as $array){ 
            $key_arrays[] = $array[$sort_key];  
        }  
        array_multisort($key_arrays,$sort_order,$sort_type,$arrays);  
        return $arrays;  
    }

    /**
	 * 搜索
	 * 
	 */
	public function searchTour(){
		$tour = model('MallGoodsView');
		$param = $this->param;
		if(is_null($param['keyword'])){
			return $this->err('参数错误！');
		}
		$data = $tour->field('id,title,cover_image AS photo,price_now_adult AS price,price_old_adult,bespoke_time,extra_address AS address,status,coordinate AS location,"" AS tags,"" AS is_tops,"" AS is_hot,"" AS is_ready,"" as detail_url')->where('title','LIKE','%'.$param['keyword'].'%')->/*whereOr('tags','LIKE','%'.$param['keyword'].'%')->*/select();
		foreach($data as $key=>$val)
		{
			$radLat1 = deg2rad(explode(',',$val['location'])[1]);
			$radLat2 = deg2rad(explode(',',$param['location'])[1]);
			$radLng1 = deg2rad(explode(',',$val['location'])[0]);
			$radLng2 = deg2rad(explode(',',$param['location'])[0]);
			$a = $radLat1 - $radLat2;
			$b = $radLng1 - $radLng2;
			$data[$key]['range'] = round(2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6378.137,1);
			$data[$key]['tags'] = $val['tags'] ? $val['tags'] : '暂无';
			$data[$key]['photo'] = $val['photo'] ? 'http://img.mall.mengdd.net' . $val['photo'] : '';
			$data[$key]['bespoke_time'] = date('H:i',strtotime($data[$key]['bespoke_time']));
			$data[$key]['intro'] = model('MallGoodsDetail')->where('seq',0)->value('desc');
			$data[$key]['score'] = model('ParentChildGrade')->where('tour_id',$val['id'])->avg('score') ? model('ParentChildGrade')->where('tour_id',$val['id'])->avg('score') : 0;$data[$key]['statue'] = 0;
			if($tour->where('is_attention','LIKE','%'.$param['useType'].'-'.$param['userId'].'y%')->find()) $data[$key]['isCollected'] = 1;else $data[$key]['isCollected'] = 2;
			$data[$key]['detail_url'] = "http://test.admin.mengdd.net/index/Spa/getSpa#/grouptrip/detail/id/" . $val['id'] . "/uid/" . $param['userId'] . "/type/" . $param['useType'];
			unset($data[$key]['status']);unset($data[$key]['is_tops']);unset($data[$key]['is_hot']);unset($data[$key]['is_ready']);
		}
		return $this->sucs($data,-1,config('view_replace_str.__IMGROOT__'));
	}

	/**
	 * tour收藏取收
	 * 
	 */
	public function travelCollect(){
		$tour = model('MallGoodsView');
		$param = $this->param;
		if(is_null($id = $param['userId'])){
			return $this->err('参数错误！');
		}
		$data['is_attention'] = $tour->where('id',$param['tid'])->value('is_attention').$param['useType'].'-'.$id.'y,';
		$where['is_attention']=array('like','%'.$param['useType'].'-'.$id.'y%');
		$isin = $tour->where('id',$param['tid'])->where($where)->find();
		if($isin) $data['is_attention'] = str_replace($param['useType'].'-'.$id.'y,', '', $isin['is_attention']);
		$result = $tour->isUpdate(true)->save($data,['id'=>$param['tid']]);
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
	 * tour我的收藏
	 * 
	 */
	public function travelCollectList(){
		$tour = model('MallGoodsView');
		$param = $this->param;
		$map['is_attention']=array('like','%'.$param['useType'].'-'.$param['userId'].'y%');
		$map['flag']=1;$map['type'] = $param['type'];
		$data = $tour->where($map)->field('id,title,cover_image AS photo,price_now_adult AS price,price_old_adult,bespoke_time,extra_address AS address,status,coordinate AS location,"" AS tags,"" AS is_tops,"" AS is_hot,"" AS is_ready,"" as detail_url')->order('update_time desc')->select();
		foreach($data as $key=>$val)
		{
			$radLat1 = deg2rad(explode(',',$val['location'])[1]);
			$radLat2 = deg2rad(explode(',',$param['location'])[1]);
			$radLng1 = deg2rad(explode(',',$val['location'])[0]);
			$radLng2 = deg2rad(explode(',',$param['location'])[0]);
			$a = $radLat1 - $radLat2;
			$b = $radLng1 - $radLng2;
			$data[$key]['range'] = round(2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6378.137,1);
			$data[$key]['tags'] = $val['tags'] ? $val['tags'] : '暂无';
			$data[$key]['photo'] = $val['photo'] ? 'http://img.mall.mengdd.net' . $val['photo'] : '';
			$data[$key]['bespoke_time'] = date('H:i',strtotime($data[$key]['bespoke_time']));
			$data[$key]['intro'] = model('MallGoodsDetail')->where('seq',0)->value('desc');
			$data[$key]['score'] = model('ParentChildGrade')->where('tour_id',$val['id'])->avg('score') ? model('ParentChildGrade')->where('tour_id',$val['id'])->avg('score') : 0;$data[$key]['statue'] = 0;
			$data[$key]['isCollected'] = 2;
			$data[$key]['detail_url'] = "http://test.admin.mengdd.net/index/Spa/getSpa#/grouptrip/detail/id/" . $val['id'] . "/uid/" . $param['userId'] . "/type/" . $param['useType'];
			unset($data[$key]['status']);unset($data[$key]['is_tops']);unset($data[$key]['is_hot']);unset($data[$key]['is_ready']);
		}
		return $this->sucs($data,-1,config('view_replace_str.__IMGROOT__'));
	}

	/**
	 * tour打开预定
	 * 
	 */
	public function openDestine(){
		$tour = model('MallGoodsView');
		$param = $this->param;
		$map['flag']=1;$map['id'] = $param['tid'];$map['status'] != 1;
		$info = $tour->field('id,title,price_now_adult AS price,price_old_adult,price_old_child,price_now_child,bespoke_time,extra_address AS address,status,remarks,price_detail,refund_detail')->where($map)->find();
		$infoma = model('MallPriceFestival')->where('goods_id',$info['id'])->select();
		$data['travelInfo'] = $info['remarks'];$data['costInfo'] = $info['price_detail'];$data['refundInfo'] = $info['refund_detail'];
		$dat['travelInfo'] = $info['remarks'];$dat['costInfo'] = $info['price_detail'];$dat['refundInfo'] = $info['refund_detail'];
		if(date('m',time()) == '01' || date('m',time()) == '03' || date('m',time()) == '05' || date('m',time()) == '07' || date('m',time()) == '08' || date('m',time()) == 10 || date('m',time()) == 12)
		{
			for($i=1;$i<32;$i++)
			{
				$data['year'] = date('Y',time());
				$data['month'] = date('m',time()) == 11 || date('m',time()) == 12 ? date('m',time()) : substr(date('m',time()),1,1);
				$data['price'] = $info['price'];
				$data['calendar'][$i]['date'] = $i;
				$data['calendar'][$i]['date_time'] = date('Y-m',time()) . '-' . ($i<10 ? ('0'.$i) : $i);
				$data['calendar'][$i]['price'][0]['type_name'] = '成人';
				$data['calendar'][$i]['price'][0]['type_id'] = 1;
				$data['calendar'][$i]['price'][0]['limit'] = -1;
				$data['calendar'][$i]['price'][0]['old_price'] = $info['price_old_adult'];
				$data['calendar'][$i]['price'][0]['new_price'] = $info['price'];
				$data['calendar'][$i]['price'][1]['type_name'] = '儿童';
				$data['calendar'][$i]['price'][1]['type_id'] = 2;
				$data['calendar'][$i]['price'][1]['limit'] = -1;
				$data['calendar'][$i]['price'][1]['old_price'] = $info['price_old_child'];
				$data['calendar'][$i]['price'][1]['new_price'] = $info['price_now_child'];
				$data['calendar'][$i]['statue'] = $i < (date('d',time()) > 9 ? date('d',time()) : substr(date('d',time()),1,1)) ? 2 : 1;
				if($data['calendar'][$i]['date_time'] == $infoma[0]['time_begin'] || $data['calendar'][$i]['date_time'] == $infoma[0]['time_end'] || ($data['calendar'][$i]['date_time'] > $infoma[0]['time_begin'] && $data['calendar'][$i]['date_time'] < $infoma[0]['time_end']))
				{
					$data['calendar'][$i]['price'][0]['old_price'] = $infoma[0]['price_old_adult'];
					$data['calendar'][$i]['price'][0]['new_price'] = $infoma[0]['price_now_adult'];
					$data['calendar'][$i]['price'][1]['old_price'] = $infoma[0]['price_old_child'];
					$data['calendar'][$i]['price'][1]['new_price'] = $infoma[0]['price_now_child'];
				}
				$data['calendar'][$i]['tag'] = $data['calendar'][$i]['price'][0]['new_price'];
			}$data['calendar'] = array_values($data['calendar']);
			for($i=1;$i<($data['month']+1==3||$data['month']+1==5||$data['month']+1==7||$data['month']+1==8||$data['month']+1==10||$data['month']+1==12||$data['month']+1==13?32:31);$i++)
			{
				$dat['year'] = date('Y',time());
				$dat['month'] = (date('m',time()) == 11 || date('m',time()) == 12 ? date('m',time()) : substr(date('m',time()),1,1)) == 12 ? 1 : (date('m',time()) == 11 || date('m',time()) == 12 ? date('m',time()) : substr(date('m',time()),1,1)) + 1;
				$dat['price'] = $info['price'];
				$dat['calendar'][$i]['date'] = $i;
				$dat['calendar'][$i]['date_time'] = date('Y',time()) . '-' . ($dat['month'] > 10 ? $dat['month'] : '0' . $dat['month']) . '-' . ($i<10 ? ('0'.$i) : $i);
				$dat['calendar'][$i]['price'][0]['type_name'] = '成人';
				$dat['calendar'][$i]['price'][0]['type_id'] = 1;
				$dat['calendar'][$i]['price'][0]['limit'] = -1;
				$dat['calendar'][$i]['price'][0]['old_price'] = $info['price_old_adult'];
				$dat['calendar'][$i]['price'][0]['new_price'] = $info['price'];
				$dat['calendar'][$i]['price'][1]['type_name'] = '儿童';
				$dat['calendar'][$i]['price'][1]['type_id'] = 2;
				$dat['calendar'][$i]['price'][1]['limit'] = -1;
				$dat['calendar'][$i]['price'][1]['old_price'] = $info['price_old_child'];
				$dat['calendar'][$i]['price'][1]['new_price'] = $info['price_now_child'];
				$dat['calendar'][$i]['statue'] = 1;
				if($dat['calendar'][$i]['date_time'] == $infoma[0]['time_begin'] || $dat['calendar'][$i]['date_time'] == $infoma[0]['time_end'] || ($dat['calendar'][$i]['date_time'] > $infoma[0]['time_begin'] && $dat['calendar'][$i]['date_time'] < $infoma[0]['time_end']))
				{
					$dat['calendar'][$i]['price'][0]['old_price'] = $infoma[0]['price_old_adult'];
					$dat['calendar'][$i]['price'][0]['new_price'] = $infoma[0]['price_now_adult'];
					$dat['calendar'][$i]['price'][1]['old_price'] = $infoma[0]['price_old_child'];
					$dat['calendar'][$i]['price'][1]['new_price'] = $infoma[0]['price_now_child'];
				}
				$dat['calendar'][$i]['tag'] = $dat['calendar'][$i]['price'][0]['new_price'];
			}$dat['calendar'] = array_values($dat['calendar']);
		}
		elseif(date('m',time()) == '04' || date('m',time()) == '06' || date('m',time()) == '09' || date('m',time()) == 11)
		{
			for($i=1;$i<31;$i++)
			{
				$data['year'] = date('Y',time());
				$data['month'] = date('m',time()) == 11 || date('m',time()) == 12 ? date('m',time()) : substr(date('m',time()),1,1);
				$data['price'] = $info['price'];
				$data['calendar'][$i]['date'] = $i;
				$data['calendar'][$i]['date_time'] = date('Y-m',time()) . '-' . ($i<10 ? ('0'.$i) : $i);
				$data['calendar'][$i]['price'][0]['type_name'] = '成人';
				$data['calendar'][$i]['price'][0]['type_id'] = 1;
				$data['calendar'][$i]['price'][0]['limit'] = -1;
				$data['calendar'][$i]['price'][0]['old_price'] = $info['price_old_adult'];
				$data['calendar'][$i]['price'][0]['new_price'] = $info['price'];
				$data['calendar'][$i]['price'][1]['type_name'] = '儿童';
				$data['calendar'][$i]['price'][1]['type_id'] = 2;
				$data['calendar'][$i]['price'][1]['limit'] = -1;
				$data['calendar'][$i]['price'][1]['old_price'] = $info['price_old_child'];
				$data['calendar'][$i]['price'][1]['new_price'] = $info['price_now_child'];
				$data['calendar'][$i]['statue'] = $i < (date('d',time()) > 9 ? date('d',time()) : substr(date('d',time()),1,1)) ? 2 : 1;
				if($data['calendar'][$i]['date_time'] == $infoma[0]['time_begin'] || $data['calendar'][$i]['date_time'] == $infoma[0]['time_end'] || ($data['calendar'][$i]['date_time'] > $infoma[0]['time_begin'] && $data['calendar'][$i]['date_time'] < $infoma[0]['time_end']))
				{
					$data['calendar'][$i]['price'][0]['old_price'] = $infoma[0]['price_old_adult'];
					$data['calendar'][$i]['price'][0]['new_price'] = $infoma[0]['price_now_adult'];
					$data['calendar'][$i]['price'][1]['old_price'] = $infoma[0]['price_old_child'];
					$data['calendar'][$i]['price'][1]['new_price'] = $infoma[0]['price_now_child'];
				}
				$data['calendar'][$i]['tag'] = $data['calendar'][$i]['price'][0]['new_price'];
			}$data['calendar'] = array_values($data['calendar']);
			for($i=1;$i<($data['month']+1==3||$data['month']+1==5||$data['month']+1==7||$data['month']+1==8||$data['month']+1==10||$data['month']+1==12||$data['month']+1==13?32:31);$i++)
			{
				$dat['year'] = date('Y',time());
				$dat['month'] = (date('m',time()) == 11 || date('m',time()) == 12 ? date('m',time()) : substr(date('m',time()),1,1)) == 12 ? 1 : (date('m',time()) == 11 || date('m',time()) == 12 ? date('m',time()) : substr(date('m',time()),1,1)) + 1;
				$dat['price'] = $info['price'];
				$dat['calendar'][$i]['date'] = $i;
				$dat['calendar'][$i]['date_time'] = date('Y',time()) . '-' . ($dat['month'] > 10 ? $dat['month'] : '0' . $dat['month']) . '-' . ($i<10 ? ('0'.$i) : $i);
				$dat['calendar'][$i]['price'][0]['type_name'] = '成人';
				$dat['calendar'][$i]['price'][0]['type_id'] = 1;
				$dat['calendar'][$i]['price'][0]['limit'] = -1;
				$dat['calendar'][$i]['price'][0]['old_price'] = $info['price_old_adult'];
				$dat['calendar'][$i]['price'][0]['new_price'] = $info['price'];
				$dat['calendar'][$i]['price'][1]['type_name'] = '儿童';
				$dat['calendar'][$i]['price'][1]['type_id'] = 2;
				$dat['calendar'][$i]['price'][1]['limit'] = -1;
				$dat['calendar'][$i]['price'][1]['old_price'] = $info['price_old_child'];
				$dat['calendar'][$i]['price'][1]['new_price'] = $info['price_now_child'];
				$dat['calendar'][$i]['statue'] = 1;
				if($dat['calendar'][$i]['date_time'] == $infoma[0]['time_begin'] || $dat['calendar'][$i]['date_time'] == $infoma[0]['time_end'] || ($dat['calendar'][$i]['date_time'] > $infoma[0]['time_begin'] && $dat['calendar'][$i]['date_time'] < $infoma[0]['time_end']))
				{
					$dat['calendar'][$i]['price'][0]['old_price'] = $infoma[0]['price_old_adult'];
					$dat['calendar'][$i]['price'][0]['new_price'] = $infoma[0]['price_now_adult'];
					$dat['calendar'][$i]['price'][1]['old_price'] = $infoma[0]['price_old_child'];
					$dat['calendar'][$i]['price'][1]['new_price'] = $infoma[0]['price_now_child'];
				}
				$dat['calendar'][$i]['tag'] = $dat['calendar'][$i]['price'][0]['new_price'];
			}$dat['calendar'] = array_values($dat['calendar']);
		}
		else
		{
			for($i=1;$i<30;$i++)
			{
				$data['year'] = date('Y',time());
				$data['month'] = date('m',time()) == 11 || date('m',time()) == 12 ? date('m',time()) : substr(date('m',time()),1,1);
				$data['price'] = $info['price'];
				$data['calendar'][$i]['date'] = $i;
				$data['calendar'][$i]['date_time'] = date('Y-m',time()) . '-' . ($i<10 ? ('0'.$i) : $i);
				$data['calendar'][$i]['price'][0]['type_name'] = '成人';
				$data['calendar'][$i]['price'][0]['type_id'] = 1;
				$data['calendar'][$i]['price'][0]['limit'] = -1;
				$data['calendar'][$i]['price'][0]['old_price'] = $info['price_old_adult'];
				$data['calendar'][$i]['price'][0]['new_price'] = $info['price'];
				$data['calendar'][$i]['price'][1]['type_name'] = '儿童';
				$data['calendar'][$i]['price'][1]['type_id'] = 2;
				$data['calendar'][$i]['price'][1]['limit'] = -1;
				$data['calendar'][$i]['price'][1]['old_price'] = $info['price_old_child'];
				$data['calendar'][$i]['price'][1]['new_price'] = $info['price_now_child'];
				$data['calendar'][$i]['statue'] = $i < (date('d',time()) > 9 ? date('d',time()) : substr(date('d',time()),1,1)) ? 2 : 1;
				if($data['calendar'][$i]['date_time'] == $infoma[0]['time_begin'] || $data['calendar'][$i]['date_time'] == $infoma[0]['time_end'] || ($data['calendar'][$i]['date_time'] > $infoma[0]['time_begin'] && $data['calendar'][$i]['date_time'] < $infoma[0]['time_end']))
				{
					$data['calendar'][$i]['price'][0]['old_price'] = $infoma[0]['price_old_adult'];
					$data['calendar'][$i]['price'][0]['new_price'] = $infoma[0]['price_now_adult'];
					$data['calendar'][$i]['price'][1]['old_price'] = $infoma[0]['price_old_child'];
					$data['calendar'][$i]['price'][1]['new_price'] = $infoma[0]['price_now_child'];
				}
				$data['calendar'][$i]['tag'] = $data['calendar'][$i]['price'][0]['new_price'];
			}$data['calendar'] = array_values($data['calendar']);
			for($i=1;$i<($data['month']+1==3||$data['month']+1==5||$data['month']+1==7||$data['month']+1==8||$data['month']+1==10||$data['month']+1==12||$data['month']+1==13?32:31);$i++)
			{
				$dat['year'] = date('Y',time());
				$dat['month'] = (date('m',time()) == 11 || date('m',time()) == 12 ? date('m',time()) : substr(date('m',time()),1,1)) == 12 ? 1 : (date('m',time()) == 11 || date('m',time()) == 12 ? date('m',time()) : substr(date('m',time()),1,1)) + 1;
				$dat['price'] = $info['price'];
				$dat['calendar'][$i]['date'] = $i;
				$dat['calendar'][$i]['date_time'] = date('Y',time()) . '-' . ($dat['month'] > 10 ? $dat['month'] : '0' . $dat['month']) . '-' . ($i<10 ? ('0'.$i) : $i);
				$dat['calendar'][$i]['price'][0]['type_name'] = '成人';
				$dat['calendar'][$i]['price'][0]['type_id'] = 1;
				$dat['calendar'][$i]['price'][0]['limit'] = -1;
				$dat['calendar'][$i]['price'][0]['old_price'] = $info['price_old_adult'];
				$dat['calendar'][$i]['price'][0]['new_price'] = $info['price'];
				$dat['calendar'][$i]['price'][1]['type_name'] = '儿童';
				$dat['calendar'][$i]['price'][1]['type_id'] = 2;
				$dat['calendar'][$i]['price'][1]['limit'] = -1;
				$dat['calendar'][$i]['price'][1]['old_price'] = $info['price_old_child'];
				$dat['calendar'][$i]['price'][1]['new_price'] = $info['price_now_child'];
				$dat['calendar'][$i]['statue'] = 1;
				if($dat['calendar'][$i]['date_time'] == $infoma[0]['time_begin'] || $dat['calendar'][$i]['date_time'] == $infoma[0]['time_end'] || ($dat['calendar'][$i]['date_time'] > $infoma[0]['time_begin'] && $dat['calendar'][$i]['date_time'] < $infoma[0]['time_end']))
				{
					$dat['calendar'][$i]['price'][0]['old_price'] = $infoma[0]['price_old_adult'];
					$dat['calendar'][$i]['price'][0]['new_price'] = $infoma[0]['price_now_adult'];
					$dat['calendar'][$i]['price'][1]['old_price'] = $infoma[0]['price_old_child'];
					$dat['calendar'][$i]['price'][1]['new_price'] = $infoma[0]['price_now_child'];
				}
				$dat['calendar'][$i]['tag'] = $dat['calendar'][$i]['price'][0]['new_price'];
			}$dat['calendar'] = array_values($dat['calendar']);
		}
		$datas[] = $data;$datas[] = $dat;
		$dataArr = $this->suc($datas,'',config('view_replace_str.__IMGROOT__'));
		$dataArr['desine'][0]['title'] = '行程说明';$dataArr['desine'][0]['content'] = $info['remarks'];$dataArr['desine'][1]['title'] = '费用详情';$dataArr['desine'][1]['content'] = $info['price_detail'];$dataArr['desine'][2]['title'] = '退款说明';$dataArr['desine'][2]['content'] = $info['refund_detail'];
		return $dataArr;
	}

	public function getAliOrder(){
		$aop = new AopClient();
		$aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
		$aop->appId = "2018010301555841";
		$aop->rsaPrivateKey = 'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDED2+ed0K1y3H+mon1UcJrwp6nLB9GZFAA/7E9n05pSjNWuiVFE4hn5TOX6axYizasdv39oYf7UEpzjLvKn9UNOTeqYHMEVzLNG6/p6qXBh5KTuT45nycKdszPE9aCnM5RYgL4KE/nk1ukpG1ika+7fenGqC/8nlCigdIJ72QXI2ZacchyitQ3CZ6cBnXGvWvocImyrWBZYQymG8iVIdWPJfJbEb9UK0tSGaV9kY05dBKdKXj6o1zZqS+l0uzrsGUcZ0cP5NwNYuxPU7EbTUbusf27FL/PccgesBpOKHK69K9ny6vc+mzsD6cbYy6OluVRohn2mUv5pQTofiy/95epAgMBAAECggEBAKAUkmhqq9dPg4YEnDvnQ71ErNGGHwsUgJYwL4FG/3jMktpvJlseNbPO2q9gpc2t7Tgn9/4M08CIsCFkeThaBFTFsQO1uHOE1v/NaXauliRME4v2Ji5aGkBa+6Lgabc/XN3qDs6b65IDKUJm1sEyfq+xgR0o2fWDNgdZxCtEgQ659J6WxLdLw40ps4QJc/bnR6jNBOhX/nP0XQdPH6BbW7ZvWi6ElGZQvlLo4JDeYDDHyvcWAd4g1x0QxczHo/GL32jDOcp8zoNhH0xiFcrCc8QaoXvHwuPIY50jDTyn9l3k8ZARnVgPRbrIDh2IZRa8RQibqrz0gu7dvgNWXWfrGvkCgYEA54kCqwW+bR9brwMAfMAagU2+fwNi6y9TZNDgtfuB1vY3OYxgW+hPuOtf2Jnd01jktwnLg7KoH/gquIFZbxRMR/zJV6k3U7Gwu7Fya8mhjVyP92zwQrO1fkM/WiPuvGre6hF2O5nr4OZNM618qzkNsWypOoUkw6lcnyep8Zl/Va8CgYEA2MbV1YJh2wb3mVwgQcMxahxysP6WsbGQuiN/rrgTgLVMJNWMvMQP++cvISFJ6imzP+jTk6VCaRY8LAsBpSRtJresY+WFozmgBDZru54VBYV43liVzY0flOnKKieKYyBtua2eqHLWhoojWrnVAnZ5HpdXVZIMZrGNC70w9qizlicCgYB01+a5yedEiGurUVeAnS2DDWnSDTJxP2vVV7fe/rKSeaR67UV/fCgnSNkQpO2WB6k8WbwTlShVIdblT5mDffnU5lPxYhriqKxou+7jSFi3zvt0QMyqzKgNtQAWjEWhXklqVC+XemDYGUDikG12tw8a95wbRS+9cg2k385adz0UTwKBgHap6XYWCEEggDs4HgRBuHQQnIvc4VrmC4aJViSraSmklMj5CTBv1xwawkbNdSu0pgXPCrqg1Ui3PjsRz1W6KfHuy3RnuQ7PxZNowvKSJ4m9NZvpPB+oJ+iZTexjdrKqlsX0j4xASMSfK5lHbp4JgmXHjKwv5Y/1k3MgnoP3d08zAoGBAKBrFOkhmHSF1/NP7RvlMmheR14BPDbgQleIH653yBN3JWypw2encnNJG1571hhE1fShpkr5NY0s9BnYP+DnGrV3l91TFvXVNOW7pTfmizq70ZCJ78aD7uDSwPeq0M7yI4PhgzwIp+XpOQtI1Zw5f32oIqL7PEVJmg2uXGb461Uw';
		$aop->format = "json";
		$aop->charset = "UTF-8";
		$aop->signType = "RSA2";
		$aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArckYOAoJKJB3QICOlbOSZGBLwFhpPHykubL6Q8vw2oXTlWnEagA6KkzaKH6pvF8N0C6fEvgvocbn3Nz813t/ONq4bZty1XP9TW1ZLM+4JdCEhLGUHuvBrJE1Pgtihu3w+mCpJbDcXwC6F/SzyzBMh5D1jRr9H5wIvrr+wW7oKEOo2UHdPmoCwZUQMlviVSuOgAsDF2dBq0dOhs/zxrURJ/rkwKljlRO287/VE4FHBG2nB2iGXb6bC4OFCeIEtgjBGbG1kjI4+feRsrMr5D9HmvCD3z6q2vBSOoqXXnikFMe/Rk3jU7qD6NwkePvIRw4qyVSycXZXH80GN0cQMGMP+QIDAQAB';
		//存订单
		$param = $this->param;
		$data['user_id'] = $param['userId'];
		$data['goods_id'] = $param['tid'] ? $param['tid'] : '';
		$data['order_sn'] = $orde = 'MDD' . $param['userType'] . $param['userId'] . date('YmdHis',time());
		$data['play_time'] = $param['year'] . '-' . ($param['month'] > 9 ? $param['month'] : '0' . $param['month']) .'-' . ($param['date'] > 9 ? $param['date'] : '0' . $param['date']);
		$data['pay_time'] = date('Y-m-d',time());
		$data['big_num'] = explode(',',$param['nums'])[0] ? explode(',',$param['nums'])[0] : '';
		$data['small_num'] = explode(',',$param['nums'])[1] ? explode(',',$param['nums'])[1] : '';
		$data['totle_price'] = $pric = ($param['totalPrice'] ? $param['totalPrice'] : '');
		if(!model('MallOrder')->saveOrder($data)) return $this->err('订单失败');//->isUpdate(false)->save($data);
		$sql = "select order_sn,count(*) as count from cc_mall_order group by order_sn having count>1";
		$orderRes = model('ParentChildOrder')->query($sql);
		if($orderRes['count']) model('MallOrder')->isUpdate(true)->save(array('order_sn'=>$data['order_sn'] . rand(0,9)),['order_sn'=>$data['order_sn']]);
		//实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
		$request = new AlipayTradeAppPayRequest();
		//SDK已经封装掉了公共参数，这里只需要传入业务参数http://mengdd.net/pay.html
		$bizcontent = "{\"body\":\"亲子游门票\"," 
		                . "\"subject\": \"App支付购买\","
		                . "\"out_trade_no\": \"$orde\","
		                . "\"timeout_express\": \"30m\"," 
		                . "\"total_amount\": \"$pric\","
		                . "\"product_code\":\"QUICK_MSECURITY_PAY\""
		                . "}";
		$request->setNotifyUrl("");
		$request->setBizContent($bizcontent);
		//这里和普通的接口调用不同，使用的是sdkExecute
		$response = $aop->sdkExecute($request);
		//htmlspecialchars是为了输出到页面时防止被浏览器将关键参数html转义，实际打印到日志以及http传输不会有这个问题
		return $this->suc(array('order_info'=>$response,'order_id'=>$orde));//echo htmlspecialchars($response);//就是orderString 可以直接给客户端请求，无需再做处理。
	}
	public function notice(){
		$aop = new AopClient;
		$aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEArckYOAoJKJB3QICOlbOSZGBLwFhpPHykubL6Q8vw2oXTlWnEagA6KkzaKH6pvF8N0C6fEvgvocbn3Nz813t/ONq4bZty1XP9TW1ZLM+4JdCEhLGUHuvBrJE1Pgtihu3w+mCpJbDcXwC6F/SzyzBMh5D1jRr9H5wIvrr+wW7oKEOo2UHdPmoCwZUQMlviVSuOgAsDF2dBq0dOhs/zxrURJ/rkwKljlRO287/VE4FHBG2nB2iGXb6bC4OFCeIEtgjBGbG1kjI4+feRsrMr5D9HmvCD3z6q2vBSOoqXXnikFMe/Rk3jU7qD6NwkePvIRw4qyVSycXZXH80GN0cQMGMP+QIDAQAB';
		$flag = $aop->rsaCheckV1($_POST, NULL, "RSA2");var_dump($flag);exit();
		return $this->suc($flag);
	}

public function getWxOrder(){
$url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
$notify_url = "http://www.mengdd.net/pay.php";

$onoce_str = $this->createNoncestr();

//存订单
$param = $this->param;
$dat['user_id'] = $param['userId'];
$dat['goods_id'] = $param['tid'];
$dat['order_sn'] = $orde = 'MDD' . $param['userType'] . $param['userId'] . date('YmdHis',time());
$dat['play_time'] = $param['year'] . '-' . ($param['month'] > 9 ? $param['month'] : '0' . $param['month']) .'-' . ($param['date'] > 9 ? $param['date'] : '0' . $param['date']);
$dat['big_num'] = explode(',',$param['nums'])[0];
$dat['small_num'] = explode(',',$param['nums'])[1];
$dat['totle_price'] = $pric = $param['totalPrice'];
// model('MallOrder')->isUpdate(false)->save($dat);

$data["appid"] = 'wxfe13b96fa0d89de3';
$data["body"] = "亲子游门票值";
$data["mch_id"] = "1495438232";
$data["nonce_str"] = $onoce_str;
$data["notify_url"] = $notify_url;
$data["out_trade_no"] = $orde;
$data["spbill_create_ip"] = $this->get_client_ip();
$data["total_fee"] = 88;
$data["trade_type"] = "APP";
$sign = $this->getSign($data);
$data["sign"] = $sign;

$xml = $this->arrayToXml($data);
$response = $this->postXmlCurl($xml, $url);

//将微信返回的结果xml转成数组
$response = $this->xmlToArray($response);$response['time_stamp'] = time();$response['order_id'] = $orde;$response['package'] = "Sign=WXPay";

//返回数据
return $this->suc($response);
}
//签名
public function getSign($Obj){
foreach ($Obj as $k => $v){
    $Parameters[$k] = $v;
}
//签名步骤一：按字典序排序参数
ksort($Parameters);
$String = $this->formatBizQueryParaMap($Parameters, false);
//echo '【string1】'.$String.'</br>';
//签名步骤二：在string后加入KEY.$this->config['api_key']
$apike = "Hskjmdd9876543211234567897777777";
$String = $String."&key=".$apike;
//echo "【string2】".$String."</br>";
//签名步骤三：MD5加密
$String = md5($String);
//echo "【string3】 ".$String."</br>";
//签名步骤四：所有字符转为大写
$result_ = strtoupper($String);
//echo "【result】 ".$result_."</br>";
return $result_;
}
/**
*  作用：产生随机字符串，不长于32位
*/
public function createNoncestr( $length = 32 ){
$chars = "abcdefghijklmnopqrstuvwxyz0123456789"; 
$str ="";
for ( $i = 0; $i < $length; $i++ )  { 
    $str.= substr($chars, mt_rand(0, strlen($chars)-1), 1); 
} 
return $str;
}	
//数组转xml
public function arrayToXml($arr){
$xml = "<xml>";
foreach ($arr as $key=>$val){
if (is_numeric($val)){
    $xml.="<".$key.">".$val."</".$key.">";
}else{
    $xml.="<".$key."><![CDATA[".$val."]]></".$key.">"; 
}
}
$xml.="</xml>";
return $xml;
}
/**
*  作用：将xml转为array
*/
public function xmlToArray($xml){  
//将XML转为array       
$array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);   
return $array_data;
}
/**
*  作用：以post方式提交xml到对应的接口url
*/
public function postXmlCurl($xml,$url,$second=30){  
//初始化curl       
$ch = curl_init();
//设置超时
curl_setopt($ch, CURLOPT_TIMEOUT, $second);
//这里设置代理，如果有的话
//curl_setopt($ch,CURLOPT_PROXY, '8.8.8.8');
//curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
curl_setopt($ch,CURLOPT_URL, $url);
curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
//设置header
curl_setopt($ch, CURLOPT_HEADER, FALSE);
//要求结果为字符串且输出到屏幕上
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
//post提交方式
curl_setopt($ch, CURLOPT_POST, TRUE);
curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
//运行curl
$data = curl_exec($ch);
//返回结果

if($data){
curl_close($ch);
return $data;
}else{
$error = curl_errno($ch);
echo "curl出错，错误码:$error"."<br>";
curl_close($ch);
return false;
}
}
/*
获取当前服务器的IP
*/
public function get_client_ip(){
if ($_SERVER['REMOTE_ADDR']) {
$cip = $_SERVER['REMOTE_ADDR'];
} elseif (getenv("REMOTE_ADDR")) {
$cip = getenv("REMOTE_ADDR");
} elseif (getenv("HTTP_CLIENT_IP")) {
$cip = getenv("HTTP_CLIENT_IP");
} else {
$cip = "unknown";
}
return $cip;
}
/**
*  作用：格式化参数，签名过程需要使用
*/
public function formatBizQueryParaMap($paraMap, $urlencode){
$buff = "";
ksort($paraMap);
foreach ($paraMap as $k => $v){
if($urlencode){
    $v = urlencode($v);
}
$buff .= $k . "=" . $v . "&";
}
$reqPar;
if (strlen($buff) > 0){
$reqPar = substr($buff, 0, strlen($buff)-1);
}
return $reqPar;
}


	/*
	 *支付订单详细
	 */
	public function getOrderPayInfo(){
		$param = $this->param;
		$data = Db::view('Mall_order o','totle_price AS price,order_sn AS order_id,pay_type,big_num,small_num,create_time')->view('Mall_goods_view v','price_old_adult,price_old_child,price_now_adult,price_now_child','o.goods_id = v.id')->view('Mall_seller_info s','shop_name,shop_photo AS shop_img','v.sid = s.sid')->where('order_sn',$param['orderId'])->find();
		if(!$data){
			return $this->err('参数错误！');
		}
		$data['old_price'] = $data['price_old_adult'] * $data['big_num'] + $data['price_old_child'] * $data['small_num'];
		$data['discount'] = $data['old_price'] - $data['price'];
		$data['pay_time'] = date('Y.m.d H:i:s',$data['create_time']);
		$data['pay_count'] = $param['userType'] == 1 ? model('Parents')->where('id',$param['userId'])->value('tel') : ($param['userType'] == 2 ? model('Teachers')->where('id',$param['userId'])->value('tel') : model('Headmasters')->where('id',$param['userId'])->value('tel'));
		$data['type_name'] = $data['pay_type'] == 0 ? '支付宝' : '微信';
		$data['shop_img'] = $data['shop_img'] ? 'http://img.mall.mengdd.net' . $data['shop_img'] : '';
		$data['ticket_detail'][0]['name'] = '成人';
		$data['ticket_detail'][0]['price'] = $data['price_now_adult'];
		$data['ticket_detail'][0]['num'] = $data['big_num'];
		$data['ticket_detail'][1]['name'] = '儿童';
		$data['ticket_detail'][1]['price'] = $data['price_now_child'];
		$data['ticket_detail'][1]['num'] = $data['small_num'];
		unset($data['create_time']);
		return $this->suc($data);
	}

	/*
	 *订单列表	
	 */
	public function getMyTourOrder(){
		$param = $this->param;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		$tdata = Db::view('Tour_sign_up s','id,school_id,tour_id,big_num,small_num,totle_num')->view('Mall_goods_view t','title,cover_image AS photo,shop_begin,shop_end,shop_week,price_now_adult AS price','t.id = s.tour_id')->where('parent_id',$param['userId'])->where('s.status',1)->where('s.flag',1)->where('t.status',1)->where('s.flag',1)->order('t.update_time desc')->select();
		$dat = Db::view('Mall_order o','id,order_sn AS order_id,goods_id AS tour_id,play_time AS time,totle_price AS price,status,order_status,rufund_time')->view('Mall_goods_view v','title,cover_image AS photo,price_now_adult AS price_new_adult,price_old_adult,bespoke_time,extra_address AS address,type,shop_begin,shop_end,shop_week,coordinate AS location','o.goods_id = v.id')->view('Mall_goods_detail d','desc AS intro','v.id = d.goods_id')->field('"" AS detail_url')->where('o.flag',1)->where('user_id',$param['userId'])->order('o.create_time DESC')->select();
		if(count($dat) < 10){
			$nextStartId = -1;
			$data = $dat;
		}else{
			$nextStartId = $param['nextStartId'];
			$data = Db::view('Mall_order o','id,order_sn AS order_id,goods_id AS tour_id,play_time AS time,totle_price AS price,status,order_status,rufund_time')->view('Mall_goods_view v','title,cover_image AS photo,price_now_adult AS price_new_adult,price_old_adult,bespoke_time,extra_address AS address,type,shop_begin,shop_end,shop_week,coordinate AS location','o.goods_id = v.id')->view('Mall_goods_detail d','desc AS intro','v.id = d.goods_id')->field('"" AS detail_url')->where('o.flag',1)->where('user_id',$param['userId'])->order('o.create_time DESC')->limit($nextStartId,10)->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= count($dat) || count($data) == 0){
				$nextStartId = -1;
			}
		}
		foreach($data as $k=>$v)
		{
			if(model('MallGoodsView')->where('id',$val['id'])->where('is_attention','LIKE','%'.$param['useType'].'-'.$param['userId'].'y%')->find()) $data[$k]['isCollected'] = 1;else $data[$k]['isCollected'] = 2;
			$data[$k]['photo'] = $data[$k]['photo'] ? 'http://img.mall.mengdd.net' . $data[$k]['photo'] : '';
			$data[$k]['bespoke_time'] = $data[$k]['bespoke_time'] ? date('H:i',$data[$k]['bespoke_time']) : '';
			$data[$k]['my_score'] = model('MallScore')->where('order_id',$v['order_id'])->value('score') ? 2 : 1;
			$data[$k]['time_name'] = '游玩时间';
			$data[$k]['statue'] = $v['status'];
			if($v['order_status'] == 3)
			{
				$data[$k]['statue'] = 3;
			}
			else
			{
				$data[$k]['statue'] = $v['status'];
			}
			$data[$k]['score'] = 3;
			unset($data[$k]['shop_begin']);unset($data[$k]['shop_end']);unset($data[$k]['shop_week']);unset($data[$k]['status']);unset($data[$k]['order_status']);unset($data[$k]['rufund_time']);
		}
		return $this->suc($data,$nextStartId,config('view_replace_str.__IMGROOT__'));
	}

	/*
	 *订单详情
	 */
	public function getMyOrderInfo(){
		$param = $this->param;
		$data = Db::view('Mall_order o','goods_id AS tour_id,totle_price AS price,order_sn AS order_id,pay_type,big_num,small_num,play_time AS open_time,create_time,status,order_status,rufund_time')->view('Mall_goods_view v','title AS tour_name,cover_image AS tour_img,price_old_adult,price_old_child,price_now_adult,price_now_child','o.goods_id = v.id')->view('Mall_seller_info s','shop_name,shop_photo AS shop_img,linktel AS link_tel','v.sid = s.sid')->where('order_sn',$param['orderId'])->find();
		// $data['old_price'] = $data['price_old_adult'] * $data['big_num'] + $data['price_old_child'] * $data['small_num'];
		$data['price'] = $data['price_now_adult'];
		if($data['order_status'] == 3)
		{
			$data['statue'] = 3;
		}
		else
		{
			$data['statue'] = $data['status'];
		}
		$data['order_num'] = $data['big_num'] + $data['small_num'];
		// $data['discount'] = $data['old_price'] - $data['price'];
		$data['open_time_name'] = '游玩时间';
		$data['order_time'] = date('Y.m.d H:i:s',$data['create_time']);
		$data['pay_time'] = date('Y.m.d H:i:s',$data['create_time']);
		$data['pay_count'] = $data['pay_phone'] = $param['userType'] == 1 ? model('Parents')->where('id',$param['userId'])->value('tel') : ($param['userType'] == 2 ? model('Teachers')->where('id',$param['userId'])->value('tel') : model('Headmasters')->where('id',$param['userId'])->value('tel'));
		$data['pay_type_name'] = $data['pay_type'] == 0 ? '支付宝' : '微信';
		$data['tour_img'] = $data['tour_img'] ? 'http://img.mall.mengdd.net' . $data['tour_img'] : '';
		$data['shop_img'] = $data['shop_img'] ? 'http://img.mall.mengdd.net' . explode('|',$data['shop_img'])[0] : '';
		$data['my_score'] = model('MallScore')->where('order_id',$data['order_id'])->value('score') ? 2 : 1;
		$data['refund_cost'] = '';
		$data['refund_price'] = '';
		$data['refund_type'] = '';
		$data['refund_apply_time'] = '';
		$data['refund_time'] = '';
		$data['refund_reason'] = '';
		$data['refund_refuse'] = '';
		$data['ticket_detail'][0]['name'] = '成人';
		$data['ticket_detail'][0]['price'] = $data['price_now_adult'];
		$data['ticket_detail'][0]['num'] = $data['big_num'];
		$data['ticket_detail'][1]['name'] = '儿童';
		$data['ticket_detail'][1]['price'] = $data['price_now_child'];
		$data['ticket_detail'][1]['num'] = $data['small_num'];
		unset($data['create_time']);unset($data['status']);unset($data['order_status']);unset($data['rufund_time']);
		return $this->suc($data);
	}

	/*
	 *活动评分
	 */
	public function gradeTour(){
		$param = $this->param;
		$data['goods_id'] = $param['tourId'];
		$data['user_id'] = $param['userId'];
		$data['score'] = $param['score'];
		$data['order_id'] = $param['orderId'];
		$result = model('MallScore')->isUpdate(false)->save($data);
		return $this->suc();
	}

	/*
	 *订单退款
	 */
	public function refundOrder(){
		$param = $this->param;
		$data['reason'] = $param['remark'];
		$data['apply_time'] = date('Y-m-d H:i:s',time());
		$data['rufund_time'] = NULL;
		$data['order_status'] = 3;
		$result = model('MallOrder')->isUpdate(true)->save($data,['order_sn'=>$param['orderId']]);
		if($result)
		{
			return $this->suc();
		}
		else
		{
			return $this->err('失败');
		}
	}

	/*
	 *退款说明
	 */
	public function refundOrderInfo(){
		$param = $this->param;
		$data = Db::view('Mall_order o','order_sn AS oid')->view('Mall_goods_view v','refund_detail AS refund_info','o.goods_id = v.id')->where('order_sn',$param['orderId'])->find();
		$data['refundSteps'][0]['name'] = '提交申请';$data['refundSteps'][0]['info'] = '提交退款申请，等待审核。';
		$data['refundSteps'][1]['name'] = '商户及平台处理退款';$data['refundSteps'][1]['info'] = '您的退款申请已受理，商户及平台会尽快完成审核，审核结果需要1-2个工作日。';
		$data['refundSteps'][2]['name'] = '第三方支付平台处理';$data['refundSteps'][2]['info'] = '商户及平台审核通过后退款申请将提交至第三方支付平台处理，第三方支付平台将会在1-3个工作日内完成处理。';
		$data['refundSteps'][3]['name'] = '退款结果';$data['refundSteps'][3]['info'] = '商户及平台处理完成后，您的退款将在扣除相应手续费后退还到您原支付平台上，请关注到账提醒，具体到账时间以原支付平台为准。';
		return $this->suc($data);
	}

	/*
	 *删除订单
	 */
	public function deleteMyOrder(){
		$param = $this->param;
		$result = model('MallOrder')->isUpdate(true)->save(array('flag'=>2),['order_sn'=>$param['orderId']]);
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
	 * 获取列表
	 * 
	 */
	public function getTourList(){
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
			if(model('TourSignUp')->where('tour_id',$val['id'])->find()) $data[$key]['statue'] = 2;
			$data[$key]['detail_url'] = "http://test.admin.mengdd.net/index/Spa/getSpa#/grouptrip/detail/id/" . $val['id'] . "/uid/" . $param['userId'] . "/type/" . $param['useType'];
			unset($data[$key]['status']);
		}
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
			$data[$k]['detail_url'] = "http://test.admin.mengdd.net/index/Spa/getSpa#/familytripdetail/id/" . $v['id'] . "/uid/" . $param['userId'] . "/type/3/from/2";
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
			$datas['down'][$nex]['detail_url'] = 'http://test.admin.mengdd.net/index/Spa/getSpa#/graduation/detail/id/'.model('ChildSchoolAlbum')->value('id').'/uid/'.$param['userId'].'/type/3/from/1';
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
			$datas['up'][$pre]['detail_url'] = 'http://test.admin.mengdd.net/index/Spa/getSpa#/graduation/detail/id/'.model('ChildSchoolAlbum')->value('id').'/uid/'.$param['userId'].'/type/3/from/1';
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
		return $this->suc($data,'','',$totalNum);
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
				$data = model('Story')->field($field)->where($where)->where('type',-1)->order('update_time')->select();
			}
			else
			{
				$data = $Model->field($field)->where($where)->order('update_time')->select();
			}
		}else{
			$nextStartId = $param['nextStartId'];
			if($param['type'] == 1)
			{
				$data = model('Story')->field($field)->where($where)->where('type',-1)->limit($nextStartId,10)->order('update_time')->select();
			}
			else
			{
				$data = $Model->field($field)->where($where)->limit($nextStartId,10)->order('update_time')->select();
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
		if($param['xid'] > 100000) $data = $sto->where('id',$param['xid'] - 100000)->where('flag',1)->field('id,audio,photo,title,content')->order('update_time desc')->select();
		else $data = $sto->where('type',$param['xid'])->field('id,audio,photo,title,content')->where('flag',1)->order('update_time desc')->select();
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
	 * 读取小故事
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
		}
		$data['is_collect'] = $story->where('id',$param['sid'])->value('is_collect').'3-'.$id.'y,';
		$where['is_collect']=array('like','%3-'.$id.'y%');
		$isin = $story->where('id',$param['sid'])->where($where)->find();
		if($isin) $data['is_collect'] = str_replace('3-'.$id.'y,', '', $isin['is_collect']);
		$result = $story->isUpdate(true)->save($data,['id'=>$param['sid']]);
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
		return $this->suc($datas,'',config('view_replace_str.__IMGROOT__'));
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