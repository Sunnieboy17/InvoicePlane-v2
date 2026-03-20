<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Database\Factories\StandardNoticeFactory;
use Modules\Core\Traits\BelongsToCompany;

/**
 * Standardisierte Hinweistexte für Rechnungen.
 * 
 * @property int         $id
 * @property int         $company_id
 * @property string      $code
 * @property string|null $title
 * @property string      $body
 * @property string|null $type
 * @property string|null $trigger
 * @property bool        $is_active
 * @property Company     $company
 */
class StandardNotice extends Model
{
    use BelongsToCompany;
    use HasFactory;

    public $timestamps = false;

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $guarded = [];

    /**
     * Hinweistypen
     */
    public const TYPE_TAX_NOTICE = 'tax_notice';
    public const TYPE_PAYMENT_NOTICE = 'payment_notice';
    public const TYPE_REMINDER_NOTICE = 'reminder_notice';
    public const TYPE_LEGAL_NOTICE = 'legal_notice';
    public const TYPE_GENERAL_NOTICE = 'general_notice';

    /**
     * Findet aktive Hinweise basierend auf Trigger.
     * 
     * @param string|null $taxCode Steuersatz-Code (z.B. 'DE-KLEIN-0')
     * @param string|null $country Ländercode (z.B. 'DE', 'FR')
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function findByTrigger(?string $taxCode = null, ?string $country = null)
    {
        return static::query()
            ->where('is_active', true)
            ->get()
            ->filter(function (StandardNotice $notice) use ($taxCode, $country) {
                if (empty($notice->trigger)) {
                    return false; // Nur automatisch ausgelöste Hinweise
                }

                $trigger = $notice->trigger;

                // Prüfe Steuersatz-Trigger
                if ($taxCode && str_contains($trigger, 'tax_code:')) {
                    preg_match('/tax_code:([^,]+)/', $trigger, $matches);
                    if (isset($matches[1])) {
                        $codes = explode('|', $matches[1]);
                        if (!in_array($taxCode, $codes)) {
                            return false;
                        }
                    }
                }

                // Prüfe Länder-Trigger
                if ($country && str_contains($trigger, 'country:')) {
                    preg_match('/country:([^,]+)/', $trigger, $matches);
                    if (isset($matches[1])) {
                        $countries = explode('|', $matches[1]);
                        if (!in_array($country, $countries) && !in_array('EU', $countries)) {
                            return false;
                        }
                        // EU umfasst auch Deutschland
                        if (in_array('EU', $countries) && $country === 'DE') {
                            return true;
                        }
                    }
                }

                return true;
            });
    }

    /**
     * Factory
     */
    protected static function newFactory(): Factory
    {
        return StandardNoticeFactory::new();
    }
}
