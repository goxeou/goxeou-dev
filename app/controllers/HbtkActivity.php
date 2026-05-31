<?php


//custom_file(yx_hbtk)
// +----------------------------------------------------------------------
// | 拓客活动
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class HbtkActivity extends Common
{
    public function initialize(){
        parent::initialize();
    }
    //活动列表
    public function index(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            
            $order = 'id desc';
            
            $where = array();
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
            if(input('param.ctime')){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['starttime','>=',strtotime($ctime[0])];
                $where[] = ['endtime','<',strtotime($ctime[1]) + 86400];
            }
            $count = 0 + Db::name('hbtk_activity')->where($where)->count();
            $data = Db::name('hbtk_activity')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $key=>&$val){
                $join_count =   Db::name('hbtk_order')->where('hid',$val['id'])->count();
                $val['join_num'] = $join_count?$join_count:0;
                $buy_count =   Db::name('hbtk_order')->where('status','in',[1,2])->where('hid',$val['id'])->count();
                $val['buy_num'] = $buy_count?$buy_count:0;
                $zf_count =   Db::name('hbtk_sharelog')->where('hid',$val['id'])->count();
                $val['zf_num'] = $zf_count?$zf_count:0;
            }
            if(input('param.field') && input('param.order')){
                $asc = input('param.order') =='asc'?'SORT_ASC':'SORT_DESC';
                $sort = array_column($data,input('param.field'));
                array_multisort($sort,$asc,SORT_REGULAR ,$data);
            }
            
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    //编辑
    public function edit(){
        if(input('param.id')){
            $info = Db::name('hbtk_activity')->where('aid',aid)->where('id',input('param.id/d'))->find();
        }else{
            $info = array(
                'id'=>'',
                'guize'=>'1、点击按钮“立即抢购”可直接付款购买。
2、点击“专属海报”登记后，生成自己的专属海报给好友，好友下单自己可获得商家提供的奖品。
3、名额有限，售完即止，欲购从速。
4、购买成功后到店核销使用即可。',
                'starttime'=>time()-100,
                'endtime'=>time()+86400-100,
//                'use_type'=>1,
//                'usescore'=>0,
//                'usemoney'=>0,
                'status'=>1,
                'sharepic'=>'',
                'gettj'=>'-1',
                'j1mc'=>bid ==0?'1':'',
                'j1pic'=>PRE_URL.'/static/img/dzp/jiangpin.png',
                'j1sl'=>'5',
                'j1yj'=>'0',
                'j1tp'=>bid ==0?'2':'3',
                'j2mc'=>bid ==0?'1':'',
                'j2pic'=>PRE_URL.'/static/img/dzp/jiangpin.png',
                'j2sl'=>'10',
                'j2yj'=>'0',
                'j2tp'=>bid ==0?'2':'3',
                'j3mc'=>bid ==0?'1':'',
                'j3pic'=>PRE_URL.'/static/img/dzp/jiangpin.png',
                'j3tp'=>bid ==0?'2':'3',
                'j4pic'=>PRE_URL.'/static/img/dzp/jiangpin.png',
                'j4tp'=>bid ==0?'2':'3',
                'j5pic'=>PRE_URL.'/static/img/dzp/jiangpin.png',
                'j5tp'=>bid ==0?'2':'3',
                'j6pic'=>PRE_URL.'/static/img/dzp/jiangpin.png',
                'j6tp'=>bid ==0?'2':'3',
                'j7pic'=>PRE_URL.'/static/img/dzp/jiangpin.png',
                'j7tp'=>bid ==0?'2':'3',
                'j8pic'=>PRE_URL.'/static/img/dzp/jiangpin.png',
                'j8tp'=>bid ==0?'2':'3',
                'j3sl'=>'30',
                'j3yj'=>'0',
                'formcontent'=>'[{"key":"input","val1":"姓名","val2":"","val3":"1"},{"key":"input","val1":"手机号","val2":"","val3":"1"}]',
                 'xn_bgnum'=>0,
                'xn_zfnum' => 0,
                'xn_buynum' => 0,
                'xn_joinnum' => 0,
                'price' => 0
            );
            $info['name'] = '';
            $info['bgpic'] = '';
            $info['fmpic'] = PRE_URL.'/static/img/hbtk/fmpic.png';
            $info['bgcolor']='#FED7EC';
            $info['color2'] = '#9C79ED';
            $info['color1'] = '#FF4A9B';
        }
        $info['type'] = 'hbtk';
        $info['gettj'] = explode(',',$info['gettj']);
        View::assign('info',$info);

        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        View::assign('memberlevel',$memberlevel);
        View::assign('score_weishu',$this->score_weishu);
        return View::fetch();
    }
    //保存
    public function save(){
        $info = input('post.info/a');
        $info['starttime'] = strtotime($info['starttime']);
        $info['endtime'] = strtotime($info['endtime']);
        $info['gettj'] = implode(',',$info['gettj']);
        $datatype = input('post.datatype/a');
        $dataval1 = input('post.dataval1/a');
        $dataval2 = input('post.dataval2/a');
        $dataval3 = input('post.dataval3/a');
        $dhdata = array();
        foreach($datatype as $k=>$v){
            if($dataval3[$k]!=1) $dataval3[$k] = 0;
            $dhdata[] = array('key'=>$v,'val1'=>$dataval1[$k],'val2'=>$dataval2[$k],'val3'=>$dataval3[$k]);
        }
        $info['formcontent'] = jsonEncode($dhdata);

        if($info['id']){
            $info['updatetime'] = time();
            Db::name('hbtk_activity')->where('aid',aid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog('编辑拓客活动'.$info['id']);
        }else{
            $info['aid'] = aid;
            $info['bid'] = bid;
            $info['createtime'] = time();
            $id = Db::name('hbtk_activity')->insertGetId($info);
            \app\commons\System::plog('添加拓客活动'.$id);
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');
        Db::name('hbtk_activity')->where('aid',aid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除拓客活动'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
    //记录
    public function record(){
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
            if(input('param.hid')){
                $where[] = ['hid','=',input('param.hid/d')];
            }
            if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
            if(input('param.pid')) $where[] = ['pid','=',input('param.pid')];
            if(input('param.nickname')) $where[] = ['nickname','like','%'.input('param.nickname').'%'];
            if(input('param.linkman')) $where[] = ['formdata','like','%'.input('param.linkman').'%'];
            if(input('param.jxmc')) $where[] = ['jxmc','like','%'.input('param.jxmc').'%'];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['createtime','>=',strtotime($ctime[0])];
                $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
            }
            if(input('?param.status') && input('param.status')!==''){
                $where[] = ['status','=',input('param.status')];
            }
            if(input('param.type')){
                $where[] = ['status','=',1];
            }
            $count = 0 + Db::name('hbtk_order')->where($where)->count();
            $data = Db::name('hbtk_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){
                $formdataArr = array();
                $formdata = json_decode($v['formdata'],true);
                foreach($formdata as $k2=>$v2){
                    $formdataArr[] = $k2.'：'.$v2;
                }
                $data[$k]['formdata'] = implode('<br>',$formdataArr);
                $data[$k]['yqnum'] = 0 + Db::name('hbtk_order')->where('aid',aid)->where('pid',$v['mid'])->where('hid',$v['hid'])->count();
                $parent = Db::name('member')->where('id',$v['pid'])->field('id,nickname,headimg')->find();
                $data[$k]['parent'] =$parent?$parent:[]; 
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        $type = input('param.type');
        $typearr = ['参与用户','购买日志'];
        $type_name = $typearr[$type];
        View::assign('type_name',$type_name);
        return View::fetch();
    }

    //改状态
    public function setst(){
        $ids = input('post.ids/a');
        $st = input('post.st/d');
        Db::name('hbtk_order')->where('aid',aid)->where('id','in',$ids)->update(['status'=>$st]);
        \app\commons\System::plog('修改拓客活动状态'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'修改成功']);
    }
    //领取记录导出
    public function recordexcel(){
        $where = [];
        $where[] = ['aid','=',aid];
        if(input('param.hid')){
            $where[] = ['hid','=',input('param.hid/d')];
        }
        $list = Db::name('hbtk_order')->where($where)->select()->toArray();

        $title = array();
        $title[] = '序号';
        $title[] = '活动ID';
        $title[] = '活动名称';
        $title[] = t('会员').'ID';
        $title[] = '昵称';
        $title[] = '奖品';
        $title[] = '兑奖信息';
        $title[] = '领取时间';
        $title[] = '状态';
        $title[] = '备注';
        $data = array();

        foreach($list as $v){
            $formdataArr = [];
            $formdata = json_decode($v['formdata'],true);
            if($formdata){
                foreach ($formdata as $key=>$val) {
                    $formdataArr[] = $key.'：'.$val;
                }
            }

            $formdatastr = implode("\r\n",$formdataArr);
            $tdata = array();
            $tdata[] = $v['id'];
            $tdata[] = $v['hid'];
            $tdata[] = $v['name'];
            $tdata[] = $v['mid'];
            $tdata[] = $v['nickname'];
            $tdata[] = $v['jxmc'];
//			$tdata[] = $v['linkman'] ? $v['linkman'].'('.$v['tel'].')':'';
            $tdata[] = $formdatastr;
            $tdata[] = date('Y-m-d H:i:s',$v['createtime']);
            $status = '';
            if($v['jx']==0){
                $status = '未中奖';
            }elseif($v['status']==1){
                $status = '已领取';
            }elseif($v['status']==0){
                $status = '未领取';
            }
            $tdata[] = $status;
            $tdata[] = $v['remark'];
            $data[] = $tdata;
        }
        $this->export_excel($title,$data);
    }
    //删除
    public function recorddel(){
        $ids = input('post.ids/a');
        Db::name('hbtk_order')->where('aid',aid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除拓客活动记录'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }
    //分享日志
    public function sharelog(){
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
            if(input('param.hid')){
                $where[] = ['hid','=',input('param.hid/d')];
            }
            if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
            if(input('param.nickname')) $where[] = ['nickname','like','%'.input('param.nickname').'%'];
            if(input('param.linkman')) $where[] = ['formdata','like','%'.input('param.linkman').'%'];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['createtime','>=',strtotime($ctime[0])];
                $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
            }
            $count = 0 + Db::name('hbtk_sharelog')->where($where)->count();
            $data = Db::name('hbtk_sharelog')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach ($data as &$val){
                $member = Db::name('member')->where('id',$val['mid'])->field('id,nickname,headimg')->find();
                $val['headimg'] = $member['headimg'];
                $val['nickname'] = $member['nickname'];
                $activity = Db::name('hbtk_activity')->where('id',$val['hid'])->field('id,name')->find();
                $val['name'] = $activity['name'];
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    //返利记录
    public function fanlilog(){
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
            $where[] = ['status','=',2];
            if(input('param.hid')){
                $where[] = ['hid','=',input('param.hid/d')];
            }
            if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
            if(input('param.nickname')) $where[] = ['nickname','like','%'.input('param.nickname').'%'];
            if(input('param.linkman')) $where[] = ['formdata','like','%'.input('param.linkman').'%'];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['createtime','>=',strtotime($ctime[0])];
                $where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
            }
            
            $count = 0 + Db::name('hbtk_order')->where($where)->count();
            $data = Db::name('hbtk_order')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach ($data as &$val){
                $parent = Db::name('member')->where('id',$val['pid'])->field('id,nickname,headimg')->find();
                $val['p_headimg'] = $parent['headimg'];
                $val['p_nickname'] = $parent['nickname'];
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
}