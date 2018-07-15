<?php
namespace app\common\model;
use app\common\model\Base;
/**
 * 亲子游
 * @author chenlisong E-mail:chenlisong1021@163.com 
 * @version 创建时间：2017年9月6日 下午4:32:44 
 * 类说明
 */
class MallOrder extends Base{
	public $rule = [
        'order_sn'        =>'require|unique:mall_order',
        // 'uid'         =>'require|alphaNum',
        // 'type'        =>'number',
        // 'passwd'    =>'min:6|max:16|confirm:confirm_passwd',
        //'confirm_passwd'=>'confirm:passwd',
        //'phone'    =>'regex:/^1[345678]\d{9}/',
    ];
    public $message = [
        'order_sn.require'     => '用户名不能为空',
        'order_sn.unique'        => '已存在',
        // 'type'            => '用户类型必须为数字',
        // 'team_id'        => '所属团队类型必须为数字',
        // 'passwd'        => '两次密码输入不一致'
        //'passwd.min'    => '密码不能小于6位数',
        //'passwd.max'    => '密码不能大于16位数',
    ];

    public function saveOrder($data)
    {        
        if(!$this->validate($this->rule,$this->message)->save($data))
        {
            return false;
        }
        return true;
    }
}