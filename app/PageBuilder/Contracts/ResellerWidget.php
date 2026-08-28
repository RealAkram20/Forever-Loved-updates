<?php

namespace App\PageBuilder\Contracts;

/**
 * A widget that belongs to the reseller theme system, not to the platform's own page builder.
 *
 * The registry discovers every class in app/PageBuilder/Widgets, which is what makes adding a
 * widget a one-file job — and also what silently pushed the four theme section widgets into
 * the platform admin's palette the moment they were written. That is wrong in both directions:
 *
 *  - The platform's own site is not themed, so a `section_split` on an admin-built page would
 *    render the plain fallback and never look like the design it was drawn for.
 *  - It doubles the palette an admin has to read, with four entries that duplicate what `hero`
 *    and `features_grid` already do for that audience.
 *
 * A marker rather than a method on PageWidgetContract, so the twelve existing widgets need no
 * change and nothing about them can break by adding this.
 *
 * Rendering is deliberately *not* filtered. A page already carrying one of these must keep
 * rendering wherever it is served — hiding a widget from a palette is a decision about what to
 * offer next, never about what to do with what already exists.
 */
interface ResellerWidget {}
