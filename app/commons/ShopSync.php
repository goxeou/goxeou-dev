<?php

namespace app\commons;
use think\facade\Db;

class ShopSync
{
    const MAIN_STORE_BID = 20;

    /**
     * 总店新增商品 → 直营店新增映射记录
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
            ->field('id')
            ->select();

        if ($stores->isEmpty()) {
            return false;
        }

        $now = time();

        foreach ($stores as $store) {
            // 如果已存在映射则跳过
            $exists = Db::name('shop_product_store')
                ->where('proid', $proid)
                ->where('bid', $store['id'])
                ->find();
            if ($exists) {
                continue;
            }

            // INSERT INTO shop_product_store 映射记录
            $data = [
                'aid'          => $product['aid'],
                'proid'        => $proid,
                'bid'          => $store['id'],
                'store_status' => $product['status'],
                'stock'        => $product['stock'],
                'sort'         => $product['sort'],
                'createtime'   => $now,
            ];

            Db::name('shop_product_store')->insert($data);
        }

        return true;
    }

    /**
     * 总店编辑商品 → 无需同步（引用模式天然可见）
     * @param int $proid 总店商品ID
     * @return bool
     */
    public static function syncProductUpdate($proid)
    {
        // 引用模式下，直营店直接引用总店商品，编辑无需同步
        return true;
    }

    /**
     * 总店删除商品 → 直营店删除映射记录
     * @param int $proid 总店商品ID
     * @return bool
     */
    public static function syncProductDelete($proid)
    {
        Db::name('shop_product_store')
            ->where('proid', $proid)
            ->delete();

        return true;
    }

    /**
     * 同步总店商品分类到所有直营店
     * 将总店（MAIN_STORE_BID）的 shop_category2 分类复制到各直营店的 shop_category2
     */
    public static function syncCategoriesToStores()
    {
        // 获取总店所有分类（按父级排序，先父后子）
        $hqCategories = Db::name('shop_category2')
            ->where('aid', self::MAIN_STORE_BID)  // aid=20 管理后台总店
            ->where('bid', self::MAIN_STORE_BID)
            ->order('pid asc, sort desc, id asc')
            ->select()
            ->toArray();

        if (empty($hqCategories)) {
            return ['status' => 0, 'msg' => '总店暂无分类数据'];
        }

        // 查找所有启用的直营店
        $stores = Db::name('business')
            ->where('type', 1)
            ->where('head_bid', self::MAIN_STORE_BID)
            ->where('status', 1)
            ->field('id')
            ->select()
            ->toArray();

        if (empty($stores)) {
            return ['status' => 0, 'msg' => '暂无启用中的直营店'];
        }

        $totalSynced = 0;
        $now = time();

        foreach ($stores as $store) {
            $storeBid = $store['id'];

            // 获取该直营店已有的分类 fromid 列表
            $existingFromIds = Db::name('shop_category2')
                ->where('aid', self::MAIN_STORE_BID)
                ->where('bid', $storeBid)
                ->where('fromid', '>', 0)
                ->column('id', 'fromid');

            // old_id → new_id 映射（用于处理子分类 pid）
            $idMap = [];

            // 第一遍：处理根分类 (pid=0)
            $rootCategories = array_filter($hqCategories, function($c) {
                return $c['pid'] == 0;
            });
            foreach ($rootCategories as $cat) {
                $fromId = $cat['id'];
                if (isset($existingFromIds[$fromId])) {
                    // 已存在，更新
                    $newId = $existingFromIds[$fromId];
                    Db::name('shop_category2')
                        ->where('id', $newId)
                        ->update([
                            'name' => $cat['name'],
                            'pic' => $cat['pic'],
                            'status' => $cat['status'],
                            'sort' => $cat['sort'],
                        ]);
                    $idMap[$fromId] = $newId;
                } else {
                    // 新增
                    $newId = Db::name('shop_category2')->insertGetId([
                        'aid' => self::MAIN_STORE_BID,
                        'bid' => $storeBid,
                        'pid' => 0,
                        'name' => $cat['name'],
                        'pic' => $cat['pic'],
                        'status' => $cat['status'],
                        'sort' => $cat['sort'],
                        'fromid' => $fromId,
                        'createtime' => $now,
                    ]);
                    $idMap[$fromId] = $newId;
                    $totalSynced++;
                }
            }

            // 第二遍：处理子分类 (pid>0)
            $childCategories = array_filter($hqCategories, function($c) {
                return $c['pid'] > 0;
            });
            foreach ($childCategories as $cat) {
                $fromId = $cat['id'];
                // 父分类必须已映射，否则跳过
                if (!isset($idMap[$cat['pid']])) {
                    continue;
                }
                $newPid = $idMap[$cat['pid']];

                if (isset($existingFromIds[$fromId])) {
                    // 已存在，更新
                    $newId = $existingFromIds[$fromId];
                    Db::name('shop_category2')
                        ->where('id', $newId)
                        ->update([
                            'pid' => $newPid,
                            'name' => $cat['name'],
                            'pic' => $cat['pic'],
                            'status' => $cat['status'],
                            'sort' => $cat['sort'],
                        ]);
                    $idMap[$fromId] = $newId;
                } else {
                    // 新增
                    $newId = Db::name('shop_category2')->insertGetId([
                        'aid' => self::MAIN_STORE_BID,
                        'bid' => $storeBid,
                        'pid' => $newPid,
                        'name' => $cat['name'],
                        'pic' => $cat['pic'],
                        'status' => $cat['status'],
                        'sort' => $cat['sort'],
                        'fromid' => $fromId,
                        'createtime' => $now,
                    ]);
                    $idMap[$fromId] = $newId;
                    $totalSynced++;
                }
            }
        }

        return ['status' => 1, 'msg' => '同步完成，共处理 ' . $totalSynced . ' 条分类记录'];
    }

