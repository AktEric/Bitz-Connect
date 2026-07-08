<?php

namespace Database\Factories;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Hotel>
 */
class HotelFactory extends Factory
{
    protected $model = Hotel::class;

    public function definition(): array
    {
        $name = $this->faker->company() . ' Hotel';

        return [
            'uuid'          => (string) Str::uuid(),
            'name'          => $name,
            'slug'          => Str::slug($name) . '-' . Str::random(4),
            'description'   => $this->faker->paragraph(),
            'city'          => $this->faker->city(),
            'state'         => $this->faker->stateAbbr(),
            'country'       => 'Brasil',
            'website'       => $this->faker->url(),
            'phone'         => $this->faker->phoneNumber(),
            'contact_email' => $this->faker->safeEmail(),
        ];
    }
}
