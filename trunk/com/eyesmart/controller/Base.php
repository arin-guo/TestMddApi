<?php
namespace app\eyesmart\controller;
use app\common\model\LoginSession;
use think\Controller;
/**
 * 
 * @author chenlisong
 *
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
	public function suc($data,$nextStartId,$ambulance=''){
		$result = array(
				'result' => 'y',
				'data' => is_null($data)?"":$data,
		);
		if(!empty($ambulance)){
			$result['ambulance'] = $ambulance;
		}
		if($nextStartId != 0){//分页参数
			$result['nextStartId'] = $nextStartId;
		}
		return $result;
	}

    /**
     * MD5加密签名
     * @param $params
     * @return string
     */
	public function generateSign($params){
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
	private function checkSession($sessionId,$userId){
		$result['status'] = array('status'=>1,'msg'=>'');
		if(empty($userId) || empty($sessionId)){
			$result['status'] = 0;
			$result['msg'] = "请先登录系统!";
		}
		$where['user_id'] = $userId;
		$info = LoginSession::where($where)->find();
		if(count($info) == 0){
			$result['status'] = 0;
			$result['msg'] = "请先登录系统!";
		}elseif(time() > $info['overdue_time']){
			$result['status'] = 0;
			$result['msg'] = "请重新登录系统!";
		}elseif ($info['session_id'] != $sessionId){
			$result['status'] = 0;
			$result['msg'] = "该账号在其他地方登录";
		}
		return $result;
	}
}