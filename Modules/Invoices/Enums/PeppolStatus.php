<?php

namespace Modules\Invoices\Enums;

use Modules\Core\Contracts\LabeledEnum;

/**
 * PeppolStatus - RB-IMP-17
 *
 * Enum for Peppol document transport status.
 */
enum PeppolStatus: string implements LabeledEnum
{
    case PENDING    = 'pending';
    case SENDING    = 'sending';
    case DELIVERED  = 'delivered';
    case ACCEPTED   = 'accepted';
    case REJECTED   = 'rejected';
    case FAILED     = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING   => trans('ip.peppol_status_pending'),
            self::SENDING   => trans('ip.peppol_status_sending'),
            self::DELIVERED => trans('ip.peppol_status_delivered'),
            self::ACCEPTED  => trans('ip.peppol_status_accepted'),
            self::REJECTED  => trans('ip.peppol_status_rejected'),
            self::FAILED    => trans('ip.peppol_status_failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING   => 'gray',
            self::SENDING   => 'info',
            self::DELIVERED => 'success',
            self::ACCEPTED  => 'success',
            self::REJECTED  => 'warning',
            self::FAILED   => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PENDING   => 'heroicon-o-clock',
            self::SENDING   => 'heroicon-o-paper-airplane',
            self::DELIVERED => 'heroicon-o-check-circle',
            self::ACCEPTED  => 'heroicon-o-check-badge',
            self::REJECTED  => 'heroicon-o-exclamation-triangle',
            self::FAILED    => 'heroicon-o-x-circle',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::DELIVERED,
            self::ACCEPTED,
            self::REJECTED,
            self::FAILED,
        ]);
    }

    public function isSuccess(): bool
    {
        return in_array($this, [
            self::DELIVERED,
            self::ACCEPTED,
        ]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
