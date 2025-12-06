<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\Installment;
use App\Models\Car;
use Carbon\Carbon;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     * Logic: Saat tombol "Simpan" ditekan pertama kali.
     */
    public function created(Order $order): void
    {
        // 1. Ambil Mobil terkait
        $car = $order->car;

        if ($order->payment_type === 'cash') {
            // SKENARIO CASH: Lunas di tempat
            // Update Mobil -> Sold
            $car->update(['status' => 'sold']);

            // Pastikan status order completed (jika admin lupa set)
            if ($order->status !== 'completed') {
                $order->updateQuietly(['status' => 'completed']);
                // updateQuietly biar ga trigger event 'updated' lagi (looping prevention)
            }

        } else {
            // SKENARIO CREDIT: Booking dulu
            // Update Mobil -> Booked
            $car->update(['status' => 'booked']);
        }
    }

    /**
     * Handle the Order "updated" event.
     * Logic: Saat Admin ganti status (Approve/Reject) atau edit data.
     */
    public function updated(Order $order): void
    {
        // Cek apakah kolom 'status' berubah? (Biar ga jalan kalau cuma edit notes)
        if ($order->isDirty('status')) {

            $newStatus = $order->status;
            $car = $order->car;

            // CASE: APPROVAL (Pending -> Approved)
            if ($newStatus === 'approved') {
                // 1. Mobil Resmi Terjual
                $car->update(['status' => 'sold']);

                // 2. GENERATE CICILAN (The Ledger)
                // Cek dulu biar ga double generate (kalau admin klik approve 2x)
                if ($order->installments()->count() === 0) {
                    $this->generateInstallments($order);
                }
            }

            // CASE: REJECTION (Pending -> Rejected)
            elseif ($newStatus === 'rejected') {
                // Balikin Mobil ke Garasi (Ready)
                $car->update(['status' => 'ready']);
            }

            // CASE: CANCEL (Completed -> Cancelled/Rejected) - Jarang terjadi tapi perlu dijaga
            elseif ($newStatus === 'rejected' && $order->payment_type === 'cash') {
                $car->update(['status' => 'ready']);
            }
        }
    }

    /**
     * Handle the Order "deleted" event.
     * Logic: Kalau data transaksi dihapus (Soft Delete), stok balik.
     */
    public function deleted(Order $order): void
    {
        // Balikin status mobil jadi Ready lagi
        $order->car->update(['status' => 'ready']);

        // Note: Data cicilan otomatis kehapus karena SoftDeletes + Cascade di database
    }

    /**
     * Helper: Generator Tabel Cicilan
     */
    private function generateInstallments(Order $order): void
    {
        $package = $order->creditPackage;

        // Safety check
        if (!$package) return;

        $tenor = $package->tenor;
        $monthlyAmount = $order->monthly_installment;
        $startDate = Carbon::parse($order->date);

        for ($i = 1; $i <= $tenor; $i++) {
            Installment::create([
                'order_id' => $order->id,
                'installment_number' => $i,
                'due_date' => $startDate->copy()->addMonths($i), // Jatuh tempo bulan depan, dst
                'amount_due' => $monthlyAmount,
                'status' => 'unpaid',
            ]);
        }
    }
}
