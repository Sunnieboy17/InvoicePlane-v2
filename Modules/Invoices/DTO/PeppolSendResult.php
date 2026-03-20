<?php

namespace Modules\Invoices\DTO;

/**
 * PeppolSendResult - RB-IMP-17
 *
 * Data Transfer Object for Peppol send operation results.
 */
class PeppolSendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $messageId = null,
        public readonly ?string $status = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $errorCode = null,
        public readonly array $metadata = []
    ) {
    }

    /**
     * Create a successful result.
     */
    public static function success(string $messageId, array $metadata = []): self
    {
        return new self(
            success: true,
            messageId: $messageId,
            status: PeppolStatus::SENDING,
            metadata: $metadata
        );
    }

    /**
     * Create a failed result.
     */
    public static function failure(string $errorMessage, ?string $errorCode = null): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
            errorCode: $errorCode
        );
    }

    /**
     * Check if result is successful.
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Get as array for logging/debugging.
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message_id' => $this->messageId,
            'status' => $this->status,
            'error_message' => $this->errorMessage,
            'error_code' => $this->errorCode,
            'metadata' => $this->metadata,
        ];
    }
}
