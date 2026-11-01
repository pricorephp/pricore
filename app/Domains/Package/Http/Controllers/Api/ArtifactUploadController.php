<?php

namespace App\Domains\Package\Http\Controllers\Api;

use App\Domains\Package\Actions\PublishArtifactAction;
use App\Domains\Package\Contracts\Enums\ArtifactPublishResult;
use App\Domains\Package\Exceptions\ArtifactRejectedException;
use App\Domains\Package\Http\Requests\UploadArtifactRequest;
use App\Http\Controllers\Controller;
use App\Models\AccessToken;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class ArtifactUploadController extends Controller
{
    /**
     * Publishes a version of an existing uploaded package. Creating packages
     * is left to admins in the web app, so a leaked CI token can't introduce
     * a new name that shadows a public package for the whole organization.
     */
    public function __invoke(
        UploadArtifactRequest $request,
        Organization $organization,
        PublishArtifactAction $publishArtifactAction,
    ): JsonResponse {
        /** @var UploadedFile $archive */
        $archive = $request->file('archive');

        /** @var AccessToken $accessToken */
        $accessToken = $request->attributes->get('accessToken');

        try {
            $published = $publishArtifactAction->handle(
                organization: $organization,
                archivePath: $archive->getRealPath() ?: $archive->path(),
                version: $request->validated('version'),
                actor: $accessToken->user,
                accessToken: $accessToken,
            );
        } catch (ArtifactRejectedException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        $version = $published->version;

        return response()->json([
            'result' => $published->result->value,
            'package' => $published->package->name,
            'version' => $version->version,
            'version_normalized' => $version->normalized_version,
            'dist' => [
                'url' => $version->dist_url,
                'shasum' => $version->dist_shasum,
                'size' => $version->dist_size,
            ],
        ], $published->result === ArtifactPublishResult::Unchanged ? Response::HTTP_OK : Response::HTTP_CREATED);
    }
}
