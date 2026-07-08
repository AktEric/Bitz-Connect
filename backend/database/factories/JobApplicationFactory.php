<?php

namespace Database\Factories;

use App\Models\Job;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    protected $model = JobApplication::class;

    public function definition(): array
    {
        return [
            'uuid'         => (string) Str::uuid(),
            'job_id'       => Job::factory(),
            'user_id'      => User::factory(),
            'cover_letter' => $this->faker->optional()->paragraph(),
            'status'       => 'pending',
        ];
    }
}
