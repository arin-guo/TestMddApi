<?php
namespace app\communal\controller;
use app\communal\controller\Base;
use think\Db;
class Cookbook extends Base{
    /**
     * 判断一周是否有菜谱
     */
    public function hasCookbooks(){
        $gettime = input('date');
        if(empty($gettime)){
            return $this->err('没有传入日期!');
        }
        $schoolId = input('schoolId');
        if(empty($schoolId)){
            return $this->err('没有传入学校id!');
        }
        $model = model('CookbookDate');
        $time = strtotime($gettime);
        $w = date('w',$time);
        $begin = $time - 3600*24*$w;
        $end = $begin + 3600*24*6;
        //获取选中周的菜谱信息
        $result = $model->where('school_id',$schoolId)->where('flag',1)->where('day_time','between time',[date('Y-m-d',$begin),date('Y-m-d',$end)])->field('id,day_time')->select();
        $weekday = array();
        for ($i = 0;$i < 7;$i++){
            $weekday[$i] = date('Y-m-d',$begin+3600*24*$i);
        }
        foreach($weekday as $key=>$val){
            $data[$key]['date'] = $val;
            $data[$key]['hascook'] = 2;
            foreach ($result as $k=>$v){
                if ($v['day_time'] == $val){
                    $data[$key]['hascook'] = 1;
                }
            }
        }
        //return var_dump($result);
        return $this->suc($data);
    }
}