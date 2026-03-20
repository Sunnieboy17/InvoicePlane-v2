<?php

return [
    #region CLIENTS MODULE
    'alert_no_client_assigned'         => 'Diesem Projekt ist kein Kunde zugeordnet.',
    'change_client'                    => 'Kunde ändern',
    'client'                           => 'Kunde',
    'client_access'                    => 'Kundenzugriff',
    'client_already_exists'            => 'Kunde existiert bereits!',
    'client_custom'                    => 'Kundenspezifisch',
    'client_form'                      => 'Kundenformular',
    'client_information'               => 'Kundeninformationen',
    'client_name'                      => 'Kundenname',
    'client_notes'                     => 'Kundennotizen',
    'client_surname'                   => 'Kundennachname',
    'client_surname_optional'          => 'Kundennachname (Optional)',
    'clients'                          => 'Kunden',
    'coc_number'                       => 'Handelsregister-Nummer',
    'company_name'                     => 'Kundenname',
    'contact_information'              => 'Kontaktinformationen',
    'customer_name'                    => 'Kundenname',
    'delete_client'                    => 'Kunde löschen',
    'delete_client_warning'            => 'Wenn Sie diesen Kunden löschen, werden auch alle zugehörigen Rechnungen, Angebote und Zahlungen gelöscht. Sind Sie sicher, dass Sie diesen Kunden dauerhaft löschen möchten?',
    'delete_user_client_warning'       => 'Sind Sie sicher, dass Sie diesen Kunden von diesem Benutzer entfernen möchten?',
    'enable_permissive_search_clients' => 'Erweiterte Suche aktivieren',
    'filter_clients'                   => 'Kunden filtern',
    'guest'                            => 'Gast',
    'guest_account_denied'             => 'Dieses Konto ist nicht konfiguriert. Bitte kontaktieren Sie den Systemadministrator.',
    'guest_read_only'                  => 'Gast (Nur Lesen)',
    'guest_url'                        => 'Gast-URL',
    'id_number'                        => 'Geschäfts-ID',
    'no_client'                        => 'Kein Kunde',
    'personal_information'             => 'Persönliche Informationen',
    'primary_contact'                  => 'Hauptansprechpartner',
    'recent_clients'                   => 'Letzte Kunden',
    'relation_number'                  => 'Kundennummer',
    'relation_required'                => 'Die Kundenbeziehung ist erforderlich.',
    'trading_name'                     => 'Firmenname',
    'unique_name'                      => 'Eindeutiger Name',
    'unique_name_helper'               => 'Eine URL-freundliche Version des Namens. Wird automatisch aus dem Firmen- oder Handelsnamen generiert.',
    'user_clients'                     => 'Benutzer-Kunden',
    #endregion

    #region GERMANY COMPLIANCE (DE §14 UStG)
    'service_date'            => 'Leistungsdatum',
    'service_period_start'   => 'Leistungszeitraum von',
    'service_period_end'     => 'Leistungszeitraum bis',
    'vat_id'                => 'USt-IdNr.',
    'vat_id_hint'           => 'Format: DE + 9 Ziffern (z.B. DE123456789)',
    'tax_number'            => 'Steuernummer',
    #endregion

    #region INVOICE VALIDATION (RB-IMP-06)
    'validation_customer_required'              => 'Ein Kunde ist erforderlich.',
    'validation_invoice_number_required'        => 'Eine Rechnungsnummer ist erforderlich.',
    'validation_invoice_date_required'          => 'Ein Rechnungsdatum ist erforderlich.',
    'validation_due_date_required'             => 'Ein Fälligkeitsdatum ist erforderlich.',
    'validation_due_date_before_invoice_date'  => 'Das Fälligkeitsdatum darf nicht vor dem Rechnungsdatum liegen.',
    'validation_invoice_items_required'         => 'Mindestens ein Rechnungsartikel ist erforderlich.',
    'validation_company_required'               => 'Firmeneinstellungen sind erforderlich.',
    'validation_company_name_required'          => 'Firmenname ist erforderlich.',
    'validation_customer_name_required'         => 'Kunden-Firmenname ist erforderlich.',
    'validation_cannot_finalize'               => 'Rechnung kann nicht abgeschlossen werden: Erforderliche Felder fehlen.',
    #endregion

    #region BANK DETAILS & PAYMENT TERMS (RB-IMP-08)
    'bank_details'           => 'Bankverbindung',
    'bank_name'             => 'Bankname',
    'account_holder'        => 'Kontoinhaber',
    'payment_terms'         => 'Zahlungsbedingungen',
    'payment_days'          => 'Zahlungsziel (Tage)',
    'payment_terms_text'    => 'Zahlungsbedingungstext',
    #endregion

    #region BUSINESS REFERENCE FIELDS (RB-IMP-09)
    'business_references'  => 'Geschäftsreferenzen',
    'buyer_reference'       => 'Käuferreferenz',
    'order_reference'       => 'Bestellreferenz',
    'project_reference'     => 'Projektreferenz',
    #endregion

    #region E-INVOICE STATUS UI (RB-IMP-13)
    'einvoice_status'                      => 'E-Rechnung Status',
    'einvoice_status_not_ready'            => 'Nicht bereit',
    'einvoice_status_partially_ready'       => 'Teilweise bereit',
    'einvoice_status_export_ready'         => 'Export bereit',
    'einvoice_status_export_failed'        => 'Export fehlgeschlagen',
    'einvoice_status_export_failed_hint'   => 'Letzter Exportunternehmen fehlgeschlagen. Bitte überprüfen Sie die Fehler und versuchen Sie es erneut.',
    'einvoice_export'                      => 'E-Rechnung exportieren',
    'einvoice_export_format'              => 'Exportformat',
    'einvoice_export_error'               => 'Export fehlgeschlagen',
    'einvoice_error_no_company'           => 'Firmendaten sind erforderlich.',
    'einvoice_error_company_vat'          => 'Firmen-USt-IdNr. ist erforderlich.',
    'einvoice_error_company_name'         => 'Firmenname ist erforderlich.',
    'einvoice_error_no_customer'          => 'Kundendaten sind erforderlich.',
    'einvoice_error_customer_name'        => 'Kunden-Firmenname ist erforderlich.',
    'einvoice_warning_customer_vat'        => 'Kunden-USt-IdNr. wird für B2B-Rechnungen empfohlen.',
    'einvoice_warning_peppol_id'          => 'Peppol-ID wird für automatische Weiterleitung empfohlen.',
    'einvoice_warning_iban'                => 'IBAN wird für SEPA-Zahlungen empfohlen.',
    'einvoice_missing_fields'              => 'Fehlende Pflichtfelder',
    'einvoice_recommended_fields'         => 'Empfohlene Felder',
    #endregion
];
