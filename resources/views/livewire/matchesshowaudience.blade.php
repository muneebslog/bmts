<?php
use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title};
use App\Models\Game;
use App\Models\Event;

new
    #[Layout('layouts.guest')]
    #[Title('Event Matches')]
    class extends Component {
    public $matches;
    public function mount(Event $id)
    {
        if (!$id) {
            $this->matches = collect();
            return;
        }

        $round = $id->rounds()
    ->whereHas('games')
    ->orderBy('round_number', 'desc')
    ->first();

$this->matches = $round?->games;



// 
// dd($this->matches);

        // Game::where('event_id', $id)
        //     ->whereNotNull('team1_id')
        //     ->whereNotNull('team2_id')
        //     ->orderBy('created_at', 'desc')
        //     ->get() ?? collect();
    }
}; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Matches</title>
    <style>
        /* Reset & Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
            font-family: Arial, sans-serif;
            background-color: #1a1a1a;
            color: #ffffff;
        }

        body {
            padding: 20px;
            overflow-x: hidden;
        }

        .container {
            max-width: 1920px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #ff6b35;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 48px;
            font-weight: bold;
            color: #ff6b35;
            margin: 0;
        }

        .back-btn {
            background-color: #ff6b35;
            color: #1a1a1a;
            border: none;
            padding: 12px 24px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: Arial, sans-serif;
        }

        .back-btn:hover {
            background-color: #f7931e;
            transform: translateY(-3px);
        }

        .back-btn:focus {
            outline: 3px solid #ff9966;
            outline-offset: 2px;
        }

        /* Section Title */
        .section-title {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 30px;
            margin-top: 20px;
            color: #ffffff;
            padding-left: 10px;
            border-left: 6px solid #ff6b35;
        }

        /* Matches Grid */
        .matches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }

        .match-card {
            background-color: #2a2a2a;
            border-radius: 12px;
            padding: 24px;
            border-left: 8px solid;
            transition: all 0.3s ease;
            outline: none;
        }

        .match-card:focus {
            outline: 4px solid #ff9966;
        }

        .match-card:hover {
            background-color: #333333;
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(255, 107, 53, 0.3);
        }

        .match-card.completed {
            border-left-color: #22c55e;
        }

        .match-card.pending {
            border-left-color: #ff6b35;
        }

        .match-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            gap: 15px;
        }

        .match-title {
            font-weight: bold;
            font-size: 20px;
            color: #ffffff;
            flex: 1;
            line-height: 1.3;
        }

        .match-badge {
            display: inline-block;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            text-transform: uppercase;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .match-badge.completed {
            background-color: #1e5631;
            color: #22c55e;
        }

        .match-badge.pending {
            background-color: #663d0d;
            color: #ff6b35;
        }

        /* Match Players Section */
        .match-players {
            margin-bottom: 20px;
            background-color: #1f1f1f;
            padding: 20px;
            border-radius: 8px;
        }

        .player-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            font-size: 18px;
            color: #ffffff;
        }

        .player-row:not(:last-child) {
            border-bottom: 1px solid #444444;
        }

        .player-name {
            font-weight: bold;
            flex: 1;
            font-size: 20px;
            line-height: 1.3;
        }

        .player-score {
            display: flex;
            gap: 12px;
            margin-left: 15px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .round-score {
            background-color: #ff6b35;
            color: #ffffff;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 18px;
            font-weight: bold;
            min-width: 48px;
            text-align: center;
        }

        .vs-separator {
            text-align: center;
            color: #666666;
            font-size: 16px;
            font-weight: bold;
            margin: 12px 0;
            padding: 8px 0;
        }

        .winner-crown {
            margin-left: 8px;
            font-size: 20px;
        }

        /* Match Footer */
        .match-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 16px;
            color: #aaaaaa;
            border-top: 1px solid #444444;
            padding-top: 16px;
            margin-bottom: 16px;
        }

        .match-footer:last-of-type {
            border-top: none;
            margin-bottom: 0;
            padding-top: 0;
        }

        .match-info {
            display: flex;
            gap: 20px;
        }

        .match-serial {
            background-color: #444444;
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 15px;
            color: #ff6b35;
        }

        .match-court {
            font-size: 18px;
            font-weight: bold;
            color: #ff6b35;
        }

        .match-action-btn {
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            border: none;
            cursor: pointer;
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            width: 100%;
            color: #1a1a1a;
            font-family: Arial, sans-serif;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .match-action-btn:hover {
            background: linear-gradient(135deg, #f7931e, #ff6b35);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 107, 53, 0.4);
        }

        .match-action-btn:focus {
            outline: 3px solid #ff9966;
            outline-offset: 2px;
        }

        /* No Data */
        .no-data {
            text-align: center;
            padding: 80px 20px;
            color: #888888;
            font-size: 26px;
            grid-column: 1 / -1;
        }

        /* Responsive Design for Android TV */
        @media (max-width: 1280px) {
            .matches-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
            }

            .header h1 {
                font-size: 40px;
            }

            .section-title {
                font-size: 28px;
            }
        }

        @media (max-width: 800px) {
            body {
                padding: 15px;
            }

            .matches-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }

            .header h1 {
                font-size: 32px;
            }

            .section-title {
                font-size: 24px;
            }

            .match-card {
                padding: 18px;
            }

            .match-players {
                padding: 16px;
            }

            .player-row {
                padding: 10px 0;
                font-size: 16px;
            }

            .player-name {
                font-size: 18px;
            }

            .round-score {
                padding: 8px 12px;
                font-size: 16px;
                min-width: 44px;
            }

            .player-score {
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Matches</h1>
            <button class="back-btn" onclick="history.back()">← Back</button>
        </div>

        <!-- Matches Section -->
        <section>
            <h2 class="section-title">All Matches</h2>
            <div class="matches-grid">
                @if ($matches && count($matches) > 0)
                    @foreach ($matches as $match)
                        <div class="match-card {{ strtolower($match->status) }}">
                            <div class="match-header">
                                <span class="match-title">{{ $match->name }}</span>
                                <span class="match-badge {{ strtolower($match->status) }}">{{ $match->status }}</span>
                            </div>

                            <div class="match-players">
                                <div class="player-row">
                                    <span class="player-name">
                                        {{ $match->team1?->name }}
                                        <span class="winner-crown">{{ $match->winner_team_id == $match->team1_id ? '👑' : '' }}</span>
                                    </span>
                                    <div class="player-score">
                                        @foreach ($match->scores as $round)
                                            <div class="round-score">{{ $round->team1_score }}</div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="vs-separator">vs</div>
                                <div class="player-row">
                                    <span class="player-name">
                                        {{ $match->team2?->name }} {{ $match->team2?->name }}
                                        <span class="winner-crown">{{ $match->winner_team_id == $match->team2_id ? '👑' : '' }}</span>
                                    </span>
                                    <div class="player-score">
                                        @foreach ($match->scores as $round)
                                            <div class="round-score">{{ $round->team2_score }}</div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="match-footer">
                                <div class="match-info">
                                    <span class="match-serial">Match #{{ $match->match_serial_number }}</span>
                                    <span class="match-court">🏆 Court #{{ $match->court_number }}</span>
                                </div>
                            </div>

                            <a href="{{ route('match.scoreboard', $match->id) }}" class="match-action-btn">View Scoreboard</a>
                        </div>
                    @endforeach
                @else
                    <div class="no-data">No matches found</div>
                @endif
            </div>
        </section>
    </div>
</body>
</html>