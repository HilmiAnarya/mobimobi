<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CarResource\Pages;
use App\Models\Car;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CarResource extends Resource
{
    protected static ?string $model = Car::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck'; // Icon Truk/Mobil
    protected static ?string $navigationLabel = 'Stok Mobil';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Informasi Kendaraan')
                            ->schema([
                                Forms\Components\TextInput::make('model')
                                    ->label('Model Mobil')
                                    ->placeholder('Contoh: Toyota Avanza Veloz 2023')
                                    ->required()
                                    ->maxLength(100)
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('color')
                                    ->label('Warna')
                                    ->required()
                                    ->maxLength(50),

                                Forms\Components\TextInput::make('price')
                                    ->label('Harga OTR (Cash)')
                                    ->required()
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->maxValue(999999999999) // Max biar gak error database
                                    ->helperText('Input angka saja tanpa titik/koma.'),
                            ])->columns(2),

                        Forms\Components\Section::make('Identitas Fisik (Legalitas)')
                            ->description('Nomor Rangka & Mesin wajib unik.')
                            ->schema([
                                Forms\Components\TextInput::make('chassis_number')
                                    ->label('Nomor Rangka')
                                    ->required()
                                    ->unique(ignoreRecord: true) // Validasi Unik
                                    ->maxLength(50),

                                Forms\Components\TextInput::make('engine_number')
                                    ->label('Nomor Mesin')
                                    ->required()
                                    ->unique(ignoreRecord: true) // Validasi Unik
                                    ->maxLength(50),
                            ])->columns(2),
                    ])->columnSpan(2), // Bagian Kiri (Lebar 2/3)

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Status & Media')
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'ready' => 'Ready Stock',
                                        'booked' => 'Booked (Sedang Proses)',
                                        'sold' => 'Sold Out (Terjual)',
                                    ])
                                    ->default('ready')
                                    ->required()
                                    ->native(false), // Tampilan dropdown lebih modern

                                Forms\Components\FileUpload::make('image_path')
                                    ->label('Foto Unit')
                                    ->image() // Validasi harus gambar
                                    ->directory('cars') // Disimpan di storage/app/public/cars
                                    ->imageEditor() // Admin bisa crop/rotate gambar
                                    ->columnSpanFull(),
                            ]),
                    ])->columnSpan(1), // Bagian Kanan (Lebar 1/3)
            ])->columns(3); // Total Grid 3 Kolom
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Foto')
                    ->square(), // Tampilan kotak

                Tables\Columns\TextColumn::make('model')
                    ->label('Unit Mobil')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Car $record): string => "Warna: {$record->color}"), // Subtext warna

                Tables\Columns\TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR') // Format Rp otomatis (Rp 200.000.000)
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge() // Tampilan Badge Kapsul
                    ->color(fn (string $state): string => match ($state) {
                        'ready' => 'info',    // Biru
                        'booked' => 'warning', // Kuning
                        'sold' => 'danger',   // Merah (Sold out biasanya merah/abu)
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),

                Tables\Columns\TextColumn::make('chassis_number')
                    ->label('No. Rangka')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // Default ngumpet
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'ready' => 'Ready Stock',
                        'booked' => 'Booked',
                        'sold' => 'Sold',
                    ]),
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
            ])
            ->defaultSort('created_at', 'desc'); // Mobil baru masuk paling atas
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCars::route('/'),
            'create' => Pages\CreateCar::route('/create'),
            'edit' => Pages\EditCar::route('/{record}/edit'),
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
