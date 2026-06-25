<?php

namespace Database\Seeders\PortfolioDemo;

use App\Models\Owner;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoOwnersSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach ($this->owners() as $attributes) {
            Owner::query()->create(array_merge($attributes, [
                'is_active' => true,
            ]));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function owners(): array
    {
        return [
            [
                'name' => 'Sofia Ramirez',
                'email' => 'owner.sofia@maint.test',
                'phone' => '+57 300 400 0001',
                'document_number' => 'DEMO-OWNER-001',
                'address' => 'Calle 11 #20-30',
            ],
            [
                'name' => 'Daniel Torres',
                'email' => 'owner.daniel@maint.test',
                'phone' => '+57 300 400 0002',
                'document_number' => 'DEMO-OWNER-002',
                'address' => 'Carrera 15 #42-18',
            ],
            [
                'name' => 'Laura Gomez',
                'email' => 'owner.laura@maint.test',
                'phone' => '+57 300 400 0003',
                'document_number' => 'DEMO-OWNER-003',
                'address' => 'Avenida 68 #10-25',
            ],
            [
                'name' => 'Mateo Vargas',
                'email' => 'owner.mateo@maint.test',
                'phone' => '+57 300 400 0004',
                'document_number' => 'DEMO-OWNER-004',
                'address' => 'Calle 80 #12-45',
            ],
            [
                'name' => 'Valentina Rios',
                'email' => 'owner.valentina@maint.test',
                'phone' => '+57 300 400 0005',
                'document_number' => 'DEMO-OWNER-005',
                'address' => 'Carrera 7 #70-12',
            ],
            [
                'name' => 'Andres Moreno',
                'email' => 'owner.andres@maint.test',
                'phone' => '+57 300 400 0006',
                'document_number' => 'DEMO-OWNER-006',
                'address' => 'Calle 45 #19-21',
            ],
            [
                'name' => 'Camila Ortega',
                'email' => 'owner.camila@maint.test',
                'phone' => '+57 300 400 0007',
                'document_number' => 'DEMO-OWNER-007',
                'address' => 'Diagonal 32 #8-11',
            ],
            [
                'name' => 'Nicolas Herrera',
                'email' => 'owner.nicolas@maint.test',
                'phone' => '+57 300 400 0008',
                'document_number' => 'DEMO-OWNER-008',
                'address' => 'Transversal 21 #90-50',
            ],
        ];
    }
}
