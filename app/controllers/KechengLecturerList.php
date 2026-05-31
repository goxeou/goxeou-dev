<?php
//custom_file(kecheng_lecturer)
// +----------------------------------------------------------------------
// | 知识付费-讲师课程管理
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
class KechengLecturerList extends Common
{
    public $lecturerid = 0;
    public $hasallauth    = false;//是否有总权限
    public function initialize(){
        parent::initialize();
        if(bid > 0) showmsg('无访问权限');
        $this->lecturerid = $this->user['lecturerid'];
        if($this->user['isadmin'] > 0){
            $this->hasallauth = true;
        }
    }
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
            $where[] = ['chaptertype','=',2];
            $where[] = ['bid','=',0];
            if($this->lecturerid){
                $where[] = ['lecturerid','=',$this->lecturerid];
            }else{
                if(!$this->hasallauth){
                    $where[] = ['id','=',0];
                }
                if(input('param.lecturerid')) $where[] = ['lecturerid','=',input('param.lecturerid')];
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
            $data  = Db::name('kecheng_list')->where($where)->page($page,$limit)->order($order)->select()->toArray();
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

                $data[$k]['lecturerinfo'] = '';
                if($this->hasallauth && $v['lecturerid']){
                    $lecturer = Db::name('kecheng_lecturer')->where('id',$v['lecturerid'])->where('aid',aid)->field('id,mid,headimg,nickname,tel')->find();
                    if($lecturer){
                        $data[$k]['lecturerinfo'] = '讲师ID：'.$lecturer['id'].' '.$lecturer['nickname'].'<br> 手机号'.$lecturer['tel'];
                    }else{
                        $data[$k]['lecturerinfo'] = '已失效';
                    }
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
        //讲师模式
        $lecturers = [];
        if($this->hasallauth){
            $lecturers = Db::name('kecheng_lecturer')->where('aid',aid)->field('id,nickname,tel')->order('sort desc,id')->select()->toArray(); 
        }
        View::assign('hasallauth',$this->hasallauth);
        View::assign('lecturers',$lecturers);
        return View::fetch();
    }
    //编辑商品
    public function edit(){
        if(input('param.id')){
            $where = [];
            $where[] = ['id','=',input('param.id/d')];
            if($this->lecturerid){
                $where[] = ['lecturerid','=',$this->lecturerid];
            }
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            $info = Db::name('kecheng_list')->where($where)->find();
            if(!$info) showmsg('课程不存在');
            if(bid != 0 && $info['bid']!=bid) showmsg('无权限操作');
            $bid = $info['bid'];
            //查询章节
            if($info['chapterid']){
                $chapter = Db::name('kecheng_chapter')->where('id',$info['chapterid'])->find();
                if($chapter){
                    $info['freecontent']   = $chapter['freecontent'];
                    $info['video_url']     = $chapter['video_url'];
                    $info['video_duration']= $chapter['video_duration'];
                    if(getcustom('video_speed')){
                        $info['isspeed']   = $chapter['isspeed'];
                    }
                    $info['isjinzhi']      = $chapter['isjinzhi'];
                }
            }
        }else{
            $bid = bid;
            $info = ['id'=>''];
        }
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

        if(getcustom('plug_businessqr') && bid != 0) {
            $aglevellist = Db::name('member_level')->where('aid',aid)->where('show_business',1)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
            $levellist = Db::name('member_level')->where('aid',aid)->where('show_business',1)->order('sort,id')->select()->toArray();
        } else {
            $aglevellist = Db::name('member_level')->where('aid',aid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
            $levellist = Db::name('member_level')->where('aid',aid)->order('sort,id')->select()->toArray();
        }
        if(getcustom('kecheng_free_memberlevel')){
            $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
            $default_cid = $default_cid ? $default_cid : 0;
            $memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
            View::assign('memberlevel',$memberlevel);
            $info['mianfei_gettj'] = explode(',',$info['mianfei_gettj']);
        }
        $default_cid = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $default_cid = $default_cid ? $default_cid : 0;
        $aglevellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->where('can_agent','<>',0)->order('sort,id')->select()->toArray();
        $levellist = Db::name('member_level')->where('aid',aid)->where('cid', $default_cid)->order('sort,id')->select()->toArray();
        $info['lvprice_data'] = json_decode($info['lvprice_data'], true);
        View::assign('levellist',$levellist);
        View::assign('aglevellist',$aglevellist);
        View::assign('clist',$clist);
        View::assign('info',$info);

        //讲师模式
        $lecturers = [];
        if($this->hasallauth){
            $lecturers = Db::name('kecheng_lecturer')->where('aid',aid)->where('checkstatus',1)->where('status',1)->field('id,nickname,tel')->order('sort desc,id')->select()->toArray(); 
        }
        View::assign('hasallauth',$this->hasallauth);
        View::assign('lecturers',$lecturers);
        return View::fetch();
    }
    //保存课程
    public function save(){
        //关联的章节ID
        $chapterid = 0;
        if(input('post.id')){
            $where = [];
            $where[] = ['id','=',input('post.id/d')];
            if($this->lecturerid){
                $where[] = ['lecturerid','=',$this->lecturerid];
            }
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            $product = Db::name('kecheng_list')->where($where)->find();
            if(!$product) showmsg('课程不存在');
            if(bid != 0 && $product['bid']!=bid) showmsg('无权限操作');
            $chapterid = $product['chapterid']??0;
        }
        $info = input('post.info/a');
        $info['freedetail'] = $info['freedetail']?\app\commons\Common::geteditorcontent($info['freedetail']):'';
        $info['detail']     = $info['detail']?\app\commons\Common::geteditorcontent($info['detail']):'';
        $data = array();
        $data['name']   = $info['name'];
        $data['pic']    = $info['pic'];
        $data['pics']   = $info['pics'];
        $data['cid']    = $info['cid'];
        $data['pcid']   = $info['pcid'];
        $data['kctype'] = $info['kctype'];//课程类型
        if(!$data['pcid']) $data['pcid'] = '0';
        if(isset($info['detail_text'])){
            $data['detail_text'] = $info['detail_text'];
        }
        if(isset($info['detail_pics'])){
            $data['detail_pics'] = $info['detail_pics'];
        }
        
        $data['status'] = $info['status'];
        $data['detail'] = $info['detail'];
        if(!$product) $data['createtime'] = time();
        $data['price'] = $info['price'];
        $data['market_price'] = $info['market_price'];
        $data['isdt']     = 0;

        $data['lvprice'] = $info['lvprice'];
        if($info['lvprice']==1){
            $data['lvprice_data'] = jsonEncode($info['lvprice_data']);
            $data['price'] = array_values($info['lvprice_data'])[0]['money_price'];
        }

        //讲师模式
        if($this->hasallauth){
            $data['join_num'] = $info['join_num']>0 ? $info['join_num'] : 0;
            $data['sort'] = $info['sort'];
            $data['lecturerid'] = $info['lecturerid'];

            $data['commissionset'] = $info['commissionset'];
            $data['commissiondata1'] = jsonEncode(input('post.commissiondata1/a'));
            $data['commissiondata2'] = jsonEncode(input('post.commissiondata2/a'));
            $data['commissiondata3'] = jsonEncode(input('post.commissiondata3/a'));
        }else{
            if($this->lecturerid){
                $data['lecturerid'] = $this->lecturerid;
            }
        }

        $data['chaptertype']     = 2;//是否关联章节 1：关联章节 2：不关联章节（不关联章节默认创建一个默认章节）
        $data['isdt']        = 0;//无答题
        $data['freecontent'] = $info['freecontent'];

        if($product){
            Db::name('kecheng_list')->where('id',$product['id'])->where('aid',aid)->update($data);
            $proid = $product['id'];
            \app\commons\System::plog('课程内容编辑'.$proid);
        }else{
            $data['aid'] = aid;
            $data['bid'] = bid;
            $proid = Db::name('kecheng_list')->insertGetId($data);
            \app\commons\System::plog('课程内容编辑'.$proid);
        }

        //讲师模式 关联的章节s
        if($chapterid){
            $chapter = Db::name('kecheng_chapter')->where('id',$chapterid)->where('aid',aid)->where('bid',bid)->find();
            if(!$chapter){
                $chapterid = 0;
            }
        }
        $data = array();
        $data['name']    = $info['name'];
        $data['pic']     = $info['pic'];
        $data['kcid']    = $proid;
        $data['sort']    = 0;
        $data['status']  = 1;
        $data['detail']  = $info['detail'];
        //$data['jumpurl'] = $info['jumpurl'];
        $data['kctype']  = $info['kctype'];
        $data['freecontent']  = $info['freecontent'];

        if($data['kctype']==1) $data['video_duration'] = '';
        if($data['kctype']==2) $data['video_duration'] = $info['voice_duration'];
        if($data['kctype']==3) $data['video_duration'] = $info['video_duration'];
        if($data['kctype']==1){
            $data['voice_url'] = '';
            $data['video_url'] = '';
        }

        $data['ismianfei'] = $info['ismianfei']??0;
        if ($data['ismianfei'] == 1) {
            if(getcustom('video_free_time')){
                $data['mianfei_unit'] = $info['mianfei_unit']??1;
                $mianfei_time = intval($info['mianfei_time']);
                $max_time = intval($info['video_duration']);
                if (!$info['mianfei_time'] || $mianfei_time > $max_time || ($data['mianfei_unit'] == 2 && $info['mianfei_time'] * 60 > $max_time)) {
                    $data['mianfei_time'] = $max_time;
                    $data['mianfei_unit'] = 1;
                } else {
                    $data['mianfei_time'] = $mianfei_time;
                }
            }
        }

        if($data['kctype']==2){
            $data['voice_url'] = $info['voice_url'];
            $data['video_url'] = '';
        }
        if($data['kctype']==3){
            $data['voice_url'] = '';
            $data['video_url'] = $info['video_url'];
        }
        $data['isjinzhi'] = $info['isjinzhi'];
        if(getcustom('video_speed')){
            $data['isspeed'] = $info['isspeed'];
        }

        $data['chaptertype']     = 2;//是否关联章节 1：关联章节 2：不关联章节（不关联章节默认创建一个默认章节）
        if($chapterid){
            Db::name('kecheng_chapter')->where('id',$chapterid)->where('aid',aid)->update($data);
            //\app\commons\System::plog('章节内容编辑'.$proid);
        }else{
            $data['aid'] = aid;
            $data['bid'] = bid;
            $data['createtime'] = time();
            $chapterid = Db::name('kecheng_chapter')->insertGetId($data);
            $up = Db::name('kecheng_list')->where('id',$proid)->update(['chapterid'=>$chapterid]);
            //\app\commons\System::plog('章节内容编辑'.$proid);
        }
        //关联的章节e

        $old_sales = 0;
        if($product){
            $bid = $product['bid'];
            $old_sales = $product['join_num'];
        }else{
            $bid = $info['bid']?:bid;
        }
        //更新商户虚拟销量
        // $sales = $info['join_num']-$old_sales;
        // if($sales!=0){
        //     \app\models\Payorder::addSales(0,'sales',aid,$bid,$sales);
        // }
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
        $where = [];
        $where[] = ['id','=',id];
        if($this->lecturerid){
            $where[] = ['lecturerid','=',$this->lecturerid];
        }
        $where[] = ['aid','=',aid];
        Db::name('kecheng_list')->where($where)->update(['ischecked'=>$st,'check_reason'=>$reason]);
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
        if($this->lecturerid){
            $where[] = ['lecturerid','=',$this->lecturerid];
        }
        $prolist = Db::name('kecheng_list')->where($where)->select();
        foreach($prolist as $pro){
            Db::name('kecheng_list')->where('id',$pro['id'])->delete();
            Db::name('kecheng_chapter')->where('id',$pro['chapterid'])->delete();
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
