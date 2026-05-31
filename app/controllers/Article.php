<?php


// +----------------------------------------------------------------------
// | 文章管理 文章列表
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class Article extends Common
{
    public function initialize(){
        parent::initialize();
        $this->defaultSet();
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
			if(bid==0){
				if(input('param.bid')){
					$where[] = ['bid','=',input('param.bid')];
				}elseif(input('param.showtype')==2){
					$where[] = ['bid','>',0];
                }elseif(input('param.showtype')=='all'){
                    $where[] = ['bid','>=',0];
				}else{
					$where[] = ['bid','=',0];
				}
                }else{
				$where[] = ['bid','=',bid];
			}
			if(input('param.mid')) $where[] = ['mid','=',input('param.mid')];
			if(input('param.pid')) $where[] = ['pid','=',input('param.pid/d')];
			if(input('param.name')) $where[] = ['name','like','%'.input('param.name').'%'];
			if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
			if(input('param.cid')) {
			    $cidtype = 1;
                if(getcustom('article_multi_category'))$cidtype = 2; 
			    if($cidtype ==1){
                    if(input('param.showtype')==2){
                        $where[] = ['pcid','=',input('param.cid')];
                    }else{
                        $cids = [input('param.cid')];
                        //查询他的一级子分类
                        $childs = Db::name('article_category')->where('pid',input('param.cid'))->column('id');
                        if($childs){
                            $cids = array_merge($cids,$childs);
                        }
                        $where[] = ['cid','in',$cids];
                    }
                }else{
                    }
            }
			if(input('param.ctime') ){
				$ctime = explode(' ~ ',input('param.ctime'));
				$where[] = ['createtime','>=',strtotime($ctime[0])];
				$where[] = ['createtime','<',strtotime($ctime[1]) + 86400];
			}
			$count = 0 + Db::name('article')->where($where)->count();
			$data = Db::name('article')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			
			$clist = Db::name('article_category')->where('aid',aid)->select()->toArray();
			$cdata = array();
		
			foreach($clist as $c){
				$cdata[$c['id']] = $c['name'];
			}
            foreach($data as $k=>$v){
				$data[$k]['cname'] = $cdata[$v['cid']];
				if($v['bid'] > 0){
					$data[$k]['bname'] = Db::name('business')->where('aid',aid)->where('id',$v['bid'])->value('name');
				}else{
					$data[$k]['bname'] = '平台';
				}
				if($v['mid']){
					$member = Db::name('member')->where('aid',aid)->where('id',$v['mid'])->find();
				}else{
					$member = array();
				}
				$data[$k]['member'] = $member;
				
				
				
				
			}    
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		//分类
		$clist = Db::name('article_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('article_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);
		$needcheck = Db::name('business_sysset')->where('aid',aid)->value('article_check');
		View::assign('needcheck',$needcheck);
		return View::fetch();
	}
	//编辑文章
	public function edit(){
		$article_id = input('param.id');
		if(input('param.id')){
			if(bid == 0){
				$info = Db::name('article')->where('aid',aid)->where('id',input('param.id/d'))->find();
			}else{
				$info = Db::name('article')->where('aid',aid)->where('bid',bid)->where('id',input('param.id/d'))->find();
			}
		}else{
			$info = ['id'=>'','canpl'=>1,'canplrp'=>1,'showname'=>1,'showreadcount'=>1,'showsendtime'=>1,'showauthor'=>1,'readcount'=>0,'pinglun_check'=>0,'showtj'=>-1,'createtime'=>time()];
			if(bid == 0){
				$set = Db::name('admin_set')->where('aid',aid)->find();
			}else{
				$set = Db::name('business')->where('id',bid)->find();
			}
			$info['author'] = $set['name'];
			$info['pic'] = $set['logo'];
			$info['bid'] = bid;
		}
		if(false){}else{
			$pclist = [];
		}
		//分类
		$clist = Db::name('article_category')->Field('id,name')->where('aid',aid)->where('bid',$info['bid'])->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('article_category')->Field('id,name')->where('aid',aid)->where('bid',$info['bid'])->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		if($info['bid'] != 0){
			$needcheck = Db::name('business_sysset')->where('aid',aid)->value('article_check');
		}else{
			$needcheck = 0;
		}
		$info['cid'] = explode(',',$info['cid']);
       
        View::assign('pclist',$pclist);
        View::assign('clist',$clist);
		View::assign('info',$info);
		View::assign('needcheck',$needcheck);
		View::assign('article_id',$article_id);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$info['content'] = \app\commons\Common::geteditorcontent($info['content']);
        $info['showtj'] = implode(',',$info['showtj']);
		if(!$info['pcid']) $info['pcid'] = 0;
        if(empty($info['showtj'])) $info['showtj'] = -1;
		$info['createtime'] = strtotime($info['createtime']);
		if($info['id']){
			if(!$info['showname']){
				$info['showname'] = 0;
			}
			if(!$info['showsubname']){
				$info['showsubname'] = 0;
			}
			if(!$info['showreadcount']){
				$info['showreadcount'] = 0;
			}
			if(!$info['showsendtime']){
				$info['showsendtime'] = 0;
			}
			if(!$info['showauthor']){
				$info['showauthor'] = 0;
			}
			if(bid != 0){
				$needcheck = Db::name('business_sysset')->where('aid',aid)->value('article_check');
				$article = Db::name('article')->where('aid',aid)->where('id',$info['id'])->find();
				if($needcheck && $article['status']!=0){
					$info['status'] = 0;
				}
			}
			Db::name('article')->where('aid',aid)->where('id',$info['id'])->update($info);
		}else{
			$info['aid'] = aid;
			$info['bid'] = bid;
			if(bid != 0){
				$needcheck = Db::name('business_sysset')->where('aid',aid)->value('article_check');
				if($needcheck){
					$info['status'] = 0;
				}
			}

			$article_id = Db::name('article')->insertGetId($info);

		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		if(bid == 0){
			Db::name('article')->where('aid',aid)->where('id','in',$ids)->delete();
		}else{
			Db::name('article')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->delete();
		}
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//设置状态
	public function setst(){
		$aid = $this->aid;
		$ids = input('post.ids/a');
		if(bid == 0){
			Db::name('article')->where('aid',aid)->where('id','in',$ids)->update(['status'=>input('post.st/d')]);
		}else{
			Db::name('article')->where('aid',aid)->where('bid',bid)->where('id','in',$ids)->update(['status'=>input('post.st/d')]);
		}
		return json(['status'=>1,'msg'=>'操作']);
	}
	//审核
	public function setcheckst(){
		if(bid!=0) return json(['status'=>0,'msg'=>'无权限操作']);
		$st = input('post.st/d');
		$id = input('post.id/d');
		$reason = input('post.reason');
		Db::name('article')->where('aid',aid)->where('id',$id)->update(['status'=>$st,'reason'=>$reason]);
		return json(['status'=>1,'msg'=>'操作成功']);
	}
	public function choosearticle(){
		if(request()->isPost()){
			$data = Db::name('article')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
			return json(['status'=>1,'msg'=>'查询成功','data'=>$data]);
		}
		//分类
		$clist = Db::name('article_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',0)->order('sort desc,id')->select()->toArray(); 
		foreach($clist as $k=>$v){
			$clist[$k]['child'] = Db::name('article_category')->field('id,name')->where('aid',aid)->where('bid',bid)->where('status',1)->where('pid',$v['id'])->order('sort desc,id')->select()->toArray(); 
		}
		View::assign('clist',$clist);
		return View::fetch();
	}

	//复制文章
	public function artcopy(){
		$article = Db::name('article')->where('aid',aid)->where('bid',bid)->where('id',input('post.id/d'))->find();
		if(!$article) return json(['status'=>0,'msg'=>'文章不存在,请重新选择']);
		$data = $article;
		unset($data['id']);
		$data['name'] = '复制-'.$data['name'];
		$data['status'] = 0;
		

		$content_info = $article['content'];
		if($content_info){
			$content_info = json_decode($content_info,true);
			foreach ($content_info as $k => $v) {
				if($v['temp'] == 'tab'){
					$designerpage_tabs = Db::name('designerpage_tab')->where('tabid',$v['id'])->select();
					$new_tabid= $v['id'].time().$k;
					$content_info[$k]['id'] = $new_tabid;
					if(!empty($designerpage_tabs)){
						foreach ($designerpage_tabs as $designerpage_tab) {
							unset($designerpage_tab['id']);
							$designerpage_tab['tabid'] = $new_tabid;
							Db::name('designerpage_tab')->insert($designerpage_tab);
						}
						
					}
				}
			}
			$data['content'] = json_encode($content_info);
		}
		$newartid = Db::name('article')->insertGetId($data);
		return json(['status'=>1,'msg'=>'复制成功','artid'=>$newartid]);
	}

	public function getUrlInfor(){
        }

    public function opengather(){
    	}
    function defaultSet(){
        $set = Db::name('article_set')->where('aid',aid)->where('bid',bid)->find();
        if(!$set){
            Db::name('article_set')->insert(['aid'=>aid,'bid'=>bid]);
        }
    }
}