    /**
     * 获取直营店显示价格
     *
     * 用法1（新签名）：ShopSync::getDisplayPrice($storeBid, $proid)
     *   直接从 DB 查询商品和映射记录计算价格
     *
     * 用法2（旧签名兼容）：ShopSync::getDisplayPrice($product, $storeBid)
     *   $product 为商品数组（需含 sell_price, min_price 等字段）
     *
     * @param int|array $storeBidOrProduct 直营店ID 或 商品数据数组（旧签名兼容）
     * @param int|null  $proidOrStoreBid   商品ID（新签名）或 直营店ID（旧签名）
     * @return float
     */
    public static function getDisplayPrice($storeBidOrProduct, $proidOrStoreBid = null)
    {
        // 检测调用方式：如果第一个参数是数组，视为旧签名 (product, storeBid)
        if (is_array($storeBidOrProduct)) {
            $product  = $storeBidOrProduct;
            $storeBid = $proidOrStoreBid;
            return self::calcDisplayPrice($product, $storeBid);
        }

        // 新签名: getDisplayPrice($storeBid, $proid)
        $storeBid = $storeBidOrProduct;
        $proid    = $proidOrStoreBid;

        // 从总店商品表取商品数据
        $product = Db::name('shop_product')->where('id', $proid)->find();
        if (!$product) {
            return 0.00;
        }

        // 从映射表取 override_price
        $storeProduct = Db::name('shop_product_store')
            ->where('proid', $proid)
            ->where('bid', $storeBid)
            ->field('override_price')
            ->find();

        if ($storeProduct && $storeProduct['override_price'] !== null) {
            $price = floatval($storeProduct['override_price']);
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

    /**
     * 旧签名内部计算逻辑
     * @param array $product  商品数据（含 override_price, sell_price, min_price）
     * @param int   $storeBid 直营店ID
     * @return float
     */
    private static function calcDisplayPrice($product, $storeBid)
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
