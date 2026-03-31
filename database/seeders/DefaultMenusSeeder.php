<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class DefaultMenusSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMenu(Menu::LOCATION_HEADER, 'Main header', [
            ['label' => 'Home', 'route_name' => 'home'],
            ['label' => 'About', 'route_name' => 'about'],
            ['label' => 'Pricing', 'route_name' => 'pricing'],
            ['label' => 'Find Memorial', 'route_name' => 'memorial.directory'],
            ['label' => 'Contact', 'route_name' => 'contact'],
        ]);

        $this->seedMenu(Menu::LOCATION_FOOTER_QUICK, 'Quick Links', [
            ['label' => 'Home', 'route_name' => 'home'],
            ['label' => 'Find Memorial', 'route_name' => 'memorial.directory'],
            ['label' => 'Create Memorial', 'route_name' => 'memorial.create.step1'],
            ['label' => 'Pricing', 'route_name' => 'pricing'],
        ]);

        $this->seedMenu(Menu::LOCATION_FOOTER_COMPANY, 'Company', [
            ['label' => 'About Us', 'route_name' => 'about'],
            ['label' => 'Contact Us', 'route_name' => 'contact'],
            ['label' => 'Privacy Policy', 'route_name' => 'privacy-policy'],
            ['label' => 'Terms of Use', 'route_name' => 'terms-of-use'],
        ]);
    }

    /**
     * @param  array<int, array{label: string, route_name: string}>  $items
     */
    private function seedMenu(string $location, string $label, array $items): void
    {
        $menu = Menu::query()->firstOrCreate(
            ['location' => $location],
            ['label' => $label]
        );

        if ($menu->allItems()->exists()) {
            return;
        }

        foreach ($items as $i => $row) {
            MenuItem::query()->create([
                'menu_id' => $menu->id,
                'parent_id' => null,
                'label' => $row['label'],
                'url' => null,
                'route_name' => $row['route_name'],
                'route_parameters' => null,
                'open_in_new_tab' => false,
                'sort_order' => $i,
            ]);
        }
    }
}
