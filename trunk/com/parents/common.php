<?php

/**
 * 生成3位不含4的随机码
 */
function buildUniqueCode($schoolId){
	$Parents = model('Parents');
	$array = ['1','2','3','5','6','7','8','9'];
	$code = "";
	do{
		$k = rand(0,9);
		$code .= $array[$k];
	}while (strlen($code) < 3);
	$count = $Parents->where('flag',1)->where('school_id',$schoolId)->where('unique_code',$code)->count();
	if($count != 0){
		buildUniqueCode($schoolId);
	}
	return $code;
}

/**
 * 生成3位不含4的随机码
 */
function buildTakeUniqueCode($schoolId){
	$TempTakes = model('TempTakes');
	$array = ['1','2','3','5','6','7','8','9'];
	$code = "#";
	do{
		$k = rand(0,9);
		$code .= $array[$k];
	}while (strlen($code) < 3);
	//当日有效
	$count = $TempTakes->where('flag',1)->whereTime('take_time', 'today')->where('school_id',$schoolId)->where('take_unique_code',$code)->count();
	if($count != 0){
		buildTakeUniqueCode($schoolId);
	}
	return $code;
}

