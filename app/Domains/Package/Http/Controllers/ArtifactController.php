<?php

namespace App\Domains\Package\Http\Controllers;

use App\Domains\Package\Actions\PublishArtifactAction;
use App\Domains\Package\Contracts\Data\PublishedArtifactData;
use App\Domains\Package\Exceptions\ArtifactRejectedException;
use App\Domains\Package\Http\Requests\UploadArtifactRequest;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ArtifactController extends Controller
{
    public function __construct(
        protected PublishArtifactAction $publishArtifactAction,
    ) {}

    public function store(UploadArtifactRequest $request, Organization $organization): RedirectResponse
    {
        $published = $this->publish($request, $organization, allowCreatingPackage: true);

        return redirect()
            ->route('organizations.packages.show', [$organization, $published->package])
            ->with('status', $this->statusMessage($published));
    }

    public function storeVersion(UploadArtifactRequest $request, Organization $organization, Package $package): RedirectResponse
    {
        if ($package->organization_uuid !== $organization->uuid) {
            abort(404);
        }

        $published = $this->publish($request, $organization, package: $package);

        return redirect()
            ->route('organizations.packages.show', [$organization, $package])
            ->with('status', $this->statusMessage($published));
    }

    protected function publish(
        UploadArtifactRequest $request,
        Organization $organization,
        ?Package $package = null,
        bool $allowCreatingPackage = false,
    ): PublishedArtifactData {
        /** @var UploadedFile $archive */
        $archive = $request->file('archive');

        try {
            return $this->publishArtifactAction->handle(
                organization: $organization,
                archivePath: $archive->getRealPath() ?: $archive->path(),
                version: $request->validated('version'),
                package: $package,
                allowCreatingPackage: $allowCreatingPackage,
                actor: $request->user(),
            );
        } catch (ArtifactRejectedException $e) {
            throw ValidationException::withMessages(['archive' => $e->getMessage()]);
        }
    }

    protected function statusMessage(PublishedArtifactData $published): string
    {
        $version = $published->version->version;

        return match (true) {
            $published->result->isUnchanged() => "Version {$version} was already published with this archive.",
            $published->packageCreated => "Package created with version {$version}.",
            default => "Version {$version} published.",
        };
    }
}
