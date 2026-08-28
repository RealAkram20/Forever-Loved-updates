{{--
    The characters-remaining line for a field inside a repeater row.

    Separate from field-counter because the value lives on the row rather than on the widget,
    so it counts `row[itemField.name]` instead of `selectedWidget.props[field.name]`. Same
    behaviour otherwise: nothing at all unless a limit was declared, and amber inside the last
    tenth — while there is still room to shorten a phrase, not once the input has gone dead.
--}}
<template x-if="itemField.max">
    <p class="mt-1 text-right text-[10px] tabular-nums"
        :class="String(row[itemField.name] ?? '').length >= itemField.max
            ? 'font-semibold text-amber-600 dark:text-amber-400'
            : ((itemField.max - String(row[itemField.name] ?? '').length) <= Math.ceil(itemField.max * 0.1)
                ? 'text-amber-600 dark:text-amber-400'
                : 'text-gray-400 dark:text-gray-500')">
        <span x-text="String(row[itemField.name] ?? '').length"></span><span>/</span><span x-text="itemField.max"></span>
    </p>
</template>
