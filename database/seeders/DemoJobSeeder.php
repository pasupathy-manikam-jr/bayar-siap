<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\Milestone;
use App\Models\User;
use App\Services\JobFlow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo data for both quick logins, all created through JobFlow so the audit log, notifications and statuses are real.
 * Demo Renovation (Free): one active job with something on every client screen.
 * Bina Jaya (Solo): a book of jobs in every state, with dates spread over the past two months.
 */
class DemoJobSeeder extends Seeder
{
    private CarbonImmutable $today;

    public function run(JobFlow $flow): void
    {
        $this->today = CarbonImmutable::now();
        $this->demoRenovation($flow);
        $this->binaJaya($flow);
        Date::setTestNow();
    }

    private function demoRenovation(JobFlow $flow): void
    {
        $this->at(0);
        $owner = User::where('email', DatabaseSeeder::LOGINS[0]['email'])->firstOrFail();
        $owner->company->update(['bank_name' => 'Maybank', 'bank_account_name' => $owner->company->name, 'bank_account_no' => '5140 1234 5678']);
        $this->logo($owner->company, 'DR', '#1F4E79', '#FFFFFF');
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Puan Siti Aminah', 'phone' => '+60 12-345 6789', 'email' => 'siti@example.com']);

        $job = $flow->createJob($owner, [
            'company_id' => $owner->company_id,
            'client_id' => $client->id,
            'title' => 'Kitchen renovation',
            'site_address' => '12 Jalan Mawar 3, Seksyen 7, 40000 Shah Alam',
            'quote_cents' => 4_850_000,
            'start_date' => now()->subWeeks(3)->toDateString(),
            'target_date' => now()->addWeeks(5)->toDateString(),
        ], config('jobproof.milestone_templates.renovation.milestones'));

        $flow->sendJob($job, $owner);
        $flow->acceptJob($job->refresh(), $client);
        [$deposit, $demolition, $fitOut, $handover] = $job->milestones()->get();

        // Milestone 1: proved, approved, paid and confirmed.
        $this->proveAndRequest($flow, $owner, $deposit, 'Signed quotation and site handover', 'site');
        $flow->approveRequest($deposit->openRequest()->firstOrFail(), $client);
        $paid = $flow->recordPayment($job->refresh(), $owner, ['milestone_id' => $deposit->id, 'amount_cents' => $deposit->amount_cents, 'paid_on' => now()->subWeeks(2)->toDateString(), 'method' => 'transfer', 'reference' => 'MBB 88213']);
        $flow->confirmPayment($paid, $client);

        // Milestone 2: waiting for the client's approval.
        $this->proveAndRequest($flow, $owner, $demolition->refresh(), 'Old cabinets removed, wiring roughed in', 'kitchen');

        // A variation order to sign and a payment to confirm.
        $vo = $flow->createVariationOrder($job, $owner, ['description' => 'Add a tall pantry cabinet beside the fridge', 'reason' => 'Requested by client on site visit', 'amount_cents' => 280_000]);
        $flow->sendVariationOrder($vo, $owner);
        $flow->recordPayment($job, $owner, ['milestone_id' => $demolition->id, 'amount_cents' => 500_000, 'paid_on' => now()->subDay()->toDateString(), 'method' => 'cash', 'reference' => 'Advance']);

        // Not started yet, but already documented: site measurements and the agreed finish.
        $this->photos($flow, $owner, $fitOut, 'Tile and cabinet samples agreed on site', ['finishes-1', 'kitchen-1']);
        $this->photos($flow, $owner, $handover, 'Reference: finished kitchen the client chose', ['handover-1', 'kitchen-2']);

        $this->command->info('Client link: '.$job->shareUrl().' (OTP codes are written to storage/logs/laravel.log)');
    }

