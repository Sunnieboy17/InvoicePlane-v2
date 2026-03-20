<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\StandardNotice;

/**
 * @extends Factory<StandardNotice>
 */
class StandardNoticeFactory extends Factory
{
    protected $model = StandardNotice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => null, // Wird vom BelongsToCompany Trait gesetzt
            'code'        => 'NOTICE-' . $this->faker->unique()->numerify('###'),
            'title'       => $this->faker->sentence(3),
            'body'        => $this->faker->paragraph(),
            'type'        => $this->faker->randomElement([
                StandardNotice::TYPE_GENERAL_NOTICE,
                StandardNotice::TYPE_TAX_NOTICE,
                StandardNotice::TYPE_PAYMENT_NOTICE,
            ]),
            'trigger'     => null,
            'is_active'   => true,
        ];
    }

    /**
     * Indicate that the notice is a tax notice.
     */
    public function taxNotice(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'    => StandardNotice::TYPE_TAX_NOTICE,
            'trigger' => 'tax_code:DE-KLEIN-0',
        ]);
    }

    /**
     * Indicate that the notice is a payment notice.
     */
    public function paymentNotice(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'    => StandardNotice::TYPE_PAYMENT_NOTICE,
            'trigger' => null,
        ]);
    }

    /**
     * Indicate that the notice is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
