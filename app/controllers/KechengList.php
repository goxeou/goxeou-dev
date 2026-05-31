<?php


// +----------------------------------------------------------------------
// | 知识付费-课程管理 author:lmy 
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class KechengList extends Common
{
	//课程列表
    public function index(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id desc';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(getcustom('kecheng_lecturer')){
				$chaptertype = input('?param.chaptertype')?input('param.chaptertype'):'';
				if(!$chaptertype && $chaptertype != 'all'){
					//是否关联章节 1：关联章节 2：不关联章节（不关联章节默认创建一个章节）
					$where[] = ['chaptertype','=',1];
				}
			}
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
            $cid = input('param.cid/d');
			if($cid) {
                //查询是否包含子类
                $clist = Db::name('kecheng_category')->where('aid',aid)->where('pid',$cid)->column('id');
                if($clist){
                    $clist[] = $cid;
                    $where[] = ['cid','in',$clist];
                } else {
                    $where[] = ['cid','=',$cid];
                }
            }
			$count = 0 + Db::name('kecheng_list')->where($where)->count();
			$data = Db::name('kecheng_list')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			$clist = Db::name('kecheng_category')->where('aid',aid)->select()->toArray();
			$cdata = array();

			foreach($clist as $c){
				$cdata[$c['id']] = $c['name'];
			}
			foreach($data as $k=>$v){
			    $v['cid'] = explode(',',$v['cid']);
				$data[$k]['cname'] = null;
                if ($v['cid']) {
                    foreach ($v['cid'] as $cid) {
                        if($data[$k]['cname'])
                            $data[$k]['cname'] .= ' ' . $cdata[$cid];
                        else
                            $data[$k]['cname'] .= $cdata[$cid];
                    }
                }
				if($v['bid'] > 0){
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['bname'] = '平台自营';
				}
				$data[$k]['chapternum'] = Db::name('kecheng_chapter')->where('kcid',$v['id'])->where('status',1)->count();
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//分类
		$clist = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);
        $this->defaultSet();
		return View::fetch();
    }
	//编辑商品
	public function edit(){
		if(input('param.id')){
			$info = Db::name('kecheng_list')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
			if(!$info) showmsg('课程不存在');
			if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
			$bid = $info['bid'];
		}else{
			$bid = bid;
		}
		/*
		if(false){}else{
			$pclist = [];
		}
		*/
		//分类
		$clist = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->where('bid',$bid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$child = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
			foreach($child as $k2=>$v2){
				$child2 = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
				$child[$k2]['child'] = $child2;
			}
			$clist[$k]['child'] = $child;
		}
        $info['cid'] = explode(',',$info['cid']);
        $info['pcid'] = explode(',',$info['pcid']);
		if(false){}else {
            $aglevellist = Db::name('member_level')->where('aid',aid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
            $levellist = Db::name('member_level')->where('aid',aid)->order('sort,id')->select()->toArray();
        }
		$default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
        $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        $info['lvprice_data'] = json_decode($info['lvprice_data'], true);
        View::assign('levellist',$levellist);
		View::assign('aglevellist',$aglevellist);
		//View::assign('pclist',$pclist);
		View::assign('clist',$clist);
		View::assign('info',$info);

		
		return View::fetch();
	}
	//保存课程
	public function save(){
		if(input('post.id')){
			$product = Db::name('kecheng_list')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
			if(!$product) showmsg('课程不存在');
			if(bid != 0 && $product['bid']!=bid) showmsg('无权限操作');
		}
		$info = input('post.info/a');
		$info['detail'] = \app\commons\Common::geteditorcontent($info['detail']);
		$data = array();
		$data['name'] = $info['name'];
		$data['pic'] = $info['pic'];
		$data['pics'] = $info['pics'];
		$data['cid'] = $info['cid'];
		$data['pcid'] = $info['pcid'];
		$data['kctype'] = $info['kctype'];//课程类型
		if(!$data['pcid']) $data['pcid'] = '0';
		if(isset($info['detail_text'])){
			$data['detail_text'] = $info['detail_text'];
		}
		if(isset($info['detail_pics'])){
			$data['detail_pics'] = $info['detail_pics'];
		}
		$data['sort'] = $info['sort'];
		$data['status'] = $info['status'];
		$data['detail'] = $info['detail'];
		if(!$product) $data['createtime'] = time();
		$data['price'] = $info['price'];
		$data['market_price'] = $info['market_price'];
		$data['isdt'] = $info['isdt'];
		$data['sxdate'] = $info['sxdate'];
		$data['dtnum'] = $info['dtnum'];
		$data['hgscore'] = $info['hgscore'];
		$data['join_num'] = $info['join_num']>0 ? $info['join_num'] : 0;

        $data['lvprice'] = $info['lvprice'];
        if($info['lvprice']==1){
            $data['lvprice_data'] = jsonEncode($info['lvprice_data']);
			$data['price'] = array_values($info['lvprice_data'])[0]['money_price'];
        }

		$data['commissionset'] = $info['commissionset'];
		$data['commissiondata1'] = jsonEncode(input('post.commissiondata1/a'));
		$data['commissiondata2'] = jsonEncode(input('post.commissiondata2/a'));
		$data['commissiondata3'] = jsonEncode(input('post.commissiondata3/a'));

		if($product){
			Db::name('kecheng_list')->where('aid',aid)->where('id',$product['id'])->update($data);
			$proid = $product['id'];
			\app\commons\System::plog('课程内容编辑'.$proid);
		}else{
			$data['aid'] = aid;
			$data['bid'] = bid;
			$proid = Db::name('kecheng_list')->insertGetId($data);
			\app\commons\System::plog('课程内容编辑'.$proid);
		}
		$old_sales = 0;
        if($product){
            $bid = $product['bid'];
            $old_sales = $product['join_num'];
        }else{
            $bid = $info['bid']?:bid;
        }
        //更新商户虚拟销量
        $sales = $info['join_num']-$old_sales;
        if($sales!=0){
            \app\models\Payorder::addSales(0,'sales',aid,$bid,$sales);
        }
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//修改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		if(bid !=0){
			$where[] = ['bid','=',bid];
		}
		Db::name('kecheng_list')->where($where)->update(['status'=>$st]);
		\app\commons\System::plog('课程内容编辑'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('kecheng_list')->where('aid',aid)->where('id',$id)->update(['ischecked'=>$st,'check_reason'=>$reason]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	
	//删除
	public function del(){
		$ids = input('post.ids/a');
		if(!$ids) $ids = array(input('post.id/d'));
		$where = [];
		$where[] = ['aid','=',aid];
		$where[] = ['id','in',$ids];
		if(bid !=0){
			$where[] = ['bid','=',bid];
		}
		$prolist = Db::name('kecheng_list')->where($where)->select();
		foreach($prolist as $pro){
			$chapter = Db::name('kecheng_list')->where($where)->select();

			Db::name('kecheng_list')->where('id',$pro['id'])->delete();
		
		}
		\app\commons\System::plog('课程删除'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	
	//选择商品
	public function chooseproduct(){
		//分类
		$clist = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		//商户
		$blist = Db::name('business')->where('aid',aid)->order('sort desc,id desc')->select()->toArray();
		View::assign('blist',$blist);
		View::assign('clist',$clist);
		return View::fetch();
	}
	//获取商品信息
	public function getproduct(){
		$proid = input('post.proid/d');
		$product = Db::name('kecheng_list')->where('aid',aid)->where('id',$proid)->find();
		$product['count'] = Db::name('kecheng_chapter')->where('kcid',$product['id'])->where('status',1)->count();
		return json(['product'=>$product]);
	}
    function defaultSet(){
        $set = Db::name('kecheng_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('kecheng_sysset')->insert(['aid'=>aid]);
        }
    }
}
