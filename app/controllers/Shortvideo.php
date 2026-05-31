<?php


// +----------------------------------------------------------------------
// | 短视频
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use think\facade\Log;

class Shortvideo extends Common
{
    //短视频列表
	public function index(){

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

        $clist = Db::name('shortvideo_category')->where($where)->where('status',1)->column('id,name','id');
	    if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'sort desc,id desc';
            }
            if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
            if(input('param.cid')) $where[] = ['cid','like','%'.input('param.cid').'%'];
            if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			if(input('param.createtime')){
				$where[] = ['createtime','>=',strtotime(input('param.createtime'))];
				$where[] = ['createtime','<',strtotime(input('param.createtime')) + 86400];
			}

            $count = 0 + Db::name('shortvideo')->where($where)->count();
            $data = Db::name('shortvideo')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){
				$data[$k]['comment_num'] = Db::name('shortvideo_comment')->where('vid',$v['id'])->where('status',1)->count();
				$data[$k]['cname'] = $clist[$v['cid']]['name'];
				if($v['mid'] > 0){
					$member = Db::name('member')->where('id',$v['mid'])->find();
					$data[$k]['headimg'] = $member['headimg'];
					$data[$k]['nickname'] = $member['nickname'];
				}
				if($v['bid'] > 0){
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['bname'] = '平台';
				}
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        $this->defaultSet();
        View::assign('clist',$clist);
		$needcheck = Db::name('business_sysset')->where('aid',aid)->value('shortvideo_check');
		View::assign('needcheck',$needcheck);
        return View::fetch();
	}
    //编辑短视频
    public function edit(){
        if(input('param.id')){
			if(bid == 0){
				$info = Db::name('shortvideo')->where('aid',aid)->where('id',input('param.id/d'))->find();
			}else{
				$info = Db::name('shortvideo')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
			}
            if(!$info) showmsg('视频不存在');
            if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
        }else{
			$info = [];
			$info['bid'] = bid;
		}
        //dump($info);
        $clist = Db::name('shortvideo_category')->where('aid',aid)->where('bid',$info['bid'])->where('status',1)->column('id,name');
        if($info && $info['productids']){
            $productdata = Db::name('shop_product')->where('aid',aid)->where('bid',$info['bid'])->where('id','in',$info['productids'])->order(Db::raw('field(id,'.$info['productids'].')'))->select()->toArray();
        }
        View::assign('productdata',$productdata);

        View::assign('clist',$clist);
        View::assign('info',$info);
        return View::fetch();
    }
    //保存商品
    public function save(){
        if(input('post.id')){
            $video = Db::name('shortvideo')->where('aid',aid)->where('id',input('post.id/d'))->find();
            if(!$video) showmsg('视频不存在');
            if(bid != 0 && $video['bid']!=bid) showmsg('无权限操作');
        }
        $info = input('post.info/a');
        //更改处理优惠券内容
        if($video){
			if(bid != 0){
				$needcheck = Db::name('business_sysset')->where('aid',aid)->value('shortvideo_check');
				if($needcheck && $video['status']!=0){
					$info['status'] = 0;
				}
			}


            Db::name('shortvideo')->where('aid',aid)->where('id',$video['id'])->update($info);
            $vid = $video['id'];
            \app\commons\System::plog('短视频编辑'.$vid);
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
            $info['createtime'] = time();
			if(bid != 0){
				$needcheck = Db::name('business_sysset')->where('aid',aid)->value('shortvideo_check');
				if($needcheck){
					$info['status'] = 0;
				}
			}

            $vid = Db::name('shortvideo')->insertGetId($info);
            \app\commons\System::plog('短视频编辑'.$vid);
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
        $list = Db::name('shortvideo')->where($where)->select()->toArray();
        Db::name('shortvideo')->where($where)->update(['status'=>$st]);
        \app\commons\System::plog('短视频改状态'.implode(',',$ids));
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
        Db::name('shortvideo')->where($where)->delete();
        \app\commons\System::plog('短视频删除'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }

	//选择视频
	public function choosevideo(){
		//分类
		$clist = Db::name('shortvideo_category')->where('aid',aid)->where('status',1)->column('id,name');
		//商户
		$blist = Db::name('business')->where('aid',aid)->order('sort desc,id desc')->select()->toArray();
		View::assign('blist',$blist);
		View::assign('clist',$clist);
		return View::fetch();
	}
	//获取视频信息
	public function getvideo(){
		$proid = input('post.proid/d');
		$product = Db::name('shortvideo')->where('aid',aid)->where('id',$proid)->find();
		if($product['bid']!=0){
			$product['logo'] = Db::name('business')->where('aid',aid)->where('id',$product['bid'])->value('logo');
		} else {
			$product['logo'] = Db::name('admin_set')->where('aid',aid)->value('logo');
		}

		return json(['product'=>$product]);
	}
	//审核
	public function setcheckst(){
		if(bid!=0) return json(['status'=>0,'msg'=>'无权限操作']);
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
        $info = Db::name('shortvideo')->where('aid',aid)->where('id',$id)->find();
        Db::name('shortvideo')->where('aid',aid)->where('id',$id)->update(['status'=>$st,'reason'=>$reason]);
        return json(['status'=>1,'msg'=>'操作成功']);
	}
    function defaultSet(){
        $set = Db::name('shortvideo_sysset')->where('aid',aid)->find();
        if(!$set){
            Db::name('shortvideo_sysset')->insert(['aid'=>aid]);
        }
    }
}