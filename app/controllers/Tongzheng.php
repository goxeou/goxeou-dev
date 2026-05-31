<?php
//custom_file(product_givetongzheng)
// +----------------------------------------------------------------------
// | 通证管理管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Tongzheng extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}
	//余额明细
    public function moneylog(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'member_tongzhenglog.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'member_tongzhenglog.id desc';
			}
			$where = [];
			$where[] = ['member_tongzhenglog.aid','=',aid];
			
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
			if(input('param.mid')) $where[] = ['member_tongzhenglog.mid','=',trim(input('param.mid'))];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['member_tongzhenglog.status','=',input('param.status')];
			$count = 0 + Db::name('member_tongzhenglog')->alias('member_tongzhenglog')->field('member.nickname,member.headimg,member_tongzhenglog.*')->join('member member','member.id=member_tongzhenglog.mid')->where($where)->count();
			$data = Db::name('member_tongzhenglog')->alias('member_tongzhenglog')->field('member.nickname,member.headimg,member_tongzhenglog.*')->join('member member','member.id=member_tongzhenglog.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		return View::fetch();
    }
	//余额明细导出
	public function moneylogexcel(){
		if(input('param.field') && input('param.order')){
			$order = 'member_tongzhenglog.'.input('param.field').' '.input('param.order');
		}else{
			$order = 'member_tongzhenglog.id desc';
		}
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
		$where = array();
		$where[] = ['member_tongzhenglog.aid','=',aid];
		
		if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
		if(input('param.mid')) $where[] = ['member_tongzhenglog.mid','=',trim(input('param.mid'))];
		if(input('?param.status') && input('param.status')!=='') $where[] = ['member_tongzhenglog.status','=',input('param.status')];
		$list = Db::name('member_tongzhenglog')->alias('member_tongzhenglog')->field('member.nickname,member.headimg,member_tongzhenglog.*')
            ->join('member member','member.id=member_tongzhenglog.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
        $count = Db::name('member_tongzhenglog')->alias('member_tongzhenglog')->field('member.nickname,member.headimg,member_tongzhenglog.*')
            ->join('member member','member.id=member_tongzhenglog.mid')->where($where)->count();
		$title = array();
		$title[] = t('会员').'信息';
		$title[] = '变更金额';
		$title[] = '变更后剩余';
		$title[] = '变更时间';
		$title[] = '备注';
		$data = array();
		foreach($list as $v){
			$tdata = array();
			$tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
			$tdata[] = $v['money'];
			$tdata[] = $v['after'];
			$tdata[] = date('Y-m-d H:i:s',$v['createtime']);
			$tdata[] = $v['remark'];
			$data[] = $tdata;
		}
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
	//余额明细删除
	public function moneylogdel(){
		$ids = input('post.ids/a');
		Db::name('member_tongzhenglog')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除余额明细'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

	//通证设置
    public function set(){
        if(request()->isPost()){
            $info = input('info');
            $data = [];
            $data['tongzheng_release_bili'] = $info['tongzheng_release_bili'];
            $data['tongzheng_transfer'] = $info['tongzheng_transfer'];
            $data['tongzheng_transfer_pwd'] = $info['tongzheng_transfer_pwd'];
            Db::name('admin_set')->where('aid',aid)->update($data);
            return json(['status'=>1,'msg'=>'设置成功']);
        }else{
            $info = Db::name('admin_set')->where('aid',aid)->find();
            View::assign('info',$info);
            return View::fetch();
        }

    }
    //释放记录
    public function release_log(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 'l.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'l.id desc';
            }
            $where = [];
            $where[] = ['l.aid','=',aid];

            if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
            if(input('param.mid')) $where[] = ['l.mid','=',trim(input('param.mid'))];
            if(input('?param.status') && input('param.status')!=='') $where[] = ['member_tongzhenglog.status','=',input('param.status')];
            $count = 0 + Db::name('tongzheng_release_log')->alias('l')->field('member.nickname,member.headimg,l.*')
                    ->join('member member','member.id=l.mid')->where($where)->count();
            $data = Db::name('tongzheng_release_log')->alias('l')->field('member.nickname,member.headimg,l.*')
                ->join('member member','member.id=l.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    //余额明细导出
    public function releaselogexcel(){
        if(input('param.field') && input('param.order')){
            $order = 'l.'.input('param.field').' '.input('param.order');
        }else{
            $order = 'l.id desc';
        }
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
        $where = array();
        $where[] = ['l.aid','=',aid];

        if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
        if(input('param.mid')) $where[] = ['l.mid','=',trim(input('param.mid'))];
        if(input('?param.status') && input('param.status')!=='') $where[] = ['l.status','=',input('param.status')];
        $list = Db::name('tongzheng_release_log')->alias('l')->field('member.nickname,member.headimg,l.*')
            ->join('member member','member.id=l.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
        $count = Db::name('tongzheng_release_log')->alias('l')->field('member.nickname,member.headimg,l.*')
            ->join('member member','member.id=l.mid')->where($where)->count();
        $title = array();
        $title[] = t('会员').'信息';
        $title[] = t('通证').'数量';
        $title[] = '释放比例';
        $title[] = '释放数量';
        $title[] = '变更时间';
        $title[] = '备注';
        $data = array();
        foreach($list as $v){
            $tdata = array();
            $tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
            $tdata[] = $v['tongzheng'];
            $tdata[] = $v['release_bili'];
            $tdata[] = $v['release_num'];
            $tdata[] = date('Y-m-d H:i:s',$v['createtime']);
            $tdata[] = $v['remark'];
            $data[] = $tdata;
        }
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
        $this->export_excel($title,$data);
    }
    public function to_release(){
        Db::startTrans();
        $sysset = Db::name('admin_set')->where('aid', aid)->find();
        $res = \app\commons\Member::release_tongzheng($sysset);
        Db::commit();
        $res = ['status'=>0,'msg'=>'释放完成'];
        return json($res);
    }

    //释放明细删除
    public function releaselogdel(){
        $ids = input('post.ids/a');
        Db::name('tongzheng_release_log')->where('aid',aid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除释放明细'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }

    //通证订单
    public function order_log(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 'l.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'l.id desc';
            }
            $where = [];
            $where[] = ['l.aid','=',aid];

            if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
            if(input('param.mid')) $where[] = ['l.mid','=',trim(input('param.mid'))];
            if(input('?param.status') && input('param.status')!=='') $where[] = ['l.status','=',input('param.status')];
            $count = 0 + Db::name('tongzheng_order_log')->alias('l')->field('member.nickname,member.headimg,l.*')
                    ->join('member member','member.id=l.mid')->where($where)->count();
            $data = Db::name('tongzheng_order_log')->alias('l')->field('member.nickname,member.headimg,l.*')
                ->join('member member','member.id=l.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    //余额明细导出
    public function orderlogexcel(){
        if(input('param.field') && input('param.order')){
            $order = 'l.'.input('param.field').' '.input('param.order');
        }else{
            $order = 'l.id desc';
        }
        $page = input('param.page')?:1;
        $limit = input('param.limit')?:10;
        $where = array();
        $where[] = ['l.aid','=',aid];

        if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
        if(input('param.mid')) $where[] = ['l.mid','=',trim(input('param.mid'))];
        if(input('?param.status') && input('param.status')!=='') $where[] = ['l.status','=',input('param.status')];
        $list = Db::name('tongzheng_order_log')->alias('l')->field('member.nickname,member.headimg,l.*')
            ->join('member member','member.id=l.mid')->where($where)->order($order)->page($page,$limit)->select()->toArray();
        $count = Db::name('tongzheng_order_log')->alias('l')->field('member.nickname,member.headimg,l.*')
            ->join('member member','member.id=l.mid')->where($where)->count();
        $title = array();
        $title[] = t('会员').'信息';
        $title[] = t('通证').'数量';
        $title[] = '已释放数量';
        $title[] = '剩余释放';
        $title[] = '状态';
        $title[] = '订单ID';
        $title[] = '变更时间';
        $title[] = '备注';
        $data = array();
        $status_arr = [0=>'释放中',1=>'释放完成',2=>'订单删除'];
        foreach($list as $v){
            $tdata = array();
            $tdata[] = $v['nickname'].'('.t('会员').'ID:'.$v['mid'].')';
            $tdata[] = $v['tongzheng'];
            $tdata[] = $v['release_num'];
            $tdata[] = $v['remain'];
            $tdata[] = $status_arr[$v['status']];
            $tdata[] = $v['orderid'];
            $tdata[] = date('Y-m-d H:i:s',$v['createtime']);
            $tdata[] = $v['remark'];
            $data[] = $tdata;
        }
        return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data,'title'=>$title]);
        $this->export_excel($title,$data);
    }
    //通证订单删除
    public function orderlogdel(){
        $ids = input('post.ids/a');
        Db::name('tongzheng_order_log')->where('aid',aid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除通证订单'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }

}
