<?php
// +----------------------------------------------------------------------
// | 注册表单自定义 | custom_file(register_fields)
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class RegisterForm extends Common
{
    public function initialize(){
        parent::initialize();
        if(!getcustom('register_fields')) showmsg('无访问权限');
        if(bid > 0) showmsg('无访问权限');
    }
	//表单列表
	public function index(){
        $info = Db::name('register_form')->where('aid',aid)->find();
        if(empty($info)){
            $info = [
                'id'=>'',
                'content' => []
            ];
        }
        View::assign('info',$info);
        return View::fetch();
	}

	//保存
	public function save(){
		$info = input('post.info/a');
		$datatype = input('post.datatype/a');
		$dataval1 = input('post.dataval1/a');
		$dataval2 = input('post.dataval2/a');
		$dataval3 = input('post.dataval3/a');
		$dataval4 = input('post.dataval4/a');
		$dataval5 = input('post.dataval5/a');
		$dataval_query = input('post.dataval_query/a');
		$dhdata = array();
		foreach($datatype as $k=>$v){
			if($dataval3[$k]!=1) $dataval3[$k] = 0;
			$dhdata[] = [
			'key'=>$v,
			'val1'=>$dataval1[$k],
			'val2'=>$dataval2[$k],
			'val3'=>$dataval3[$k],
			'val4'=>$dataval4[$k],
			'val5'=>($dataval5 ? $dataval5[$k] : ''),
			'query'=>($dataval_query[$k] ? $dataval_query[$k] : '0'),
			];
		}
		$info['content'] = $dhdata?json_encode($dhdata,JSON_UNESCAPED_UNICODE):'';
		if($info['id']){
            Db::name('register_form')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑自定义注册表单'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('register_form')->insertGetId($info);
			\app\commons\System::plog('添加自定义注册表单'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
        Db::name('register_form')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除自定义注册表单'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}