    private function binaJaya(JobFlow $flow): void
    {
        $this->at(60);
        $owner = User::where('email', DatabaseSeeder::LOGINS[1]['email'])->firstOrFail();
        $owner->company->forceFill(['plan' => 'solo', 'bank_name' => 'CIMB Bank', 'bank_account_name' => 'Bina Jaya Construction Sdn Bhd', 'bank_account_no' => '8007 4455 1290'])->save();
        $this->logo($owner->company, 'BJ', '#B91C1C', '#FFFFFF');
        $client = fn (string $name, string $phone, ?string $email = null) => Client::create(['company_id' => $owner->company_id, 'name' => $name, 'phone' => $phone, 'email' => $email]);
        $newJob = fn (Client $c, string $title, string $address, int $quoteCents, array $milestones, int $weeks) => $flow->createJob($owner, [
            'company_id' => $owner->company_id, 'client_id' => $c->id, 'title' => $title, 'site_address' => $address, 'quote_cents' => $quoteCents,
            'start_date' => now()->toDateString(), 'target_date' => now()->addWeeks($weeks)->toDateString(),
        ], $milestones);

        // 1. Completed: every milestone paid and confirmed.
        $tan = $client('Mr Tan Wei Ming', '016-778 2301', 'weiming.tan@example.com');
        $roof = $newJob($tan, 'Roof replacement', '8 Jalan Melawati 2, Taman Melawati, 53100 Kuala Lumpur', 2_860_000, config('jobproof.milestone_templates.installation.milestones'), 3);
        $flow->sendJob($roof, $owner);
        $this->at(59);
        $flow->acceptJob($roof->refresh(), $tan);
        foreach ($roof->milestones()->get() as $i => $milestone) {
            $this->at(56 - $i * 14);
            $this->proveAndRequest($flow, $owner, $milestone, $i === 0 ? 'Old roof stripped, new battens in' : 'Metal deck roofing complete, gutters fitted', 'roof');
            $this->at(55 - $i * 14);
            $flow->approveRequest($milestone->openRequest()->firstOrFail(), $tan);
            $payment = $flow->recordPayment($roof->refresh(), $owner, ['milestone_id' => $milestone->id, 'amount_cents' => $milestone->amount_cents, 'paid_on' => now()->toDateString(), 'method' => 'transfer', 'reference' => 'CIMB '.(70120 + $i)]);
            $flow->confirmPayment($payment, $tan);
        }

        // 2. Active extension: paid, verbally approved, rejected, open request, signed / unsigned / draft VOs, a dispute.
        $this->at(45);
        $aziz = $client('Encik Aziz bin Rahman', '012-901 4477', 'aziz.rahman@example.com');
        $ext = $newJob($aziz, 'Double-storey rear extension', '27 Jalan Sutera 5, Taman Sutera, 43000 Kajang', 18_500_000, config('jobproof.milestone_templates.house_build.milestones'), 20);
        $flow->sendJob($ext, $owner);
        $this->at(44);
        $flow->acceptJob($ext->refresh(), $aziz);
        [$deposit, $foundation, $structure, $roofing, $brickwork, $mechanical, $finishes] = $ext->milestones()->get()->all();

        $this->at(42);
        $this->proveAndRequest($flow, $owner, $deposit, 'Site cleared, hoarding up', 'site');
        $flow->approveRequest($deposit->openRequest()->firstOrFail(), $aziz);
        $flow->confirmPayment($flow->recordPayment($ext->refresh(), $owner, ['milestone_id' => $deposit->id, 'amount_cents' => $deposit->amount_cents, 'paid_on' => now()->toDateString(), 'method' => 'transfer', 'reference' => 'MBB 551209']), $aziz);

        $this->at(30);
        $this->proveAndRequest($flow, $owner, $foundation, 'Footings poured and cured', 'foundation');
        $this->at(29);
        $flow->approveVerbally($foundation->openRequest()->firstOrFail(), $owner, 'Encik Aziz approved on site visit, 2pm, with the architect present');
        $flow->confirmPayment($flow->recordPayment($ext, $owner, ['milestone_id' => $foundation->id, 'amount_cents' => $foundation->amount_cents, 'paid_on' => now()->toDateString(), 'method' => 'cheque', 'reference' => 'Cheque 004512']), $aziz);

        $this->at(21);
        $signed = $flow->createVariationOrder($ext, $owner, ['description' => 'Extra power points and a 15A socket for the kitchen', 'reason' => 'Requested by client', 'amount_cents' => 650_000]);
        $flow->sendVariationOrder($signed, $owner);
        $this->at(20);
        $flow->signVariationOrder($signed, $aziz, 'Aziz bin Rahman', null);

        $this->at(14);
        $this->proveAndRequest($flow, $owner, $structure, 'Columns and beams cast, level 1', 'structure');
        $this->at(13);
        $flow->rejectRequest($structure->openRequest()->firstOrFail(), $aziz, 'Column C3 has visible honeycombing. Please patch it before I pay.');
        $this->at(10);
        $flow->comment($structure, $owner, 'Patched C3 with non-shrink grout. New photos added, will re-request after it cures.');

        $this->at(6);
        $this->proveAndRequest($flow, $owner, $roofing, 'Roof trusses up', 'roof');
        $this->at(5);
        $flow->comment($roofing, $aziz, 'Can you send a photo of the truss connections from the back?');
        $flow->recordPayment($ext, $owner, ['milestone_id' => $roofing->id, 'amount_cents' => 200_000, 'paid_on' => now()->toDateString(), 'method' => 'cash', 'reference' => 'Advance for roofing sheets']);
        $this->at(4);
        $flow->disputePayment($ext->payments()->where('client_status', 'pending')->firstOrFail(), $aziz, 'I paid RM 1,500 in cash, not RM 2,000.');

        $this->at(3);
        $deduction = $flow->createVariationOrder($ext, $owner, ['description' => 'Client supplies own floor tiles', 'reason' => 'Agreed at site meeting', 'amount_cents' => -320_000, 'milestone_id' => $finishes->id]);
        $flow->sendVariationOrder($deduction, $owner);
        $this->at(1);
        $flow->createVariationOrder($ext, $owner, ['description' => 'Wet kitchen canopy extension (3m x 2m)', 'amount_cents' => 480_000]);

        // Upcoming stages are documented too: mock-ups, conduit runs and the agreed tiles.
        $this->photos($flow, $owner, $brickwork, 'Plaster sample panel on the side wall', ['plaster-1', 'plaster-2']);
        $this->photos($flow, $owner, $mechanical, 'Conduit routes marked before closing the ceiling', ['electrical-1', 'electrical-2']);
        $this->photos($flow, $owner, $finishes, 'Tile selection and floor setting-out', ['finishes-1', 'finishes-2']);
        $this->photos($flow, $owner, $ext->milestones()->whereNotNull('variation_order_id')->firstOrFail(), 'Socket positions marked for VO-001', ['electrical-2', 'electrical-1']);

        // 3. Sent, waiting for the client to accept (old enough for the 48-hour reminder).
        $this->at(3);
        $nurul = $client('Puan Nurul Huda', '019-332 8810');
        $flow->sendJob($newJob($nurul, 'Aircon installation, 3 units', 'B-12-3 Residensi Harmoni, Jalan Ampang, 50450 Kuala Lumpur', 960_000, config('jobproof.milestone_templates.installation.milestones'), 1), $owner);

        // 4. Draft, not sent yet.
        $this->at(0);
        $lim = $client('Mr Lim Chee Keong', '017-445 9902', 'cheekeong.lim@example.com');
        $newJob($lim, 'Fencing and auto gate', 'Lot 432, Jalan Bukit Beruntung, 48300 Rawang', 1_420_000, [
            ['name' => 'Deposit', 'percent' => 40],
            ['name' => 'Fence panels installed', 'percent' => 40],
            ['name' => 'Auto gate commissioned', 'percent' => 20],
        ], 4);

        // 5. Cancelled after it was sent.
        $this->at(25);
        $pool = $newJob($tan, 'Swimming pool, 8m x 4m', '8 Jalan Melawati 2, Taman Melawati, 53100 Kuala Lumpur', 9_800_000, [
            ['name' => 'Deposit', 'percent' => 20], ['name' => 'Excavation and shell', 'percent' => 50], ['name' => 'Tiling and filtration', 'percent' => 30],
        ], 10);
        $flow->sendJob($pool, $owner);
        $this->at(22);
        $flow->cancelJob($pool->refresh(), $owner, 'Client postponed the pool until next year.');
    }

