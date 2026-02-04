<?php

use Livewire\Volt\Component;
use App\Models\Game;
use App\Models\GameScore;
use Carbon\Carbon;

new class extends Component {
    public Game $match;

    // Form inputs
    public $team1_sets = [];
    public $team2_sets = [];
    public $winner_team_id;
    public $shuttles_used = 0;
    public $umpire;
    public $service_judge;

    public function mount(Game $match)
    {
        $this->match = $match;

        // Initialize sets based on 'best of' (e.g., if best of 3, show 3 potential sets)
        for ($i = 1; $i <= $this->match->bestof; $i++) {
            $this->team1_sets[$i] = 0;
            $this->team2_sets[$i] = 0;
        }

        $this->winner_team_id = $match->team1_id;
    }

    public function saveResult()
    {
        $this->validate([
            'winner_team_id' => 'required|exists:teams,id',
            'team1_sets.*' => 'required|integer|min:0',
            'team2_sets.*' => 'required|integer|min:0',
        ]);

        // 1. Update Match Status
        $this->match->update([
            'status' => 'completed',
            'winner_team_id' => $this->winner_team_id,
            'shuttles_used' => $this->shuttles_used,
            'empire_name' => $this->umpire,
            'service_judge_name' => $this->service_judge,
            'start_time' => $this->match->start_time ?? now(),
            'end_time' => now(),
        ]);

        // 2. Clear any existing scores (in case of re-entry)
        $this->match->scores()->delete();

        // 3. Save individual set scores
        $t1_match_points = 0;
        $t2_match_points = 0;

        foreach ($this->team1_sets as $index => $score1) {
            $score2 = $this->team2_sets[$index];

            // Only save the set if it was actually played (score > 0)
            if ($score1 > 0 || $score2 > 0) {
                $this->match->scores()->create([
                    'set_number' => $index,
                    'team1_score' => $score1,
                    'team2_score' => $score2,
                ]);

                if ($score1 > $score2)
                    $t1_match_points++;
                else
                    $t2_match_points++;
            }
        }

        // 4. Update the match aggregate points
        $this->match->update([
            'team1_points' => $t1_match_points,
            'team2_points' => $t2_match_points,
        ]);

        session()->flash('status', 'Match results updated successfully!');
        return $this->redirectRoute('event.matches', $this->match->event_id);
    }
}; ?>

<div class="p-6 max-w-4xl mx-auto">
    <header class="mb-8 bg-gradient-to-r from-gray-800 to-gray-900 p-6 rounded-xl text-white shadow-lg">
        <h1 class="text-2xl font-bold">Manual Result Entry</h1>
        <p class="opacity-75">{{ $match->name }} | SN# {{ $match->match_serial_number }}</p>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border dark:border-gray-700">
            <h2 class="text-lg font-bold text-blue-600">{{ $match->team1->name }}</h2>
            <p class="text-xs text-gray-500">Team 1</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border dark:border-gray-700">
            <h2 class="text-lg font-bold text-purple-600">{{ $match->team2->name }}</h2>
            <p class="text-xs text-gray-500">Team 2</p>
        </div>

        <div
            class="col-span-1 md:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-md border dark:border-gray-700">
            <h3 class="font-bold mb-4 border-b pb-2 dark:text-white">Set Scores</h3>

            @foreach($team1_sets as $index => $score)
                <div class="flex items-center gap-4 mb-4">
                    <span class="font-bold text-gray-400 w-12">Set {{ $index }}</span>
                    <flux:input type="number" wire:model="team1_sets.{{ $index }}" placeholder="Team 1 Score" />
                    <span class="text-xl font-bold">-</span>
                    <flux:input type="number" wire:model="team2_sets.{{ $index }}" placeholder="Team 2 Score" />
                </div>
            @endforeach
        </div>

        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-md border dark:border-gray-700">
            <h3 class="font-bold mb-4 dark:text-white">Officials & Stats</h3>
            <div class="space-y-4">
                <flux:input label="Umpire Name" wire:model="umpire" />
                <flux:input label="Service Judge" wire:model="service_judge" />
                <flux:input label="Shuttles Used" type="number" wire:model="shuttles_used" />
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-md border dark:border-gray-700">
            <h3 class="font-bold mb-4 dark:text-white">Final Outcome</h3>
            <flux:select label="Winner Team" wire:model="winner_team_id">
                <flux:select.option value="{{ $match->team1_id }}">{{ $match->team1->name }}</flux:select.option>
                <flux:select.option value="{{ $match->team2_id }}">{{ $match->team2->name }}</flux:select.option>
            </flux:select>

            <div class="mt-8">
                <flux:button variant="primary" color="green" wire:click="saveResult" class="w-full">
                    Submit Final Result
                </flux:button>
            </div>
        </div>
    </div>
</div>