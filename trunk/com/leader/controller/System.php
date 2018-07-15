<?php
namespace app\leader\controller;
use app\leader\controller\Base;
use think\Db;
use sendsms\Sendsms;
use OSS\OssClient;
class System extends Base{
	
	/**
	 * 获取banner列表
	 */
	public function getBannerList(){
		$Leader = model('Headmasters');$Banner = model('Banners');
		$param = $this->param;
		if(empty($param['userId'])){
			return $this->err('参数错误！');
		}
		$schoolId = $Leader->where('id',$param['userId'])->value('school_id');
		$where['flag'] = 1;
		$where['is_on'] = 1;
		$where['school_id'] = $schoolId;
		$field = 'id,title,photo,type,url';
		$data = $Banner->where($where)->field($field)->order('seq asc')->select();
/*ji add*/		foreach($data as $k=>$v){$data[$k]['url'] = $v['url'] . "/uid/" . $param['userId'] . "/type/3";}
		return $this->suc($data,'',config('view_replace_str.__IMGROOT__'));
	}

	/**
	 * 获取发现banner列表
	 */
	public function getFindBannerList(){
		$Leader = model('Headmasters');$Banner = model('Banners');
		$param = $this->param;
		if(empty($param['userId'])){
			return $this->err('参数错误！');
		}
		$schoolId = $Leader->where('id',$param['userId'])->value('school_id');
		$where['flag'] = 1;
		$where['is_on'] = 3;
		$where['school_id'] = $schoolId;
		$field = 'id,title,photo,type,url';
		$map = explode(',',model('Schools')->where('id',$schoolId)->value('place_code'))[0] . ',' . explode(',',model('Schools')->where('id',$schoolId)->value('place_code'))[1];
		if(model('ChildSchoolAlbum')->where('place_code',$map)->find()) $data = $Banner->where($where)->field($field)->order('seq asc')->select();
		if(empty($data) && model('ChildSchoolAlbum')->where('place_code',$map)->find()) $data = $Banner->where('remark','毕业照'.model('ChildSchoolAlbum')->value('id'))->where('flag',1)->field($field)->order('seq asc')->select();
		foreach($data as $k=>$v){$data[$k]['url'] = $v['url'] . "/uid/" . $param['userId'] . "/type/3";}
		return $this->suc($data,'',config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 发送短信
	 */
	public function sendMsg(){
		$param = $this->param;
		if(!preg_match("/1[34578]{1}\d{9}$/",$param['tel'])){
			return $this->err("错误的手机号！");
		}
		$send = array();
		if(cache('app_send_msg_'.$param['tel']) != false){
			$send = cache('app_send_msg_'.$param['tel']);
		}
		$send['send_code'] = rand(1001,9999);
		if(($send['send_time'] + 60) > time() && !empty($send['send_time'])) {//将获取的缓存时间转换成时间戳加上60秒后与当前时间比较，小于当前时间为频繁获取
			return $this->err("请勿频繁获取验证码！");
		}else{
			$send['send_time'] = time();
			$send['send_tel'] = $param['tel'];
			cache('app_send_msg_'.$param['tel'],$send,600);
			$sendSms = new Sendsms(config('app_sendmsg_key'), config('app_sendmsg_secret'));
			//3077101为短信模版
			$result = $sendSms->sendSMSTemplate('3077101',array($param['tel']),array($send['send_code']));
			if($result['code'] == '200'){
				//判断通道商回执是否发送成功
				$backData['msg'] = '发送成功!';
				return $this->suc($backData);
			}else{
				return $this->err('发送失败！');
			}
		}
	}

	
	/**
	 * 客户端版本更新
	 * Enter description here ...
	 */
	public function updateClient(){
		$AppVersion = model('AppVersion');
		$param = $this->param;
		$info = $AppVersion->where('flag',1)->where('status',1)->where('use_type',3)->where('type',$param['type'])->find();
		$version = $param['ver'];
		$newVersion = $info['version'];
		$result = $this->compareVersion($version, $newVersion);
		if($result['needUpdate'] == 1){//必须更新
			$result['updateInfo'] = array($info['title'],$info['desc']);
			$result['versionName'] = $newVersion;
			if($param['type'] == 1){//android
				$result['updateUrl'] = $info['url'];
			}
		}
		return $this->suc($result,'',config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 *  版本号比较
	 *  @param $v1 新版本号
	 *  @param $v2 旧版本号
	 *  return boolean
	 */
	private function compareVersion($version,$newVersion){
		$result = array('needUpdate'=>0,'mustUpdate'=>0);
		$param1  = explode('.',$version);
		$param2  = explode('.',$newVersion);
		if($param1[0] == $param2[0] && $param1[1] == $param2[1]){//第一位和第二位只要不同强制更新
			if($param1[2] != $param2[2]){//第三位不同，选择更新
				$result['needUpdate'] = 1;
				$result['mustUpdate'] = 0;
			}
		}else{
			$result['needUpdate'] = 1;
			$result['mustUpdate'] = 1;
		}
		//如果当前版本号大于等于系统版本号，则不更新
		if(version_compare($version, $newVersion,'>=')){
			$result['needUpdate'] = 0;
			$result['mustUpdate'] = 0;
		}
		return $result;
	}
	
	/**
	 * 上传图片
	 */
	public function uploadImg(){
		$param = $this->param;
		$img = $param['imgPath'];
		if(empty($img)){
			return $this->err('参数错误！');
		}
		//匹配出图片的格式
		if(preg_match('/^(data:\s*image\/(\w+);base64,)/', $img, $result)){
			$type = $result[2];
			$path = config('app_upload_path')."/uploads/leader/".input('type')."/".date('Ymd')."/";
			if(!file_exists($path)){
				//检查是否有该文件夹，如果没有就创建，并给予最高权限
				mkdir($path, 0777, true);
			}
			$new_file = $path.(microtime(true)*10000).".".$type;
			if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $img)))){
				$file_path = ltrim($new_file,config('app_upload_path'));
				if($result !== false){
					$backData['url'] = $file_path;
					return $this->suc($backData,'',config('view_replace_str.__IMGROOT__'));
				}else{
					return $this->err('服务器繁忙！');
				}
			}else{
				return $this->err('上传失败！');
			}
		}else{
			return $this->err('上传失败！');
		}
	}

