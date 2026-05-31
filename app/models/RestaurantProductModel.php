<?php




namespace app\models;

use think\Model;

class RestaurantProductModel extends Model
{

    protected $name = 'restaurant_product';
    protected $autoWriteTimestamp = true;

    public function getList($where = [], $page = 1, $limit = 15, $order = '')
    {
        $order = $order ? $order : 'sort desc,id';
        $page = $page ? intval($page) : 1;
        $limit = $limit ? intval($limit) : 15;

        $model = $this;
        if ($where) {
            $model = $model->where($where);
        }
        $count = $model->count();
        if ($limit != 'all') {
            $model = $model->page($page, $limit);
        }

        $data = $model->order($order)->select()->toArray();
        if($data) {
            foreach ($data as &$item) {
                $item['cname'] = '';
                if($item['cid']) {
                    $cids = explode(',', $item['cid']);
                    $item['cnameArr'] = RestaurantProductCategoryModel::whereIn('id', $cids)->column('name');
                    $item['cname'] = implode(',', $item['cnameArr']);
                }
            }
        }

        return ['count' => $count, 'list' => $data];

    }

    public function productCategory()
    {
//        return $this->hasOne(RestaurantProductCategoryModel::class, 'id', 'cid');
    }

}