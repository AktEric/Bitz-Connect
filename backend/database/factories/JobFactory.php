<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    protected $model = Job::class;

    public function definition(): array
    {
        return [
            'uuid'          => (string) Str::uuid(),
            'hotel_id'      => Hotel::factory(),
            'title'         => $this->faker->jobTitle(),
            'description'   => $this->faker->paragraph(),
            'requirements'  => $this->faker->sentence(),
            'benefits'      => $this->faker->sentence(),
            'contract_type' => $this->faker->randomElement(['clt', 'pj', 'freelance', 'internship']),
            'modality'      => $this->faker->randomElement(['on_site', 'remote', 'hybrid']),
            'city'          => $this->faker->city(),
            'state'         => $this->faker->stateAbbr(),
            'salary_range'  => null,
            'status'        => 'active',
        ];
    }

    /** Vaga ativa (padrão). */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'active']);
    }

    /** Vaga pausada. */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'paused']);
    }

    /** Vaga encerrada. */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'closed']);
    }
}
