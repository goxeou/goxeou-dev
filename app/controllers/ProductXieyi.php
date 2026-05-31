<?php
//custom_file(product_xieyi)
// +----------------------------------------------------------------------
// | 商品协议
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ProductXieyi extends Common
{
    public function initialize(){
        parent::initialize();
    }
	//列表
	public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['bid','>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
				}else{
					$where[] = ['bid','=',0];
				}
			}else{
				$where[] = ['bid','=',bid];
			}
			if(input('param.pid')) $where[] = ['pid','=',input('param.pid/d')];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('product_xieyi')->where($where)->count();
			$data = Db::name('product_xieyi')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		View::assign('bid',bid);
		return View::fetch();
	}
	//编辑协议
	public function edit(){
		if(input('param.id')){
			if(bid == 0){
				$info = Db::name('product_xieyi')->where('aid',aid)->where('id',input('param.id/d'))->find();
			}else{
				$info = Db::name('product_xieyi')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
			}
		}

		if($info['bid'] != 0){
			$needcheck = Db::name('business_sysset')->where('aid',aid)->value('product_xieyi_check');
		}else{
			$needcheck = 0;
		}
		View::assign('info',$info);
		View::assign('needcheck',$needcheck);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$info['content'] = \app\commons\Common::geteditorcontent($info['content']);
		$info['createtime'] = time();
        $info['status'] = 1;
		if($info['id']){
			if(bid != 0){
				$needcheck = Db::name('business_sysset')->where('aid',aid)->value('product_xieyi_check');
				$product_xieyi = Db::name('product_xieyi')->where('aid',aid)->where('id',$info['id'])->find();
				if($needcheck && $product_xieyi['status']!=1){
					$info['status'] = 0;
				}
			}
			Db::name('product_xieyi')->where('aid',aid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog('修改产品协议'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			if(bid != 0){
				$needcheck = Db::name('business_sysset')->where('aid',aid)->value('product_xieyi_check');
				if($needcheck){
					$info['status'] = 0;
				}
			}
			Db::name('product_xieyi')->insert($info);
            \app\commons\System::plog('添加产品协议');
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		if(bid == 0){
			Db::name('product_xieyi')->where('aid',aid)->where('id','in',$ids)->delete();
		}else{
			Db::name('product_xieyi')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		}
		$ids_str = is_array($ids)?implode(',',$ids):$ids;
        \app\commons\System::plog('删除产品协议'.$ids_str);
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//设置状态
	public function setst(){
		$aid = $this->aid;
		$id = input('id');
		$reason = input('reason');
		if(bid == 0){
			Db::name('product_xieyi')->where('aid',aid)->where('id','=',$id)->update(['status'=>input('post.st/d'),'reason'=>$reason]);
		}else{
			Db::name('product_xieyi')->where('aid',aid)->where('bid',bid)->where('id','=',$id)->update(['status'=>input('post.st/d'),'reason'=>$reason]);
		}
        \app\commons\System::plog('修改协议状态'.$id);
		return json(['status'=>1,'msg'=>'操作']);
	}

    public function choosexieyi(){
        if(input('id')){
            $info = Db::name('product_xieyi')->where('id',input('id'))->find();
            return json(['status'=>1,'data'=>$info]);
        }
        return View::fetch();
    }
}