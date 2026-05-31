<?php
//custom_file(kecheng_lecturer)
// +----------------------------------------------------------------------
// | 课程讲师
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class KechengLecturer extends Common
{
    public function initialize(){
        parent::initialize();
        if(bid > 0) showmsg('无访问权限');
    }
    //列表
    public function index(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = input('param.field').' '.input('param.order');
            }else{
                $order = 'sort desc,id';
            }
            $where = array();
            $where[] = ['aid','=',aid];
            if(input('param.realname')) $where[] = ['realname','like','%'.input('param.realname').'%'];
            if(input('?param.checkstatus') && input('param.checkstatus')!=='') $where[] = ['checkstatus','=',input('param.checkstatus')];
            if(input('?param.status') && input('param.status')!=='') $where[] = ['status','=',input('param.status')];
            $count = 0 + Db::name('kecheng_lecturer')->where($where)->count();
            $data = Db::name('kecheng_lecturer')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            foreach($data as $k=>$v){
                $member = Db::name('member')->where('id',$v['mid'])->find();
                $data[$k]['nickname2'] = $member['nickname'];
                $data[$k]['headimg2'] = $member['headimg'];
            }
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
    //编辑
    public function edit(){
        if(input('param.id')){
            $info = Db::name('kecheng_lecturer')->where('aid',aid)->where('id',input('param.id/d'))->find();
        }else{
            $info = array('id'=>'');
        }
        View::assign('info',$info);
        return View::fetch();
    }
    public function save(){
        $info = input('post.info/a');
        if($info['id']){
            $oldinfo = Db::name('kecheng_lecturer')->where('id',$info['id'])->where('aid',aid)->find();
            if(!$oldinfo){
                return json(['status'=>0,'msg'=>'讲师不存在']); 
            }
        }
        if(!$info['nickname'] || empty($info['nickname'])) {
            return json(['status'=>0,'msg'=>'请填写昵称']); 
        }
        $nickname_len = mb_strlen($info['nickname']);
        if($nickname_len>=30){
            return json(['status'=>0,'msg'=>'昵称不能超出30个字符']); 
        }


        if(!$info['realname'] || empty($info['realname'])) {
            return json(['status'=>0,'msg'=>'请填写姓名']); 
        }
        $info['realname'] = trim($info['realname']);
        $realname_len = mb_strlen($info['realname']);
        if($realname_len>=30){
            return json(['status'=>0,'msg'=>'姓名不能超出30个字符']); 
        }
        $hasrealname = Db::name('kecheng_lecturer')->where('aid',aid)->where('realname',$info['realname'])->field('id')->find();
        if($hasrealname){
            if(($info['id'] && $hasrealname['id'] != $info['id']) || !$info['id']){
                return json(['status'=>0,'msg'=>'该姓名已存在，请填写其它姓名']);
            }
        }

        if(!$info['tel'] || empty($info['tel'])) {
            return json(['status'=>0,'msg'=>'请填写手机号']); 
        }
        $info['tel'] = trim($info['tel']);
        if(!checkTel($info['tel'])){
            return json(['status'=>0, 'msg'=>'请填写正确的手机号']);
        }
        $hastel = Db::name('kecheng_lecturer')->where('aid',aid)->where('tel',$info['tel'])->field('id')->find();
        if($hastel){
            if(($info['id'] && $hastel['id'] != $oldinfo['id']) || !$info['id']){
                return json(['status'=>0,'msg'=>'该手机号已存在，请填写其它手机号']);
            }
        }
        //查询管理员账号
        $hasun = Db::name('admin_user')->where('un',$info['tel'])->field('id')->find();
        if($hasun){
            if(($info['id'] && $hasun['id'] != $oldinfo['userid']) || !$info['id']){
                return json(['status'=>0,'msg'=>'该手机号已存在，请填写其它手机号!']);
            }
        }

        if(!$info['mid'] || empty($info['mid'])) {
            return json(['status'=>0,'msg'=>'请选择'.t('会员')]); 
        }
        $info['mid'] = trim($info['mid']);
        $hasmid = Db::name('kecheng_lecturer')->where('aid',aid)->where('mid',$info['mid'])->field('id')->find();
        if($hasmid){
            if(($info['id'] && $hasmid['id'] != $info['id']) || !$info['id']){
                return json(['status'=>0,'msg'=>'该'.t('会员').'ID已存在，请填写其它'.t('会员').'ID']);
            }
        }

        if(!$info['id']){
            if(!$info['pwd'] || empty($info['pwd'])) {
                return json(['status'=>0,'msg'=>'请填写登录密码']); 
            }
        }
        if($info['pwd'] && !empty($info['pwd'])){
            $info['pwd'] = md5($info['pwd']);
        }

        $shortdesc_len = mb_strlen($info['shortdesc']);
        if($shortdesc_len>=200){
            return json(['status'=>0,'msg'=>'简介不能超出200个字符']); 
        }

        $opttype = false;
        if($info['id']){
            $info['updatetime']  = time();
            $up = Db::name('kecheng_lecturer')->where('aid',aid)->where('id',$info['id'])->update($info);
            if(!$up){
                return json(['status'=>0,'msg'=>'操作失败']); 
            }
            $id = $info['id'];
            $opttype = true;
            $checkstatus = $oldinfo['checkstatus'];

            \app\commons\System::plog('编辑课程讲师'.$info['id']);
        }else{
            $info['aid'] = aid;
            $info['checkstatus'] = 1;
            $info['status']      = 1;
            $info['createtime']  = time();
            $id = Db::name('kecheng_lecturer')->insertGetId($info);
            if(!$id){
                return json(['status'=>0,'msg'=>'操作失败']); 
            }
            $opttype     = true;
            $checkstatus = 1;

            \app\commons\System::plog('添加课程讲师'.$id);
        }
        if($opttype){
            //后台管理员账号
            $uinfo = [];
            $uinfo['un'] = $info['tel'];
            if($info['pwd']){
                $uinfo['pwd'] = $info['pwd'];
            }
            $user = '';
            if($oldinfo && $oldinfo['userid']){
                $user = Db::name('admin_user')->where('id',$oldinfo['userid'])->where('lecturerid',$oldinfo['id'])->where('aid',aid)->field('id')->find();
            }
            if($user){
                Db::name('admin_user')->where('id',$user['id'])->where('aid',aid)->update($uinfo);
            }else{
                if($checkstatus == 1){
                    $uinfo['aid']  = aid;
                    $uinfo['mid']  = $info['mid'];
                    $uinfo['lecturerid'] = $id;
                    $uinfo['auth_data']        = '{"150":"KechengLecturer\/mycenter,KechengLecturer\/mycenter","151":"KechengLecturerList\/index,KechengLecturerList\/*"}';
                    $uinfo['hexiao_auth_data'] = '';
                    $uinfo['wxauth_data']      = '';
                    $uinfo['random_str'] = random(16);
                    $uinfo['isadmin']    = 0;
                    $uinfo['status']     = 1;
                    $uinfo['createtime'] = time();
                    $uid = Db::name('admin_user')->insertGetId($uinfo);
                    Db::name('kecheng_lecturer')->where('id',$id)->update(['userid'=>$uid]);
                }
            }
        }
        return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
    }
    //改状态
    public function setst(){
        $st = input('post.st/d');
        $ids = input('post.ids/a');
        Db::name('kecheng_lecturer')->where('aid',aid)->where('id','in',$ids)->update(['status'=>$st]);
        \app\commons\System::plog('课程讲师改状态'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'操作成功']);
    }
    //删除
    public function del(){
        $ids = input('post.ids/a');
        $lecturers =  Db::name('kecheng_lecturer')->where('aid',aid)->where('id','in',$ids)->select()->toArray();
        if(!$lecturers){
            return json(['status'=>0,'msg'=>'没有可删除的讲师']);
        }
        foreach($lecturers as $lv){
            Db::name('kecheng_lecturer')->where('id',$lv['id'])->where('aid',aid)->delete();
            Db::name('admin_user')->where('id',$lv['userid'])->where('aid',aid)->delete();
        }
        \app\commons\System::plog('课程讲师删除'.implode(',',$ids));
        return json(['status'=>1,'msg'=>'删除成功']);
    }


    //审核
    public function setcheckst(){
        $st = input('post.st/d');
        $id = input('post.id/d');
        $lecturer = Db::name('kecheng_lecturer')->where('id',$id)->where('aid',aid)->find();
        if(!$lecturer){
            return json(['status'=>0,'msg'=>'讲师不存在']);
        }
        $reason = input('post.reason');
        if($st == 1){
            //查询管理员账号
            $hasun = Db::name('admin_user')->where('un',$lecturer['tel'])->field('id')->find();
            if($hasun){
                if($hasun['id'] != $lecturer['userid']){
                    return json(['status'=>0,'msg'=>'该手机号管理员账号已存在，请删除已存在的管理员或更换其他手机号重试!']);
                }
            }
        }
        $up = Db::name('kecheng_lecturer')->where('id',$id)->where('aid',aid)->update(['checkstatus'=>$st,'checkreason'=>$reason]);
        if($up){
            //后台管理员账号
            $user = '';
            if($lecturer['userid']){
                $user = Db::name('admin_user')->where('id',$lecturer['userid'])->where('lecturerid',$lecturer['id'])->where('aid',aid)->field('id')->find();
            }
            if($st == 1){
                if($user){
                    Db::name('admin_user')->where('id',$user['id'])->where('aid',aid)->update(['status'=>1]);
                }else{
                    $uinfo = [];
                    $uinfo['un']   = $lecturer['tel'];
                    $uinfo['pwd']  = $lecturer['pwd'];
                    $uinfo['aid']  = aid;
                    $uinfo['mid']  = $lecturer['mid'];
                    $uinfo['lecturerid']       = $id;
                    $uinfo['auth_data']        = '{"150":"KechengLecturer\/mycenter,KechengLecturer\/mycenter","151":"KechengLecturerList\/index,KechengLecturerList\/*"}';
                    $uinfo['hexiao_auth_data'] = '';
                    $uinfo['wxauth_data']      = '';
                    $uinfo['random_str'] = random(16);
                    $uinfo['isadmin']    = 0;
                    $uinfo['status']     = 1;
                    $uinfo['createtime'] = time();
                    $uid = Db::name('admin_user')->insertGetId($uinfo);
                    Db::name('kecheng_lecturer')->where('id',$lecturer['id'])->update(['userid'=>$uid]);
                }
            }else{
                if($user){
                    Db::name('admin_user')->where('id',$user['id'])->where('aid',aid)->delete();
                    Db::name('kecheng_lecturer')->where('id',$lecturer['id'])->update(['userid'=>0]);
                }
            }
        }
        $statusname = '';
        if($st == 1) $statusname = '通过';
        if($st == -1) $statusname = '驳回';
        if($st == 0) $statusname = '待审核';
        \app\commons\System::plog('课程讲师'.$id.'审核'.$statusname);
        return json(['status'=>1,'msg'=>'操作成功']);
    }

    public function choosepeisonguser(){
        if(request()->isPost()){
            $data = Db::name('kecheng_lecturer')->where('aid',aid)->where('status',1)->where('id',input('post.id/d'))->find();
            return json(['status'=>1,'msg'=>'查询成功','data'=>$data]);
        }
        return View::fetch();
    }

    public function mycenter(){
        $lecturerid = $this->user['lecturerid'];
        if($lecturerid && $lecturerid>0){
            $lecturer = Db::name('kecheng_lecturer')->where('id',$lecturerid)->where('aid',aid)->find();
        }else{
            showmsg('管理员未绑定讲师');
        }

        //是否能发放验证码
        $cansendsms = false;
        // $smsset = Db::name('admin_set_sms')->where('aid',aid)->field('id,tmpl_smscode,tmpl_smscode_st,status')->find();
        // if($smsset && $smsset['status'] == 1 && $smsset['tmpl_smscode'] && $smsset['tmpl_smscode_st']==1){
        //     $cansendsms = true;
        // }

        if(request()->isAjax()){
            $formdata = input('post.info/a');

            if(!$formdata['nickname'] || empty($formdata['nickname'])) {
                return json(['status'=>0,'msg'=>'请填写昵称']); 
            }
            $nickname_len = mb_strlen($formdata['nickname']);
            if($nickname_len>=30){
                return json(['status'=>0,'msg'=>'昵称不能超出30个字符']); 
            }

            if(!$formdata['realname'] || empty($formdata['realname'])) {
                return json(['status'=>0,'msg'=>'请填写姓名']); 
            }
            $formdata['realname'] = trim($formdata['realname']);
            $hasrealname = Db::name('kecheng_lecturer')->where('aid',aid)->where('realname',$formdata['realname'])->field('id')->find();
            if($hasrealname){
                if($hasrealname['id'] != $lecturer['id']){
                    return json(['status'=>0,'msg'=>'该姓名已存在，请填写其它姓名']);
                }
            }

            if(!$formdata['tel'] || empty($formdata['tel'])) {
                return json(['status'=>0,'msg'=>'请填写手机号']); 
            }
            $formdata['tel'] = trim($formdata['tel']);
            if(!checkTel($formdata['tel'])){
                return json(['status'=>0, 'msg'=>'请填写正确的手机号']);
            }
            $hastel = Db::name('kecheng_lecturer')->where('aid',aid)->where('tel',$formdata['tel'])->field('id')->find();
            if($hastel){
                if($hastel['id'] != $lecturer['id']){
                    return json(['status'=>0,'msg'=>'该手机号已存在，请填写其它手机号']);
                }
            }

            //需要验证码
            if($cansendsms && ($formdata['tel'] != $lecturer['tel'])){
                $formdata['smscode'] = trim($formdata['smscode']);
                $prefix = $this->user['id'].'-'.$this->user['lecturerid'];
                if(md5($formdata['tel'].'-'.$formdata['smscode']) != cache($prefix.'_smscode') || cache($prefix.'_smscodetimes')>5){
                    cache($prefix.'_smscodetimes',cache($prefix.'_smscodetimes')+1);
                    return json(['status'=>0,'msg'=>'短信验证码错误']);
                }
                cache($prefix.'_smscode',null);
                cache($prefix.'_smscodetimes',null);
            }

            $shortdesc_len = mb_strlen($formdata['shortdesc']);
            if($shortdesc_len>=200){
                return json(['status'=>0,'msg'=>'简介不能超出200个字符']); 
            }

            $data = [];
            $data['headimg']  = $formdata['headimg'];
            $data['nickname'] = $formdata['nickname'];
            $data['realname'] = $formdata['realname'];
            $data['tel']      = $formdata['tel'];
            $data['shortdesc']= $formdata['shortdesc'];
            $data['updatetime'] = time();
            // if($formdata['pwd'] && !empty($formdata['pwd'])){
            //     $formdata['pwd'] = md5($formdata['pwd']);
            // }
            $up = Db::name('kecheng_lecturer')->where('id',$lecturerid)->where('aid',aid)->update($data);
            if(!$up){
                return json(['status'=>0,'msg'=>'操作失败']);
            }
            \app\commons\System::plog('编辑课程讲师个人资料'.$lecturerid);
            return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('mycenter')]);
        }else{
            View::assign('cansendsms',$cansendsms);
            View::assign('info',$lecturer);
            return View::fetch();
        }
    }

    //发送验证码
    public function sendsms(){
        if(request()->isAjax()){
            $code = rand(100000,999999);
            $tel = input('post.tel');
            if(!checkTel($tel)){
                return json(['status'=>0,'msg'=>'手机号格式错误']);
            }
            $prefix = $this->user['id'].'-'.$this->user['lecturerid'];
            cache($prefix.'_smscode',md5($tel.'-'.$code),600);
            cache($prefix.'_smscodetimes',0);
            $rs = \app\commons\Sms::send(aid,$tel,'tmpl_smscode',['code'=>$code]);
            return json($rs);
        }
    }
}
