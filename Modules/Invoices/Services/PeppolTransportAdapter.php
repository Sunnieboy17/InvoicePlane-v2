<?php

namespace Modules\Invoices\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Invoices\Contracts\PeppolTransportInterface;
use Modules\Invoices\DTO\PeppolSendResult;
use Modules\Invoices\Enums\PeppolStatus;
use RuntimeException;

/**
 * PeppolTransportAdapter - RB-IMP-17
 *
 * Adapter for Peppol transport integration.
 *
 * This is a foundation/adapter class that provides:
 * - Clear separation between document generation and transport
 * - Configuration-driven endpoint/credentials
 * - Structured logging
 * - Stub implementation for MVP
 *
 * NOT in scope:
 * - Complete Peppol SMP lookup from scratch
 * - Full AS4 messaging implementation
 * - Peppol Access Point ownership
 *
 * Integration points:
 * - Configure Peppol Access Point endpoint
 * - Configure authentication credentials
 * - Set up document format (UBL, CII)
 */
class PeppolTransportAdapter implements PeppolTransportInterface
{
    /**
     * Default Peppol AP endpoint (configurable).
     */
    protected string $endpoint;

    /**
     * Access Point ID.
     */
    protected string $apId;

    /**
     * Authentication credentials.
     */
    protected string $apiKey;

    /**
     * Timeout in seconds.
     */
    protected int $timeout;

    public function __construct()
    {
        $this->endpoint = config('invoices.peppol.transport.endpoint', '');
        $this->apId = config('invoices.peppol.transport.ap_id', '');
        $this->apiKey = config('invoices.peppol.transport.api_key', '');
        $this->timeout = config('invoices.peppol.transport.timeout', 30);
    }

    /**
     * @inheritDoc
     */
    public function send(string $documentXml, string $recipientId, array $options = []): PeppolSendResult
    {
        // Generate message ID
        $messageId = $options['message_id'] ?? $this->generateMessageId();

        Log::info('PeppolTransport: Sending document', [
            'message_id' => $messageId,
            'recipient_id' => $recipientId,
            'format' => $options['format'] ?? 'unknown',
        ]);

        // Check if endpoint is configured
        if (empty($this->endpoint)) {
            Log::warning('PeppolTransport: No endpoint configured');
            return PeppolSendResult::failure(
                'Peppol endpoint not configured. Please configure invoices.peppol.transport.endpoint in config.',
                'CONFIG_MISSING'
            );
        }

        // Validate recipient ID format
        if (!$this->isValidPeppolId($recipientId)) {
            return PeppolSendResult::failure(
                "Invalid Peppol ID format: {$recipientId}. Expected format: XX:YYYYYYYY",
                'INVALID_RECIPIENT'
            );
        }

        try {
            // Build request
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/xml',
                    'X-Message-Id' => $messageId,
                    'X-Sender-Id' => $this->apId,
                    'X-Recipient-Id' => $recipientId,
                ])
                ->withToken($this->apiKey)
                ->send('POST', $this->endpoint, [
                    'body' => $documentXml,
                ]);

            if ($response->successful()) {
                Log::info('PeppolTransport: Document sent successfully', [
                    'message_id' => $messageId,
                    'status_code' => $response->status(),
                ]);

                return PeppolSendResult::success($messageId, [
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);
            }

            Log::error('PeppolTransport: Failed to send document', [
                'message_id' => $messageId,
                'status_code' => $response->status(),
                'error' => $response->body(),
            ]);

            return PeppolSendResult::failure(
                "HTTP {$response->status()}: {$response->body()}",
                'HTTP_ERROR'
            );

        } catch (\Exception $e) {
            Log::error('PeppolTransport: Exception during send', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return PeppolSendResult::failure(
                'Transport error: ' . $e->getMessage(),
                'TRANSPORT_ERROR'
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function isValidRecipient(string $peppolId): bool
    {
        return $this->isValidPeppolId($peppolId);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(string $messageId): PeppolStatus
    {
        // Check if endpoint is configured
        if (empty($this->endpoint)) {
            return PeppolStatus::FAILED;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->apiKey)
                ->get("{$this->endpoint}/status/{$messageId}");

            if ($response->successful()) {
                $data = $response->json();
                return PeppolStatus::from($data['status'] ?? 'pending');
            }

            return PeppolStatus::FAILED;

        } catch (\Exception $e) {
            Log::error('PeppolTransport: Failed to get status', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            return PeppolStatus::FAILED;
        }
    }

    /**
     * Validate Peppol ID format.
     */
    protected function isValidPeppolId(string $peppolId): bool
    {
        // Peppol ID format: XX:YYYYYYYY (e.g., BE:0123456789)
        // Must have country code prefix and identifier
        return (bool) preg_match('/^[A-Z]{2}:[A-Za-z0-9]+$/', $peppolId);
    }

    /**
     * Generate unique message ID.
     */
    protected function generateMessageId(): string
    {
        $domain = config('invoices.peppol.transport.domain', parse_url(url('/'), PHP_URL_HOST));
        return Str::uuid()->toString() . '@' . $domain;
    }
}
