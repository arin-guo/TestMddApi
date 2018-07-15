<?php
namespace app\teacher\controller;

class Index
{
    public function index()
    {
    	$AskLeave = model('AskLeave');$TimeCard = model('TimeCard');$Child = model('Childs');
    	//获取老师所在的班级
    	$where['flag'] = 1;
    	$where['id'] = 1;
    	$where['status'] = 0;
    	$info = $AskLeave->where($where)->find();
//     	$dateList = getCompareDateList(date('Y-m-d',date('Y-m-d',strtotime($info['begin_time']))), date('Y-m-d',strtotime($info['end_time'])));
    	$dateList = getCompareDateList('2017-09-25 12:00:00','2017-09-26 13:00:00');
    	dump($dateList);
    }
}
