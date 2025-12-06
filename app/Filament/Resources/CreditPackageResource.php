<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreditPackageResource\Pages;
use App\Models\CreditPackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CreditPackageResource extends Resource
{
    protected static ?string $model = CreditPackage::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator'; // Icon Kalkulator
    protected static ?string $navigationLabel = 'Paket Kredit';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas Paket')
                    ->description('Kode paket untuk memudahkan pemilihan saat transaksi.')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Kode Paket')
                            ->placeholder('Contoh: TENOR-12, PROMO-LEBARAN')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Rumus Perhitungan (Rules)')
                    ->description('Angka ini akan menentukan besarnya cicilan customer.')
                    ->schema([
                        Forms\Components\TextInput::make('tenor')
                            ->label('Tenor (Jangka Waktu)')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->suffix('Bulan') // UX: Biar jelas satuannya
                            ->helperText('Berapa kali customer harus membayar cicilan.'),

                        Forms\Components\TextInput::make('interest_rate')
                            ->label('Bunga (Flat)')
                            ->required()
                            ->numeric()
                            ->suffix('%') // UX: Satuan Persen
                            ->minValue(0)
                            ->step(0.01) // Bisa input koma, misal 2.5%
                            ->helperText('Total persentase bunga selama tenor berlangsung.'),

                        Forms\Components\TextInput::make('down_payment_rule')
                            ->label('Minimal DP')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->helperText('Batas minimum uang muka agar paket ini bisa dipilih.'),
                    ])->columns(3), // Grid 3 kolom biar rapi sebaris
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode Paket')
                    ->weight('bold')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('tenor')
                    ->label('Tenor')
                    ->formatStateUsing(fn (string $state): string => "$state Bulan") // Format: "12 Bulan"
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('interest_rate')
                    ->label('Bunga')
                    ->suffix('%')
                    ->sortable()
                    ->alignCenter()
                    ->color(fn (string $state): string => $state > 0 ? 'danger' : 'success'), // Merah kalau ada bunga, Hijau kalau 0%

                Tables\Columns\TextColumn::make('down_payment_rule')
                    ->label('Syarat Min. DP')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCreditPackages::route('/'),
            'create' => Pages\CreateCreditPackage::route('/create'),
            'edit' => Pages\EditCreditPackage::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
