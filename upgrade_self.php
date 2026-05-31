<?php

    
    
  

    
    
function pdo_fieldexists2($tablename, $fieldname){
	$fields = \think\facade\Db::query("SHOW COLUMNS FROM " . $tablename);
	if(empty($fields)){
		return false;
	}
	foreach ($fields as $field) {
		if ($fieldname == $field['Field']){
			return true;
		}
	}
	return false;
}

//检查表是否存在
function pdo_fieldexists3($tablename){
	$table = \think\facade\Db::query("SHOW TABLES LIKE '". $tablename."'");
	if(empty($table)){
		return false;
	}else{
		return true;
	}
}

//检查索引是否存在
function pdo_indexExists($tablename, $indexname){
    $table = \think\facade\Db::query("SHOW INDEX FROM " . $tablename. " WHERE key_name = '" .$indexname. "'");
    if(empty($table)){
        return false;
    }else{
        return true;
    }
}