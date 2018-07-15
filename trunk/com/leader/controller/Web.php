<?php
namespace app\leader\controller;
use think\Controller;
use think\Db;
/**
 * 资讯管理
 * @author ji
 * @version 创建时间：2018.4.4
 * 类说明
 */
class Web extends Controller{
	
	/**
	 * 获取新闻详情
	 * 
	 */
	public function reportDetail(){
		$report = model('Report');
		if(is_null($id = input('id'))){
			return $this->err('参数错误！');
		}
		$field = 'id,title,source,visit_num,up_num,content';
		$data = $report->field($field)->find($id);
		return $this->suc($data);
	}
	
	/**
	 * 失败
	 * @param $errorMsg
	 * @param string $errorCode
	 * @param string $ambulance
	 * @return array
	 */
	public function err($errorMsg,$errorCode='',$ambulance=''){
		$result = array(
				'result' => 'n',
				'errorCode' => $errorCode,
				'errorMsg' => $errorMsg
		);
	
		if(!empty($ambulance)){
			$result['ambulance'] = $ambulance;
		}
		return $result;
	}
	
	/**
     * 成功
     * @param $data
     * @param $nextStartId
     * @param string $ambulance
     * @return array
     */
	public function suc($data,$nextStartId='',$ambulance=''){
		$data = $this->handleBackData($data);
		$result = array(
				'result' => 'y',
				'data' => is_null($data)?"":$data,
		);
		if(!empty($ambulance)){
			$result['ambulance'] = $ambulance;
		}
		if(is_int($nextStartId)){//分页参数
			$result['nextStartId'] = $nextStartId;
		}
		return $result;
	}
	
	/**
	 * 递归数组键改为驼峰命名
	 * @param unknown $data
	 * @param unknown $backData
	 */
	protected function handleBackData($data = array()){
		$backData = array();
		if(is_object($data)){
			$data = $data->toArray();
		}
		if(!is_array($data)||empty($data)){
			return $backData;
		}
		foreach($data as $k=>$v){
			if(is_object($v)){
				$v = $v->toArray();
			}
			$_key = $this->convertUnder($k);
			$backData[$_key]= is_array($v)?$this->handleBackData($v):$v;
		}
		return $backData;
	}
	
	/**
	 * 下划线转驼峰命名
	 * @param unknown $str
	 * @param string $ucfirst  如为true 首字母大写
	 * @return Ambigous <string, unknown>
	 */
	protected function convertUnder( $str , $ucfirst = false){
		$str = ucwords(str_replace('_', ' ', $str));
		$str = str_replace(' ','',lcfirst($str));
		return $ucfirst ? ucfirst($str) : $str;
	}
}