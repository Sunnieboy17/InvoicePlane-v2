<?php

namespace Modules\Invoices\Contracts;

/**
 * PeppolTransportInterface - RB-IMP-17
 *
 * Interface for Peppol transport integration.
 *
 * Separates document generation from transport/delivery.
 * Implementing classes handle the actual Peppol network communication.
 *
 * NOT in scope:
 * - Complete Peppol network implementation from scratch
 * - Full Access Point implementation
 * - Certification
 */
interface PeppolTransportInterface
{
    /**
     * Send an invoice via Peppol.
     *
     * @param string $documentXml The invoice XML document
     * @param string $recipientId Peppol ID of recipient (e.g., BE:0123456789)
     * @param array $options Additional options (format, documentId, etc.)
     *
     * @return PeppolSendResult
     */
    public function send(string $documentXml, string $recipientId, array $options = []): PeppolSendResult;

    /**
     * Check if a Peppol ID is valid/reachable.
     *
     * @param string $peppolId Peppol ID to check
     *
     * @return bool
     */
    public function isValidRecipient(string $peppolId): bool;

    /**
     * Get the transport status for a previously sent document.
     *
     * @param string $messageId The Peppol message ID
     *
     * @return PeppolStatus
     */
    public function getStatus(string $messageId): PeppolStatus;
}
