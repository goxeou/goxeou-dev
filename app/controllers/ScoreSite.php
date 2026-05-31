<?php


// +----------------------------------------------------------------------
// | 商家管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class ScoreSite extends Common
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
            if(input('?param.is_open') && input('param.is_open')!==''){
                $where[] = ['is_open','=',input('param.is_open')];
            }
			$bset = Db::name('score_site_sysset')->where('aid',aid)->find();
			$count = 0 + Db::name('score_site')->where($where)->count();
			$carr = Db::name('business_category')->where('aid',aid)->column('name','id');
			$data = Db::name('score_site')->where($where)->page($page,$limit)->order($order)->select()->toArray();
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
					$data[$k]['business_pid'] = $member['pid'];
				}else{
					$data[$k]['nickname'] = '';
					$data[$k]['headimg'] = '';
					$data[$k]['business_pid'] = '';
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//分类
		$clist = Db::name('score_site_category')->Field('id,name')->where('aid',aid)->where('status',1)->order('sort desc,id')->select()->toArray();
		$bset = Db::name('score_site_sysset')->where('aid',aid)->find();
        View::assign('clist',$clist);
		View::assign('bset',$bset);

		View::assign('isadmin',$this->user['isadmin']);
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('score_site')->where('aid',aid)->where('id',input('param.id/d'))->find();
			$uinfo = [];
		}else{
			$info = array('id'=>'','cid'=>'0');
			$uinfo = [];
		}
		$info['cid'] = explode(',',$info['cid']);
        $set = Db::name('score_site_sysset')->where('aid',aid)->find();
      
		$submchidlength = 0;
		View::assign('clist',$clist);
		View::assign('info',$info);
		View::assign('uinfo',$uinfo);
		View::assign('set',$set);
        $sysset = Db::name('admin_set')->where('aid',aid)->find();
        return View::fetch();
	}
	public function save(){
		$info = input('post.info/a');
        $member = Db::name('member')->where('id',$info['mid'])->find();
	    if(empty($member)){
			return json(['status'=>0,'msg'=>'该会员不存在']);
		}
	
    	$hasbusiness = Db::name('score_site')->where('mid',$info['mid'])->find();
		if($hasbusiness && $hasbusiness['id']!= $info['id']){
			return json(['status'=>0,'msg'=>'该会员已绑定过商家']);
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

        if($info['id']){
			$bid = $info['id'];
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['id','=',$info['id']];
            Db::name('score_site')->where($where)->update($info);
			\app\commons\System::plog('修改站点'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$bid = Db::name('score_site')->insertGetId($info);
			\app\commons\System::plog('添加站点'.$bid);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//改状态
	public function setst(){
		$st = input('post.st/d');
		$ids = input('post.ids/a');
		Db::name('score_site')->where('aid',aid)->where('id','in',$ids)->update(['status'=>$st]);
		\app\commons\System::plog('修改站点状态'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	//审核
	public function setcheckst(){
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		$business = Db::name('score_site')->where('aid',aid)->where('id',$id)->find();
		if(!$business) return json(['status'=>0,'msg'=>'商家不存在']);
		Db::name('score_site')->where('aid',aid)->where('id',$id)->update(['status'=>$st,'reason'=>$reason]);
	
		return json(['status'=>1,'msg'=>'操作成功']);
	}

	public function del(){
		$ids = input('post.ids/a');
		Db::name('score_site')->where('aid',aid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除站点'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//充值
	public function recharge(){
		$bid = input('post.rechargemid/d');
		$money = floatval(input('post.rechargemoney'));
        if($money == 0){
			return json(['status'=>0,'msg'=>'请输入充值金额']);
		}
		$info = Db::name('score_site')->where('aid',aid)->where('id',$bid)->find();
		if(!$info) return json(['status'=>0,'msg'=>'未找到该商家']);
		\app\commons\ScoreSite::addmoney(aid,$bid,$money,'平台充值');
		\app\commons\System::plog('给站点充值'.$bid);
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

	//分类列表
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
			$count = 0 + Db::name('business_category')->where($where)->count();
			$data = Db::name('business_category')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function categoryedit(){
		if(input('param.id')){
			$info = Db::name('business_category')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		$pcatelist = Db::name('business_category')->where('aid',aid)->order('sort desc,id')->select()->toArray();
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function categorysave(){
		$info = input('post.info/a');
		if($info['id']){
			Db::name('business_category')->where('aid',aid)->where('id',$info['id'])->update($info);
			\app\commons\System::plog('修改商家分类'.$info['id']);
		}else{
			$info['aid'] = aid;
			$info['createtime'] = time();
			$id = Db::name('business_category')->insertGetId($info);
			\app\commons\System::plog('添加商家分类'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function categorydel(){
		$ids = input('post.ids/a');
		Db::name('business_category')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除商家分类'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//系统设置
	public function sysset(){
		if(request()->isPost()){
			$rs = Db::name('score_site_sysset')->where('aid',aid)->find();
			$info = input('post.info/a');

			$info['wxfw_apiclient_cert'] = str_replace(PRE_URL.'/','',$info['wxfw_apiclient_cert']);
			$info['wxfw_apiclient_key'] = str_replace(PRE_URL.'/','',$info['wxfw_apiclient_key']);

            if(!empty($info['wxfw_apiclient_cert']) && substr($info['wxfw_apiclient_cert'], -4) != '.pem'){
                return json(['status'=>0,'msg'=>'PEM证书格式错误']);
            }
            if(!empty($info['wxfw_apiclient_key']) && substr($info['wxfw_apiclient_key'], -4) != '.pem'){
                return json(['status'=>0,'msg'=>'证书密钥格式错误']);
            }
             if($rs){
                //关闭多站点分销设置
                if($rs['commission_canset'] == 1 && $info['commission_canset'] == 0){
                    Db::name('shop_product')->where('aid',aid)->where('bid','>',0)->update(['commissionset'=>-1]);
                }
				Db::name('score_site_sysset')->where('aid',aid)->update($info);
				\app\commons\System::plog('多站点系统设置');
			}else{
				$info['aid'] = aid;
				Db::name('score_site_sysset')->insert($info);
			}
			return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
		}
		$info = Db::name('score_site_sysset')->where('aid',aid)->find();
		if(!$info){
			Db::name('score_site_sysset')->insert(['aid'=>aid]);
			$info = Db::name('score_site_sysset')->where('aid',aid)->find();
		}
        //权限
        if($this->auth_data == 'all' || in_array('Cashier/*',$this->auth_data)){
            $info['business_auth'] = true;
        }
        if($info['wxfw_status']==0){
            $info['duli_disabled'] = true;
        }
        $scoredk_kouchu_list = [0=>'否',1=>'是'];
        View::assign('scoredk_kouchu_list',$scoredk_kouchu_list);
        View::assign('info',$info);
		return View::fetch();
	}
	public function choosebusiness(){
		return View::fetch();
	}
	public function getbusinessinfo(){
		$id = input('post.id/d');
		$info = Db::name('score_site')->where('id',$id)->where('aid',aid)->find();
		return json(['status'=>1,'data'=>$info]);
	}
	//登录
	public function blogin(){
		$id = input('param.id/d');
		$user = Db::name('admin_user')->where('aid',aid)->where('bid',$id)->where('isadmin',1)->find();
		if(!$user) die('未找到该商家');
		session('ADMIN_AUTH_UID',$user['id']);
		session('ADMIN_AUTH_BID',$id);
		return redirect(PRE_URL.'/business.php?s=/Backstage/index');
	}

	//站点销量统计
	public function sales(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 's.'.input('param.field').' '.input('param.order');
            }else{
                $order = 's.bid desc';
            }
            $where = array();
            $where[] = ['s.aid','=',aid];
            if(input('param.bid')) $where[] = ['s.bid','=',input('param.bid/d')];
            if(input('param.name')) $where[] = ['b.name','like','%'.input('param.name').'%'];
            $count = 0 + Db::name('business_sales')
                    ->alias('s')
                    ->join('business b','s.bid=b.id','left')
                    ->where($where)->count();
            $data = Db::name('business_sales')
                ->alias('s')
                ->join('business b','s.bid=b.id','left')
                ->field('s.*,b.name')
                ->where($where)->page($page,$limit)->order($order)->select()->toArray();

            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        //判断管理员商品虚拟销量和数据是否匹配，不匹配显示手动更新按钮（解决首次更新代码销量没有数据问题）
        $show_update = 0;
        $product_sales = Db::name('shop_product')->where('aid',aid)->where('bid',0)->sum('sales');
        $business_sales = Db::name('business_sales')->where('aid',aid)->where('bid',0)->value('sales');
        if($product_sales>$business_sales){
            $show_update = 1;
        }
        View::assign('show_update',$show_update);

        return View::fetch();
    }

    //导出
    public function excel(){
        }

    public function setdepositrefund(){
	    }
}
