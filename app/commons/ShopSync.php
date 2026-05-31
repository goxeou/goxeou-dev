<?php

namespace app\commons;
use think\facade\Db;

class ShopSync
{
    const MAIN_STORE_BID = 20;

    /**
     * 总店新增商品 → 同步到所有直营店
     * @param int $proid 总店商品ID
     * @return bool
     */
    public static function syncNewProductToStores($proid)
    {
        // 查询源商品
        $product = Db::name('shop_product')->where('id', $proid)->find();
        if (!$product) {
            return false;
        }

        // 只有总店商品才同步
        if ($product['bid'] != self::MAIN_STORE_BID) {
            return false;
        }

        // 查找所有启用的直营店
        $stores = Db::name('business')
            ->where('type', 1)
            ->where('head_bid', self::MAIN_STORE_BID)
            ->where('status', 1)
            ->field('id,price_adjust_percent')
            ->select();

        if ($stores->isEmpty()) {
            return false;
        }

        $now = time();

        foreach ($stores as $store) {
            // 跳过已同步的
            $exists = Db::name('shop_product')
                ->where('source_pid', $proid)
                ->where('bid', $store['id'])
                ->find();
            if ($exists) {
                continue;
            }

            // 计算初始售价
            $priceAdjustPercent = floatval($store['price_adjust_percent'] ?: 0);
            $basePrice = floatval($product['sell_price'] ?: 0);
            $calculatedPrice = round($basePrice * (1 + $priceAdjustPercent / 100), 2);
            $minPrice = floatval($product['min_price'] ?: 0);
            $finalPrice = max($calculatedPrice, $minPrice);

            // 构建插入数据
            $data = [
                'aid'           => $product['aid'],
                'bid'           => $store['id'],
                'cid'           => $product['cid'],
                'cid2'          => $product['cid2'],
                'gid'           => $product['gid'],
                'name'          => $product['name'],
                'procode'       => $product['procode'],
                'barcode'       => $product['barcode'],
                'fwid'          => $product['fwid'],
                'fuwupoint'     => $product['fuwupoint'],
                'sellpoint'     => $product['sellpoint'],
                'pic'           => $product['pic'],
                'pics'          => $product['pics'],
                'diypics'       => $product['diypics'],
                'detail'        => $product['detail'],
                'market_price'  => $product['market_price'],
                'sell_price'    => $finalPrice,
                'cost_price'    => $product['cost_price'],
                'price_type'    => $product['price_type'],
                'givescore'     => $product['givescore'],
                'weight'        => $product['weight'],
                'sort'          => $product['sort'],
                'status'        => $product['status'],
                'stock'         => $product['stock'],
                'min_price'     => $minPrice,
                'override_price'=> null,
                'sync_from_bid' => self::MAIN_STORE_BID,
                'source_pid'    => $proid,
                'createtime'    => $now,
                'guigedata'     => $product['guigedata'],
                'commissionset' => $product['commissionset'],
                'commissiondata1' => $product['commissiondata1'],
                'commissiondata2' => $product['commissiondata2'],
                'commissiondata3' => $product['commissiondata3'],
                'commissiondata4' => $product['commissiondata4'],
                'commission1'   => $product['commission1'],
                'commission2'   => $product['commission2'],
                'commission3'   => $product['commission3'],
                'commissionset4' => $product['commissionset4'],
                'fenhongset'    => $product['fenhongset'],
                'gdfenhongset'  => $product['gdfenhongset'],
                'gdfenhongdata1' => $product['gdfenhongdata1'],
                'gdfenhongdata2' => $product['gdfenhongdata2'],
                'teamfenhongset' => $product['teamfenhongset'],
                'teamfenhongdata1' => $product['teamfenhongdata1'],
                'teamfenhongdata2' => $product['teamfenhongdata2'],
                'areafenhongset' => $product['areafenhongset'],
                'areafenhongdata1' => $product['areafenhongdata1'],
                'areafenhongdata2' => $product['areafenhongdata2'],
                'commissionpingjiset' => $product['commissionpingjiset'],
                'commissionpingjidata1' => $product['commissionpingjidata1'],
                'commissionpingjidata2' => $product['commissionpingjidata2'],
                'freighttype'   => $product['freighttype'],
                'freightdata'   => $product['freightdata'],
                'freightcontent'=> $product['freightcontent'],
                'fastbuy'       => $product['fastbuy'],
                'lvprice'       => $product['lvprice'],
                'lvprice_data'  => $product['lvprice_data'],
                'isfuwu'        => $product['isfuwu'],
                'fuwuday'       => $product['fuwuday'],
                'video'         => $product['video'],
                'video_duration'=> $product['video_duration'],
                'bcid'          => $product['bcid'],
                'perlimit'      => $product['perlimit'],
                'perlimitdan'   => $product['perlimitdan'],
                'limit_start'   => $product['limit_start'],
                'showtj'        => $product['showtj'],
                'gettj'         => $product['gettj'],
                'gettjurl'      => $product['gettjurl'],
                'gettjtip'      => $product['gettjtip'],
                'scoredkmaxset' => $product['scoredkmaxset'],
                'scoredkmaxval' => $product['scoredkmaxval'],
                'ischecked'     => $product['ischecked'],
                'check_reason'  => $product['check_reason'],
                'start_hours'   => $product['start_hours'],
                'end_hours'     => $product['end_hours'],
                'start_time'    => $product['start_time'],
                'end_time'      => $product['end_time'],
                'show_recommend'=> $product['show_recommend'],
                'recommend_productids' => $product['recommend_productids'],
                'product_type'  => $product['product_type'],
                'contact_require' => $product['contact_require'],
                'print_name'    => $product['print_name'],
                'no_discount'   => $product['no_discount'],
            ];

            Db::name('shop_product')->insert($data);
        }

        return true;
    }

