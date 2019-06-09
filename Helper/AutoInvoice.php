<?php

namespace Xigen\AutoInvoice\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

/**
 * AutoInvoice Helper.
 */
class AutoInvoice extends AbstractHelper
{
    const MODULE_ENABLED = 'autoinvoice/autoinvoice/enabled';
    const MODULE_PAYMENTS = 'autoinvoice/autoinvoice/payments';
    const MODULE_CUSTOMER_GROUP = 'autoinvoice/autoinvoice/customer_group';
    const MODULE_CUSTOMER_EMAIL = 'autoinvoice/autoinvoice/customer_email';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory
     */
    protected $invoiceCollectionFactory;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var \Magento\Sales\Model\Order\ShipmentFactory
     */
    protected $shipmentFactory;

    /**
     * @var \Magento\Framework\DB\TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $orderRepositoryInterface;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepositoryInterface;

    /**
     * Undocumented function
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Sales\Model\Order\ShipmentFactory $shipmentFactory
     * @param \Magento\Framework\DB\TransactionFactory $transactionFactory
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\ResourceModel\Order\Invoice\CollectionFactory $invoiceCollectionFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\ShipmentFactory $shipmentFactory,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepositoryInterface
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->invoiceService = $invoiceService;
        $this->shipmentFactory = $shipmentFactory;
        $this->transactionFactory = $transactionFactory;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        $this->customerRepositoryInterface = $customerRepositoryInterface;
    }

    /**
     * Create invoide by entity_id.
     *
     * @param int $orderIdMODULE_CUSTOMER_GROUP
     *
     * @return void
     */
    public function createInvoice($orderId)
    {
        try {
            $order = $this->getById($orderId);
            if (!$order || !$order->getId() || !$order->canInvoice() || !$order->getPayment()) {
                return;
            }

            $paymentMethod = $order->getPayment()->getMethod();

            if (!$paymentMethod || !in_array($paymentMethod, explode(',', $this->getAutoInvoicePayments()))) {
                return;
            }

            $invoice = $this->invoiceService->prepareInvoice($order);
            if (!$invoice || !$invoice->getTotalQty()) {
                return;
            }
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);
            $order->addStatusHistoryComment('Automatically INVOICED', false);
            $transactionSave = $this->transactionFactory
                ->create()
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionSave->save();
        } catch (\Exception $e) {
            $order->addStatusHistoryComment('Exception message: '.$e->getMessage(), false);
            $order->save();
        }
    }

    /**
     * Is the module enabled in configuration.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->scopeConfig->getValue(self::MODULE_ENABLED);
    }

    /**
     * Is the module enabled for certain payments in configuration.
     *
     * @return string
     */
    public function getAutoInvoicePayments()
    {
        return $this->scopeConfig->getValue(self::MODULE_PAYMENTS);
    }

    /**
     * Is the module enabled for certain customer group(s) in configuration.
     *
     * @return string
     */
    public function getCustomerGroup()
    {
        $config = $this->scopeConfig->getValue(self::MODULE_CUSTOMER_GROUP);
        if ($config) {
            return explode(',', $config);
        }
        return false;
    }

    /**
     * Is the module enabled for certain email(s) in configuration.
     *
     * @return string
     */
    public function getCustomerEmail()
    {
        $config = $this->scopeConfig->getValue(self::MODULE_CUSTOMER_EMAIL);
        if ($config) {
            $emailArray = [];
            $emails = explode(',', trim($this->scopeConfig->getValue(self::MODULE_CUSTOMER_EMAIL)));
            foreach ($emails as $email) {
                $emailArray[trim($email)] = trim($email);
            }
            return $emailArray;
        }
        return false;
    }

    /**
     * Get order by Id.
     *
     * @param int $orderId
     *
     * @return \Magento\Sales\Model\Data\Order
     */
    public function getById($orderId)
    {
        try {
            return $this->orderRepositoryInterface->get($orderId);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get customer by Id.
     *
     * @param int $customerId
     *
     * @return \Magento\Customer\Model\Data\Customer
     */
    public function getCustomerById($customerId)
    {
        try {
            return $this->customerRepositoryInterface->getById($customerId);
        } catch (\Exception $e) {
            $this->logger->critical($e);

            return false;
        }
    }
}
