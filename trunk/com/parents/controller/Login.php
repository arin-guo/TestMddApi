<?php
namespace app\parents\controller;
use app\parents\controller\Base;
use think\Db;
/**
 * 登录注册
 * @author chenlisong E-mail:chenlisong1021@163.com 
 * @version 创建时间：2017年8月6日 上午10:09:32 
 * 类说明
 */
class Login extends Base{
	
	public function index(){
		return $this->err("非法操作！");
	}
	
	/**
	 * 登录接口
	 */
	public function login(){
		$User = model('Parents');$Childs = model('Childs');
		$param = $this->param;
		if(empty($param['password']) || empty($param['tel'])){
			return $this->err("参数错误！");
		}
		$where['tel'] = $param['tel'];
		$where['flag'] = 1;
		$info = $User->where($where)->find();
		if(count($info) == 0){
			return $this->err("未找到该用户！");
		}
		if($info['password'] != md5($param['password'])){
			return $this->err("用户名或密码不正确！");
		}
		if($info['is_lock'] == 2){
			return $this->err("该账户已被锁定，请联系管理员！");
		}
		//判断注册流程是否完成
		if(empty($info['realname'])){
			return $this->err('该账户未绑定身份信息！',10001);
		}
		$childList = Db::view('ParentChild','child_id as id,relation')->view('Childs','classes_id,realname,status,id_card','ParentChild.child_id = Childs.id')
					->where('ParentChild.parent_id',$info['id'])->where('ParentChild.flag',1)->where('Childs.flag',1)->select();
		//获取班级信息
		$tId = Db::name('Type')->where('type_name',10002)->where('reserve',$info['school_id'])->where('flag',1)->value('id');
		foreach ($childList as $key=>$val){
			$classInfo = Db::name('Classes')->where('id',$val['classes_id'])->field('cats_code,name')->find();
			$classType = Db::name('Subtype')->where('subtype_code',$classInfo['cats_code'])->where('flag',1)->where('parent_id',$tId)->value('subtype_name');
			$childList[$key]['className'] = $classType.$classInfo['name'];
		}
		if(empty($childList)){
			return $this->err('该账户未添加孩子信息！',10002);
		}
		//session管理
		$LoginSession = model('LoginSession');
		$sessionData['user_id'] = $info["id"];
		$sessionId = strtolower(md5(md5(microtime())));
		$sessionData['session_id'] = $sessionId;
		$sessionData['overdue_time'] = time()+(60*24*3600);//60天后过期
		$sessionData['create_time'] = time();
		$sessionData['type'] = 1;
		$is_ex = $LoginSession->where('user_id',$info["id"])->where('type',1)->count();
		if($is_ex > 0){
			$LoginSession->isUpdate(true)->save($sessionData,['user_id'=>$info["id"],'type'=>1]);
		}else{
			$LoginSession->isUpdate(false)->save($sessionData);
		}
		//记录日志
		Db::name('LoginLog')->insert(['user_id'=>$info['id'],'login_time'=>time(),'login_ip'=>request()->ip(),'type'=>1]);
		//准备返回数据
		$backData['sessionId'] = $sessionId;
		$backData['userId'] =  $info['id'];
		$backData['tel'] =  $info['tel'];
		$backData['realname'] =  $info['realname'];
		$backData['schoolId'] =  $info['school_id'];
		$backData['type'] =  $info['type'];
		$backData['photo'] =  $info['photo'];
		$backData['uniqueCode'] =  $info['unique_code'];
		$backData['is_vip'] = $info['is_vip'];
		$backData['address'] = $info['address'];
		$backData['sex'] = $info['sex'];
		$backData['childList'] = $childList;
		$backData['schoolName'] = Db::name('Schools')->where('id',$info['school_id'])->value('name');
		$backData['schoolIsDevice'] = Db::name('Schools')->where('id',$info['school_id'])->value('is_device');
		$backData['leaderTel '] = Db::name('Headmasters')->where('school_id',$info['school_id'])->where('flag',1)->value('tel');
		$User->where($where)->setField('jpush_id',$param['jpushId']);//设置极光推送ID
		return $this->suc($backData,'',config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 退出
	 */
	public function logout(){
		$LoginSession = model('LoginSession');$User = model('Parents');
		$param = $this->param;
		if(!empty($param['userId']) && !empty($param['sessionId'])){
			$where['session_id'] = $param['sessionId'];
			$where['user_id'] = $param['userId'];
			$where['type'] = 1;
			$LoginSession->where($where)->delete();
			//清空极光推送ID
			$User->where('id',$param['userId'])->setField('jpush_id','');
		}
		return $this->suc();
	}
	
	/**
	 * 注册step1
	 */
	public function registerStep1(){
		$User = model("Parents");$School = model('Schools');
		$param = $this->param;
		if(!preg_match("/1[3456789]{1}\d{9}$/",$param['tel'])){
			return $this->err("错误的手机格式！");
		}
		$send = cache('app_send_msg_'.$param['tel']);
		if(empty($param['sendCode'])){
			return $this->err('验证码为空！');
		}
		if($param['tel'] != $send['send_tel']){
			return $this->err("验证码失效！");
		}
		if($param['sendCode'] != $send['send_code']){
			return $this->err("验证码错误！");
		}
        //验证学校
        if(empty($param['uniqueCode'])){
            return $this->err('幼儿园邀请码为空！');
        }
        $schoolinfo = $School->where('flag',1)->where('unique_code',$param['uniqueCode'])->field('id,is_open')->find();
        if(empty($schoolinfo)){
            return $this->err('邀请码有误，未找到相应园所！');
        }
        if($schoolinfo['is_open'] != 1){
            return $this->err('园所暂未开放注册，请联系园所管理员！');
        }
		//验证手机号唯一
		$is_ex = $User->where('tel',$param['tel'])->where('flag',1)->count();
		if($is_ex > 0){
			return $this->err("该手机号码已注册，可直接登录！");
		}
		$data['school_id'] = $schoolinfo['id'];
		$data['username'] = "家长".substr($param['tel'], -4);
		$data['password'] = md5($param['password']);
		$data['status'] = 1;
		$data['tel'] = $param['tel'];
		$data['type'] = 1;//主家长
		$data['create_time'] = time();//获取当前时间戳
		$data['jpush_id'] = $param['jpushId'];
		$data['unique_code'] = buildUniqueCode($schoolinfo['id']);
		$data['is_main_pick'] = 1;
		$data['parent_id'] = 0;
		$result  = $User->isUpdate(false)->save($data);
		// 写入数据
		if($result){
			//清空手机验证码的缓存时间和验证码
			cache("app_send_msg_".$param['tel'],null);
			$backData['tel'] =  $param['tel'];
			$backData['schoolId'] =  $schoolinfo['id'];
			$backData['parentId'] =  $User->id;
			return $this->suc($backData,'');
		} else {
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 注册step2
	 */
	public function registerStep2(){
		$User = model("Parents");
		$param = $this->param;
		if(empty($param['tel']) || empty($param['realname'])){
			return $this->err('参数错误！');
		}
		$info = $User->where('tel',$param['tel'])->where('flag',1)->find();
		if(empty($info)){
			return $this->err('未找到该用户！');
		}
		//检查身份证唯一
		if(!empty($param['idCard'])){
			$count = $User->where('id_card',$param['idCard'])->where('flag',1)->count();
			if($count != 0){
				return $this->err('该身份证已被绑定！');
			}
		}
		$data['realname'] = $param['realname'];
		$data['id_card'] = empty($param['idCard'])?"":$param['idCard'];
		$data['address'] = empty($param['address'])?"":$param['address'];
		$result = $User->isUpdate(true)->save($data,['tel'=>$param['tel'],'flag'=>1]);
		if($result !== false){
			$backData['tel'] = $info['tel'];
			$backData['parentId'] = empty($param['parentId'])?"":$param['parentId'];
			$backData['schoolId'] = empty($param['schoolId'])?"":$param['schoolId'];
			return $this->suc($backData);
		}else{
			return $this->err('系统繁忙！');
		}
	}

    /**
     * 家长添加人脸信息
     */
    public function addParentFaceInfo(){
        $User = model("Parents");
        $param = $this->param;
        if(empty($param['tel']) || empty($param['schoolId']) || empty($param['parentId']) || empty($param['faceInfo'])){
            return $this->err('参数错误！');
        }
        $User->where('id',$param['parentId'])->update(['update_time'=>time()]);
//        $path = config('app_upload_path')."/uploads/eyesmart/faceinfo/".$param['schoolId']."/parents/";
//        if(!file_exists($path)){
//            //检查是否有该文件夹，如果没有就创建，并给予最高权限
//            mkdir($path, 0777, true);
//        }
//        $new_file = $path.$param['parentId'].".face";
//        if(file_put_contents($new_file, $param['faceInfo'])){
//            $backData['tel'] = $param['tel'];
//            return $this->suc($backData);
//        }else{
//            return $this->err('注册人脸失败',-10005);
//        }


        $path = config('app_upload_path')."/uploads/eyesmart/faceinfo/".$param['schoolId']."/parents/";
        if(!file_exists($path)){
            //检查是否有该文件夹，如果没有就创建，并给予最高权限
            mkdir($path, 0777, true);
        }
        $new_file = $path.$param['parentId'].".face";
        $size = filesize($new_file);
        //$data['size'] = $size;
        //return $this->suc($data);
        if($size != false && $size > 0){
            $faceinfo = file_get_contents($new_file);
            $facearray = explode(',',$faceinfo);
            if(count($facearray) < 5){
                if (file_put_contents($new_file, ",".$param['faceInfo'],FILE_APPEND)){
                    $backData['tel'] = $param['tel'];
                    return $this->suc($backData);
                }else{
                    return $this->err('系统繁忙！');
                }
            }else{
                array_shift($facearray);
                array_push($facearray,$param['faceInfo']);
                $faceinfo = implode(',',$facearray);
                if (file_put_contents($new_file, $faceinfo)){
                    $backData['tel'] = $param['tel'];
                    return $this->suc($backData);
                }else{
                    return $this->err('系统繁忙！');
                }
            }
        }elseif($size == false){
            if (file_put_contents($new_file, $param['faceInfo'])){
                $backData['tel'] = $param['tel'];
                return $this->suc($backData);
            }else{
                return $this->err('系统繁忙！');
            }
        }

    }

	/**
	 * 获取年级与班级
	 */
	public function getCatsAndClass(){
		$User = model('Parents');$Type = model('Type');$Subtype = model('Subtype');$Class = model('Classes');
		$param = $this->param;
		$info = $User->where('tel',$param['tel'])->where('flag',1)->find();
		if(empty($info)){
			return $this->err('未找到该用户！');
		}
		$tId = $Type->where('type_name',10002)->where('reserve',$info['school_id'])->where('flag',1)->value('id');
		$data['catsList'] = $Subtype->where('parent_id',$tId)->where('flag',1)->field('subtype_code as catsCode,subtype_name as catsName')->select();
		foreach ($data['catsList'] as $key=>$val){
			$data['catsList'][$key]['classList'] = $Class->where('is_open',1)->where('school_id',$info['school_id'])->where('cats_code',$val['catsCode'])->where('flag',1)->field('id,name')->order('id asc')->select();
		}
		return $this->suc($data);
	}
	
	/**
	 * 注册step3
	 */
	public function registerStep3(){
		$User = model("Parents");$Childs = model('Childs');
		$param = $this->param;
		if(empty($param['tel']) || empty($param['realname']) || empty($param['relation']) || empty($param['birthday']) || empty($param['age']) || !in_array($param['sex'], array(1,2))){
			return $this->err('参数错误！');
		}
		$info = $User->where('tel',$param['tel'])->where('flag',1)->find();
		if(empty($info)){
			return $this->err('未找到该用户！');
		}
		if($info['type'] != 1){
			return $this->err('您的账号无权限执行此操作！');
		}
		//检查身份证唯一
		if(!empty($param['idCard'])){
			$count = $Childs->where('id_card',$param['idCard'])->where('flag',1)->count();
			if($count != 0){
				return $this->err('该身份证已被绑定！');
			}
		}
		//判断孩子最多添加4个
		$count = Db::name('ParentChild')->where('parent_id',$info['id'])->where('flag',1)->count();
		if($count >= 4){
			return $this->err('亲，再多服务不过来了！');
		}
		Db::startTrans();
		try{
			//判断小孩是否需要立即分班
			if(intval($param['classId']) != 0 ){
				$count = Db::name('Classes')->where('flag',1)->where('school_id',$info['school_id'])->where('id',$param['classId'])->count();
				if($count == 0){
					return $this->err('未找到该班级，请重试！');
				}
			}
			$childData['classes_id'] = intval($param['classId']);
			//每个家长下所有小孩的识别码都相同,如已经有小孩，就不再生成新的识别码
			$childData['unique_code'] = $info['unique_code'];
			$childData['realname'] = $param['realname'];
			$childData['id_card'] = empty($param['idCard'])?"":$param['idCard'];
			$childData['school_id'] = $info['school_id'];
			$childData['birthday'] = $param['birthday'];
			$childData['age'] = $param['age'];
			$childData['sex'] = $param['sex'];
			$child = $Childs->isUpdate(false)->save($childData);
			//小孩与家长绑定
			$relationData[0]['relation'] = $param['relation'];
			$relationData[0]['parent_id'] = $info['id'];
			$relationData[0]['child_id'] = $Childs->id;
			$relationData[0]['create_time'] = time();
			//获取所有从属家长
			$ids = $User->where('parent_id',$info['id'])->where('flag',1)->field('id')->select();
			if(!empty($ids)){
				$i = 1;
				foreach ($ids as $key=>$val){
					$relationData[$i]['relation'] = Db::name('ParentChild')->where('parent_id',$val['id'])->where('flag',1)->value('relation');
					$relationData[$i]['parent_id'] = $val['id'];
					$relationData[$i]['child_id'] = $Childs->id;
					$relationData[$i]['create_time'] = time();
					$i++;
				}
			}
			Db::name('ParentChild')->insertAll($relationData);
			//记录日志
			Db::name('LoginLog')->insert(['user_id'=>$info['id'],'login_time'=>time(),'login_ip'=>request()->ip(),'type'=>1]);
			//家长的状态改为正常
			if($info['status'] != 1){
				$User->where('id',$info['id'])->setField('status',1);
			}
			//准备返回数据
			$backData['childId'] = $Childs->id;
			$backData['tel'] = $info['tel'];
			// 提交事务
			Db::commit();
			return $this->suc($backData);
		}catch (\Exception $e) {
		    // 回滚事务
		    Db::rollback();
			return $this->err("系统繁忙！");
		}
	}
	
	/**
	 * 找回密码
	 */
	public function findPwd(){
		$User = model('Parents');
		$param = $this->param;
		if(!preg_match("/1[34578]{1}\d{9}$/",$param['tel'])){
			return $this->err("错误的手机格式！");
		}
		if(!preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{6,12}$/', $param['password'])){
			return $this->err("请输入6-12位数字和英文组合的密码！");
		}
		$send = cache('app_send_msg_'.$param['tel']);
		if(empty($param['sendCode'])){
			return $this->err('验证码为空！');
		}
		if($param['tel'] != $send['send_tel']){
			return $this->err("验证码失效！");
		}
		if($param['sendCode'] != $send['send_code']){
			return $this->err("验证码错误！");
		}
		$where['flag'] = 1;
		$where['tel'] = $param['tel'];
		$info = $User->where($where)->find();
		if(empty($info)){
			return $this->err("未找到该用户！");
		}
		$data['password'] = md5($param['password']);
		$data['update_time'] = time();
		$result = $User->save($data,['tel'=>$param['tel'],'flag'=>1]);
		if($result){
			cache("app_send_msg_".$param['tel'],null);
			return $this->suc();
		}else {
			return $this->err("系统错误！");
		}
	}
	
	/**
	 * 验证密码
	 */
	public function checkPassword(){
		$User = model('Parents');
		$param = $this->param;
		if(empty($param['password']) || empty($param['tel'])){
			return $this->err("参数错误！");
		}
		$where['tel'] = $param['tel'];
		$where['flag'] = 1;
		$where['password'] = md5($param['password']);
		$info = $User->where($where)->find();
		if(count($info) != 0){
			return $this->suc();
		}else{
			return $this->err("验证失败！");
		}
	}
}