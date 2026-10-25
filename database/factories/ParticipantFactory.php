<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Participant>
 */
class ParticipantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $skills = [
            'Right Hand Batsman',
            'Left Hand Batsman',
            'Right-Arm Fast',
            'Left-Arm Fast',
            'All Rounder',
            'Right Arm Leg Spin',
            'Left Arm Spinner',
            'Off Spinner',
            'Wicket Keeper'
        ];

        return [
            'full_name' => $this->faker->name(),
            'nick_name' => $this->faker->firstName(),
            'passport_picture' => 'passports/default.jpg',
            'id_picture' => 'ids/default.jpg',
            'skill_categories' => $this->faker->randomElements($skills, rand(1, 3)),
            'performance' => $this->faker->paragraph(),
            'city' => $this->faker->city(),
            'address' => $this->faker->address(),
            'mobile' => $this->faker->numerify('##########'),
            'email' => $this->faker->unique()->safeEmail(),
            'dob' => $this->faker->dateTimeBetween('-50 years', '-18 years'),
            'nationality' => $this->faker->randomElement(['USA', 'UK', 'Australia', 'Pakistan', 'Sri Lanka', 'Bangladesh', 'South Africa']),
            'identity' => $this->faker->regexify('[A-Z0-9]{12}'),
            'kit_size' => $this->faker->randomElement(['small', 'medium', 'large', 'xl', 'xxl']),
            'shirt_number' => $this->faker->numerify('##'),
            'airline' => $this->faker->optional()->word(),
            'arrival_date' => $this->faker->optional()->dateTime(),
            'arrival_time' => $this->faker->optional()->time(),
            'hotel_name' => $this->faker->optional()->company(),
            'checkin' => $this->faker->optional()->date(),
            'checkout' => $this->faker->optional()->date(),
        ];
    }
}
