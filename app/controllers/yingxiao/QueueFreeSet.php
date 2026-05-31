<?php
// +----------------------------------------------------------------------
// | 营销-排队免单 custom_file(yx_queue_free)
// +----------------------------------------------------------------------
namespace app\controllers\yingxiao;
use app\controllers\Common;
use think\facade\View;
use think\facade\Db;

class QueueFreeSet extends \app\controllers\Common
{
    public function initialize(){
		parent::initialize();
        $this->defaultSet();
	}
	public function index(){

        $setPlatform = Db::name('queue_free_set')->where('aid',aid)->where('bid',0)->find();
        if(bid > 0){
            if($setPlatform['status'] == 0){
                showmsg('未开启相关功能');
            }
        }
		if(request()->isAjax()){
//            dd(input('post.'));
			$info = input('post.info/a');
            if($info['gettj_children'])$info['gettj_children'] = implode(',',$info['gettj_children']);
            if($info['order_types'])$info['order_types'] = implode(',',$info['order_types']);
            if(bid > 0){
                $bset = Db::name('queue_free_set')->where('aid',aid)->where('bid',bid)->find();
                if(($bset['rate_status_business'] == -1 && $setPlatform['rate_status_business'] == 1) || $bset['rate_status_business'] == 1){
                    if($info['rate'] > $setPlatform['rate_max'] || $info['rate'] < $setPlatform['rate_min']){
                        return json(['status'=>0,'msg'=>'比例范围为'.$setPlatform['rate_min'].'~'.$setPlatform['rate_max']]);
                    }
                }                   
            }
            if(getcustom('yx_queue_free_fanli_commission')){
                $queue_free_commission = input('param.queue_free_commission');
                $queue_commission = [];
                foreach($queue_free_commission['levelid'] as $key=>$val){
                    $ratio =    $queue_free_commission['ratio'][$key];
                    $queue_commission[$val] =$ratio?$ratio:0 ;
                }
                $info['queue_free_commission'] = json_encode($queue_commission);
                $info['queue_free_commission_jicha_status'] = $info['queue_free_commission_jicha_status']?:0;
            }
            if(getcustom('yx_queue_duli_queue')){
                if($info['duli_queue_levelid']) $info['duli_queue_levelid'] =implode(',',$info['duli_queue_levelid']);;
            }
            if(getcustom('yx_queue_free_money_range')){
                $range = input('param.range');
                $rangedata = [];
                foreach($range['start'] as $key=>$val){
                    if($val !=''){
                        $rangedata[$key]['title'] = $range['title'][$key];
                        $rangedata[$key]['start'] = $val;
                        $rangedata[$key]['end'] = $range['end'][$key];
                        $rangedata[$key]['rate'] = $range['rate'][$key];
                        $rangedata[$key]['money_max'] = $range['money_max'][$key];
                        $rangedata[$key]['rate_back'] = $range['rate_back'][$key];
                        if(!$range['no'][$key]){
                            $rangedata[$key]['no'] = $val.$range['end'][$key];
                        }else{
                            $rangedata[$key]['no'] =$range['no'][$key];;
                        }
                    }
                }
                
                $info['queue_money_range'] =$rangedata?json_encode($rangedata,JSON_UNESCAPED_UNICODE):null;
            }
            if(getcustom('yx_queue_free_freeze_account')){
                if(isset($info['freeze_exchange_wallet'])){
                    $info['freeze_exchange_wallet'] = implode(',',$info['freeze_exchange_wallet']);
                }else{
                    $info['freeze_exchange_wallet'] = '';
                }
            }
            if(bid > 0){
                $binfo = [];
                if(($bset['rate_status_business'] == -1 && $setPlatform['rate_status_business'] == 1) || $bset['rate_status_business'] == 1){
                    $binfo['rate'] = $info['rate'];
                }
                if(getcustom('business_apply_queue_free_rate_back')){
                    $binfo['rate_back'] = $info['rate_back'];
                }
                
                if($binfo){
                    Db::name('queue_free_set')->where('aid',aid)->where('bid',bid)->update($binfo);
                }                
            }else{
                Db::name('queue_free_set')->where('aid',aid)->where('bid',bid)->update($info);
            }
           
            \app\commons\System::plog('排队免单设置');
			return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
		}
		$info = Db::name('queue_free_set')->where('aid',aid)->where('bid',bid)->find();
		if(!$info) $info = ['status'=>0];
        if(getcustom('yx_queue_duli_queue')){
            if($info['duli_queue_levelid']) $info['duli_queue_levelid'] =explode(',',$info['duli_queue_levelid']);
        }
        if(getcustom('yx_queue_free_freeze_account')){
            if(isset($info['freeze_exchange_wallet'])){
                $info['freeze_exchange_wallet'] = explode(',',$info['freeze_exchange_wallet']);
            }else{
                $info['freeze_exchange_wallet'] = [];
            }
        }
		View::assign('info',$info);

        if(getcustom('yx_queue_free_fanli_commission')){
            $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
            $default_cid = $default_cid ? $default_cid : 0;
            $queuelevel_list =Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->field('id,name')->where('isdefault',0)->order('sort desc,id')->select()->toArray();
            $queue_free_commission = json_decode($info['queue_free_commission'],true);
            if($queue_free_commission){
                foreach($queuelevel_list as $qk=>$qv){
                    $queuelevel_list[$qk]['ratio'] = $queue_free_commission[$qv['id']];
                }
            }
            View::assign('queuelevel_list',$queuelevel_list);
        }
        if(getcustom('yx_queue_duli_queue')){
            $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
            $default_cid = $default_cid ? $default_cid : 0;
            $duli_levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
            View::assign('duli_levellist',$duli_levellist);
        }
        
        View::assign('setPlatform',$setPlatform);//平台设置
        //定制标签显示
        $mode_show = 0;
        if(getcustom('yx_queue_free_other_mode') || getcustom('yx_queue_free_today_average')){
            $mode_show = 1;
        }
        View::assign('mode_show',$mode_show);//平台设置
		return View::fetch();
	}

    public function defaultSet(){
        $set = Db::name('queue_free_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            if(bid > 0){
                Db::name('queue_free_set')->insert(['aid'=>aid,'bid'=>bid,'status'=>0,'rate_status_business'=>-1,'money_max'=>null,'createtime'=>time(),'gettj_children'=>-1]);
            }else{
                Db::name('queue_free_set')->insert(['aid'=>aid,'bid'=>bid,'status'=>0,'createtime'=>time(),'gettj_children'=>-1,'queue_type_business' => -1]);
            }
        }
    }

}
