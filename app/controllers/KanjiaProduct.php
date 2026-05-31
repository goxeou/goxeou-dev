<?php


// +----------------------------------------------------------------------
// | 积分商城-商品管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class KanjiaProduct extends Common
{
	//商品列表
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
					$where[] = ['bid','<>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
				}else{
					$where[] = ['bid','=',0];
				}
                }else{
				$where[] = ['bid','=',bid];
			}
			if(input('param.name')) $where[] = ['name','like','%'.$_GET['name'].'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];

			$count = 0 + Db::name('kanjia_product')->where($where)->count();
			$data = Db::name('kanjia_product')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				if($v['bid'] > 0){
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['bname'] = '平台自营';
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑商品
	public function edit(){
		if(input('param.id')){
			$info = Db::name('kanjia_product')->where('aid',aid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('商品不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
		}else{
			$info = [];
			$info['starttime'] = time();
			$info['endtime'] = time() + 7*86400;
		}
		$freightdata = array();
		if($info && $info['freightdata']){
			$freightdata = Db::name('freight')->where('aid',aid)->where('id','in',$info['freightdata'])->order('sort desc,id')->select()->toArray();
		}
		$aglevellist = Db::name('member_level')->where('aid',aid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
        $levellist = Db::name('member_level')->where('aid',aid)->order('sort,id')->select()->toArray();

		View::assign('aglevellist',$aglevellist);
		View::assign('levellist',$levellist);
		View::assign('freightdata',$freightdata);
		View::assign('info',$info);
		return View::fetch();
	}
	//保存商品
	public function save(){
		if(input('post.id')){
			$product = Db::name('kanjia_product')->where('aid',aid)->where('id',input('post.id/d'))->find();
			if(!$product) showmsg('商品不存在');
			if(bid != 0 && $product['bid']!=bid) showmsg('无权限操作');
		}
		
		$info = input('post.info/a');
		$info['detail'] = \app\commons\Common::geteditorcontent($info['detail']);
		$data = array();
		$data['name'] = $info['name'];
		$data['pic'] = $info['pic'];
		$data['pics'] = $info['pics'];
		//$data['fuwupoint'] = $info['fuwupoint'];
		//$data['sellpoint'] = $info['sellpoint'];
		//$data['procode'] = $info['procode'];
		$data['sell_price'] = $info['sell_price'];
		$data['min_price'] = $info['min_price'];
		$data['weight'] = $info['weight'];
		$data['stock'] = $info['stock'];
		if($info['oldsales'] != $info['sales']){
			$data['sales'] = $info['sales'];
		}
		$data['starttime'] = strtotime($info['starttime']);
		$data['endtime'] = strtotime($info['endtime']);
		$data['directbuy'] = $info['directbuy'] ? $info['directbuy'] : 0;
		$data['sort'] = $info['sort'];
		$data['detail'] = $info['detail'];
		$data['freighttype'] = $info['freighttype'];
        $data['freightdata'] = $info['freightdata'];
        $data['contact_require'] = intval($info['contact_require']);
		$data['helpgive_type'] = $info['helpgive_type'];
		$data['helpgive_percent'] = $info['helpgive_percent'];
		$data['helpgive_ff'] = $info['helpgive_ff'];
		$data['freightcontent'] = $info['freightcontent'];

		$kjdata = array();
		$postkjdata = input('post.kjdata/a');
		foreach($postkjdata[0] as $k=>$v){
			$tdata = array();
			$tdata['startnum'] = $v;
			$tdata['endnum'] = $postkjdata[1][$k];
			$tdata['startmoney'] = $postkjdata[2][$k];
			$tdata['endmoney'] = $postkjdata[3][$k];
			$kjdata[] = $tdata;
		}
		$data['kjdata'] = json_encode($kjdata);
		if(!$product) $data['createtime'] = time();

		if(bid !=0 ){
			$bset = Db::name('business_sysset')->where('aid',aid)->find();
			if($bset['product_check'] == 1){
				$data['ischecked'] = 0;
			}
		} else {
            $data['feepercent'] = $info['feepercent'] == '' || $info['feepercent'] < 0 ? null : $info['feepercent'];//商品独立抽成费率
        }
		if($product){
			Db::name('kanjia_product')->where('aid',aid)->where('id',$product['id'])->update($data);
			$proid = $product['id'];
			\app\commons\System::plog('编辑砍价商品'.$proid);
		}else{
			$data['aid'] = aid;
			$data['bid'] = bid;
			$proid = Db::name('kanjia_product')->insertGetId($data);
			\app\commons\System::plog('添加砍价商品'.$proid);
		}
        //更新商户虚拟销量
        if($product){
            $bid = $product['bid'];
        }else{
            $bid = $info['bid']?:bid;
        }
        $sales = $info['sales']-$info['oldsales'];
        if($sales!=0){
            \app\models\Payorder::addSales(0,'sales',aid,$bid,$sales);
        }
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		if(bid !=0){
			$where[] = ['bid','=',bid];
		}
		Db::name('kanjia_product')->where($where)->update(['status'=>$st]);

		\app\commons\System::plog('砍价商品改状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		if(bid != 0) showmsg('无权限操作');
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('kanjia_product')->where('aid',aid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		if(bid !=0){
			$where[] = ['bid','=',bid];
		}
		Db::name('kanjia_product')->where($where)->delete();

		\app\commons\System::plog('删除砍价商品'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//参与列表
	public function joinlist(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'kanjia_join.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'kanjia_join.id desc';
			}
			$where = array();
			$where[] = ['kanjia_join.aid','=',aid];
			if(bid != 0){
				$where[] = ['kanjia_join.bid','=',bid];
			}
			if(input('param.proid')){
				$where[] = ['kanjia_join.proid','=',input('param.proid')];
			}
			if(input('?param.status') && input('param.status')!=='') $where[] = ['kanjia_join.status','=',input('param.status')];
			$count = 0 + Db::name('kanjia_join')->alias('kanjia_join')->join('member member','member.id=kanjia_join.mid')->where($where)->count();
			$data = Db::name('kanjia_join')->alias('kanjia_join')->field('member.nickname,member.headimg,kanjia_join.*')->join('member member','member.id=kanjia_join.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
	}
	public function gethelplist(){
		$data = Db::name('kanjia_help')->alias('kanjia_help')->field('member.nickname,member.headimg,kanjia_help.*')->join('member member','member.id=kanjia_help.mid')->where('kanjia_help.aid',aid)->where('kanjia_help.joinid',input('post.id/d'))->order('id desc')->select()->toArray();
		return json(['code'=>0,'msg'=>'查询成功','helplist'=>$data]);
	}
	//删除
	public function joinlistdel(){
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		if(bid !=0){
			$where[] = ['bid','=',bid];
		}
		Db::name('kanjia_join')->where($where)->delete();
		\app\commons\System::plog('砍价参与记录删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

    //选择商品
    public function chooseproduct(){
        //商户
        $blist = Db::name('business')->where('aid',aid)->order('sort desc,id desc')->select()->toArray();
        View::assign('blist',$blist);
        return View::fetch();
    }
    //获取商品信息
    public function getproduct(){
        $proid = input('post.proid/d');
        $product = Db::name('kanjia_product')->where('aid',aid)->where('id',$proid)->find();

        return json($product);
    }
}
