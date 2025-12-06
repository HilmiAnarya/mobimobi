<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    // Ikon di sidebar (User Group)
    protected static ?string $navigationIcon = 'heroicon-o-users';

    // Label di sidebar
    protected static ?string $navigationLabel = 'Data Pelanggan';

    // Urutan menu (biar paling atas di sidebar)
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas Pelanggan')
                    ->description('Pastikan NIK sesuai KTP untuk keperluan administrasi.')
                    ->schema([
                        Forms\Components\TextInput::make('nik')
                            ->label('NIK (KTP)')
                            ->required()
                            ->numeric() // Harus angka
                            ->length(16) // Wajib 16 digit
                            ->unique(ignoreRecord: true) // Gak boleh kembar, kecuali punya sendiri pas edit
                            ->columnSpanFull(), // Biar panjang sendiri barisnya

                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('phone')
                            ->label('Nomor HP/WA')
                            ->tel()
                            ->required()
                            ->maxLength(15),

                        Forms\Components\Textarea::make('address')
                            ->label('Alamat Domisili')
                            ->required()
                            ->columnSpanFull() // Memanjang ke samping
                            ->rows(3),
                    ])->columns(2), // Layout 2 kolom (Kiri: Nama, Kanan: HP)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nik')
                    ->label('NIK')
                    ->searchable() // Bisa dicari via search box
                    ->copyable() // Admin bisa klik buat copy NIK
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->weight('bold') // Biar tebal dikit
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon')
                    ->icon('heroicon-m-phone')
                    ->searchable(),

                Tables\Columns\TextColumn::make('address')
                    ->label('Alamat')
                    ->limit(30) // Potong kalau kepanjangan biar tabel rapi
                    ->tooltip(fn (Customer $record): string => $record->address), // Hover buat liat full

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Terdaftar Sejak')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Default ngumpet biar bersih
            ])
            ->filters([
                // Filter buat liat data yang dihapus (Soft Deletes)
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(), // Hapus permanen
                Tables\Actions\RestoreAction::make(), // Balikin data
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    // Penting buat Soft Deletes (Biar data sampah gak nampil di list utama)
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
