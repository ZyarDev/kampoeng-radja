<?php
namespace Database\Seeders;
use App\Models\Departemen;
use Illuminate\Database\Seeder;
class DepartemenSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Management', 'Marcom', 'Marketing', 'OPS 1', 'OPS 2'] as $name) {
            Departemen::firstOrCreate(['nama_departemen' => $name]);
        }
    }
}
