<?php

namespace App\Domains\Organization\Actions;

use App\Models\Organization;
use App\Models\OrganizationSshKey;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class GenerateOrganizationSshKeyAction
{
    public function handle(Organization $organization, string $name): OrganizationSshKey
    {
        $tempPath = sys_get_temp_dir().'/pricore-sshkeygen-'.Str::random(16);

        try {
            $result = Process::run([
                'ssh-keygen', '-t', 'ed25519',
                '-f', $tempPath,
                '-N', '',
                '-C', "pricore:{$organization->slug}",
            ]);

            if ($result->failed()) {
                throw new RuntimeException('Failed to generate SSH key: '.$result->errorOutput());
            }

            $privateKey = file_get_contents($tempPath);
            $publicKey = file_get_contents($tempPath.'.pub');

            if ($privateKey === false || $publicKey === false) {
                throw new RuntimeException('Failed to read generated SSH key files');
            }

            $fingerprint = $this->extractFingerprint($tempPath.'.pub');

            return OrganizationSshKey::create([
                'organization_uuid' => $organization->uuid,
                'name' => $name,
                'public_key' => trim($publicKey),
                'private_key' => trim($privateKey),
                'fingerprint' => $fingerprint,
            ]);
        } finally {
            @unlink($tempPath);
            @unlink($tempPath.'.pub');
        }
    }

    protected function extractFingerprint(string $publicKeyPath): string
    {
        $result = Process::run(['ssh-keygen', '-lf', $publicKeyPath]);

        if ($result->failed()) {
            return '';
        }

        // Output format: "256 SHA256:xxxxx comment (ED25519)"
        $output = trim($result->output());
        if (preg_match('/SHA256:\S+/', $output, $matches)) {
            return $matches[0];
        }

        return '';
    }
}
