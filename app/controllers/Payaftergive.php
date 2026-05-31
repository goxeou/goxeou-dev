<?php


// +----------------------------------------------------------------------
// | 支付后赠送 custom_file(payaftergive)
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Payaftergive extends Common
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
                $order = 'sort desc,id desc';
            }
            $where = array();
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
            $count = 0 + Db::name('payaftergive')->where($where)->count();
            $data = Db::name('payaftergive')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){
                if($v['starttime'] > time()){
                    $data[$k]['status'] = '<button class="layui-btn layui-btn-sm" style="background-color:#888">未开始</button>';
                }elseif($v['endtime'] < time()){
                    $data[$k]['status'] = '<button class="layui-btn layui-btn-sm layui-btn-disabled">已结束</button>';
                }else{
                    $data[$k]['status'] = '<button class="layui-btn layui-btn-sm" style="background-color:#5FB878">进行中</button>';
                }
                $data[$k]['starttime'] = date('Y-m-d H:i',$v['starttime']);
                $data[$k]['endtime'] = date('Y-m-d H:i',$v['endtime']);
                $data[$k]['score'] = dd_money_format($v['score'],$this->score_weishu);
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    //编辑
    public function edit(){
        if(input('param.id')){
            $info = Db::name('payaftergive')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
            $info['starttime'] = date('Y-m-d H:i:s',$info['starttime']);
            $info['endtime'] = date('Y-m-d H:i:s',$info['endtime']);
            $info['score'] = dd_money_format($info['score'],$this->score_weishu);
        }else{
            $info = array('id'=>'','starttime'=>date('Y-m-d 00:00:00'),'endtime'=>date('Y-m-d 00:00:00',time()+7*86400),'gettj'=>'-1','sort'=>0,'limittimes'=>0);
        }
        $info['gettj'] = explode(',',$info['gettj']);
		
		$couponList = [];
		if($info['coupon_ids']){
			$coupon_ids = explode(',', $info['coupon_ids']);
			foreach($coupon_ids as $couponid){
				$couponList[] = Db::name('coupon')->where('aid',aid)->where('bid',bid)->where('id',$couponid)->find();
			}
		}
        View::assign('couponList',$couponList);

        View::assign('info',$info);
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        View::assign('memberlevel',$memberlevel);
        View::assign('bid',bid);
        return View::fetch();
    }
    //保存
    public function save(){
        $info = input('post.info/a');
        $info['gettj'] = implode(',',$info['gettj']);
        $info['starttime'] = strtotime($info['starttime']);
        $info['endtime'] = strtotime($info['endtime']);
		$info['paygive_scene'] = implode(',',$info['paygive_scene']);
        if(bid > 0){
            unset($info['money']);
            unset($info['score']);
        }
        if($info['id']){
            Db::name('payaftergive')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog('编辑支付赠送'.$info['id']);
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
            $info['createtime'] = time();
            $id = Db::name('payaftergive')->insertGetId($info);
            \app\commons\System::plog('添加支付赠送'.$id);
        }

        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');
        Db::name('payaftergive')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除支付赠送'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
	//赠送数据
	public function record(){
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = 'payaftergive_record.'.input('param.field').' '.input('param.order');
			}else{
				$order = 'payaftergive_record.id desc';
			}
			$where = [];
			$where[] = ['payaftergive_record.aid','=',aid];
			if(input('param.id/d')) $where[] = ['payaftergive_record.hid','=',input('param.id/d')];
			if(input('param.mid')) $where[] = ['payaftergive_record.mid','=',input('param.mid/d')];
			if(input('param.nickname')) $where[] = ['member.nickname','like','%'.input('param.nickname').'%'];
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['payaftergive_record.createtime','>=',strtotime($ctime[0])];
				$where[] = ['payaftergive_record.createtime','<',strtotime($ctime[1]) + 86400];
			}

			$count = 0 + Db::name('payaftergive_record')->alias('payaftergive_record')->join('member member','payaftergive_record.mid=member.id')->where($where)->count();
			$data = Db::name('payaftergive_record')->alias('payaftergive_record')->field('payaftergive_record.*,member.nickname,member.headimg')->join('member member','payaftergive_record.mid=member.id')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		$coupon = [];
		if(input('param.id/d')){
			$payaftergive = Db::name('payaftergive')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
		}
		View::assign('payaftergive',$payaftergive);
		return View::fetch();
	}
	//删除
	public function recorddel(){
		$ids = input('post.ids/a');
		Db::name('payaftergive_record')->where('aid',aid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除支付赠送记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}

    public function getBusinessList()
    {
        if(bid>0){
            return json(['status'=>0,'msg'=>'不支持此操作']);
        }
        $businesslist = [];
        return json(['status'=>1,'datalist'=>$businesslist]);
    }


}