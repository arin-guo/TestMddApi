<?php
namespace app\communal\controller;
use app\communal\controller\Base;
use think\Db;
class System extends Base{
    /**
     * form上传图片
     */
    public function formUploadImg(){
        $param = $this->param;
        if (empty($param['userType']) || empty($param['type'])){
            return $this->err('参数缺失！');
        }
        switch ($param['userType']){
            case 1:
                $type = 'parent';
                break;
            case 2:
                $type = 'teacher';
                break;
            case 3:
                $type = 'leader';
                break;
        }
        $file = request()->file('image');
        if(empty($file)){
            return $this->err('image为空！');
        }
        $valid['size'] = 8388608;//8m
        $valid['ext'] = 'jpg,png,gif';
        //验证规则+传图
        $path = config('app_upload_path')."/uploads/".$type."/".input('type')."/";
        $info = $file->validate($valid)->rule('date')->move($path);
        if ($info){
            $file_path = ltrim($path,config('app_upload_path'));
            $backData['url'] = $file_path.$info->getSaveName();
            return $this->suc($backData,'',config('view_replace_str.__IMGROOT__'));
        }else{
            $backData = $file->getError();
            return $this->err($backData);
        }
    }

}