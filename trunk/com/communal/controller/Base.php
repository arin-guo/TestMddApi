<?php
namespace app\communal\controller;
use think\Controller;
use think\Db;
/**
 * 基础类
 * @author chenlisong E-mail:chenlisong1021@163.com 
 * @version 创建时间：2017年6月22日 下午2:02:54 
 * 类说明
 */
class Base extends Controller{
	
	/**
	 * 前置操作
	 */
	public function _initialize(){
		request()->header('Content-Type:application/json; charset=utf-8');
 		$this->param = request()->param();
		$params = $this->param;
		$sign = $params['sign'];
 		if(isset($sign)){
 			if($sign != getFields('app_ajax_test_token')){//测试token
 				//效验时间戳
 				if((time() - $params['timestamp']) > 600000){
 					json($this->err('访问超时！',-10003))->send();
 					exit;
 				}
 				$sysSign = $this->generateSign($params);
 				if($sysSign != $sign) {
 					json($this->err('签名错误！',-10002))->send();
 					exit;
 				}
 			}
 		}else{
 			json($this->err('签名错误！',-10001))->send();
 			exit;
 		}
 		//验证session
 		$moduleName = strtolower(request()->controller());
 		if($moduleName != 'login' && $moduleName != 'system'){//过滤无需验证登录的模块
 			$userId = $this->param['userId'];
 			$sessionId = $this->param['sessionId'];$this->param['useType'] = $this->param['useType'] ? $this->param['useType'] : $this->param['userType'];
 			$checkResult = $this->checkSession($sessionId,$userId,$this->param['useType']);
 			if($checkResult['status'] == 0){
 				json($this->err($checkResult['msg'],"-10000"))->send();
 				exit;
 			}
 		}
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
	public function sucs($data,$nextStartId='',$ambulance='',$totalNum=''){/*ji add*/
		$data = $this->handleBackData($data);
		$result = array(
				'result' => 'y',
				'data' => is_null($data)?"":$data,
		);
		if(!empty($ambulance)){
			$result['ambulance'] = $ambulance;
		}
		if($nextStartId == -1){//分页参数
			$result['nextStartId'] = -1;
		}
		if(!empty($totalNum) || $totalNum == 0){
			$result['totalNum'] = $totalNum;
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
	
	/**
	 * MD5加密签名
	 * @param $params
	 * @return string
	 */
	protected function generateSign($params){
		ksort($params);//所有请求参数按照字母先后顺序排序
		$key = getFields('app_ajax_return_key');
		$timestamp = $params['timestamp'];
		//把所有参数名和参数值串在一起
		foreach ($params as $k => $v){
			if($k != 'sign'){
				$newSign .= strtolower($k)."=".$v."&";
			}
		}
		$newSign = substr($newSign,0,strlen($newSign)-1);
		$newSign .= $key;
		//使用MD5进行加密，再转化成小写
		return strtolower((md5($newSign)));
	}
	
	/**
	 * 判断会话是否存在
	 * 0：请先登录系统 -1：该账号在其他地方登录1：成功
	 */
	protected function checkSession($sessionId,$userId,$type){
		$LoginSession = model('LoginSession');
		$result['status'] = array('status'=>1,'msg'=>'');
		if(empty($userId) || empty($sessionId)){
			$result['status'] = 0;
			$result['msg'] = "请先登录系统!";
		}
		$where['user_id'] = $userId;
		$where['type'] = $type;//园长端
		$info = $LoginSession->where($where)->find();
		if(count($info) == 0){
			$result['status'] = 0;
			$result['msg'] = "请先登录系统!";
			return $result;
		}elseif(time() > $info['overdue_time']){
			$result['status'] = 0;
			$result['msg'] = "请重新登录系统!";
			return $result;
		}elseif ($info['session_id'] != $sessionId){
			$result['status'] = 0;
			$result['msg'] = "该账号在其他地方登录";
			return $result;
		}else{
			return $result;
		}
	}
}