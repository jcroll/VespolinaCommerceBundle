<?php

/**
 * (c) 2011 - ∞ Vespolina Project http://www.vespolina-project.org
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Vespolina\CommerceBundle\Controller\Process;

use Payum\Request\BinaryMaskStatusRequest;
use Payum\Request\CaptureRequest;
use Vespolina\CommerceBundle\Form\Type\Process\PaymentFormType;
use Vespolina\CommerceBundle\Entity\CreditCard;
use Payum\Registry\RegistryInterface;
use Payum\Bundle\PayumBundle\Security\TokenFactory;

class ExecutePaymentController extends AbstractProcessStepController
{
    public function executeAction()
    {
        $paymentName = 'paypal_pro_checkout_via_omnipay';
        $payment = $this->getPayum()->getPayment($paymentName);

        $processManager = $this->getProcessManager();
        $request = $this->container->get('request');
        $paymentForm = $this->createPaymentForm();

        if ($this->isPostForForm($request, $paymentForm)) {
            $paymentForm->handleRequest($request);
            if ($paymentForm->isValid()) {
                /** @var CreditCard $creditCard */
                $creditCard = $paymentForm->getData();

                $storage = $this->getPayum()->getStorageForClass(
                    'Vespolina\DefaultStoreBundle\Document\PaymentDetails',
                    $paymentName
                );

                $paymentDetails = $storage->createModel();
                $paymentDetails['amount'] = (float) 10;
                $paymentDetails['card'] = array(
                    'number' => $creditCard->getNumber(),
                    'expiryMonth' => $creditCard->getExpiryMonth(),
                    'expiryYear' => $creditCard->getExpiryYear(),
                    'cvv' => $creditCard->getCvv()
                );

                try {
                    $payment->execute(new CaptureRequest($paymentDetails));
                    $payment->execute($status = new BinaryMaskStatusRequest($paymentDetails));

                    unset($paymentDetails['card']);
                    $storage->updateModel($paymentDetails);
                } catch (\Exception $e) {
                    unset($paymentDetails['card']);
                    $storage->updateModel($paymentDetails);

                    throw $e;
                }

                if ($status->isSuccess()) {
                    //Signal enclosing process step that we are done here
                    /** @var \Vespolina\CommerceBundle\ProcessScenario\Checkout\CheckoutProcessB2C $process */
                    $process = $this->processStep->getProcess();
                    $process->completeProcessStep($this->processStep);
                    $processManager->updateProcess($process);

                    return $process->execute();
                } else {
                    $this->container->get('session')->getFlashBag()->add('danger', $response->getMessage());
                }
            }
        }

        return $this->render('VespolinaCommerceBundle:Process:Step/executePayment.html.twig',
            array(
                'context' => $this->processStep->getContext(),
                'currentProcessStep' => $this->processStep,
                'paymentForm' => $paymentForm->createView()
            )
        );
    }


    /**
     * @return \Symfony\Component\Form\Form
     */
    protected function createPaymentForm()
    {
        $creditCard = new CreditCard();
        $paymentForm = $this->container->get('form.factory')->create(new PaymentFormType(), $creditCard, array());

        return $paymentForm;
    }

    /**
     * @return RegistryInterface
     */
    protected function getPayum()
    {
        return $this->container->get('payum');
    }

    /**
     * @return TokenFactory
     */
    protected function getTokenFactory()
    {
        return $this->container->get('payum.security.token_factory');
    }
}
