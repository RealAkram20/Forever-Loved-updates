
<div>
    <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] overflow-hidden">
        @unless($hasMemorials)
            <div class="px-6 pt-5 pb-0">
                <div class="rounded-lg border border-blue-100 dark:border-blue-900/30 bg-blue-50 dark:bg-blue-900/20 p-4 text-sm text-blue-700 dark:text-blue-300">
                    You don't have any memorial profiles yet. Death anniversaries will appear here automatically once you
                    <a href="{{ route('memorial.create.step1') }}" class="font-semibold underline hover:text-blue-900 dark:hover:text-blue-100">create a memorial</a>.
                    In the meantime, you can add your own events below.
                </div>
            </div>
        @endunless
        <div class="custom-calendar overflow-x-auto">
            <div id="calendar" class="min-h-screen"
                 data-events-url="{{ $eventsUrl }}"
                 data-store-url="{{ $storeUrl }}"
                 data-update-url-base="{{ $updateUrlBase }}"
                 data-destroy-url-base="{{ $destroyUrlBase }}"
                 data-csrf-token="{{ csrf_token() }}"
            ></div>
        </div>
    </div>

    <!-- Event Modal -->
    <div class="fixed inset-0 items-center justify-center hidden p-5 overflow-y-auto modal z-99999" id="eventModal">
        <div class="modal-close-btn fixed inset-0 h-full w-full bg-gray-400/50 dark:bg-gray-900/70 backdrop-blur-[32px]"></div>
        <div class="modal-dialog relative flex w-full max-w-[700px] flex-col overflow-y-auto rounded-3xl bg-white dark:bg-gray-900 p-6 lg:p-11">

            <!-- Close Button -->
            <button class="modal-close-btn transition-color absolute top-5 right-5 z-999 flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-gray-600 dark:hover:text-gray-300 sm:h-11 sm:w-11">
                <svg class="fill-current" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M6.04289 16.5418C5.65237 16.9323 5.65237 17.5655 6.04289 17.956C6.43342 18.3465 7.06658 18.3465 7.45711 17.956L11.9987 13.4144L16.5408 17.9565C16.9313 18.347 17.5645 18.347 17.955 17.9565C18.3455 17.566 18.3455 16.9328 17.955 16.5423L13.4129 12.0002L17.955 7.45808C18.3455 7.06756 18.3455 6.43439 17.955 6.04387C17.5645 5.65335 16.9313 5.65335 16.5408 6.04387L11.9987 10.586L7.45711 6.04439C7.06658 5.65386 6.43342 5.65386 6.04289 6.04439C5.65237 6.43491 5.65237 7.06808 6.04289 7.4586L10.5845 12.0002L6.04289 16.5418Z" fill="" />
                </svg>
            </button>

            <div class="flex flex-col px-2 overflow-y-auto modal-content custom-scrollbar">

                <!-- Modal Header -->
                <div class="modal-header">
                    <h5 class="mb-2 font-semibold text-gray-800 dark:text-white/90 modal-title text-theme-xl lg:text-2xl" id="eventModalLabel">
                        Add Event
                    </h5>
                    <p class="text-sm text-gray-500 dark:text-gray-400" id="eventModalSubtitle">
                        Schedule a personal event on your calendar
                    </p>
                </div>

                <!-- Anniversary Detail (shown when clicking an anniversary) -->
                <div id="anniversary-detail" class="mt-6 hidden">
                    <div class="flex items-center gap-4 rounded-xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-white/[0.02] p-5">
                        <div id="anniversary-photo" class="h-14 w-14 shrink-0 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden flex items-center justify-center">
                            <svg class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 dark:text-white/90" id="anniversary-name"></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400" id="anniversary-date-label"></p>
                        </div>
                    </div>
                    <div class="mt-4 flex">
                        <a id="anniversary-link" href="#" class="inline-flex items-center gap-2 text-sm font-medium text-brand-500 hover:text-brand-600">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                            View Memorial Profile
                        </a>
                    </div>
                </div>

                <!-- Event Form (hidden for anniversaries) -->
                <div id="event-form" class="mt-8 modal-body">

                    <!-- Event Title -->
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Event Title
                        </label>
                        <input id="event-title" type="text" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" placeholder="Enter event title" />
                    </div>

                    <!-- Event Color -->
                    <div class="mt-6">
                        <label class="block mb-4 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Event Color
                        </label>
                        <div class="flex flex-wrap items-center gap-4 sm:gap-5">

                            <div class="n-chk">
                                <div class="form-check form-check-danger form-check-inline">
                                    <label class="flex items-center text-sm text-gray-700 dark:text-gray-300 form-check-label" for="modalDanger">
                                        <span class="relative">
                                            <input class="sr-only form-check-input" type="radio" name="event-level" value="Danger" id="modalDanger" />
                                            <span class="flex items-center justify-center w-5 h-5 mr-2 border border-gray-300 dark:border-gray-600 rounded-full box"></span>
                                        </span>
                                        Red
                                    </label>
                                </div>
                            </div>

                            <div class="n-chk">
                                <div class="form-check form-check-success form-check-inline">
                                    <label class="flex items-center text-sm text-gray-700 dark:text-gray-300 form-check-label" for="modalSuccess">
                                        <span class="relative">
                                            <input class="sr-only form-check-input" type="radio" name="event-level" value="Success" id="modalSuccess" />
                                            <span class="flex items-center justify-center w-5 h-5 mr-2 border border-gray-300 dark:border-gray-600 rounded-full box"></span>
                                        </span>
                                        Green
                                    </label>
                                </div>
                            </div>

                            <div class="n-chk">
                                <div class="form-check form-check-primary form-check-inline">
                                    <label class="flex items-center text-sm text-gray-700 dark:text-gray-300 form-check-label" for="modalPrimary">
                                        <span class="relative">
                                            <input class="sr-only form-check-input" type="radio" name="event-level" value="Primary" id="modalPrimary" />
                                            <span class="flex items-center justify-center w-5 h-5 mr-2 border border-gray-300 dark:border-gray-600 rounded-full box"></span>
                                        </span>
                                        Blue
                                    </label>
                                </div>
                            </div>

                            <div class="n-chk">
                                <div class="form-check form-check-warning form-check-inline">
                                    <label class="flex items-center text-sm text-gray-700 dark:text-gray-300 form-check-label" for="modalWarning">
                                        <span class="relative">
                                            <input class="sr-only form-check-input" type="radio" name="event-level" value="Warning" id="modalWarning" />
                                            <span class="flex items-center justify-center w-5 h-5 mr-2 border border-gray-300 dark:border-gray-600 rounded-full box"></span>
                                        </span>
                                        Orange
                                    </label>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Start Date -->
                    <div class="mt-6">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Start Date
                        </label>
                        <div class="relative">
                            <input id="event-start-date" type="date" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full appearance-none rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" onclick="this.showPicker()" />
                            <span class="absolute top-1/2 right-3.5 -translate-y-1/2 pointer-events-none">
                                <svg class="fill-gray-700 dark:fill-gray-400" width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M4.33317 0.0830078C4.74738 0.0830078 5.08317 0.418794 5.08317 0.833008V1.24967H8.9165V0.833008C8.9165 0.418794 9.25229 0.0830078 9.6665 0.0830078C10.0807 0.0830078 10.4165 0.418794 10.4165 0.833008V1.24967L11.3332 1.24967C12.2997 1.24967 13.0832 2.03318 13.0832 2.99967V4.99967V11.6663C13.0832 12.6328 12.2997 13.4163 11.3332 13.4163H2.6665C1.70001 13.4163 0.916504 12.6328 0.916504 11.6663V4.99967V2.99967C0.916504 2.03318 1.70001 1.24967 2.6665 1.24967L3.58317 1.24967V0.833008C3.58317 0.418794 3.91896 0.0830078 4.33317 0.0830078ZM4.33317 2.74967H2.6665C2.52843 2.74967 2.4165 2.8616 2.4165 2.99967V4.24967H11.5832V2.99967C11.5832 2.8616 11.4712 2.74967 11.3332 2.74967H9.6665H4.33317ZM11.5832 5.74967H2.4165V11.6663C2.4165 11.8044 2.52843 11.9163 2.6665 11.9163H11.3332C11.4712 11.9163 11.5832 11.8044 11.5832 11.6663V5.74967Z" fill="" />
                                </svg>
                            </span>
                        </div>
                    </div>

                    <!-- End Date -->
                    <div class="mt-6">
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            End Date <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <div class="relative">
                            <input id="event-end-date" type="date" class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full appearance-none rounded-lg border border-gray-300 dark:border-gray-700 bg-transparent px-4 py-2.5 text-sm text-gray-800 dark:text-white/90 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden" onclick="this.showPicker()" />
                            <span class="absolute top-1/2 right-3.5 -translate-y-1/2 pointer-events-none">
                                <svg class="fill-gray-700 dark:fill-gray-400" width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M4.33317 0.0830078C4.74738 0.0830078 5.08317 0.418794 5.08317 0.833008V1.24967H8.9165V0.833008C8.9165 0.418794 9.25229 0.0830078 9.6665 0.0830078C10.0807 0.0830078 10.4165 0.418794 10.4165 0.833008V1.24967L11.3332 1.24967C12.2997 1.24967 13.0832 2.03318 13.0832 2.99967V4.99967V11.6663C13.0832 12.6328 12.2997 13.4163 11.3332 13.4163H2.6665C1.70001 13.4163 0.916504 12.6328 0.916504 11.6663V4.99967V2.99967C0.916504 2.03318 1.70001 1.24967 2.6665 1.24967L3.58317 1.24967V0.833008C3.58317 0.418794 3.91896 0.0830078 4.33317 0.0830078ZM4.33317 2.74967H2.6665C2.52843 2.74967 2.4165 2.8616 2.4165 2.99967V4.24967H11.5832V2.99967C11.5832 2.8616 11.4712 2.74967 11.3332 2.74967H9.6665H4.33317ZM11.5832 5.74967H2.4165V11.6663C2.4165 11.8044 2.52843 11.9163 2.6665 11.9163H11.3332C11.4712 11.9163 11.5832 11.8044 11.5832 11.6663V5.74967Z" fill="" />
                                </svg>
                            </span>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="flex items-center gap-3 mt-6 modal-footer sm:justify-end">
                    <button type="button" class="modal-close-btn flex w-full justify-center rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 sm:w-auto">
                        Close
                    </button>
                    <button type="button" class="btn btn-delete-event hidden bg-error-500 hover:bg-error-600 flex w-full justify-center rounded-lg px-4 py-2.5 text-sm font-medium text-white sm:w-auto" data-fc-event-public-id="">
                        Delete
                    </button>
                    <button type="button" class="btn btn-update-event bg-brand-500 hover:bg-brand-600 flex w-full justify-center rounded-lg px-4 py-2.5 text-sm font-medium text-white sm:w-auto" style="display: none;" data-fc-event-public-id="">
                        Update Changes
                    </button>
                    <button type="button" class="btn btn-add-event bg-brand-500 hover:bg-brand-600 flex w-full justify-center rounded-lg px-4 py-2.5 text-sm font-medium text-white sm:w-auto">
                        Add Event
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>