	/**ji add
	 * 上传文件..:视频
	 */
	/*public function uploadFiles(){
		$param = $this->param;*/
		/*// $img = $param['imgPath'];
		// if(empty($img)){
		// 	return $this->err('参数错误！');
		// }
		$file = request()->file('image');
        $valid['size'] = 1160971520;//2M
        $valid['ext'] = 'mp4,jpg,png';
        $path = config('app_upload_path').'/uploads/leader/';
        $info = $file->validate($valid)->rule('date')->move($path);
        if($info)
    	{
    		$backData['url'] = $info->getSaveName();
			return $this->suc($backData,'',*//*config('view_replace_str.__IMGROOT__')*//*'http://test.upload.mengdd.net');
    	}
    	else
    	{
			return $this->err('上传失败！');
		}*/
		/*$bucketName = config('OSS_TEST_BUCKET');
        $ossClient = new OssClient(config('OSS_ACCESS_ID'), config('OSS_ACCESS_KEY'), config('OSS_ENDPOINT'), false);
        $web=config('OSS_WEB_SITE');
        $filecs = $_FILES['image'];
        $resultget = ossUpPic($filecs,'hsee',$ossClient,$bucketName,$web,0);*/
        /*$Headmasters = model('Headmasters');$FriendCircle = model('FriendCircle');
		$info = $Headmasters->where('id',$param['userId'])->where('flag',1)->find();
		$data['school_id'] = $info['school_id'];$data['class_id'] = 0;$data['teacher_id'] = 0;
		$data['content'] = ',,,';$data['vedio_url'] = $resultget['msg'];if($param['type']) $data['type'] = $param['type'];
		$data['up_num'] = 0;
		$result = $FriendCircle->isUpdate(false)->save($data);*/
        /*if($resultget)
    	{return $this->suc(array('url'=>$resultget['msg']));}
    	else
    	{return $this->err('上传失败！');
		}
	}*/
	public function uploadFiles(){
        $param = $this->param;
        $file = request()->file('image');
        if(empty($file)){
            return $this->err('image为空！');
        }
        $valid['size'] = 8388608;//8m
        $valid['ext'] = 'mp4,jpg,png';
        //验证规则+传图
        $path = config('app_upload_path')."/uploads/mp4/";
        $info = $file->validate($valid)->rule('date')->move($path);
        if ($info){
            $file_path = ltrim($path,config('app_upload_path'));
            $backData['url'] = config('view_replace_str.__IMGROOT__') . $file_path.$info->getSaveName();
            return $this->suc($backData,'',config('view_replace_str.__IMGROOT__'));
        }else{
            $backData = $file->getError();
            return $this->err($backData);
        }
    }
    /**
     * 上传log到oss
     */
    public function uploadLog(){
        $param = $this->param;
        $bucketName = config('OSS_TEST_BUCKET');
        $ossClient = new OssClient(config('OSS_ACCESS_ID'), config('OSS_ACCESS_KEY'), config('OSS_ENDPOINT'), false);
        $web=config('OSS_WEB_SITE');
        $filecs = $_FILES['log'];
        $resultget = ossUpLog($filecs,'log/'.date('Ymd',time()),$ossClient,$bucketName,$web,0);
        if($resultget)
        {return $this->suc(array('url'=>$resultget['msg']));}
        else
        {return $this->err('上传失败！');
        }
    }
	/**
	 * 获取静态页
	 */
	public function getStaticPage(){
		$param = $this->param;
		if(!in_array($param['type'], array('about_us','about_school'))){
			return $this->err('参数错误！');
		}
		switch ($param['type']){
			case "about_us":
				$data['appCustomTel'] = getFields('app_custom_tel');
				$data['appWechatNo'] = getFields('app_wechat_no');
				$data['appWebsiteUrl'] = getFields('app_website_url');
				break;
			case "about_school":
				$School = model('Schools');
				$sId = Db::name('Headmasters')->where('id',$param['userId'])->value('school_id');
				$data = $School->where('id',$sId)->field('name,logo,desc,website,address,custome_tel,recruit_tel')->find();
				$data['name'] = !empty($data['name']) ? $data['name'] : '';$data['logo'] = !empty($data['logo']) ? $data['logo'] : '';$data['desc'] = !empty($data['desc']) ? $data['desc'] : '';$data['website'] = !empty($data['website']) ? $data['website'] : '';$data['address'] = !empty($data['address']) ? $data['address'] : '';$data['custome_tel'] = !empty($data['custome_tel']) ? $data['custome_tel'] : '';$data['recruit_tel'] = !empty($data['recruit_tel']) ? $data['recruit_tel'] : '';
				break;
		}
		return $this->suc($data,'',config('view_replace_str.__IMGROOT__'));
	}

