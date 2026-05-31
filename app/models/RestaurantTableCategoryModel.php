<?php




namespace app\models;

use think\Model;

class RestaurantTableCategoryModel extends Model
{

    protected $name = 'restaurant_table_category';
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
        if ($limit != 'all') {
            $model = $model->page($page, $limit);
        }

        $data = $model->order($order)->select();

        return $data;

    }

}