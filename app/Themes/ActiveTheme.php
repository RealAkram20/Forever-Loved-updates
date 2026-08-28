<?php

namespace App\Themes;

use Illuminate\Support\Facades\View;

/**
 * Which template's blades this request renders with.
 *
 * Deliberately answers *only* that question. A theme also carries colours and fonts, but
 * those resolve through App\Helpers\ThemeSetting and follow a different tenant: markup
 * follows the **host** (whose site is this), while palette follows the **branding tenant**
 * (whose brand is this) — the same split ThemeSetting already documents between siteTenant()
 * and tenant(). A reseller's staff opening their dashboard on the platform's host should see
 * their own colours and our layout; keeping the two questions in separate classes is what
 * stops that being an accident.
 *
 * Applied by whichever middleware bound the tenant — ResolveResellerByHost for a real
 * reseller host, ResolveReseller for the /r/{slug} development fallback,
 * ResolveResellerByCustomDomain for their own domain.
 */
class ActiveTheme
{
    /** @var array<int, string>|null The finder's paths before any template was prepended. */
    private ?array $basePaths = null;

    private string $applied = ThemeRegistry::BASE;

    public function __construct(private readonly ThemeRegistry $registry) {}

    public function template(): string
    {
        return $this->applied;
    }

    public function manifest(): ?ThemeManifest
    {
        return $this->registry->manifest($this->applied);
    }

    /**
     * Render this request with `$template`, or with the base template when it is null,
     * unknown, or the base itself.
     *
     * An unknown template resolving to the base rather than throwing is deliberate: a theme
     * directory can go missing between a deploy and the row that points at it, and a site
     * that renders in the wrong design is recoverable in a way that a site returning 500 to
     * every visitor is not.
     */
    public function use(?string $template): void
    {
        $template = $template !== null && $this->registry->exists($template)
            ? $template
            : ThemeRegistry::BASE;

        if ($template === $this->applied && $this->basePaths !== null) {
            return;
        }

        $finder = View::getFinder();

        // Captured on first use rather than in a provider: `basic` is registered as a view
        // location during boot, and capturing before that would leave it out of the fallback
        // chain for every request that later switches template.
        $this->basePaths ??= $finder->getPaths();

        $paths = $this->basePaths;

        if ($template !== ThemeRegistry::BASE) {
            array_unshift($paths, ThemeRegistry::path($template));
        }

        $finder->setPaths($paths);

        // The finder memoises name => path, and the container is *not* rebuilt per request
        // under a long-running worker or inside the test suite. Without this, the first
        // tenant to render `components.home-header` decides what every later one sees —
        // one reseller's header served on another's domain. Cheap, and the only thing
        // standing between this design and that bug.
        $finder->flush();

        $this->applied = $template;
    }

    /** Back to the base template — the platform's own host, and anything with no tenant. */
    public function reset(): void
    {
        $this->use(null);
    }

    /**
     * Whether the *active* template supplies this view itself, rather than inheriting it.
     *
     * Asked by PageController::home() about `pages.visitor.home`, and the answer decides a
     * precedence question the cascade alone cannot: a template like Dignified ships a designed
     * front page, and that page has to outrank the **platform's** page-builder layout — which
     * is a fallback for tenants who have built nothing, and would otherwise serve our
     * arrangement of blocks on their themed site. It must not outrank the *reseller's own*
     * built layout, which is a thing they made on purpose.
     *
     * A path comparison rather than a manifest flag, because a flag can disagree with what is
     * actually on disk and this cannot. The base template answers false by design: its home is
     * the generic block renderer, which is exactly what the platform layout is for.
     */
    public function ownsView(string $view): bool
    {
        if ($this->applied === ThemeRegistry::BASE) {
            return false;
        }

        try {
            $path = View::getFinder()->find($view);
        } catch (\InvalidArgumentException) {
            return false;
        }

        $root = ThemeRegistry::path($this->applied);

        return str_starts_with(
            str_replace('\\', '/', $path),
            rtrim(str_replace('\\', '/', $root), '/').'/'
        );
    }
}
