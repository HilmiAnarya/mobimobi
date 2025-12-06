<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Car;
use App\Models\CreditPackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Transaksi Penjualan';
    protected static ?int $navigationSort = 4; // Paling bawah (setelah master data)

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // --- SECTION 1: DATA UTAMA ---
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Data Pesanan')
                            ->schema([
                                // Pilih Customer
                                Forms\Components\Select::make('customer_id')
                                    ->relationship('customer', 'name')
                                    ->label('Pelanggan')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([ // Fitur Quick Create Customer (Biar ga pindah halaman)
                                        Forms\Components\TextInput::make('name')->required(),
                                        Forms\Components\TextInput::make('nik')->required(),
                                        Forms\Components\TextInput::make('phone')->required(),
                                        Forms\Components\Textarea::make('address')->required(),
                                    ]),

                                // Pilih Mobil (STOCK GUARD LOGIC)
                                Forms\Components\Select::make('car_id')
                                    ->label('Unit Mobil')
                                    ->options(function () {
                                        // Hanya ambil mobil yang statusnya 'ready'
                                        return Car::where('status', 'ready')
                                            ->pluck('model', 'id');
                                    })
                                    ->searchable()
                                    ->required()
                                    ->live() // Trigger reaktivitas
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        // SNAPSHOT PRICE LOGIC
                                        $car = Car::find($state);
                                        if ($car) {
                                            $set('total_price', $car->price);
                                            // Reset hitungan kredit kalau ganti mobil
                                            $set('down_payment', 0);
                                            self::calculateCredit($get, $set);
                                        }
                                    }),

                                Forms\Components\DatePicker::make('date')
                                    ->label('Tanggal Transaksi')
                                    ->default(now())
                                    ->required(),

                                Forms\Components\TextInput::make('order_number')
                                    ->label('No. Invoice')
                                    ->placeholder('Auto Generated')
                                    ->readOnly()
                                    ->dehydrated(false), // Gak perlu disave manual, Model yang handle
                            ])->columns(2),

                        Forms\Components\Section::make('Pembayaran')
                            ->schema([
                                // Total Harga (Readonly hasil snapshot)
                                Forms\Components\TextInput::make('total_price')
                                    ->label('Harga OTR')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->readOnly() // Admin gaboleh edit manual
                                    ->required()
                                    ->dehydrated(), // Tetap kirim ke database

                                // Cash / Credit Switcher
                                Forms\Components\Select::make('payment_type')
                                    ->label('Metode Pembayaran')
                                    ->options([
                                        'cash' => 'Tunai (Cash Keras)',
                                        'credit' => 'Kredit (Cicilan)',
                                    ])
                                    ->required()
                                    ->default('cash')
                                    ->live(), // Trigger show/hide form kredit
                            ]),
                    ])->columnSpan(2),

                // --- SECTION 2: KALKULATOR KREDIT (Conditional) ---
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Simulasi Kredit')
                            ->description('Otomatis menghitung bunga & angsuran.')
                            ->schema([
                                // Pilih Paket
                                Forms\Components\Select::make('credit_package_id')
                                    ->label('Paket Kredit')
                                    ->options(CreditPackage::all()->mapWithKeys(function ($item) {
                                        return [$item->id => "{$item->code} ({$item->tenor} Bulan)"];
                                    }))
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::calculateCredit($get, $set)),

                                // Input DP
                                Forms\Components\TextInput::make('down_payment')
                                    ->label('Uang Muka (DP)')
                                    ->prefix('Rp')
                                    ->numeric()
                                    ->default(0)
                                    ->live(onBlur: true) // Tunggu admin selesai ngetik baru hitung
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::calculateCredit($get, $set)),

                                // Hasil Hitungan (Readonly)
                                Forms\Components\TextInput::make('interest_amount')
                                    ->label('Total Bunga (Rp)')
                                    ->readOnly()
                                    ->prefix('Rp')
                                    ->dehydrated(),

                                Forms\Components\TextInput::make('monthly_installment')
                                    ->label('Angsuran / Bulan')
                                    ->readOnly()
                                    ->prefix('Rp')
                                    ->dehydrated()
                                    ->helperText('Sudah termasuk bunga.'),
                            ])
                            ->visible(fn (Get $get) => $get('payment_type') === 'credit'), // Hide kalau Cash

                        Forms\Components\Section::make('Status & Catatan')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'pending' => 'Pending (Menunggu Approval)',
                                        'approved' => 'Approved (Disetujui)',
                                        'rejected' => 'Rejected (Ditolak)',
                                        'completed' => 'Completed (Selesai/Lunas)',
                                    ])
                                    ->default('pending')
                                    ->required(),

                                Forms\Components\Textarea::make('notes')
                                    ->label('Catatan Tambahan')
                                    ->rows(3),
                            ]),
                    ])->columnSpan(1),
            ])->columns(3);
    }

    // --- THE BRAIN: Logic Hitung Kredit ---
    public static function calculateCredit(Get $get, Set $set): void
    {
        $price = (float) $get('total_price'); // Harga Mobil
        $dp = (float) $get('down_payment');   // DP Inputan
        $packageId = $get('credit_package_id');

        // Kalau data belum lengkap, stop.
        if (!$packageId || $price <= 0) {
            return;
        }

        $package = CreditPackage::find($packageId);
        if (!$package) return;

        // 1. Hitung Pokok Hutang
        $principal = $price - $dp;

        // 2. Hitung Bunga (Principal * Rate / 100)
        // Asumsi: Rate di database adalah total bunga selama tenor (Flat)
        $bungaRupiah = $principal * ($package->interest_rate / 100);

        // 3. Hitung Total yang harus dibayar & Cicilan Bulanan
        $totalLoan = $principal + $bungaRupiah;
        $monthly = $totalLoan / $package->tenor;

        // 4. Set Nilai ke Form
        $set('interest_amount', round($bungaRupiah));
        $set('monthly_installment', round($monthly));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Invoice')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Pelanggan')
                    ->searchable(),

                Tables\Columns\TextColumn::make('car.model')
                    ->label('Unit Mobil')
                    ->limit(20),

                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y')
                    ->label('Tanggal'),

                Tables\Columns\TextColumn::make('total_price')
                    ->label('Total')
                    ->money('IDR'),

                Tables\Columns\TextColumn::make('payment_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'success',
                        'credit' => 'warning',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'approved' => 'info',
                        'completed' => 'success',
                        'rejected' => 'danger',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status'),
                Tables\Filters\SelectFilter::make('payment_type'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(), // Soft Delete
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            // Panggil Class Relation Manager disini
            OrderResource\RelationManagers\InstallmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'), // Wajib ada view buat approval
            'edit' => Pages\EditOrder::route('/{record}/edit'),
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
