{{--
    The characters-remaining line under a limited field.

    Only appears when the widget declared a limit, so unlimited fields are not decorated with a
    number nobody needs. It turns amber inside the last tenth rather than only at zero: the
    useful moment is while there is still room to shorten a sentence, not after the input has
    already stopped accepting letters.

    The explanation matters as much as the number. "40 / 40" tells a reseller they have hit a
    wall; `field.max_hint` tells them the wall is the design.
--}}
<template x-if="field.max">
    <div class="mt-1 flex items-start justify-between gap-3">
        <p class="text-[10px] leading-tight text-gray-400 dark:text-gray-500" x-text="field.max_hint || ''"></p>

        <p class="shrink-0 text-[10px] tabular-nums"
            :class="(selectedWidget.props[field.name] || '').length >= field.max
                ? 'font-semibold text-amber-600 dark:text-amber-400'
                : ((field.max - (selectedWidget.props[field.name] || '').length) <= Math.ceil(field.max * 0.1)
                    ? 'text-amber-600 dark:text-amber-400'
                    : 'text-gray-400 dark:text-gray-500')">
            <span x-text="(selectedWidget.props[field.name] || '').length"></span><span>/</span><span x-text="field.max"></span>
        </p>
    </div>
</template>
