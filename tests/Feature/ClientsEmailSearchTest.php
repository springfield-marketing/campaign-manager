<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\ListClients;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientsEmailSearchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_email_filter_finds_contacts_by_any_email_case_insensitively(): void
    {
        $this->actingAs(User::factory()->create());

        $alice = Client::create(['full_name' => 'Alice', 'is_institution' => false]);
        $alice->emails()->create(['email' => 'Alice@Example.com', 'is_primary' => true]);

        $bob = Client::create(['full_name' => 'Bob', 'is_institution' => false]);
        $bob->emails()->create(['email' => 'bob@test.com', 'is_primary' => true]);

        Livewire::test(ListClients::class)
            ->loadTable()
            ->filterTable('email_search', ['email' => 'alice@example'])
            ->assertCanSeeTableRecords([$alice])
            ->assertCanNotSeeTableRecords([$bob]);
    }

    #[Test]
    public function it_matches_a_non_primary_email_too(): void
    {
        $this->actingAs(User::factory()->create());

        $client = Client::create(['full_name' => 'Carol', 'is_institution' => false]);
        $client->emails()->create(['email' => 'carol@primary.com', 'is_primary' => true]);
        $client->emails()->create(['email' => 'carol.work@company.com', 'is_primary' => false]);

        Livewire::test(ListClients::class)
            ->loadTable()
            ->filterTable('email_search', ['email' => 'carol.work'])
            ->assertCanSeeTableRecords([$client]);
    }
}
