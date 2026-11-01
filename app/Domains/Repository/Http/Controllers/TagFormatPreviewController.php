<?php

namespace App\Domains\Repository\Http\Controllers;

use App\Domains\Repository\Actions\BuildTagFormatPreviewAction;
use App\Domains\Repository\Services\GitProviders\GitProviderFactory;
use App\Domains\Repository\Services\TagFormats\TagFormat;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Repository;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class TagFormatPreviewController extends Controller
{
    use AuthorizesRequests;

    protected const TAG_LIMIT = 20;

    public function __invoke(Request $request, Organization $organization, Repository $repository, BuildTagFormatPreviewAction $buildTagFormatPreviewAction): JsonResponse
    {
        if ($repository->organization_uuid !== $organization->uuid) {
            abort(404);
        }

        $this->authorize('deleteRepository', $organization);

        $template = $request->query('tag_format');

        if (! is_string($template) || ! TagFormat::isValid($template)) {
            return response()->json(['error' => 'Invalid tag format.'], 422);
        }

        try {
            // Typing in the settings form asks again on every change; one provider call serves them all
            $tags = Cache::remember(
                "repository:{$repository->uuid}:tag-names",
                now()->addMinutes(5),
                fn () => array_column(GitProviderFactory::make($repository)->getTags(), 'name'),
            );
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'The tags could not be loaded from the Git provider.'], 502);
        }

        rsort($tags, SORT_NATURAL);

        return response()->json([
            'tags' => $buildTagFormatPreviewAction->handle(
                $repository,
                TagFormat::fromTemplate($template),
                array_slice($tags, 0, self::TAG_LIMIT),
            ),
        ]);
    }
}
