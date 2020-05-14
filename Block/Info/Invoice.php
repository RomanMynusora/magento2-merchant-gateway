<?php

namespace Heidelpay\MGW\Block\Info;

use Heidelpay\MGW\Model\Config;
use heidelpayPHP\Heidelpay;
use heidelpayPHP\Exceptions\HeidelpayApiException;
use heidelpayPHP\Resources\Payment;
use heidelpayPHP\Resources\TransactionTypes\Charge;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Payment\Block\Info;
use Magento\Sales\Model\Order;

/**
 * Customer Account Order Invoice Information Block
 *
 * Copyright (C) 2019 heidelpay GmbH
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @link  https://docs.heidelpay.com/
 *
 * @author Justin Nuß
 *
 * @package  heidelpay/magento2-merchant-gateway
 */
class Invoice extends Info
{
    protected $_template = 'Heidelpay_MGW::info/invoice.phtml';

    /**
     * @var Config
     */
    protected $_moduleConfig;

    /**
     * @var Payment
     */
    protected $_payment;

    /**
     * @var Charge
     */
    protected $charge;

    /**
     * Invoice constructor.
     * @param Template\Context $context
     * @param Config $moduleConfig
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Config $moduleConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->_moduleConfig = $moduleConfig;
    }

    /**
     * @inheritDoc
     */
    public function toPdf(): string
    {
        $this->setTemplate('Heidelpay_MGW::info/pdf/invoice.phtml');
        return $this->toHtml();
    }

    /**
     * Returns the first charge for the payment.
     *
     * @throws LocalizedException
     * @throws HeidelpayApiException
     *
     * @return Charge|null
     */
    protected function _getCharge()
    {
        if (!$this->charge instanceof Charge) {
            try {
                $this->charge = $this->_getPayment()->getChargeByIndex(0);
            } catch (HeidelpayApiException $e) {
                $this->_logger->error($e->getMessage() . ' [' . $e->getCode() . ']');
            }
        }
        return $this->charge;
    }

    /**
     * Returns the payment.
     *
     * @throws LocalizedException
     * @throws HeidelpayApiException
     *
     * @return Payment
     */
    protected function _getPayment(): Payment
    {
        if ($this->_payment === null) {
            /** @var Heidelpay $client */
            $client = $this->_moduleConfig->getHeidelpayClient();

            /** @var Order $order */
            $order = $this->getInfo()->getOrder();

            $this->_payment = $client->fetchPaymentByOrderId($order->getIncrementId());
        }

        return $this->_payment;
    }

    /**
     * @throws LocalizedException
     * @throws HeidelpayApiException
     * @return string
     */
    public function getAccountHolder(): string
    {
        return $this->_getCharge()->getHolder();
    }

    /**
     * @throws LocalizedException
     * @throws HeidelpayApiException
     * @return string
     */
    public function getAccountIban(): string
    {
        return $this->_getCharge()->getIban();
    }

    /**
     * @throws LocalizedException
     * @throws HeidelpayApiException
     * @return string
     */
    public function getAccountBic(): string
    {
        return $this->_getCharge()->getBic();
    }

    /**
     * @throws LocalizedException
     * @throws HeidelpayApiException
     * @return string
     */
    public function getReference(): string
    {
        return $this->_getCharge()->getDescriptor();
    }

    /**
     * Returns the order for the invoice.
     *
     * @throws LocalizedException
     *
     * @return Order
     */
    public function getOrder(): Order
    {
        $order = $this->_getData('order');
        if ($order) {
            return $order;
        }
        return $this->getInfo()->getOrder();
    }
}
