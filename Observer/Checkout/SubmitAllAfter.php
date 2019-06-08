<?php

namespace Xigen\AutoInvoice\Observer\Checkout;

/**
 * SubmitAllAfter event.
 */
class SubmitAllAfter implements \Magento\Framework\Event\ObserverInterface
{
    protected $helper;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Xigen\AutoInvoice\Helper\AutoInvoice $helper
    ) {
        $this->logger = $logger;
        $this->helper = $helper;
    }

    /**
     * Execute observer.
     *
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return void
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        // $this->logger->debug('event');

        if (!$this->helper->isEnabled()) {
            return $this;
        }

        // $this->logger->debug('enabled');

        $order = $observer->getEvent()->getOrder();
        if (!$order->getId()) {
            return $this;
        }

        // $this->logger->debug('id:'.$order->getId());

        $invoice = $this->helper->createInvoice($order->getId());

        return $this;
    }
}
