<?php

namespace Xigen\AutoInvoice\Observer\Checkout;

/**
 * SubmitAllAfter event.
 */
class SubmitAllAfter implements \Magento\Framework\Event\ObserverInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Xigen\AutoInvoice\Helper\AutoInvoice
     */
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
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        $this->logger->debug('event triggered');

        if (!$this->helper->isEnabled()) {
            return $this;
        }

        $this->logger->debug('AutoInvoice enabled');

        $order = $observer->getEvent()->getOrder();
        if (!$order->getId()) {
            return $this;
        }

        $this->logger->debug((string) __('AutoInvoice inspecting order id : %1', $order->getId()));

        $customerGroups = $this->helper->getCustomerGroup();
        $emails = $this->helper->getCustomerEmail();

        // if no values skip extra validation
        if ($customerGroups || $emails) {

            // $this->logger->debug((string) __('order customer group id : %1', $order->getCustomerGroupId()));

            if ($customerGroups) {
                if (!in_array($order->getCustomerGroupId(), $customerGroups)) {
                    return $this;
                }
            }

            // $this->logger->debug((string) __('order customer email id : %1', $order->getCustomerEmail()));
            if ($emails) {
                if (!in_array($order->getCustomerEmail(), $emails)) {
                    return $this;
                }
            }
        }

        $invoice = $this->helper->createInvoice($order->getId());

        return $this;
    }
}
