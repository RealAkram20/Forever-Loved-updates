<?php

namespace App\Http\Controllers;

use App\Models\Memorial;
use App\Models\MemorialShare;
use App\Models\MemorialView;
use App\Models\StoryChapter;
use App\Models\SubscriptionPlan;
use App\Models\Tribute;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->hasRole(['admin', 'super-admin']);

        $data = [
            'title'   => 'Dashboard',
            'isAdmin' => $isAdmin,
        ];

        if ($isAdmin) {
            $data = array_merge($data, $this->adminMetrics($request));
        }

        $data = array_merge($data, $this->userMetrics($request, $user));

        return view('pages.dashboard.index', $data);
    }

    private function adminMetrics(Request $request): array
    {
        $period = $request->get('period', 'all');
        $dateFrom = $this->resolveDateFrom($period);

        $usersQuery = User::query();
        $memorialsQuery = Memorial::query();
        $subsQuery = UserSubscription::query();

        if ($dateFrom) {
            $usersQuery->where('created_at', '>=', $dateFrom);
            $memorialsQuery->where('created_at', '>=', $dateFrom);
            $subsQuery->where('created_at', '>=', $dateFrom);
        }

        $registeredUsers = $usersQuery->count();
        $totalMemorials = $memorialsQuery->count();
        $activeMemorials = (clone $memorialsQuery)->where('status', Memorial::STATUS_ACTIVE)->count();

        $activeSubscriptions = UserSubscription::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })->count();

        $totalSales = UserSubscription::join('subscription_plans', 'user_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
            ->where('subscription_plans.price', '>', 0);
        if ($dateFrom) {
            $totalSales->where('user_subscriptions.created_at', '>=', $dateFrom);
        }
        $totalSalesAmount = (clone $totalSales)->sum('subscription_plans.price');
        $totalSalesCount = $totalSales->count();

        $currency = \App\Models\SystemSetting::get('payments.currency', 'USD');

        $recentMemorials = Memorial::with('owner')->latest()->limit(8)->get();

        $recentUsers = User::withCount('memorials')->latest()->limit(6)->get();

        $monthlyGrowth = $this->monthlyGrowthData();

        $topMemorialIds = MemorialView::select('memorial_id', DB::raw('COUNT(*) as view_count'))
            ->groupBy('memorial_id')
            ->orderByDesc('view_count')
            ->limit(5)
            ->pluck('view_count', 'memorial_id');

        $topMemorials = $topMemorialIds->isNotEmpty()
            ? Memorial::whereIn('id', $topMemorialIds->keys())->get()->map(function ($m) use ($topMemorialIds) {
                $m->views_count = $topMemorialIds[$m->id] ?? 0;
                $m->shares_count = MemorialShare::where('memorial_id', $m->id)->count();
                return $m;
            })->sortByDesc('views_count')->values()
            : collect();

        return [
            'period'              => $period,
            'registeredUsers'     => $registeredUsers,
            'totalMemorials'      => $totalMemorials,
            'activeMemorials'     => $activeMemorials,
            'activeSubscriptions' => $activeSubscriptions,
            'totalSalesAmount'    => $totalSalesAmount,
            'totalSalesCount'     => $totalSalesCount,
            'currency'            => $currency,
            'recentMemorials'     => $recentMemorials,
            'recentUsers'         => $recentUsers,
            'monthlyGrowth'       => $monthlyGrowth,
            'topMemorials'        => $topMemorials,
        ];
    }

    private function userMetrics(Request $request, $user): array
    {
        $memorialId = $request->get('memorial_id');

        $userMemorials = $user->memorials()->orderBy('full_name')->get(['id', 'full_name', 'slug', 'profile_photo_path', 'status']);

        if ($userMemorials->isEmpty()) {
            return [
                'userMemorials'    => $userMemorials,
                'selectedMemorial' => null,
                'userStats'        => null,
            ];
        }

        if ($memorialId && $memorialId !== 'all') {
            $memorialIds = $user->memorials()->where('id', $memorialId)->pluck('id');
            $selectedMemorial = $userMemorials->firstWhere('id', (int) $memorialId);
        } else {
            $memorialIds = $user->memorials()->pluck('id');
            $selectedMemorial = null;
            $memorialId = 'all';
        }

        $totalMemorials = $user->memorials()->count();

        $totalVisits = MemorialView::whereIn('memorial_id', $memorialIds)
            ->selectRaw('COUNT(DISTINCT visitor_hash) as cnt')
            ->value('cnt');

        $totalShares = MemorialShare::whereIn('memorial_id', $memorialIds)->count();

        $totalTributes = Tribute::whereIn('memorial_id', $memorialIds)->count();

        $totalChapters = StoryChapter::whereIn('memorial_id', $memorialIds)->count();

        $visitsToday = MemorialView::whereIn('memorial_id', $memorialIds)
            ->where('viewed_at', '>=', Carbon::today())
            ->selectRaw('COUNT(DISTINCT visitor_hash) as cnt')
            ->value('cnt');

        $visitsThisWeek = MemorialView::whereIn('memorial_id', $memorialIds)
            ->where('viewed_at', '>=', Carbon::now()->subDays(7))
            ->selectRaw('COUNT(DISTINCT visitor_hash) as cnt')
            ->value('cnt');

        $visitsThisMonth = MemorialView::whereIn('memorial_id', $memorialIds)
            ->where('viewed_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw('COUNT(DISTINCT visitor_hash) as cnt')
            ->value('cnt');

        $sharesByType = MemorialShare::whereIn('memorial_id', $memorialIds)
            ->select('share_type', DB::raw('COUNT(*) as count'))
            ->groupBy('share_type')
            ->pluck('count', 'share_type')
            ->toArray();

        $tributesByType = Tribute::whereIn('memorial_id', $memorialIds)
            ->select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $weeklyVisits = $this->weeklyVisitsData($memorialIds);

        $recentTributes = Tribute::with('user', 'memorial')
            ->whereIn('memorial_id', $memorialIds)
            ->latest()
            ->limit(5)
            ->get();

        return [
            'userMemorials'     => $userMemorials,
            'selectedMemorial'  => $selectedMemorial,
            'selectedMemorialId' => $memorialId,
            'userStats' => [
                'totalMemorials'  => $totalMemorials,
                'totalVisits'     => (int) $totalVisits,
                'totalShares'     => $totalShares,
                'totalTributes'   => $totalTributes,
                'totalChapters'   => $totalChapters,
                'visitsToday'     => (int) $visitsToday,
                'visitsThisWeek'  => (int) $visitsThisWeek,
                'visitsThisMonth' => (int) $visitsThisMonth,
                'sharesByType'    => $sharesByType,
                'tributesByType'  => $tributesByType,
                'weeklyVisits'    => $weeklyVisits,
                'recentTributes'  => $recentTributes,
            ],
        ];
    }

    private function resolveDateFrom(string $period): ?Carbon
    {
        return match ($period) {
            'today'      => Carbon::today(),
            'this_week'  => Carbon::now()->startOfWeek(),
            'this_month' => Carbon::now()->startOfMonth(),
            'this_year'  => Carbon::now()->startOfYear(),
            default      => null,
        };
    }

    private function monthlyGrowthData(): array
    {
        $year = Carbon::now()->year;

        $users = User::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at)')
            ->pluck('count', 'month')->toArray();

        $memorials = Memorial::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at)')
            ->pluck('count', 'month')->toArray();

        $months = [];
        $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = [
                'label'     => $monthLabels[$i - 1],
                'users'     => $users[$i] ?? 0,
                'memorials' => $memorials[$i] ?? 0,
            ];
        }

        return $months;
    }

    private function weeklyVisitsData($memorialIds): array
    {
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $count = MemorialView::whereIn('memorial_id', $memorialIds)
                ->whereDate('viewed_at', $date)
                ->selectRaw('COUNT(DISTINCT visitor_hash) as cnt')
                ->value('cnt');
            $days[] = [
                'label' => $date->format('D'),
                'date'  => $date->format('M d'),
                'count' => (int) $count,
            ];
        }
        return $days;
    }
}
