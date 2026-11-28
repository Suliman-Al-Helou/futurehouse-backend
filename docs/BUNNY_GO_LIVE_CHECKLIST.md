# Bunny.net Stream — Go-Live Checklist (Launch Gate)

اتبع هذه الخطوات الخمس بالترتيب يوم الإطلاق الفعلي قبل فتح استقبال المدفوعات:

## 1. إعدادات الأمان داخل لوحة Bunny.net Stream
1. افتح مكتبة الفيديو في **Bunny Dashboard → Stream → Library → Security**.
2. فعّل **CDN Token Authentication** (التوقيع عبر `token_path`).
3. **لا تُفعّل** خيار IP Validation (لضمان عدم انقطاع البث على شبكات الموبايل 4G/5G وSSR).
4. انسخ القيم التالية من لوحة التحكم:
   - `Video Library ID` و `API Key` و `CDN Hostname` من **Stream → Library → API**.
   - `Token Authentication Key` من **Stream → Library → Security → Token Authentication**.

## 2. تعبئة مفاتيح البيئة (`.env`)
أضف القيم المنسوخة في ملف `.env` على سيرفر الإنتاج وتأكد من ضبط:
```env
APP_ENV=production
VIDEO_DEFAULT_PROVIDER=hls
BUNNY_STREAM_LIBRARY_ID=...
BUNNY_STREAM_API_KEY=...
BUNNY_STREAM_TOKEN_KEY=...
BUNNY_STREAM_HOSTNAME=vz-xxxxxxxx-xxx.b-cdn.net
```
ثم نفّذ:
```bash
php artisan config:clear
```

## 3. ترحيل الدروس المدفوعة (`videos:import-hls`)
1. انسخ القالب إلى `mapping.json`:
   ```bash
   cp mapping.example.json mapping.json
   ```
2. استبدل كل خانة `"REPLACE_WITH_BUNNY_GUID"` بمعرّف الفيديو الفعلي (`GUID`) من Bunny Stream.
3. شغّل أمر الترحيل:
   ```bash
   php artisan videos:import-hls --map=mapping.json
   ```

## 4. تشغيل بوابة التدقيق (`videos:audit-providers`)
تحقق من جاهزية جميع الدروس المنشورة المدفوعة:
```bash
php artisan videos:audit-providers
```
- **الشرط الإلزامي:** يجب أن ينتهي الأمر بـ **Exit Code 0** (`Launch Gate Audit PASSED.`).

## 5. تشغيل اختبار التحمل (`hls-launch-gate.k6.js`)
استبدل رابط السيرفر الفعلي، ومعرّف درس HLS حقيقي، وتوكن طالب مسجل:
```bash
k6 run \
  -e API_BASE_URL="https://api.yourdomain.com" \
  -e LESSON_ID="<hls_lesson_id>" \
  -e STUDENT_TOKEN="<sanctum_student_token>" \
  tests/load/hls-launch-gate.k6.js
```
- **الشرط الإلزامي:** نجاح `checks > 99%` ورجوع `403 Forbidden` للروابط غير الموقعة من Bunny CDN Edge.
