<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TwoFactor;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use OTPHP\TOTP;
use Symfony\Component\Clock\NativeClock;

final class TwoFactorService
{
    private const ISSUER = 'TaskHive';
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return array{token: string, expiresAt: \DateTimeImmutable} */
    public function createLoginChallenge(User $user): array
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable('+5 minutes');

        $challenge = (new TwoFactor())
            ->setUser($user)
            ->setTokenHash($this->hashToken($token))
            ->setExpiresAt($expiresAt);

        $this->em->persist($challenge);
        $this->em->flush();

        return ['token' => $token, 'expiresAt' => $expiresAt];
    }

    public function findChallenge(string $token): ?TwoFactor
    {
        $challenge = $this->em->getRepository(TwoFactor::class)->findOneBy([
            'tokenHash' => $this->hashToken($token),
        ]);

        if (!$challenge instanceof TwoFactor) {
            return null;
        }

        if ($challenge->getUsedAt() !== null || $challenge->getExpiresAt() < new \DateTimeImmutable()) {
            return null;
        }

        return $challenge;
    }

    public function isChallengeBlocked(TwoFactor $challenge): bool
    {
        return $challenge->getAttempts() >= self::MAX_ATTEMPTS;
    }

    public function verifyCode(User $user, string $code, ?string $secret = null): bool
    {
        $secret ??= $user->getTotpSecret();
        if ($secret === null || !preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $totp = TOTP::create($secret, 30, 'sha1', 6, clock: new NativeClock());

        return $totp->verify($code, null, 29);
    }

    public function createSecret(): string
    {
        return TOTP::generate(new NativeClock(), 20)->getSecret();
    }

    /** @return array{secret: string, qrCode: string, otpAuthUrl: string} */
    public function buildSetupPayload(User $user, string $secret): array
    {
        $totp = TOTP::create($secret, 30, 'sha1', 6, clock: new NativeClock());
        $totp->setIssuer(self::ISSUER);
        $totp->setLabel($user->getEmail());

        $otpAuthUrl = $totp->getProvisioningUri();
        $qrCode = new QrCode($otpAuthUrl, size: 260, margin: 12);
        $result = (new SvgWriter())->write($qrCode);

        return [
            'secret' => $secret,
            'qrCode' => $result->getDataUri(),
            'otpAuthUrl' => $otpAuthUrl,
        ];
    }

    public function markChallengeAsUsed(TwoFactor $challenge): void
    {
        $challenge->setUsedAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    public function increaseAttempts(TwoFactor $challenge): void
    {
        $challenge->incrementAttempts();
        $this->em->flush();
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
