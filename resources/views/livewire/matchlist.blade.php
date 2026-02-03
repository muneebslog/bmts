<?php
use Livewire\Volt\Component;
use App\Models\Event;
use Livewire\Attributes\{Layout, Title};

new
    #[Layout('layouts.guest')]
    #[Title('Event Matches')]
    class extends Component {
    public $events;
    public function mount()
    {
       $this->events = Event::has('games')->get() ?? collect();
    }
}; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events & Matches</title>
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
            color: #ffffff;
            padding-left: 10px;
            border-left: 6px solid #ff6b35;
        }

        /* Events Grid */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 50px;
        }

        .event-card {
            background-color: #2a2a2a;
            border-radius: 12px;
            padding: 25px;
            border-left: 8px solid #ff6b35;
            text-decoration: none;
            color: inherit;
            display: block;
            transition: all 0.3s ease;
            cursor: pointer;
            outline: none;
        }

        .event-card:focus {
            outline: 4px solid #ff9966;
        }

        .event-card:hover {
            background-color: #333333;
            transform: translateY(-8px);
            box-shadow: 0 10px 30px rgba(255, 107, 53, 0.3);
        }

        .event-content {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .event-avatar {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .event-info {
            flex: 1;
        }

        .event-title {
            font-weight: bold;
            font-size: 24px;
            margin-bottom: 8px;
            color: #ffffff;
        }

        .event-subtitle {
            font-size: 18px;
            color: #aaaaaa;
        }

        /* No Data Message */
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #888888;
            font-size: 24px;
            grid-column: 1 / -1;
        }

        /* Matches Section */
        .matches-section {
            margin-top: 50px;
            padding-top: 40px;
            border-top: 2px solid #333333;
        }

        .matches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }

        .match-card {
            background-color: #2a2a2a;
            border-radius: 12px;
            padding: 20px;
            border-left: 8px solid;
            transition: all 0.3s ease;
            outline: none;
        }

        .match-card:focus {
            outline: 4px solid #ff9966;
        }

        .match-card:hover {
            background-color: #333333;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
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
            margin-bottom: 16px;
            gap: 15px;
        }

        .match-title {
            font-weight: bold;
            font-size: 18px;
            color: #ffffff;
            flex: 1;
        }

        .match-badge {
            display: inline-block;
            padding: 8px 16px;
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

        .match-players {
            margin-bottom: 16px;
        }

        .player-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            font-size: 18px;
            color: #ffffff;
            border-bottom: 1px solid #444444;
        }

        .player-row:last-child {
            border-bottom: none;
        }

        .player-name {
            font-weight: bold;
            flex: 1;
        }

        .player-score {
            display: flex;
            gap: 10px;
            margin-left: 15px;
        }

        .round-score {
            background-color: #444444;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            min-width: 40px;
            text-align: center;
            color: #ff6b35;
        }

        .vs-separator {
            text-align: center;
            color: #666666;
            font-size: 16px;
            font-weight: bold;
            margin: 10px 0;
            padding: 5px 0;
        }

        .match-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #aaaaaa;
            border-top: 1px solid #444444;
            padding-top: 16px;
            margin-top: 16px;
        }

        .match-serial {
            background-color: #444444;
            padding: 8px 14px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 13px;
            color: #ff6b35;
        }

        .match-action-btn {
            background-color: transparent;
            border: 3px solid #ff6b35;
            cursor: pointer;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
            margin-top: 16px;
            width: 100%;
            color: #ff6b35;
            font-family: Arial, sans-serif;
        }

        .match-action-btn:hover {
            background-color: #ff6b35;
            color: #1a1a1a;
        }

        .match-action-btn:focus {
            outline: 3px solid #ff9966;
        }

        .winner-crown {
            margin-left: 8px;
        }

        /* Button Styles */
        .btn-primary {
            background: linear-gradient(135deg, #ff6b35, #f7931e);
            color: white;
            font-weight: bold;
            font-size: 18px;
            padding: 16px 32px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: Arial, sans-serif;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 107, 53, 0.4);
        }

        .btn-primary:focus {
            outline: 3px solid #ff9966;
            outline-offset: 2px;
        }

        /* Responsive Design for Android TV */
        @media (max-width: 1280px) {
            .events-grid {
                grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
                gap: 25px;
            }

            .matches-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
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

            .events-grid,
            .matches-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .header {
                margin-bottom: 30px;
            }

            .header h1 {
                font-size: 32px;
            }

            .section-title {
                font-size: 24px;
            }

            .event-card {
                padding: 20px;
            }

            .event-avatar {
                width: 70px;
                height: 70px;
                font-size: 32px;
            }

            .event-title {
                font-size: 20px;
            }

            .match-card {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Events</h1>
            <button class="back-btn" onclick="history.back()">← Back</button>
        </div>

        <!-- Events Section -->
        <section>
            <h2 class="section-title">My Events</h2>
            <div class="events-grid">
                <!-- PHP Loop would go here -->
                @foreach ($events as $event)
                    <a href="{{ route('match.audience',$event->id) }}" class="event-card" tabindex="0">
                        <div class="event-content">
                            <div class="event-avatar">{{ substr($event->name, 0, 1) }}</div>
                            <div class="event-info">
                                <div class="event-title">{{ $event->name }}</div>
                                <div class="event-subtitle">{{ count($event->games) }} matches</div>
                            </div>
                        </div>
                    </a>
                @endforeach

                @if (count($events) === 0)
                    <div class="no-data">No events available</div>
                @endif
            </div>
        </section>
    </div>
</body>
</html>