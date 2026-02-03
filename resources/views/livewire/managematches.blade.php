<?php

use Livewire\Volt\Component;
use App\Models\Game;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\Round;
use App\Models\Player;
use Flux\Flux;
use Illuminate\Support\Facades\DB;


new class extends Component {

    public $event;
    public $numberOfPlayers;
    public $rounds = [];
    public $round;
    public $matches = [];
    public $players = [];
    public $selectedMatch;
    public $max_rounds = 1;
    public $team1 = [];
    public $team2 = [];
    public $match_date;

    public $bracketSize; // 64 or 128




    public $isDoubles = 0;

    public function mount(Event $eventid)
    {
        $this->event = $eventid->load('players', 'rounds.games');

        $this->players = $this->event->players ?? collect();
        $this->rounds = $this->event->rounds ?? collect();

        // Safely fetch first round (or null)
        $this->round = $this->rounds->first();

        // Safely fetch matches (empty collection if no rounds exist)
        $this->matches = $this->round?->games ?? collect();

        $this->numberOfPlayers = $this->players->count();
    }

    private function getBracketOptions($playersCount)
    {
        $options = [];

        $power = 1;
        while ($power <= $playersCount) {
            if ($power >= 2) {
                $options[] = $power;
            }
            $power *= 2;
        }

        // Also allow next higher bracket (byes)
        if (!in_array($power, $options)) {
            $options[] = $power;
        }

        return $options;
    }


    public function getList()
    {
        $this->round = $this->event->rounds[0] ?? collect();
        $this->matches = $this->round->games ?? collect();
        $this->numberOfPlayers = $this->players->count();

    }

    public function playersRoundCount()
    {
        return (int) ceil(log($this->numberOfPlayers, 2));
    }


    public function generateRoundsAndMatches()
    {
        $playersCount = (int) $this->numberOfPlayers;

        // Singles / Doubles
        $this->isDoubles = ($this->isDoubles == 1);

        // Convert players to teams if doubles
        if ($this->isDoubles) {
            $playersCount = intdiv($playersCount, 2);
        }

        // Basic validation
        if ($playersCount < 2) {
            session()->flash('error', 'Not enough participants.');
            return;
        }

        // Bracket size must be selected
        if (!$this->bracketSize) {
            session()->flash('error', 'Please select a bracket size (e.g. 64 or 128).');
            return;
        }

        $slots = (int) $this->bracketSize;

        // Prevent invalid bracket
        if ($slots < 2 || ($slots & ($slots - 1)) !== 0) {
            session()->flash('error', 'Bracket size must be a power of 2.');
            return;
        }

        if ($slots < $playersCount) {
            session()->flash(
                'error',
                "Bracket size ($slots) is smaller than participants ($playersCount). Reduce players or run qualifiers."
            );
            return;
        }

        // Prevent regeneration
        if (Game::where('event_id', $this->event->id)->exists()) {
            session()->flash('error', 'Matches already exist for this event.');
            return;
        }

        // Best-of safety
        $this->max_rounds = max(1, (int) $this->max_rounds);

        DB::transaction(function () use ($slots) {

            $totalRounds = (int) log($slots, 2);

            // Build round names
            $roundNames = [];
            if ($totalRounds == 1) {
                $roundNames = ['Final'];
            } elseif ($totalRounds == 2) {
                $roundNames = ['Semifinal', 'Final'];
            } elseif ($totalRounds == 3) {
                $roundNames = ['Quarterfinal', 'Semifinal', 'Final'];
            } else {
                for ($i = 1; $i <= $totalRounds - 3; $i++) {
                    $roundNames[] = "Round $i";
                }
                $roundNames[] = 'Quarterfinal';
                $roundNames[] = 'Semifinal';
                $roundNames[] = 'Final';
            }

            // Create rounds
            for ($i = 1; $i <= $totalRounds; $i++) {

                $matchesCount = pow(2, $totalRounds - $i);

                $round = Round::create([
                    'name' => $roundNames[$i - 1] ?? "Round $i",
                    'round_number' => $i,
                    'matches_count' => $matchesCount,
                    'event_id' => $this->event->id,
                ]);

                // Create matches ONLY for first round
                if ($i === 1) {
                    for ($j = 1; $j <= $matchesCount; $j++) {
                        Game::create([
                            'round_id' => $round->id,
                            'event_id' => $this->event->id,
                            'player1_id' => null,
                            'player2_id' => null,
                            'is_doubles' => $this->isDoubles,
                            'bestof' => $this->max_rounds,
                        ]);
                    }

                    // Sync match count (safety)
                    $round->update([
                        'matches_count' => Game::where('round_id', $round->id)->count()
                    ]);
                }
            }
        });

        Flux::modal('match-generator')->close();
        $this->getList();

        session()->flash(
            'message',
            "Tournament created using {$this->bracketSize}-slot bracket successfully!"
        );
    }




    public function getRound($roundId)
    {
        $round = Round::find($roundId);
        if ($round) {
            $this->round = $round;
            $this->matches = $round->games;
        }

    }






    public function openMatch($id)
    {
        $match = Game::find($id);

        if ($match) {
            $this->selectedMatch = $match;
            if ($match->team1 && $match->team2) {
                $this->team1 = $match->team1->players->pluck('id')->toArray(); // get player IDs as array
                $this->team2 = $match->team2->players->pluck('id')->toArray();
            } else {
                if ($match->is_doubles) {
                    $this->team1 = [1 => $this->players[0]->id, 2 => $this->players[0]->id];
                    $this->team2 = [1 => $this->players[0]->id, 2 => $this->players[0]->id];

                } else {
                    $this->team1 = [1 => $this->players[0]->id];
                    $this->team2 = [1 => $this->players[0]->id];
                }
            }
            $this->match_date = \Carbon\Carbon::parse($match->match_date)->format('Y-m-d');

            Flux::modal('add-match')->show();

        }
    }

    public function dataCheck(): bool
    {
        // Basic validation for required fields
        $var = $this->validate([
            'team1' => 'required|array',
            'team2' => 'required|array',
            'match_date' => 'required|date',

        ]);
        $hasError = false;

        // Check duplicates inside team1
        if (count($this->team1) !== count(array_unique($this->team1))) {
            $this->addError('team1.1', 'Team 1 contains duplicate players.');
            $this->addError('team1.2', 'Team 1 contains duplicate players.');
            $hasError = true;
        }
        // dd('team1 checked', $hasError);


        // Check duplicates inside team2
        if (count($this->team2) !== count(array_unique($this->team2))) {
            $this->addError('team2.1', 'Team 2 contains duplicate players.');
            $this->addError('team2.2', 'Team 2 contains duplicate players.');
            $hasError = true;
        }
        // dd('team2 checked', $hasError);


        // Check overlap between team1 and team2
        if (!empty(array_intersect($this->team1, $this->team2))) {
            $this->addError('team1.1', 'A player cannot appear in both teams.');
            $this->addError('team2.1', 'A player cannot appear in both teams.');
            $hasError = true;
        }

        // dd($hasError);
        return $hasError;



    }


    public function updateMatch()
    {
        $hasError = $this->dataCheck();

        if ($hasError) {
            return; // Stop execution if validation fails
        }

        DB::transaction(function () {
            $match = $this->selectedMatch;

            if ($match) {
                // Create Team 1
                // TEAM 1
                $team1Players = Player::whereIn('id', $this->team1)->pluck('name')->toArray();
                $team1Name = implode(' & ', $team1Players);

                $team1 = Team::create([
                    'name' => $team1Name,
                    'event_id' => $this->event->id,
                ]);

                foreach ($this->team1 as $playerId) {
                    TeamPlayer::create([
                        'team_id' => $team1->id,
                        'player_id' => $playerId,
                    ]);
                }


                // TEAM 2
                $team2Players = Player::whereIn('id', $this->team2)->pluck('name')->toArray();
                $team2Name = implode(' & ', $team2Players);

                $team2 = Team::create([
                    'name' => $team2Name,
                    'event_id' => $this->event->id,
                ]);

                foreach ($this->team2 as $playerId) {
                    TeamPlayer::create([
                        'team_id' => $team2->id,
                        'player_id' => $playerId,
                    ]);
                }

                // Update match
                $matchnumber = ($match->id - $match->round->games[0]->id) + 1;
                $match->update([
                    'team1_id' => $team1->id,
                    'team2_id' => $team2->id,
                    'match_date' => $this->match_date,

                    'name' => 'Match # ' . $matchnumber,
                    'status' => 'ready',
                ]);
            }
        });

        Flux::modal('add-match')->close();
        $this->getList();

        session()->flash('success', 'Match updated successfully!');
    }
    public function processMatches()
    {
        $round = $this->round;

        if ($round->round_number <= 1) {
            return;
        }

        $previousRound = Round::where('event_id', $this->event->id)
            ->where('round_number', $round->round_number - 1)
            ->first();

        if (!$previousRound) {
            return;
        }

        // Check if all previous round games are completed
        $totalGames = $previousRound->games()->count();
        $completedGames = $previousRound->games()
            ->where('status', 'completed')
            ->count();

        if ($totalGames !== $completedGames) {
            session()->flash('error', 'Not all matches in the previous round are completed.');
            return;
        }

        // ✅ Fetch completed matches
        $previousMatches = $previousRound->games()
            ->where('status', 'completed')
            ->whereNotNull('winner_team_id')
            ->get();

        DB::transaction(function () use ($round, $previousMatches) {

            $firstMatch = $previousMatches->first();

            $isDoubles = $firstMatch?->is_doubles ?? 0;
            $bestOf = $firstMatch?->bestof ?? $this->max_rounds;

            $winners = $previousMatches
                ->pluck('winner_team_id')
                ->map(fn($id) => Team::find($id))
                ->filter()
                ->values();

            for ($i = 0; $i < $winners->count(); $i += 2) {
                Game::create([
                    'round_id' => $round->id,
                    'event_id' => $this->event->id,
                    'team1_id' => $winners[$i]->id,
                    'team2_id' => $winners[$i + 1]->id,
                    'is_doubles' => $isDoubles,   // ✅ inherited
                    'bestof' => $bestOf,      // ✅ inherited
                    'name' => 'Match # ' . (($i / 2) + 1),
                    'status' => 'ready',
                ]);

                $this->round->increment('matches');
            }
        });


        $this->getRound($round->id);

        session()->flash('message', 'Matches for the round generated successfully!');
    }

    public function resetplayers($id)
    {
        $match = Game::find($id);
        if ($match->status == 'ready') {
            $match->update([
                'team1_id' => null,
                'team2_id' => null,
                'status' => 'pending'
            ]);
        }
        $this->getList();
    }



};
?>


