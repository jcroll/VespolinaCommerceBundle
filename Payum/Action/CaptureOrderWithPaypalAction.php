<?php

namespace Vespolina\CommerceBundle\Payum\Action;

use Payum\Action\PaymentAwareAction;
use Payum\Bridge\Spl\ArrayObject;
use Payum\Exception\RequestNotSupportedException;
use Payum\Request\CaptureRequest;
use Payum\Request\SecuredCaptureRequest;

class CaptureOrderWithPaypalAction extends PaymentAwareAction
{
    /**
     * {@inheritDoc}
     */
    public function execute($request)
    {
        /** @var $request SecuredCaptureRequest */
        if (!$this->supports($request)) {
            throw RequestNotSupportedException::createActionNotSupported($this, $request);
        }

        /** @var OrderInterface $order */
        $order = $request->getModel();

        $paymentDetails = $order->getPayment()->getDetails();
        if (empty($paymentDetails)) {
            $paymentDetails['RETURNURL'] = $request->getToken()->getTargetUrl();
            $paymentDetails['CANCELURL'] = $request->getToken()->getTargetUrl();
            $paymentDetails['INVNUM'] = $order->getNumber();

            $paymentDetails['PAYMENTREQUEST_0_CURRENCYCODE'] = $order->getCurrency();
            $paymentDetails['PAYMENTREQUEST_0_AMT'] = number_format(($order->getTotal() + $order->getShippingTotal()) / 100, 2);
            $paymentDetails['PAYMENTREQUEST_0_ITEMAMT'] = number_format($order->getItemsTotal() / 100, 2);
            $paymentDetails['PAYMENTREQUEST_0_TAXAMT'] = number_format($order->getTaxTotal() / 100, 2);
            $paymentDetails['PAYMENTREQUEST_0_SHIPPINGAMT'] = number_format($order->getShippingTotal() / 100, 2);

            $m = 0;
            foreach ($order->getItems() as $item) {
                $paymentDetails['L_PAYMENTREQUEST_0_AMT'.$m] =  number_format($item->getTotal() / 100, 2);
                $paymentDetails['L_PAYMENTREQUEST_0_QTY'.$m] =  $item->getQuantity();

                $m++;
            }
        }

        // TODO: find a way to simply the next logic

        $paymentDetails = ArrayObject::ensureArrayObject($paymentDetails);

        try {
            $this->payment->execute(new CaptureRequest($paymentDetails));
            $order->getPayment()->setDetails((array) $paymentDetails);
        } catch (\Exception $e) {
            $order->getPayment()->setDetails((array) $paymentDetails);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof SecuredCaptureRequest &&
            $request->getModel() instanceof OrderInterface
            ;
    }
}