    /**
     * 总店编辑商品 → 更新直营店同步副本
     * @param int $proid 总店商品ID
     * @return bool
     */
    public static function syncProductUpdate($proid)
    {
        // 查询源商品
        $product = Db::name('shop_product')->where('id', $proid)->find();
        if (!$product) {
            return false;
        }

        // 只有总店商品才同步
        if ($product['bid'] != self::MAIN_STORE_BID) {
            return false;
        }

        // 查找所有直营店（需要价格调整百分比来计算售价）
        $stores = Db::name('business')
            ->where('type', 1)
            ->where('head_bid', self::MAIN_STORE_BID)
            ->where('status', 1)
            ->field('id,price_adjust_percent')
            ->select();

        if ($stores->isEmpty()) {
            return false;
        }

        $basePrice = floatval($product['sell_price'] ?: 0);
        $minPrice = floatval($product['min_price'] ?: 0);

        // 构建更新数据（不包含 override_price 和 stock）
        $updateData = [
            'name'          => $product['name'],
            'cid'           => $product['cid'],
            'cid2'          => $product['cid2'],
            'gid'           => $product['gid'],
            'procode'       => $product['procode'],
            'barcode'       => $product['barcode'],
            'fwid'          => $product['fwid'],
            'fuwupoint'     => $product['fuwupoint'],
            'sellpoint'     => $product['sellpoint'],
            'pic'           => $product['pic'],
            'pics'          => $product['pics'],
            'diypics'       => $product['diypics'],
            'detail'        => $product['detail'],
            'market_price'  => $product['market_price'],
            'cost_price'    => $product['cost_price'],
            'weight'        => $product['weight'],
            'sort'          => $product['sort'],
            'status'        => $product['status'],
            'min_price'     => $minPrice,
            'guigedata'     => $product['guigedata'],
            'commissionset' => $product['commissionset'],
            'commissiondata1' => $product['commissiondata1'],
            'commissiondata2' => $product['commissiondata2'],
            'commissiondata3' => $product['commissiondata3'],
            'commissiondata4' => $product['commissiondata4'],
            'commission1'   => $product['commission1'],
            'commission2'   => $product['commission2'],
            'commission3'   => $product['commission3'],
            'commissionset4' => $product['commissionset4'],
            'fenhongset'    => $product['fenhongset'],
            'gdfenhongset'  => $product['gdfenhongset'],
            'gdfenhongdata1' => $product['gdfenhongdata1'],
            'gdfenhongdata2' => $product['gdfenhongdata2'],
            'teamfenhongset' => $product['teamfenhongset'],
            'teamfenhongdata1' => $product['teamfenhongdata1'],
            'teamfenhongdata2' => $product['teamfenhongdata2'],
            'areafenhongset' => $product['areafenhongset'],
            'areafenhongdata1' => $product['areafenhongdata1'],
            'areafenhongdata2' => $product['areafenhongdata2'],
            'commissionpingjiset' => $product['commissionpingjiset'],
            'commissionpingjidata1' => $product['commissionpingjidata1'],
            'commissionpingjidata2' => $product['commissionpingjidata2'],
            'freighttype'   => $product['freighttype'],
            'freightdata'   => $product['freightdata'],
            'freightcontent'=> $product['freightcontent'],
            'fastbuy'       => $product['fastbuy'],
            'lvprice'       => $product['lvprice'],
            'lvprice_data'  => $product['lvprice_data'],
            'isfuwu'        => $product['isfuwu'],
            'fuwuday'       => $product['fuwuday'],
            'video'         => $product['video'],
            'video_duration'=> $product['video_duration'],
            'bcid'          => $product['bcid'],
            'perlimit'      => $product['perlimit'],
            'perlimitdan'   => $product['perlimitdan'],
            'limit_start'   => $product['limit_start'],
            'showtj'        => $product['showtj'],
            'gettj'         => $product['gettj'],
            'gettjurl'      => $product['gettjurl'],
            'gettjtip'      => $product['gettjtip'],
            'scoredkmaxset' => $product['scoredkmaxset'],
            'scoredkmaxval' => $product['scoredkmaxval'],
            'ischecked'     => $product['ischecked'],
            'check_reason'  => $product['check_reason'],
            'start_hours'   => $product['start_hours'],
            'end_hours'     => $product['end_hours'],
            'start_time'    => $product['start_time'],
            'end_time'      => $product['end_time'],
            'show_recommend'=> $product['show_recommend'],
            'recommend_productids' => $product['recommend_productids'],
            'product_type'  => $product['product_type'],
            'contact_require' => $product['contact_require'],
            'print_name'    => $product['print_name'],
            'no_discount'   => $product['no_discount'],
        ];

        foreach ($stores as $store) {
            // 计算调整后的售价
            $priceAdjustPercent = floatval($store['price_adjust_percent'] ?: 0);
            $calculatedPrice = round($basePrice * (1 + $priceAdjustPercent / 100), 2);
            $finalPrice = max($calculatedPrice, $minPrice);

            $updateData['sell_price'] = $finalPrice;

            // 不覆盖 override_price（直营店手动修改的价格）和 stock
            Db::name('shop_product')
                ->where('sync_from_bid', self::MAIN_STORE_BID)
                ->where('source_pid', $proid)
                ->where('bid', $store['id'])
                ->update($updateData);
        }

        return true;
    }

