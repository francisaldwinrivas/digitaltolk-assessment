<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;
use DTApi\Repsitory\UserRepository;
use DTApi\Model\User;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function testCreateOrUpdateMethod()
    {
        $userRepository = new UserRepository(new User());

        // Total count of users
        $userTotal = User::all()->count();

        $userDefaultData = $this->getUserData();
        $newUser = $userRepostory->createOrUpdate($id = null, $userDefaultData);

        // Total count shouldve been incremented by 1 after
        // the previous Insert above
        $this->assertEquals($userTotal + 1, User::all()->count());

        // Newly inserted user should exist on our database
        $this->assertTrue(User::find($newUser->id)->exists());

        // Select an existing user
        // Update the name of the user to a new one
        $randomName = $this->faker->name // Some random name
        $existingUser = User::inRandomOrder()->first();
        $newUserData = array_merge([
            'name' => $randomName
        ], $existingUser->toArray());

        // Total count of users
        $userTotal = User::all()->count();

        $updatedUser = $userRepository->createOrUpdate($existingUser->id, $newUserData);

        // Total count of users should not be changed
        $this->assertEquals($userTotal, User::all()->count());

        // Verify that the name of the user has been updated
        $this->assertEquals($updatedUser->name, $randomName);

        // You can also verify that no other column has been updated
        $this->assertEquals($updatedUser->email, $existingUser->email);
        // so on...
    }

    /**
     * Assume this method returns all necessary user data
     * needed to be inserted on the database
     * 
     * @override method overrides the default values with the one
     * from the override array
     */
    private function getUserData($overrides = array()): array
    {
        // User data should be defined here
        $defaults = array();
        $defaults['some_field'] = 'Some value';
        // .. so on

        $data = array_merge($defaults, $overrides);
        return $data;
    }
}