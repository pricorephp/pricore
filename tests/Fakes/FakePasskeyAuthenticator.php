<?php

namespace Tests\Fakes;

use App\Models\User;
use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\UnsignedIntegerObject;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Support\WebAuthn;
use OpenSSLAsymmetricKey;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * A software ES256 authenticator that signs real WebAuthn assertions, so passkey
 * logins can be tested without a browser.
 */
class FakePasskeyAuthenticator
{
    private OpenSSLAsymmetricKey $privateKey;

    private string $credentialId;

    public function __construct()
    {
        $privateKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);

        assert($privateKey instanceof OpenSSLAsymmetricKey);

        $this->privateKey = $privateKey;
        $this->credentialId = random_bytes(32);
    }

    public function register(User $user, string $name = 'Test passkey'): Passkey
    {
        $details = openssl_pkey_get_details($this->privateKey);

        assert(is_array($details));

        // OpenSSL drops leading zero bytes from the coordinates, while COSE requires their full 32 bytes.
        $publicKey = MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create(str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create(str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT)));

        $credentialRecord = CredentialRecord::create(
            publicKeyCredentialId: $this->credentialId,
            type: 'public-key',
            transports: [],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            credentialPublicKey: (string) $publicKey,
            userHandle: $user->getPasskeyUserHandle(),
            counter: 0,
        );

        return $user->passkeys()->create([
            'name' => $name,
            'credential_id' => Base64UrlSafe::encodeUnpadded($this->credentialId),
            'credential' => json_decode(WebAuthn::toJson($credentialRecord), true, flags: JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * Sign the challenge from a `/passkeys/.../options` response into the credential the browser would post.
     *
     * @param  array{challenge: string, rpId?: string}  $options
     * @return array{id: string, rawId: string, type: string, response: array<string, string>}
     */
    public function assert(array $options, string $userHandle, bool $userVerified = true, ?string $origin = null): array
    {
        $relyingPartyId = $options['rpId'] ?? config()->string('passkeys.relying_party_id');

        $clientDataJson = json_encode([
            'type' => 'webauthn.get',
            'challenge' => $options['challenge'],
            'origin' => $origin ?? config('passkeys.allowed_origins')[0],
            'crossOrigin' => false,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        // User present (0x01), optionally user verified (0x04), with a counter of 1.
        $flags = $userVerified ? 0x05 : 0x01;
        $authenticatorData = hash('sha256', $relyingPartyId, binary: true).chr($flags).pack('N', 1);

        openssl_sign($authenticatorData.hash('sha256', $clientDataJson, binary: true), $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        $credentialId = Base64UrlSafe::encodeUnpadded($this->credentialId);

        return [
            'id' => $credentialId,
            'rawId' => $credentialId,
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64UrlSafe::encodeUnpadded($clientDataJson),
                'authenticatorData' => Base64UrlSafe::encodeUnpadded($authenticatorData),
                'signature' => Base64UrlSafe::encodeUnpadded($signature),
                'userHandle' => Base64UrlSafe::encodeUnpadded($userHandle),
            ],
        ];
    }
}