	/**ji add
	 * @authority 修改学校详情
	 */
	public function eidtAboutSchool(){
		$param = $this->param;
		$data['name'] = $param['name'];$data['logo'] = $param['logo'];
		$data['website'] = $param['website'];$data['address'] = $param['address'];
		$data['custome_tel'] = $param['customeTel'];$data['desc'] = $param['desc'];
		$sid = model('Headmasters')->find($param['userId'])['school_id'];
		$result = model('Schools')->isUpdate(true)->save($data,['id'=>$sid]);
		if($result !== false){
			return $this->suc();
		}else{
			return $this->err("系统繁忙！");
		}
	}

	//压力测试
    public function saveparents(){
	    $param = $this->param;
	    if(empty($param['end'])){
	        return $this->err('缺少参数');
        }
	    Db::startTrans();
	    try{
            for ($i = 0;$i < $param['end'];$i++){
                $data['password'] = md5(000000);
                $data['tel'] = 19012341000 + $i;
                $data['realname'] = '批量测试'.$i;
                $data['unique_code'] = 400 + $i;
                $data['school_id'] = 1;
                $data['status'] = 1;
                $data['type'] = 1;//主家长
                $data['is_main_pick'] = 1;
                $data['parent_id'] = 0;
                $pid = Db::name('parents')->insertGetId($data);
                $cdata['classes_id'] = 1;
                $cdata['school_id'] = 1;
                $cdata['realname'] = '测试'.$i.'的孩子';
                $cdata['status'] = 1;
                $cdata['unique_code'] = $data['unique_code'];
                $cid = Db::name('childs')->insertGetId($cdata);
                $pcdata['relation'] = '关系'.$i;
                $pcdata['parent_id'] = $pid;
                $pcdata['child_id'] = $cid;
                Db::name('parent_child')->insert($pcdata);
            }
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            return $e->getMessage();
        }
    }
}