<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('zoom_meetings', function (Blueprint $table) {
        // 1. فحص ذكي لحقل الكورس: إذا كان موجوداً مسبقاً يتم تخطيه
        if (! Schema::hasColumn('zoom_meetings', 'course_id')) {
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
        }
        
        // 2. فحص ذكي لحقل المدة بالدقائق: لمنع التعارض مع الملف السابق
        if (! Schema::hasColumn('zoom_meetings', 'duration_minutes')) {
            $table->smallInteger('duration_minutes')->notNull()->default(60);
        }

        // 3. بناء حقول كود التحقق المؤقت (هذه ناقصة وسيتم بناؤها بنجاح الآن)
        if (! Schema::hasColumn('zoom_meetings', 'attendance_code')) {
            $table->string('attendance_code', 4)->nullable();
            $table->timestamp('attendance_code_expires_at')->nullable();
        }
    });

    Schema::table('zoom_attendances', function (Blueprint $table) {
        // 4. بناء حقول تأكيد حضور الطلاب في جدول الحضور والغياب
        if (! Schema::hasColumn('zoom_attendances', 'confirmed_at')) {
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmation_method')->nullable(); // manual, code
        }
    });
}


    public function down(): void
    {
        Schema::table('zoom_attendances', function (Blueprint $table) {
            $table->dropColumn(['confirmed_at', 'confirmation_method']);
        });

        Schema::table('zoom_meetings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_id');
            $table->dropColumn(['duration_minutes', 'attendance_code', 'attendance_code_expires_at']);
        });
    }
};
