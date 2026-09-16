<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PersonFactory extends Factory
{
    public function definition(): array
    {
        return ['public_id' => Str::uuid(), 'first_name' => fake()->firstName(), 'last_name' => fake()->lastName(), 'email' => fake()->unique()->safeEmail()];
    }
}
