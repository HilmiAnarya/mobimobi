<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Installment;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    // Judul Tab
    protected static ?string $title = 'Jadwal Cicilan & Pembayaran';

    // Icon Tab
    protected static ?string $icon = 'heroicon-o-banknotes';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('installment_number')
                    ->required()
                    ->maxLength(255),
                // Kita biarin default aja, karena cicilan digenerate otomatis.
                // Jarang admin edit manual kecuali ada revisi fatal.
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('installment_number')
            ->columns([
                Tables\Columns\TextColumn::make('installment_number')
                    ->label('Cicilan Ke')
                    ->formatStateUsing(fn (string $state): string => "Bulan ke-{$state}")
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('Jatuh Tempo')
                    ->date('d M Y') // Format: 01 Jan 2024
                    ->sortable()
                    ->color(fn (Installment $record) =>
                        // Merah kalau telat & belum bayar
                        ($record->due_date < now() && $record->status === 'unpaid') ? 'danger' : 'gray'
                    ),

                Tables\Columns\TextColumn::make('amount_due')
                    ->label('Tagihan')
                    ->money('IDR'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'unpaid' => 'gray',   // Belum bayar (Abu)
                        'paid' => 'success',  // Lunas (Hijau)
                        'overdue' => 'danger', // Telat (Merah)
                    })
                    ->formatStateUsing(fn (string $state) => strtoupper($state)),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Dibayar Tgl')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('penalty_fee')
                    ->label('Denda')
                    ->money('IDR')
                    ->placeholder('-'),
            ])
            ->filters([
                // Filter status biar gampang cari yang nunggak
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'unpaid' => 'Belum Lunas',
                        'paid' => 'Lunas',
                        'overdue' => 'Jatuh Tempo',
                    ]),
            ])
            ->headerActions([
                // Gak perlu Create Action manual, kan udah auto generate
            ])
            ->actions([
                // --- ACTION 1: TOMBOL BAYAR CEPAT ---
                Tables\Actions\Action::make('bayar')
                    ->label('Bayar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation() // Muncul popup "Yakin?"
                    ->modalHeading('Konfirmasi Pembayaran')
                    ->modalDescription('Pastikan uang cash/transfer sudah diterima. Aksi ini tidak bisa dibatalkan.')
                    ->modalSubmitActionLabel('Ya, Terima Uang')
                    ->action(function (Installment $record) {
                        // Logic Update
                        $record->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                            'amount_paid' => $record->amount_due, // Asumsi bayar full
                        ]);

                        // Notif Sukses
                        Notification::make()
                            ->title('Pembayaran Berhasil')
                            ->success()
                            ->send();

                        // Note: Observer Order akan otomatis nge-cek
                        // kalau ini cicilan terakhir, Order jadi Completed.
                    })
                    ->visible(fn (Installment $record) => $record->status !== 'paid'), // Tombol ilang kalau udah lunas

                // --- ACTION 2: Edit Manual (Jaga-jaga) ---
                Tables\Actions\EditAction::make()
                    ->color('gray'),
            ])
            ->bulkActions([
                // Bulk action standar
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('due_date', 'asc'); // Urutkan dari cicilan pertama
    }
}
