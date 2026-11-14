<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FaqRequest;
use App\Http\Requests\UpdateFaqRequest;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use App\Support\ContentCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class FaqController extends Controller
{
    /** Ordered FAQ list with long-term caching (24 hours). */
    public function index(): JsonResponse
    {
        $faqs = Cache::remember('faqs:all', 86400, function () {
            return FaqResource::collection(Faq::ordered()->get())->resolve();
        });

        return response()->json(['data' => $faqs]);
    }

    /** Creates a FAQ. */
    public function store(FaqRequest $request): JsonResponse
    {
        $faq = Faq::create($request->validated());
        ContentCache::forgetFaqs();

        return response()->json(new FaqResource($faq), 201);
    }

    /** Updates a FAQ. */
    public function update(UpdateFaqRequest $request, Faq $faq): JsonResponse
    {
        $faq->update($request->validated());
        ContentCache::forgetFaqs();

        return response()->json(new FaqResource($faq));
    }

    /** Deletes a FAQ. */
    public function destroy(Faq $faq): Response
    {
        $faq->delete();
        ContentCache::forgetFaqs();

        return response()->noContent();
    }
}
