<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Memorial;
use App\Models\MemorialCollaborator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller
{
    public function index()
    {
        return view('pages.calender', [
            'title' => 'Calendar',
        ]);
    }

    /**
     * Return all calendar events for the authenticated user as JSON.
     * Includes memorial death anniversaries (this year) + user-created events.
     */
    public function events(Request $request): JsonResponse
    {
        $user = Auth::user();
        $events = [];
        $currentYear = Carbon::now()->year;

        $ownedMemorials = Memorial::where('user_id', $user->id)
            ->whereNotNull('date_of_passing')
            ->where('status', Memorial::STATUS_ACTIVE)
            ->get();

        $collaboratingIds = MemorialCollaborator::where('user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->pluck('memorial_id');

        $collaboratingMemorials = Memorial::whereIn('id', $collaboratingIds)
            ->whereNotNull('date_of_passing')
            ->where('status', Memorial::STATUS_ACTIVE)
            ->get();

        $memorials = $ownedMemorials->merge($collaboratingMemorials)->unique('id');

        foreach ($memorials as $memorial) {
            $passing = $memorial->date_of_passing;
            $anniversaryDate = Carbon::create($currentYear, $passing->month, $passing->day);
            $yearsSince = $passing->year ? ($currentYear - $passing->year) : null;
            $suffix = $yearsSince ? " ({$yearsSince}" . $this->ordinalSuffix($yearsSince) . " Anniversary)" : '';

            $events[] = [
                'id' => 'memorial-' . $memorial->id,
                'title' => $memorial->full_name . $suffix,
                'start' => $anniversaryDate->toDateString(),
                'allDay' => true,
                'editable' => false,
                'extendedProps' => [
                    'calendar' => 'Danger',
                    'type' => 'anniversary',
                    'memorialId' => $memorial->id,
                    'memorialSlug' => $memorial->slug,
                    'profilePhoto' => $memorial->profile_photo_url,
                ],
            ];
        }

        $userEvents = CalendarEvent::where('user_id', $user->id)->get();

        foreach ($userEvents as $event) {
            $events[] = [
                'id' => (string) $event->id,
                'title' => $event->title,
                'start' => $event->start_date->toDateString(),
                'end' => $event->end_date?->toDateString(),
                'allDay' => true,
                'editable' => true,
                'extendedProps' => [
                    'calendar' => $event->color,
                    'type' => 'user',
                ],
            ];
        }

        return response()->json($events);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'color' => 'required|string|in:Danger,Success,Primary,Warning',
        ]);

        $event = CalendarEvent::create([
            'user_id' => Auth::id(),
            ...$validated,
        ]);

        return response()->json([
            'id' => (string) $event->id,
            'title' => $event->title,
            'start' => $event->start_date->toDateString(),
            'end' => $event->end_date?->toDateString(),
            'allDay' => true,
            'editable' => true,
            'extendedProps' => [
                'calendar' => $event->color,
                'type' => 'user',
            ],
        ], 201);
    }

    public function update(Request $request, CalendarEvent $calendarEvent): JsonResponse
    {
        if ($calendarEvent->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'color' => 'required|string|in:Danger,Success,Primary,Warning',
        ]);

        $calendarEvent->update($validated);

        return response()->json([
            'id' => (string) $calendarEvent->id,
            'title' => $calendarEvent->title,
            'start' => $calendarEvent->start_date->toDateString(),
            'end' => $calendarEvent->end_date?->toDateString(),
            'allDay' => true,
            'editable' => true,
            'extendedProps' => [
                'calendar' => $calendarEvent->color,
                'type' => 'user',
            ],
        ]);
    }

    public function destroy(CalendarEvent $calendarEvent): JsonResponse
    {
        if ($calendarEvent->user_id !== Auth::id()) {
            abort(403);
        }

        $calendarEvent->delete();

        return response()->json(['success' => true]);
    }

    private function ordinalSuffix(int $n): string
    {
        if (in_array($n % 100, [11, 12, 13])) {
            return 'th';
        }

        return match ($n % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
