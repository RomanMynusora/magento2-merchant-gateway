<?php

namespace Heidelpay\MGW\Model\Observer;

use Heidelpay\MGW\Helper\Payment as PaymentHelper;
use Heidelpay\MGW\Model\Config;
use Heidelpay\MGW\Model\Method\Base;
use heidelpayPHP\Constants\ApiResponseCodes;
use heidelpayPHP\Exceptions\HeidelpayApiException;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\StatusResolver;
use Psr\Log\LoggerInterface;

use function in_array;

/**
 * Observer for automatically tracking shipments in the Gateway
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
class ShipmentObserver implements ObserverInterface
{
    /**
     * List of payment method codes for which the shipment can be tracked in the gateway.
     */
    public const SHIPPABLE_PAYMENT_METHODS = [
        Config::METHOD_INVOICE_GUARANTEED,
        Config::METHOD_INVOICE_GUARANTEED_B2B,
    ];

    /**
     * @var Config
     */
    protected $_moduleConfig;

    /**
     * @var StatusResolver
     */
    protected $_orderStatusResolver;

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ShipmentObserver constructor.
     * @param Config $moduleConfig
     * @param StatusResolver $orderStatusResolver
     * @param PaymentHelper $paymentHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $moduleConfig,
        StatusResolver $orderStatusResolver,
        PaymentHelper $paymentHelper,
        LoggerInterface $logger
    ) {
        $this->_moduleConfig = $moduleConfig;
        $this->_orderStatusResolver = $orderStatusResolver;
        $this->_paymentHelper = $paymentHelper;
        $this->logger = $logger;
    }

    /**
     * @param Observer $observer
     * @return void
     * @throws HeidelpayApiException
     * @throws NoSuchEntityException
     * @throws InputException
     */
    public function execute(Observer $observer): void
    {
        /** @var Shipment $shipment */
        $shipment = $observer->getEvent()->getShipment();

        if (!$shipment->isObjectNew()) {
            return;
        }

        $order = $shipment->getOrder();

        /** @var MethodInterface $methodInstance */
        $methodInstance = $order->getPayment()->getMethodInstance();

        if (!$methodInstance instanceof Base) {
            return;
        }

        $payment = $this->_moduleConfig
            ->getHeidelpayClient()
            ->fetchPaymentByOrderId($order->getIncrementId());

        $this->_paymentHelper->processState($order, $payment);

        if (in_array($order->getPayment()->getMethod(), self::SHIPPABLE_PAYMENT_METHODS, true)) {
            /** @var Order\Invoice $invoice */
            $invoice = $order
                ->getInvoiceCollection()
                ->getFirstItem();

            try {
                $payment->ship($invoice->getId());
            } catch (HeidelpayApiException $e) {
                $allowedExceptions = [
                    ApiResponseCodes::API_ERROR_TRANSACTION_SHIP_NOT_ALLOWED,
                    ApiResponseCodes::CORE_ERROR_INSURANCE_ALREADY_ACTIVATED
                ];
                if (in_array($e->getCode(), $allowedExceptions, true)) {
                    $this->logger->error($e->getMerchantMessage() . '[' . $e->getCode() . ']', ['incrementId' => $order->getIncrementId()]);
                    throw $e;
                }
            }
        }
    }
}
