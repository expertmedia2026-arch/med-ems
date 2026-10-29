<?php

namespace Tests\Feature\Api\V1;

use App\Enums\Role;
use App\Models\Hospital;
use App\Models\HospitalModule;
use App\Models\HospitalSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MortuaryCase;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MortuaryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_when_mortuary_api_is_requested_without_authentication(): void
    {
        $this->getJson('/api/v1/mortuary')->assertUnauthorized();
    }

    public function test_mortuary_attendant_can_create_receive_move_and_release_a_walk_in_case(): void
    {
        $hospital = $this->createHospital();
        $attendant = $this->createStaff($hospital, Role::MortuaryAttendant);
        Sanctum::actingAs($attendant);

        $created = $this->postJson('/api/v1/mortuary/walk-in', [
            'first_name' => 'Morgan',
            'last_name' => 'Taylor',
            'gender' => 'unknown',
            'estimated_age' => 46,
            'deceased_at' => now()->subHours(2)->toDateTimeString(),
            'external_contact_name' => 'Alex Taylor',
            'external_contact_phone' => '555-0101',
            'external_contact_address' => '12 Sample Street',
            'handoff_note' => 'Transferred from a community facility.',
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.status', 'awaiting_transfer')
            ->assertJsonPath('data.source', 'walk_in')
            ->assertJsonPath('data.patient.identity_status', 'external_mortuary')
            ->assertJsonPath('data.fees.intake', 0);
        $this->assertNotNull($created->json('data.invoice.id'));
        $caseId = $created->json('data.id');

        $this->postJson("/api/v1/mortuary/{$caseId}/receive", [
            'storage_location' => 'Cold room A, shelf 2',
            'body_tag_number' => 'MOR-TAG-0001',
            'handoff_note' => 'Identity documents received.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'received');

        $this->postJson("/api/v1/mortuary/{$caseId}/move", [
            'storage_location' => 'Cold room B, shelf 1',
            'note' => 'Moved to the lower-temperature room.',
        ])
            ->assertOk()
            ->assertJsonPath('data.storage_location', 'Cold room B, shelf 1');

        $this->postJson("/api/v1/mortuary/{$caseId}/release", [
            'released_to' => 'Alex Taylor',
            'recipient_relationship' => 'Sibling',
            'recipient_id_reference' => 'ID-123',
            'release_authority' => 'Police clearance',
            'release_note' => 'Released to the verified next of kin.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'released')
            ->assertJsonPath('data.released_to', 'Alex Taylor');

        $this->assertDatabaseHas('mortuary_cases', [
            'id' => $caseId,
            'status' => 'released',
            'storage_location' => 'Cold room B, shelf 1',
            'body_tag_number' => 'MOR-TAG-0001',
            'released_by' => $attendant->id,
        ]);
        $this->assertDatabaseHas('mortuary_case_events', [
            'mortuary_case_id' => $caseId,
            'event_type' => 'location_changed',
        ]);

        $this->getJson("/api/v1/mortuary/{$caseId}")
            ->assertOk()
            ->assertJsonPath('data.events.0.type', 'released')
            ->assertJsonPath('data.invoice.id', $created->json('data.invoice.id'));

        $this->getJson('/api/v1/mortuary?status=released')
            ->assertOk()
            ->assertJsonPath('data.cases.data.0.id', $caseId)
            ->assertJsonPath('data.cases.current_page', 1);
    }

    public function test_unpaid_intake_fee_prevents_mortuary_receipt(): void
    {
        $hospital = $this->createHospital();
        HospitalSetting::create([
            'hospital_id' => $hospital->id,
            'name' => 'Mortuary Billing Hospital',
            'mortuary_intake_fee' => 45,
        ]);
        $attendant = $this->createStaff($hospital, Role::MortuaryAttendant);
        $patient = $this->createPatient($hospital);
        $case = MortuaryCase::create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'source' => 'walk_in',
            'status' => 'awaiting_transfer',
            'deceased_at' => now()->subHour(),
            'opened_by' => $attendant->id,
            'intake_fee' => 45,
            'daily_storage_fee' => 0,
            'state_tax_daily_fee' => 0,
        ]);
        Sanctum::actingAs($attendant);

        $this->postJson("/api/v1/mortuary/{$case->id}/receive", [
            'storage_location' => 'Cold room A',
        ])
            ->assertPaymentRequired()
            ->assertJsonPath('message', 'The intake fee must be paid before the body can be received into mortuary custody.');

        $this->assertDatabaseHas('mortuary_cases', [
            'id' => $case->id,
            'status' => 'awaiting_transfer',
        ]);
    }

    public function test_unpaid_mortuary_charges_prevent_release(): void
    {
        $hospital = $this->createHospital();
        $attendant = $this->createStaff($hospital, Role::MortuaryAttendant);
        $patient = $this->createPatient($hospital);
        $invoice = Invoice::create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
        ]);
        $case = MortuaryCase::create([
            'hospital_id' => $hospital->id,
            'patient_id' => $patient->id,
            'invoice_id' => $invoice->id,
            'source' => 'walk_in',
            'status' => 'received',
            'deceased_at' => now()->subDay(),
            'opened_by' => $attendant->id,
            'received_by' => $attendant->id,
            'received_at' => now()->subHour(),
            'storage_billed_through' => now(),
            'storage_location' => 'Cold room A',
            'intake_fee' => 45,
            'daily_storage_fee' => 0,
            'state_tax_daily_fee' => 0,
        ]);
        InvoiceItem::create([
            'hospital_id' => $hospital->id,
            'invoice_id' => $invoice->id,
            'category' => 'mortuary',
            'description' => 'Mortuary intake fee',
            'quantity' => 1,
            'unit_price' => 45,
            'billable_type' => $case->getMorphClass(),
            'billable_id' => $case->id,
        ]);
        Sanctum::actingAs($attendant);

        $this->postJson("/api/v1/mortuary/{$case->id}/release", [
            'released_to' => 'Alex Taylor',
            'recipient_relationship' => 'Sibling',
            'release_authority' => 'Police clearance',
        ])
            ->assertPaymentRequired()
            ->assertJsonPath('message', 'Pay all mortuary intake, storage, and State tax charges on the linked invoice before release.');

        $this->assertDatabaseHas('mortuary_cases', [
            'id' => $case->id,
            'status' => 'received',
        ]);
    }

    public function test_mortuary_attendant_cannot_view_a_case_from_another_hospital(): void
    {
        $hospital = $this->createHospital();
        $attendant = $this->createStaff($hospital, Role::MortuaryAttendant);
        $otherHospital = $this->createHospital('other-mortuary-hospital');
        $otherAttendant = $this->createStaff($otherHospital, Role::MortuaryAttendant);
        $patient = $this->createPatient($otherHospital);
        $case = MortuaryCase::create([
            'hospital_id' => $otherHospital->id,
            'patient_id' => $patient->id,
            'source' => 'walk_in',
            'status' => 'awaiting_transfer',
            'deceased_at' => now(),
            'opened_by' => $otherAttendant->id,
        ]);
        Sanctum::actingAs($attendant);

        $this->getJson("/api/v1/mortuary/{$case->id}")->assertNotFound();
    }

    private function createHospital(string $slug = 'mortuary-test-hospital'): Hospital
    {
        $hospital = Hospital::create([
            'name' => 'Mortuary Test Hospital',
            'slug' => $slug,
            'is_active' => true,
        ]);
        HospitalModule::create([
            'hospital_id' => $hospital->id,
            'module' => 'mortuary',
            'enabled' => true,
        ]);

        return $hospital;
    }

    private function createStaff(Hospital $hospital, Role $role): User
    {
        return User::factory()->create([
            'hospital_id' => $hospital->id,
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function createPatient(Hospital $hospital): Patient
    {
        return Patient::create([
            'hospital_id' => $hospital->id,
            'hospital_number' => 'MOR-'.fake()->unique()->numerify('#####'),
            'first_name' => 'Case',
            'last_name' => 'Patient',
            'gender' => 'unknown',
            'date_of_birth' => '1980-01-01',
            'identity_status' => 'external_mortuary',
        ]);
    }
}
