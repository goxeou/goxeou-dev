<?php


// +----------------------------------------------------------------------
// | 小票打印机
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Wifiprint extends Common
{
	//列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			$where[] = ['bid','=',bid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			$count = 0 + Db::name('wifiprint_set')->where($where)->count();
			$data = Db::name('wifiprint_set')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('wifiprint_set')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
		}else{
			$shopname = Db::name('admin_set')->where('aid',aid)->value('name');
			$info = array('id'=>'','name'=>'小票打印机','title'=>$shopname,'voice'=>2);
		}
		$mendianArr = Db::name('mendian')->where('aid',aid)->where('bid',bid)->order('id')->column('name','id');
		View::assign('mendianArr',$mendianArr);
		View::assign('info',$info);
        $client_id = $info['client_id']?$info['client_id']:'';
		$pushurl = $_SERVER['SERVER_NAME'].'/?s=/ApiYilianyunNotify/notify/client_id/'.$client_id;
        View::assign('pushurl',$pushurl);
		return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
		$info['print_zt_mdid'] = implode(',',$info['print_zt_mdid']);
		if(!$info['access_token']){
			$url = 'https://open-api.10ss.net/oauth/oauth';
			$data = [];
			$data['client_id'] = $info['client_id']; //应用ID
			$data['grant_type'] = 'client_credentials';
			$data['scope'] = 'all';
			$data['timestamp'] = time();
			$data['id'] = \app\commons\Wifiprint::uuid4();
			$data['sign'] = md5($data['client_id'].$data['timestamp'].$info['client_secret']);
			$rs = request_post($url,$data);
			$rs = json_decode($rs,true);
			if($rs && $rs['error']==0){
				$info['access_token'] = $rs['body']['access_token'];
				Db::name('wifiprint_set')->where('client_id',$info['client_id'])->update(['access_token'=>$info['access_token']]);
			}
		}
		if($info['id']){
			Db::name('wifiprint_set')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('编辑小票打印机'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['createtime'] = time();
			$id = Db::name('wifiprint_set')->insertGetId($info);
			\app\commons\System::plog('添加小票打印机'.$id);
		}
		//音量
        if($info['access_token'] && $info['client_id'] && $info['machine_code'] && $info['client_secret']){
            $url = 'https://open-api.10ss.net/printer/setsound';
            $data = [];
            $data['client_id'] = $info['client_id']; //应用ID
            $data['access_token'] = $info['access_token'];
            $data['machine_code'] = $info['machine_code'];
            $data['response_type'] = $info['voice_type'] ? $info['voice_type'] : 'horn';
            $data['voice'] = $info['voice'];
            $data['timestamp'] = time();
            $data['id'] = \app\commons\Wifiprint::uuid4();
            $data['sign'] = md5($data['client_id'].$data['timestamp'].$info['client_secret']);
            $rs = request_post($url,$data);
            $rs = json_decode($rs,true);
            if($rs && $rs['error']==0){

            }
        }

		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//编辑
	public function edit2(){
		if(input('param.id')){
			$info = Db::name('wifiprint_set')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$shopname = Db::name('admin_set')->where('aid',aid)->value('name');
			$info = array('id'=>'','name'=>'小票打印机','title'=>$shopname);
		}
		$mendianArr = Db::name('mendian')->where('aid',aid)->where('bid',bid)->order('id')->column('name','id');
		View::assign('mendianArr',$mendianArr);
		View::assign('info',$info);
		return View::fetch();
	}
	public function save2(){
		$info = input('post.info/a');
		$info['print_zt_mdid'] = implode(',',$info['print_zt_mdid']);
		
		$postdata = [];
		$postdata['user'] = $info['client_id'];
		$postdata['stime'] = time();
		$postdata['sig'] = sha1($info['client_id'].$info['client_secret'].$postdata['stime']);
		$postdata['apiname'] = 'Open_printerAddlist';
		$postdata['printerContent'] = $info['machine_code'].'#'.$info['msign'].'#'.$info['name'];
		$rs = request_post('http://api.feieyun.cn/Api/Open/',$postdata);
		if($info['id']){
            $machine = Db::name('wifiprint_set')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->find();
			Db::name('wifiprint_set')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
            //标签打印机修改尺寸后需打印一次 打印机保存设置
            if($info['machine_type']==1 && ($machine['width'] != $info['width'] || $machine['height'] != $info['height'])){
                $machine = Db::name('wifiprint_set')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->find();
                $rs = $this->printTextFeie($machine);
            }
			\app\commons\System::plog('编辑小票打印机'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			$info['type'] = 1;
			$info['createtime'] = time();
			$id = Db::name('wifiprint_set')->insertGetId($info);
			\app\commons\System::plog('添加小票打印机'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
    //编辑
    public function edit3(){
        }
    public function save3(){
        }

    public function edit4(){
        }
    public function save4(){
        }
    public function edit5(){
        }
    public function save5(){
        }
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('wifiprint_set')->where('aid',aid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\commons\System::plog('小票打印机改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		Db::name('wifiprint_set')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('小票打印机删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//打印测试
	public function printtest(){
		$id = input('post.id/d');
		$machine = Db::name('wifiprint_set')->where('aid',aid)->where('id',$id)->find();
		if(!$machine){
			return json(['status'=>0,'error'=>1,'msg'=>'未找到打印机']);
		}
		if($machine['type']==0){
			$content = "<MS>1,".$machine['voice']."</MS>";
            $content .= '<SIZE>'.$machine['width'].','.$machine['height'].'</SIZE>';
			$content .= "<center>@@2 ** ".$machine['title']." **</center>\r\r";
			$content .= "测试打印，看到该内容表示打印机配置成功\r";
			$rs = \app\commons\Wifiprint::yilianyun_print($machine['client_id'],$machine['client_secret'],$machine['access_token'],$machine['machine_code'],$machine['msign'],$content);
			return json($rs);
		}elseif($machine['type']==1){
			$rs = $this->printTextFeie($machine);
			return json($rs);
		}elseif($machine['type']==3){
            }elseif($machine['type']==4){
            }
		return json(['status'=>0,'msg'=>'未找到打印机类型']);
	}

    public function printTextFeie($machine){
        if($machine['machine_type']==1){
            $content = '<SIZE>'.$machine['width'].','.$machine['height'].'</SIZE>';
            $content .= '<TEXT x="9" y="10" font="12" w="1" h="2" r="0">#001       五号桌      1/3</TEXT><TEXT x="80" y="80" font="12" w="2" h="2" r="0">可乐鸡翅</TEXT><TEXT x="9" y="180" font="12" w="1" h="1" r="0">张三先生       13800138000</TEXT>';
        }else{
            $content = '';
            $content .= "<CB>** ".$machine['title']." **</CB><BR><BR>";
            $content .= "测试打印，看到该内容表示打印机配置成功<BR>";
        }
        $rs = \app\commons\Wifiprint::feie_print($machine['client_id'],$machine['client_secret'],$machine['machine_code'],$machine['msign'],$content,$machine['machine_type']);
        return $rs;
    }
}
