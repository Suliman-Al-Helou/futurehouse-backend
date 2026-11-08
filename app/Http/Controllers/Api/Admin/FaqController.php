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
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class FaqController extends Controller
{
    /** Ordered FAQ list. */
    public function index(): AnonymousResourceCollection
    {
        return FaqResource::collection(Faq::ordered()->get());
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
