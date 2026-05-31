<?php



//排队订单
namespace app\controllers;

use app\models\RestaurantQueueModel;
use think\facade\Db;
use think\facade\View;

class RestaurantQueue extends Common
{

    public function initialize(){
        parent::initialize();
    }

    //队列
    public function index()
    {
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'id desc';
            }
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
            if(input('param.order_no')) $where[] = ['queue_no','like','%'.input('param.order_no').'%'];
            if(input('param.linkman')) $where[] = ['linkman','like','%'.input('param.linkman').'%'];
            if(input('param.tel')) $where[] = ['tel','like','%'.input('param.tel').'%'];
            if(input('param.create_time') ){
                $ctime = explode(' ~ ',input('param.create_time'));
                $where[] = ['create_time','>=',strtotime($ctime[0])];
                $where[] = ['create_time','<',strtotime($ctime[1]) + 86400];
            }
            if(input('?param.status') && input('param.status')!==''){
                $where[] = ['status','=',input('param.status')];
            }
            $data = [];
            $data = (new RestaurantQueueModel())->getList($where, $page, $limit, $order);
            $list = $data['list'];
			foreach($list as $k=>$v){
				$list[$k]['cname'] = Db::name('restaurant_queue_category')->where('id',$v['cid'])->value('name');
				$member = Db::name('member')->where('id',$v['mid'])->find();
				if($member){
					$list[$k]['headimg'] = $member['headimg'];
					$list[$k]['nickname'] = $member['nickname'];
				}else{
					$list[$k]['headimg'] = '';
					$list[$k]['nickname'] = '';
				}
			}

            return json(['code'=>0,'msg'=>'查询成功','count'=>$data['count'],'data'=>$list]);
        }
		$config = include(ROOT_PATH.'config.php');
		$authtoken = $config['authtoken'];

		View::assign('aid',aid);
		View::assign('bid',bid);
		View::assign('token',md5(md5($authtoken.aid.bid)));
        $this->defaultSet();
        return View::fetch();
    }
	//改状态
	public function setst(){
		$id = input('post.id/d');
		$st = input('post.st/d');
		$queue = Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
		if($st == 1){//叫号
			$rs = \app\customs\Restaurant::call_no(aid,$queue['call_text']);
			$file = 'upload/'.aid.'/'.date('Ymd').'/'.md5($rs).'.mp3';
            $filepath = ROOT_PATH.$file;
            if(!is_dir(dirname($filepath))){
                @mkdir(dirname($filepath),0777,true);
            }
			file_put_contents($filepath,$rs);
			Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('id',$id)->update(['need_play'=>1,'call_time'=>time(),'call_voice_url'=>PRE_URL.'/'.$file]);
            
		}elseif($st == 2){//过号
			Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('id',$id)->update(['expiry_time'=>time()]);
		}
		Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('id',$id)->update(['status'=>$st]);
        return json(['status'=>1,'msg'=>'操作成功']);
	}

    //设置备注
    public function setRemark(){
        $orderid = input('post.orderid/d');
        $content = input('post.content');
        Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('id',$orderid)->update(['remark'=>$content]);
        \app\commons\System::plog('餐饮排队设置备注'.$orderid);
        return json(['status'=>1,'msg'=>'设置完成']);
    }
    //删除
    public function del(){
		$ids = input('post.ids/a');
        Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('餐饮排队删除'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
	//添加编辑
	public function edit(){
		if(request()->isAjax()){
			$info = input('post.info/a');
			if($info['id']){
				Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('id',$info['id'])->update($info);
				\app\commons\System::plog('修改排队记录'.$info['id']);
			}else{
				$category = Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',bid)->where('id',$info['cid'])->find();
				$hasnum = Db::name('restaurant_queue')->where('aid',aid)->where('bid',bid)->where('date',date('Y-m-d'))->where('cid',$info['cid'])->count();
				$queue_no = $hasnum + 1;
				if($queue_no < 10) $queue_no = '0'.$queue_no;
				$queue_no = $category['code'].$queue_no;
					
				$info['date'] = date('Y-m-d');
				$info['queue_no'] = $queue_no;
				$info['call_text'] = str_replace('[排队号]',$queue_no,$category['call_text']);
				$info['create_time'] = time();

				$info['aid'] = aid;
				$info['bid'] = bid;
				$info['date'] = date('Y-m-d');
				$info['create_time'] = time();
				$id = Db::name('restaurant_queue')->insertGetId($info);
                \app\commons\System::plog('添加排队'.$id);
			}
			return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
		}
		if(input('param.id')){
			$info = Db::name('restaurant_queue')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'');
		}
		$clist = Db::name('restaurant_queue_category')->where('aid',aid)->where('bid',bid)->where('status',1)->select()->toArray();
		View::assign('info',$info);
		View::assign('clist',$clist);
		
		$config = include(ROOT_PATH.'config.php');
		$authtoken = $config['authtoken'];

		View::assign('aid',aid);
		View::assign('bid',bid);
		View::assign('token',md5(md5($authtoken.aid.bid)));
		return View::fetch();
	}
    function defaultSet(){
        $set = Db::name('restaurant_queue_sysset')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            $insert = ['aid'=>aid,'bid'=>bid,'screen_pic'=>PRE_URL.'/static/img/restaurant_queue_bg.jpg'];
            Db::name('restaurant_queue_sysset')->insert($insert);
        }
    }
}