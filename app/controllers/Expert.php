<?php


// +----------------------------------------------------------------------
// | 专家管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Expert extends Common
{
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
	}
	//列表
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
            //if(input('param.cid')) $where[] = ['cid','=',input('param.cid/d')];
			if(input('param.cid')) $where[] = Db::raw("find_in_set(".input('param.cid/d').",cid)");
			
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!==''){
				$where[] = ['status','=',input('param.status')];
			}
           
			$bset = Db::name('expert_sysset')->where('aid',aid)->find();
			$count = 0 + Db::name('expert')->where($where)->count();
			$carr = Db::name('expert_category')->where('aid',aid)->column('name','id');
			$data = Db::name('expert')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$cnames = [];
				if($v['cid']){
					$cids = explode(',',$v['cid']);
					foreach($cids as $cid){
						$cnames[] = $carr[$cid];
					}
				}
				$data[$k]['cname'] = implode(',',$cnames);
				if($v['mid']){
					$member = Db::name('member')->where('id',$v['mid'])->find();
					$data[$k]['nickname'] = $member['nickname'];
					$data[$k]['headimg'] = $member['headimg'];
				}else{
					$data[$k]['nickname'] = '';
					$data[$k]['headimg'] = '';
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//标签
		$clist = Db::name('expert_category')->Field('id,name')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
		$bset = Db::name('expert_sysset')->where('aid',aid)->find();
        View::assign('clist',$clist);
		View::assign('bset',$bset);

		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('expert')->where('aid',aid)->where('id',input('param.id/d'))->find();
        }else{
			$info = array('id'=>'','cid'=>'0');
		}
		$info['cid'] = explode(',',$info['cid']);
		$clist = Db::name('expert_category')->Field('id,name')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
        $set = Db::name('expert_sysset')->where('aid',aid)->find();
		View::assign('clist',$clist);
		View::assign('info',$info);
		View::assign('set',$set);

        return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
        $info['endtime'] = strtotime($info['endtime']);
        $member = Db::name('member')->where('id',$info['mid'])->find();
	    if(empty($member)){
			return json(['status'=>0,'msg'=>'该会员不存在']);
		}
    	$hasexpert = Db::name('expert')->where('mid',$info['mid'])->find();
		if($hasexpert && $hasexpert['id']!= $info['id']){
			return json(['status'=>0,'msg'=>'该会员已绑定过专家']);
		}
        if($info['latitude'] && $info['longitude'] && !$info['district']){
            //通过坐标获取省市区
            $mapqq = new \app\commons\MapQQ();
            $address_component = $mapqq->locationToAddress($info['latitude'],$info['longitude']);
            if($address_component && $address_component['status']==1){
                $info['province'] = $address_component['province'];
                $info['city'] = $address_component['city'];
                $info['district'] = $address_component['district'];
            }
        }
    	$cnames = Db::name('expert_category')->Field('id,name')->where('aid',aid)->where('id','in',explode(',',$info['cid']))->column('name','id');
		if ($cnames) {
		    $info['cnames'] = implode(',',$cnames);
		}else {
		    $info['cnames'] ='';
		}
        if($info['id']){
			$bid = $info['id'];
            Db::name('expert')->where($where)->update($info);
			\app\commons\System::plog('修改专家'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$bid = Db::name('expert')->insertGetId($info);
			\app\commons\System::plog('添加专家'.$bid);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('expert')->where('aid',aid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\commons\System::plog('修改专家状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		$expert = Db::name('expert')->where('aid',aid)->where('id',$id)->find();
		if(!$expert) return json(['status'=>0,'msg'=>'专家不存在']);
		Db::name('expert')->where('aid',aid)->where('id',$id)->update(['status'=>$st,'reason'=>$reason]);
		if($st == 1){
		
		}else{
			//商品下架
		//	Db::name('shop_product')->where('aid',aid)->where('bid',$id)->update(['status'=>0]);
		}
		return json(['status'=>1,'msg'=>'操作成功']);
	}

	public function del(){
		$ids = input('post.ids/a');
		Db::name('expert')->where('aid',aid)->where('id','in',$ids)->delete();
	//	Db::name('admin_user')->where('aid',aid)->where('bid','in',$ids)->delete();
	
        \app\commons\System::plog('删除专家'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//充值
	public function recharge(){
		$bid = input('post.rechargemid/d');
		$money = floatval(input('post.rechargemoney'));
        if($money == 0){
			return json(['status'=>0,'msg'=>'请输入充值金额']);
		}
		$info = Db::name('expert')->where('aid',aid)->where('id',$bid)->find();
		if(!$info) return json(['status'=>0,'msg'=>'未找到该专家']);
		\app\commons\expert::addmoney(aid,$bid,$money,'平台充值');
		\app\commons\System::plog('给专家充值'.$bid);
		return json(['status'=>1,'msg'=>'充值成功']);
	}


	private function getnewids($arr,$ids){
		if(!$ids) return $ids;
		$ids = explode(',',$ids);
		$newids = [];
		foreach($ids as $id){
			$newids[] = $arr[$id];
		}
		return implode(',',$newids);
	}

	//标签列表
    public function category(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'sort desc,id';
			}
			$where = array();
			$where[] = ['aid','=',aid];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			$count = 0 + Db::name('expert_category')->where($where)->count();
			$data = Db::name('expert_category')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function categoryedit(){
		if(input('param.id')){
			$info = Db::name('expert_category')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		$pcatelist = Db::name('expert_category')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function categorysave(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('expert_category')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('修改专家标签'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('expert_category')->insertGetId($info);
			\app\commons\System::plog('添加专家标签'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function categorydel(){
		$ids = input('post.ids/a');
		Db::name('expert_category')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除专家标签'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//系统设置
	public function sysset(){
		if(request()->isPost()){
			$rs = Db::name('expert_sysset')->where('aid',aid)->find();
			$info = input('post.info/a');
             if($rs){
				Db::name('expert_sysset')->where('aid',aid)->update($info);
				\app\commons\System::plog('专家系统设置');
			}else{
				$info['aid'] = aid;
				Db::name('expert_sysset')->insert($info);
			}
			return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
		}
		$info = Db::name('expert_sysset')->where('aid',aid)->find();
		if(!$info){
			Db::name('expert_sysset')->insert(['aid'=>aid]);
			$info = Db::name('expert_sysset')->where('aid',aid)->find();
		}
		View::assign('info',$info);
		return View::fetch();
	}
	public function chooseexpert(){
		return View::fetch();
	}
	public function getexpertinfo(){
		$id = input('post.id/d');
		$info = Db::name('expert')->where('id',$id)->where('aid',aid)->find();
		return json(['status'=>1,'data'=>$info]);
	}

}
