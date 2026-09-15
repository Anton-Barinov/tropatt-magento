<?php

namespace Tropatt\Crm\Model;

use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Tropatt\Crm\Api\WebhookInterface;
use Tropatt\Crm\Model\Registry\EchoGuard;

/**
 * Inbound CRM webhook: verifies the signature and applies the mapped status.
 */
class Webhook implements WebhookInterface
{
    /** @var Config */
    private $config;

    /** @var Signature */
    private $signature;

    /** @var OrderRepositoryInterface */
    private $orderRepository;

    /** @var EchoGuard */
    private $echoGuard;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        Config $config,
        Signature $signature,
        OrderRepositoryInterface $orderRepository,
        EchoGuard $echoGuard,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->signature = $signature;
        $this->orderRepository = $orderRepository;
        $this->echoGuard = $echoGuard;
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public function execute($payload, $signature = '', $timestamp = '')
    {
        $secret = $this->config->webhookSecret();

        if ($secret === '' || $signature === '' || $timestamp === '') {
            return $this->json(401, ['error' => 'Missing authentication headers']);
        }

        if (!$this->signature->withinTolerance($timestamp)) {
            return $this->json(401, ['error' => 'Timestamp out of tolerance window']);
        }

        if (!$this->signature->verifyWebhook($timestamp, (string)$payload, (string)$signature, $secret)) {
            return $this->json(401, ['error' => 'Invalid cryptographic signature']);
        }

        $data = json_decode((string)$payload, true);
        if (!is_array($data)) {
            return $this->json(400, ['error' => 'Invalid JSON payload']);
        }

        $orderId = (int)($data['external_order_id'] ?? 0);
        if ($orderId <= 0) {
            return $this->json(422, ['error' => 'Missing external_order_id']);
        }

        $status = !empty($data['external_status'])
            ? (string)$data['external_status']
            : StatusMapper::magentoStatusFor($this->config->statusMapping(), (string)($data['new_status'] ?? ''));

        if ($status === null || $status === '') {
            return $this->json(200, ['success' => true, 'notice' => 'Ignored: no mapping for status']);
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Throwable $exception) {
            return $this->json(404, ['error' => 'Order not found: ' . $orderId]);
        }

        // Anti-echo: the save below must not publish an order event back to the CRM.
        $this->echoGuard->suppress(true);

        try {
            $order->setStatus($status);
            $this->orderRepository->save($order);
        } catch (\Throwable $exception) {
            $this->logger->error('TropaTT CRM: failed to apply the order status', [
                'order_id' => $orderId,
                'status' => $status,
                'error' => $exception->getMessage(),
            ]);
            $this->echoGuard->suppress(false);

            return $this->json(500, ['error' => 'Failed to apply the status']);
        }

        $this->echoGuard->suppress(false);

        return $this->json(200, ['success' => true, 'order_id' => $orderId, 'new_status' => $status]);
    }

    /**
     * @return string
     */
    private function json($status, array $payload)
    {
        http_response_code($status);

        return (string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
