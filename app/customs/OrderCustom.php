<?php


namespace app\customs;
use think\facade\Db;
class OrderCustom
{
    public function deal_first_cashback($aid,$mid,$back_price,$og,$v,$type = 0,$canshtype = 'shop')
    {

        if(getcustom('yx_cashback_time') || getcustom('yx_cashback_stage')){
            //自定义第一次发放
            //参数为 ：back_price总返回数额 og订单商品 v购物返现设置 type发放类型 0:立即发放（前面已发放过） 1：自定义发放（需要在这发放）canshtype 记录类型

            //返现类型 1、余额 2、佣金 3、积分 小数位数
            $money_weishu = 2;$commission_weishu = 2;$score_weishu = 0;
            if(getcustom('member_money_weishu',$aid)){
                $money_weishu = Db::name('admin_set')->where('aid',$aid)->value('member_money_weishu');
            }
            if(getcustom('fenhong_money_weishu',$aid)){
                $commission_weishu = Db::name('admin_set')->where('aid',$aid)->value('fenhong_money_weishu');
            }
            if(getcustom('score_weishu',$aid)){
                $score_weishu = Db::name('admin_set')->where('aid',$aid)->value('score_weishu');
            }

            $return_type = $v['return_type'];//返现时间类型
            if($type != $return_type){
                return;
            }

            $return_day    = 0;//返现天数
            $delaysend_day = 0;//延迟天数
            $ave_num       = 0;//平均每次发放数值

            if($return_type == 1){
                if(getcustom('yx_cashback_time')){
                    //再次验证自定义天数返现
                    $return_day = $v['return_day'];//返现天数
                    if(getcustom('yx_cashback_time_delaysend')){
                        $delaysend_day = $v['delaysend_day'];//延迟天数
                    }
                    //如果天数小于2，则直接发放
                    if($return_day<=1){
                        $status   = 2;
                        $ave_num  = $back_price;
                        $send_num = $back_price;
                    }else{
                        $status   = 1;
                        //计算平均发放数值
                        $ave_num = $back_price/$return_day;
                        //计算此次发放数值
                        if($v['back_type'] == 1){
                            $send_num = $ave_num = dd_money_format($ave_num,$money_weishu);
                        }else if($v['back_type'] == 2){
                            $send_num = $ave_num = dd_money_format($ave_num,$commission_weishu);
                        }else{
                            $ave_num  = dd_money_format($ave_num,$score_weishu);
                            if($score_weishu ==0 && $ave_num<=1){
                                $ave_num = 1;
                            }
                            $send_num = $ave_num;
                        }
                        //处理特殊情况
                        if($send_num>=$back_price){
                            $send_num = $back_price;
                            $status   = 2;
                        }
                    }
                }
            }else if($return_type == 2){
                if(getcustom('yx_cashback_stage')){
                    //再次验证阶梯数值返现
                    $stagedata  = json_decode($v['stagedata'],true);
                    if(!$stagedata){
                        return;
                    }

                    $status     = 1;
                    $stageratio = 0;//获取阶梯返还比例
                    foreach($stagedata as $stage){
                        if($stage['stageday']<=1 && 1<=$stage['stageday2']){
                            $stageratio = $stage['stageratio'];
                        }
                        //获取最大返现天数
                        if($stage['stageday2']>$return_day){
                            $return_day = $stage['stageday2'];
                        }
                    }
                    if($return_day<=0){
                        return;
                    }

                    //计算此次发放数值
                    $send_num = $back_price*$stageratio*0.01;
                    if($v['back_type'] == 1){
                        $send_num = dd_money_format($send_num,$money_weishu);
                    }else if($v['back_type'] == 2){
                        $send_num = dd_money_format($send_num,$commission_weishu);
                    }else{
                        $send_num2  = dd_money_format($send_num,$score_weishu);
                        if($score_weishu ==0 && $send_num>0 && $send_num<=1){
                            $send_num = 1;
                        }else{
                            $send_num = $send_num2;
                        }
                    }

                    //处理特殊情况：如果返还天数小于2，或者此次发放数额大等于全部发放数额，则直接发放
                    if($return_day<=1 || $send_num>=$back_price){
                        $send_num = $back_price;
                        $status   = 2;
                    }
                }
            }else{
                $status   = 2;
                $ave_num  = $back_price;
                $send_num = $back_price;
            }
            if($type >= 1){
                //是否发放返现 -1 不发放也不记录发放值 0 : 不发放但记录发放值 1：发放 
                $sendstatus = 1;
            }else{
                $sendstatus = 0;
            }

            $data = [];
            $data['cashback_id'] = $v['id'];//活动id
            //判定限额
            $cashback_member_check = Db::name('cashback_member')->where('aid',aid)->where('mid',$mid)->where(['cashback_id'=>$v['id'],'pro_id'=>$og['proid'],'type'=>$canshtype])->find();
            if(getcustom('yx_cashback_time_delaysend')){
                //选项为自定义天数的延迟发放
                if($return_type == 1 && $delaysend_day>0){
                    $data['delaysend_day'] = $delaysend_day;
                    $nowday = strtotime(date("Y-m-d"));
                    $data['delaysend_starttime'] = strtotime(" +".$data['delaysend_day']." day",$nowday);
                    $status     = 1;
                    $sendstatus = -1;
                }
            }
            if($v['back_type'] == 1){
                $data['moneystatus']    = $status;
                $data['allmoney']       = $back_price;//总返回数值
                $data['moneyave']       = $ave_num;//平均每次发放是数值
                $data['moneyday']       = $return_day;//发放多少天
                $data['money_sendtime'] = time();//发放时间
                $data['money_name']     = $v['name'];;//返现名称
                if($sendstatus >=0){
                    $data['money']         = $send_num;//已发放的数值
                    $data['money_sendnum'] = 1;//发放次数
                    if($sendstatus == 1){
                        if(getcustom('cashback_max')){
                            if($v['goods_multiple_max'] > 0 &&  $cashback_member_check['cashback_money_max'] > $cashback_member_check['cashback_money']){
                                //最大可追加金额
                                $cashback_money_max = $cashback_member_check['cashback_money_max'] - $cashback_member_check['cashback_money'];
                                if($cashback_money_max < $send_num){
                                    $send_num = $cashback_money_max;
                                }    
                                if($send_num <=0){
                                    return ;
                                }
                            }
                        }
                        //需要发放第一次
                        \app\commons\Member::addmoney($aid,$mid,$send_num,$v['name']);
                        //累计到参与人统计表
                        Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('cashback_money',$send_num)->update();
                        //写入发放日志
                        $res_cashback_do_log = self::cashbackMemerDoLog($aid,$mid,$v,$og,$send_num);
                    }
                }
            }else if($v['back_type'] == 2){
                $data['commissionstatus']    = $status;
                $data['allcommission']       = $back_price;
                $data['commissionave']       = $ave_num;
                $data['commissionday']       = $return_day;
                $data['commission_sendtime'] = time();
                $data['commission_name']     = $v['name'];;//返现名称
                if($sendstatus >=0){
                    $data['commission']         = $send_num;//已发放的数值
                    $data['commission_sendnum'] = 1;//发放次数
                    if($sendstatus == 1){
                        if(getcustom('cashback_max')){
                            if($v['goods_multiple_max'] > 0 &&  $cashback_member_check['cashback_money_max'] > $cashback_member_check['commission']){
                                //最大可追加金额
                                $cashback_money_max = $cashback_member_check['cashback_money_max'] - $cashback_member_check['commission'];
                                if($cashback_money_max < $send_num){
                                    $send_num = $cashback_money_max;
                                }    
                                if($send_num <=0){
                                    return ;
                                }                                                
                                
                            }
                        }
                        //需要发放第一次
                        \app\commons\Member::addcommission($aid,$mid,$mid,$send_num,$v['name']);
                        //累计到参与人统计表
                        Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('commission',$send_num)->update();
                        //写入发放日志
                        $res_cashback_do_log = self::cashbackMemerDoLog($aid,$mid,$v,$og,$send_num);
                    }
                }
            }else if($v['back_type'] == 3){
                $data['scorestatus']    = $status;
                $data['allscore']       = $back_price;
                $data['scoreave']       = $ave_num;
                $data['scoreday']       = $return_day;
                $data['score_sendtime'] = time();
                $data['score_name']     = $v['name'];;//返现名称
                if($sendstatus >=0){
                    $data['score']         = $send_num;//已发放的数值
                    $data['score_sendnum'] = 1;//发放次数
                    if($sendstatus == 1){
                        if(getcustom('cashback_max')){
                            if($v['goods_multiple_max'] > 0 &&  $cashback_member_check['cashback_money_max'] > $cashback_member_check['score']){
                                //最大可追加金额
                                $cashback_money_max = $cashback_member_check['cashback_money_max'] - $cashback_member_check['score'];
                                if($cashback_money_max < $send_num){
                                    $send_num = $cashback_money_max;
                                }    
                                if($send_num <=0){
                                    return ;
                                }
                            }
                        }
                        //需要发放第一次
                        \app\commons\Member::addscore($aid,$mid,$send_num,$v['name']);
                        //累计到参与人统计表
                        Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('score',$send_num)->update();
                        //写入发放日志
                        $res_cashback_do_log = self::cashbackMemerDoLog($aid,$mid,$v,$og,$send_num);
                    }
                }
            }

            if(getcustom('cashback_yongjin')){
                //记录累计返现金额
                Db::name('member')->where('id',$og['mid'])->inc('cashback_total',$send_num)->update();
            }
            //查询是否有此商品返现表
            $goods_cashback = Db::name('shop_order_goods_cashback')->where('mid',$mid)->where('sog_id',$og['id'])->where('pro_id',$og['proid'])->where('back_type',$v['back_type'])->where('canshtype',$canshtype)->field('id')->find();
            if($goods_cashback){
                $data['updatetime'] = time();
                Db::name('shop_order_goods_cashback')->where('id',$goods_cashback['id'])->update($data);
                $gcid = $goods_cashback['id'];
            }else{
                $data['aid']    = $og['aid'];
                $data['bid']    = $og['bid'];
                $data['mid']    = $mid;
                $data['sog_id'] = $og['id'];//订单商品id
                $data['pro_id'] = $og['proid'];//订单商品id
                $data['back_type'] = $v['back_type'];//返回类型 1：余额 2：佣金 3：积分
                $data['canshtype'] = $canshtype;//购物返回类型 如商城 shop
                if(getcustom('cashback_yongjin')){
                    $data['cashback_yongjin'] = $v['cashback_yongjin'];//抵扣返现
                }
                if(getcustom('yx_cashback_stage')){
                    //阶梯性返现
                    if($return_type == 2){
                        $data['return_type'] = 2;
                        $data['stagedata']   = $v['stagedata'];
                    }
                }
                $gcid = Db::name('shop_order_goods_cashback')->insertGetId($data);
            }

            if(getcustom('yx_cashback_time_tjspeed') || getcustom('yx_cashback_time_teamspeed')){
                //返现时间自定义的加速返现
                if($return_type == 1 && $status == 1){
                    //再次查询
                    $where = [];
                    $where[] = ['id','=',$gcid];
                    if($v['back_type'] == 1){
                        $where[] = ['moneystatus','=',$status];
                    }else if($v['back_type'] == 2){
                        $where[] = ['commissionstatus','=',$status];
                    }else if($v['back_type'] == 3){
                        $where[] = ['scorestatus','=',$status];
                    }
                    if(getcustom('yx_cashback_time_tjspeed')){
                        //用户加速余额加速
                        $cashback_speed_num = Db::name('member')->where('id',$mid)->value('cashback_speed_num');
                        if($cashback_speed_num>0){
                            $mv = Db::name('shop_order_goods_cashback')->where($where)->find();
                            if($mv){
                                $res = self::deal_cashbacklist($mv,['isspeed'=>3,'cashback_speed'=>$cashback_speed_num,'remark'=>'加速余额释放','nocheckstatus'=>true]);
                                if($res && $res['status'] == 1){
                                    if($res['param']['cashback_speed']<=0){
                                        Db::name('member')->where('id',$mid)->dec('cashback_speed_num',$cashback_speed_num)->update();
                                    }else{
                                        $decnum = $cashback_speed_num - $res['param']['cashback_speed'];
                                        Db::name('member')->where('id',$mid)->dec('cashback_speed_num',$decnum)->update();
                                    }
                                }
                            }
                        }
                    }
                    if(getcustom('yx_cashback_time_teamspeed')){
                        //团队业绩达标加速
                        if(!empty($v['teamspeeddata'])){
                            $mv = Db::name('shop_order_goods_cashback')->where($where)->find();
                            if($mv){
                                $teamspeeddata = json_decode($v['teamspeeddata'],true);
                                $downmids = \app\commons\Member::getteammids($aid,$mid);
                                if($downmids && $teamspeeddata){
                                    //查询团队业绩
                                    $yejiwhere = [];
                                    $yejiwhere[] = ['status','=','3'];
                                    $teamyeji = Db::name('shop_order_goods')->where('aid',$aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');

                                    $teamspeed_money  = 0;
                                    $team_speed       = 0;
                                    foreach($teamspeeddata as $tv){
                                        if($teamyeji>=$teamspeed_money && $teamyeji>=$tv['money']){
                                            $teamspeed_money = $tv['money'];
                                            $team_speed      = $back_price*$tv['speed']/100;
                                            $team_speed      = round($team_speed,2);
                                        }
                                    }
                                    unset($tv);
                                    if($team_speed>0){
                                        $res = self::deal_cashbacklist($mv,['isspeed'=>2,'team_speed'=>$team_speed,'teamspeed'=>$team_speed,'teamspeed_yeji'=>$teamyeji,'teamspeed_money'=>$teamspeed_money,'remark'=>'团队业绩达标加速','nocheckstatus'=>true]);
                                        if($res && $res['status'] == 1 && $res['param']['team_speed']>0){
                                            //增加加速余额
                                            Db::name('member')->where('id',$mid)->inc('cashback_speed_num',$res['param']['team_speed'])->update();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function deal_autocashback($aid=0)
    {
        if(getcustom('yx_cashback_time') || getcustom('yx_cashback_stage')){
            // 自定义每天返现
            //读取配置
            if($aid){
                $syssetlist = Db::name('admin_set')->where('aid',$aid)->select()->toArray();
            }else{
                $syssetlist = Db::name('admin_set')->where('1=1')->select()->toArray();
            }
            if($syssetlist){
                //今天
                $today = strtotime(date("Y-m-d",time()));
                foreach($syssetlist as $sv){
                    //余额返现
                    self::deal_moneycashback($sv,$today);
                    //佣金返现
                    self::deal_commissioncashback($sv,$today);
                    //积分返现
                    self::deal_scorecashback($sv,$today);
                }
            }
        }
    }

    private static function deal_moneycashback($sv,$today)
    {
        if(getcustom('yx_cashback_time') || getcustom('yx_cashback_stage')){
            //余额返现
            $where = [];
            $where[] = ['aid','=',$sv['aid']];
            $where[] = ['back_type','=',1];
            $where[] = ['moneystatus','=',1];
            $where[] = ['money_sendtime','<',$today];
            if(getcustom('cashback_yongjin')){
                $where[] = ['cashback_yongjin','<>',2];
            }
            $moneylist = Db::name('shop_order_goods_cashback')
                ->where($where)
                ->order('id asc')
                ->select()
                ->toArray();
            if($moneylist){
                foreach($moneylist as $mv){
                    if(getcustom('yx_cashback_time_delaysend')){
                        //延迟发放
                        if($mv['delaysend_starttime']>time()){
                            //只更新发放时间
                            Db::name('shop_order_goods_cashback')->where('id',$mv['id'])->update(['money_sendtime'=>time()]);
                            continue;
                        }
                    }
                    //走统一的处理方法
                    $res = self::deal_cashbacklist($mv);
                    if($res['status'] == 0){
                        continue;
                    }
                }
            }
        }
    }

    private static function deal_commissioncashback($sv,$today)
    {
        if(getcustom('yx_cashback_time') || getcustom('yx_cashback_stage')){
            //佣金返现
            $where = [];
            $where[] = ['aid','=',$sv['aid']];
            $where[] = ['back_type','=',2];
            $where[] = ['commissionstatus','=',1];
            $where[] = ['commission_sendtime','<',$today];
            if(getcustom('cashback_yongjin')){
                $where[] = ['cashback_yongjin','<>',2];
            }
            $commissionlist = Db::name('shop_order_goods_cashback')
                ->where($where)
                ->order('id asc')
                ->select()
                ->toArray();
            if($commissionlist){
                foreach($commissionlist as $mv){
                    if(getcustom('yx_cashback_time_delaysend')){
                        //延迟发放
                        if($mv['delaysend_starttime']>time()){
                            //只更新发放时间
                            Db::name('shop_order_goods_cashback')->where('id',$mv['id'])->update(['commission_sendtime'=>time()]);
                            continue;
                        }
                    }
                    //走统一的处理方法
                    $res = self::deal_cashbacklist($mv);
                    if($res['status'] == 0){
                        continue;
                    }
                }
            }
        }
    }

    private static function deal_scorecashback($sv,$today)
    {
        if(getcustom('yx_cashback_time') || getcustom('yx_cashback_stage')){
            //积分返现
            $where = [];
            $where[] = ['aid','=',$sv['aid']];
            $where[] = ['back_type','=',3];
            $where[] = ['scorestatus','=',1];
            $where[] = ['score_sendtime','<',$today];
            if(getcustom('cashback_yongjin')){
                $where[] = ['cashback_yongjin','<>',2];
            }
            $scorelist = Db::name('shop_order_goods_cashback')
                ->where($where)
                ->order('id asc')
                ->select()
                ->toArray();
            if($scorelist){
                foreach($scorelist as $mv){
                    if(getcustom('yx_cashback_time_delaysend')){
                        //延迟发放
                        if($mv['delaysend_starttime']>time()){
                            //只更新发放时间
                            Db::name('shop_order_goods_cashback')->where('id',$mv['id'])->update(['score_sendtime'=>time()]);
                            continue;
                        }
                    }
                    //走统一的处理方法
                    $res = self::deal_cashbacklist($mv);
                    if($res['status'] == 0){
                        continue;
                    }
                }
            }
        }
    }

    public static function deal_collagecashback($aid,$order)
    {
        if(getcustom('yx_cashback_collage')){
            //购物返现
            $cashbacklist = Db::name('cashback')
                ->where('aid',$aid)
                ->where('bid',0)
                ->where('fwtype',3)
                ->where('starttime','<',$order['paytime'])
                ->where('endtime','>',$order['paytime'])
                ->order('sort desc')
                ->select()->toArray();

            //查询购买用户
            $member = Db::name('member')->where('id',$order['mid'])->find();
            if($member && $cashbacklist){
                //返现类型 1、余额 2、佣金 3、积分 小数位数
                $money_weishu = 2;$commission_weishu = 2;$score_weishu = 0;
                if(getcustom('member_money_weishu')){
                    $money_weishu = Db::name('admin_set')->where('aid',$aid)->value('member_money_weishu');
                }
                if(getcustom('fenhong_money_weishu')){
                    $commission_weishu = Db::name('admin_set')->where('aid',$aid)->value('fenhong_money_weishu');
                }
                if(getcustom('score_weishu')){
                    $score_weishu = Db::name('admin_set')->where('aid',$aid)->value('score_weishu');
                }
                $product = Db::name('collage_product')->where('id',$order['proid'])->field('id')->find();
                if($product){
                    foreach($cashbacklist as $v){

                        if(getcustom('yx_cashback_collage_moneyreturn')){
                            //判断余额支付返还情况
                            if($order['paytypeid'] == 1){
                                if($order['buytype'] == 1){
                                    //单独 若单购余额返设置为关闭
                                    if($v['alone_moneyreturn'] != 1){
                                        continue;
                                    }
                                }else if($order['buytype'] == 2){
                                    //拼团 团长 若拼团余额返还设置为关闭
                                    if(!$v['team_moneyreturn']){
                                        continue;
                                    }
                                }else{
                                    //拼团 团员 若拼团余额返还设置不是全都返还
                                    if($v['team_moneyreturn'] != 1){
                                        continue;
                                    }
                                }
                            }
                        }

                        $gettj = explode(',',$v['gettj']);
                        if(!in_array('-1',$gettj) && !in_array($member['levelid'],$gettj)){ //不是所有人
                            continue;
                        }

                        $collageids = explode(',',$v['collageids']);
                        if(!in_array($product['id'],$collageids)){
                            continue;
                        }

                        $back_ratio = $v['back_ratio'];//返现利率
                        //如果返现利率大于0
                        if($back_ratio>0){
                            //计算返现
                            $back_price = $back_ratio*$order['totalprice']/100;

                            //返现类型 1、余额 2、佣金 3、积分
                            if($v['back_type'] == 1 ){
                                $back_price = dd_money_format($back_price,$money_weishu);
                            }else if($v['back_type']== 2){
                                $back_price = dd_money_format($back_price,$commission_weishu);
                            }else if($v['back_type'] == 3){
                                $back_price = dd_money_format($back_price,$score_weishu);
                            }

                            $return_type = 0;//发放状态
                            if(getcustom('yx_cashback_time') || getcustom('yx_cashback_stage')){
                                $return_type = $v['return_type'];
                            }

                            //构建商品信息
                            $og = [];
                            $og['aid']   = $order['aid'];
                            $og['bid']   = $order['bid'];
                            $og['mid']   = $order['mid'];
                            $og['id']    = $order['id'];
                            $og['proid'] = $order['proid'];
                            $og['ordertype']  = 'collage';

                            //记录参与的会员
                            $cashback_member_check = Db::name('cashback_member')->where(['cashback_id'=>$v['id'],'pro_id'=>$og['proid'],'type'=>'collage'])->where('mid',$order['mid'])->where('aid',$order['aid'])->find();
                            if(!$cashback_member_check){
                                $cashback_member = [];
                                $cashback_member['aid']          = $order['aid'];
                                $cashback_member['mid']          = $order['mid'];
                                $cashback_member['cashback_id']  = $v['id'];
                                $cashback_member['back_type']    = $v['back_type'];
                                $cashback_member['pro_id']       = $order['proid'];
                                $cashback_member['pro_num']      = $order['num'];
                                $cashback_member['type']               = 'collage';
                                $cashback_member['cashback_money_max'] = 0;
                                $cashback_member['create_time']        = time();
                                $insert = Db::name('cashback_member')->insert($cashback_member);
                                $cashback_member_check = Db::name('cashback_member')->where(['cashback_id'=>$v['id'],'pro_id'=>$og['proid'],'type'=>'collage'])->where('mid',$order['mid'])->where('aid',$order['aid'])->find();
                            }else{
                                Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('pro_num',$order['num'])->update();
                            }

                            if(!$return_type){
                                if($back_price>0){
                                    if($v['back_type'] == 1 ){
                                        \app\commons\Member::addmoney($aid,$order['mid'],$back_price,$v['name']);
                                        //累计到参与人统计表
                                        Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('cashback_money',$back_price)->update();
                                    }
                                    if($v['back_type'] == 2){
                                        \app\commons\Member::addcommission($aid,$order['mid'],$order['mid'],$back_price,$v['name']);
                                        //累计到参与人统计表
                                        Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('commission',$back_price)->update();
                                    }
                                    if($v['back_type'] == 3){
                                        \app\commons\Member::addscore($aid,$order['mid'],$back_price,$v['name']);
                                        //累计到参与人统计表
                                        Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('score',$back_price)->update();
                                    }
                                    if(getcustom('yx_cashback_time')){
                                        //直接发放
                                        \app\customs\OrderCustom::deal_first_cashback($aid,$order['mid'],$back_price,$og,$v,0,'collage');
                                    }
                                    //写入发放日志
                                    \app\customs\OrderCustom::cashbackMemerDoLog($order['aid'],$order['mid'],$v,$og,$back_price);
                                }
                            }else{
                                if($back_price>0){
                                    if(getcustom('yx_cashback_time') || getcustom('yx_cashback_stage')){
                                        //处理自定义第一次发放
                                        \app\customs\OrderCustom::deal_first_cashback($aid,$order['mid'],$back_price,$og,$v,$return_type,'collage');
                                    }
                                }
                            }
                        }

                    }
                }

            }
        }
    }

    public static function deal_invitecashback($aid,$order,$oglist,$member){
        if(getcustom('yx_invite_cashback')){
            //邀请返现
            //查询上级是否有分销权限
            $parent = Db::name('member')
                ->alias('m')
                ->join('member_level ml','ml.id = m.levelid')
                ->where('m.id',$member['pid'])
                ->where('ml.can_agent','>',0)
                ->where('m.aid',$aid)
                ->field('m.id,m.levelid')
                ->find();
            if($parent){
                foreach($oglist as $og){

                    //查询商品
                    $product = Db::name('shop_product')
                        ->where('id',$og['proid'])
                        ->field('id,bid,cid')
                        ->find();
                    if($product){

                        //查询邀请返现设置
                        $iclist = Db::name('invite_cashback')
                            ->where('aid',$aid)
                            ->where('bid',0)
                            ->where('starttime','<',$order['paytime'])
                            ->where('endtime','>',$order['paytime'])
                            ->order('sort desc')
                            ->select()
                            ->toArray();
                        if($iclist){

                            //能发放奖励的列表
                            $new_iclist = [];
                            //查询是否有单独商品设置 若有则只发单独设置的返现设置
                            $alone   = false;

                            foreach($iclist as $v){
                                //返现设置是否存在
                                $v['invite_cashbak_data']  = $v['invite_cashbak_data'] ? json_decode($v['invite_cashbak_data'],true) : [];
                                if(empty($v['invite_cashbak_data'])){
                                    continue;
                                }

                                $gettj = explode(',',$v['gettj']);
                                if(!in_array('-1',$gettj) && !in_array($parent['levelid'],$gettj)){ //不是所有人
                                    continue;
                                }

                                if($v['fwtype']>=3){
                                    //其他类型不适应
                                    continue;
                                }

                                //指定商品:若指定商品，则不走所有商品及指定类目设置
                                if($v['fwtype']==2){
                                    $productids = explode(',',$v['productids']);
                                    if(!in_array($product['id'],$productids)){
                                        continue;
                                    }
                                    $alone = true;

                                    //如果需上级购买商品
                                    if($v['needbuy'] == 1){
                                        //查询上级是否买过此商品
                                        $ogoods = Db::name('shop_order_goods')
                                            ->alias('og')
                                            ->join('shop_order o','o.id=og.orderid')
                                            ->where('og.proid',$og['proid'])
                                            ->where('o.mid',$parent['id'])
                                            ->where('o.status','in','1,2,3')
                                            ->where('o.aid',$aid)
                                            ->field('og.id')
                                            //->order('o.status desc')
                                            ->find();
                                        if(!$ogoods){
                                            continue;
                                        }
                                    }

                                }else{
                                    //若指定商品，则不走所有商品及指定类目设置
                                    if($alone){
                                        continue;
                                    }

                                    if($v['fwtype']==1){//指定类目可用
                                        $categoryids = explode(',',$v['categoryids']);
                                        $cids  = explode(',',$product['cid']);
                                        $clist = Db::name('shop_category')->where('pid','in',$categoryids)->select()->toArray();
                                        foreach($clist as $vc){
                                            $categoryids[] = $vc['id'];
                                            $cate2 = Db::name('shop_category')->where('pid',$vc['id'])->find();
                                            $categoryids[] = $cate2['id'];
                                        }
                                        if(!array_intersect($cids,$categoryids)){
                                            continue;
                                        }
                                    }

                                    //如果需上级购买商品
                                    if($v['needbuy'] == 1){
                                        $where = [];
                                        $where[] = ['o.mid','=',$parent['id']];
                                        $where[] = ['o.status','in','1,2,3'];

                                        if($v['fwtype']==1){
                                            $whereCid = [];
                                            foreach($categoryids as $cid){
                                                $whereCid[] = "find_in_set({$cid},p.cid)";
                                            }
                                            $where[] = Db::raw(implode(' or ',$whereCid));
                                        }

                                        $where[] = ['o.aid','=',$aid];
                                        //查询上级是否买过商品
                                        $ogoods = Db::name('shop_order_goods')
                                            ->alias('og')
                                            ->join('shop_order o','o.id=og.orderid')
                                            ->join('shop_product p','p.id=og.proid')
                                            ->where($where)
                                            ->field('og.id')
                                            //->order('o.status desc')
                                            ->find();
                                        if(!$ogoods){
                                            continue;
                                        }
                                    }
                                }

                                //是否开启复购：若没开启，则每个邀请人一种商品只能发一次，开启则无邀请人数限制
                                if(!$v['isagain']){
                                    //查询此用户此商品是否已给上级记录过，记录过则不在发放
                                    $count = Db::name('member_invite_cashback_log')
                                        ->where('order_mid',$member['id'])
                                        ->where('mid',$parent['id'])
                                        ->where('cashback_id',$v['id']);
                                    if($v['fwtype']==2){
                                        $count = $count->where('proid',$og['proid']);
                                    }
                                    $count = $count
                                        ->where('status','>=',0)
                                        ->count();
                                    if($count){
                                        continue;
                                    }
                                }

                                //查询上级已返现过的次数
                                $pnum = Db::name('member_invite_cashback_log')
                                    ->where('mid',$parent['id']);
                                if($v['fwtype']==2){
                                    $pnum = $pnum->where('proid',$og['proid']);
                                }
                                $pnum = $pnum
                                    ->where('cashback_id',$v['id'])
                                    ->count();
                                $v['pnum'] = 0 + $pnum;

                                //查询设置的推N返一次数
                                $v['icnum'] = count($v['invite_cashbak_data']);

                                //若没有开启循环，则只按顺序发一次
                                if(!$v['iscycle'] && $v['pnum']>=$v['icnum']){
                                    continue;
                                }

                                //如果开启循环，且开启了循环次数限制，则需要查看循环几次后固定发奖设置
                                if($v['iscycle'] && $v['cyclenum']>0){
                                    //若固定发奖未设置则跳过发放
                                    if($v['cyclemoney'] <=0 && $v['cyclescore']<=0 && $v['cyclecommission']<=0 && $v['cyclemoney2'] <=0 && $v['cyclescore2']<=0 && $v['cyclecommission2']<=0){
                                        continue;
                                    }
                                }

                                array_push($new_iclist,$v);
                            }
                            unset($v);

                            //如果可以发放
                            if($new_iclist){
                                foreach($new_iclist as $v){
                                    //若有单独设置则只发单独设置的返现设置
                                    if($alone){
                                        if($v['fwtype']==2){
                                            //返现金额
                                            $back_price = $og['real_totalprice'];
                                            if(getcustom('money_dec')){
                                                //如果是余额抵扣，则要加上余额部分
                                                if($og['dec_money'] && $og['dec_money'] >0){
                                                    $back_price = $og['real_totalprice']+$og['dec_money'];
                                                }
                                            }
                                            if($back_price>0){
                                                //发放返回
                                                $mid_order_gid = $ogoods && $ogoods['id']?$ogoods['id']:0;
                                                self::deal_sendback($aid,$order,$og,$parent,$v,$back_price,$mid_order_gid);
                                            }
                                        }
                                    }else{
                                        //返现金额
                                        $back_price = $og['real_totalprice'];
                                        if(getcustom('money_dec')){
                                            //如果是余额抵扣，则要加上余额部分
                                            if($og['dec_money'] && $og['dec_money'] >0){
                                                $back_price = $og['real_totalprice']+$og['dec_money'];
                                            }
                                        }
                                        if($back_price>0){
                                            //发放返回
                                            $mid_order_gid = $ogoods && $ogoods['id']?$ogoods['id']:0;
                                            self::deal_sendback($aid,$order,$og,$parent,$v,$back_price,$mid_order_gid);
                                        }
                                    }
                                }
                                unset($v);
                            }
                        }
                        
                    }
                }
            }
        }
    }

    public static function deal_sendback($aid,$order,$og,$parent,$set,$back_price,$mid_order_gid=0){
        if(getcustom('yx_invite_cashback')){

            //固定余额返现
            $money       = 0;
            //百分比余额返现
            $money2      = 0;
            //固定积分返现
            $score       = 0;
            //百分比积分返现
            $score2      = 0;
            //固定佣金返现
            $commission  = 0;
            //百分比佣金返现
            $commission2 = 0;

            if(getcustom('yx_invite_cashback_ordertj')){
                //判断是否是付款后就发放
                if($set['ordertj'] && $set['ordertj'] == 1){
                    //如果开启循环，且开启了循环次数限制
                    if($set['iscycle'] && $set['cyclenum']>0){
                        //查询已循环的次数
                        $num = floor($set['pnum'] / $set['icnum']);
                        //若循环次数超出，则需要按照循环次数后固定发奖设置
                        if($num>=$set['cyclenum']){
                            //固定余额返现
                            $money       = $set['cyclemoney'];
                            //百分比
                            $money2      = $set['cyclemoney2']*$back_price/100/$og['num'];
                            //固定积分返现
                            $score       = $set['cyclescore'];
                            //百分比
                            $score2      = $set['cyclescore2']*$back_price/100/$og['num'];
                            //固定佣金返现
                            $commission  = $set['cyclecommission'];
                            //百分比
                            $commission2 = $set['cyclecommission2']*$back_price/100/$og['num'];
                        }else{
                            //查询余数 = 上级已返现过的次数%设置的推N返一次数
                            $ynum = $set['pnum'] % $set['icnum'];
                            foreach($set['invite_cashbak_data'] as $ik=>$iv){
                                if($ik == $ynum){
                                    //固定余额返现
                                    $money       = $iv['money'];
                                    //百分比余额返现
                                    $money2      = $iv['money2']*$back_price/100/$og['num'];
                                    //固定积分返现
                                    $score       = $iv['score'];
                                    //百分比积分返现
                                    $score2      = $iv['score2']*$back_price/100/$og['num'];
                                    //固定佣金返现
                                    $commission  = $iv['commission'];
                                    //百分比佣金返现
                                    $commission2 = $iv['commission2']*$back_price/100/$og['num'];
                                }
                            }
                            unset($ik);
                            unset($iv);
                        }
                    }else{
                        //查询余数 = 上级已返现过的次数%设置的推N返一次数
                        $ynum = $set['pnum'] % $set['icnum'];
                        foreach($set['invite_cashbak_data'] as $ik=>$iv){
                            if($ik == $ynum){
                                //固定余额返现
                                $money       = $iv['money'];
                                //百分比余额返现
                                $money2      = $iv['money2']*$back_price/100/$og['num'];
                                //固定积分返现
                                $score       = $iv['score'];
                                //百分比积分返现
                                $score2      = $iv['score2']*$back_price/100/$og['num'];
                                //固定佣金返现
                                $commission  = $iv['commission'];
                                //百分比佣金返现
                                $commission2 = $iv['commission2']*$back_price/100/$og['num'];
                            }
                        }
                        unset($ik);
                        unset($iv);
                    }
                }
            }

            //总余额
            $allmoney = $money+$money2;
            $allmoney = round($allmoney,2);
            //总积分
            $allscore = $score+$score2;
            $allscore = intval($allscore);
            //总佣金
            $allcommission = $commission+$commission2;
            $allcommission = round($allcommission,2);

            //添加返回记录
            $log = [];
            $log['aid'] = $aid;
            $log['mid'] = $parent['id'];//返回者

            $status = 0;
            if(getcustom('yx_invite_cashback_ordertj')){
                //判断是否是付款后就发放
                if($set['ordertj'] && $set['ordertj'] == 1){
                    $log['mid_order_gid'] = $mid_order_gid;
                    $status = 1;
                }
            }
            //商品信息
            $log['proid']        = $og['proid'];
            $log['num']          = $og['num'];
            $log['back_price']   = $back_price;

            $log['order_id']     = $order['id'];//订单id
            $log['order_gid']    = $og['id'];//订单商品id
            $log['order_mid']    = $order['mid'];//订单用户id
            $log['cashback_id']  = $set['id'];//邀请返现设置id
            $log['productids']   = !empty($set['productids'])?$set['productids']:'';
            $log['categoryids']  = !empty($set['categoryids'])?$set['categoryids']:'';

            $log['money']        = $money;
            $log['money2']       = $money2;
            $log['allmoney']     = $allmoney;
            $log['score']        = $score;
            $log['score2']       = $score2;
            $log['allscore']     = $allscore;
            $log['commission']   = $commission;
            $log['commission2']  = $commission2;
            $log['allcommission']= $allcommission;
            $log['status']       = $status;
            $log['create_time']  = time();
            $log['update_time']  = time();
            $insert = Db::name('member_invite_cashback_log')->insert($log);
            if(getcustom('yx_invite_cashback_ordertj')){
                //判断是否是付款后就发放
                if($set['ordertj'] && $set['ordertj'] == 1){
                    if($insert){
                        $remark = '商品'.$og['name'].'邀请返还';
                        if($allmoney>0 ){
                            \app\commons\Member::addmoney($aid,$parent['id'],$allmoney,$remark);
                        }
                        if($allscore){
                            \app\commons\Member::addscore($aid,$parent['id'],$allscore,$remark);
                        }
                        if($allcommission>0){
                            \app\commons\Member::addcommission($aid,$parent['id'],$order['mid'],$allcommission,$remark);
                        }
                    }
                }
            }

        }
    }
    public static function deal_ictips($aid,$mid,$type=0,$prodoucts=[]){
        
        if(getcustom('yx_invite_cashback')){

            if($type == 1){
                $payorder = $prodoucts;
                if($payorder['type'] == 'shop'){
                    $proids = Db::name('shop_order_goods')->where('orderid',$payorder['orderid'])->where('aid',$aid)->column('proid');
                }else{
                    $proids = Db::name('shop_order_goods')->where('ordernum', 'like',  $payorder['ordernum'] . '_%' )->where('aid',$aid)->column('proid');
                }
                if($proids){
                    $where   = [];
                    $where[] = ['id','in',$proids];
                    $where[] = ['aid','=',$aid];
                    $nowtime   = time();
                    $nowhm     = date('H:i');
                    $where[] = Db::raw("`status`=1 or (`status`=2 and unix_timestamp(start_time)<=$nowtime and unix_timestamp(end_time)>=$nowtime) or (`status`=3 and ((start_hours<end_hours and start_hours<='$nowhm' and end_hours>='$nowhm') or (start_hours>=end_hours and (start_hours<='$nowhm' or end_hours>='$nowhm'))) )");
                    $where[] = ['ischecked','=',1];
                    $prodoucts = Db::name('shop_product')->where($where)->field('id,aid,bid,cid,pic,name')->select()->toArray();
                }else{
                    $prodoucts = [];
                }
            }

            $member = Db::name('member')
                ->alias('m')
                ->join('member_level ml','ml.id = m.levelid')
                ->where('m.id',$mid)
                ->where('ml.can_agent','>',0)
                ->where('m.aid',$aid)
                ->field('m.id,m.levelid')
                ->find();

            $data = [];
            $data['ictips']  = '';
            $data['proid']   = '';
            $data['propic']  = '';
            $data['proname'] = '';
            if($prodoucts){
                //查询邀请返现
                $iclist = Db::name('invite_cashback')
                    ->where('aid',$aid)
                    ->where('bid',0)
                    ->where('starttime','<',time())
                    ->where('endtime','>',time())
                    ->order('sort desc')
                    ->select()
                    ->toArray();

                if($iclist){
                    //能发放奖励的列表
                    $new_iclist = [];
                    //查询是否有单独商品设置 若有则只发单独设置的返现设置
                    $alone   = false;
                    foreach($prodoucts as $product){
                        if($data['ictips']){
                            break;
                        }
                        foreach($iclist as $v){

                            //返现设置是否存在
                            $v['invite_cashbak_data']  = $v['invite_cashbak_data'] ? json_decode($v['invite_cashbak_data'],true) : [];
                            if(empty($v['invite_cashbak_data'])){
                                continue;
                            }

                            $gettj = explode(',',$v['gettj']);
                            if(!in_array('-1',$gettj) && !in_array($member['levelid'],$gettj)){ //不是所有人
                                continue;
                            }

                            if($v['fwtype']>=3){
                                //其他类型不适应
                                continue;
                            }

                            if($v['fwtype']==2){//指定商品可用
                                $productids = explode(',',$v['productids']);
                                if(!in_array($product['id'],$productids)){
                                    continue;
                                }
                                $alone = true;
                            }else if($v['fwtype']==1){//指定类目可用
                                $categoryids = explode(',',$v['categoryids']);
                                $cids  = explode(',',$product['cid']);
                                $clist = Db::name('shop_category')->where('pid','in',$categoryids)->select()->toArray();
                                foreach($clist as $vc){
                                    $categoryids[] = $vc['id'];
                                    $cate2 = Db::name('shop_category')->where('pid',$vc['id'])->find();
                                    $categoryids[] = $cate2['id'];
                                }
                                if(!array_intersect($cids,$categoryids)){
                                    continue;
                                }
                            }
                            array_push($new_iclist,$v);
                        }
                        //如果可以发放
                        if($new_iclist){
                            foreach($new_iclist as $v){
                                //若有单独设置则只发单独设置的返现设置
                                if($alone){
                                    if($v['fwtype']==2){
                                        //计算N数值
                                        $icnum = count($v['invite_cashbak_data']);
                                        if($icnum>0){
                                            $data['ictips']  = $type?'推荐'.$icnum.'个人消费全返，点击分享好友':'推荐'.$icnum.'个人消费全返';
                                            $data['proid']   = $product['id'];
                                            $data['propic']  = $product['pic'];
                                            $data['proname'] = $product['name'];
                                        }
                                    }
                                }else{
                                    //计算N数值
                                    $icnum = count($v['invite_cashbak_data']);
                                    if($icnum>0){
                                        $data['ictips']  = $type?'推荐'.$icnum.'个人消费全返，点击分享好友':'推荐'.$icnum.'个人消费全返';
                                        $data['proid']   = $product['id'];
                                        $data['propic']  = $product['pic'];
                                        $data['proname'] = $product['name'];
                                    }
                                }
                            }
                            unset($v);
                        }
                    }
                    
                }
            }
            return $data;
        }
    }

    public static function deal_invitecashback2($aid,$order,$oglist,$member){
        if(getcustom('yx_invite_cashback')){
            //处理邀请邀请返现
            foreach($oglist as $og){

                //查询商品
                $product = Db::name('shop_product')
                    ->where('id',$og['proid'])
                    ->field('id,bid,cid')
                    ->find();
                if(!$product){
                    Db::name('member_invite_cashback_log')->where('proid',$og['proid'])->where('status',0)->update(['status'=>-1,'update_time'=>time(),'cancel_time'=>time(),'reason'=>'商品不存在']);
                    continue;
                }

                //查询记录是否存在
                $loglist = Db::name('member_invite_cashback_log')
                    ->where('order_id',$order['id'])
                    ->where('proid',$og['proid'])
                    ->where('order_mid',$order['mid'])
                    ->where('status',0)
                    ->select()
                    ->toArray();
                if($loglist){

                    foreach($loglist as $lv){

                        //查询上级是否有分销权限
                        $parent = Db::name('member')
                            ->alias('m')
                            ->join('member_level ml','ml.id = m.levelid')
                            ->where('m.id',$lv['mid'])
                            ->where('ml.can_agent','>',0)
                            ->where('m.aid',$aid)
                            ->field('m.id,m.levelid')
                            ->find();
                        if(!$parent){
                            Db::name('member_invite_cashback_log')->where('id',$lv['id'])->where('status',0)->update(['status'=>-1,'update_time'=>time(),'cancel_time'=>time(),'reason'=>'上级不存在或无分销权限']);
                            continue;
                        }

                        //查询邀请返现
                        $set = Db::name('invite_cashback')
                            ->where('id',$lv['cashback_id'])
                            ->where('aid',$aid)
                            ->where('bid',0)
                            ->where('starttime','<',$order['paytime'])
                            ->where('endtime','>',$order['paytime'])
                            ->find();
                        if(!$set){
                            Db::name('member_invite_cashback_log')->where('id',$lv['id'])->where('status',0)->update(['status'=>-1,'update_time'=>time(),'cancel_time'=>time(),'reason'=>'邀请返现活动不存在或已结束']);
                            continue;
                        }

                        if(getcustom('yx_invite_cashback_ordertj')){
                            //判断是否是确认收货后发放,不是则不发放
                            if($set['ordertj'] || $set['ordertj'] == 1){
                                continue;
                            }
                        }

                        if($set['fwtype']==2){
                            $productids = explode(',',$set['productids']);
                            if(!in_array($product['id'],$productids)){
                                Db::name('member_invite_cashback_log')->where('id',$lv['id'])->where('status',0)->update(['status'=>-1,'update_time'=>time(),'cancel_time'=>time(),'reason'=>'邀请活动已失效']);
                                continue;
                            }

                            //如果需上级购买商品
                            if($set['needbuy'] == 1){
                                //再验证上级是否买过此商品
                                $ogoods = Db::name('shop_order_goods')
                                    ->alias('og')
                                    ->join('shop_order o','o.id=og.orderid')
                                    ->where('og.proid',$og['proid'])
                                    ->where('o.mid',$lv['mid'])
                                    ->where('o.status',3)
                                    ->where('o.aid',$aid)
                                    ->field('og.id')
                                    ->find();
                                if(!$ogoods){
                                    Db::name('member_invite_cashback_log')->where('id',$lv['id'])->where('status',0)->update(['status'=>-1,'update_time'=>time(),'cancel_time'=>time(),'reason'=>'上级购买此商品记录不存在或订单未完成']);
                                    continue;
                                }
                            }
                            $alone = true;
                        }else{
                            if($set['fwtype']==1){//指定类目可用
                                $categoryids = explode(',',$set['categoryids']);
                                $cids  = explode(',',$product['cid']);
                                $clist = Db::name('shop_category')->where('pid','in',$categoryids)->select()->toArray();
                                foreach($clist as $vc){
                                    $categoryids[] = $vc['id'];
                                    $cate2 = Db::name('shop_category')->where('pid',$vc['id'])->find();
                                    $categoryids[] = $cate2['id'];
                                }
                                if(!array_intersect($cids,$categoryids)){
                                    Db::name('member_invite_cashback_log')->where('id',$lv['id'])->where('status',0)->update(['status'=>-1,'update_time'=>time(),'cancel_time'=>time(),'reason'=>'邀请活动已失效']);
                                    continue;
                                }
                            }

                            //如果需上级购买商品
                            if($set['needbuy'] == 1){
                                $where = [];
                                $where[] = ['o.mid','=',$lv['mid']];
                                $where[] = ['o.status','=',3];

                                if($set['fwtype']==1){
                                    $whereCid = [];
                                    foreach($categoryids as $cid){
                                        $whereCid[] = "find_in_set({$cid},p.cid)";
                                    }
                                    $where[] = Db::raw(implode(' or ',$whereCid));
                                }

                                $where[] = ['o.aid','=',$aid];
                                //再验证上级是否买过商品
                                $ogoods = Db::name('shop_order_goods')
                                    ->alias('og')
                                    ->join('shop_order o','o.id=og.orderid')
                                    ->join('shop_product p','p.id=og.proid')
                                    ->where($where)
                                    ->field('og.id')
                                    ->find();
                                if(!$ogoods){
                                    Db::name('member_invite_cashback_log')->where('id',$lv['id'])->where('status',0)->update(['status'=>-1,'update_time'=>time(),'cancel_time'=>time(),'reason'=>'上级购买记录不存在或订单未完成']);
                                    continue;
                                }
                            }
                        } 

                        //返现设置数据是否存在
                        $set['invite_cashbak_data']  = $set['invite_cashbak_data'] ? json_decode($set['invite_cashbak_data'],true) : [];
                        if(empty($set['invite_cashbak_data'])){
                            Db::name('member_invite_cashback_log')->where('id',$lv['id'])->update(['status'=>-1,'update_time'=>time(),'cancel_time'=>time(),'reason'=>'邀请返现活动已失效']);
                            continue;
                        }

                        $gettj = explode(',',$set['gettj']);
                        if(!in_array('-1',$gettj) && !in_array($parent['levelid'],$gettj)){ //不是所有人
                            Db::name('member_invite_cashback_log')->where('id',$lv['id'])->update(['status'=>-1,'update_time'=>time(),'cancel_time'=>time(),'reason'=>'上级等级不符']);
                            continue;
                        }

                        if($set['fwtype']>=3){
                            //其他类型不适应
                            Db::name('member_invite_cashback_log')->where('id',$lv['id'])->update(['status'=>-1,'update_time'=>time(),'cancel_time'=>time(),'reason'=>'邀请返现类型错误']);
                            continue;
                        }

                        //是否开启复购，若没开启，则每个邀请人只能发一次，开启则无邀请人数限制
                        if(!$set['isagain']){
                            //查询此用户此商品是否已给上级记录过，记录过则不在发放
                            $count = Db::name('member_invite_cashback_log')
                                ->where('order_mid',$member['id'])
                                ->where('mid',$parent['id'])
                                ->where('cashback_id',$set['id']);
                            if($set['fwtype']==2){
                                 $count = $count->where('proid',$og['proid']);
                            }
                            $count = $count
                                ->where('status',1)
                                ->count();
                            if($count){
                                Db::name('member_invite_cashback_log')->where('id',$lv['id'])->update(['status'=>-1,'update_time'=>time(),'cancel_time'=>time(),'reason'=>'此邀请人此商品已给上级发放过返现']);
                                continue;
                            }
                        }

                        //查询上级已返现过的次数
                        $pnum = Db::name('member_invite_cashback_log')
                            ->where('mid',$parent['id']);
                        if($set['fwtype']==2){
                            $pnum = $pnum->where('proid',$og['proid']);
                        }
                        $pnum = $pnum
                            ->where('cashback_id',$set['id'])
                            ->where('status',1)
                            ->count();
                        $set['pnum'] = 0+$pnum;

                        //查询设置的推N返一次数
                        $set['icnum'] = count($set['invite_cashbak_data']);

                        //若没有开启循环，则只按顺序发一次
                        if(!$set['iscycle'] && $set['pnum']>=$set['icnum']){
                            Db::name('member_invite_cashback_log')->where('id',$lv['id'])->update(['status'=>-1,'update_time'=>time(),'cancel_time'=>time(),'reason'=>'此邀请返现活动上级奖励已全部发放完毕']);
                            continue;
                        }

                        //如果开启循环，且开启了循环次数限制，则需要查看循环几次后固定发奖设置
                        if($set['iscycle'] && $set['cyclenum']>0){
                            //若固定发奖未设置则跳过发放
                            if($set['cyclemoney'] <=0 && $set['cyclescore']<=0 && $set['cyclecommission']<=0 && $set['cyclemoney2'] <=0 && $set['cyclescore2']<=0 && $set['cyclecommission2']<=0){
                                Db::name('member_invite_cashback_log')->where('id',$lv['id'])->update(['status'=>-1,'update_time'=>time(),'cancel_time'=>time(),'reason'=>'循环次数已满']);
                                continue;
                            }
                        }

                        //返现金额
                        $back_price = $og['real_totalprice'];
                        if(getcustom('money_dec')){
                            //如果是余额抵扣，则要加上余额部分
                            if($og['dec_money'] && $og['dec_money'] >0){
                                $back_price = $og['real_totalprice']+$og['dec_money'];
                            }
                        }
                        if($back_price>0){
                            //发放返回
                            $mid_order_gid = $ogoods && $ogoods['id']?$ogoods['id']:0;
                            self::deal_sendback2($aid,$mid_order_gid,$lv['id'],$order,$og,$parent,$set,$back_price);
                        }else{
                            Db::name('member_invite_cashback_log')->where('id',$lv['id'])->update(['status'=>-1,'update_time'=>time(),'cancel_time'=>time(),'reason'=>'订单商品实际金额小于等于0']);
                            continue;
                        }
                    }
                }
            }
        }
    }
    public static function deal_sendback2($aid,$mid_order_gid,$logid,$order,$og,$parent,$set,$back_price){
        if(getcustom('yx_invite_cashback')){

            //固定余额返现
            $money       = 0;
            //百分比余额返现
            $money2      = 0;
            //固定积分返现
            $score       = 0;
            //百分比积分返现
            $score2      = 0;
            //固定佣金返现
            $commission  = 0;
            //百分比佣金返现
            $commission2 = 0;
            
            //如果开启循环，且开启了循环次数限制
            if($set['iscycle'] && $set['cyclenum']>0){
                //查询已循环的次数
                $num = floor($set['pnum'] / $set['icnum']);
                //若循环次数超出，则需要按照循环次数后固定发奖设置
                if($num>=$set['cyclenum']){
                    //固定余额返现
                    $money       = $set['cyclemoney'];
                    //百分比
                    $money2      = $set['cyclemoney2']*$back_price/100/$og['num'];
                    //固定积分返现
                    $score       = $set['cyclescore'];
                    //百分比
                    $score2      = $set['cyclescore2']*$back_price/100/$og['num'];
                    //固定佣金返现
                    $commission  = $set['cyclecommission'];
                    //百分比
                    $commission2 = $set['cyclecommission2']*$back_price/100/$og['num'];
                }else{
                    //查询余数 = 上级已返现过的次数%设置的推N返一次数
                    $ynum = $set['pnum'] % $set['icnum'];
                    foreach($set['invite_cashbak_data'] as $ik=>$iv){
                        if($ik == $ynum){
                            //固定余额返现
                            $money       = $iv['money'];
                            //百分比余额返现
                            $money2      = $iv['money2']*$back_price/100/$og['num'];
                            //固定积分返现
                            $score       = $iv['score'];
                            //百分比积分返现
                            $score2      = $iv['score2']*$back_price/100/$og['num'];
                            //固定佣金返现
                            $commission  = $iv['commission'];
                            //百分比佣金返现
                            $commission2 = $iv['commission2']*$back_price/100/$og['num'];
                        }
                    }
                    unset($ik);
                    unset($iv);
                }
            }else{
                //查询余数 = 上级已返现过的次数%设置的推N返一次数
                $ynum = $set['pnum'] % $set['icnum'];
                foreach($set['invite_cashbak_data'] as $ik=>$iv){
                    if($ik == $ynum){
                        //固定余额返现
                        $money       = $iv['money'];
                        //百分比余额返现
                        $money2      = $iv['money2']*$back_price/100/$og['num'];
                        //固定积分返现
                        $score       = $iv['score'];
                        //百分比积分返现
                        $score2      = $iv['score2']*$back_price/100/$og['num'];
                        //固定佣金返现
                        $commission  = $iv['commission'];
                        //百分比佣金返现
                        $commission2 = $iv['commission2']*$back_price/100/$og['num'];
                    }
                }
                unset($ik);
                unset($iv);
            }

            //总余额
            $allmoney = $money+$money2;
            $allmoney = round($allmoney,2);
            //总积分
            $allscore = $score+$score2;
            $allscore = intval($allscore);
            //总佣金
            $allcommission = $commission+$commission2;
            $allcommission = round($allcommission,2);

            //添加返回记录
            $log = [];
            $log['mid_order_gid'] = $mid_order_gid;//返回者购买订单商品id

            //商品信息
            $log['num']          = $og['num'];
            $log['back_price']   = $back_price;
            //$log['cashback_id']  = $set['id'];//邀请返现设置id

            $log['money']        = $money;
            $log['money2']       = $money2;
            $log['allmoney']     = $allmoney;
            $log['score']        = $score;
            $log['score2']       = $score2;
            $log['allscore']     = $allscore;
            $log['commission']   = $commission;
            $log['commission2']  = $commission2;
            $log['allcommission']= $allcommission;
            $log['status']       = 1;
            $log['update_time']  = time();
            $insert = Db::name('member_invite_cashback_log')->where('status',0)->where('id',$logid)->update($log);

            if($insert){
                $remark = '商品'.$og['name'].'邀请返还';
                if($allmoney>0 ){
                    \app\commons\Member::addmoney($aid,$parent['id'],$allmoney,$remark);
                }
                if($allscore){
                    \app\commons\Member::addscore($aid,$parent['id'],$allscore,$remark);
                }
                if($allcommission>0){
                    \app\commons\Member::addcommission($aid,$parent['id'],$order['mid'],$allcommission,$remark);
                }
            }
        }
    }

    public static function count_sendback($aid,$log){
        if(getcustom('yx_invite_cashback')){
            //计算未发放的记录奖励
            $status = $log['status'];

            //固定余额返现
            $money       = 0;
            //百分比余额返现
            $money2      = 0;
            //固定积分返现
            $score       = 0;
            //百分比积分返现
            $score2      = 0;
            //固定佣金返现
            $commission  = 0;
            //百分比佣金返现
            $commission2 = 0;

            $order = Db::name('shop_order')->where('id',$log['order_id'])->where('aid',$aid)->field('paytime')->find();
            if($order){
                //查询邀请返现
                $set = Db::name('invite_cashback')
                    ->where('id',$log['cashback_id'])
                    ->where('aid',$aid)
                    ->where('bid',0)
                    ->where('starttime','<',$order['paytime'])
                    ->where('endtime','>',$order['paytime'])
                    ->find();
                if($set){

                    $set['invite_cashbak_data'] = $set['invite_cashbak_data']?json_decode($set['invite_cashbak_data'],true):[];
                    if(!empty($set['invite_cashbak_data'])){
                        //查询上级已返现过的次数
                        $pnum1 = Db::name('member_invite_cashback_log')
                            ->where('id','<',$log['id'])
                            ->where('mid',$log['mid']);
                        if($set['fwtype']==2){
                            $pnum1 = $pnum1->where('proid',$log['proid']);
                        }
                        $pnum1 = $pnum1
                            ->where('cashback_id',$set['id'])
                            ->where('status',0)
                            ->count();
                        $pnum1 = 0+$pnum1;

                        $pnum2 = Db::name('member_invite_cashback_log')
                            ->where('mid',$log['mid']);
                        if($set['fwtype']==2){
                            $pnum2 = $pnum2->where('proid',$log['proid']);
                        }
                        $pnum2 = $pnum2
                            ->where('cashback_id',$set['id'])
                            ->where('status',1)
                            ->count();
                        $pnum2 = 0+$pnum2;

                        $set['pnum'] =  $pnum1+$pnum2;
                        //查询设置的推N返一次数
                        $set['icnum'] = count($set['invite_cashbak_data']);

                        //若没有开启循环，则只按顺序发一次
                        if(!$set['iscycle'] && $set['pnum']>=$set['icnum']){
                            Db::name('member_invite_cashback_log')->where('id',$log['id'])->update(['status'=>-1,'cancel_time'=>time(),'reason'=>'此邀请返现活动上级奖励已全部发放完毕']);
                            $status = -1;
                        }else{
                            //如果开启循环，且开启了循环次数限制，则需要查看循环几次后固定发奖设置
                            if($set['iscycle'] && $set['cyclenum']>0){
                                //若固定发奖未设置则跳过发放
                                if($set['cyclemoney'] <=0 && $set['cyclescore']<=0 && $set['cyclecommission']<=0 && $set['cyclemoney2'] <=0 && $set['cyclescore2']<=0 && $set['cyclecommission2']<=0){
                                    Db::name('member_invite_cashback_log')->where('id',$log['id'])->update(['status'=>-1,'update_time'=>time(),'cancel_time'=>time(),'reason'=>'循环次数已满']);
                                    $status = -1;
                                }else{
                                    //查询已循环的次数
                                    $num = floor($set['pnum'] / $set['icnum']);
                                    //若循环次数超出，则需要按照循环次数后固定发奖设置
                                    if($num>=$set['cyclenum']){
                                        //固定余额返现
                                        $money       = $set['cyclemoney'];
                                        $money2      = $set['cyclemoney2']*$log['back_price']/100/$log['num'];
                                        //固定积分返现
                                        $score       = $set['cyclescore'];
                                        $score2      = $set['cyclescore2']*$log['back_price']/100/$log['num'];
                                        //固定佣金返现
                                        $commission  = $set['cyclecommission'];
                                        $commission2 = $set['cyclecommission2']*$log['back_price']/100/$log['num'];
                                    }else{
                                        //查询余数 = 上级已返现过的次数%设置的推N返一次数
                                        $ynum = $set['pnum'] % $set['icnum'];
                                        foreach($set['invite_cashbak_data'] as $ik=>$iv){
                                            if($ik == $ynum){
                                                //固定余额返现
                                                $money       = $iv['money'];
                                                //百分比余额返现
                                                $money2      = $iv['money2']*$log['back_price']/100/$log['num'];
                                                //固定积分返现
                                                $score       = $iv['score'];
                                                //百分比积分返现
                                                $score2      = $iv['score2']*$log['back_price']/100/$log['num'];
                                                //固定佣金返现
                                                $commission  = $iv['commission'];
                                                //百分比佣金返现
                                                $commission2 = $iv['commission2']*$log['back_price']/100/$log['num'];
                                            }
                                        }
                                        unset($ik);
                                        unset($iv);
                                    }
                                }
                            }else{
                                //查询余数 = 上级已返现过的次数%设置的推N返一次数
                                $ynum = $set['pnum'] % $set['icnum'];
                                foreach($set['invite_cashbak_data'] as $ik=>$iv){
                                    if($ik == $ynum){
                                        //固定余额返现
                                        $money       = $iv['money'];
                                        //百分比余额返现
                                        $money2      = $iv['money2']*$log['back_price']/100/$log['num'];
                                        //固定积分返现
                                        $score       = $iv['score'];
                                        //百分比积分返现
                                        $score2      = $iv['score2']*$log['back_price']/100/$log['num'];
                                        //固定佣金返现
                                        $commission  = $iv['commission'];
                                        //百分比佣金返现
                                        $commission2 = $iv['commission2']*$log['back_price']/100/$log['num'];
                                    }
                                }
                                unset($ik);
                                unset($iv);
                            }

                            //总余额
                            $allmoney = $money+$money2;
                            $allmoney = round($allmoney,2);
                            //总积分
                            $allscore = $score+$score2;
                            $allscore = intval($allscore);
                            //总佣金
                            $allcommission = $commission+$commission2;
                            $allcommission = round($allcommission,2);
                        }

                    }else{
                        Db::name('member_invite_cashback_log')->where('id',$log['id'])->update(['status'=>-1,'cancel_time'=>time(),'reason'=>'邀请返现活动已失效']);
                        $status = -1;
                    }
                    
                }else{
                    Db::name('member_invite_cashback_log')->where('id',$log['id'])->update(['status'=>-1,'cancel_time'=>time(),'reason'=>'邀请返现活动不存在或已结束']);
                    $status = -1;
                }
            }else{
                Db::name('member_invite_cashback_log')->where('id',$log['id'])->update(['status'=>-1,'cancel_time'=>time(),'reason'=>'下级订单不存在']);
                $status = -1;
            }

            $data = [];
            //商品信息
            $data['money']        = $money;
            $data['money2']       = $money2;
            $data['allmoney']     = $allmoney;
            $data['score']        = $score;
            $data['score2']       = $score2;
            $data['allscore']     = $allscore;
            $data['commission']   = $commission;
            $data['commission2']  = $commission2;
            $data['allcommission']= $allcommission;
            $data['allcommission']= $allcommission;
            $data['status']       = $status;

            return $data;
        }
    }

    public function cancel_invitecashbacklog($aid,$order,$reason=''){
        $open_cashback = 0;
        if(getcustom('commission_orderrefund_deduct')){
            $open_cashback = 1;
            $open_commission_orderrefund_deduct = Db::name('admin_set')->where('aid',$aid)->value('open_commission_orderrefund_deduct');
            if($open_commission_orderrefund_deduct !=1){
                $open_cashback = 0;
            }
        }
        if(getcustom('yx_invite_cashback')){
            //已发放佣金退回
            if($open_cashback == 1){
                //查询记录是否存在
                $loglist = Db::name('member_invite_cashback_log')
                ->where('order_id',$order['id'])
                ->where('order_mid',$order['mid'])
                ->where('status',1)
                ->select()
                ->toArray();
                foreach($loglist as $k=>$v){
                    $allmoney = $v['allmoney'];
                    $allscore = $v['allscore'];
                    $allcommission = $v['allcommission'];     
                    $remark = $reason.'扣除';
                    if($allmoney>0 ){
                       // \app\commons\Member::addmoney($aid,$v['mid'],-1*$allmoney,$remark);
                    }
                    if($allscore){
                       // \app\commons\Member::addscore($aid,$v['mid'],-1*$allscore,$remark);
                    }
                    if($allcommission>0){
                        \app\commons\Member::addcommission($aid,$v['mid'],$order['mid'],-1*$allcommission,$remark);
                    }

                }
                $logs = Db::name('member_invite_cashback_log')
                ->where('order_id',$order['id'])
                ->where('order_mid',$order['mid'])
                ->where('aid',$aid)
                ->where('status',1)
                ->update(['status'=>-1,'cancel_time'=>time(),'reason'=>$reason]);
            }
            
            //订单取消返回
            $logs = Db::name('member_invite_cashback_log')
                ->where('order_id',$order['id'])
                ->where('order_mid',$order['mid'])
                ->where('aid',$aid)
                ->where('status',0)
                ->update(['status'=>-1,'cancel_time'=>time(),'reason'=>$reason]);
        }
    }
    /**
     * 所有参与活动的用户平均发放返现记录
     * aid 商家id
     * mid 会员id
     * cashback 购物返现活动
     * og 商品
     * back_price_total 返现数量
     */   
     
     public static function cashbackMemerDoLog($aid,$mid,$cashback,$og,$back_price_total){
        $cashback_member = [];
        $cashback_member['aid'] = $aid;
        $cashback_member['mid'] = $mid;
        $cashback_member['cashback_id'] = $cashback['id'];
        $cashback_member['pro_id'] = $og['proid'];
        if($cashback['back_type'] == 1 ){
            $cashback_member['cashback_money'] = $back_price_total;
        }else if($cashback['back_type'] == 2 ){
            $cashback_member['commission'] = $back_price_total;
        }else if($cashback['back_type'] == 3 ){
            $cashback_member['score'] = $back_price_total;
        }
        $cashback_member['back_type']   = $cashback['back_type'];
        $cashback_member['type']        = $og['ordertype']??'shop';
        $cashback_member['create_time'] = time();
        $insert = Db::name('cashback_member_log')->insert($cashback_member);
        return $insert;
    }

    public static function deal_refund_combine($order,$money){
        if(getcustom('pay_money_combine')){
            //处理余额组合支付退款
            $data = [];
            $data['status'] = 1;
            $data['refund_combine_money']  = 0;//退余额部分
            $data['refund_combine_wxpay']  = 0;//退微信部分
            $data['refund_combine_alipay'] = 0;//退支付宝部分
            //先退微信或支付宝，后退余额
            if($order['combine_money']>0 || $order['combine_wxpay']>0 || $order['combine_alipay']>0){
                if($order['combine_wxpay']>0){
                    //统计是否已退完
                    $cha = $order['combine_wxpay'] - $order['refund_combine_wxpay'];
                    if($cha>0){
                        //判断能微信能退多少
                        $cha2 = $money-$cha;
                        if($cha2>=0){
                            $data['refund_combine_wxpay'] = $cha;
                        }else{
                            $data['refund_combine_wxpay'] = $money;
                        }
                    }
                }else if($order['combine_alipay']>0){
                    //统计是否已退完
                    $cha = $order['combine_alipay'] - $order['refund_combine_alipay'];
                    if($cha>0){
                        //判断能支付宝能退多少
                        $cha2 = $money-$cha;
                        if($cha2>=0){
                            $data['refund_combine_alipay'] = $cha;
                        }else{
                            $data['refund_combine_alipay'] = $money;
                        }
                    }
                }
                if($order['combine_money']>0){
                    //统计是否已退完
                    $cha2 = $order['combine_money'] - $order['refund_combine_money'];
                    if($cha2>0){
                        //计算组合余额应退
                        $data['refund_combine_money'] = $money-$data['refund_combine_wxpay']-$data['refund_combine_alipay'];
                    }
                }
                if($data['refund_combine_money']<=0 && $data['refund_combine_wxpay']<=0 && $data['refund_combine_alipay']<=0){
                    return ['status' => 0, 'msg' => '已退还全部'];
                }
            }
            return $data;
        }
    }

    public static function deal_refund_combine2($refund_money,$order,$payorder,$params,$type){
        if(getcustom('pay_money_combine')){
            //余额组合支付退款（仅商城）refund_combine 1 走shop_refund_order 退款； 2 走shop_order 直接退款
            if($params && $params['refund_combine'] && $params['refund_order'] && $payorder['type'] == 'shop'){
                $refund_order = $params['refund_order'];
                //判断是否支付了微信或支付宝部分，且有微信或支付宝退款，且没有退款过
                $refund_money = 0;
                if($order['combine_'.$type]>0){
                    $order['totalprice'] = $order['combine_'.$type];
                    //走shop_refund_order 退款
                    if($params['refund_combine'] == 1){
                        if($refund_order['refund_combine_'.$type]>0 && $refund_order['refundcombine'] == 0 ){
                            $refund_money  = $refund_order['refund_combine_'.$type];
                        }
                    //走shop_order 退款
                    }else{
                        if($order['combine_'.$type] > $order['refund_combine_'.$type]){
                            $refund_money = $order['combine_'.$type] - $order['refund_combine_'.$type];
                        }
                    }
                }
            }
            return $refund_money;
        }
    }

    public static function deal_refund_combine3($refund_money,$order,$payorder,$params,$type,$mid,$rs,$paytype,$remark='',$reason=''){
        if(getcustom('pay_money_combine')){
            //余额组合支付退款（仅商城）
            //refund_combine 1 走shop_refund_order 退款;2 走shop_order 退款 3 走shop_order 退款并清空组合支付数据(需未支付)
            if($params && $params['refund_combine'] && $params['refund_order'] && $payorder['type'] == 'shop'){
                $refund_order = $params['refund_order'];
                if(!$rs || $rs['status'] !=1){
                    $msg = $rs && $rs['msg']?$rs['msg']:'退款错误';
                    return ['status'=>0,'msg'=>$msg];
                }
                if($refund_money>0){
                    if($params['refund_combine'] == 1){
                        //更新组合退款状态
                        Db::name('shop_refund_order')->where('id',$refund_order['id'])->update(['refundcombine'=>$paytype]);
                    }
                    //增加组合支付微信或支付宝退款部分
                    Db::name('shop_order')->where('id',$order['id'])->inc('refund_combine_'.$type,$refund_money)->update();
                }
                //判断是否支付了余额部分，且有余额退款，且没有退款完成
                if($order['combine_money']>0){
                    $refund_money2 = 0;
                    //走shop_refund_order 退款
                    if($params['refund_combine'] == 1){
                        if($refund_order['refund_combine_money']>0 && $refund_order['refundcombine'] !=1){
                            $refund_money2 = $refund_order['refund_combine_money'];
                        }
                    //走shop_order 退款
                    }else{
                        if($order['combine_money'] > $order['refund_combine_money']){
                            $refund_money = $order['combine_money'] - $order['refund_combine_money'];
                        }
                    }
                    if($refund_money2>0){
                        //退余额
                        $rs2 = \app\commons\Member::addmoney($order['aid'],$mid,$refund_money2,$remark.' '.$reason);
                        if($rs2['status'] != 1){
                            return ['status'=>0,'msg'=>$rs2['msg']?$rs2['msg']:t('余额').'退款出错'];
                        }else{
                            if($params['refund_combine'] == 1){
                                //更新组合退款状态
                                Db::name('shop_refund_order')->where('id',$refund_order['id'])->update(['refundcombine'=>1]);
                            }

                            //清空组合支付数据(需未支付)
                            if($params['refund_combine'] == 3 && $order['status'] == 0){
                                Db::name('shop_order')->where('id',$order['id'])->update(['combine_money'=>0,'combine_wxpay'=>0,'combine_alipay'=>0,'refund_combine_money'=>0,'refund_combine_wxpay'=>0,'refund_combine_alipay'=>0]);
                            }else{
                                //增加组合支付余额退款部分
                                Db::name('shop_order')->where('id',$order['id'])->inc('refund_combine_money',$refund_money2)->update();
                            }
                        }
                    }
                } 
            }
            return ['status'=>1,'msg'=>''];
        }
    }

    public static function deal_cashbackspeed($member,$real_totalprice,$cashback){
        if(getcustom('yx_cashback_time_tjspeed')){
            //购物返现 直推、间推上级加速
            if($member['pid']>0 && $real_totalprice>0){
                //先执行今天发放奖励
                self::deal_autocashback($member['aid']);
                //上级、上上级加速
                $parent = Db::name('member')->where('id',$member['pid'])->where('aid',$member['aid'])->field('id,aid,pid')->find();
                if($parent){
                    if($cashback['parent_speed']>0){
                        self::deal_cashbackspeed2($real_totalprice,$cashback['parent_speed'],$parent,'直推下级订单完成加速');
                    }
                    if($parent['pid']>0 && $cashback['parent2_speed']>0){
                        $parent2 = Db::name('member')->where('id',$parent['pid'])->where('aid',$parent['aid'])->field('id,aid,pid')->find();
                        if($parent2){
                            self::deal_cashbackspeed2($real_totalprice,$cashback['parent2_speed'],$parent2,'间推订单完成级加速');
                        }
                    }
                }
            }
        }
    }
    public static function deal_cashbackteamspeed($member,$allreal_totalprice){
        if(getcustom('yx_cashback_time_teamspeed')){
            //购物返现 团队业绩达标上级加速
            if($member['pid']>0 && $allreal_totalprice>0){
                //先执行今天发放奖励
                self::deal_autocashback($member['aid']);
                //上级团队业绩达标加速
                self::deal_cashbackspeed3($member,$allreal_totalprice);
            }
        }
    }
    private static function deal_cashbackspeed2($real_totalprice,$parent_speed,$parent,$remark=''){
        if(getcustom('yx_cashback_time_tjspeed')){
            $parent_speed = $real_totalprice*$parent_speed/100;
            $parent_speed = round($parent_speed,2);
            if($parent_speed>0){
                $nowtime = strtotime(date("Y-m-d"));
                //查询上级未返完的记录
                $where = [];
                $where[] = ['mid','=',$parent['id']];
                $where[] = ['back_type','>=',1];
                $where[] = ['back_type','<=',3];
                $where[] = ['money_sendtime|commission_sendtime|score_sendtime','>=',$nowtime];
                $where[] = ['moneystatus|commissionstatus|scorestatus','=',1];
                if(getcustom('cashback_yongjin')){
                    $where[] = ['cashback_yongjin','<>',2];
                }
                $where[] = ['aid','=',$parent['aid']];
                $cashbacklist = Db::name('shop_order_goods_cashback')
                    ->where($where)
                    ->order('id asc')
                    ->select()
                    ->toArray();
                //如果存在则加速返现活动，否则直接加入上级的待加速数额中
                if($cashbacklist){
                    foreach($cashbacklist as $mv){
                        if($parent_speed<=0){
                            break;
                        }
                        if(getcustom('yx_cashback_stage')){
                            //阶梯性返现不走加速
                            if(!empty($mv['return_type']) && $mv['return_type'] == 2){
                                continue;
                            }
                        }

                        //走统一的处理方法
                        $res = self::deal_cashbacklist($mv,['isspeed'=>1,'parent_speed'=>$parent_speed,'remark'=>$remark]);
                        if($res && $res['status'] == 1){
                            $parent_speed = $res['param']['parent_speed'];
                        }else{
                            continue;
                        }

                    }
                    unset($mv);
                    //有剩余则累计
                    if($parent_speed>0){
                        Db::name('member')->where('id',$parent['id'])->inc('cashback_speed_num',$parent_speed)->update();
                    }
                }else{
                    Db::name('member')->where('id',$parent['id'])->inc('cashback_speed_num',$parent_speed)->update();
                }
            }
        }
    }
    private static function deal_cashbackspeed3($member,$real_totalprice,$cashback=''){
        if(getcustom('yx_cashback_time_teamspeed')){
            $parents = Db::name('member')->where('id','in',$member['path'])->field('id,aid')->select()->toArray();
            if($parents){
                foreach($parents as $parent){

                    $nowtime = strtotime(date("Y-m-d"));
                    //查询上级未返完的记录
                    $where = [];
                    $where[] = ['mid','=',$parent['id']];
                    $where[] = ['back_type','>=',1];
                    $where[] = ['back_type','<=',3];
                    $where[] = ['money_sendtime|commission_sendtime|score_sendtime','>=',$nowtime];
                    $where[] = ['moneystatus|commissionstatus|scorestatus','=',1];
                    if(getcustom('cashback_yongjin')){
                        $where[] = ['cashback_yongjin','<>',2];
                    }
                    $where[] = ['aid','=',$parent['aid']];
                    $cashbacklist = Db::name('shop_order_goods_cashback')
                        ->where($where)
                        ->order('id asc')
                        ->select()
                        ->toArray();

                    if($cashbacklist){
                        $cashback_speed_num = 0;//加速余额
                        foreach($cashbacklist as $mv){
                            if(getcustom('yx_cashback_stage')){
                                //阶梯性返现不走加速
                                if(!empty($mv['return_type']) && $mv['return_type'] == 2){
                                    continue;
                                }
                            }

                            //用户加速余额加速
                            if($cashback_speed_num>0){
                                $res = self::deal_cashbacklist($mv,['isspeed'=>3,'cashback_speed'=>$cashback_speed_num,'remark'=>'团队业绩达标加速余额释放']);
                                if($res && $res['status'] == 1){
                                    if($res['param']['cashback_speed']<=0){
                                        $cashback_speed_num = 0;
                                        //Db::name('member')->where('id',$parent['id'])->dec('cashback_speed_num',$cashback_speed_num)->update();
                                    }else{
                                        $cashback_speed_num = $res['param']['cashback_speed'];
                                    }
                                }
                            }

                            if($mv['back_type'] == 1){
                                //总返回数值
                                $back_price = $mv['allmoney'];
                            }else if($mv['back_type'] == 2){
                                //总返回数值
                                $back_price = $mv['allcommission'];
                            }else if($mv['back_type'] == 3){
                                //总返回数值
                                $back_price = $mv['allscore'];
                            }

                            //团队业绩达标加速
                            $teamspeeddata = Db::name('cashback')->where('id',$mv['cashback_id'])->value('teamspeeddata');
                            if($teamspeeddata && !empty($teamspeeddata)){
                                $teamspeeddata = json_decode($teamspeeddata,true);
                                //查询下级团队
                                $downmids = \app\commons\Member::getteammids($mv['aid'],$mv['mid']);
                                if($downmids && $teamspeeddata){
                                    $teamyeji = Db::name('shop_order_goods')->where('mid','in',$downmids)->where('status',3)->where('aid',$mv['aid'])->sum('real_totalprice');
                                    $teamyeji += $real_totalprice;

                                    //如果业绩大于已发放业绩
                                    if($teamyeji > $mv['teamspeed_yeji']){
                                        $teamspeed_money = 0;
                                        $team_speed      = 0;
                                        foreach($teamspeeddata as $tv){
                                            //如果团队业绩大于当前达标业绩，且当前达标业绩大于之前当前达标业绩 且当前达标业绩大于已发放达标业绩
                                            if($teamyeji>=$tv['money'] && $tv['money']>$teamspeed_money && $tv['money']> $mv['teamspeed_money']){
                                                $teamspeed_money = $tv['money'];
                                                $team_speed      = $back_price*$tv['speed']/100;
                                                $team_speed      = round($team_speed,2);
                                            }
                                        }
                                        unset($tv);
                                        if($team_speed>0){
                                            $res = self::deal_cashbacklist($mv,['isspeed'=>2,'team_speed'=>$team_speed,'teamspeed'=>$team_speed,'teamspeed_yeji'=>$teamyeji,'teamspeed_money'=>$teamspeed_money,'remark'=>'团队业绩达标加速']);
                                            if($res && $res['status'] == 1){
                                                if($res['param']['team_speed']>0){
                                                    $cashback_speed_num += $res['param']['team_speed'];
                                                    //Db::name('member')->where('id',$parent['id'])->inc('cashback_speed_num',$res['param']['team_speed'])->update();
                                                }
                                            }else{
                                                continue;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        unset($mv);
                        if($cashback_speed_num>0){
                            Db::name('member')->where('id',$parent['id'])->inc('cashback_speed_num',$cashback_speed_num)->update();
                        }
                    }
                }
            }
        }
    }
    private static function deal_cashbacklist($mv,$param=[]){
        if(getcustom('yx_cashback_time') || getcustom('yx_cashback_stage')){
            $isspeed       = 0;//是否加速释放(仅自定义天数的支持加速) 0:不加速 1：推荐加速 2：团队加速 3:余额加速 
            $nocheckstatus = false;//不需要验证状态
            $return_type = 1;//默认是 1 自定义返还 2、阶梯返还
            if(getcustom('yx_cashback_stage')){
                //计算此次阶梯返还发放的数值
                if(!empty($mv['return_type']) && $mv['return_type']==2){
                    $return_type = 2;
                }
            }
            if(getcustom('yx_cashback_time_tjspeed') || getcustom('yx_cashback_time_teamspeed')){
                if($param && $param['isspeed']){
                    if($param['isspeed']>0 && $return_type != 1){
                        return;//仅自定义天数的支持加速
                    }
                    $isspeed       = $param['isspeed'];
                    $nocheckstatus = $param['nocheckstatus']?true:false;
                }
            }
            if(!$nocheckstatus){
                if($mv['canshtype'] == "shop"){
                    $count = Db::name('shop_order_goods')
                        ->alias('sog')
                        ->join('shop_order so','so.id = sog.orderid')
                        ->where('sog.id',$mv['sog_id'])
                        ->where('sog.aid',$mv['aid'])
                        ->where('sog.status',3)
                        ->where('so.status',3)
                        ->where('so.delete',0)
                        ->count();
                    if(!$count){
                        return ['status'=>0,'param'=>$param];
                    }
                }else if($mv['canshtype'] == "collage"){
                    $count = Db::name('collage_order')
                        ->where('id',$mv['sog_id'])
                        ->where('aid',$mv['aid'])
                        ->where('status',3)
                        ->where('delete',0)
                        ->count();
                    if(!$count){
                        return ['status'=>0,'param'=>$param];
                    }
                }else{
                    return ['status'=>0,'param'=>$param];
                }
            }
            if(getcustom('yx_cashback_time_tjspeed') || getcustom('yx_cashback_time_teamspeed')){
                //如果是团队业绩加速，先更新业绩，防止多次业绩增速的产生
                if($isspeed == 2){
                    $teamdata = [];
                    $teamdata['teamspeed']       = $param['teamspeed'];
                    $teamdata['teamspeed_yeji']  = $param['teamspeed_yeji'];
                    $teamdata['teamspeed_money'] = $param['teamspeed_money'];
                    $upteam = Db::name('shop_order_goods_cashback')->where('id',$mv['id'])->where('teamspeed_yeji','<',$param['teamspeed_yeji'])->where('teamspeed_money','<',$param['teamspeed_money'])->update($teamdata);
                    if(!$upteam){
                        return ['status'=>0,'param'=>$param];
                    }
                }
            }

            if($mv['back_type'] == 1){
                //总返回数值
                $allmoney = $mv['allmoney'];
                //已发放的数值
                $money    = $mv['money'];
                //平均每次发放的数值
                $moneyave = $mv['moneyave'];
                //返回天数
                $day      = $mv['moneyday'];
                //发放次数
                $sendnum  = $mv['money_sendnum'];
            }else if($mv['back_type'] == 2){
                //总返回数值
                $allmoney = $mv['allcommission'];
                //已发放的数值
                $money    = $mv['commission'];
                //平均每次发放的数值
                $moneyave = $mv['commissionave'];
                //返回天数
                $day      = $mv['commissionday'];
                //发放次数
                $sendnum  = $mv['commission_sendnum'];
            }else if($mv['back_type'] == 3){
                //总返回数值
                $allmoney = $mv['allscore'];
                //已发放的数值
                $money    = $mv['score'];
                //平均每次发放的数值
                $moneyave = $mv['scoreave'];
                //返回天数
                $day      = $mv['scoreday'];
                //发放次数
                $sendnum  = $mv['score_sendnum'];
            }
            $send_money = $moneyave;//发放数值

            if(getcustom('yx_cashback_stage')){
                //计算此次阶梯返还发放的数值
                if($return_type==2){
                    //阶梯数值
                    $stagedata  = json_decode($mv['stagedata'],true);
                    //无数值，则结束
                    if(!$stagedata){
                        //结束
                        $updata = [];
                        if($mv['back_type'] == 1){
                            $updata['moneystatus']         = 2;
                            $updata['money_sendtime']      = time();
                        }else if($mv['back_type'] == 2){
                            $updata['commissionstatus']    = 2;
                            $updata['commission_sendtime'] = time();
                        }else if($mv['back_type'] == 3){
                            $updata['scorestatus']         = 2;
                            $updata['score_sendtime']      = time();
                        }
                        $updata['updatetime'] = time();
                        $up = Db::name('shop_order_goods_cashback')->where('id',$mv['id'])->update($updata);
                        return;
                    }

                    //计算创建记录到现在的天数
                    $nowday    = strtotime(date('Y-m-d',time()));
                    $createday = strtotime(date('Y-m-d',$mv['createtime']));
                    $chaday    = intval(($nowday-$createday)/86400);
                    //超出返现天数，则结束
                    if($chaday>$day){
                        //结束
                        $updata = [];
                        if($mv['back_type'] == 1){
                            $updata['moneystatus']         = 2;
                            $updata['money_sendtime']      = time();
                        }else if($mv['back_type'] == 2){
                            $updata['commissionstatus']    = 2;
                            $updata['commission_sendtime'] = time();
                        }else if($mv['back_type'] == 3){
                            $updata['scorestatus']         = 2;
                            $updata['score_sendtime']      = time();
                        }
                        $updata['updatetime'] = time();
                        $up = Db::name('shop_order_goods_cashback')->where('id',$mv['id'])->update($updata);
                        return;
                    }

                    //获取阶梯返还比例
                    $stageratio = 0;
                    foreach($stagedata as $stage){
                        if($stage['stageday']<=$chaday && $chaday<=$stage['stageday2']){
                            $stageratio = $stage['stageratio'];
                        }
                    }

                    //返现类型 1、余额 2、佣金 3、积分 小数位数
                    $money_weishu = 2;$commission_weishu = 2;$score_weishu = 0;
                    if(getcustom('member_money_weishu')){
                        $money_weishu = Db::name('admin_set')->where('aid',$mv['aid'])->value('member_money_weishu');
                    }
                    if(getcustom('fenhong_money_weishu')){
                        $commission_weishu = Db::name('admin_set')->where('aid',$mv['aid'])->value('fenhong_money_weishu');
                    }
                    if(getcustom('score_weishu')){
                        $score_weishu = Db::name('admin_set')->where('aid',$mv['aid'])->value('score_weishu');
                    }

                    //计算发放值
                    $send_money = $allmoney*$stageratio*0.01;
                    if($mv['back_type'] ==1){
                        $send_money = dd_money_format($send_money,$money_weishu);
                    }else if($mv['back_type'] ==2){
                        $send_money = dd_money_format($send_money,$commission_weishu);
                    }else{
                        $send_money2  = dd_money_format($send_money,$score_weishu);
                        if($score_weishu == 0 && $send_money>0 && $send_money<=1){
                            $send_money = 1;
                        }else{
                            $send_money = $send_money2;
                        }
                    }
                }
            }

            $send_all = $money+$send_money;//总发放数值
            $updata = [];
            if($money>=$allmoney){
                if($mv['back_type'] == 1){
                    $updata['moneystatus']         = 2;
                    $updata['money_sendtime']      = time();
                }else if($mv['back_type'] == 2){
                    $updata['commissionstatus']    = 2;
                    $updata['commission_sendtime'] = time();
                }else if($mv['back_type'] == 3){
                    $updata['scorestatus']         = 2;
                    $updata['score_sendtime']      = time();
                }
                $send_money = 0;
            }else{
                $new_sendnum = $sendnum+1;//发放次数
                if(getcustom('yx_cashback_time_tjspeed') || getcustom('yx_cashback_time_teamspeed')){
                    //如果是加速
                    if($isspeed){
                        //1：推荐加速 2：团队加速 3、余额加速
                        if($isspeed == 1){
                            $send_money  = $param['parent_speed'];//发放数值
                            $send_all    = $money+$param['parent_speed'];//总发放数值
                        }else if($isspeed == 2){
                            $send_money  = $param['team_speed'];//发放数值
                            $send_all    = $money+$param['team_speed'];//总发放数值
                        }else{
                            $send_money  = $param['cashback_speed'];//发放数值
                            $send_all    = $money+$param['cashback_speed'];//总发放数值
                        }
                    }
                }
                if($send_all<$allmoney){
                    //如果是返还时间是自定义类型
                    if($return_type == 1){
                        //发放次数大于等于返回天数，则这次结束，返回剩余全部
                        if($new_sendnum >= $day){
                            $status     = 2;
                            $send_all   = $allmoney;
                            $send_money = $allmoney-$money;
                        }else{
                            $status  = 1;
                            if(getcustom('yx_cashback_time_tjspeed') || getcustom('yx_cashback_time_teamspeed')){
                                //如果是加速
                                if($isspeed){
                                    $speednum = $send_money/$moneyave;
                                    if($speednum<1){
                                        $speednum = 1;
                                    }else{
                                        $speednum = floor($speednum);
                                    }
                                    $new_sendnum = $sendnum+$speednum;//发放次数
                                }
                            }
                        }
                        if(getcustom('yx_cashback_time_tjspeed') || getcustom('yx_cashback_time_teamspeed')){
                            //如果是加速
                            if($isspeed){
                                if($isspeed == 1){
                                    $param['parent_speed']  = 0;
                                }else if($isspeed == 2){
                                    $param['team_speed']    = 0;
                                }else{
                                    $param['cashback_speed'] = 0;
                                }
                            }
                        }
                    }else if($return_type == 2){
                        //创建记录到现在的天数大于等于最大天数，则这次结束
                        if($chaday>=$day){
                            $status  = 2;
                        }else{
                            $status  = 1;
                        }
                    }
                }else if($send_all >= $allmoney ){
                    $status  = 2;
                    if($send_all > $allmoney){
                        $send_all   = $allmoney;
                        $send_money = $allmoney-$money;
                        if(getcustom('yx_cashback_time_tjspeed') || getcustom('yx_cashback_time_teamspeed')){
                            //如果是加速
                            if($isspeed){
                                if($isspeed == 1){
                                    $param['parent_speed'] -= $send_money;
                                }else if($isspeed == 2){
                                    $param['team_speed']   -= $send_money;
                                }else{
                                    $param['cashback_speed']   -= $send_money;
                                }
                            }
                        }
                    }else{
                        if(getcustom('yx_cashback_time_tjspeed') || getcustom('yx_cashback_time_teamspeed')){
                            //如果是加速
                            if($isspeed){
                                if($isspeed == 1){
                                    $param['parent_speed']  = 0;
                                }else if($isspeed == 2){
                                    $param['team_speed']    = 0;
                                }else{
                                    $param['cashback_speed']= 0;
                                }
                            }
                        }
                    }
                }

                if($mv['back_type'] == 1){
                    $updata['moneystatus']        = $status;
                    $updata['money']              = $send_all;
                    $updata['money_sendnum']      = $new_sendnum;
                }else if($mv['back_type'] == 2){
                    $updata['commissionstatus']   = $status;
                    $updata['commission']         = $send_all;
                    $updata['commission_sendnum'] = $new_sendnum;
                }else if($mv['back_type'] == 3){
                    $updata['scorestatus']        = $status;
                    $updata['score']              = $send_all;
                    $updata['score_sendnum']      = $new_sendnum;
                }
            }
            if($send_money && $send_money>0){
                if($mv['back_type'] == 1){
                    $updata['money_sendtime']      = time();
                }else if($mv['back_type'] == 2){
                    $updata['commission_sendtime'] = time();
                }else if($mv['back_type'] == 3){
                    $updata['score_sendtime']      = time();
                }
                $cashback_member_check = Db::name('cashback_member')->where('aid',$mv['aid'])->where('mid',$mv['mid'])->where(['cashback_id'=>$mv['cashback_id'],'pro_id'=>$mv['pro_id'],'type'=>$mv['canshtype']])->find();
                if(getcustom('cashback_max')){
                    if($mv['cashback_id'] >0 && $mv['pro_id'] >0){
                        if($cashback_member_check){
                            //追加金额
                            if($mv['back_type'] == 1){
                                $money_max = $cashback_member_check['cashback_money'];
                            }else if($mv['back_type'] == 2){
                                $money_max = $cashback_member_check['commission'];
                            }else if($mv['back_type'] == 3){
                                $money_max = $cashback_member_check['score'];
                            }
                            if($cashback_member_check['cashback_money_max'] > $money_max){
                                //最大可追加金额
                                $cashback_money_max = $cashback_member_check['cashback_money_max'] - $money_max;
                                if($cashback_money_max < $send_money){
                                    $send_money = $cashback_money_max;
                                }    
                                if($send_money <=0){
                                    return ;
                                }
                            }
                        }
                    }
                }

                if($mv['back_type'] == 1){
                    $remark = $isspeed?($mv['money_name'].$param['remark']):$mv['money_name'];
                    //发放返现
                    \app\commons\Member::addmoney($mv['aid'],$mv['mid'],$send_money,$remark);
                    //累计到参与人统计表
                    Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('cashback_money',$send_money)->update();
                }else if($mv['back_type'] == 2){
                    $remark = $isspeed?($mv['commission_name'].$param['remark']):$mv['commission_name'];
                    //发放返现
                    \app\commons\Member::addcommission($mv['aid'],$mv['mid'],$mv['mid'],$send_money,$remark);
                    //累计到参与人统计表
                    Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('commission',$send_money)->update();
                }else if($mv['back_type'] == 3){
                    $remark = $isspeed?($mv['score_name'].$param['remark']):$mv['score_name'];
                    //发放返现
                    \app\commons\Member::addscore($mv['aid'],$mv['mid'],$send_money,$remark);
                    //累计到参与人统计表
                    Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('score',$send_money)->update();
                }

                //写入发放日志
                $cashback = ['id'=>$mv['cashback_id'],'back_type'=>$mv['back_type']];
                $og       = ['proid'=>$mv['pro_id'],'ordertype'=>$mv['canshtype']];
                $res_cashback_do_log = self::cashbackMemerDoLog($mv['aid'],$mv['mid'],$cashback,$og,$send_money);
            }
            //更新
            $updata['updatetime'] = time();
            $up = Db::name('shop_order_goods_cashback')->where('id',$mv['id'])->update($updata);

            if(getcustom('cashback_yongjin')){
                //记录累计返现金额
                Db::name('member')->where('id',$mv['mid'])->inc('cashback_total',$send_money)->update();
            }
            return ['status'=>1,'param'=>$param];
        }
    }

    public static function deal_maidancashback($aid,$order)
    {
        if(getcustom('yx_cashback_maidan')){
            //购物返现 买单返现
            $cashbacklist = Db::name('cashback')
                ->where('aid',$aid)
                ->where('bid',0)
                ->where('fwtype',4)
                ->where('maidan_minpaymoney','<=',$order['paymoney'])
                ->where('starttime','<',$order['paytime'])
                ->where('endtime','>',$order['paytime'])
                ->order('sort desc')
                ->select()->toArray();
            //查询购买用户
            $member = Db::name('member')->where('id',$order['mid'])->find();
            if($member && $cashbacklist){
                //返现类型 1、余额 2、佣金 3、积分 小数位数
                $money_weishu = 2;$commission_weishu = 2;$score_weishu = 0;
                if(getcustom('member_money_weishu')){
                    $money_weishu = Db::name('admin_set')->where('aid',$aid)->value('member_money_weishu');
                }
                if(getcustom('fenhong_money_weishu')){
                    $commission_weishu = Db::name('admin_set')->where('aid',$aid)->value('fenhong_money_weishu');
                }
                if(getcustom('score_weishu')){
                    $score_weishu = Db::name('admin_set')->where('aid',$aid)->value('score_weishu');
                }
                foreach($cashbacklist as $v){

                    //判断仅平台 还是仅商户
                    if(($v['maidan_type'] == 1 && $order['bid'] != 0) || ($v['maidan_type'] == 2 && $order['bid'] == 0)){
                        continue;
                    }

                    //判断是否超出最高支付额度
                    if($v['maidan_maxpaymoney']>0 && $order['paymoney'] > $v['maidan_maxpaymoney']){
                        continue;
                    }

                    $gettj = explode(',',$v['gettj']);
                    if(!in_array('-1',$gettj) && !in_array($member['levelid'],$gettj)){ //不是所有人
                        continue;
                    }

                    $back_ratio = $v['back_ratio'];//返现利率
                    //如果返现利率大于0
                    if($back_ratio>0){
                        //计算返现
                        $back_price = $back_ratio*$order['paymoney']/100;

                        //返现类型 1、余额 2、佣金 3、积分
                        if($v['back_type'] == 1 ){
                            $back_price = dd_money_format($back_price,$money_weishu);
                        }else if($v['back_type']== 2){
                            $back_price = dd_money_format($back_price,$commission_weishu);
                        }else if($v['back_type'] == 3){
                            $back_price = dd_money_format($back_price,$score_weishu);
                        }

                        $return_type = 0;//发放类型 0：立即发放 1、自定义 2、阶梯
                        if(getcustom('yx_cashback_time') || getcustom('yx_cashback_stage')){
                            $return_type = $v['return_type'];
                        }

                        //构建商品信息
                        $og = [];
                        $og['aid']   = $order['aid'];
                        $og['bid']   = $order['bid'];
                        $og['mid']   = $order['mid'];
                        $og['id']    = $order['id'];
                        $og['proid'] = 0;
                        $og['ordertype']  = 'maidan';

                        //记录参与的会员
                        $cashback_member_check = Db::name('cashback_member')->where(['cashback_id'=>$v['id'],'pro_id'=>$og['proid'],'type'=>'maidan'])->where('mid',$order['mid'])->where('aid',$order['aid'])->find();
                        if(!$cashback_member_check){
                            $cashback_member = [];
                            $cashback_member['aid']          = $order['aid'];
                            $cashback_member['mid']          = $order['mid'];
                            $cashback_member['cashback_id']  = $v['id'];
                            $cashback_member['back_type']    = $v['back_type'];
                            $cashback_member['pro_id']       = 0;
                            $cashback_member['pro_num']      = 0;
                            $cashback_member['type']         = 'maidan';
                            $cashback_member['cashback_money_max'] = 0;
                            $cashback_member['create_time']        = time();
                            $insert = Db::name('cashback_member')->insert($cashback_member);
                            $cashback_member_check = Db::name('cashback_member')->where(['cashback_id'=>$v['id'],'pro_id'=>$og['proid'],'type'=>'maidan'])->where('mid',$order['mid'])->where('aid',$order['aid'])->find();
                        }

                        if(!$return_type){
                            if($back_price>0){
                                if($v['back_type'] == 1 ){
                                    \app\commons\Member::addmoney($aid,$order['mid'],$back_price,$v['name']);
                                    //累计到参与人统计表
                                    Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('cashback_money',$back_price)->update();
                                }
                                if($v['back_type'] == 2){
                                    \app\commons\Member::addcommission($aid,$order['mid'],$order['mid'],$back_price,$v['name']);
                                    //累计到参与人统计表
                                    Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('commission',$back_price)->update();
                                }
                                if($v['back_type'] == 3){
                                    \app\commons\Member::addscore($aid,$order['mid'],$back_price,$v['name']);
                                    //累计到参与人统计表
                                    Db::name('cashback_member')->where('id',$cashback_member_check['id'])->inc('score',$back_price)->update();
                                }
                                if(getcustom('yx_cashback_time')){
                                    //直接发放
                                    \app\customs\OrderCustom::deal_first_cashback($aid,$order['mid'],$back_price,$og,$v,0,'maidan');
                                }
                                //写入发放日志
                                \app\customs\OrderCustom::cashbackMemerDoLog($order['aid'],$order['mid'],$v,$og,$back_price);
                            }
                        }else{
                            if($back_price>0){
                                if(getcustom('yx_cashback_time') || getcustom('yx_cashback_stage')){
                                    //处理自定义第一次发放
                                    \app\customs\OrderCustom::deal_first_cashback($aid,$order['mid'],$back_price,$og,$v,$return_type,'maidan');
                                }
                            }
                        }
                    }

                }
            }
        }
    }
}