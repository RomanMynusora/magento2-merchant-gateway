<?php

namespace Heidelpay\MGW\Model\Observer;

use heidelpayPHP\Exceptions\HeidelpayApiException;
use heidelpayPHP\Resources\AbstractHeidelpayResource;
use heidelpayPHP\Resources\Payment;
use heidelpayPHP\Resources\TransactionTypes\AbstractTransactionType;
use Magento\Framework\Exception\InputException;
use Magento\Sales\Model\Order;

/**
 * Observer for webhooks about completed payments
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
class PaymentCompletedObserver extends AbstractPaymentWebhookObserver
{
    /**
     * @param Order $order
     * @param AbstractHeidelpayResource $resource
     * @return void
     * @throws InputException
     * @throws HeidelpayApiException
     */
    public function executeWith(Order $order, AbstractHeidelpayResource $resource): void
    {
        $payment = $resource;

        if ($resource instanceof AbstractTransactionType) {
            $payment = $resource->getPayment();
        }

        if ($payment instanceof Payment && $payment->isCompleted()) {
            $this->_paymentHelper->handlePaymentCompletion($order, $payment);
        }
    }
}