    /** Move the clock to a past day (at 10am-ish) so dates in the feed look like real work. */
    private function at(int $daysAgo): void
    {
        Date::setTestNow($this->today->subDays($daysAgo)->setTime(10, 0)->addMinutes(random_int(0, 360)));
    }

    /** Two real site photos for the stage (database/seeders/photos, credits.json lists source and licence). */
    private function proveAndRequest(JobFlow $flow, User $owner, Milestone $milestone, string $caption, string $topic): void
    {
        $flow->startMilestone($milestone, $owner);
        $this->photos($flow, $owner, $milestone, $caption, ["{$topic}-1", "{$topic}-2"]);
        $flow->requestPayment($milestone->refresh(), $owner, "Please review {$milestone->name}.");
    }

    /**
     * Real site photos from database/seeders/photos (credits.json lists source and licence).
     *
     * @param  list<string>  $files  photo names without .jpg
     */
    private function photos(JobFlow $flow, User $owner, Milestone $milestone, string $caption, array $files): void
    {
        foreach ($files as $i => $file) {
            $path = "proofs/{$owner->company_id}/{$milestone->id}/".Str::uuid().'.jpg';
            Storage::put($path, $jpeg = (string) file_get_contents(__DIR__."/photos/{$file}.jpg"));
            $flow->addProof($milestone, $owner, [
                'type' => 'photo', 'path' => $path, 'sha256' => hash('sha256', $jpeg),
                'captured_at' => now()->subDays(3)->addHours($i + 1), 'lat' => 3.0733, 'lng' => 101.5185,
                'caption' => $i === 0 ? $caption : null,
            ]);
        }
    }

    /** A simple monogram logo, so the client pages and header show the contractor's mark. */
    private function logo(Company $company, string $initials, string $background, string $foreground): void
    {
        $path = "logos/{$company->id}/logo.svg";
        Storage::put($path, <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96"><rect width="96" height="96" rx="14" fill="{$background}"/><path d="M14 40 48 16l34 24" fill="none" stroke="{$foreground}" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/><text x="48" y="76" font-family="Arial, Helvetica, sans-serif" font-size="32" font-weight="700" text-anchor="middle" fill="{$foreground}">{$initials}</text></svg>
            SVG);
        $company->update(['logo_path' => $path]);
    }
}
