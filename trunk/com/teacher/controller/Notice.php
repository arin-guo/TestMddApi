<?php
namespace app\teacher\controller;
use app\teacher\controller\Base;
use think\Db;
/**
 * 消息管理
 * @author chenlisong E-mail:chenlisong1021@163.com 
 * @version 创建时间：2017年10月27日 下午2:53:19 
 * 类说明
 */
class Notice extends Base{
	
	/**
	 * 获取通讯录列表
	 * 老师默认获得所有学生家长以及同事的手机
	 */
	public function getMailtelList(){
		$Teacher = model('Teachers');$Parent = model('Parents');$Child = model('Childs');
		$param = $this->param;
		$info = $Teacher->where('id',$param['userId'])->find();
		//获取班级学生家长的联系方式
		$classes_id = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',1)->value('classes_id');
        $classes_idr = Db::name('TeacherClass')->where('teacher_id',$param['userId'])->where('flag',1)->where('teacher_type',3)->value('classes_id');
		if(empty($classes_id) && empty($classes_idr)){
			return $this->err('您还未被分配班级，如有疑问，请联系园长！');
		}else{
		    if(empty($classes_id)){
		        $classes_id = $classes_idr;
            }
        }
		$field = 'realname as name,tel';
		$ids = $Child->where('status',1)->where('flag',1)->where('classes_id',$classes_id)->value('GROUP_CONCAT(id)');
		$data['parentsList'] = [];
		if(!empty($ids)){
			$ids2 = Db::name('ParentChild')->where('flag',1)->where('child_id','in',$ids)->value('GROUP_CONCAT(parent_id)');
			if(!empty($ids2)){
				$data['parentsList'] = $Parent->where('flag',1)->where('status',1)->where('type',1)->where('id','in',$ids2)->field($field)->select();
			}
		}
		//获取同事的联系方式
		$data['teacherList'] = $Teacher->where('flag',1)->where('school_id',$info['school_id'])->where('is_job',1)->field($field)->select();
		return $this->suc($data,'',config('view_replace_str.__IMGROOT__'));
	}
	
	/**
	 * 获取公告列表
	 */
	public function getNoticeList(){
		$Notice = model('Notice');$Teacher = model('Teachers');
		$param = $this->param;
		if(is_null($param['nextStartId'])){
			return $this->err('参数错误！');
		}
		$info = $Teacher->where('id',$param['userId'])->find();
		$where['flag'] = 1;
		$where['school_id'] = $info['school_id'];
		$where['allow_persons'] = array('LIKE', '%'.$info['tel'].'%');
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

	/**ji add
	 * 创建公告
	 */
	public function createNotice(){
		$Notice = model('Notice');$Teacher = model('Teachers');
		$param = $this->param;
		if(empty($param['title']) || empty($param['content'])){
			return $this->err('参数错误！');
		}
		$info = $Teacher->where('id',$param['userId'])->find();
		$data['school_id'] = $info['school_id'];
		$data['author'] = $info['realname'];
		$data['title'] = $param['title'];
		$data['content'] = $param['content'];
		$data['allow_persons'] = $param['allowPersons'] ? $param['allowPersons'] . ',' . $Teacher->where('id',$param['userId'])->value('tel') : $Teacher->where('id',$param['userId'])->value('tel');
		$result = $Notice->isUpdate(false)->save($data);
		if($result){
			//统计本校家长端与教师端
			Db::name('Parents')->where('flag',1)->where('school_id',$info['school_id'])->where('status',1)->where('tel','IN',[$param['allowPersons']])->where('jpush_id','neq','')->chunk(100,function($models){
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
			});
			return $this->suc();
		}else{
			return $this->err('系统繁忙！');
		}
	}
	
	/**
	 * 园所风采
	 */
	public function getNewsList(){
		$News = model('News');$Teacher = model('Teachers');
		$param = $this->param;
		if(is_null($param['nextStartId']) || !in_array($param['type'], array(1,2))){
			return $this->err('参数错误！');
		}
		$info = $Teacher->where('id',$param['userId'])->find();
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
	 * ji add
	 * 老师默认获得学生家长手机
	 */
	public function getParentsList(){
		$Parent = model('Parents');$Teacher = model('Teachers');
		$param = $this->param;
		$info = $Teacher->where('id',$param['userId'])->find();
		$cids = model('TeacherClass')->where('teacher_id',$info['id'])->value('GROUP_CONCAT(classes_id)');
		$field = 'id,photo,realname as name,tel';
		$data = $Parent->where('id','IN',model('ParentChild')->where('child_id','IN',model('Childs')->where('classes_id','IN',$cids)->value('GROUP_CONCAT(id)'))->value('GROUP_CONCAT(parent_id)'))->where('flag',1)->where('status',1)->field($field)->select();
		foreach($data as $key=>$val)
		{
			if(empty($val['name'])) $data[$key]['name'] = '未命名';
		}
		return $this->suc($data,'',config('view_replace_str.__IMGROOT__'));
	}
}