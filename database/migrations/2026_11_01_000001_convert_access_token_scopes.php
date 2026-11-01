<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert the read/write scopes to the scopes of the REST API. Tokens without
     * stored scopes could only install packages, so they keep exactly that.
     */
    public function up(): void
    {
        $this->convert(['read' => 'composer', 'write' => 'write:packages'], default: ['composer']);
    }

    public function down(): void
    {
        $this->convert(['composer' => 'read', 'write:packages' => 'write'], default: null);
    }

    /**
     * Map known scopes and drop the rest; tokens left without any get $default.
     *
     * @param  array<string, string>  $map
     * @param  array<int, string>|null  $default
     */
    protected function convert(array $map, ?array $default): void
    {
        DB::table('access_tokens')->orderBy('uuid')->chunkById(500, function ($accessTokens) use ($map, $default) {
            foreach ($accessTokens as $accessToken) {
                $scopes = json_decode($accessToken->scopes ?? 'null', true);
                $converted = array_values(array_unique(array_map(
                    fn (string $scope) => $map[$scope],
                    array_filter(is_array($scopes) ? $scopes : [], fn ($scope) => is_string($scope) && isset($map[$scope])),
                )));

                DB::table('access_tokens')
                    ->where('uuid', $accessToken->uuid)
                    ->update(['scopes' => $converted === [] ? ($default === null ? null : json_encode($default)) : json_encode($converted)]);
            }
        }, 'uuid');
    }
};
