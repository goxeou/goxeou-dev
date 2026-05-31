<?php


// +----------------------------------------------------------------------
// | 页面设计
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\Log;
use think\facade\View;
use think\facade\Db;

class DesignerPage extends Common
{
    public $type = 0;//类型 0 常规设计页面 1模板库 2:子账号模板库（模板库类型同样是1）
    public $into = 0;//进入方式 0：默认普通 1：控制台
    public $template_auth = 0;//模板权限 0：无模板权限 1：主账号权限 2:子账号权限
    public function initialize(){
        parent::initialize();
        $type = input('?param.type')?input('param.type/d'):0;
        $into = input('?param.into')?input('param.into/d'):0;
        if(getcustom('design_template')){
            if(aid == 1 && $type == 1 && $into == 1){
                $this->template_auth = 1;
            }
        }
         if(getcustom('design_template_cat_create')){
            //处理子账号模板库
            if(aid != 1 && !bid && ($this->auth_data == 'all' || in_array('AddTemplateCat',$this->auth_data))){
                $this->template_auth = 2;
            }
        }
        $this->type = $type;
        $this->into = $into;
    }
    //列表
    public function index(){
        $page = input('param.page');
        $limit = input('param.limit');
        if(input('param.field') && input('param.order')){
            $order = input('param.field').' '.input('param.order');
        }else{
            $order = Db::raw('if(ishome=0,999,ishome),id');
        }
        
        $type = $this->type;//0 常规设计页面 1模板库 2:子账号模板库（模板库类型同样是1）
        if(getcustom('design_template_cat_create')){
            if($type == 2 && $this->template_auth != 2){
                showmsg('无访问权限');
            }
        }
        $into = $this->into;
        if(bid>0){
            //还原商家设置的个人中心页面
            Db::name('designerpage')->where('aid',aid)->where('bid',bid)->where('ishome',2)->update(['ishome'=>0]);
        }
        $where = array();
        if(!$type){
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
        }else{
            $aid = 1;$bid=0;
             if(getcustom('design_template_cat_create')){
                //处理主账号和子账号模板库
                if(aid != 1){
                    //多商户只能读取自己站点
                    if(bid || (!bid && $type == 2)){
                        $aid = aid;
                    }
                }
            }
            //重置aid 和 bid
            $where[] = ['aid','=',$aid];
            $where[] = ['bid','=',$bid];
        }

        if(getcustom('design_cat')){
            $cid = input('param.cid',0);
        }
        if(getcustom('design_template')){
            //查看模板库
            if($type >= 1){
                $where[] = ['type','=',1];

                //查询显示的分类
                $catwhere = [];
                if(getcustom('design_template_cat_create')){
                    //处理主账号和子账号模板库
                    if(aid != 1){
                        if(getcustom('design_cat_showtj')){
                            if(bid){
                                $where2 = "(aid = ".aid." and bid = 0 and (find_in_set('-1',showtj2) or find_in_set('".bid."',showtj2)))";
                            }else{
                                if($type == 1){
                                    $where2 = "(aid = 1 and bid = 0 and (find_in_set('-1',showtj) or find_in_set('".aid."',showtj)))";
                                }else{
                                    $where2 = "aid = ".aid." and bid = 0";
                                }
                            }
                        }else{
                            if(bid){
                                $where2 = "aid = ".aid." and bid = 0";
                            }else{
                                if($type == 1){
                                    $where2 = "aid = 1 and bid = 0";
                                }else{
                                    $where2 = "aid = ".aid." and bid = 0";
                                }
                            }
                        }
                        $catwhere[] = Db::raw($where2);
                    }else{
                        $catwhere[] = ['aid','=',1];
                        $catwhere[] = ['bid','=',0];
                        if(getcustom('design_cat_showtj')){
                            //主账号对自己商家分类控制
                            if(bid){
                                $where2 = "(find_in_set('-1',showtj2) or find_in_set('".bid."',showtj2))";
                                $catwhere[] = Db::raw($where2);
                            }
                        }
                    }
                }else{
                    $catwhere[] = ['aid','=',1];
                    $catwhere[] = ['bid','=',0];
                }
                $catwhere[] = ['status','=',1];
                $catwhere[] = ['is_del','=',0];
                $catwhere[] = ['type','=',1];
                $catids = Db::name('designerpage_category')->where($catwhere)->order('sort desc')->column('id');
                if($catids){
                    array_push($catids,0);
                }else{
                    $catids = [0];
                }
                if($cid> 0){
                    if(in_array($cid,$catids)){
                        $where[] = ['cid','=',$cid];
                    }else{
                        $where[] = ['cid','=',0];
                    }
                }else{
                    $where[] = ['cid','in',$catids];
                }
            }else{
                $where[] = ['type','=',0];
                if($cid){
                    $where[] = ['cid','=',$cid];
                }
            }
        }else{
            if($cid){
                $where[] = ['cid','=',$cid];
            }
        }
        if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
        if(input('?param.ishome')) $where[] = ['ishome','=',input('param.ishome')];
        if(request()->isAjax()){
            $count = 0 + Db::name('designerpage')->where($where)->count();
            $data = Db::name('designerpage')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        $count = 0 + Db::name('designerpage')->where($where)->count();
        $datalist = Db::name('designerpage')->where($where)->limit(10)->order($order)->select()->toArray();
        View::assign('count',$count);
        View::assign('datalist',$datalist);
        if($this->auth_data == 'all' || in_array('DesignerPage/*',$this->auth_data) || in_array('DesignerPage/sethome',$this->auth_data)){
            $auth['setHome'] = true;
        }else{
            $auth['setHome'] = false;
        }
        if($this->auth_data == 'all' || in_array('DesignerPage/*',$this->auth_data) || in_array('DesignerPage/rehome',$this->auth_data)){
            $auth['rehome'] = true;
        }else{
            $auth['rehome'] = false;
        }
        if($this->auth_data == 'all' || in_array('DesignerPage/*',$this->auth_data) || in_array('DesignerPage/del',$this->auth_data)){
            $auth['del'] = true;
        }else{
            $auth['del'] = false;
        }

        $auth['edit'] = $auth['copy'] = $auth['add']  = true;
        $typeparam = '';
        if(getcustom('design_template')){
            $typeparam  = "&type=".$type.'&into='.$into;
            if($type >= 1){
                if(!$this->template_auth){
                    $auth['edit'] = $auth['add']  = $auth['del']  = false;
                }else{
                    if($this->template_auth == 2){
                        if($type == 1){
                            $auth['del']  = false;
                            $auth['edit'] = false;
                        }else{
                            $auth['copy'] = false;
                        }
                        $auth['add']  = false;
                    }else{
                        $auth['copy'] = false;
                    }
                }
            }

            //模板库切换
            $tab = (($into &&$into ==1) || (aid ==1 && !bid))?false:true;
            View::assign('tab',$tab);

            //是否显示模板库提示
            $template_tip = (($into &&$into ==1) || $type == 2)?true:false;
            View::assign('template_tip',$template_tip);
        }
        View::assign('typeparam',$typeparam);

        if(getcustom('design_cat')){
            $where = [];
            if(!$type){
                $where[] = ['aid','=',aid];
                $where[] = ['bid','=',bid];
                if(getcustom('design_template')){
                    $where[] = ['type','=',0];
                }
            }else{
                if(getcustom('design_template_cat_create')){
                    if(aid != 1){
                        if(getcustom('design_cat_showtj')){
                            if(bid){
                                $where2 = "(aid = ".aid." and bid = 0 and (find_in_set('-1',showtj2) or find_in_set('".bid."',showtj2)))";
                            }else{
                                if($type == 1){
                                    $where2 = "(aid = 1 and bid = 0 and (find_in_set('-1',showtj) or find_in_set('".aid."',showtj)))";
                                }else{
                                    $where2 = "aid = ".aid." and bid = 0";
                                }
                            }
                        }else{
                            if(bid){
                                $where2 = "aid = ".aid." and bid = 0";
                            }else{
                                if($type == 1){
                                    $where2 = "aid = 1 and bid = 0";
                                }else{
                                    $where2 = "aid = ".aid." and bid = 0";
                                }
                            }
                        }
                        $where[] = Db::raw($where2);
                    }else{
                        $where[] = ['aid','=',1];
                        $where[] = ['bid','=',0];
                        if(getcustom('design_cat_showtj')){
                            //主账号对自己商家分类控制
                            if(bid){
                                $where2 = "(find_in_set('-1',showtj2) or find_in_set('".bid."',showtj2))";
                                $where[] = Db::raw($where2);
                            }
                        }
                    }
                }else{
                    $where[] = ['aid','=',1];
                    $where[] = ['bid','=',0];
                }
                $where[] = ['type','=',1];
            }
            $where[] = ['status','=',1];
            $where[] = ['is_del','=',0];
            $cat_list = Db::name('designerpage_category')->where($where)->field('id,name')->order('sort desc')->select()->toArray();
            View::assign('cat_list',$cat_list);
        }
        
 
        
        
        
        
        View::assign('auth',$auth);
        View::assign('type',$type);
        View::assign('into',$into);
        View::assign('template_auth',$this->template_auth);
        return View::fetch();
    }
    //编辑new
    public function editnew(){
        $type = $this->type;
        $into = $this->into;
        if(request()->isAjax()){
            if(getcustom('design_template')){
                if($type >=1){
                    if(!$this->template_auth){
                        return json(['status'=>0,'msg'=>'无操作权限']);
                    }
                    if($type == 2 && $this->template_auth != 2){
                        return json(['status'=>0,'msg'=>'无操作权限']);
                    }
                }
            }
            $id = input('post.id/d');
            $content = input('post.content');
            $pageinfo = input('post.pageinfo');
            if($id){
                $designerpage = Db::name('designerpage')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
                if(getcustom('design_template')){
                    if($designerpage['type'] >=1 && !$this->template_auth){
                        return json(['status'=>0,'msg'=>'无操作权限']);
                    }
                }
            }
            $info = array();
            $info['content']  = \app\commons\Common::geteditorcontent($content,aid);
            $info['pageinfo'] = $pageinfo;
            $pageinfo = json_decode($pageinfo,true);
            $info['name'] = $pageinfo[0]['params']['title'];
            if(getcustom('design_template')){
                $info['type'] = $type?1:0;
            }
            if(getcustom('design_cat')){
                $info['cid'] = $pageinfo[0]['params']['cid']?$pageinfo[0]['params']['cid']:0;
            }
            if(!$designerpage){
                $info['createtime'] = time();
                $info['aid'] = aid;
                $info['bid'] = bid;
            }
            $info['updatetime'] = time();

            if($designerpage){
                Db::name('designerpage')->where('aid',aid)->where('bid',bid)->where('id',$designerpage['id'])->update($info);
                $pageid = $designerpage['id'];
                \app\commons\System::plog('编辑自定义页面'.$pageid);
            }else{
                $pageid = Db::name('designerpage')->insertGetId($info);
                \app\commons\System::plog('添加自定义页面');
            }
            return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index'),'pageid'=>$pageid]);
        }
        if(input('param.id')){
            $info = Db::name('designerpage')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
        }else{
            $info = array('id'=>'');
        }
        $uid = 0;
        $pagedata= $info['content'] && json_decode($info['content']) ? $info['content'] : '[]';
        $pagedata = \app\commons\System::initpagecontent($pagedata,aid,-1,'all','','','',0,0,$uid);
        $pageinfo= $info['pageinfo'] && json_decode($info['pageinfo']) ? $info['pageinfo'] : "[{id:'M0000000000000',temp:'topbar',params:{title:'默认标题',gid:0,'bgcolor':'#F5F5F5','needlogin':'0',quanxian:{'all':true},fufei:'0',showgg:0,ggcover:0,ggskip:1,guanggao:'".PRE_URL.'/static/imgsrc/picture-1.jpg'."',hrefurl:'',ggrenqun:{'0':true},cishu:'0'}}]";
        $dc = getcustom('restaurant') ? 1 : 0;
        View::assign('dc',$dc);
        if(getcustom('design_cat')){
            $where = [];
            if(!$type){
                $where[] = ['aid','=',aid];
                $where[] = ['bid','=',bid];
                if(getcustom('design_template')){
                    $where[] = ['type','=',0];
                }
            }else{
                if(getcustom('design_template_cat_create')){
                    $where[] = ['aid','=',aid];
                }else{
                    $where[] = ['aid','=',1];
                }
                $where[] = ['bid','=',0];
                $where[] = ['type','=',1];
            }
            $where[] = ['status','=',1];
            $where[] = ['is_del','=',0];
            $cat_list = Db::name('designerpage_category')->where($where)->order('sort desc')->select()->toArray();
            View::assign('cat_list',$cat_list);
        }
        $mendianlist = [];
        if(getcustom('show_location')){
            $sysset = Db::name('admin_set')->where('aid',aid)->find();
            if($sysset['mode']==3){
                $mendianlist = Db::name('mendian')->where('aid',aid)->where('bid',bid)->where('status',1)->select()->toArray();
            }
            View::assign('sysset',$sysset);
            View::assign('mendianlist',$mendianlist);
        }
        if(in_array($this->user['isadmin'],[1,2])){
            View::assign('isadmin',true);
        }

        $cat_type = 0;
        if(getcustom('design_template')){
            if($type >=1){
                $type     = $this->template_auth?$type:0;
                $cat_type = $this->template_auth?1:0;
            }
        }else{
            if(getcustom('design_cat')){
                $cat_type = 1;
            }
        }
        $areaBids = [];
        $mendian_upgrade = false;
        $platform = Db::name('admin')->where('id',aid)->value('platform');
    
        View::assign('platform',explode(',',$platform));
        View::assign('mendian_upgrade',$mendian_upgrade);
        View::assign('areaBids',$areaBids);
        View::assign('cat_type',$cat_type);
        View::assign('type',$type);
        View::assign('into',$into);
        View::assign('info',$info);
        View::assign('pagedata',$pagedata);
        View::assign('pageinfo',$pageinfo);

        $typeparam = '';
        if(getcustom('design_template')){
            $typeparam  = "&type=".$type.'&into='.$into;
        }
        View::assign('typeparam',$typeparam);
        return View::fetch();
    }
    //编辑
    public function edit(){
        $type = $this->type;//0常规设计页面 1模板库
        $into = $this->into;
        if(request()->isAjax()){
            if(getcustom('design_template')){
                if($type >=1){
                    if(!$this->template_auth){
                        return json(['status'=>0,'msg'=>'无操作权限']);
                    }
                    if($type == 2 && $this->template_auth != 2){
                        return json(['status'=>0,'msg'=>'无操作权限']);
                    }
                }
            }
            $id = input('post.id/d');
            $content = input('post.content');
            $pageinfo = input('post.pageinfo');
            if($id){
                $designerpage = Db::name('designerpage')->where('aid',aid)->where('bid',bid)->where('id',$id)->find();
                if(getcustom('design_template')){
                    if($designerpage['type'] >=1 && !$this->template_auth){
                        return json(['status'=>0,'msg'=>'无操作权限']);
                    }
                }
            }
            $info = array();
            $info['content'] = \app\commons\Common::geteditorcontent($content,aid);
            $info['pageinfo'] = $pageinfo;
            $pageinfo = json_decode($pageinfo,true);
            $info['name'] = $pageinfo[0]['params']['title'];
            if(getcustom('design_template')){
                $info['type'] = $type?1:0;
            }
            if(getcustom('design_cat')){
                $info['cid'] = $pageinfo[0]['params']['cid']?$pageinfo[0]['params']['cid']:0;
            }
            if(!$designerpage){
                $info['createtime'] = time();
                $info['aid'] = aid;
                $info['bid'] = bid;
            }
            $info['updatetime'] = time();

            if($designerpage){
                Db::name('designerpage')->where('aid',aid)->where('bid',bid)->where('id',$designerpage['id'])->update($info);
                $pageid = $designerpage['id'];
                \app\commons\System::plog('编辑自定义页面'.$pageid);
            }else{
                $pageid = Db::name('designerpage')->insertGetId($info);
                \app\commons\System::plog('添加自定义页面');
            }
            return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index'),'pageid'=>$pageid]);
        }
        if(input('param.id')){
            $info = Db::name('designerpage')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
        }else{
            $info = array('id'=>'');
        }
        $pagedata= $info['content'] && json_decode($info['content']) ? $info['content'] : '[]';
        $pagedata = \app\commons\System::initpagecontent($pagedata,aid,-1);
        $pageinfo= $info['pageinfo'] && json_decode($info['pageinfo']) ? $info['pageinfo'] : "[{id:'M0000000000000',temp:'topbar',params:{title:'默认标题',gid:0,'bgcolor':'#f5f5f5','needlogin':'0',quanxian:{'all':true},fufei:'0',showgg:0,guanggao:'".PRE_URL.'/static/imgsrc/picture-1.jpg'."',hrefurl:'',ggrenqun:{'0':true},cishu:'0'}}]";
        $dc = getcustom('restaurant') ? 1 : 0;
        View::assign('dc',$dc);
        if(getcustom('design_cat')){
            $where = [];
            if(!$type){
                $where[] = ['aid','=',aid];
                $where[] = ['bid','=',bid];
                if(getcustom('design_template')){
                    $where[] = ['type','=',0];
                }
            }else{
                if(getcustom('design_template_cat_create')){
                    $where[] = ['aid','=',aid];
                }else{
                    $where[] = ['aid','=',1];
                }
                $where[] = ['bid','=',0];
                $where[] = ['type','=',1];
            }
            $where[] = ['status','=',1];
            $where[] = ['is_del','=',0];
            $cat_list = Db::name('designerpage_category')->where($where)->order('sort desc')->select()->toArray();
            View::assign('cat_list',$cat_list);
        }
        $mendianlist = [];
        if(getcustom('show_location')){
            $sysset = Db::name('admin_set')->where('aid',aid)->find();
            if($sysset['mode']==3){
                $mendianlist = Db::name('mendian')->where('aid',aid)->where('bid',bid)->where('status',1)->select()->toArray();
            }
            View::assign('sysset',$sysset);
            View::assign('mendianlist',$mendianlist);
        }
        if(in_array($this->user['isadmin'],[1,2])){
            View::assign('isadmin',true);
        }

        $cat_type = 0;
        if(getcustom('design_template')){
            if($type >=1){
                $type     = $this->template_auth?$type:0;
                $cat_type = $this->template_auth?1:0;
            }
        }else{
            if(getcustom('design_cat')){
                $cat_type = 1;
            }
        }

        View::assign('cat_type',$cat_type);
        View::assign('type',$type);
        View::assign('into',$into);
        View::assign('info',$info);
        View::assign('pagedata',$pagedata);
        View::assign('pageinfo',$pageinfo);

        $typeparam = '';
        if(getcustom('design_template')){
            $typeparam  = "&type=".$type.'&into='.$into;
        }
        View::assign('typeparam',$typeparam);
        return View::fetch();
    }
	//编辑选项卡内容
	public function designer_tab(){
        $article_id = input('param.article_id');
		$pagetitle = input('param.tabname');
		$tabid = input('param.tabid');
		$tabindexid = input('param.tabindexid');

        $where = [];
        $where[] = ['aid','=',aid];
        if($article_id) $where[] = ['article_id','=',$article_id];
        $where[] = ['tabid','=',$tabid];
        $where[] = ['tabindexid','=',$tabindexid];

		$info = Db::name('designerpage_tab')->where($where)->find();
		$pagedata= $info['content'] && json_decode($info['content']) ? $info['content'] : '[]';
		View::assign('pagetitle',$pagetitle);
		View::assign('pagedata',$pagedata);

		return View::fetch();
	}
	public function designer_tab_save(){
        $article_id = input('param.article_id');
		$tabid = input('post.tabid');
		$tabindexid = input('post.tabindexid');
		$content = input('post.tabpagedata');

        $where = [];
        $where[] = ['aid','=',aid];
        if($article_id) $where[] = ['article_id','=',$article_id];
        $where[] = ['tabid','=',$tabid];
        $where[] = ['tabindexid','=',$tabindexid];

		$designerpage = Db::name('designerpage_tab')->where($where)->find();

		$info = array();
		$info['content'] = \app\commons\Common::geteditorcontent($content);
		if(!$designerpage){
			$info['createtime'] = time();
			$info['aid'] = aid;
            if($article_id) $info['article_id'] = $article_id;
			$info['tabid'] = $tabid;
			$info['tabindexid'] = $tabindexid;
		}
		$info['updatetime'] = time();
		if($designerpage){
            $where = [];
            $where[] = ['aid','=',aid];
            if($article_id) $where[] = ['article_id','=',$article_id];
            $where[] = ['tabid','=',$tabid];
            $where[] = ['tabindexid','=',$tabindexid];
			Db::name('designerpage_tab')->where($where)->update($info);
			$pageid = $designerpage['id'];
		}else{
			$pageid = Db::name('designerpage_tab')->insertGetId($info);
		}
		return json(['status'=>1,'msg'=>'操作成功','pageid'=>$pageid]);
	}

    //设置成主页
    public function sethome(){
        $id = input('post.id/d');
        $ishome = input('post.ishome/d');
        if(empty($id)) {
            return json(['status'=>0,'msg'=>'参数错误']);
        }

        Db::name('designerpage')->where('aid',aid)->where('bid',bid)->where('ishome',$ishome)->update(['ishome'=>0]);
        Db::name('designerpage')->where('aid',aid)->where('bid',bid)->where('id',$id)->update(['ishome'=>$ishome]);
        \app\commons\System::plog('设置设计页面为主页'.$id);
        return json(['status'=>1,'msg'=>'操作成功']);
    }
    //取消首页
    public function rehome(){
        $id = input('post.id/d');
        if(empty($id)) {
            return json(['status'=>0,'msg'=>'参数错误']);
        }
        Db::name('designerpage')->where('aid',aid)->where('bid',bid)->where('id',$id)->update(['ishome'=>0]);
        return json(['status'=>1,'msg'=>'操作成功']);
    }
    //复制
    public function copy(){
        $id = input('post.id/d');
        if(empty($id)) {
            return json(['status'=>0,'msg'=>'参数错误']);
        }

        $type = $this->type;
        $where = [];
        $where[] = ['id','=',$id];
        if(!$type){
            $where[] = ['aid','=',aid];
            $where[] = ['bid','=',bid];
            if(getcustom('design_template')){
                $where[] = ['type','=',0];
            }
        }else{
            if(getcustom('design_template')){
                //查询显示的分类
                $catwhere = [];
                if(getcustom('design_template_cat_create')){
                    //处理主账号和子账号模板库
                    if(aid != 1){
                        if(getcustom('design_cat_showtj')){
                            if(bid){
                                $where2 = "(aid = ".aid." and bid = 0 and (find_in_set('-1',showtj2) or find_in_set('".bid."',showtj2)))";
                            }else{
                                if($type == 1){
                                    $where2 = "(aid = 1 and bid = 0 and (find_in_set('-1',showtj) or find_in_set('".aid."',showtj)))";
                                }else{
                                    $where2 = "aid = ".aid." and bid = 0 ";
                                }
                            }
                        }else{
                            if(bid){
                                $where2 = "aid = ".aid." and bid = 0 ";
                            }else{
                                if($type == 1){
                                    $where2 = "aid = 1 and bid = 0 ";
                                }else{
                                    $where2 = "aid = ".aid." and bid = 0 ";
                                }
                            }
                        }
                        $catwhere[] = Db::raw($where2);
                    }else{
                        $catwhere[] = ['aid','=',1];
                        $catwhere[] = ['bid','=',0];
                        if(getcustom('design_cat_showtj')){
                            //主账号对自己商家分类控制
                            if(bid){
                                $where2 = "(find_in_set('-1',showtj2) or find_in_set('".bid."',showtj2))";
                                $catwhere[] = Db::raw($where2);
                            }
                        }
                    }
                }else{
                    $catwhere[] = ['aid','=',1];
                    $catwhere[] = ['bid','=',0];
                }
                $catwhere[] = ['status','=',1];
                $catwhere[] = ['is_del','=',0];
                $catwhere[] = ['type','=',1];
                $catids = Db::name('designerpage_category')->where($catwhere)->order('sort desc')->column('id');
                if($catids){
                    array_push($catids,0);
                }else{
                    $catids = [0];
                }
                if($cid> 0){
                    if(in_array($cid,$catids)){
                        $where[] = ['cid','=',$cid];
                    }else{
                        $where[] = ['cid','=',0];
                    }
                }else{
                    $where[] = ['cid','in',$catids];
                }
            }else{
                $where[] = ['aid','=',1];
                $where[] = ['bid','=',0];
            }
            $where[] = ['type','=',1];
        }
        $copydata = Db::name('designerpage')->where($where)->find();
        if(!$copydata){
            return json(['status'=>0,'msg'=>'页面不存在']);
        }
        $copydata['id'] = '';
        $copydata['aid'] = aid;
        $copydata['bid'] = bid;
        $copydata['ishome'] = 0;
        if($type){
            if(getcustom('design_template')){
                $copydata['type'] = 0;
            }
            if(getcustom('design_cat')){
                $copydata['cid'] = 0;
            }
        }
        //处理tab
        $content = json_decode($copydata['content'],true);
        if($content){
            foreach($content as &$cv){
                if($cv['temp'] == 'tab'){
                    $oldid = $cv['id'];
                    //生成新id
                    $cv['id'] = 'M'.date("YmdHis").rand(11111,99999);
                    //查询tab
                    $tab = Db::name('designerpage_tab')->where('tabid',$oldid)->where('aid',aid)->select()->toArray();
                    if($tab){

                        //更改tab中tabid为新id
                        foreach($tab as $tv){
                            unset($tv['id']);
                            unset($tv['createtime']);
                            unset($tv['updatetime']);

                            $tabdata = [];
                            $tabdata = $tv;
                            $tabdata['tabid']      = $cv['id'];
                            $tabdata['createtime'] = time();
                            //插入数据
                            $insert = Db::name('designerpage_tab')->insert($tabdata);
                        }
                    }
                }
                //如果是店招，则把系统logo为店铺logo
                if($cv['temp']=='shop' && bid>0){
                    $params = $cv['params'];
                    $params['bid'] = bid;
                    $shopinfo = $cv['shopinfo'];
                    $newshopinfo = Db::name('business')->where('aid',aid)->where('id',bid)->find();
                    $shopinfo['name'] = $newshopinfo['name'];
                    $shopinfo['desc'] = $newshopinfo['desc'];
                    $shopinfo['logo'] = $newshopinfo['logo'];
                    $shopinfo['tel'] = $newshopinfo['tel'];
                    $cv['shopinfo']  = $shopinfo;
                    $cv['params']  = $params;
                }
            }
            unset($cv);
            //重新赋值
            $copydata['content'] = json_encode($content);
        }

        $copydata['createtime'] = time();
        $copydata['updatetime'] = time();
        $id = Db::name('designerpage')->insertGetId($copydata);
        \app\commons\System::plog('复制设计页面'.$id);
        return json(['status'=>1,'msg'=>'操作成功']);
    }
    //删除
    public function del(){
        $id = input('post.id/d');
        if(empty($id)) {
            return json(['status'=>0,'msg'=>'参数错误']);
        }
        $info = Db::name('designerpage')->where('id',$id)->where('aid',aid)->where('bid',bid)->find();
        if(empty($info)) {
            return json(['status'=>0,'msg'=>'数据不存在']);
        }
        if(getcustom('design_template')){
            if($info['type'] >= 1 && !$this->template_auth){
                return json(['status'=>0,'msg'=>'无操作权限']);
            }
        }
        Db::name('designerpage')->where('id',$id)->where('aid',aid)->where('bid',bid)->delete();
        \app\commons\System::plog('删除设计页面'.$id);
        return json(['status'=>1,'msg'=>'删除成功']);
    }
    //文本自定义
    public function textset(){
        if(bid > 0) showmsg('无权限访问');
        if(request()->isPost()){
            $info = input('post.info/a');
            \think\facade\Cache::set('textset_'.aid,$info);
            \app\commons\System::plog('设置文本自定义');
            return json(['status'=>1,'msg'=>'设置成功','url'=>true]);
        }

        $textset = \think\facade\Cache::get('textset_'.aid);
        if(!$textset) $textset = ['余额'=>'余额','积分'=>'积分','佣金'=>'佣金','优惠券'=>'优惠券','会员'=>'会员'];
        View::assign('info',$textset);
        return View::fetch();
    }
    //预览
    public function preview(){
        $aid = aid;
        $id = input('param.id/d');
        $type = $this->type;
        if($id){
            $where = [];
            $where[] = ['id','=',$id];
            if(!$type){
                $where[] = ['aid','=',aid];
            }else{
                $aid = 1;
                if(getcustom('design_template_cat_create')){
                    //处理主账号和子账号模板库
                    if(aid != 1){
                        if(bid || (!bid &&$type == 2)){
                            $aid = aid;
                        }
                    }
                }
            }
            $where[] = ['aid','=',$aid];
            $info = Db::name('designerpage')->where($where)->find();
        }

        $pagedata= $info['content'] && json_decode($info['content']) ? $info['content'] : '[]';
        $pagedata = \app\commons\System::initpagecontent($pagedata,$aid,-1);
        $pageinfo= $info['pageinfo'] && json_decode($info['pageinfo']) ? $info['pageinfo'] : "[{id:'M0000000000000',temp:'topbar',params:{title:'',desc:'',img:'',kw:'',footer:'1',footermenu:'', floatico:'0',floatstyle:'right',floatwidth:'30px',floattop:'100px',floatimg:'',floatlink:''}}]";
        $sysset = Db::name('admin_set')->where('aid',aid)->find();
        if($info['bid']!=0){
            $business = Db::name('business')->where('aid',aid)->where('id',$info['bid'])->find();
            $sysset['name'] = $business['name'];
            $sysset['logo'] = $business['logo'];
        }
        $dc = getcustom('restaurant') ? 1 : 0;
        View::assign('dc',$dc);
        View::assign('info',$info);
        View::assign('pagedata',$pagedata);
        View::assign('pageinfo',$pageinfo);
        View::assign('sysset',$sysset);
        return View::fetch();
    }

    //选择坐标
    public function choosezuobiao(){
        return View::fetch();
    }
    //选择链接
    public function chooseurl(){
        //商品分类
        $clist = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',0)->order('sort desc,id')->select()->toArray();
        foreach($clist as $k=>$v){
            $child = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
            foreach($child as $k2=>$v2){
                $child2 = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
                $child[$k2]['child'] = $child2;
            }
            $clist[$k]['child'] = $child;
        }
        if(bid > 0){
            $clist2 = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray();
            foreach($clist2 as $k=>$v){
                $child = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
                foreach($child as $k2=>$v2){
                    $child2 = Db::name('shop_category2')->Field('id,name')->where('aid',aid)->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
                    $child[$k2]['child'] = $child2;
                }
                $clist2[$k]['child'] = $child;
            }
        }else{
            $clist2 = [];
        }
        //商品分组
        $glist = Db::name('shop_group')->where('aid',aid)->order('sort desc,id')->select()->toArray();
        //文章分类
        $aclist = Db::name('article_category')->Field('id,name,pic')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray();
        foreach($aclist as $k=>$v){
            $aclist[$k]['child'] = Db::name('article_category')->Field('id,name,pic')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
        }
        $bclist = Db::name('business_category')->where('aid',aid)->order('sort desc,id')->select()->toArray();
        View::assign('bclist',$bclist);
        View::assign('clist',$clist);
        View::assign('clist2',$clist2);
        View::assign('glist',$glist);
        View::assign('aclist',$aclist);
        //课程分类
        $kclist = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',0)->order('sort desc,id')->select()->toArray();
        foreach($kclist as $k=>$v){
          $child = Db::name('kecheng_category')->Field('id,name')->where('aid',aid)->where('bid',bid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
          $kclist[$k]['child'] = $child;
        }
        View::assign('kclist',$kclist);
        if(bid != 0){
            $bset = Db::name('business_sysset')->where('aid',aid)->find();
            View::assign('bset',$bset);
            return View::fetch('chooseurl2');
        }

        $rwvideoad = ['id'=>'','givescore'=>'','givemoney'=>'0','givemoneyparent'=>'0','givetimes'=>'1','givetimestotal'=>'999'];
        $oldhrefurl = urldecode(input('param.oldhrefurl'));
        if($oldhrefurl && strpos($oldhrefurl,'rewardedVideoAd::') === 0){
            $rwvideoadId = substr($oldhrefurl,17);
            $rwvideoad = Db::name('designerpage_rwvideoad')->where('id',$rwvideoadId)->find();
        }
        if($oldhrefurl && strpos($oldhrefurl,'miniProgram::') === 0){
            $rwvideoadId = explode('|',$oldhrefurl)[3];
            $rwvideoad = Db::name('designerpage_rwvideoad')->where('id',$rwvideoadId)->find();
        }
        View::assign('rwvideoad',$rwvideoad);
        //var_dump($oldhrefurl);

        //判断小程序 授权接入还是 手动接入
       
        $appinfo = \app\commons\System::appinfo(aid,'wx');

        $showmendianapply = false;
        View::assign('showmendianapply',$showmendianapply);
        View::assign('authtype',$appinfo['authtype']);
        return View::fetch();
    }
    //选择链接 用于设置多商户默认底部菜单
    public function chooseurl3(){
        //商品分类
        $clist = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',0)->order('sort desc,id')->select()->toArray();
        foreach($clist as $k=>$v){
            $child = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
            foreach($child as $k2=>$v2){
                $child2 = Db::name('shop_category')->Field('id,name')->where('aid',aid)->where('pid',$v2['id'])->order('sort desc,id')->select()->toArray();
                $child[$k2]['child'] = $child2;
            }
            $clist[$k]['child'] = $child;
        }
        //商品分组
        $glist = Db::name('shop_group')->where('aid',aid)->order('sort desc,id')->select()->toArray();
        //文章分类
        $aclist = Db::name('article_category')->Field('id,name,pic')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray();
        foreach($aclist as $k=>$v){
            $aclist[$k]['child'] = Db::name('article_category')->Field('id,name,pic')->where('aid',aid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray();
        }

        $bclist = Db::name('business_category')->where('aid',aid)->order('sort desc,id')->select()->toArray();
        View::assign('bclist',$bclist);
        View::assign('clist',$clist);
        View::assign('glist',$glist);
        View::assign('aclist',$aclist);
        return View::fetch();
    }

    public function api(){
        $areaBids = [];
        //区域代理账号登录，只可以看到他代理区域下的商品和商家
        $categroy_selmore = false;//分类多选
        $categoryseltype = input('?param.categoryseltype')?input('categoryseltype/d'):0;//单选还是多选 0：单选 1：多选
        if(input('param.op') == 'selectproduct'){//查询商品
            $where = [];
            $where[] = ['aid','=',aid];
            if($areaBids){
                $where[] = ['bid','in',$areaBids];
            }

            $inbackcids = false;//是否在$backcids内
            $nowtime = time();
            $nowhm   = date('H:i');
            $where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");

            $where[] = ['ischecked','=',1];
            if($_POST['kw']){
                $where[] = ['name','like','%'.$_POST['kw'].'%'];
            }
            if($_POST['cid']){
                $cid = $_POST['cid'];
                if($categroy_selmore && $categoryseltype == 1){
                    $newcids = [];
                    foreach($cid as $ck=>$cv){
                        //选择全部
                        if($ck == 0 && $cv == 'true'){
                            $newcids = [];
                            break;
                        }else{
                            if($cv == 'true'){
                                //查询是否在$backcids内
                                if($inbackcids && !in_array($ck,$backcids)) {
                                    if(!in_array(-1,$newcids)){
                                        $newcids[] = -1;
                                    }
                                }else{
                                    $newcids[] = $ck;
                                }
                            }
                        }
                    }
                    if($newcids){
                        $chidlc = Db::name('shop_category')->where('aid',aid)->where('pid','in',$newcids)->column('id');
                    }
                    $cids = $newcids;
                }else{
                    $cid = intval($cid);
                    if($cid){
                        $chidlc = Db::name('shop_category')->where('aid',aid)->where('pid',$cid)->column('id');
                        $cids = [$cid];
                    }else{
                        $cids = [];
                    }
                }

                if($cids){
                    if($chidlc){
                        $cids = array_merge($chidlc,$cids);
                        $whereCid = '(';
                        foreach($cids as $k => $c){
                            if(count($cids) == ($k + 1))
                                $whereCid .= "find_in_set({$c},cid)";
                            else
                                $whereCid .= " find_in_set({$c},cid) or ";
                        }
                        $where[] = Db::raw($whereCid . ')');
                    }else{
                        $whereCid = [];
                        foreach ($cids as $ck => $cc) {
                            //查询是否在$backcids内
                            if($inbackcids) {
                                if(in_array($cc,$backcids)){
                                    $whereCid[] = "find_in_set({$cc},cid)";
                                }
                            }else{
                                $whereCid[] = "find_in_set({$cc},cid)";
                            }
                        }
                        if(!empty($whereCid)){
                            $where[] = Db::raw(implode(' or ',$whereCid));
                        }else{
                            $where[] = ['cid','=',0];
                        }
                    }
                }
            }else{
                }
            if($_POST['cid2']){
                $cid2 = $_POST['cid2'];
                if($categroy_selmore && $categoryseltype == 1){
                    $newcids2 = [];
                    foreach($cid2 as $ck2=>$cv2){
                        //选择全部
                        if($ck2 == 0 && $cv2 == 'true'){
                            $newcids2 = [];
                            break;
                        }else{
                            if($cv2 == 'true'){
                                $newcids2[] = $ck2;
                            }
                        }
                    }
                    if($newcids2){
                        $chidlc2 = Db::name('shop_category2')->where('aid',aid)->where('pid','in',$newcids2)->column('id');
                        $cids2 = $newcids2;
                        if($chidlc2){
                            $cids2 = array_merge($chidlc2, $cids2);
                        }
                        $whereCid2 = '(';
                        foreach($cids2 as $k2=> $c2){
                            if(count($cids2) == ($k2+ 1))
                                $whereCid2 .= "find_in_set({$c2},cid2)";
                            else
                                $whereCid2 .= " find_in_set({$c2},cid2) or ";
                        }
                        $where[] = Db::raw($whereCid2 . ')');
                    }
                }else{
                    $cid2 = intval($cid);
                    if($cid2 > 0){
                        $chidlc2 = Db::name('shop_category2')->where('aid',aid)->where('pid',$cid2)->column('id');
                        if($chidlc2){
                            $chidlc2 = array_merge($chidlc2, [$cid2]);
                            $whereCid2 = '(';
                            foreach($chidlc2 as $k => $c){
                                if(count($chidlc2) == ($k + 1))
                                    $whereCid2 .= "find_in_set({$c},cid2)";
                                else
                                    $whereCid2 .= " find_in_set({$c},cid2) or ";
                            }
                            $where[] = Db::raw($whereCid2 . ')');
                        }else{
                            $where[] = Db::raw("find_in_set({$cid2},cid2)");
                        }
                    }
                }
            }
            if($_POST['giddata']){
                $_string = array();
                foreach($_POST['giddata'] as $gid=>$istrue){
                    $gid = strval($gid);
                    if($istrue=='true'){
                        if($gid == 'all'){
                            $_string[] = "1=1";
                        }elseif($gid == '0'){
                            $_string[] = "gid is null or gid=''";
                        }else{
                            $_string[] = "find_in_set({$gid},gid)";
                        }
                    }
                }
                if(!$_string){
                    $where2 = '0=1';
                }else{
                    $where2 = implode(" or ",$_string);
                }
            }else{
                $where2 = '1=1';
            }
            if(!bid){
                if($categroy_selmore && $categoryseltype == 1){
                    $bids = input('param.bid');
                    if($bids){
                        $newbids = [];
                        foreach($bids as $ck=>$cv){
                            //选择全部
                            if($ck == -1 && $cv == 'true'){
                                $newbids = [];
                                break;
                            }else{
                                if($cv == 'true'){
                                    $newbids[] = $ck;
                                }
                            }
                        }
                        if($newbids){
                            $where[] = ['bid','in',$newbids];
                        }
                    }
                }else{
                    if(input('param.bid')!==''){
                        $where[] = ['bid','=',input('param.bid/d')];
                    }
                }
            }else{
                $where[] = ['bid','=',bid];
            } 
            $order = 'sort desc';
            if($_POST['sortby'] == 'sort') $order = 'sort desc,id desc';
            if($_POST['sortby'] == 'createtimedesc') $order = 'createtime desc';
            if($_POST['sortby'] == 'createtime') $order = 'createtime';
            if($_POST['sortby'] == 'sales') $order = 'sales desc,sort desc';
            if($_POST['sortby'] == 'rand') $order = Db::raw('rand()');
            $field = 'id,bid,name,pic,sell_price,market_price,sales,price_type';
            $result = Db::name('shop_product')->field($field)->where($where)->where($where2)->order($order)->limit($_POST['proshownum'])->select()->toArray();
            if(!$result) $result = [];
            return $result;
        }
        if(input('param.op') == 'selectRestaurantproduct'){//查询菜品
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['status','=',1];
            $where[] = ['ischecked','=',1];
            if($_POST['kw']){
                $where[] = ['name','like','%'.$_POST['kw'].'%'];
            }
            if($areaBids){
                $where[] = ['bid','in',$areaBids];
            }
            if($_POST['cid']){
                $cid = intval($_POST['cid']);
                $where[] = Db::raw("find_in_set({$cid},cid)");
            }
            if($_POST['giddata']){
                $_string = array();
                foreach($_POST['giddata'] as $gid=>$istrue){
                    if($istrue=='true'){
                        if($gid == 'all'){
                            $_string[] = "1=1";
                        }elseif($gid == '0'){
                            $_string[] = "gid is null or gid=''";
                        }else{
                            $_string[] = "find_in_set({$gid},gid)";
                        }
                    }
                }
                if(!$_string){
                    $where2 = '0=1';
                }else{
                    $where2 = implode(" or ",$_string);
                }
            }else{
                $where2 = '1=1';
            }

            if(!bid){
                if($categroy_selmore && $categoryseltype == 1){
                    $bids = input('param.bid');
                    if($bids){
                        $newbids = [];
                        foreach($bids as $ck=>$cv){
                            //选择全部
                            if($ck == -1 && $cv == 'true'){
                                $newbids = [];
                                break;
                            }else{
                                if($cv == 'true'){
                                    $newbids[] = $ck;
                                }
                            }
                        }
                        if($newbids){
                            $where[] = ['bid','in',$newbids];
                        }
                    }
                }else{
                    if(input('param.bid')!==''){
                        $where[] = ['bid','=',input('param.bid/d')];
                    }
                }
            }else{
                $where[] = ['bid','=',bid];
            } 

            $order = 'sort desc';
            if($_POST['sortby'] == 'sort') $order = 'sort desc,id desc';
            if($_POST['sortby'] == 'createtimedesc') $order = 'create_time desc';
            if($_POST['sortby'] == 'createtime') $order = 'create_time';
            if($_POST['sortby'] == 'sales') $order = 'sales desc,sort desc';
            if($_POST['sortby'] == 'rand') $order = Db::raw('rand()');
            $result = Db::name('restaurant_product')->field('id,name,pic,sell_price,market_price,sales')->where($where)->where($where2)->order($order)->limit($_POST['proshownum'])->select()->toArray();
            if(!$result) $result = [];
            //echo M()->_sql();
            return $result;
        }
        if(input('param.op') == 'selectcollage'){//查询拼团商品
            $where = [];
            $where[] = ['aid','=',aid];
            if(!bid){
                if($categroy_selmore && $categoryseltype == 1){
                    $bids = input('param.bid');
                    if($bids){
                        $newbids = [];
                        foreach($bids as $ck=>$cv){
                            //选择全部
                            if($ck == -1 && $cv == 'true'){
                                $newbids = [];
                                break;
                            }else{
                                if($cv == 'true'){
                                    $newbids[] = $ck;
                                }
                            }
                        }
                        if($newbids){
                            $where[] = ['bid','in',$newbids];
                        }
                    }
                }else{
                    if(input('param.bid')!==''){
                        $where[] = ['bid','=',input('param.bid/d')];
                    }
                }
            }else{
                $where[] = ['bid','=',bid];
            }
            if($areaBids){
                $where[] = ['bid','in',$areaBids];
            }
            $where[] = ['status','=',1];
            $where[] = ['ischecked','=',1];

            if($_POST['kw']){
                $where[] = ['name','like','%'.$_POST['kw'].'%'];
            }
            if($_POST['cid']){
                $cid = $_POST['cid'];
                //如果开启多选
                if($categroy_selmore && $categoryseltype == 1){
                    $newcids = [];
                    foreach($cid as $ck=>$cv){
                        //选择全部
                        if($ck == 0 && $cv == 'true'){
                            $newcids = [];
                            break;
                        }else{
                            if($cv == 'true'){
                                $newcids[] = $ck;
                            }
                        }
                    }
                    if($newcids){
                        $chidlc = Db::name('collage_category')->where('aid',aid)->where('pid','in',$newcids)->select()->toArray();
                        $cids = $newcids;
                        if($chidlc){
                            foreach($chidlc as $c){
                                $cids[] = intval($c['id']);
                            }
                        }
                        $where[] = ['cid','in',$cids];
                    }
                }else{
                    $cid = intval($cid);
                    $chidlc = Db::name('collage_category')->where('aid',aid)->where('pid',$cid)->select()->toArray();
                    if($chidlc){
                        $cids = [$cid];
                        foreach($chidlc as $c){
                            $cids[] = intval($c['id']);
                        }
                        $where[] = ['cid','in',$cids];
                    }else{
                        $where[] = ['cid','=',$cid];
                    }
                }
            }
            if($_POST['giddata']){
                $_string = array();
                foreach($_POST['giddata'] as $gid=>$istrue){
                    if($istrue=='true'){
                        if($gid == '0'){
                            $_string[] = "gid is null or gid=''";
                        }else{
                            $_string[] = "find_in_set({$gid},gid)";
                        }
                    }
                }
                if(!$_string){
                    $where2 = '0=1';
                }else{
                    $where2 = implode(" or ",$_string);
                }
            }else{
                $where2 = '1=1';
            }

            $order = 'sort desc';
            if($_POST['sortby'] == 'sort') $order = 'sort desc,id desc';
            if($_POST['sortby'] == 'createtimedesc') $order = 'createtime desc';
            if($_POST['sortby'] == 'createtime') $order = 'createtime';
            if($_POST['sortby'] == 'sales') $order = 'sales desc,sort desc';
            $result = Db::name('collage_product')->field('id,name,pic,sell_price,market_price,sales,teamnum')->where($where)->where($where2)->order($order)->limit($_POST['proshownum'])->select()->toArray();
            if(!$result) $result = [];
            //echo Db::name()->getlastsql();
            return $result;
        }
        if(input('param.op') == 'selectcycle'){//查询周期购商品
            $where = [];
            $where[] = ['aid','=',aid];
            if($areaBids){
                $where[] = ['bid','in',$areaBids];
            }
            if(!bid){
                if($categroy_selmore && $categoryseltype == 1){
                    $bids = input('param.bid');
                    if($bids){
                        $newbids = [];
                        foreach($bids as $ck=>$cv){
                            //选择全部
                            if($ck == -1 && $cv == 'true'){
                                $newbids = [];
                                break;
                            }else{
                                if($cv == 'true'){
                                    $newbids[] = $ck;
                                }
                            }
                        }
                        if($newbids){
                            $where[] = ['bid','in',$newbids];
                        }
                    }
                }else{
                    if(input('param.bid')!==''){
                        $where[] = ['bid','=',input('param.bid/d')];
                    }
                }
            }else{
                $where[] = ['bid','=',bid];
            }
            $where[] = ['status','=',1];
            $where[] = ['ischecked','=',1];

            if($_POST['kw']){
                $where[] = ['name','like','%'.$_POST['kw'].'%'];
            }
            if($_POST['cid']){
                $cid = $_POST['cid'];
                //如果开启多选
                if($categroy_selmore && $categoryseltype == 1){
                    $newcids = [];
                    foreach($cid as $ck=>$cv){
                        //选择全部
                        if($ck == 0 && $cv == 'true'){
                            $newcids = [];
                            break;
                        }else{
                            if($cv == 'true'){
                                $newcids[] = $ck;
                            }
                        }
                    }
                    if($newcids){
                        $chidlc = Db::name('cycle_category')->where('aid',aid)->where('pid','in',$newcids)->select()->toArray();
                        $cids = $newcids;
                        if($chidlc){
                            foreach($chidlc as $c){
                                $cids[] = intval($c['id']);
                            }
                        }
                        $where[] = ['cid','in',$cids];
                    }
                }else{
                    $cid = intval($cid);
                    $chidlc = Db::name('cycle_category')->where('aid',aid)->where('pid',$cid)->select()->toArray();
                    if($chidlc){
                        $cids = [$cid];
                        foreach($chidlc as $c){
                            $cids[] = intval($c['id']);
                        }
                        $where[] = ['cid','in',$cids];
                    }else{
                        $where[] = ['cid','=',$cid];
                    }
                }
            }
            if($_POST['giddata']){
                $_string = array();
                foreach($_POST['giddata'] as $gid=>$istrue){
                    if($istrue=='true'){
                        if($gid == '0'){
                            $_string[] = "gid is null or gid=''";
                        }else{
                            $_string[] = "find_in_set({$gid},gid)";
                        }
                    }
                }
                if(!$_string){
                    $where2 = '0=1';
                }else{
                    $where2 = implode(" or ",$_string);
                }
            }else{
                $where2 = '1=1';
            }

            $order = 'sort desc';
            if($_POST['sortby'] == 'sort') $order = 'sort desc,id desc';
            if($_POST['sortby'] == 'createtimedesc') $order = 'createtime desc';
            if($_POST['sortby'] == 'createtime') $order = 'createtime';
            if($_POST['sortby'] == 'sales') $order = 'sales desc,sort desc';
            $result = Db::name('cycle_product')->field('id,name,pic,sell_price,market_price,sales,ps_cycle')->where($where)->where($where2)->order($order)->limit($_POST['proshownum'])->select()->toArray();
          if($result){
              foreach($result as $key=>&$val){
                  $ps_cycle = ['1' => '每日一期','2' => '每周一期' ,'3' => '每月一期'];
                  $val['pspl'] = $ps_cycle[$val['ps_cycle']];
              }
          }
            if(!$result) $result = [];
            //echo Db::name()->getlastsql();
            return $result;
        }
        if(input('param.op') == 'selectluckycollage'){//查询幸运拼团商品
            $where = [];
            $where[] = ['aid','=',aid];
            if($areaBids){
                $where[] = ['bid','in',$areaBids];
            }
            if(!bid){
                if($categroy_selmore && $categoryseltype == 1){
                    $bids = input('param.bid');
                    if($bids){
                        $newbids = [];
                        foreach($bids as $ck=>$cv){
                            //选择全部
                            if($ck == -1 && $cv == 'true'){
                                $newbids = [];
                                break;
                            }else{
                                if($cv == 'true'){
                                    $newbids[] = $ck;
                                }
                            }
                        }
                        if($newbids){
                            $where[] = ['bid','in',$newbids];
                        }
                    }
                }else{
                    if(input('param.bid')!==''){
                        $where[] = ['bid','=',input('param.bid/d')];
                    }
                }
            }else{
                $where[] = ['bid','=',bid];
            }
            $where[] = ['status','=',1];
            $where[] = ['ischecked','=',1];

            if($_POST['kw']){
                $where[] = ['name','like','%'.$_POST['kw'].'%'];
            }
            if($_POST['cid']){
                $cid = $_POST['cid'];
                //如果开启多选
                if($categroy_selmore && $categoryseltype == 1){
                    $newcids = [];
                    foreach($cid as $ck=>$cv){
                        //选择全部
                        if($ck == 0 && $cv == 'true'){
                            $newcids = [];
                            break;
                        }else{
                            if($cv == 'true'){
                                $newcids[] = $ck;
                            }
                        }
                    }
                    if($newcids){
                        $chidlc = Db::name('lucky_collage_category')->where('aid',aid)->where('pid','in',$newcids)->select()->toArray();
                        $cids = $newcids;
                        if($chidlc){
                            foreach($chidlc as $c){
                                $cids[] = intval($c['id']);
                            }
                        }
                        $where[] = ['cid','in',$cids];
                    }
                }else{
                    $cid = intval($cid);
                    $chidlc = Db::name('lucky_collage_category')->where('aid',aid)->where('pid',$cid)->select()->toArray();
                    if($chidlc){
                        $cids = [$cid];
                        foreach($chidlc as $c){
                            $cids[] = intval($c['id']);
                        }
                        $where[] = ['cid','in',$cids];
                    }else{
                        $where[] = ['cid','=',$cid];
                    }
                }
            }
            if($_POST['giddata']){
                $_string = array();
                foreach($_POST['giddata'] as $gid=>$istrue){
                    if($istrue=='true'){
                        if($gid == '0'){
                            $_string[] = "gid is null or gid=''";
                        }else{
                            $_string[] = "find_in_set({$gid},gid)";
                        }
                    }
                }
                if(!$_string){
                    $where2 = '0=1';
                }else{
                    $where2 = implode(" or ",$_string);
                }
            }else{
                $where2 = '1=1';
            }

            $order = 'sort desc';
            if($_POST['sortby'] == 'sort') $order = 'sort desc,id desc';
            if($_POST['sortby'] == 'createtimedesc') $order = 'createtime desc';
            if($_POST['sortby'] == 'createtime') $order = 'createtime';
            if($_POST['sortby'] == 'sales') $order = 'sales desc,sort desc';
            $result = Db::name('lucky_collage_product')->field('id,name,pic,sell_price,market_price,sales,teamnum,gua_num,fy_money,fy_money_val,fy_type')->where($where)->where($where2)->order($order)->limit($_POST['proshownum'])->select()->toArray();
            foreach($result as $k=>$r){
                $result[$k]['money'] = $r['fy_money_val'];
                if($r['fy_type']==1){
                    $result[$k]['money'] = $r['sell_price']*$r['fy_money'];
                }

            }
            if(!$result) $result = [];

            return $result;
        }
        if(input('param.op') == 'selectkanjia'){//查询砍价商品
            $where = [];
            $where[] = ['aid','=',aid];
            if($areaBids){
                $where[] = ['bid','in',$areaBids];
            }
            if(!bid){
                if($categroy_selmore && $categoryseltype == 1){
                    $bids = input('param.bid');
                    if($bids){
                        $newbids = [];
                        foreach($bids as $ck=>$cv){
                            //选择全部
                            if($ck == -1 && $cv == 'true'){
                                $newbids = [];
                                break;
                            }else{
                                if($cv == 'true'){
                                    $newbids[] = $ck;
                                }
                            }
                        }
                        if($newbids){
                            $where[] = ['bid','in',$newbids];
                        }
                    }
                }else{
                    if(input('param.bid')!==''){
                        $where[] = ['bid','=',input('param.bid/d')];
                    }
                }
            }else{
                $where[] = ['bid','=',bid];
            }
            $where[] = ['status','=',1];
            $where[] = ['ischecked','=',1];

            if($_POST['kw']){
                $where[] = ['name','like','%'.$_POST['kw'].'%'];
            }

            $order = 'sort desc';
            if($_POST['sortby'] == 'sort') $order = 'sort desc,id desc';
            if($_POST['sortby'] == 'createtimedesc') $order = 'createtime desc';
            if($_POST['sortby'] == 'createtime') $order = 'createtime';
            if($_POST['sortby'] == 'sales') $order = 'sales desc,sort desc';
            $result = Db::name('kanjia_product')->field('id,name,pic,sell_price,min_price,sales')->where($where)->order($order)->limit($_POST['proshownum'])->select()->toArray();
            if(!$result) $result = [];
            return $result;
        }
        if(input('param.op') == 'selectseckill'){//查询秒杀商品
            $where = [];
            $where[] = ['aid','=',aid];
            if($areaBids){
                $where[] = ['bid','in',$areaBids];
            }
            if(!bid){
                if($categroy_selmore && $categoryseltype == 1){
                    $bids = input('param.bid');
                    if($bids){
                        $newbids = [];
                        foreach($bids as $ck=>$cv){
                            //选择全部
                            if($ck == -1 && $cv == 'true'){
                                $newbids = [];
                                break;
                            }else{
                                if($cv == 'true'){
                                    $newbids[] = $ck;
                                }
                            }
                        }
                        if($newbids){
                            $where[] = ['bid','in',$newbids];
                        }
                    }
                }else{
                    if(input('param.bid')!==''){
                        $where[] = ['bid','=',input('param.bid/d')];
                    }
                }
            }else{
                $where[] = ['bid','=',bid];
            }
			$where[] = ['status','=',1];
			$where[] = ['ischecked','=',1];

			if($_POST['kw']){
				$where[] = ['name','like','%'.$_POST['kw'].'%'];
			}

            $set = Db::name('seckill_sysset')->where('aid',aid)->find();

            $nowtime = time();
            $duration = $set['duration'];
            $qstart_time = $nowtime-$duration*3600;
            if($_POST['showrange']){
                // 显示范围
                if($_POST['showrange'] == 1){
                    // 进行中
                    $where[] = ['starttime','between',[$qstart_time,$nowtime]];
                }elseif($_POST['showrange'] == 2){
                    // 未开始
                    $where[] = ['starttime','>',$nowtime];
                }else{
                    // 全部
                    $where[] = ['starttime','>',$qstart_time];
                }
            }else{
                // 全部
                $where[] = ['starttime','>',$qstart_time];
            }
               

			$order = 'sort desc';
			if($_POST['sortby'] == 'sort') $order = 'sort desc,id desc';
			if($_POST['sortby'] == 'createtimedesc') $order = 'createtime desc';
			if($_POST['sortby'] == 'createtime') $order = 'createtime';
			if($_POST['sortby'] == 'sales') $order = 'sales desc,sort desc';
            if($_POST['sortby'] == 'starttime') $order = 'starttime asc';
			$result = Db::name('seckill_product')->field('id,name,pic,sell_price,market_price,sales,stock,seckill_date,seckill_time,starttime')->where($where)->order($order)->limit($_POST['proshownum'])->select()->toArray();
			if(!$result) $result = [];

            foreach($result as $k2=>$v2){
                //倒计时
                $seckill_endtime = $v2['starttime'] + $duration * 3600;
                if($seckill_endtime < $nowtime) {//已结束
                    $result[$k2]['seckill_status'] = 2;
                    $result[$k2]['hour'] = 0;
                    $result[$k2]['minute'] = 0;
                    $result[$k2]['second'] = 0;
                }else{
                    if($v2['starttime'] > $nowtime){ //未开始
                        $result[$k2]['seckill_status'] = 0;
                        $lefttime = $v2['starttime'] - $nowtime;
                        $result[$k2]['hour'] = floor($lefttime / 3600);
                        $result[$k2]['minute'] = floor(($lefttime - $result[$k2]['hour'] * 3600) / 60);
                        $result[$k2]['second'] = $lefttime - ($result[$k2]['hour'] * 3600) - ($result[$k2]['minute'] * 60);
                        //带天数
                        $result[$k2]['day'] = floor($lefttime / 86400);
                        $result[$k2]['day_hour'] = floor(($lefttime - $result[$k2]['day'] * 86400) / 3600);
                    }else{ //进行中
                        $result[$k2]['seckill_status'] = 1;
                        $lefttime = $seckill_endtime - $nowtime;
                        $result[$k2]['hour'] = floor($lefttime / 3600);
                        $result[$k2]['minute'] = floor(($lefttime - $result[$k2]['hour'] * 3600) / 60);
                        $result[$k2]['second'] = $lefttime - ($result[$k2]['hour'] * 3600) - ($result[$k2]['minute'] * 60);//带天数
                        $result[$k2]['day'] = floor($lefttime / 86400);
                        $result[$k2]['day_hour'] = floor(($lefttime - $result[$k2]['day'] * 86400) / 3600);
                    }
                }
            }
            return $result;
        }

        if(input('param.op') == 'selectscoreshop'){//查询积分兑换商品
            
            $where = [];
            $where[] = ['aid','=',aid];
            if($areaBids){
                $where[] = ['bid','in',$areaBids];
            }
            if(input('?param.bid') && input('param.bid')!==''){
                $where[] = ['bid','=',input('param.bid/d')];
            }
            $where[] = ['ischecked','=',1];
            $where[] = ['status','=',1];
            if($_POST['kw']){
                $where[] = ['name','like','%'.$_POST['kw'].'%'];
            }
            if($_POST['cid']){
                $cid = $_POST['cid'];
                //如果开启多选
                if($categroy_selmore && $categoryseltype == 1){
                    $newcids = [];
                    foreach($cid as $ck=>$cv){
                        //选择全部
                        if($ck == 0 && $cv == 'true'){
                            $newcids = [];
                            break;
                        }else{
                            if($cv == 'true'){
                                $newcids[] = $ck;
                            }
                        }
                    }
                    
                    if($newcids){
                        $chidlc = Db::name('scoreshop_category')->where('aid',aid)->where('pid','in',$newcids)->select()->toArray();
                        $cids = $newcids;
                        if($chidlc){
                            foreach($chidlc as $c){
                                $cids[] = intval($c['id']);
                            }
                        }
                        $where[] = ['cid','in',$cids];
                    }
                }else{
                    $cid = intval($cid);
                    $chidlc = Db::name('scoreshop_category')->where('aid',aid)->where('pid',$cid)->select()->toArray();
                    if($chidlc){
                        $cids = [$cid];
                        foreach($chidlc as $c){
                            $cids[] = intval($c['id']);
                        }
                        $where[] = ['cid','in',$cids];
                    }else{
                        $where[] = ['cid','=',$cid];
                    }
                }
            }
            if($_POST['giddata']){
                $_string = array();
                foreach($_POST['giddata'] as $gid=>$istrue){
                    if($istrue=='true'){
                        if($gid == '0'){
                            $_string[] = "gid is null or gid=''";
                        }else{
                            $_string[] = "find_in_set({$gid},gid)";
                        }
                    }
                }
                if(!$_string){
                    $where2 = '0=1';
                }else{
                    $where2 = implode(" or ",$_string);
                }
            }else{
                $where2 = '1=1';
            }

            $order = 'sort desc';
            if($_POST['sortby'] == 'sort') $order = 'sort desc,id desc';
            if($_POST['sortby'] == 'createtimedesc') $order = 'createtime desc';
            if($_POST['sortby'] == 'createtime') $order = 'createtime';
            if($_POST['sortby'] == 'sales') $order = 'sales desc,sort desc';
            $result = Db::name('scoreshop_product')->field('id,name,pic,sell_price,score_price,money_price,sales')->where($where)->where($where2)->order($order)->limit($_POST['proshownum'])->select()->toArray();
            if(!$result) $result = [];
            //echo M()->_sql();
            return $result;
        }

        if(input('param.op') == 'selecttuangou'){//查询团购商品
            $where = [];
            $where[] = ['aid','=',aid];
            if($areaBids){
                $where[] = ['bid','in',$areaBids];
            }
            if(!bid){
                if($categroy_selmore && $categoryseltype == 1){
                    $bids = input('param.bid');
                    if($bids){
                        $newbids = [];
                        foreach($bids as $ck=>$cv){
                            //选择全部
                            if($ck == -1 && $cv == 'true'){
                                $newbids = [];
                                break;
                            }else{
                                if($cv == 'true'){
                                    $newbids[] = $ck;
                                }
                            }
                        }
                        if($newbids){
                            $where[] = ['bid','in',$newbids];
                        }
                    }
                }else{
                    if(input('param.bid')!==''){
                        $where[] = ['bid','=',input('param.bid/d')];
                    }
                }
            }else{
                $where[] = ['bid','=',bid];
            }
            $where[] = ['status','=',1];
            $where[] = ['ischecked','=',1];

            if($_POST['kw']){
                $where[] = ['name','like','%'.$_POST['kw'].'%'];
            }
            if($_POST['cid']){
                $cid = $_POST['cid'];
                //如果开启多选
                if($categroy_selmore && $categoryseltype == 1){
                    $newcids = [];
                    foreach($cid as $ck=>$cv){
                        //选择全部
                        if($ck == 0 && $cv == 'true'){
                            $newcids = [];
                            break;
                        }else{
                            if($cv == 'true'){
                                $newcids[] = $ck;
                            }
                        }
                    }
                    if($newcids){
                        $chidlc = Db::name('tuangou_category')->where('aid',aid)->where('pid','in',$newcids)->select()->toArray();
                        $cids = $newcids;
                        if($chidlc){
                            foreach($chidlc as $c){
                                $cids[] = intval($c['id']);
                            }

                        }
                        $where[] = ['cid','in',$cids];
                    }
                }else{
                    $cid = intval($cid);
                    $chidlc = Db::name('tuangou_category')->where('aid',aid)->where('pid',$cid)->select()->toArray();
                    if($chidlc){
                        $cids = [$cid];
                        foreach($chidlc as $c){
                            $cids[] = intval($c['id']);
                        }
                        $where[] = ['cid','in',$cids];
                    }else{
                        $where[] = ['cid','=',$cid];
                    };
                }
            }

            $order = 'sort desc';
            if($_POST['sortby'] == 'sort') $order = 'sort desc,id desc';
            if($_POST['sortby'] == 'createtimedesc') $order = 'createtime desc';
            if($_POST['sortby'] == 'createtime') $order = 'createtime';
            if($_POST['sortby'] == 'sales') $order = 'sales desc,sort desc';
            $result = Db::name('tuangou_product')->field('id,name,pic,sell_price,market_price,sales,pricedata')->where($where)->order($order)->limit($_POST['proshownum'])->select()->toArray();
            if(!$result) $result = [];
            foreach($result as $k2=>$v2){
                $buynum = $v2['sales'];
                $pricedata = json_decode($v2['pricedata'],true);
                $nowpricedata = array('num'=>0,'money'=>$v2['sell_price']);
                foreach($pricedata as $k3=>$v3){
                    if($buynum >= $v3['num']){
                        $nowpricedata = $v3;
                    }
                }
                $v2['sell_price'] = $nowpricedata['money'];
                $minpricedata = end($pricedata);
                $min_price = $minpricedata['money'];
                $v2['min_price'] = $min_price;
                $result[$k2] = $v2;
            }
            //echo Db::name()->getlastsql();
            return $result;
        }
        if(input('param.op') == 'selectkecheng'){//查询课程
            $where = [];
            $where[] = ['aid','=',aid];
            if($areaBids){
                $where[] = ['bid','in',$areaBids];
            }
            $where[] = ['status','=',1];
            if($_POST['kw']){
                $where[] = ['name','like','%'.$_POST['kw'].'%'];
            }
            if($_POST['cid']){
                $cid = $_POST['cid'];
                //如果开启多选
                if($categroy_selmore && $categoryseltype == 1){
                    $newcids = [];
                    foreach($cid as $ck=>$cv){
                        //选择全部
                        if($ck == 0 && $cv == 'true'){
                            $newcids = [];
                            break;
                        }else{
                            if($cv == 'true'){
                                $newcids[] = $ck;
                            }
                        }
                    }
                    if($newcids){
                        $chidlc = Db::name('kecheng_category')->where('aid',aid)->where('pid','in',$newcids)->select()->toArray();
                        $cids = $newcids;
                        if($chidlc){
                            $cids = array_merge($chidlc, $cids);
                        }
                        $whereCid = '(';
                        foreach($cids as $k => $c){
                            if(count($cids) == ($k + 1))
                                $whereCid .= "find_in_set({$c},cid)";
                            else
                                $whereCid .= " find_in_set({$c},cid) or ";
                        }
                        $where[] = Db::raw($whereCid . ')');
                    }
                }else{
                    $cid = intval($cid);
                    $chidlc = Db::name('kecheng_category')->where('aid',aid)->where('pid',$cid)->column('id');
                    if($chidlc){
                        $chidlc = array_merge($chidlc, [$cid]);
                        $whereCid = '(';
                        foreach($chidlc as $k => $c){
                            if(count($chidlc) == ($k + 1))
                                $whereCid .= "find_in_set({$c},cid)";
                            else
                                $whereCid .= " find_in_set({$c},cid) or ";
                        }
                        $where[] = Db::raw($whereCid . ')');
                    }else{
                        $where[] = Db::raw("find_in_set({$cid},cid)");
                    }
                }
            }
            if(getcustom('kecheng_lecturer')){
                if($_POST['kctype'] && $_POST['kctype'] != 'all'){
                    $where[] = ['kctype','=',$_POST['kctype']];
                }
                if($_POST['chaptertype'] && $_POST['chaptertype'] != 'all'){
                    $where[] = ['chaptertype','=',$_POST['chaptertype']];
                }
            }
            if(!bid){
                if($categroy_selmore && $categoryseltype == 1){
                    $bids = input('param.bid');
                    if($bids){
                        $newbids = [];
                        foreach($bids as $ck=>$cv){
                            //选择全部
                            if($ck == -1 && $cv == 'true'){
                                $newbids = [];
                                break;
                            }else{
                                if($cv == 'true'){
                                    $newbids[] = $ck;
                                }
                            }
                        }
                        if($newbids){
                            $where[] = ['bid','in',$newbids];
                        }
                    }
                }else{
                    if(input('param.bid')!==''){
                        $where[] = ['bid','=',input('param.bid/d')];
                    }
                }
            }else{
                $where[] = ['bid','=',bid];
            }

            $order = 'sort desc';
            if($_POST['sortby'] == 'sort') $order = 'sort desc,id desc';
            if($_POST['sortby'] == 'createtimedesc') $order = 'createtime desc';
            if($_POST['sortby'] == 'createtime') $order = 'createtime';
            if($_POST['sortby'] == 'sales') $order = 'join_num desc,sort desc';
            if($_POST['sortby'] == 'rand') $order = Db::raw('rand()');
            $result = Db::name('kecheng_list')->field('id,name,pic,price,market_price,join_num')->where($where)->order($order)->limit($_POST['proshownum'])->select()->toArray();
            if(!$result) $result = [];
            foreach($result as $k2=>$v2){
                $result[$k2]['count'] = Db::name('kecheng_chapter')->where('kcid',$v2['id'])->where('status',1)->count();
            }
            return $result;
        }
        if(input('param.op') == 'selectarticle'){//查询文章
            $where = [];
            $where[] = ['aid','=',aid];
            if($areaBids){
                $where[] = ['bid','in',$areaBids];
            }
            $where[] = ['bid','=',input('param.bid/d')];
            $where[] = ['status','=',1];

            if($_POST['kw']){
                $where[] = ['name','like','%'.$_POST['kw'].'%'];
            }
            if($_POST['cid']){
                $cid = $_POST['cid'];
                //如果开启多选
                if($categroy_selmore && $categoryseltype == 1){
                    $newcids = [];
                    foreach($cid as $ck=>$cv){
                        //选择全部
                        if($ck == 0 && $cv == 'true'){
                            $newcids = [];
                            break;
                        }else{
                            if($cv == 'true'){
                                $newcids[] = $ck;
                            }
                        }
                    }
                    if($newcids){
                        $chidlc = Db::name('article_category')->where('aid',aid)->where('pid','in',$newcids)->select()->toArray();
                    }
                    $cids = $newcids;
                }else{
                    $cid = intval($cid);
                    $chidlc = Db::name('article_category')->where('aid',aid)->where('pid',$cid)->select()->toArray();
                    $cids = array($cid);
                }
                if($chidlc){
                    foreach($chidlc as $c){
                        $cids[] = intval($c['id']);
                    }
                }

                if($cids){
                    if(false){}else{
                        $where[] = ['cid','in',$cids];
                    }
                }
            }
            if($_POST['giddata']){
                $_string = array();
                foreach($_POST['giddata'] as $gid=>$istrue){
                    if($istrue=='true'){
                        if($gid == '0'){
                            $_string[] = "gid is null or gid=''";
                        }else{
                            $_string[] = "find_in_set({$gid},gid)";
                        }
                    }
                }
                if(!$_string){
                    $where2 = '1=0';
                }else{
                    $where2 = implode(" or ",$_string);
                }
            }else{
                $where2 = '1=1';
            }
            $order = 'sort desc';
            if($_POST['sortby'] == 'sort') $order = 'sort desc,id desc';
            if($_POST['sortby'] == 'sendtimedesc') $order = 'sendtime desc';
            if($_POST['sortby'] == 'sendtime') $order = 'sendtime';
            if($_POST['sortby'] == 'readcount') $order = 'readcount desc,sort desc';
            $field = 'id,cid,name,subname,pic,sendtime,createtime,readcount';
            $result = Db::name('article')->field($field)->where($where)->where($where2)->order($order)->limit($_POST['shownum'])->select()->toArray();
            if(!$result) $result = array();
            //echo M()->_sql();
            $clist = Db::name('article_category')->where('aid',aid)->where('bid',bid)->select()->toArray();
            $cdata = array();
            foreach($clist as $c){
                $cdata[$c['id']] = $c['name'];
            }
            foreach($result as $k=>$v){
                $result[$k]['cname'] = $cdata[$v['cid']];
                }
            return $result;
        }
        if(input('param.op') == 'selectluntan'){//查询文章
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['status','=',1];
            if($_POST['kw']){
                $where[] = ['content|nickname','like','%'.$_POST['kw'].'%'];
            }
            if($_POST['cid']){
                $cid = $_POST['cid'];
                //如果开启多选
                if($categroy_selmore && $categoryseltype == 1){
                    $newcids = [];
                    foreach($cid as $ck=>$cv){
                        //选择全部
                        if($ck == 0 && $cv == 'true'){
                            $newcids = [];
                            break;
                        }else{
                            if($cv == 'true'){
                                $newcids[] = $ck;
                            }
                        }
                    }
                    if($newcids){
                        $chidlc = Db::name('luntan_category')->where('aid',aid)->where('pid','in',$newcids)->select()->toArray();
                    }
                    $cids = $newcids;
                }else{
                    $cid = intval($cid);
                    $chidlc = Db::name('luntan_category')->where('aid',aid)->where('pid',$cid)->select()->toArray();
                    $cids = array($cid);
                }
                if($chidlc){
                    foreach($chidlc as $c){
                        $cids[] = intval($c['id']);
                    }
                }

                if($cids){
                    if(false){}else{
                        $where[] = ['cid','in',$cids];
                    }
                }
            }
            if($_POST['giddata']){
                $_string = array();
                foreach($_POST['giddata'] as $gid=>$istrue){
                    if($istrue=='true'){
                        if($gid == '0'){
                            $_string[] = "gid is null or gid=''";
                        }else{
                            $_string[] = "find_in_set({$gid},gid)";
                        }
                    }
                }
                if(!$_string){
                    $where2 = '1=0';
                }else{
                    $where2 = implode(" or ",$_string);
                }
            }else{
                $where2 = '1=1';
            }
            $order = 'is_top desc';
            if($_POST['sortby'] == 'sort') $order = 'is_top desc,id desc';
            if($_POST['sortby'] == 'sendtimedesc') $order = 'createtime desc';
            if($_POST['sortby'] == 'sendtime') $order = 'createtime';
            if($_POST['sortby'] == 'readcount') $order = 'readcount desc,is_top desc';
            $field = 'id,cid,nickname name,headimg,content subname,headimg pic,pics,createtime,readcount';
            $result = Db::name('luntan')->field($field)->where($where)->where($where2)->order($order)->limit($_POST['shownum'])->select()->toArray();
            if(!$result) $result = array();
            //echo M()->_sql();
            $clist = Db::name('luntan_category')->where('aid',aid)->select()->toArray();
            $cdata = array();
            foreach($clist as $c){
                $cdata[$c['id']] = $c['name'];
            }
            foreach($result as $k=>$v){
                $result[$k]['cname'] = $cdata[$v['cid']];
                }
            return $result;
        }
        if(input('param.op') == 'selectcoupon'){//查询优惠券
            $time = time();
            if($areaBids){
                $where[] = ['bid','in',$areaBids];
            }
            $where = "aid=".aid." and bid=".input('param.bid/d')." and unix_timestamp(starttime)<={$time} and unix_timestamp(endtime)>={$time}";
            $order = 'sort desc';
            if($_POST['sortby'] == 'sort') $order = 'sort desc,id desc';
            if($_POST['sortby'] == 'createtimedesc') $order = 'createtime desc';
            if($_POST['sortby'] == 'createtime') $order = 'createtime';
            if($_POST['sortby'] == 'stock') $order = 'stock desc,sort desc';
            $result = Db::name('coupon')->field('id,name,type,limit_count,money,createtime,minprice,stock')->where($where)->order($order)->limit($_POST['shownum'])->select()->toArray();
            if(!$result) $result = [];
            return $result;
        }

        if(input('param.op') == 'selectshortvideo'){//查询短视频
            $where = [];
            $where[] = ['aid','=',aid];
            if($areaBids){
                $where[] = ['bid','in',$areaBids];
            }
            if(!bid){
                if($categroy_selmore && $categoryseltype == 1){
                    $bids = input('param.bid');
                    if($bids){
                        $newbids = [];
                        foreach($bids as $ck=>$cv){
                            //选择全部
                            if($ck == -1 && $cv == 'true'){
                                $newbids = [];
                                break;
                            }else{
                                if($cv == 'true'){
                                    $newbids[] = $ck;
                                }
                            }
                        }
                        if($newbids){
                            $where[] = ['bid','in',$newbids];
                        }
                    }
                }else{
                    if(input('param.bid')!==''){
                        $where[] = ['bid','=',input('param.bid/d')];
                    }
                }
            }else{
                $where[] = ['bid','=',bid];
            }
            if(input('param.cid')){
                $cid = input('param.cid');
                //如果开启多选
                if($categroy_selmore && $categoryseltype == 1){
                    $newcids = [];
                    foreach($cid as $ck=>$cv){
                        //选择全部
                        if($ck == 0 && $cv == 'true'){
                            $newcids = [];
                            break;
                        }else{
                            if($cv == 'true'){
                                $newcids[] = $ck;
                            }
                        }
                    }
                    $cids = $newcids;
                    if($cids){
                        $where[] = ['cid','in',$cids];
                    }
                }else{
                    $where[] = ['cid','=',input('param.cid/d')];
                }
            }
            $where[] = ['status','=',1];
            $order = 'sort desc';
            if(input('param.sortby') == 'sort') $order = 'sort desc,id desc';
            if(input('param.sortby') == 'sendtimedesc') $order = 'createtime desc';
            if(input('param.sortby') == 'sendtime') $order = 'createtime';
            if(input('param.sortby') == 'viewnum') $order = 'view_num desc,sort desc';
            $result = Db::name('shortvideo')->field('id,name,description,coverimg,createtime,view_num,zan_num')->where($where)->order($order)->limit(input('param.shownum'))->select()->toArray();
            if(!$result) $result = array();
            foreach($result as $k=>$v){
                if($v['bid']!=0){
                    $result[$k]['logo'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('logo');
                } else {
                    $result[$k]['logo'] = Db::name('admin_set')->where('aid',aid)->value('logo');
                }
            }
            return $result;
        }
        if(input('param.op') == 'selectliveroom'){//查询直播间
            $time = time();
            $where = "aid=".aid." and status=1";
            $order = 'roomId desc';
            if($_POST['sortby'] == 'sort') $order = 'roomId desc';
            if($_POST['sortby'] == 'starttimedesc') $order = 'startTime desc';
            if($_POST['sortby'] == 'starttime') $order = 'startTime';
            if($_POST['sortby'] == 'endtimedesc') $order = 'endTime desc';
            if($_POST['sortby'] == 'endtime') $order = 'endTime';
            if($_POST['sortby'] == 'rand') $order = Db::raw('rand()');
            $result = Db::name('live_room')->field("id,bid,roomId,name,coverImg,shareImg,startTime,endTime,anchorName")->where($where)->order($order)->limit($_POST['shownum'])->select()->toArray();
            if(!$result) $result = [];
            $todaystart = strtotime(date('Y-m-d'));
            foreach($result as $k=>$v){
                $result[$k]['startTime'] = date('m-d H:i',$v['startTime']);
                $result[$k]['endTime'] = date('m-d H:i',$v['endTime']);
                $result[$k]['status'] = 1;
                if($v['startTime'] > time()){ //未开始
                    $result[$k]['status'] = 0;
                    if(date('Y-m-d') == date('Y-m-d',$v['startTime'])){
                        $result[$k]['showtime'] = '今天'.date('H:i',$v['startTime']).'开播';
                    }elseif(date('Y-m-d',time()+86400) == date('Y-m-d',$v['startTime'])){
                        $result[$k]['showtime'] = '明天'.date('H:i',$v['startTime']).'开播';
                    }elseif(date('Y-m-d',time()+86400*2) == date('Y-m-d',$v['startTime'])){
                        $result[$k]['showtime'] = '后天'.date('H:i',$v['startTime']).'开播';
                    }else{
                        $result[$k]['showtime'] = date('m-d H:i',$v['startTime']).'开播';
                    }
                }
                if($v['endTime'] < time()){ //已结束
                    $result[$k]['status'] = 2;
                }
            }
            return $result;
        }
        if(input('param.op') == 'selectbusiness'){//查询商家
            $where = [];
            $where[] = ['aid','=',aid];
            if($areaBids){
                $where[] = ['id','in',$areaBids];
            }
            $where[] = ['status','=',1];
            if($_POST['cid']){
                $cid = $_POST['cid'];
                //如果开启多选
                if($categroy_selmore && $categoryseltype == 1){
                    $newcids = [];
                    foreach($cid as $ck=>$cv){
                        //选择全部
                        if($ck == 0 && $cv == 'true'){
                            $newcids = [];
                            break;
                        }else{
                            if($cv == 'true'){
                                $newcids[] = $ck;
                            }
                        }
                    }
                    $cids = $newcids;
                    if($cids){
                        $whereCid = '(';
                        foreach($cids as $k => $c){
                            if(count($cids) == ($k + 1))
                                $whereCid .= "find_in_set({$c},cid)";
                            else
                                $whereCid .= " find_in_set({$c},cid) or ";
                        }
                        $where[] = Db::raw($whereCid . ')');
                    }
                }else{
                    $cid = intval($cid);
                    $where[] = Db::raw("find_in_set({$cid},cid)");
                }
            }
            $order = 'sort desc';
            if($_POST['sortby'] == 'sort') $order = 'sort desc,id desc';
            if($_POST['sortby'] == 'createtimedesc') $order = 'createtime desc';
            if($_POST['sortby'] == 'createtime') $order = 'createtime';
            if($_POST['sortby'] == 'sales') $order = 'sales desc,sort desc';
            if($_POST['sortby'] == 'scoredesc') $order = 'comment_score desc,sort desc';
            if($_POST['sortby'] == 'rand') $order = Db::raw('rand()');
            $result = Db::name('business')->field('id,name,cid,logo,desc,createtime,sales,address,comment_score,content,tel')->where($where)->order($order)->limit($_POST['shownum'])->select()->toArray();
            if(!$result) $result = [];

            $nowtime = time();
            $nowhm = date('H:i');
            foreach($result as $k=>$v){
                $statuswhere = "`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )";
                $prolist = Db::name('shop_product')->where('bid',$v['id'])->where('ischecked',1)->where($statuswhere)->field('id,pic,name,sales,market_price,sell_price')->limit(8)->order('sales desc,sort desc,id desc')->select()->toArray();
                $result[$k]['content'] = strip_tags($v['content']);
                $result[$k]['prolist'] = $prolist;

                $result[$k]['catname'] = '';
                if($v['cid']){
                    $catnames = Db::name('business_category')->where('id','in',$v['cid'])->column('name');
                    if($catnames){
                        $result[$k]['catname'] = implode(' ',$catnames);
                    }
                }
            }
            //echo M()->_sql();
            return $result;
        }

        /*查询预约服务商品*/
        if(input('param.op') == 'selectyuyue'){/*查询预约服务商品*/
            $where = [];
            $where[] = ['aid','=',aid];
            if($areaBids){
                $where[] = ['bid','in',$areaBids];
            }
            if(!bid){
                if($categroy_selmore && $categoryseltype == 1){
                    $bids = input('param.bid');
                    if($bids){
                        $newbids = [];
                        foreach($bids as $ck=>$cv){
                            //选择全部
                            if($ck == -1 && $cv == 'true'){
                                $newbids = [];
                                break;
                            }else{
                                if($cv == 'true'){
                                    $newbids[] = $ck;
                                }
                            }
                        }
                        if($newbids){
                            $where[] = ['bid','in',$newbids];
                        }
                    }
                }else{
                    if(input('param.bid')!==''){
                        $where[] = ['bid','=',input('param.bid/d')];
                    }
                }
            }else{
                $where[] = ['bid','=',bid];
            }
            $where[] = ['status','=',1];
            $where[] = ['ischecked','=',1];

            if($_POST['cid']){
                $cid = $_POST['cid'];
                //如果开启多选
                if($categroy_selmore && $categoryseltype == 1){
                    $newcids = [];
                    foreach($cid as $ck=>$cv){
                        //选择全部
                        if($ck == 0 && $cv == 'true'){
                            $newcids = [];
                            break;
                        }else{
                            if($cv == 'true'){
                                $newcids[] = $ck;
                            }
                        }
                    }
                    if($newcids){
                        $chidlc = Db::name('yuyue_category')->where('aid',aid)->where('pid','in',$newcids)->select()->toArray();
                        $cids = $newcids;
                        if($chidlc){
                            $cids = array_merge($chidlc, $cids);
                        }
                        $whereCid = '(';
                        foreach($cids as $k => $c){
                            if(count($cids) == ($k + 1))
                                $whereCid .= "find_in_set({$c},cid)";
                            else
                                $whereCid .= " find_in_set({$c},cid) or ";
                        }
                        $where[] = Db::raw($whereCid . ')');
                    }
                }else{
                    $cid = intval($cid);
                    $chidlc = Db::name('yuyue_category')->where('aid',aid)->where('pid',$cid)->column('id');
                    if($chidlc){
                        $chidlc = array_merge($chidlc, [$cid]);
                        $whereCid = '(';
                        foreach($chidlc as $k => $c){
                            if(count($chidlc) == ($k + 1))
                                $whereCid .= "find_in_set({$c},cid)";
                            else
                                $whereCid .= " find_in_set({$c},cid) or ";
                        }
                        $where[] = Db::raw($whereCid . ')');
                    }else{
                        $where[] = Db::raw("find_in_set({$cid},cid)");
                    }
                }
            }


            if($_POST['kw']){
                $where[] = ['name','like','%'.$_POST['kw'].'%'];
            }

            $order = 'sort desc';
            if($_POST['sortby'] == 'sort') $order = 'sort desc,id desc';
            if($_POST['sortby'] == 'createtimedesc') $order = 'createtime desc';
            if($_POST['sortby'] == 'createtime') $order = 'createtime';
            if($_POST['sortby'] == 'sales') $order = 'sales desc,sort desc';
            $result = Db::name('yuyue_product')->where($where)->order($order)->limit($_POST['proshownum'])->select()->toArray();
            $color1 = Db::name('admin_set')->where('aid',aid)->value('color1');
            if($result){
                foreach($result as &$rv){
                    $rv['color1'] = $color1;

                    //自定义服务方式距上方距离
                    $rv['fwtypetop1'] = $rv['fwtypetop1wx'] = 0;
                    $rv['fwtypetop2'] = $rv['fwtypetop2wx'] = 0;
                    $rv['fwtypetop3'] = $rv['fwtypetop3wx'] = 0;
                    if($rv['fwtype']){
                        $fwtypes = explode(',',$rv['fwtype']);
                        $rv['fwtype'] = $fwtypes;
                        if(in_array(1,$fwtypes)){
                            $rv['fwtypetop2'] = 20;$rv['fwtypetop2wx']  = 40;
                            $rv['fwtypetop3'] = 20; $rv['fwtypetop3wx'] = 40;
                        }
                        if(in_array(2,$fwtypes)){
                            if($rv['fwtypetop3'] == 20){
                                $rv['fwtypetop3']= 40; $rv['fwtypetop3wx'] = 80;
                            }
                        }
                    }


                    $rv['fuwulist'] = '';
                    if($rv['fwid']){
                        $fuwulist = Db::name('yuyue_fuwu')->where('id','in',$rv['fwid'])->where('status',1)->where('aid',aid)->order('sort desc,id')->column('name');
                        $rv['fuwulist'] = $fuwulist??'';
                    }
                    $rv['opentip']   = $rv['opentip']?$rv['opentip']:'营业中';
                    $rv['noopentip'] = $rv['noopentip']?$rv['noopentip']:'休息中';
                    $rv['catnames'] = '';
                    if($rv['cid']){
                        $cats = Db::name('yuyue_category')->where('id','in',$rv['cid'])->where('aid',aid)->field('name')->select()->toArray();
                        if($cats){
                            foreach($cats as $cv){
                                $rv['catnames'] .= "[".$cv['name']."]";
                            }
                            unset($cv);
                        }
                    }
                    
                }
                unset($rv);
            }
            if(!$result) $result = [];
            return $result;
        }

        //zhaopin
        /*查询酒店列表 lmy 20240628*/
        if(input('param.op') == 'selectHotel'){
            $where = [];
            $where[] = ['aid','=',aid];
            $where[] = ['status','=',1];
            if($_POST['cid']){
                $cid = $_POST['cid'];
                $cid = intval($cid);
                $chidlc = Db::name('hotel_category')->where('aid',aid)->where('pid',$cid)->column('id');
                if($chidlc){
                    $chidlc = array_merge($chidlc, [$cid]);
                    $whereCid = '(';
                    foreach($chidlc as $k => $c){
                        if(count($chidlc) == ($k + 1))
                            $whereCid .= "find_in_set({$c},cid)";
                        else
                            $whereCid .= " find_in_set({$c},cid) or ";
                    }
                    $where[] = Db::raw($whereCid . ')');
                }else{
                    $where[] = Db::raw("find_in_set({$cid},cid)");
                }
            }
            $order = 'sort desc';
            if($_POST['sortby'] == 'sort') $order = 'sort desc,id desc';
            if($_POST['sortby'] == 'createtimedesc') $order = 'createtime desc';
            if($_POST['sortby'] == 'createtime') $order = 'createtime';
            if($_POST['sortby'] == 'sales') $order = 'sales desc,sort desc';
            $result = Db::name('hotel')->where($where)->order($order)->limit($_POST['shownum'])->select()->toArray();
            foreach($result as &$r){
                $r['tag'] = explode(',',$r['tag']);
                //查询最低的房型价格
                $where = [];
                $where[] =['hotelid','=',$r['id']];
                $nowtime = time();
                $where[] = Db::raw("unix_timestamp(datetime)>=$nowtime");
                $room = Db::name('hotel_room_prices')->where($where)->field('sell_price')->order('sell_price')->find();
                //echo db('hotel_room_prices')->getlastsql();
                $r['sell_price'] = $room['sell_price'];
            }
			
			if(!$result) $result = [];
			return $result;
		}
		/*查询房型列表 lmy 20240809*/
		if(input('param.op') == 'selectHotelRoom'){
			$where = [];
			$where[] = ['aid','=',aid];
			$where[] = ['status','=',1];
			if($_POST['gid']){
				$gid = $_POST['gid'];
				$gid = intval($gid);
				$where[] = Db::raw("find_in_set({$gid},gid)");
			}
			if($_POST['hotelid']){
				$hotelid = $_POST['hotelid'];
				$hotelid = intval($hotelid);
				$where[] = ['hotelid','=',$hotelid];
			}
			$order = 'sort desc';
			if($_POST['sortby'] == 'sort') $order = 'sort desc,id desc';
			if($_POST['sortby'] == 'createtimedesc') $order = 'createtime desc';
			if($_POST['sortby'] == 'createtime') $order = 'createtime';
			if($_POST['sortby'] == 'sales') $order = 'sales desc,sort desc';
			if($_POST['sortby'] == 'stock') $order = 'stock desc';
			$result = Db::name('hotel_room')->field('id,name,pic,tag,stock,sales,isdaymoney')->where($where)->order($order)->limit($_POST['shownum'])->select()->toArray();
			foreach($result as &$r){
				$r['tag'] = explode(',',$r['tag']);
				//查询最低的房型价格
				$where = [];
				$where[] =['roomid','=',$r['id']];
				$nowtime = time();
				$where[] = Db::raw("unix_timestamp(datetime)>=$nowtime");
				//是否有设置余额定价

				if($r['isdaymoney']==1){
					$roomprice = Db::name('hotel_room_prices')->where($where)->where('daymoney','>=','1')->field('daymoney')->order('daymoney')->find();
					if($roomprice){
						$r['sell_price'] = $roomprice['daymoney'];
					}else{
						$r['sell_price'] = 0;
					}
				}else{
					$roomprice = Db::name('hotel_room_prices')->where($where)->field('sell_price')->order('sell_price')->find();
					if($roomprice){
						$r['sell_price'] = $roomprice['sell_price'];
					}else{
						$r['sell_price'] = 0;
					}
					
				}

			}
			
			if(!$result) $result = [];
			return $result;
		}


	}

    //增加激励广告记录
    public function addRewardedVideoAd(){
        $data = [];
        $data['aid'] = aid;
        $data['type'] = input('post.type');
        $data['unitid'] = input('post.unitid');
        $data['givescore'] = input('post.givescore/d');
        $data['givemoney'] = input('post.givemoney/f');
        $data['givemoneyparent'] = input('post.givemoneyparent/f');
        $data['givetimes'] = input('post.givetimes/d');
        $data['givetimestotal'] = input('post.givetimestotal/d');
        $data['rad_url'] = input('post.rad_url');
        if(input('post.id')){
            Db::name('designerpage_rwvideoad')->where('id',input('post.id'))->update($data);
            $id = input('post.id');
        }else{
            $data['createtime'] = time();
            $id = Db::name('designerpage_rwvideoad')->insertGetId($data);
        }
        return json(['status'=>1,'msg'=>'','adid'=>$id]);
    }
    //获取小程序二维码
    public function getwxqrcode(){
        $path = input('param.path');
        $rs = \app\commons\Wechat::getQRCode(aid,'wx',$path,[],bid);
        return json($rs);
    }

    //地图搜索
    public function searchFormMap(){
        $keyword = input('post.keywords');
        $lat = input('post.lat');
        $lng = input('post.lng');

        if(empty($keyword) || empty($lat) || empty($lng)){
            return json(['status' => 0, 'msg' => '参数错误']);
        }
        $mapqq = new \app\commons\MapQQ();
        $results = $mapqq->searchNearbyPlace($keyword,['type'=>'city','lat'=>$lat,'lng'=>$lng],'',1);
        if($results['status'] == 1){
            if(empty($results['data']) && isset($results['cluster'])){
                return json(['status' => 0, 'msg' => '请输入详细地址']);
            }
            return json(['status' => 1, 'data' => $results['data']]);
        }
        return json(['status' => 0, 'msg' => '请求失败']);
    }
}
