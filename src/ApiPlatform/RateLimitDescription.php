<?php

declare(strict_types=1);

namespace JacyImp\ApiPlatformRateLimiter\ApiPlatform;

use ApiPlatform\Metadata\Operation;
use JacyImp\ApiPlatformRateLimiter\Core\IntervalNormalizer;
use JacyImp\ApiPlatformRateLimiter\Core\SharedRateLimitRegistry;
use JacyImp\ApiPlatformRateLimiter\Metadata\RateLimit;

/** @internal */
final readonly class RateLimitDescription
{
    /** @param array<string, RateLimit> $globalRateLimits */
    public function __construct(
        private RateLimitMetadataExtractor $extractor,
        private SharedRateLimitRegistry $buckets,
        private IntervalNormalizer $intervalNormalizer,
        private array $globalRateLimits = [],
    ) {
    }

    public function describe(Operation $operation, string $operationName): string
    {
        $lines = [];
        foreach ($this->extractor->extract($operation) as $limit) {
            $line = $this->describeLimit($limit, $operation, $operationName);
            if ($line === null) {
                continue;
            }

            $lines[] = '- ' . $line;
        }
        foreach ($this->globalRateLimits as $name => $limit) {
            $line = $this->describeLimit($limit, $operation, $name, true);
            if ($line === null) {
                continue;
            }

            $lines[] = '- Global quota: ' . $line;
        }

        if ($lines === []) {
            return '';
        }

        return "### Rate limits\n\n" . implode("\n", $lines)
            . "\n\nQuotas apply per resolved identity. Exceeding a quota returns HTTP 429."
            . ' Runtime bypass rules may exempt requests.';
    }

    private function describeLimit(
        RateLimit $limit,
        Operation $operation,
        string $name,
        bool $global = false,
    ): ?string {
        $bucket = $limit->bucket ?? $name;
        $resolvedBucket = $global
            ? 'global:' . $name . ($limit->bucket === null ? '' : ':' . $bucket)
            : ($limit->bucket === null ? 'operation:' : 'shared:') . $bucket;
        $conditional = $limit->when !== null;
        foreach ($this->extractor->extractBypasses($operation) as $bypass) {
            if ($bypass->bucket !== null && $limit->bucketResolver !== null) {
                $conditional = true;
                continue;
            }
            if (
                $bypass->bucket !== null
                && $bypass->bucket !== $resolvedBucket
                && ($global || $bypass->bucket !== $bucket)
            ) {
                continue;
            }
            if ($bypass->when === null) {
                return null;
            }
            $conditional = true;
        }

        $declaration = $limit;
        if ($limit->limit === null && $limit->bucket !== null) {
            $declaration = $this->buckets->get($limit->bucket);
        }
        $conditional = $conditional || $declaration->when !== null;
        $interval = $declaration->interval;
        $period = is_string($interval)
            ? $interval
            : ($interval === null ? null : $this->intervalNormalizer->normalize($interval) . ' seconds');
        $quota = is_int($declaration->limit) ? $declaration->limit : 'Dynamic';
        $cost = $declaration->limit !== null && is_int($limit->cost) && is_int($declaration->cost)
            ? ($declaration === $limit ? $limit->cost : $limit->cost * $declaration->cost)
            : null;
        $text = $period === null
            ? 'Quota and interval determined at request time.'
            : sprintf('%s tokens per %s (%s).', $quota, $period, $declaration->policy->value);
        $text .= $cost === null
            ? ' Request cost is determined at request time.'
            : sprintf(' Cost: %d token(s) per request.', $cost);
        if ($limit->bucket !== null || $limit->bucketResolver !== null) {
            $text .= ' Shared bucket.';
        }

        return $text . ($conditional ? ' Applies conditionally.' : '');
    }
}
