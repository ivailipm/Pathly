<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable();
        });

        // Milestones that are already done (they have tasks and all are done) get the time their last task changed
        $finished = DB::table('tasks')
            ->select('milestone_id')
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when status = 'done' then 1 else 0 end) as done")
            ->selectRaw('max(updated_at) as last_updated_at')
            ->groupBy('milestone_id')
            ->get()
            ->filter(fn (object $row) => (int) $row->total > 0 && (int) $row->done === (int) $row->total);

        foreach ($finished as $row) {
            DB::table('milestones')
                ->where('id', $row->milestone_id)
                ->update(['completed_at' => $row->last_updated_at]);
        }
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