    /**
     * 总店删除商品 → 删除直营店同步副本及相关规格
     * @param int $proid 总店商品ID
     * @return bool
     */
    public static function syncProductDelete($proid)
    {
        // 查找所有已同步的商品ID
        $syncedIds = Db::name('shop_product')
            ->where('source_pid', $proid)
            ->where('sync_from_bid', self::MAIN_STORE_BID)
            ->column('id');

        if (!empty($syncedIds)) {
            // 删除关联的规格（ddwx_shop_guige）
            Db::name('shop_guige')
                ->whereIn('proid', $syncedIds)
                ->delete();

            // 删除同步商品
            Db::name('shop_product')
                ->where('source_pid', $proid)
                ->where('sync_from_bid', self::MAIN_STORE_BID)
                ->delete();
        }

        return true;
    }

    /**
     * 获取直营店商品的显示价格
     * @param array $product 商品数据（含 override_price, min_price, sell_price, sync_from_bid）
     * @param int   $storeBid 直营店bid
     * @return float 最终显示价格
     */
    public static function getDisplayPrice($product, $storeBid)
    {
        // 如果直营店手动设置了覆盖价，直接使用
        if (isset($product['override_price']) && $product['override_price'] !== null) {
            $price = floatval($product['override_price']);
        } else {
            // 查询直营店调价百分比
            $store = Db::name('business')
                ->where('id', $storeBid)
                ->field('price_adjust_percent')
                ->find();

            $priceAdjustPercent = floatval($store['price_adjust_percent'] ?: 0);
            $basePrice = floatval($product['sell_price'] ?: 0);
            $price = round($basePrice * (1 + $priceAdjustPercent / 100), 2);
        }

        // 最低保护价保护
        $minPrice = floatval($product['min_price'] ?: 0);
        return max($price, $minPrice);
    }
}
