<?php

namespace Modules\Invoices\Enums;

use Modules\Core\Contracts\LabeledEnum;

/**
 * CreditNoteType - RB-IMP-15
 *
 * Enum for credit note / cancellation types.
 *
 * Represents the different types of credit notes:
 * - CREDIT: Full or partial credit to customer
 * - CANCELLATION: Complete invoice cancellation
 * - CORRECTION: Corrected invoice (replacement)
 */
enum CreditNoteType: string implements LabeledEnum
{
    case CREDIT       = 'credit';
    case CANCELLATION = 'cancellation';
    case CORRECTION   = 'correction';

    public function label(): string
    {
        return match ($this) {
            self::CREDIT       => trans('ip.credit_note_type_credit'),
            self::CANCELLATION => trans('ip.credit_note_type_cancellation'),
            self::CORRECTION   => trans('ip.credit_note_type_correction'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CREDIT       => trans('ip.credit_note_type_credit_desc'),
            self::CANCELLATION => trans('ip.credit_note_type_cancellation_desc'),
            self::CORRECTION   => trans('ip.credit_note_type_correction_desc'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CREDIT       => 'warning',
            self::CANCELLATION => 'danger',
            self::CORRECTION   => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CREDIT       => 'heroicon-o-minus-circle',
            self::CANCELLATION => 'heroicon-o-x-circle',
            self::CORRECTION   => 'heroicon-o-arrow-path',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
