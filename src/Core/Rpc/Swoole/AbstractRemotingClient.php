<?php

namespace Hyperf\Seata\Core\Rpc\Swoole;


use Exception;
use Hyperf\Pool\Pool;
use Hyperf\Seata\Core\Protocol\AbstractMessage;
use Hyperf\Seata\Core\Protocol\Transaction\GlobalBeginResponse;
use Hyperf\Seata\Core\Rpc\AbstractRpcRemoting;
use Hyperf\Seata\Core\Rpc\Address;
use Hyperf\Seata\Core\Rpc\Processor\RemotingProcessorInterface;
use Hyperf\Seata\Core\Rpc\RemotingClientInterface;
use Hyperf\Seata\Core\Rpc\RpcClientBootstrapInterface;
use Hyperf\Seata\Core\Rpc\TransactionMessageHandler;
use Hyperf\Seata\Discovery\Registry\RegistryFactory;
use Hyperf\Seata\Exception\SeataErrorCode;
use Hyperf\Seata\Exception\SeataException;
use Hyperf\Seata\Tm\TransactionManagerHolder;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Coroutine;

abstract class AbstractRemotingClient extends AbstractRpcRemoting implements RemotingClientInterface
{

    protected null|TransactionMessageHandler $transactionMessageHandler = null;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var RegistryFactory
     */
    protected $registryFactory;

    protected const MSG_ID_PREFIX = "msgId:";
    protected const FUTURES_PREFIX = "futures:";
    protected const SINGLE_LOG_POSTFIX = ";";
    protected const MAX_MERGE_SEND_MILLS = 1;
    protected const THREAD_PREFIX_SPLIT_CHAR = "_";

    protected const MAX_MERGE_SEND_THREAD = 1;
    protected const KEEP_ALIVE_TIME = PHP_INT_MAX;
    protected const SCHEDULE_INTERVAL_MILLS = 5;
    protected const MERGE_THREAD_PREFIX = "rpcMergeMessageSend";

    /**
     * @see \Hyperf\Seata\Core\Rpc\TransactionRole
     */
    protected int $transactionRole;
    protected SwooleClientConnectionManager $connectionManager;
    protected array $recvChannelMap = [];
    protected SwooleClientBootstrap $clientBootstrap;

    protected RpcClientBootstrapInterface $rpcClientBootstrap;

    public function __construct(int $transactionRole)
    {
        parent::__construct();
        $this->transactionRole = $transactionRole;
        $container = ApplicationContext::getContainer();
        $this->registryFactory = $container->get(RegistryFactory::class);
        $this->rpcClientBootstrap = $container->get(RpcClientBootstrapInterface::class);
        $this->rpcClientBootstrap->setClientHandler(new ClientHandler());
        $this->connectionManager = $container->get(SwooleClientConnectionManager::class);
        $this->clientBootstrap = $container->get(SwooleClientBootstrap::class);
    }

    public function init() {
        // @TODO 启动一个 reconnect 的 Timer
        \Swoole\Timer::tick(10 * 1000 , function () {
            $this->connectionManager->reconnect($this->transactionServiceGroup);
        });

        parent::init();
        // TODO merge send runnable
//        (new MergedSendRunnable($this->isSending, $this->basketMap, $this))->run();
        $this->clientBootstrap->start();
    }

    /**
     * @param \Hyperf\Seata\Core\Protocol\AbstractMessage $message
     * @param int $timeout
     * @return \Hyperf\Seata\Core\Protocol\Transaction\GlobalBeginResponse
     */
    public function sendMsgWithResponse(AbstractMessage $message, int $timeout = 100)
    {
        $validAddress = $this->loadBalance($this->getTransactionServiceGroup());
        $connection = $this->connectionManager->acquireConnection($validAddress);
        $result = $this->sendAsyncRequestWithResponse($connection, $message, $timeout);
        $connection->release();
        if ($result instanceof GlobalBeginResponse && ! $result->getResultCode()) {
            if ($this->logger) {
                $this->logger->error('begin response error,release socket');
            }
        }
        return $result;
    }

    abstract protected function getTransactionServiceGroup(): string;

    private function loadBalance(string $transactionServiceGroup): ?Address
    {
        $address = null;
        try {
            $addressList = $this->lookupAddresses($transactionServiceGroup);
            // @todo 通过负载均衡器选择一个地址
            $address = $addressList[0];
        } catch (Exception $exception) {
            if ($this->logger) {
                $this->logger->error($exception->getMessage());
            }
        }
        if (! $address instanceof Address) {
            throw new SeataException(SeataErrorCode::NoAvailableService);
        }
        return $address;
    }

    /**
     * @param string $transactionServiceGroup
     * @return array<Address>
     */
    protected function lookupAddresses(string $transactionServiceGroup): array
    {
        return $this->registryFactory->getInstance()->lookup($transactionServiceGroup);;
    }

    public function setTransactionMessageHandler(TransactionMessageHandler $transactionMessageHandler) {
        $this->transactionMessageHandler = $transactionMessageHandler;
    }

    public function getTransactionMessageHandler(): ?TransactionMessageHandler
    {
        return $this->transactionMessageHandler;
    }

    public function registerProcessor(int $messageType, RemotingProcessorInterface $processor)
    {
        $this->processorTable[$messageType] = $processor;
    }
}