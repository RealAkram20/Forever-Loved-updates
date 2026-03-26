<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class CalenderArea extends Component
{
    public string $eventsUrl;
    public string $storeUrl;
    public string $updateUrlBase;
    public string $destroyUrlBase;
    public bool $hasMemorials;

    public function __construct()
    {
        $this->eventsUrl = route('calendar.events');
        $this->storeUrl = route('calendar.events.store');
        $this->updateUrlBase = url('/calendar/events');
        $this->destroyUrlBase = url('/calendar/events');

        $user = auth()->user();
        $ownedCount = $user ? $user->memorials()->count() : 0;
        $collabCount = $user ? \App\Models\MemorialCollaborator::where('user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->count() : 0;
        $this->hasMemorials = ($ownedCount + $collabCount) > 0;
    }

    public function render(): View|Closure|string
    {
        return view('components.calender-area');
    }
}
