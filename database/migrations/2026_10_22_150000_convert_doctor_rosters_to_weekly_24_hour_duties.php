<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $doctorIds = DB::table('users')->where('role', 'doctor')->pluck('id');
        if ($doctorIds->isEmpty()) {
            return;
        }

        $weeklyDuties = DB::table('staff_duties')
            ->whereIn('user_id', $doctorIds)
            ->whereDate('duty_date', '>=', now()->toDateString())
            ->orderBy('duty_date')
            ->orderBy('id')
            ->get()
            ->groupBy(function ($duty) {
                $weekStart = Carbon::parse($duty->duty_date)->startOfWeek(Carbon::MONDAY)->toDateString();

                return $duty->user_id.':'.$weekStart;
            });

        foreach ($weeklyDuties as $duties) {
            $primary = $duties->first();
            DB::table('staff_duties')->where('id', $primary->id)->update([
                'shift' => '24_hour',
                'updated_at' => now(),
            ]);

            $redundantIds = $duties->skip(1)->pluck('id');
            if ($redundantIds->isNotEmpty()) {
                DB::table('staff_duties')->whereIn('id', $redundantIds)->delete();
            }
        }
    }

    public function down(): void
    {
        DB::table('staff_duties')->where('shift', '24_hour')->update([
            'shift' => 'morning',
            'updated_at' => now(),
        ]);
    }
};
