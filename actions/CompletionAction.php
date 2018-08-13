<?php
namespace alexvendor2018\fias\actions;

use Yii;
use alexvendor2018\fias\models\FiasAddressSearch;
use yii\helpers\Json;

class CompletionAction extends \yii\base\Action
{
    public function run()
    {
        $model = new FiasAddressSearch();
        return Json::encode($model->search(Yii::$app->request->get('term', '')));
    }
}