<?php

$factory->define(\App\Battle::class, function(\Faker\Generator $faker) {
    return [
                                                                                    'title' => $faker->word,
                                                                                    'body' => $faker->word,
                                                                                    'is_featured' => $faker->boolean,
                                        ];
});