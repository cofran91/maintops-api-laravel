<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workshop>
 */
class WorkshopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' Workshop';

        return [
            'manager_user_id' => User::factory(),
            'name' => $name,
            'code' => Str::upper(Str::slug($name, '-')),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'weekly_schedule' => [
                'monday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
                'tuesday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
                'wednesday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
                'thursday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
                'friday' => ['opens_at' => '08:00', 'closes_at' => '17:00'],
            ],
            'is_active' => true,
        ];
    }
}
