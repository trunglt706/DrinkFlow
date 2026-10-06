<?php

declare(strict_types=1);

namespace App\Services\SponsorDraw;

/**
 * Verifiable winner selection of a sponsor draw ("csprng-commit-reveal-v1").
 *
 * 1. When the session is created, the eligible members are frozen in a snapshot ordered by room_user_id
 *    (positions 0..N-1) and hashed: SHA-256("drinkflow-sponsor-snapshot-v1\n{session}\n{pos}:{room_user_id}\n…").
 * 2. A 32-byte seed is drawn from the OS CSPRNG (random_bytes). Only its commitment is published before the
 *    first turn: SHA-256("drinkflow-sponsor-commit-v1|{session}|{snapshot_hash}|{seed_hex}").
 * 3. Byte stream: HMAC-SHA256(key = seed bytes, message = "drinkflow-sponsor-draw-v1:{counter}"), counter = 0, 1, 2…,
 *    read as big-endian unsigned 32-bit integers.
 * 4. uniform(n): rejection sampling below floor(2^32 / n) * n, then value mod n (no modulo bias).
 * 5. Partial Fisher–Yates over the positions: for k = 0..K-1, j = k + uniform(N - k), swap(k, j); the k-th winner
 *    is the position now at index k. The order is fully determined by the seed, so a turn can never be re-rolled.
 * 6. The seed is revealed once drawing is over; anyone can then check the commitment and recompute the winners
 *    (resources/js/sponsor-draw/verify.js runs the same steps in the browser).
 *
 * Assurance level: this proves the seed was not changed after the commitment was published, but the operator
 * could have generated several seeds before publishing one. A public randomness beacon would be stronger.
 */
final class SponsorDrawRandomizer
{
    public const METHOD = 'csprng-commit-reveal-v1';

    private const STREAM_PREFIX = 'drinkflow-sponsor-draw-v1:';

    /**
     * A new secret seed (64 hex characters).
     */
    public function newSeed(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Hash of the frozen snapshot (internal IDs and positions only, no personal data).
     *
     * @param int $sessionId Session ID.
     * @param list<int> $roomUserIds Eligible room_user IDs in position order (ascending ID).
     * @return string SHA-256 hex digest.
     */
    public function snapshotHash(int $sessionId, array $roomUserIds): string
    {
        $lines = ['drinkflow-sponsor-snapshot-v1', (string) $sessionId];
        foreach (array_values($roomUserIds) as $position => $roomUserId) {
            $lines[] = $position.':'.$roomUserId;
        }

        return hash('sha256', implode("\n", $lines));
    }

    /**
     * Hash of a frozen shop list (positions and names; shops are no personal data).
     *
     * @param int $sessionId Session ID.
     * @param list<string> $names Shop names in position order.
     * @return string SHA-256 hex digest.
     */
    public function namesSnapshotHash(int $sessionId, array $names): string
    {
        $lines = ['drinkflow-shop-snapshot-v1', (string) $sessionId];
        foreach (array_values($names) as $position => $name) {
            $lines[] = $position.':'.$name;
        }

        return hash('sha256', implode("\n", $lines));
    }

    /**
     * Public commitment to the seed, published before the first turn.
     */
    public function commitment(string $seedHex, int $sessionId, string $snapshotHash): string
    {
        return hash('sha256', "drinkflow-sponsor-commit-v1|{$sessionId}|{$snapshotHash}|{$seedHex}");
    }

    /**
     * Snapshot positions of the winners, in draw order.
     *
     * @param string $seedHex Secret seed (hex).
     * @param int $eligibleCount Snapshot size N.
     * @param int $winners Number of winners K (K <= N).
     * @return list<int> Positions of the K winners.
     */
    public function winnerPositions(string $seedHex, int $eligibleCount, int $winners): array
    {
        $winners = min($winners, $eligibleCount);
        $positions = range(0, max(0, $eligibleCount - 1));
        $stream = $this->stream(hex2bin($seedHex));

        for ($k = 0; $k < $winners; $k++) {
            $j = $k + $this->uniform($stream, $eligibleCount - $k);
            [$positions[$k], $positions[$j]] = [$positions[$j], $positions[$k]];
        }

        return array_slice($positions, 0, $winners);
    }

    /**
     * Unbiased integer in [0, n).
     *
     * @param \Generator<int, int> $stream 32-bit stream.
     */
    private function uniform(\Generator $stream, int $n): int
    {
        if ($n <= 1) {
            return 0;
        }
        $limit = intdiv(4294967296, $n) * $n;
        while (true) {
            $value = $stream->current();
            $stream->next();
            if ($value < $limit) {
                return $value % $n;
            }
        }
    }

    /**
     * Deterministic stream of big-endian unsigned 32-bit integers derived from the seed.
     *
     * @return \Generator<int, int>
     */
    private function stream(string $seed): \Generator
    {
        for ($counter = 0; ; $counter++) {
            foreach (unpack('N8', hash_hmac('sha256', self::STREAM_PREFIX.$counter, $seed, true)) as $value) {
                yield $value;
            }
        }
    }
}
