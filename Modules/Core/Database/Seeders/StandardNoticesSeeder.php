<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Company;
use Modules\Core\Models\StandardNotice;

/**
 * Seeder für standardisierte Hinweistexte (z.B. für deutsche Rechnungslegalität).
 * 
 * Diese Hinweistexte werden automatisch in Rechnungen eingefügt basierend auf:
 * - Steuersatz (z.B. Kleinunternehmer)
 * - Kundenland (z.B. Reverse Charge bei B2B EU)
 * - Rechnungsart (z.B. Export)
 */
class StandardNoticesSeeder extends AbstractSeeder
{
    protected array $germanNotices = [
        // =====================================================
        // KLEINUNTERNEHMERREGELUNG (§19 UStG)
        // =====================================================
        [
            'code'        => 'DE-KLEIN-001',
            'title'       => 'Kleinunternehmer (§19 UStG)',
            'body'        => 'Gemäß §19 UStG wird keine Umsatzsteuer ausgewiesen.',
            'type'        => 'tax_notice',
            'trigger'     => 'tax_code:DE-KLEIN-0',
            'is_active'   => true,
        ],

        // =====================================================
        // REVERSE CHARGE (§13b UStG) - 19% Regelbesteuerung
        // =====================================================
        [
            'code'        => 'DE-RC-STD-19',
            'title'       => 'Reverse Charge 19% (§13b UStG)',
            'body'        => 'Steuerschuldnerschaft des Leistungsempfängers (§13b Abs. 2 Nr. 1 UStG). '
                            . 'Die USt wird auf Ihre Rechnung nicht ausgewiesen, da die Steuerschuld nach §13b UStG auf den Leistungsempfänger übergeht.',
            'type'        => 'tax_notice',
            'trigger'     => 'rc:yes,tax_code:DE-VAT-STD-19',
            'is_active'   => true,
        ],

        // =====================================================
        // REVERSE CHARGE (§13b UStG) - 7% Ermäßigte Steuer
        // =====================================================
        [
            'code'        => 'DE-RC-RED-7',
            'title'       => 'Reverse Charge 7% (§13b UStG)',
            'body'        => 'Steuerschuldnerschaft des Leistungsempfängers (§13b Abs. 2 Nr. 1 UStG i.V.m. §12 Abs. 2 UStG). '
                            . 'Die USt wird auf Ihre Rechnung nicht ausgewiesen, da die Steuerschuld nach §13b UStG auf den Leistungsempfänger übergeht.',
            'type'        => 'tax_notice',
            'trigger'     => 'rc:yes,tax_code:DE-VAT-RED-7',
            'is_active'   => true,
        ],

        // =====================================================
        // INNERGEMEINSCHAFTLICHE LIEFERUNG (0%)
        // =====================================================
        [
            'code'        => 'DE-EU-ZERO-001',
            'title'       => 'Innergemeinschaftliche Lieferung',
            'body'        => 'Steuerschuldnerschaft des Leistungsempfängers (§13b Abs. 1 UStG - Innergemeinschaftliche Lieferung). '
                            . 'Die Umsatzsteuer wird gemäß §13b UStG auf Ihre Rechnung nicht ausgewiesen.',
            'type'        => 'tax_notice',
            'trigger'     => 'tax_code:EU-VAT-ZERO,country:EU',
            'is_active'   => true,
        ],

        // =====================================================
        // EXPORT (Nicht-EU)
        // =====================================================
        [
            'code'        => 'DE-EXPORT-001',
            'title'       => 'Export-Lieferung',
            'body'        => 'Steuerfreie Ausfuhrlieferung gemäß §4 Nr. 1 UStG i.V.m. §6 UStG. '
                            . 'Nachweis der Ausfuhr liegt vor.',
            'type'        => 'tax_notice',
            'trigger'     => 'tax_code:EXPORT-0',
            'is_active'   => true,
        ],
    ];

    protected array $generalNotices = [
        // Zahlungsbedingungen
        [
            'code'        => 'GEN-PAY-001',
            'title'       => 'Zahlungsbedingungen Standard',
            'body'        => 'Zahlbar innerhalb von 30 Tagen nach Rechnungsdatum ohne Abzug.',
            'type'        => 'payment_notice',
            'trigger'     => null,
            'is_active'   => true,
        ],
        // Mahnhinweis
        [
            'code'        => 'GEN-RMK-001',
            'title'       => 'Mahnhinweis',
            'body'        => 'Bei Zahlungsverzug werden Mahngebühren in Höhe von € 5,00 je Mahnung sowie Verzugszinsen in Höhe von 9 Prozentpunkten über dem Basiszinssatz erhoben.',
            'type'        => 'reminder_notice',
            'trigger'     => null,
            'is_active'   => false, // Nur bei Bedarf aktivieren
        ],
    ];

    public function buildOne(?int $companyId = null): void
    {
        $query = Company::query();

        if ($companyId) {
            $query->where('id', $companyId);
        }

        $query->each(function (Company $company) {
            Log::info("Seeding standard notices for company: {$company->name}");

            $noticesToUpsert = [];

            // Deutsche Hinweistexte
            foreach ($this->germanNotices as $notice) {
                $noticesToUpsert[] = [
                    'company_id'  => $company->id,
                    'code'        => $notice['code'],
                    'title'       => $notice['title'],
                    'body'        => $notice['body'],
                    'type'        => $notice['type'],
                    'trigger'     => $notice['trigger'],
                    'is_active'   => $notice['is_active'],
                ];
            }

            // Generische Hinweistexte
foreach ($this->generalNotices as $notice) {
                $noticesToUpsert[] = [
                    'company_id'  => $company->id,
                    'code'        => $notice['code'],
                    'title'       => $notice['title'],
                    'body'        => $notice['body'],
                    'type'        => $notice['type'],
                    'trigger'     => $notice['trigger'],
                    'is_active'   => $notice['is_active'],
                ];
            }

            $existingCount = StandardNotice::query()->where('company_id', $company->id)->count();

            StandardNotice::upsert(
                $noticesToUpsert,
                ['company_id', 'code'],
                ['title', 'body', 'type', 'trigger', 'is_active']
            );

            $totalCount   = count($noticesToUpsert);
            $createdCount = $totalCount - $existingCount;

            Log::info(sprintf(
                'Standard notices for %s: %d created/updated, %d already existed',
                $company->name,
                $createdCount,
                $existingCount
            ));
        });
    }
}