<div>
    <livewire:eventheader :event="$event" />
    <!-- ========== HEADER ========== -->
    <header class="flex flex-wrap lg:justify-start lg:flex-nowrap z-50 w-fullx` py-7 pt-0">
        <nav
            class="relative max-w-7xl w-full flex flex-wrap lg:grid lg:grid-cols-12 basis-full items-center px-4 md:px-6 lg:px-8 mx-auto">
            <div class="lg:col-span-3 flex items-center">
                <!-- Logo -->
                <a class="flex-none rounded-xl text-xl inline-block font-semibold focus:outline-hidden focus:opacity-80"
                    href="index.html" aria-label="Preline">
                    Manage Matches
                </a>
                <!-- End Logo -->

                <div class="ms-1 sm:ms-2">

                </div>
            </div>

            <!-- Button Group -->
            <div class="flex items-center gap-x-1 lg:gap-x-2 ms-auto py-1 lg:ps-6 lg:order-3 lg:col-span-3">
                <flux:modal.trigger name="match-generator">
                    <button type="button"
                        class="py-2 px-3 inline-flex items-center gap-x-2 text-sm font-medium text-nowrap rounded-xl border border-transparent bg-yellow-400 text-black hover:bg-yellow-500 focus:outline-hidden focus:bg-yellow-500 transition disabled:opacity-50 disabled:pointer-events-none">
                        Generate Matches
                    </button>
                </flux:modal.trigger>
                <a wire:navigate href="{{ route('event.players', $event->id) }}">
                    <flux:button variant="primary" type="button">
                        Manage Players
                    </flux:button>
                </a>




            </div>
            <!-- End Button Group -->

            <!-- Collapse -->
            <div id="hs-pro-hcail"
                class="hs-collapse hidden overflow-hidden transition-all duration-300 basis-full grow lg:block lg:w-auto lg:basis-auto lg:order-2 lg:col-span-6"
                aria-labelledby="hs-pro-hcail-collapse">
                <div
                    class="flex flex-col gap-y-4 gap-x-0 mt-5 lg:flex-row lg:justify-center lg:items-center lg:gap-y-0 lg:gap-x-7 lg:mt-0">

                </div>
            </div>
            <!-- End Collapse -->
        </nav>
    </header>
    <header
        class="flex flex-wrap  sm:justify-start sm:flex-nowrap w-full bg-white text-sm py-3 px-3 dark:bg-neutral-800">
        <nav class="max-w-[85rem] w-full mx-auto px-4 sm:flex sm:items-center sm:justify-between">
            <a class="flex-none font-semibold text-xl text-black focus:outline-hidden focus:opacity-80 dark:text-white"
                href="#" aria-label="Brand">{{ $round->name ?? 'No Rounds' }}</a>
            <div class="flex flex-row items-center gap-3 mt-5 sm:justify-end sm:mt-0 sm:ps-5">
                @foreach ($rounds as $round)
                    <flux:button wire:click="getRound({{ $round->id }})">{{ $round->name }}</flux:button>
                @endforeach

            </div>
        </nav>
    </header>
    <!-- ========== END HEADER ========== -->
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800">
            <div class="flex items-center">
                <svg class="h-5 w-5 flex-shrink-0 text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-4a1 1 0 00-.993.883L9 7v3a1 1 0 001.993.117L11 10V7a1 1 0 00-1-1zm0 8a1.25 1.25 0 100-2.5 1.25 1.25 0 000 2.5z"
                        clip-rule="evenodd" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif


    <flux:modal name="match-generator" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Matches Generator</flux:heading>
                <flux:text class="mt-2">Make changes to your personal details.</flux:text>
            </div>
            <h3>
                Players count: {{ $numberOfPlayers }}</h3>
            <h3>
                {{-- Total Rounds Needed: {{ $this->playersRoundCount() }} --}}
            </h3>
            <h3>
                {{-- Total Matches in first round: {{ (int) ($numberOfPlayers / 2) }} --}}
            </h3>
            <flux:input label="Best of" wire:model="max_rounds" type="number" placeholder="Max rounds per match" />
            <flux:radio.group wire:model="isDoubles" label="Teams Type">
                <flux:radio value="0" checked label="Single" />
                <flux:radio value="1" label="Double" />
            </flux:radio.group>

            <select wire:model="bracketSize" class="form-select">
                <option value="">Select bracket size</option>
                <option value="64">64 (Top 64 players)</option>
                <option value="128">128 (With byes)</option>
            </select>



            <div>
                @if (session()->has('message'))
                    <div class="p-3 mb-3 bg-green-600 text-white rounded">
                        {{ session('message') }}
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="p-3 mb-3 bg-red-600 text-white rounded">
                        {{ session('error') }}
                    </div>
                @endif
            </div>

            <div class="flex">
                <flux:spacer />
                <flux:button wire:click="generateRoundsAndMatches" variant="primary">Generate Matches
                </flux:button>
            </div>
        </div>
    </flux:modal>


    <div class=" grid grid-cols-2 gap-5 sm:grid-cols-3">
        @forelse ($matches as $match)
            @if ($match->status == 'completed')
                <!-- Match Card Enhanced -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 pb-3 border-l-4 border-green-500">
                    <!-- Header: Serial & Title with Button -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">

                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ $match->name}}
                            </span>
                        </div>


                    </div>

                    <!-- Players Section -->
                    <div class="space-y-3 mb-4">
                        <!-- Player 1 -->
                        <div class="flex items-center justify-between">
                            <span class="text-gray-800 dark:text-white text-sm font-semibold">
                                {{ $match->team1->name ?? 'Player 1' }}{{ $match->winner_team_id == $match->team1_id ? '👑' : '' }}
                            </span>
                            <div class=" flex gap-2">


                                @foreach ($match->scores as $round)
                                    <div class="flex justify-between items-center bg-gray-100 dark:bg-gray-700 p-1 rounded mb-2">
                                        <span class="font-medium text-sm"> {{ $round->team1_score }}</span>

                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <flux:separator text="vs" />

                        <!-- Player 2 -->
                        <div class="flex items-center justify-between">
                            <span class="text-gray-800 dark:text-white text-sm font-semibold">
                                {{ $match->team2->name ?? 'Player 2' }}{{ $match->winner_team_id == $match->team2_id ? '👑' : '' }}
                            </span>
                            <div class=" flex gap-2">

                                @foreach ($match->scores as $round)
                                    <div class="flex justify-between items-center bg-gray-100 dark:bg-gray-700 p-1 rounded mb-2">
                                        <span class="font-medium text-sm"> {{ $round->team2_score }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>


                    <!-- Footer: Date & Court -->
                    <div class="text-sm text-gray-500 flex justify-between dark:text-gray-400">
                        <span
                            class="text-xs font-bold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                            {{ $match->id }}
                        </span>
                        <p>📍 {{$match->round->name . '-' . $match->name ?? 'N/A' }}</p>
                    </div>
                    <div class=" flex justify-end items-center mt-3 ">
                        <flux:button wire:navigate href="{{ route('match.details', $match->id) }}" class=" ">See Details
                        </flux:button>
                    </div>

                </div>
            @elseif ($match->status == 'ongoing')
                <!-- Match Card Enhanced -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 pb-3 border-l-4 border-green-500">
                    <!-- Header: Serial & Title with Button -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">

                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ $match->name }}
                            </span>
                        </div>


                    </div>

                    <!-- Players Section -->
                    <div class="space-y-3 mb-4">
                        <!-- Player 1 -->
                        <div class="flex items-center justify-between">
                            <span class="text-gray-800 dark:text-white text-sm font-semibold">
                                {{ $match->team1->name ?? 'Player 1' }}{{ $match->winner_team_id == $match->team1_id ? '👑' : '' }}
                            </span>
                            <div class=" flex gap-2">


                                @foreach ($match->scores as $round)
                                    <div class="flex justify-between items-center bg-gray-100 dark:bg-gray-700 p-1 rounded mb-2">
                                        <span class="font-medium text-sm"> {{ $round->team1_score }}</span>

                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <flux:separator text="vs" />

                        <!-- Player 2 -->
                        <div class="flex items-center justify-between">
                            <span class="text-gray-800 dark:text-white text-sm font-semibold">
                                {{ $match->team2->name ?? 'Player 2' }}{{ $match->winner_team_id == $match->team2_id ? '👑' : '' }}
                            </span>
                            <div class=" flex gap-2">

                                @foreach ($match->scores as $round)
                                    <div class="flex justify-between items-center bg-gray-100 dark:bg-gray-700 p-1 rounded mb-2">
                                        <span class="font-medium text-sm"> {{ $round->team2_score }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>


                    <!-- Footer: Date & Court -->
                    <div class="text-sm text-gray-500 flex justify-between dark:text-gray-400">
                        <span
                            class="text-xs font-bold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                            {{ $match->id }}
                        </span>
                        <p>📍 Court #{{ $match->court_number ?? 'N/A' }}</p>
                    </div>
                    <div class=" flex justify-end items-center mt-3 ">
                        <flux:button wire:navigate href="{{ route('match.control', $match->id) }}" class=" ">
                            Continue Match
                        </flux:button>
                    </div>

                </div>

            @elseif ($match->status == null || $match->status == 'pending' || $match->status == 'ready')
                <!-- Match Card Enhanced -->
                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 pb-3 border-l-4 {{ $match->status == 'ready' ? 'border-yellow-500' : 'border-red-500' }}">
                    <!-- Header: Serial & Title with Button -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">

                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                {{ $match->name ?? 'Match' }}
                            </span>
                        </div>
                        @if ($match->team1 && $match->team2)
                            <!--<flux:icon.pencil-square  variant="micro" />-->
                        @else
                            <flux:button wire:click="openMatch({{ $match->id }})" size="sm">

                                +
                        @endif
                        </flux:button>
                    </div>

                    <!-- Players Section -->
                    <div class="space-y-3 mb-4">
                        <!-- Player 1 -->
                        <div class="flex items-center justify-between">
                            <span class="text-gray-800 dark:text-white text-sm font-semibold">
                                {{ $match->team1?->players?->first()->name ?? 'Player' }}
                                <span class="text-gray-800 dark:text-white text-xs ">
                                    ( {{ $match->team1?->players?->first()->subtext ?? '' }} )
                                </span>
                            </span>
                            @if ($match->is_doubles)
                                <span class="text-gray-800 dark:text-white text-sm font-semibold">
                                    {{ $match->team1?->players?->last()->name ?? 'Player' }}
                                    <span class="text-gray-800 dark:text-white text-xs ">
                                        ( {{ $match->team1?->players?->last()->subtext ?? '' }} )
                                    </span>
                                </span>
                            @endif

                        </div>

                        <flux:separator text="vs" />

                        <!-- Player 2 -->
                        <div class="flex items-center justify-between">
                            <span class="text-gray-800 dark:text-white text-sm font-semibold">
                                {{ $match->team2?->players?->first()->name ?? 'Player' }}
                                <span class="text-gray-800 dark:text-white text-xs ">
                                    ( {{ $match->team2?->players?->first()->subtext ?? '' }} )
                                </span>
                            </span>
                            @if ($match->is_doubles)
                                <span class="text-gray-800 dark:text-white text-sm font-semibold">
                                    {{ $match->team2?->players?->last()->name ?? 'Player' }}
                                    <span class="text-gray-800 dark:text-white text-xs ">
                                        ( {{ $match->team2?->players?->last()->subtext ?? '' }} )
                                    </span>
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Footer: Date & Court -->
                    <div class="text-sm text-gray-500 flex justify-between dark:text-gray-400">
                        <p>📍 Court #{{ $match->court_number ?? 'N/A' }}</p>

                        <span
                            class="text-xs font-bold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                            {{ $match->id }}

                        </span>
                    </div>
                    <div class="flex justify-between items-center">

                        @if ($match->status == 'ready' && $match->round->round_number)
                            <div class=" flex justify-end items-center mt-3 ">
                                <flux:button variant="danger" wire:click="resetplayers({{$match->id}})" size='xs' wire:confirm
                                    class=" ">Reset Players
                                </flux:button>
                            </div>
                        @endif
                        @if ($match->status == 'ready')
                            <div class=" flex justify-end items-center mt-3 ">
                                <flux:button wire:navigate href="{{ route('match.control', $match->id) }}" class=" ">Start Match
                                </flux:button>
                            </div>
                        @endif
                    </div>
                </div>

            @endif

        @empty
            <div class="">
                @if ($round?->round_number > 1)
                    <flux:button wire:click="processMatches" variant="primary">Generate Matches for this Round
                    </flux:button>

                @else
                    NO MATCHES CREATED YET!
                @endif
            </div>

        @endforelse
    </div>
    {{--
    <flux:modal.trigger name="edit-profile">
        <flux:button>Edit profile</flux:button>
    </flux:modal.trigger> --}}

    <flux:modal name="add-match" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Add Match Data</flux:heading>
                <flux:text class="mt-2">Fill in the details to create a new match.</flux:text>
            </div>

            <flux:heading class=" text-center" size="lg">Team 1</flux:heading>
            <div class=" flex flex-wrap gap-2">

                <div class=" w-full">

                    <flux:select wire:model="team1.1" label="Player 1">
                        @forelse ($players as $player)
                            <flux:select.option value="{{ $player->id }}">{{ $player->name }}</flux:select.option>
                        @empty
                            <flux:select.option disabled>No players available</flux:select.option>
                        @endforelse
                    </flux:select>
                </div>

                @if ($selectedMatch?->is_doubles)
                    <div class=" w-full">

                        <flux:select wire:model="team1.2" label="Player 2">
                            @forelse ($players as $player)
                                <flux:select.option value="{{ $player->id }}">{{ $player->name }}</flux:select.option>
                            @empty
                                <flux:select.option disabled>No players available</flux:select.option>
                            @endforelse
                        </flux:select>
                    </div>

                @endif
            </div>

            <flux:heading class=" text-center -m-y-2" size="lg">Team 2</flux:heading>
            <div class=" flex flex-wrap gap-2">

                <div class=" w-full">
                    <flux:select wire:model="team2.1" label="Player 1">
                        @forelse ($players as $player)
                            <flux:select.option value="{{ $player->id }}">{{ $player->name }}</flux:select.option>
                        @empty
                            <flux:select.option disabled>No players available</flux:select.option>
                        @endforelse
                    </flux:select>
                </div>
                @if ($selectedMatch?->is_doubles)
                    <div class=" w-full">
                        <flux:select wire:model="team2.2" label="Player 2">
                            @forelse ($players as $player)
                                <flux:select.option value="{{ $player->id }}">{{ $player->name }}</flux:select.option>
                            @empty
                                <flux:select.option disabled>No players available</flux:select.option>
                            @endforelse
                        </flux:select>
                    </div>
                @endif
            </div>

            <flux:input wire:model="match_date" label="Match Date" type="date" />

            <div class="flex">
                <flux:spacer />

                <flux:button type="button" wire:click="updateMatch" variant="primary">Save changes</flux:button>
            </div>
        </div>
    </flux:modal>


</div>