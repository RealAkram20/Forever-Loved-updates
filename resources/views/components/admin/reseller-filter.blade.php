@props(['resellers', 'default' => '', 'standalone' => true])

{{--
    Reseller scope selector for the platform-wide admin lists. Renders nothing when no
    resellers exist, so single-tenant installs never see a control that can only ever
    have one meaningful value.

    standalone: its own auto-submitting form, carrying the page's other query params
    through as hidden inputs. Set false to drop the select into a filter form the page
    already has — nested forms are invalid, and two adjacent filter bars read as a bug.
--}}
@if ($resellers->isNotEmpty())
    {{-- Mirror the controller's own default, or the control would claim "All owners"
         on a page that actually landed pre-filtered. --}}
    @php $current = request('reseller', $default); @endphp

    @if ($standalone)
        <form method="GET" class="flex items-center gap-2">
            @foreach (request()->except(['reseller', 'page']) as $key => $value)
                @if (is_scalar($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <label for="reseller-filter" class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">Owner</label>
            <x-admin.reseller-select :resellers="$resellers" :current="$current" auto />
        </form>
    @else
        <x-admin.reseller-select :resellers="$resellers" :current="$current" />
    @endif
@endif
