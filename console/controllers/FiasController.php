<?php
namespace alexvendor2018\fias\console\controllers;

use alexvendor2018\fias\console\base\Loader;
use alexvendor2018\fias\console\base\XmlReader;
use alexvendor2018\fias\models\FiasAddressObject;
use alexvendor2018\fias\models\FiasHouse;
use alexvendor2018\fias\models\FiasUpdateLog;
use Yii;
use yii\console\Controller;
use yii\helpers\Console;

class FiasController extends Controller
{
    public function actionIndex()
    {
        $loader = $this->getLoader();

        $directory = $loader->load();
        $versionId = $directory->getVersionId();

        $transaction = Yii::$app->getDb()->beginTransaction();
        try {
            Yii::$app->getDb()->createCommand('SET foreign_key_checks = 0;')->execute();
            FiasAddressObject::import(new XmlReader(
                $directory->getAddressObjectFile(),
                FiasAddressObject::XML_OBJECT_KEY,
                array_keys(FiasAddressObject::getXmlAttributes()),
                FiasAddressObject::getXmlFilters()
            ));

            FiasHouse::import(new XmlReader(
                $directory->getHouseFile(),
                FiasHouse::XML_OBJECT_KEY,
                array_keys(FiasHouse::getXmlAttributes()),
                FiasHouse::getXmlFilters()
            ));

            if (!$log = FiasUpdateLog::findOne(['version_id' => $versionId])) {
                $log = new FiasUpdateLog();
                $log->version_id = $versionId;
            }

            $log->created_at = time();
            $log->save(false);
            Yii::$app->getDb()->createCommand('SET foreign_key_checks = 1;')->execute();
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    public function actionUpdate()
    {
        $loader = $this->getLoader();

        /** @var FiasUpdateLog $currentVersion */
        $currentVersion = FiasUpdateLog::find()->orderBy('id desc')->limit(1)->one();

        $filesInfo = $loader->isUpdateRequired($currentVersion ? $currentVersion->version_id : null);
        if ($filesInfo === null) {
            Console::output('База в актуальном состоянии или не инициализирована');
            return;
        } else {
            Console::prompt("Вы хотите выполнить обновление с версии {$currentVersion->version_id} на {$filesInfo->getVersionId()}", ['required' => true]);
        }

        $directory = $loader->load($filesInfo);
        $versionId = $directory->getVersionId();

        $deletedHouseFile = $directory->getDeletedHouseFile();

        if ($deletedHouseFile) {
            FiasHouse::remove(new XmlReader(
                $deletedHouseFile,
                FiasHouse::XML_OBJECT_KEY,
                array_keys(FiasHouse::getXmlAttributes()),
                FiasHouse::getXmlFilters()
            ));
        }

        $deletedAddressObjectsFile = $directory->getDeletedAddressObjectFile();
        if ($deletedAddressObjectsFile) {
            FiasAddressObject::remove(new XmlReader(
                $deletedAddressObjectsFile,
                FiasAddressObject::XML_OBJECT_KEY,
                array_keys(FiasAddressObject::getXmlAttributes()),
                FiasAddressObject::getXmlFilters()
            ));
        }

        $attributes = FiasAddressObject::getXmlAttributes();
        $attributes['PREVID'] = 'previous_id';

        $transaction = Yii::$app->getDb()->beginTransaction();
        try {
            Yii::$app->getDb()->createCommand('SET foreign_key_checks = 0;')->execute();
            FiasAddressObject::updateRecords(new XmlReader(
                $directory->getAddressObjectFile(),
                FiasAddressObject::XML_OBJECT_KEY,
                array_keys($attributes),
                FiasAddressObject::getXmlFilters()
            ), $attributes);

            $attributes = FiasHouse::getXmlAttributes();
            $attributes['PREVID'] = 'previous_id';

            FiasHouse::updateRecords(new XmlReader(
                $directory->getHouseFile(),
                FiasHouse::XML_OBJECT_KEY,
                array_keys($attributes),
                FiasHouse::getXmlFilters()
            ), $attributes);

            if (!$log = FiasUpdateLog::findOne(['version' => $versionId])) {
                $log = new FiasUpdateLog();
                $log->version_id = $versionId;
            }

            $log->created_at = time();
            $log->save(false);
            Yii::$app->getDb()->createCommand('SET foreign_key_checks = 1;')->execute();
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * @return Loader
     */
    protected function getLoader()
    {
        return new Loader(
            'http://fias.nalog.ru/WebServices/Public/DownloadService.asmx?WSDL',
            Yii::getAlias('@console/runtime')
        );
    }
}