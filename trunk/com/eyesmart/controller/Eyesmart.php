<?php
namespace app\eyesmart\controller;
use app\eyesmart\controller\Base;
use think\Db;
use think\Log;
/**
 * 释码云接口
 * @author chenlisong E-mail:chenlisong1021@163.com 
 * @version 创建时间：2017年9月2日 下午3:02:23 
 * 类说明
 */
class Eyesmart extends Base{
	
	/**
	 * 首页
	 */
	public function index(){
		return $this->err('hi..');
	}
	
	/**
	 * 客户端版本更新
	 * Enter description here ...
	 */
	public function updateClient(){
		$DeviceVersion = model('DeviceVersion');
		$info = $DeviceVersion->where('flag',1)->where('status',1)->find();
		$param = $this->param;
		if(empty($param['ver'])){
			return $this->err('参数错误！');
		}
		$version = $param['ver'];
		$newVersion = $info['version'];
		$result = version_compare($newVersion, $version,'>');
		$reback['needUpdate'] = false;
		if($result){//必须更新
			$reback['needUpdate'] = true;
			$reback['updateTitle'] = $info['title'];
			$reback['updateDesc'] = $info['desc'];
			$reback['versionName'] = $newVersion;
			$reback['updateUrl'] = $info['url'];
		}
		return $this->suc($reback,'',config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 根据学校名称获取学校Id
	 */
	public function getSchoolIdByName(){
		$School = model('Schools');
		$deviceAndroid = model('DeviceAndroid');
		$deviceSchool = model('DeviceSchool');
		$param = $this->param;
		if(!empty($param['schoolName']) && empty($param['uniqueCode'])){
            $data['schoolId'] = $School->where('name',$param['schoolName'])->where('flag',1)->value('id');
            if(empty($data['schoolId'])){
                return $this->err('未找到该学校！');
            }
            //获取学校上学放学的时间
            $school_time = $School->where('id',$data['schoolId'])->field('onschool_time,offschool_time,device_password,name'/*ji change*/)->find();
            $data['schoolName'] = $school_time['name'];
            $data['onschoolTime'] = $school_time['onschool_time'];
            $data['offschoolTime'] = $school_time['offschool_time'];
            $data['devicePassword'] = $school_time['device_password'];/*ji add*/
            $data['timestamp'] = time();
            //return $this->suc($data);
        }elseif (!empty($param['uniqueCode']) && empty($param['schoolName'])){
            $school_time = $School->where('unique_code',$param['uniqueCode'])->field('id,onschool_time,offschool_time,device_password,name'/*ji change*/)->find();
            if(empty($school_time['id'])){
                return $this->err('未找到该学校！');
            }
            $data['schoolId'] = $school_time['id'];
            $data['schoolName'] = $school_time['name'];
            $data['onschoolTime'] = $school_time['onschool_time'];
            $data['offschoolTime'] = $school_time['offschool_time'];
            $data['devicePassword'] = $school_time['device_password'];/*ji add*/
            $data['timestamp'] = time();
            //return $this->suc($data);
        }else{
            return $this->err('未正确传入学校名称或学校邀请码！');
        }
        if(!empty($param['versionCode'])){
            $schoolwhere['school_id'] = $data['schoolId'];
            $schoolwhere['status'] = 1;
            $daid = $deviceSchool->where($schoolwhere)->field('version_id')->order('create_time desc')->find();
            $dawhere['status'] = 1;
            $dawhere['flag']  = 1;
            if(!empty($daid)){
                $dawhere['id'] = $daid['version_id'];
                //$dawhere['range'] = 2;
                $newversion = $deviceAndroid->where($dawhere)->field('version,url')->find();
                $versionres = version_compare($newversion['version'], $param['versionCode'],'>');
                if ($versionres){
                    $data['needUpdate'] = 1;
                    $data['versionCode'] = $newversion['version'];
                    $urlhead = rtrim(config('view_replace_str.__IMGROOT__'),'/');
                    $data['url'] = $urlhead.$newversion['url'];
                }else{
                    $data['needUpdate'] = 0;
                }
            }else{
                //$dawhere['range'] = 1;
                $newversion = $deviceAndroid->where($dawhere)->field('version,url')->order('create_time desc')->find();
                $versionres = version_compare($newversion['version'], $param['versionCode'],'>');
                if ($versionres){
                    $data['needUpdate'] = 1;
                    $data['versionCode'] = $newversion['version'];
                    $urlhead = rtrim(config('view_replace_str.__IMGROOT__'),'/');
                    $data['url'] = $urlhead.$newversion['url'];
                }else{
                    $data['needUpdate'] = 0;
                }
            }
        }else{
            $data['needUpdate'] = 0;
        }
        return $this->suc($data);
	}
	
	/**
	 * 上传人脸信息
	 */
	public function addFaceInfo(){
		$School = model('Schools');
		$param = $this->param;
		if(empty($param['faceInfo']) || !in_array($param['type'], array(1,2)) || empty($param['schoolId']) || empty($param['userId'])){
			return $this->err('参数错误！');
		}
		//查看学校ID是否正确
		$count = $School->where('flag',1)->where('id',$param['schoolId'])->count();
		if($count == 0){
			return $this->err('学校不存在！');
		}
		if($param['type'] == 1){//家长录入，需要确定是哪个小孩
			$Parents = model('Parents');
			//判断家长录入的学校与设备学校是否一致
			$where = ['id'=>$param['userId'],'flag'=>1,'status'=>1];
			$info = $Parents->get($where);
			if($info['school_id'] != $param['schoolId']){
				return $this->err('录入学校与设备所在学校不同，无法操作！');
			}
			//更新时间
			$Parents->where($where)->update(['update_time'=>time()]);
		}elseif($param['type'] == 2){
			$Teachers = model('Teachers');
			$where = ['school_id'=>$param['schoolId'],'id'=>$param['userId'],'flag'=>1];
			$count = $Teachers->where($where)->count();
			if($count == 0){
				return $this->err('未找到该用户！');
			}
			//更新时间
			$Teachers->where($where)->update(['update_time'=>time()]);
		}
		$path = $param['type'] == 1 ? "parents":"teachers";
		$path = config('app_upload_path')."/uploads/eyesmart/faceinfo/".$param['schoolId']."/".$path."/";
		if(!file_exists($path)){
			//检查是否有该文件夹，如果没有就创建，并给予最高权限
			mkdir($path, 0777, true);
		}
		$new_file = $path.$param['userId'].".face";//userId_时间戳
		if(file_put_contents($new_file, $param['faceInfo'])){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
    /**
     * 安卓上传人脸信息step1
     */
    public function androidGetPersonInfo(){
        $param = $this->param;
        if(empty($param['schoolId']) || empty($param['tel'])){
            return $this->err('参数缺失！');
        }
        $parent = model('parents');
        $teacher = model('teachers');
        //$child = model('childs');
        $where['flag'] = 1;
        $where['school_id'] = $param['schoolId'];
        $where['tel'] = $param['tel'];
        $pinfo = $parent->where($where)->field('id,status,realname')->find();
        $tinfo = $teacher->where($where)->field('id,is_job,realname')->find();
        if($pinfo['status'] == 2 || $pinfo['status'] == -1){
            return $this->err('该家长的孩子已经毕业或者转学',-10006);
        }
        if($tinfo['is_job'] == 2 ){
            return $this->err('该教师已经离职',-10006);
        }
        if(empty($pinfo) && empty($tinfo)){
            return $this->err('没有在幼儿园查到此人信息,请联系工作人员',-10006);
        }elseif (empty($pinfo) && !empty($tinfo)){
            $data['name'] = $tinfo['realname'];
        }elseif (!empty($pinfo) && empty($tinfo)){
            $data['name'] = $pinfo['realname'];
        }elseif (!empty($pinfo) && !empty($tinfo)){
            $data['name'] = $pinfo['realname'];
        }
        $childIds = Db::name('ParentChild')->where('flag',1)->where('parent_id',$pinfo['id'])->value("GROUP_CONCAT(child_id)");

        $data['parentId'] = empty($pinfo)?0:$pinfo['id'];
        $data['teacherId'] = empty($tinfo)?0:$tinfo['id'];
        $childinfo = Db::name('childs')->field('id as childId,realname')->where('flag',1)->where('status',1)->where('school_id',$param['schoolId'])->where('id','in',$childIds)->select();
        $data['childList'] = empty($childinfo)?[]:$childinfo;
        return $this->suc($data);
    }
    /**
     * 安卓上传人脸信息step2
     */
    public function androidAddPersonFace(){
        $param = $this->param;
        if(empty($param['type']) || empty($param['schoolId']) || empty($param['faceInfo']) ){
            return $this->err('参数缺失！');
        }
        $where['flag'] = 1;
        $where['school_id'] = $param['schoolId'];
        if ($param['type'] == 1){
            $parent = model('parents');
            $teacher = model('teachers');
            //又是老师又是家长
            if (!empty($param['parentId']) && !empty($param['teacherId'])){
                //获取详细信息info
                $pinfo = $parent->where($where)->where('id',$param['parentId'])->field('id,status,unique_code,realname,tel,id_card,parent_id,is_main_pick')->find();
                $tinfo = $teacher->where($where)->where('id',$param['teacherId'])->field('id,is_job,realname,id_card,tel')->find();
                //更新时间
                $parent->where('id',$pinfo['id'])->update(['update_time'=>time()]);
                $teacher->where('id',$tinfo['id'])->update(['update_time'=>time()]);
                //将人脸信息保存到文件夹中
                $type = array($pinfo['id']=>'parents',$tinfo['id']=>'teachers');
                foreach ($type as $key=>$value){
                    $path = config('app_upload_path')."/uploads/eyesmart/faceinfo/".$param['schoolId']."/".$value."/";
                    if(!file_exists($path)){
                        //检查是否有该文件夹，如果没有就创建，并给予最高权限
                        mkdir($path, 0777, true);
                    }
                    $new_file = $path.$key.".face";//userId_时间戳
                    if(file_put_contents($new_file, $param['faceInfo'])){
                        //根据老师还是家长返回唯一码
                        if($value == 'parents'){
                            $data['parent']['parentId'] = $pinfo['id'];
                            $data['parent']['realname'] = $pinfo['realname'];
                            $data['parent']['IdCard'] = $pinfo['id_card'];
                            $data['parent']['uniqueCode'] = $pinfo['unique_code'];
                            $data['parent']['tel'] = $pinfo['tel'];
                            //默认接送人返回0，适应旧接口
                            $data['parent']['isMain'] = $pinfo['is_main_pick'] == 1 ? 0 : 2;
                            $childIds = Db::name('ParentChild')->where('flag',1)->where('parent_id',$pinfo['id'])->value("GROUP_CONCAT(child_id)");
                            $data['childList'] = Db::name('childs')->field('id as childId,unique_code as uniqueCode,realname')->where('flag',1)->where('status',1)->where('school_id',$param['schoolId'])->where('id','in',$childIds)->select();
                        }else{
                            $data['teacher']['teacherId'] = $tinfo['id'];
                            $data['teacher']['realname'] = $tinfo['realname'];
                            $data['teacher']['IdCard'] = $tinfo['id_card'];
                            $data['teacher']['tel'] = $tinfo['tel'];
                        }
                    }else{
                        return $this->err('系统繁忙！');
                    }
                }
                //返回数据
                return $this->suc($data);
                //只是家长不是老师
            }elseif(!empty($param['parentId']) && empty($param['teacherId'])){
                //获取详细信息info
                $pinfo = $parent->where($where)->where('id',$param['parentId'])->field('id,status,unique_code,realname,tel,id_card,parent_id,is_main_pick')->find();
                $parent->where('id',$pinfo['id'])->update(['update_time'=>time()]);
                $path = config('app_upload_path')."/uploads/eyesmart/faceinfo/".$param['schoolId']."/parents/";
                if(!file_exists($path)){
                    //检查是否有该文件夹，如果没有就创建，并给予最高权限
                    mkdir($path, 0777, true);
                }
                $new_file = $path.$pinfo['id'].".face";//userId_时间戳
                if(file_put_contents($new_file, $param['faceInfo'])){
                    $data['parent']['parentId'] = $pinfo['id'];
                    $data['parent']['realname'] = $pinfo['realname'];
                    $data['parent']['IdCard'] = $pinfo['id_card'];
                    $data['parent']['uniqueCode'] = $pinfo['unique_code'];
                    $data['parent']['tel'] = $pinfo['tel'];
                    //默认接送人返回0，适应旧接口
                    $data['parent']['isMain'] = $pinfo['is_main_pick'] == 1 ? 0 : 2;
                    $childIds = Db::name('ParentChild')->where('flag',1)->where('parent_id',$pinfo['id'])->value("GROUP_CONCAT(child_id)");
                    $data['childList'] = Db::name('childs')->field('id as childId,unique_code as uniqueCode,realname')->where('flag',1)->where('status',1)->where('school_id',$param['schoolId'])->where('id','in',$childIds)->select();
                    return $this->suc($data);
                }else{
                    return $this->err('系统繁忙！');
                }
                //只是老师不是家长
            }elseif (empty($param['parentId']) && !empty($param['teacherId'])){
                $tinfo = $teacher->where($where)->where('id',$param['teacherId'])->field('id,is_job,realname,id_card,tel')->find();
                $teacher->where('id',$tinfo['id'])->update(['update_time'=>time()]);
                $path = config('app_upload_path')."/uploads/eyesmart/faceinfo/".$param['schoolId']."/teachers/";
                if(!file_exists($path)){
                    //检查是否有该文件夹，如果没有就创建，并给予最高权限
                    mkdir($path, 0777, true);
                }
                $new_file = $path.$tinfo['id'].".face";//userId_时间戳
                if(file_put_contents($new_file, $param['faceInfo'])){
                    $data['teacher']['teacherId'] = $tinfo['id'];
                    $data['teacher']['realname'] = $tinfo['realname'];
                    $data['teacher']['IdCard'] = $tinfo['id_card'];
                    $data['teacher']['tel'] = $tinfo['tel'];
                    return $this->suc($data);
                }else{
                    return $this->err('系统繁忙！');
                }
            }
        //孩子录入人脸
        }elseif ($param['type'] == 2){
            $child = model('childs');
            $parent = model('parents');
            $cinfo = $child->where($where)->where('id',$param['childId'])->field('id,unique_code,realname')->find();
            $child->where('id',$cinfo['id'])->update(['update_time'=>time()]);
            $parent->where('id',$param['parentId'])->update(['update_time'=>time()]);
            $path = config('app_upload_path')."/uploads/eyesmart/faceinfo/".$param['schoolId']."/children/";
            if(!file_exists($path)){
                //检查是否有该文件夹，如果没有就创建，并给予最高权限
                mkdir($path, 0777, true);
            }
            $new_file = $path.$cinfo['id'].".face";//userId_时间戳
            if(file_put_contents($new_file, $param['faceInfo'])){
                $data['child']['childId'] = $cinfo['id'];
                $data['child']['realname'] = $cinfo['realname'];
                $data['child']['uniqueCode'] = $cinfo['unique_code'];
                return $this->suc($data);
            }else{
                return $this->err('系统繁忙！');
            }
        }
    }
    /**
     * 安卓上传人脸信息
     */
    public function androidAddFaceInfo(){
        $parent = model('parents');
        $teacher = model('teachers');
        $param = $this->param;
        if(empty($param['faceInfo']) || empty($param['schoolId']) || empty($param['tel'])){
            return $this->err('参数错误！');
        }
        //查看手机号在属于家长还是老师
        $pinfo = Db::name('parents')->where('flag',1)->where('school_id',$param['schoolId'])->where('tel',$param['tel'])->field('id,status,unique_code,realname,tel,id_card,parent_id,is_main_pick')->find();
        $tinfo = Db::name('teachers')->where('flag',1)->where('school_id',$param['schoolId'])->where('tel',$param['tel'])->field('id,is_job,realname,id_card,tel')->find();
        if($pinfo['status'] == 2 || $pinfo['status'] == -1){
            return $this->err('该家长的孩子已经毕业或者转学',-10006);
        }
        if($tinfo['is_job'] == 2 ){
            return $this->err('该教师已经离职',-10006);
        }
        if(empty($pinfo) && empty($tinfo)){
            return $this->err('没有在幼儿园查到此人信息,请联系工作人员',-10006);
        }elseif (!empty($pinfo) && !empty($tinfo)){
            //更新时间
            $parent->where('id',$pinfo['id'])->update(['update_time'=>time()]);
            $teacher->where('id',$tinfo['id'])->update(['update_time'=>time()]);
            //将人脸信息保存到文件夹中
            $type = array($pinfo['id']=>'parents',$tinfo['id']=>'teachers');
            foreach ($type as $key=>$value){
                $path = config('app_upload_path')."/uploads/eyesmart/faceinfo/".$param['schoolId']."/".$value."/";
                if(!file_exists($path)){
                    //检查是否有该文件夹，如果没有就创建，并给予最高权限
                    mkdir($path, 0777, true);
                }
                $new_file = $path.$key.".face";//userId_时间戳
                if(file_put_contents($new_file, $param['faceInfo'])){
                    //根据老师还是家长返回唯一码
                    if($value == 'parents'){
                        $data['parent']['parentId'] = $pinfo['id'];
                        $data['parent']['realname'] = $pinfo['realname'];
                        $data['parent']['IdCard'] = $pinfo['id_card'];
                        $data['parent']['uniqueCode'] = $pinfo['unique_code'];
                        $data['parent']['tel'] = $pinfo['tel'];
                        //默认接送人返回0，适应旧接口
                        $data['parent']['isMain'] = $pinfo['is_main_pick'] == 1 ? 0 : 2;
                        $childIds = Db::name('ParentChild')->where('flag',1)->where('parent_id',$pinfo['id'])->value("GROUP_CONCAT(child_id)");
                        $data['childList'] = Db::name('childs')->field('id as childId,unique_code as uniqueCode,realname')->where('flag',1)->where('status',1)->where('school_id',$param['schoolId'])->where('id','in',$childIds)->select();
                    }else{
                        $data['teacher']['teacherId'] = $tinfo['id'];
                        $data['teacher']['realname'] = $tinfo['realname'];
                        $data['teacher']['IdCard'] = $tinfo['id_card'];
                        $data['teacher']['tel'] = $tinfo['tel'];
                    }
                }else{
                    return $this->err('系统繁忙！');
                }
            }
            //返回数据
            return $this->suc($data);
        }elseif (!empty($pinfo) && empty($tinfo)){
            $parent->where('id',$pinfo['id'])->update(['update_time'=>time()]);
            $path = config('app_upload_path')."/uploads/eyesmart/faceinfo/".$param['schoolId']."/parents/";
            if(!file_exists($path)){
                //检查是否有该文件夹，如果没有就创建，并给予最高权限
                mkdir($path, 0777, true);
            }
            $new_file = $path.$pinfo['id'].".face";//userId_时间戳
            if(file_put_contents($new_file, $param['faceInfo'])){
                $data['parent']['parentId'] = $pinfo['id'];
                $data['parent']['realname'] = $pinfo['realname'];
                $data['parent']['IdCard'] = $pinfo['id_card'];
                $data['parent']['uniqueCode'] = $pinfo['unique_code'];
                $data['parent']['tel'] = $pinfo['tel'];
                //默认接送人返回0，适应旧接口
                $data['parent']['isMain'] = $pinfo['is_main_pick'] == 1 ? 0 : 2;
                $childIds = Db::name('ParentChild')->where('flag',1)->where('parent_id',$pinfo['id'])->value("GROUP_CONCAT(child_id)");
                $data['childList'] = Db::name('childs')->field('id as childId,unique_code as uniqueCode,realname')->where('flag',1)->where('status',1)->where('school_id',$param['schoolId'])->where('id','in',$childIds)->select();
                return $this->suc($data);
            }else{
                return $this->err('系统繁忙！');
            }
        }elseif (empty($pinfo) && !empty($tinfo)){
            $teacher->where('id',$tinfo['id'])->update(['update_time'=>time()]);
            $path = config('app_upload_path')."/uploads/eyesmart/faceinfo/".$param['schoolId']."/teachers/";
            if(!file_exists($path)){
                //检查是否有该文件夹，如果没有就创建，并给予最高权限
                mkdir($path, 0777, true);
            }
            $new_file = $path.$tinfo['id'].".face";//userId_时间戳
            if(file_put_contents($new_file, $param['faceInfo'])){
                $data['teacher']['teacherId'] = $tinfo['id'];
                $data['teacher']['realname'] = $tinfo['realname'];
                $data['teacher']['IdCard'] = $tinfo['id_card'];
                $data['teacher']['tel'] = $tinfo['tel'];
                return $this->suc($data);
            }else{
                return $this->err('系统繁忙！');
            }
        }
    }
	/**
	 * 修改人脸信息
	 */
	public function editFaceInfo(){
		$param = $this->param;
		if(empty($param['faceInfo']) || !in_array($param['type'], array(1,2)) || empty($param['schoolId']) || empty($param['userId'])){
			return $this->err('参数错误！');
		}
// 		if($param['type'] == 1){
// 			$Parents = model('Parents');
// 			$where = ['id'=>$param['userId'],'flag'=>1,'status'=>1];
// 			$Parents->where($where)->update(['update_time'=>time()]);
// 		}elseif($param['type'] == 2){
// 			$Teachers = model('Teachers');
// 			$where = ['school_id'=>$param['schoolId'],'id'=>$param['userId'],'flag'=>1];
// 			$Teachers->where($where)->update(['update_time'=>time()]);
// 		}
		$path = $param['type'] == 1 ? "parents":"teachers";
		$path = config('app_upload_path')."/uploads/eyesmart/faceinfo/".$param['schoolId']."/".$path."/";
		if(!file_exists($path)){
			//检查是否有该文件夹，如果没有就创建，并给予最高权限
			mkdir($path, 0777, true);
		}
		$new_file = $path.$param['userId'].".face";//userId_时间戳
		if (file_put_contents($new_file, $param['faceInfo'])){
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
    /**
     * 安卓修改人脸信息
     */
    public function androidEditFaceInfo(){
        $param = $this->param;
        if(empty($param['faceInfo']) || !in_array($param['type'], array(1,2,3)) || empty($param['schoolId']) || empty($param['userId'])){
            return $this->err('参数错误！');
        }
        switch ($param['type']){
            case 1:
                $path = 'parents';
                break;
            case 2:
                $path = 'teachers';
                break;
            case 3:
                $path = 'children';
                break;
        }
        //$path = $param['type'] == 1 ? "parents":"teachers";
        $path = config('app_upload_path')."/uploads/eyesmart/faceinfo/".$param['schoolId']."/".$path."/";
        if(!file_exists($path)){
            //检查是否有该文件夹，如果没有就创建，并给予最高权限
            mkdir($path, 0777, true);
        }
        $new_file = $path.$param['userId'].".face";//userId_时间戳
        if (file_put_contents($new_file, $param['faceInfo'])){
            return $this->suc();
        }else{
            return $this->err('系统繁忙！');
        }
    }
	/**
	 * 获取教职工集合
	 * 分页
	 */
	public function getTeacherList(){
		$Teacher = model('Teachers');
		$param = $this->param;
		if(empty($param['schoolId']) || is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		$where['flag'] = 1;
		$where['school_id'] = $param['schoolId'];
		$where['is_job'] = 1;
		$where['update_time'] = array('egt',intval(input('lastTime')));
		$count = $Teacher->where($where)->count();
		$field = 'id as teacherId,realname,id_card as IdCard,tel';
		if($count < 10){
			$nextStartId = -1;
			$data = $Teacher->where($where)->field($field)->order('create_time desc,id desc')->select();
		}else{
			$nextStartId = $param['nextStartId'];
			$data = $Teacher->where($where)->field($field)->limit($nextStartId,10)->order('create_time desc,id desc')->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		//获取人脸信息
		foreach ($data as $key=>$val){
			$path = config('app_upload_path')."/uploads/eyesmart/faceinfo/".$param['schoolId']."/teachers/".$val['teacherId'].".face";
			$faceInfo = file_get_contents($path);
			$data[$key]['faceInfo'] = $faceInfo?$faceInfo:"";
		}
		return $this->suc($data,$nextStartId);
	}
	
	/**
	 * 获取家长信息与孩子信息及其关系
	 * 只有主家属才能接送孩子，并且有人脸信息
	 */
	public function getParentList(){
		$Parent = model('Parents');$Child = model('Childs');
		$param = $this->param;
		if(empty($param['schoolId']) || is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		$where['flag'] = 1;
		$where['school_id'] = $param['schoolId'];
		$where['status'] = 1;//正常的家长，离线或毕业不计入
		$where['update_time'] = array('egt',intval(input('lastTime')));
		$count = $Parent->where($where)->count();
		$field = 'id as parentId,realname,id_card as IdCard,tel,is_main_pick as isMain';
		if($count < 10){
			$nextStartId = -1;
			$data['parentList'] = $Parent->where($where)->field($field)->order('create_time desc')->select();
		}else{
			$nextStartId = $param['nextStartId'];
			$data['parentList'] = $Parent->where($where)->field($field)->limit($nextStartId,10)->order('create_time desc')->select();
			$nextStartId = $nextStartId + 10;
			if($nextStartId >= $count || count($data) == 0){
				$nextStartId = -1;
			}
		}
		//默认接送人返回0，适应旧接口
		foreach ($data['parentList'] as $item=>$value){
			$data['parentList'][$item]['isMain'] = $value['isMain'] == 1 ? 0 : 2;
		}
		$ids = [];
		//获取人脸信息
		foreach ($data['parentList'] as $key=>$val){
			$path = config('app_upload_path')."/uploads/eyesmart/faceinfo/".$param['schoolId']."/parents/".$val['parentId'].".face";
			$faceInfo = file_get_contents($path);
			$data['parentList'][$key]['faceInfo'] = $faceInfo?$faceInfo:"";
			array_push($ids,$val['parentId']);
		}
		//家长与孩子的绑定信息
		$data['unionList'] = Db::name('ParentChild')->where('flag',1)->where('parent_id','in',$ids)->field('parent_id as parentId,child_id as childId,relation')->select();
		$childIds = Db::name('ParentChild')->where('flag',1)->where('parent_id','in',$ids)->value("GROUP_CONCAT(child_id)");
		$where2['flag'] = 1;
		$where2['status'] = 1;
		$where2['school_id'] = $param['schoolId'];
		$where2['id'] = array('in',$childIds);
		$data['childList'] = $Child->where($where2)->field('id as childId,unique_code as uniqueCode,realname,classes_id as classId')->select();
		return $this->suc($data,$nextStartId);
	}
    /**
     * 安卓
     * 柜机获取教职工集合
     */
    public function androidGetTeacherList(){
        $Teacher = model('Teachers');
        $param = $this->param;
        if(empty($param['schoolId'])){
            return $this->err('参数错误！');
        }
        $where['school_id'] = $param['schoolId'];
        $where['update_time'] = array('egt',intval(input('lastTime')));
        $field = "id as teacherId,realname,id_card as IdCard,tel";
        $data = $Teacher->where($where)->field($field)->order('create_time desc,id desc')->select();
        $delwhere['school_id'] = $param['schoolId'];
        $delwhere['flag'] = 2;
        $delwhere['update_time'] = array('egt',intval(input('lastTime')));
        $delid = $Teacher->where($delwhere)->order('create_time desc')->column('id');
        $jobwhere['school_id'] = $param['schoolId'];
        $jobwhere['flag'] = 1;
        $jobwhere['is_job'] = array('neq',1);
        $jobwhere['update_time'] = array('egt',intval(input('lastTime')));
        $jobid = $Teacher->where($jobwhere)->order('create_time desc')->column('id');
        $delteacherid = array_merge($delid,$jobid);
        //获取人脸信息
        foreach ($data as $key=>$val){
            if(!in_array($val['teacherId'],$delteacherid)) {
                $path = config('app_upload_path') . "/uploads/eyesmart/faceinfo/" . $param['schoolId'] . "/teachers/" . $val['teacherId'] . ".face";
                $faceInfo = file_get_contents($path);
                $data[$key]['faceInfo'] = $faceInfo ? $faceInfo : "";
            }else{
                $data[$key]['faceInfo'] = "";
            }
        }
        $backdata['teacherList'] = $data;
        $backdata['updateTime'] = intval(time());
        return $this->suc($backdata);
    }
    /**
     * 安卓
     * 获取家长信息与孩子信息及其关系
     * 只有主家属才能接送孩子，并且有人脸信息
     */
    public function androidGetParentList(){
        $Parent = model('Parents');$Child = model('Childs');
        $param = $this->param;
        if(empty($param['schoolId'])){
            return $this->err('未找到学校！');
        }
        //$where['flag'] = 1;
        $where['school_id'] = $param['schoolId'];
        //$where['status'] = 1;//正常的家长，离线或毕业不计入
        $where['update_time'] = array('egt',intval(input('lastTime')));
        $field = "id as parentId,realname,id_card as IdCard,tel,is_main_pick as isMain";
        $data['parentList'] = $Parent->where($where)->field($field)->order('create_time desc')->select();
        //取出删除的家长id
        $delwhere['flag'] = 2;
        $delwhere['school_id'] = $param['schoolId'];
        $delwhere['update_time'] = array('egt',intval(input('lastTime')));
        $dellist = $Parent->where($delwhere)->order('create_time desc')->column('id');
        //取出状态不是正常的家长id
        $statuswhere['flag'] = 1;
        $statuswhere['school_id'] = $param['schoolId'];
        $statuswhere['status'] = array('neq',1);
        $statuswhere['update_time'] = array('egt',intval(input('lastTime')));
        $statuslist = $Parent->where($statuswhere)->order('create_time desc')->column('id');
        //两种删除的家长id数组合并
        $delparentid = array_merge($dellist,$statuslist);
        //默认接送人返回0，适应旧接口
        foreach ($data['parentList'] as $item=>$value){
            $data['parentList'][$item]['isMain'] = $value['isMain'] == 1 ? 0 : 2;
        }
        $ids = [];
        //获取人脸信息
        foreach ($data['parentList'] as $key=>$val){
            if(!in_array($val['parentId'],$delparentid)){
                $path = config('app_upload_path')."/uploads/eyesmart/faceinfo/".$param['schoolId']."/parents/".$val['parentId'].".face";
                $faceInfo = file_get_contents($path);
                $data['parentList'][$key]['faceInfo'] = $faceInfo?$faceInfo:"";
                array_push($ids,$val['parentId']);
            }else{
                $data['parentList'][$key]['faceInfo'] = "";
                array_push($ids,$val['parentId']);
            }
        }
        //家长与孩子的绑定信息
        $data['unionList'] = Db::view('ParentChild','parent_id as parentId,relation')
                    ->view('Childs','id as childId,status as cstatus,flag as cflag','ParentChild.child_id = Childs.id')
                    ->where('ParentChild.flag',1)
                    ->where('ParentChild.parent_id','in',$ids)
                    ->select();
        foreach ($data['unionList'] as $key=>$val){
            $childIds[$key] = $val['childId'];
            if($val['cstatus'] != 1 || $val['cflag'] != 1){
                $delchildid[] = $val['childId'];
            }
        }
        $childIds = implode(',',$childIds);
//        $where2['flag'] = 1;
//        $where2['status'] = 1;
        $where2['school_id'] = $param['schoolId'];
        $where2['id'] = array('in',$childIds);
        $data['childList'] = $Child->where($where2)->field('id as childId,unique_code as uniqueCode,realname,classes_id as classId')->select();
        //获取人脸信息
        foreach ($data['childList'] as $key=>$val){
            if(!in_array($val['childId'],$delchildid)){
                $path = config('app_upload_path')."/uploads/eyesmart/faceinfo/".$param['schoolId']."/children/".$val['childId'].".face";
                $faceInfo = file_get_contents($path);
                $data['childList'][$key]['faceInfo'] = $faceInfo?$faceInfo:"";
            }else{
                $data['childList'][$key]['faceInfo'] = "";
            }
        }
        $data['updateTime'] = intval(time());
        return $this->suc($data);
    }
    /**
     * 安卓获取临时接送
     */
    public function androidGetTempTakes(){
        $TempTakes = model('TempTakes');
        $param = $this->param;
        if(empty($param['schoolId'])){
            return $this->err('参数错误！');
        }
        if(empty($param['takeUniqueCode']) && empty($param['idCard'])){
            return $this->err('参数错误！');
        }elseif(empty($param['takeUniqueCode'])){
            $where['take_id_card'] = $param['idCard'];
        }else{
            $where['take_unique_code'] = $param['takeUniqueCode'];
        }
        $field = 'user_id as userId,take_realname as takeRealname,take_relation as takeRelation,child_realname as realname,take_type as takeType';
        $info = $TempTakes->where('flag',1)->whereTime('take_time','today')->where('status',2)->where($where)->where('school_id',$param['schoolId'])->field($field)->find();
        if(empty($info)){
            return $this->err('未找到您的接送信息！');
        }
        $TempTakes->where('flag',1)->whereTime('take_time','today')->where('status',2)->where($where)->where('school_id',$param['schoolId'])->update(['status'=>3,'update_time'=>time()]);
        return $this->suc($info);
    }
	/**
	 * 获取临时接送
	 */
	public function getTempTakes(){
		$TempTakes = model('TempTakes');
		$param = $this->param;
		if(empty($param['schoolId'])){
			return $this->err('参数错误！');
		}
		if(empty($param['takeUniqueCode']) && empty($param['idCard'])){
			return $this->err('参数错误！');
		}elseif(empty($param['takeUniqueCode'])){
			$where['take_id_card'] = $param['idCard'];
		}else{
			$where['take_unique_code'] = $param['takeUniqueCode'];
		}
		$field = 'user_id as userId,take_realname as takeRealname,take_relation as takeRelation,child_realname as realname,take_type as takeType';
		$info = $TempTakes->where('flag',1)->whereTime('take_time','today')->where('status',2)->where($where)->where('school_id',$param['schoolId'])->field($field)->find();
		if(empty($info)){
			return $this->err('未找到您的接送信息！');
		}
		$TempTakes->where('flag',1)->whereTime('take_time','today')->where('status',2)->where($where)->where('school_id',$param['schoolId'])->update(['status'=>3,'update_time'=>time()]);
		return $this->suc($info);
	}
	
	/**
	 * 识别成功记录
	 * 记录一张人脸信息与userId
	 */
	public function faceRecord(){
		$param = $this->param;
		if(empty($param['recordTime']) || empty($param['userId']) || !in_array($param['type'], array(1,2)) || empty($param['schoolId']) || !in_array($param['isIn'],array(1,2))){
			return $this->err('参数错误');
		}
		$img = $param['imgPath'];
		if(empty($img)){
			return $this->err('上传失败！');
		}
		$file_src = input('schoolId')."_".(input('type')==1?"parentFace":"techerFace");//学校ID_家长，学校ID_教职工
		//匹配出图片的格式
		if(preg_match('/^(data:\s*image\/(\w+);base64,)/', $img, $result)){
			$path = config('app_upload_path')."/uploads/eyesmart/photo/".$file_src."/".date('Ymd')."/";
			if(!file_exists($path)){
				//检查是否有该文件夹，如果没有就创建，并给予最高权限
				mkdir($path, 0777, true);
			}
			$new_file = $path.input('userId')."_".(microtime(true)*10000).".".$result[2];//userId_时间戳
			if (file_put_contents($new_file, base64_decode(str_replace($result[1],'', $img)))){
					$faceImgPath = ltrim($new_file,config('app_upload_path'));
				}else{
					return $this->err('上传图片错误，记录失败！');
				}
			}else{
			return $this->err('格式错误！');
		}
		Db::startTrans();
		try {
			//家长记录出入园
			if($param['type'] == 1){
				$Parent = model('Parents');$Child = model('Childs');$TimeCard = model('TimeCard');$School = model('Schools');
				//根据家长ID获取小孩，如家长有多个小孩，默认记录所有小孩
				$ids = Db::name('ParentChild')->where('parent_id',$param['userId'])->where('flag',1)->value('GROUP_CONCAT(child_id)');
				if(empty($ids)){
					return $this->err('没有找到您在本园的孩子信息！');
				}
				$childList = $Child->where('id','in',$ids)->where('status',1)->where('flag',1)->field('id,photo,realname')->select();
				//获取学校上学放学的时间
				$scInfo = $School->where('id',$param['schoolId'])->field('onschool_time,offschool_time')->find();
				$parentName = $Parent->where('id',$param['userId'])->value('realname');
				foreach($childList as $key=>$val){
					//判断今日是否已经有打卡记录，如无则新增一条记录
					$where['type'] = 1;
					$where['flag'] = 1;
					$where['school_id'] = $param['schoolId'];
					$where['user_id'] = $val['id'];//孩子ID
					$where['day_time'] = date('Y-m-d',$param['recordTime']);
					$tinfo = $TimeCard->where($where)->find();
					if(empty($tinfo)){
						$data['type'] = 1;
						$data['school_id'] = $param['schoolId'];
						$data['user_id'] = $val['id'];//插入的小孩ID
						$data['realname'] = $val['realname'];
						$data['photo'] = $val['photo'];
						$data['day_time'] = date('Y-m-d',$param['recordTime']);
						if($param['isIn'] == 1){//入园
							$data['record_time'] = $param['recordTime'];
							$data['face_img'] = $faceImgPath;
							//判断小孩是否迟到或早退
							$bak = $this->beLateOrLeave($param['recordTime'], $scInfo['onschool_time'], $scInfo['offschool_time']);
							$data['in_status'] = $bak['inStatus'];
							$data['out_status'] = 0;
							$data['parent_name'] = $parentName;
							$data['is_in'] = 1;
							$data['in_num'] = 1;
							$data['precision'] = intval($param['precision']);
						}else{//出园
							$data['record_time'] = "0|".$param['recordTime'];
							$data['face_img'] = "|".$faceImgPath;
							//判断小孩是否迟到或早退
							$bak = $this->beLateOrLeave($param['recordTime'], $scInfo['onschool_time'], $scInfo['offschool_time']);
							$data['in_status'] = 0;
							$data['out_status'] = $bak['outStatus'];
							$data['parent_name'] = $parentName;
							$data['is_in'] = 2;
							$data['out_num'] = 1;
							$data['precision'] = "|".intval($param['precision']);
						}
						$result = $TimeCard->isUpdate(false)->create($data);
					}else{
						if($param['isIn'] == 1){//入园
							//如果有重复顶替掉上一条记录
							if(empty($tinfo['record_time'])){
								$data['record_time'] = $param['recordTime'];
								//如果有请假的，那么不判断是否有迟到早退
								if($tinfo['in_status'] != 2 && $tinfo['out_status'] != 2){
									$bak = $this->beLateOrLeave($time, $scInfo['onschool_time'], $scInfo['offschool_time']);
									$data['in_status'] = $bak['inStatus'];
									$data['out_status'] = 0;
									$data['is_in'] = 1;
									$data['in_num'] = 1;
								}
							}else{
								$time = explode("|", $tinfo['record_time']);
								$time[0] = $param['recordTime'];
								$time = implode("|", $time);
								$data['record_time'] = $time;
								$data['is_in'] = 1;
								$data['in_num'] = 'in_num+1';
							}
							if(empty($tinfo['face_img'])){
								$data['face_img'] = $faceImgPath;
							}else{
								$face = explode("|", $tinfo['face_img']);
								$face[0] = $faceImgPath;
								$face = implode("|", $face);
								$data['face_img'] = $face;
							}
							if($tinfo['precision'] == ""){
								$data['precision'] = intval($param['precision']);
							}else{
								$precision = explode("|", $tinfo['precision']);
								$precision[0] = intval($param['precision']);
								$precision = implode("|", $precision);
								$data['precision'] = $precision;
							}
						}else{//出园
							//如果有重复顶替掉上一条记录
							if(empty($tinfo['record_time'])){
								$data['record_time'] = "0|".$param['recordTime'];
								//如果有请假的，那么不判断是否有迟到早退
								if($tinfo['in_status'] != 2 && $tinfo['out_status'] != 2){
									$bak = $this->beLateOrLeave($time, $scInfo['onschool_time'], $scInfo['offschool_time']);
									$data['in_status'] = 0;
									$data['out_status'] = $bak['outStatus'];
								}
								$data['is_in'] = 2;
								$data['out_num'] = 1;
							}else{
								$time = explode("|", $tinfo['record_time']);
								$time[1] = $param['recordTime'];
								$time = implode("|", $time);
								$data['record_time'] = $time;
								//如果有请假的，那么不判断是否有迟到早退
								if($tinfo['in_status'] != 2 && $tinfo['out_status'] != 2){
									$bak = $this->beLateOrLeave($time, $scInfo['onschool_time'], $scInfo['offschool_time']);
									$data['out_status'] = $bak['outStatus'];
								}
								$data['is_in'] = 2;
								$data['out_num'] = 'out_num+1';
							}
							if(empty($tinfo['face_img'])){
								$data['face_img'] = "|".$faceImgPath;
							}else{
								$face = explode("|", $tinfo['face_img']);
								$face[1] = $faceImgPath;
								$face = implode("|", $face);
								$data['face_img'] = $face;
							}
							if($tinfo['precision'] == ""){
								$data['precision'] = "|".intval($param['precision']);
							}else{
								$precision = explode("|", $tinfo['precision']);
								$precision[1] = intval($param['precision']);
								$precision = implode("|", $precision);
								$data['precision'] = $precision;
							}
						}
						$data['update_time'] = time();
						$result = $TimeCard->where($where)->update($data);
					}
					$message .= "[".$val['realname']."]";
					$childId = $val['id'];
				}
				//推送给所有从属家长
				$ids = Db::name('ParentChild')->where('child_id',$childId)->where('flag',1)->value('GROUP_CONCAT(parent_id)');
				$jpushId = $Parent->where('id','in',$ids)->where('flag',1)->whereNotNull('jpush_id')->where('jpush_id','neq','')->value('GROUP_CONCAT(jpush_id)');
				if(!empty($jpushId)){
					//判断是入园还是出园
					if($param['isIn'] == 1){
						$message = "您的小孩".$message."被".$parentName."接送入园！";
					}else{
						$message = "您的小孩".$message."被".$parentName."接送出园！";
					}
					$extra = array('viewCode'=>80000,'imgPath'=>config('view_replace_str.__IMGROOT__').$faceImgPath,'recordTime'=>$param['recordTime'],'parentName' => $parentName,'precision'=>$param['precision']);
					jpushToId($jpushId, $message,1,$extra);
				}
			}else{//教职工
				$TimeCard = model('TimeCard');$School = model('Schools');$Teacher = model('Teachers');
				//获取学校上班下班的时间
				$scInfo = $School->where('id',$param['schoolId'])->field('onwork_time,offwork_time')->find();
				$terinfo = $Teacher->where('id',$param['userId'])->where('flag',1)->where('is_job',1)->find();
				if(empty($terinfo)){
					return $this->err("未找到该教职工的信息！");
				}
				//判断今日是否已经有打卡记录，如无则新增一条记录
				$where['type'] = 2;
				$where['flag'] = 1;
				$where['school_id'] = $param['schoolId'];
				$where['user_id'] = $param['userId'];
				$where['day_time'] = date('Y-m-d',$param['recordTime']);
				$tinfo = $TimeCard->where($where)->find();
				if(empty($tinfo)){
					$data['type'] = 2;
					$data['school_id'] = $param['schoolId'];
					$data['user_id'] = $param['userId'];//教师ID
					$data['realname'] = $terinfo['realname'];
					$data['photo'] = $terinfo['photo'];
					$data['day_time'] = date('Y-m-d',$param['recordTime']);
					if($param['isIn'] == 1){//入园
						$data['record_time'] = $param['recordTime'];
						$data['face_img'] = $faceImgPath;
						//判断教师是否迟到或早退
						$bak = $this->beLateOrLeave($param['recordTime'], $scInfo['onwork_time'], $scInfo['offwork_time']);
						$data['in_status'] = $bak['inStatus'];
						$data['out_status'] = 0;
						$data['is_in'] = 1;
						$data['in_num'] = 1;
						$data['precision'] = intval($param['precision']);
					}else{//出园
						$data['record_time'] = "0|".$param['recordTime'];
						$data['face_img'] = "|".$faceImgPath;
						//判断教师是否迟到或早退
						$bak = $this->beLateOrLeave($param['recordTime'], $scInfo['onwork_time'], $scInfo['offwork_time']);
						$data['in_status'] = 0;
						$data['out_status'] = $bak['outStatus'];
						$data['is_in'] = 2;
						$data['out_num'] = 1;
						$data['precision'] = "|".intval($param['precision']);
					}
					$result = $TimeCard->isUpdate(false)->create($data);
				}else{
					//如果有重复，则入园取第一条，出园取最后一条
					if($param['isIn'] == 1){//入园
						if(empty($tinfo['record_time'])){
							$data['record_time'] = $param['recordTime'];
							//如果有请假的，那么不判断是否有迟到早退
							if($tinfo['in_status'] != 2 && $tinfo['out_status'] != 2){
								$bak = $this->beLateOrLeave($time, $scInfo['onwork_time'], $scInfo['offwork_time']);
								$data['in_status'] = $bak['inStatus'];
								$data['out_status'] = 0;
							}
							$data['is_in'] = 1;
							$data['in_num'] = 'in_num+1';
						}
						if(empty($tinfo['face_img'])){
							$data['face_img'] = $faceImgPath;
						}
						if($tinfo['precision'] == ""){
							$data['precision'] = intval($param['precision']);
						}
					}else{//出园
						//如果有重复顶替掉上一条记录
						if(empty($tinfo['record_time'])){
							$data['record_time'] = "0|".$param['recordTime'];
							//如果有请假的，那么不判断是否有迟到早退
							if($tinfo['in_status'] != 2 && $tinfo['out_status'] != 2){
								$bak = $this->beLateOrLeave($time, $scInfo['onwork_time'], $scInfo['offwork_time']);
								$data['in_status'] = 0;
								$data['out_status'] = $bak['outStatus'];
							}
							$data['is_in'] = 2;
							$data['out_num'] = 1;
						}else{
							$time = explode("|", $tinfo['record_time']);
							$time[1] = $param['recordTime'];
							$time = implode("|", $time);
							$data['record_time'] = $time;
							//如果有请假的，那么不判断是否有迟到早退
							if($tinfo['in_status'] != 2 && $tinfo['out_status'] != 2){
								$bak = $this->beLateOrLeave($time, $scInfo['onwork_time'], $scInfo['offwork_time']);
								$data['out_status'] = $bak['outStatus'];
							}
							$data['is_in'] = 2;
							$data['out_num'] = 'out_num+1';
						}
						if(empty($tinfo['face_img'])){
							$data['face_img'] = "|".$faceImgPath;
						}else{
							$face = explode("|", $tinfo['face_img']);
							$face[1] = $faceImgPath;
							$face = implode("|", $face);
							$data['face_img'] = $face;
						}
						if($tinfo['precision'] == ""){
							$data['precision'] = "|".intval($param['precision']);
						}else{
							$precision = explode("|", $tinfo['precision']);
							$precision[1] = intval($param['precision']);
							$precision = implode("|", $precision);
							$data['precision'] = $precision;
						}
					}
					$data['update_time'] = time();
					$result = $TimeCard->where($where)->update($data);
				}
				//推送给教职工
				$jpushId = $Teacher->where('id',$param['userId'])->value('jpush_id');
				if(!empty($jpushId)){
					//判断是否早退或迟到
					if($data['inStatus'] == -1){
						$message = "考勤成功，您已迟到！";
					}elseif($data['out_status'] == -1){
						$message = "考勤成功，还未到下班时间！";
					}else{
						$message = "您今天考勤成功！";
					}
					$extra = array('viewCode'=>80001);
					jpushToId($jpushId, $message,2,$extra);
				}
				//推送给园长，透析消息
				$jpushId2 = Db::name("Headmasters")->where('flag',1)->where('school_id',$param['schoolId'])->value('jpush_id');
				if(!empty($jpushId2)){
					$extra = array('viewCode'=>80012);
					jpushToId($jpushId2, "考勤成功！",3,$extra,true);
				}
			}
			Db::commit();
			return $this->suc();
		} catch (\Exception $e) {
			Db::rollback();
			return $this->err("记录失败！");
		}
	}
    /**
     * 安卓
     * 临时接送成功
     * 记录一张照片
     */
    public function androidTempRecord(){
        $param = $this->param;
        if(empty($param['takeCode']) || empty($param['recordTime']) || empty($param['schoolId']) || empty($param['imgPath'])){
            return $this->err('参数缺失');
        }
        $temptake = model('TempTakes');
        $tinfo = $temptake->where('flag',1)->whereTime('take_time', 'today')->where('school_id',$param['schoolId'])->where('status','in',[1,2])->where('take_unique_code',$param['takeCode'])->field('id,user_id as parent_id,child_id,take_realname,take_type,take_time,child_realname')->find();
        if (empty($tinfo)){
            return $this->err('接送信息不存在!',601);
        }else{//上传临时接送的人脸
            $img = $param['imgPath'];
            $file_src = input('schoolId')."_temptakes";//学校ID_临时接送
            //匹配出图片的格式
            if(preg_match('/^(data:\s*image\/(\w+);base64,)/', $img, $result)){
                $path = config('app_upload_path')."/uploads/eyesmart/photo/".$file_src."/".date('Ymd')."/";
                if(!file_exists($path)){
                    //检查是否有该文件夹，如果没有就创建，并给予最高权限
                    mkdir($path, 0777, true);
                }
                $new_file = $path.$tinfo['parent_id']."_".(microtime(true)*10000).".".$result[2];//userId_时间戳
                if (file_put_contents($new_file, base64_decode(str_replace($result[1],'', $img)))){
                    $faceImgPath = ltrim($new_file,config('app_upload_path'));
                }else{
                    return $this->err('上传图片错误，记录失败！');
                }
            }else{
                return $this->err('格式错误！');
            }
        }
        //将临时接送信息保存
        try{
            $Parent = model('Parents');$Child = model('Childs');$TimeCard = model('TimeCard');$School = model('Schools');
            //获取学校上学放学的时间
            $scInfo = $School->where('id',$param['schoolId'])->field('onschool_time,offschool_time')->find();
            //临时接送信息
            $data['type'] = 4;//临时接送的类别
            $data['school_id'] = $param['schoolId'];
            $data['user_id'] = $tinfo['child_id'];//插入的小孩ID
            $data['realname'] = $tinfo['child_realname'];
            $data['day_time'] = date('Y-m-d',$param['recordTime']);
            if($tinfo['take_type'] == 1){//入园
                $data['record_time'] = $param['recordTime'];
                $data['face_img'] = $faceImgPath;
                //判断小孩是否迟到或早退
                $bak = $this->beLateOrLeave($param['recordTime'], $scInfo['onschool_time'], $scInfo['offschool_time']);
                $data['in_status'] = $bak['inStatus'];
                $data['out_status'] = 0;
                $data['parent_name'] = $tinfo['take_realname'];
                $data['is_in'] = 1;
                $data['in_num'] = 1;
            }elseif($tinfo['take_type'] == 2){//出园
                $data['record_time'] = "0|".$param['recordTime'];
                $data['face_img'] = "|".$faceImgPath;
                //判断小孩是否迟到或早退
                $bak = $this->beLateOrLeave($param['recordTime'], $scInfo['onschool_time'], $scInfo['offschool_time']);
                $data['in_status'] = 0;
                $data['out_status'] = $bak['outStatus'];
                $data['parent_name'] = $tinfo['take_realname'];
                $data['is_in'] = 2;
                $data['out_num'] = 1;
            }
            $result = $TimeCard->isUpdate(false)->create($data);
            $temptake->where('id',$tinfo['id'])->update(['status'=>3]);
            //推送给所有从属家长
            $ids = Db::name('ParentChild')->where('child_id',$tinfo['child_id'])->where('flag',1)->value('GROUP_CONCAT(parent_id)');
            $jpushId = $Parent->where('id','in',$ids)->where('flag',1)->whereNotNull('jpush_id')->where('jpush_id','neq','')->value('GROUP_CONCAT(jpush_id)');
            if(!empty($jpushId)){
                //判断是入园还是出园
                if($tinfo['take_type'] == 1){
                    $message = "您的小孩".$tinfo['child_realname']."被".$tinfo['take_realname']."接送入园！";
                }else{
                    $message = "您的小孩".$tinfo['child_realname']."被".$tinfo['take_realname']."接送出园！";
                }
                $extra = array('viewCode'=>80000,'imgPath'=>config('view_replace_str.__IMGROOT__').$faceImgPath,'recordTime'=>$param['recordTime'],'parentName' => $tinfo['take_realname'],'precision'=>0);
                jpushToId($jpushId, $message,1,$extra);
            }
            $backdata['takeName'] = $tinfo['take_realname'];
            return $this->suc($backdata);
        }catch (\Exception $e) {
            //Db::rollback();
            return $this->err("记录失败！");
        }
    }
    /**
     * 安卓
     * 识别成功记录
     * 记录一张人脸信息与userId
     */
    public function androidFaceRecord(){
        $param = $this->param;
        if(empty($param['recordTime']) || empty($param['userId']) || !in_array($param['type'], array(1,2,3)) || empty($param['schoolId']) || !in_array($param['isIn'],array(1,2))){
            return $this->err('参数错误');
        }
        $img = $param['imgPath'];
        if(empty($img)){
            return $this->err('上传失败！');
        }
        switch ($param['type']){
            case 1:
                $type = 'parents';
                break;
            case 2:
                $type = 'teachers';
                break;
            case 3:
                $type = 'children';
                break;
        }
        $file_src = input('schoolId')."_".$type;//学校ID_家长，学校ID_教职工,学校ID_孩子
        //匹配出图片的格式
        if(preg_match('/^(data:\s*image\/(\w+);base64,)/', $img, $result)){
            $path = config('app_upload_path')."/uploads/eyesmart/photo/".$file_src."/".date('Ymd')."/";
            if(!file_exists($path)){
                //检查是否有该文件夹，如果没有就创建，并给予最高权限
                mkdir($path, 0777, true);
            }
            $new_file = $path.input('userId')."_".(microtime(true)*10000).".".$result[2];//userId_时间戳
            if (file_put_contents($new_file, base64_decode(str_replace($result[1],'', $img)))){
                $faceImgPath = ltrim($new_file,config('app_upload_path'));
            }else{
                return $this->err('上传图片错误，记录失败！');
            }
        }else{
            return $this->err('格式错误！');
        }
        //Db::startTrans();
        try {
            //家长记录出入园
            if($param['type'] == 1){
                $Parent = model('Parents');$Child = model('Childs');$TimeCard = model('TimeCard');$School = model('Schools');
                //根据家长ID获取小孩，如家长有多个小孩，默认记录所有小孩
                $ids = Db::name('ParentChild')->where('parent_id',$param['userId'])->where('flag',1)->value('GROUP_CONCAT(child_id)');
                if(empty($ids)){
                    //无孩子情况下的操作
                    return $this->suc();
                }else{
                    $childList = $Child->where('id','in',$ids)->where('status',1)->where('flag',1)->field('id,photo,realname')->select();
                    //获取学校上学放学的时间
                    $scInfo = $School->where('id',$param['schoolId'])->field('onschool_time,offschool_time')->find();
                    $parentName = $Parent->where('id',$param['userId'])->value('realname');
                    foreach($childList as $key=>$val){
                        //判断今日是否已经有打卡记录，如无则新增一条记录
                        $where['type'] = 1;
                        $where['flag'] = 1;
                        $where['school_id'] = $param['schoolId'];
                        $where['user_id'] = $val['id'];//孩子ID
                        $where['day_time'] = date('Y-m-d',$param['recordTime']);
                        $tinfo = $TimeCard->where($where)->find();
                        if(empty($tinfo)){
                            $data['type'] = 1;
                            $data['school_id'] = $param['schoolId'];
                            $data['user_id'] = $val['id'];//插入的小孩ID
                            $data['realname'] = $val['realname'];
                            $data['photo'] = $val['photo'];
                            $data['day_time'] = date('Y-m-d',$param['recordTime']);
                            if($param['isIn'] == 1){//入园
                                $data['record_time'] = $param['recordTime'];
                                $data['face_img'] = $faceImgPath;
                                //判断小孩是否迟到或早退
                                $bak = $this->beLateOrLeave($param['recordTime'], $scInfo['onschool_time'], $scInfo['offschool_time']);
                                $data['in_status'] = $bak['inStatus'];
                                $data['out_status'] = 0;
                                $data['parent_name'] = $parentName;
                                $data['is_in'] = 1;
                                $data['in_num'] = 1;
                                $data['precision'] = intval($param['precision']);
                            }else{//出园
                                $data['record_time'] = "0|".$param['recordTime'];
                                $data['face_img'] = "|".$faceImgPath;
                                //判断小孩是否迟到或早退
                                $bak = $this->beLateOrLeave($param['recordTime'], $scInfo['onschool_time'], $scInfo['offschool_time']);
                                $data['in_status'] = 0;
                                $data['out_status'] = $bak['outStatus'];
                                $data['parent_name'] = $parentName;
                                $data['is_in'] = 2;
                                $data['out_num'] = 1;
                                $data['precision'] = "|".intval($param['precision']);
                            }
                            $result = $TimeCard->isUpdate(false)->create($data);
                        }else{
                            if($param['isIn'] == 1){//入园
                                //如果有重复顶替掉上一条记录
                                if(empty($tinfo['record_time'])){
                                    $data['record_time'] = $param['recordTime'];
                                    //如果有请假的，那么不判断是否有迟到早退
                                    if($tinfo['in_status'] != 2 && $tinfo['out_status'] != 2){
                                        $bak = $this->beLateOrLeave($time, $scInfo['onschool_time'], $scInfo['offschool_time']);
                                        $data['in_status'] = $bak['inStatus'];
                                        $data['out_status'] = 0;
                                        $data['is_in'] = 1;
                                        $data['in_num'] = 1;
                                    }
                                }else{
                                    $time = explode("|", $tinfo['record_time']);
                                    $time[0] = $param['recordTime'];
                                    $time = implode("|", $time);
                                    $data['record_time'] = $time;
                                    $data['is_in'] = 1;
                                    $data['in_num'] = 'in_num+1';
                                }
                                if(empty($tinfo['face_img'])){
                                    $data['face_img'] = $faceImgPath;
                                }else{
                                    $face = explode("|", $tinfo['face_img']);
                                    $face[0] = $faceImgPath;
                                    $face = implode("|", $face);
                                    $data['face_img'] = $face;
                                }
                                if($tinfo['precision'] == ""){
                                    $data['precision'] = intval($param['precision']);
                                }else{
                                    $precision = explode("|", $tinfo['precision']);
                                    $precision[0] = intval($param['precision']);
                                    $precision = implode("|", $precision);
                                    $data['precision'] = $precision;
                                }
                            }else{//出园
                                //如果有重复顶替掉上一条记录
                                if(empty($tinfo['record_time'])){
                                    $data['record_time'] = "0|".$param['recordTime'];
                                    //如果有请假的，那么不判断是否有迟到早退
                                    if($tinfo['in_status'] != 2 && $tinfo['out_status'] != 2){
                                        $bak = $this->beLateOrLeave($time, $scInfo['onschool_time'], $scInfo['offschool_time']);
                                        $data['in_status'] = 0;
                                        $data['out_status'] = $bak['outStatus'];
                                    }
                                    $data['is_in'] = 2;
                                    $data['out_num'] = 1;
                                }else{
                                    $time = explode("|", $tinfo['record_time']);
                                    $time[1] = $param['recordTime'];
                                    $time = implode("|", $time);
                                    $data['record_time'] = $time;
                                    //如果有请假的，那么不判断是否有迟到早退
                                    if($tinfo['in_status'] != 2 && $tinfo['out_status'] != 2){
                                        $bak = $this->beLateOrLeave($time, $scInfo['onschool_time'], $scInfo['offschool_time']);
                                        $data['out_status'] = $bak['outStatus'];
                                    }
                                    $data['is_in'] = 2;
                                    $data['out_num'] = 'out_num+1';
                                }
                                if(empty($tinfo['face_img'])){
                                    $data['face_img'] = "|".$faceImgPath;
                                }else{
                                    $face = explode("|", $tinfo['face_img']);
                                    $face[1] = $faceImgPath;
                                    $face = implode("|", $face);
                                    $data['face_img'] = $face;
                                }
                                if($tinfo['precision'] == ""){
                                    $data['precision'] = "|".intval($param['precision']);
                                }else{
                                    $precision = explode("|", $tinfo['precision']);
                                    $precision[1] = intval($param['precision']);
                                    $precision = implode("|", $precision);
                                    $data['precision'] = $precision;
                                }
                            }
                            $data['update_time'] = time();
                            $result = $TimeCard->where($where)->update($data);
                        }
                        $message .= "[".$val['realname']."]";
                        $childId = $val['id'];
                    }
                    //推送给所有从属家长
                    $ids = Db::name('ParentChild')->where('child_id',$childId)->where('flag',1)->value('GROUP_CONCAT(parent_id)');
                    $jpushId = $Parent->where('id','in',$ids)->where('flag',1)->whereNotNull('jpush_id')->where('jpush_id','neq','')->value('GROUP_CONCAT(jpush_id)');
                    if(!empty($jpushId)){
                        //判断是入园还是出园
                        if($param['isIn'] == 1){
                            $message = "您的小孩".$message."被".$parentName."接送入园！";
                        }else{
                            $message = "您的小孩".$message."被".$parentName."接送出园！";
                        }
                        $extra = array('viewCode'=>80000,'imgPath'=>config('view_replace_str.__IMGROOT__').$faceImgPath,'recordTime'=>$param['recordTime'],'parentName' => $parentName,'precision'=>$param['precision']);
                        jpushToId($jpushId, $message,1,$extra);
                    }
                }
            }elseif($param['type'] == 2){//教职工
                $TimeCard = model('TimeCard');$School = model('Schools');$Teacher = model('Teachers');
                //获取学校上班下班的时间
                $scInfo = $School->where('id',$param['schoolId'])->field('onwork_time,offwork_time')->find();
                $terinfo = $Teacher->where('id',$param['userId'])->where('flag',1)->where('is_job',1)->find();
                if(empty($terinfo)){
                    return $this->err("未找到该教职工的信息！");
                }
                //判断今日是否已经有打卡记录，如无则新增一条记录
                $where['type'] = 2;
                $where['flag'] = 1;
                $where['school_id'] = $param['schoolId'];
                $where['user_id'] = $param['userId'];
                $where['day_time'] = date('Y-m-d',$param['recordTime']);
                $tinfo = $TimeCard->where($where)->find();
                if(empty($tinfo)){
                    $data['type'] = 2;
                    $data['school_id'] = $param['schoolId'];
                    $data['user_id'] = $param['userId'];//教师ID
                    $data['realname'] = $terinfo['realname'];
                    $data['photo'] = $terinfo['photo'];
                    $data['day_time'] = date('Y-m-d',$param['recordTime']);
                    if($param['isIn'] == 1){//入园
                        $data['record_time'] = $param['recordTime'];
                        $data['face_img'] = $faceImgPath;
                        //判断教师是否迟到或早退
                        $bak = $this->beLateOrLeave($param['recordTime'], $scInfo['onwork_time'], $scInfo['offwork_time']);
                        $data['in_status'] = $bak['inStatus'];
                        $data['out_status'] = 0;
                        $data['is_in'] = 1;
                        $data['in_num'] = 1;
                        $data['precision'] = intval($param['precision']);
                    }else{//出园
                        $data['record_time'] = "0|".$param['recordTime'];
                        $data['face_img'] = "|".$faceImgPath;
                        //判断教师是否迟到或早退
                        $bak = $this->beLateOrLeave($param['recordTime'], $scInfo['onwork_time'], $scInfo['offwork_time']);
                        $data['in_status'] = 0;
                        $data['out_status'] = $bak['outStatus'];
                        $data['is_in'] = 2;
                        $data['out_num'] = 1;
                        $data['precision'] = "|".intval($param['precision']);
                    }
                    $result = $TimeCard->isUpdate(false)->create($data);
                }else{
                    //如果有重复，则入园取第一条，出园取最后一条
                    if($param['isIn'] == 1){//入园
                        if(empty($tinfo['record_time'])){
                            $data['record_time'] = $param['recordTime'];
                            //如果有请假的，那么不判断是否有迟到早退
                            if($tinfo['in_status'] != 2 && $tinfo['out_status'] != 2){
                                $bak = $this->beLateOrLeave($time, $scInfo['onwork_time'], $scInfo['offwork_time']);
                                $data['in_status'] = $bak['inStatus'];
                                $data['out_status'] = 0;
                            }
                            $data['is_in'] = 1;
                            $data['in_num'] = 'in_num+1';
                        }
                        if(empty($tinfo['face_img'])){
                            $data['face_img'] = $faceImgPath;
                        }
                        if($tinfo['precision'] == ""){
                            $data['precision'] = intval($param['precision']);
                        }
                    }else{//出园
                        //如果有重复顶替掉上一条记录
                        if(empty($tinfo['record_time'])){
                            $data['record_time'] = "0|".$param['recordTime'];
                            //如果有请假的，那么不判断是否有迟到早退
                            if($tinfo['in_status'] != 2 && $tinfo['out_status'] != 2){
                                $bak = $this->beLateOrLeave($time, $scInfo['onwork_time'], $scInfo['offwork_time']);
                                $data['in_status'] = 0;
                                $data['out_status'] = $bak['outStatus'];
                            }
                            $data['is_in'] = 2;
                            $data['out_num'] = 1;
                        }else{
                            $time = explode("|", $tinfo['record_time']);
                            $time[1] = $param['recordTime'];
                            $time = implode("|", $time);
                            $data['record_time'] = $time;
                            //如果有请假的，那么不判断是否有迟到早退
                            if($tinfo['in_status'] != 2 && $tinfo['out_status'] != 2){
                                $bak = $this->beLateOrLeave($time, $scInfo['onwork_time'], $scInfo['offwork_time']);
                                $data['out_status'] = $bak['outStatus'];
                            }
                            $data['is_in'] = 2;
                            $data['out_num'] = 'out_num+1';
                        }
                        if(empty($tinfo['face_img'])){
                            $data['face_img'] = "|".$faceImgPath;
                        }else{
                            $face = explode("|", $tinfo['face_img']);
                            $face[1] = $faceImgPath;
                            $face = implode("|", $face);
                            $data['face_img'] = $face;
                        }
                        if($tinfo['precision'] == ""){
                            $data['precision'] = "|".intval($param['precision']);
                        }else{
                            $precision = explode("|", $tinfo['precision']);
                            $precision[1] = intval($param['precision']);
                            $precision = implode("|", $precision);
                            $data['precision'] = $precision;
                        }
                    }
                    $data['update_time'] = time();
                    $result = $TimeCard->where($where)->update($data);
                }
                //推送给教职工
                $jpushId = $Teacher->where('id',$param['userId'])->value('jpush_id');
                if(!empty($jpushId)){
                    //判断是否早退或迟到
                    if($data['inStatus'] == -1){
                        $message = "考勤成功，您已迟到！";
                    }elseif($data['out_status'] == -1){
                        $message = "考勤成功，还未到下班时间！";
                    }else{
                        $message = "您今天考勤成功！";
                    }
                    $extra = array('viewCode'=>80001);
                    jpushToId($jpushId, $message,2,$extra);
                }
                //推送给园长，透析消息
                $jpushId2 = Db::name("Headmasters")->where('flag',1)->where('school_id',$param['schoolId'])->value('jpush_id');
                if(!empty($jpushId2)){
                    $extra = array('viewCode'=>80012);
                    jpushToId($jpushId2, "考勤成功！",3,$extra,true);
                }
            }elseif($param['type'] == 3){//孩子照片
                $Parent = model('Parents');
                $Child = model('Childs');
                $TimeCard = model('TimeCard');
                $School = model('Schools');
                //根据孩子id获得所有的家长id
                $ids = Db::name('ParentChild')->where('child_id',$param['userId'])->where('flag',1)->value('GROUP_CONCAT(parent_id)');
                if(empty($ids)){
                    //无孩子情况下的操作
                    return $this->suc();
                }else{
                    //$childList = $Child->where('id','in',$ids)->where('status',1)->where('flag',1)->field('id,photo,realname')->select();
                    //获取学校上学放学的时间
                    $scInfo = $School->where('id',$param['schoolId'])->field('onschool_time,offschool_time')->find();
                    $childinfo = $Child->where('id',$param['userId'])->field('id,realname,photo')->find();
                        //判断今日是否已经有打卡记录，如无则新增一条记录
                        $where['type'] = 3;
                        $where['flag'] = 1;
                        $where['school_id'] = $param['schoolId'];
                        $where['user_id'] = $childinfo['id'];//孩子ID
                        $where['day_time'] = date('Y-m-d',$param['recordTime']);
                        $tinfo = $TimeCard->where($where)->find();
                        if(empty($tinfo)){
                            $data['type'] = 3;
                            $data['school_id'] = $param['schoolId'];
                            $data['user_id'] = $childinfo['id'];//插入的小孩ID
                            $data['realname'] = $childinfo['realname'];
                            $data['photo'] = $childinfo['photo'];
                            $data['day_time'] = date('Y-m-d',$param['recordTime']);
                            if($param['isIn'] == 1){//入园
                                $data['record_time'] = $param['recordTime'];
                                $data['face_img'] = $faceImgPath;
                                //判断小孩是否迟到或早退
                                $bak = $this->beLateOrLeave($param['recordTime'], $scInfo['onschool_time'], $scInfo['offschool_time']);
                                $data['in_status'] = $bak['inStatus'];
                                $data['out_status'] = 0;
                                $data['parent_name'] = '';
                                $data['is_in'] = 1;
                                $data['in_num'] = 1;
                                $data['precision'] = intval($param['precision']);
                            }else{//出园
                                $data['record_time'] = "0|".$param['recordTime'];
                                $data['face_img'] = "|".$faceImgPath;
                                //判断小孩是否迟到或早退
                                $bak = $this->beLateOrLeave($param['recordTime'], $scInfo['onschool_time'], $scInfo['offschool_time']);
                                $data['in_status'] = 0;
                                $data['out_status'] = $bak['outStatus'];
                                $data['parent_name'] = '';
                                $data['is_in'] = 2;
                                $data['out_num'] = 1;
                                $data['precision'] = "|".intval($param['precision']);
                            }
                            $result = $TimeCard->isUpdate(false)->create($data);
                        }else{
                            if($param['isIn'] == 1){//入园
                                //如果有重复顶替掉上一条记录
                                if(empty($tinfo['record_time'])){
                                    $data['record_time'] = $param['recordTime'];
                                    //如果有请假的，那么不判断是否有迟到早退
                                    if($tinfo['in_status'] != 2 && $tinfo['out_status'] != 2){
                                        $bak = $this->beLateOrLeave($time, $scInfo['onschool_time'], $scInfo['offschool_time']);
                                        $data['in_status'] = $bak['inStatus'];
                                        $data['out_status'] = 0;
                                        $data['is_in'] = 1;
                                        $data['in_num'] = 1;
                                    }
                                }else{
                                    $time = explode("|", $tinfo['record_time']);
                                    $time[0] = $param['recordTime'];
                                    $time = implode("|", $time);
                                    $data['record_time'] = $time;
                                    $data['is_in'] = 1;
                                    $data['in_num'] = 'in_num+1';
                                }
                                if(empty($tinfo['face_img'])){
                                    $data['face_img'] = $faceImgPath;
                                }else{
                                    $face = explode("|", $tinfo['face_img']);
                                    $face[0] = $faceImgPath;
                                    $face = implode("|", $face);
                                    $data['face_img'] = $face;
                                }
                                if($tinfo['precision'] == ""){
                                    $data['precision'] = intval($param['precision']);
                                }else{
                                    $precision = explode("|", $tinfo['precision']);
                                    $precision[0] = intval($param['precision']);
                                    $precision = implode("|", $precision);
                                    $data['precision'] = $precision;
                                }
                            }else{//出园
                                //如果有重复顶替掉上一条记录
                                if(empty($tinfo['record_time'])){
                                    $data['record_time'] = "0|".$param['recordTime'];
                                    //如果有请假的，那么不判断是否有迟到早退
                                    if($tinfo['in_status'] != 2 && $tinfo['out_status'] != 2){
                                        $bak = $this->beLateOrLeave($time, $scInfo['onschool_time'], $scInfo['offschool_time']);
                                        $data['in_status'] = 0;
                                        $data['out_status'] = $bak['outStatus'];
                                    }
                                    $data['is_in'] = 2;
                                    $data['out_num'] = 1;
                                }else{
                                    $time = explode("|", $tinfo['record_time']);
                                    $time[1] = $param['recordTime'];
                                    $time = implode("|", $time);
                                    $data['record_time'] = $time;
                                    //如果有请假的，那么不判断是否有迟到早退
                                    if($tinfo['in_status'] != 2 && $tinfo['out_status'] != 2){
                                        $bak = $this->beLateOrLeave($time, $scInfo['onschool_time'], $scInfo['offschool_time']);
                                        $data['out_status'] = $bak['outStatus'];
                                    }
                                    $data['is_in'] = 2;
                                    $data['out_num'] = 'out_num+1';
                                }
                                if(empty($tinfo['face_img'])){
                                    $data['face_img'] = "|".$faceImgPath;
                                }else{
                                    $face = explode("|", $tinfo['face_img']);
                                    $face[1] = $faceImgPath;
                                    $face = implode("|", $face);
                                    $data['face_img'] = $face;
                                }
                                if($tinfo['precision'] == ""){
                                    $data['precision'] = "|".intval($param['precision']);
                                }else{
                                    $precision = explode("|", $tinfo['precision']);
                                    $precision[1] = intval($param['precision']);
                                    $precision = implode("|", $precision);
                                    $data['precision'] = $precision;
                                }
                            }
                            $data['update_time'] = time();
                            $result = $TimeCard->where($where)->update($data);
                        }
                        $message .= "[".$childinfo['realname']."]";

                    //推送给所有从属家长
                    $jpushId = $Parent->where('id','in',$ids)->where('flag',1)->whereNotNull('jpush_id')->where('jpush_id','neq','')->value('GROUP_CONCAT(jpush_id)');
                    if(!empty($jpushId)){
                        //判断是入园还是出园
                        if($param['isIn'] == 1){
                            $message = "您的小孩".$message."已经入园！";
                        }else{
                            $message = "您的小孩".$message."已经出园！";
                        }
                        $extra = array('viewCode'=>80000,'imgPath'=>config('view_replace_str.__IMGROOT__').$faceImgPath,'recordTime'=>$param['recordTime'],'childName' => $childinfo['realname'],'precision'=>$param['precision']);
                        jpushToId($jpushId, $message,1,$extra);
                    }
                }
            }
            //Db::commit();
            return $this->suc();
        } catch (\Exception $e) {
            //Db::rollback();
            $err = $e->getMessage();
            return $this->err($err);
        }
    }
	/**
	 * 判断是否早退
	 */
	protected function beLateOrLeave($time,$beginTime,$endTime){
		$beginTime = strtotime(date('Y-m-d',$time)." ".$beginTime);
		$endTime = strtotime(date('Y-m-d',$time)." ".$endTime);
		$timeArr = explode('|', $time);
		$bak = array('inStatus'=>1,'outStatus'=>1);
		foreach ($timeArr as $key=>$val){
			//拿第一个判断是否迟到
			if($key == 0){
				if($val > $beginTime){
					$bak['inStatus'] = -1;
				}
			}
			//判断是否早退
			if($key > 0){
				if($val < $endTime){
					$bak['outStatus'] = -1;
				}else{
					$bak['outStatus'] = 1;
				}
			}
		}
		return $bak;
	}
}