<?php

use App\Models\LeaveType;
use App\Models\User;

beforeEach(function () {
    // role and manager_id are not fillable, but factory states/attributes bypass mass assignment.
    $this->admin = User::factory()->admin()->create(['name' => 'Ahmed Admin', 'email' => 'admin@example.test']);
    $this->manager = User::factory()->manager()->create([
        'name' => 'Mariyam Manager',
        'email' => 'mariyam@example.test',
        'manager_id' => $this->admin->id,
    ]);
    $this->staff = User::factory()->create([
        'name' => 'Alice Staff',
        'email' => 'alice@example.test',
        'manager_id' => $this->manager->id,
    ]);
    $this->otherStaff = User::factory()->create([
        'name' => 'Bob Staff',
        'email' => 'bob@example.test',
        'manager_id' => $this->manager->id,
    ]);

    LeaveType::create(['name' => 'Annual', 'default_days' => 30]);
    LeaveType::create(['name' => 'Family Responsibility', 'default_days' => 10]);
});

it('redirects guests to the login page', function () {
    $this->get(route('admin.index'))->assertRedirect(route('login'));
});

it('forbids staff from the admin page and hides the nav link', function () {
    $this->actingAs($this->staff)->get(route('admin.index'))->assertForbidden();

    $this->actingAs($this->staff)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(route('admin.index'));
});

it('forbids managers from the admin page even though they pass the review gate', function () {
    $this->actingAs($this->manager)->get(route('admin.index'))->assertForbidden();
});

it('lets an admin see the nav link and open the admin page', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('admin.index'));

    $this->actingAs($this->admin)->get(route('admin.index'))->assertOk();
});

it('lists every user with their role and manager', function () {
    // assertOk also proves eager loading: four users load together, so a missing
    // with('manager') would throw LazyLoadingViolationException (lazy loading is prevented outside production).
    $this->actingAs($this->admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertSeeInOrder(['Alice Staff', 'alice@example.test', 'Staff', 'Mariyam Manager'])
        ->assertSeeInOrder(['Ahmed Admin', 'admin@example.test', 'Admin', 'None']);
});

it('lists the leave types with their default days', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertSee('Family Responsibility')
        ->assertSee('<td class="px-6 py-4">10</td>', false)
        ->assertSee('<td class="px-6 py-4">30</td>', false);
});
