<?php

namespace App\Reports;

use App\Helpers\BrandingHelper;
use App\Models\Reseller;

/**
 * Whose document this is.
 *
 * The rule the rest of the white-label work already established: an admin export carries
 * platform branding; a reseller export carries the reseller's own name, logo and colour
 * and NO platform branding whatsoever. Resellers hand these PDFs to funeral-home clients
 * and to families — a Forever logo on that page breaks the product they were sold.
 */
final class ReportBranding
{
    public function __construct(
        public readonly string $organisationName,
        public readonly ?string $logoPath,
        public readonly string $primaryColor,
        public readonly string $scopeLabel,
        public readonly string $filePrefix,
    ) {}

    public static function platform(): self
    {
        return new self(
            organisationName: BrandingHelper::documentTitleName(),
            logoPath: self::localPath(BrandingHelper::logoUrl()),
            primaryColor: BrandingHelper::primaryColor(),
            scopeLabel: 'Platform-wide',
            filePrefix: BrandingHelper::documentTitleName(),
        );
    }

    public static function forReseller(Reseller $reseller): self
    {
        return new self(
            organisationName: $reseller->name,
            logoPath: $reseller->logo_path ? self::localPath(asset('storage/'.$reseller->logo_path)) : null,
            primaryColor: $reseller->primary_color ?: BrandingHelper::primaryColor(),
            scopeLabel: $reseller->name,
            filePrefix: $reseller->name,
        );
    }

    /**
     * Dompdf reads images off disk, not over HTTP — remote fetching stays disabled so a
     * report can never be turned into an outbound request. Anything that does not resolve
     * to a real local file becomes null, and the PDF prints the organisation name instead
     * of a broken-image box.
     */
    public static function localPath(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        // Strip any subdirectory the app is installed under (/Forever/storage/… → /storage/…)
        $base = parse_url((string) config('app.url'), PHP_URL_PATH);
        if (is_string($base) && $base !== '' && $base !== '/' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        $candidate = public_path(ltrim($path, '/'));

        return is_file($candidate) ? $candidate : null;
    }
}
