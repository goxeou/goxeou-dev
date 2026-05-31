<?php


//custom_file(wx_fws_liuliangzhu)
// +----------------------------------------------------------------------
// | 微信广告    
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use app\commons\Wechat;

class Wxadvert extends Common
{
    public $clist = [];
    public $template_type = [];
	public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无操作权限');
		$this->clist =[
            ['name' => '小程序banner','value' =>'SLOT_ID_WEAPP_BANNER'],
            ['name' => '小程序视频广告','value' =>'SLOT_ID_WEAPP_VIDEO_FEEDS'],
            ['name' => '小程序模板广告','value' =>'SLOT_ID_WEAPP_TEMPLATE'],
        ];
		$this->template_type=[
            ['name' => '竖版上图下文','value' =>'6'],
            ['name' => '竖版上文下图','value' =>'7'],
            ['name' => '竖版上图下文叠加A','value' =>'12'],
            ['name' => '竖版上图下文叠加B','value' =>'13'],
            ['name' => '横幅上图下文','value' =>'2'],
            ['name' => '横幅下图上文','value' =>'3'],
            ['name' => '横板上图下文叠加A','value' =>'4'],
            ['name' => '横板上图下文叠加B','value' =>'11'],
            ['name' => '横幅左图右文','value' =>'9'],
            ['name' => '横幅右图左文','value' =>'10'],
            ['name' => '横幅单图','value' =>'20'],
            ['name' => '单格子无动画','value' =>'100001'],
            ['name' => '多格子无轮播','value' =>'100003'],
        ];
	}
	
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
            if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
            if(input('param.type')) $where[] = ['type','=',input('param.type')];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['createtime','>=',strtotime($ctime[0])];
                $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
            }
            
            $count = 0 + Db::name('wx_advert')->where($where)->count();
            $cclist = array_column($this->clist, 'name','value');
            $data = Db::name('wx_advert')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach ($data as $key=>&$val){
                $val['typename'] =$cclist[$val['type']]; 
                $val['createtime'] =date('Y-m-d H:i:s',$val['createtime']); 
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        //分类
      
        View::assign('clist',$this->clist);
        return View::fetch();
    }
	 //编辑
	 public function edit(){
	     
         if(input('param.id/d')){
                $info = Db::name('wx_advert')->where('id',input('param.id'))->find();
         }else{
              $info = [];
         }
         View::assign('info',$info);
         View::assign('clist',$this->clist);
         View::assign('template_type',$this->template_type);
         return View::fetch();
     }
	
	 public function save(){
         $info = input('post.info/a');
         if($info['id']){
             Db::name('wx_advert')->where('id',$info['id'])->update($info);
             $data =[
                 'name' => $info['name'],
                 'ad_unit_id' => $info['ad_unit_id'],
                 'status'=> $info['status'] ==1?'AD_UNIT_STATUS_ON':'AD_UNIT_STATUS_OFF'
             ]; 
             if($info['type'] =='SLOT_ID_WEAPP_TEMPLATE'){
                 $data['tmpl_id'] = $info['tmpl_id'];
             }
             Wechat::editAdunit(aid,'wx',$data);
         }else{
             //判断是否有资格开通
             $publisher_status =  Wechat::isOpenPublisher(aid,'wx');
             if($publisher_status['status'] ==0){
                 return json(['status'=>0,'msg'=>$publisher_status['msg']]);
             }else{
                 if($publisher_status['isopen']==0){
                     return json(['status'=>0,'msg'=>'未开通流量主']);
                 }
             }

             //获取
             $tmpl_id = $info['tmpl_id']?$info['tmpl_id']:'';
             $ad_unit_id= Wechat::createAdunit(aid,'wx',$info['name'],$info['type'],$tmpl_id);
             if(!$ad_unit_id['ad_unit_id']){
                return json(['status'=>0,'msg'=>$ad_unit_id['msg']]);
            }
            $info['aid'] = aid;
            $info['bid'] = bid;
            $info['createtime'] = time();
            $info['ad_unit_id'] = $ad_unit_id['ad_unit_id'];
            Db::name('wx_advert')-> insert($info);
         }
         return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
     }
	

    //开通流量主
    public function create_publisher(){
        //判断是否有资格开通
        $publisher_status =  Wechat::isOpenPublisher(aid,'wx');
        if($publisher_status['status'] ==0){
            return json(['status'=>0,'msg'=>$publisher_status['msg']]);
        }else{
            if($publisher_status['isopen']==0){
                return json(['status'=>0,'msg'=>'无开通流量主权限']);
            }
        }
       $data =  Wechat::createPublisher(aid,'wx');
       if($data['status'] ==0){
           return json(['status'=>0,'msg'=>$data['msg']]);
       }
       return json(['status'=>1,'msg'=>'开通成功']);
       
    }
    //获取广告的代码
    public function getcode(){
	    $id = input('param.id/d');
	    $wx_advert = Db::name('wx_advert')->where('id',$id)->find();
	    if(!$wx_advert){
            return json(['status'=>0,'msg'=>'广告不存在']); 
        }
	    if(!$wx_advert['code']){
	        $res = Wechat::getAdunitCode(aid,'wx',$wx_advert['ad_unit_id']);
	        if(!$res['code']){
                return json(['status'=>0,'msg'=>$res['msg']]);
            }
            $wx_advert['code'] = $res['code'];
        }
        return json(['status'=>1,'msg'=>'成功','data' => $wx_advert['code']]);
    }
    //细分数据
    public function xfdata(){
        if(request()->isAjax()){
            $id = input('param.id/d');
            $page = input('param.page/d',1);
            $limit = input('param.limt/d',10);
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $start_date = $ctime[0];
                $end_date = $ctime[1];
            }
            $ad = Db::name('wx_advert')->where('id',$id)->find();
            $data = [
                'page' => $page,
                'page_size' => $page ==1?100:$limit,
                'start_date'  =>$start_date?$start_date:date('Y-m-d'),
                'end_date'  =>$end_date?$end_date:date('Y-m-d',strtotime('-7 days')),
                'ad_unit_id' => $ad['ad_unit_id']
            ];
            $rdata =  Wechat::getAdxfData(aid,'wx',$data);

            if($rdata['list']['stat_item']){
                if(count($rdata['list']['stat_item']) >10){
                    $rdata['list']  = array_slice($rdata['list']['stat_item'],0,10);
                }
            }else{
                $rdata['list']['stat_item'] = [];
                $rdata['total_num']=0;
                $rdata['summary']=[];
            }
            
            return json(['code'=>0,'msg'=>'查询成功','count'=>$rdata['total_num'],'data'=>$rdata['list']['stat_item'],'summary' => $rdata['summary']]);
        }
        View::assign('clist',$this->clist);
        return View::fetch();
    }
    
    //获取小程序广告汇总数据
    public function addata(){
        if(request()->isAjax()){
            $page = input('param.page/d',1);
            $limit = input('param.limt/d',10);
            $type = input('param.type');
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $start_date = $ctime[0];
                $end_date = $ctime[1];
            }
            $data = [
                'page' => $page,
                'page_size' => $page ==1?100:$limit,
                'start_date'  =>$start_date?$start_date:date('Y-m-d'),
                'end_date'  =>$end_date?$end_date:date('Y-m-d',strtotime('-7 days'))
            ];
            if($type){
                $data['ad_slot'] = $type;
            }
            $rdata =  Wechat::getAdSummaryData(aid,'wx',$data);
          
            if($rdata['list']){
                if(count($rdata['list']) >10){
                    $rdata['list']  = array_slice($rdata['list'],0,10);
                }
            }else{
                $rdata['list'] = [];
                $rdata['total_num']=0;
                $rdata['summary']=[];
            }
           
            return json(['code'=>0,'msg'=>'查询成功','count'=>$rdata['total_num'],'data'=>$rdata['list'],'summary' => $rdata['summary']]);
        }
        View::assign('clist',$this->clist);
        return View::fetch();
    }
    //获取小程序广告汇总数据
    public function settle(){
        if(request()->isAjax()){
            $page = input('param.page/d',1);
            $limit = input('param.limt/d',10);
            $start_date = input('param.start_date');
            $end_date = input('param.end_date');

            $data = [
                'page' => $page,
                'page_size' => $page ==1?100:$limit,
                'start_date'  =>$start_date?$start_date:date('Y-m-d'),
                'end_date'  =>$end_date?$end_date:date('Y-m-d',strtotime('-7 days'))
            ];
            $rdata =  Wechat::getAdSettleData(aid,'wx',$data);
            if($rdata['list']){
                if(count($rdata['list']) >10){
                    $rdata['list']  = array_slice($rdata['list'],0,10);
                }
            }else{
                $rdata['list'] = [];
                $rdata['total_num']=0;
                $rdata['jsdata']=[];
            }

            return json(['code'=>0,'msg'=>'查询成功','count'=>$rdata['total_num'],'data'=>$rdata['list'],'summary' => $rdata['jsdata']]);
        }
        return View::fetch();
    }
}
