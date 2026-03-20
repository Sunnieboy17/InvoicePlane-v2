<?php

namespace Modules\Core\Filament\Admin\Resources\Companies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        /*
         * Logo, quote_template, invoice_template
         */
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Section::make(trans('ip.basic'))
                            ->columnSpan(1)
                            ->columns(1)
                            ->schema([
                                TextInput::make('name')
                                    ->label(trans('ip.name'))
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug((string) $state, '_'))),

                                TextInput::make('slug')
                                    ->label(trans('ip.slug'))
                                    ->required()
                                    ->readOnly()
                                    ->dehydrated(),
                            ]),

                        //
                        // ─── RIGHT COLUMN (3/4 width) ────────────────────────────────
                        //
                        Section::make(trans('ip.details'))
                            ->columnSpan(1)   // 3/4 of the total width
                            ->columns(2)      // two‐columns inside
                            ->schema([
                                TextInput::make('search_code')
                                    ->label(trans('ip.search_code'))
                                    ->required(),
                                TextInput::make('vat_number')
                                    ->label(trans('ip.vat_id'))
                                    ->hint(trans('ip.vat_id_hint'))
                                    ->nullable()
                                    ->rule(new \Modules\Core\Rules\GermanVatId()),

                                TextInput::make('id_number')
                                    ->label(trans('ip.id_number'))
                                    ->nullable(),

                                TextInput::make('coc_number')
                                    ->label(trans('ip.coc_number'))
                                    ->nullable(),
                            ]),

                        // ─── BANK DETAILS (RB-IMP-08) ─────────────────────────────────
                        Section::make(trans('ip.bank_details'))
                            ->columns(2)
                            ->schema([
                                TextInput::make('iban')
                                    ->label('IBAN')
                                    ->nullable()
                                    ->maxLength(34),
                                TextInput::make('bic')
                                    ->label('BIC/SWIFT')
                                    ->nullable()
                                    ->maxLength(11),
                                TextInput::make('bank_name')
                                    ->label(trans('ip.bank_name'))
                                    ->nullable(),
                                TextInput::make('account_holder')
                                    ->label(trans('ip.account_holder'))
                                    ->nullable(),
                            ]),

                        // ─── PAYMENT TERMS (RB-IMP-08) ────────────────────────────────
                        Section::make(trans('ip.payment_terms'))
                            ->columns(1)
                            ->schema([
                                TextInput::make('default_payment_terms')
                                    ->label(trans('ip.payment_days'))
                                    ->numeric()
                                    ->suffix('Tage')
                                    ->nullable()
                                    ->default(30),
                                TextInput::make('default_payment_terms_text')
                                    ->label(trans('ip.payment_terms_text'))
                                    ->nullable()
                                    ->maxLength(500),
                            ]),
                    ]),
            ]);
    }
}
