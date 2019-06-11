<?php

namespace Xigen\AutoInvoice\Console\Command;

use Magento\Sales\Model\Service\InvoiceService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Invoice console command.
 */
class Invoice extends Command
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    protected $orderRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

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
     * @var Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\App\State $state,
        \Magento\Sales\Model\Order\ShipmentFactory $shipmentFactory,
        \Magento\Framework\DB\TransactionFactory $transactionFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface
    ) {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->state = $state;
        $this->shipmentFactory = $shipmentFactory;
        $this->transactionFactory = $transactionFactory;
        $this->orderRepositoryInterface = $orderRepositoryInterface;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('xigen:invoice:order')
            ->setDescription('Invoice order within magento')
            ->addOption(
                'orderid',
                'o',
                InputOption::VALUE_REQUIRED,
                'Order ID',
                false
            );

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_GLOBAL);

        try {
            $this->output = $output;

            $orderid = $input->getOption('orderid');
            $this->output->writeln((string) __('%1 Processing order <info>%2<info>', date('Y-m-d H:i:s'), $orderid));

            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $orderid, 'eq')
                ->create();

            $order = $this->orderRepository
                ->getList($searchCriteria)
                ->getFirstItem();

            if ($order && $order->getId()) {
                $this->output->writeln((string) __('[%1] Processing order <info>%2 : %3<info>', date('Y-m-d H:i:s'), $order->getId(), $order->getIncrementId()));
                $this->createInvoice($order->getId());
            }
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());
            $this->output->writeln((string) __('<error>Error:</error> Unable to load order %1', $e->getMessage()));

            return;
        }
    }

    /**
     * Create invoice by entity_id.
     *
     * @param int $orderId
     *
     * @return void
     */
    public function createInvoice($orderId)
    {
        try {
            $order = $this->getById($orderId);
            if (!$order || !$order->getId() || !$order->canInvoice() || !$order->getPayment()) {
                $this->output->writeln((string) __('[%1] Cannot invoice <info>%2<info>', date('Y-m-d H:i:s'), $orderId));

                return;
            }

            $paymentMethod = $order->getPayment()->getMethod();

            if (!$paymentMethod) {
                return;
            }

            $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $this->invoiceService = $this->_objectManager->create(InvoiceService::class);

            $invoice = $this->invoiceService->prepareInvoice($order);
            if (!$invoice || !$invoice->getTotalQty()) {
                return;
            }
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);
            $order->addStatusHistoryComment('Console INVOICED', false);
            $transactionSave = $this->transactionFactory
                ->create()
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionSave->save();
            $this->output->writeln((string) __('[%1] Order Invoiced order <info>%2 : %3<info>', date('Y-m-d H:i:s'), $order->getId(), $order->getIncrementId()));
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());
            $this->output->writeln((string) __('[%1] <error>Error:</error> Unable to load order %2', date('Y-m-d H:i:s'), $e->getMessage()));
        }
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
}
