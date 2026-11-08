{{-- resources/views/errors/503.blade.php --}}
@php
    header('Content-Type: application/json');
    echo json_encode([
        'status' => false,
        'message' => 'الموقع في وضع الصيانة حالياً، نعود إليكم قريباً.',
        'retry_after' => 3600 // عدد الثواني المتوقعة لانتهاء الصيانة
    ]);
    exit;
@endphp
