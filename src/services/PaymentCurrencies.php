<?php

namespace craft\commerce\services;

use Craft;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\records\PaymentCurrency as PaymentCurrencyRecord;
use craft\db\Query;
use yii\base\Component;
use yii\db\Expression;

/**
 * Payment currency service.
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   craft.plugins.commerce.services
 * @since     1.2
 */
class PaymentCurrencies extends Component
{

    private $_allCurrencies;

    /**
     * @param int $id
     *
     * @return PaymentCurrency|null
     */
    public function getPaymentCurrencyById($id)
    {
        foreach ($this->getAllPaymentCurrencies() as $currency) {
            if ($currency->id == $id) {
                return $currency;
            }
        }

        return null;
    }

    /**
     * @return PaymentCurrency[]
     */
    public function getAllPaymentCurrencies()
    {
        if (null === $this->_allCurrencies) {
            $schema = Craft::$app->getDb()->schema;
            $rows = (new Query())
                ->select([
                    'id',
                    'iso',
                    'primary',
                    'rate'
                ])->from('{{%commerce_paymentcurrencies}}')
                ->orderBy(new Expression('('.$schema->quoteColumnName('primary').' = 1) desc, '.$schema->quoteColumnName('iso')))
                ->all();
            $this->_allCurrencies = PaymentCurrency::populateModels($rows);
        }

        return $this->_allCurrencies;
    }

    /**
     * @param string $iso
     *
     * @return PaymentCurrency|null
     */
    public function getPaymentCurrencyByIso($iso)
    {
        foreach ($this->getAllPaymentCurrencies() as $currency) {
            if ($currency->iso == $iso) {
                return $currency;
            }
        }

        return null;
    }

    /**
     * Return the primary currencies ISO code as a string.
     *
     * @return string
     */
    public function getPrimaryPaymentCurrencyIso()
    {
        return $this->getPrimaryPaymentCurrency()->iso;
    }

    /**
     * Returns the primary currency all prices are entered as.
     *
     * @return PaymentCurrency
     */
    public function getPrimaryPaymentCurrency()
    {
        foreach ($this->getAllPaymentCurrencies() as $currency) {
            if ($currency->primary) {
                return $currency;
            }
        }

        return null;
    }

    /**
     * @param float  $amount This is the unit of price in the primary store currency
     * @param string $currency
     *
     * @return float
     */
    public function convert($amount, $currency)
    {
        $destinationCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($currency);

        return $amount * $destinationCurrency->rate;
    }

    /**
     * @param PaymentCurrency $model
     *
     * @return bool
     * @throws Exception
     * @throws \CDbException
     * @throws \Exception
     */
    public function savePaymentCurrency(PaymentCurrency $model)
    {
        if ($model->id) {
            $record = PaymentCurrencyRecord::findOne($model->id);

            if (!$record) {
                throw new Exception(Craft::t('commerce', 'commerce', 'No currency exists with the ID “{id}”',
                    ['id' => $model->id]));
            }
        } else {
            $record = new PaymentCurrencyRecord();
        }

        $record->iso = strtoupper($model->iso);
        $record->primary = $model->primary;
        // If this rate is primary, the rate must be 1 since it is now the rate all prices are enter in as.
        $record->rate = $model->primary ? 1 : $model->rate;

        $record->validate();
        $model->addErrors($record->getErrors());

        if (!$model->hasErrors()) {

            if ($record->primary) {
                PaymentCurrencyRecord::updateAll(['primary' => 0]);
            }

            $record->save(false);

            // Now that we have a record ID, save it on the model
            $model->id = $record->id;

            return true;
        }

        return false;
    }

    /**
     * @param int $id
     *
     */
    public function deletePaymentCurrencyById($id)
    {
        $paymentCurrency = PaymentCurrencyRecord::findOne($id);

        if ($paymentCurrency) {
            $paymentCurrency->delete();
        }
    }
}