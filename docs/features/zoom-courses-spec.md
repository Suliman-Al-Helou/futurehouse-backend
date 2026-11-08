# 📋 Feature Specification: Zoom Meetings & Courses Integration

## 1.Intent & Business Value

- create link zoom , after and the meeting downlad it , then upload it to the course as a video link , uploade it form the platform 
to privet youtube chanel , add the list of meet zoom form youtube to corse form to added. 

## 2. Current Baseline
- **Backend (Laravel 13):**
 - zoom_meetings : table have colum `course_id`, `zoom_link`, `starts_at`, `duration_minutes`.


## 3. التعديلات المطلوبة (Desired Delta)
- [ ] ما الذي نريد إضافته أو حذفه في واجهة الطالب؟
- [ ] ما الذي نريد تعديله في لوحة الأدمن؟
- [ ] ما هي التغييرات المطلوبة في تدفق تسجيل الحضور (Attendance Flow)؟

## 4. القواعد الثابتة التي يُمنع كسرها (Invariants & Rules)
- (مثال: لا يجوز لطالب غير مسجل في الكورس الانضمام للقاء).
- (مثال: كود الحضور تنتهي صلاحيته بعد 10 دقائق من إنشائه).33