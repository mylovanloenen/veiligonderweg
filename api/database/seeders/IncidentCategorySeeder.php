<?php

namespace Database\Seeders;

use App\Models\IncidentCategory;
use Illuminate\Database\Seeder;

class IncidentCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('veiligonderweg.incident_categories') as $category) {
            IncidentCategory::updateOrCreate(['slug' => $category['slug']], [
                'name' => $category['name'],
                'ttl_minutes' => $category['ttl_minutes'],
            ]);
        }
    }
}
