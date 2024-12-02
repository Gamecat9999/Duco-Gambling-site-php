<?php
session_start();

// Configuration
$DUINOCOIN_API_URL = "https://server.duinocoin.com";
$FAUCET_USERNAME = "ENTER_USERNAME_HERE";
$FAUCET_PASSWORD = "ENTER_PASSWORD_HERE";
$BET_AMOUNT = 0.25;

// Gems configuration (using letters with colors)
$GEMS = [
    'R' => '#ff4444',  // Red
    'B' => '#4444ff',  // Blue
    'G' => '#44ff44',  // Green
    'Y' => '#ffff44',  // Yellow
    'P' => '#ff44ff',  // Purple
    'O' => '#ff8844'   // Orange
];

// Winning combinations and multipliers
$MULTIPLIERS = [
    3 => 0.25,  // 3 in a row
    4 => 0.35,  // 4 in a row
    5 => 0.5    // 5 in a row
];

// Transaction function
function transfer_duinocoin($sender_username, $sender_password, $recipient_username, $amount) {
    global $DUINOCOIN_API_URL;
    $url = "$DUINOCOIN_API_URL/transaction/?username=" . urlencode($sender_username) 
         . "&password=" . urlencode($sender_password) 
         . "&recipient=" . urlencode($recipient_username) 
         . "&amount=" . urlencode($amount) 
         . "&memo=GemCascade";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if ($response === FALSE) {
        error_log("Transaction failed - no response");
        return FALSE;
    }
    
    $result = json_decode($response, true);
    return ($result && !isset($result['error']));
}

// Game logic
function generateGrid() {
    global $GEMS;
    $grid = [];
    $gemTypes = array_keys($GEMS);
    
    for ($i = 0; $i < 6; $i++) {
        $row = [];
        for ($j = 0; $j < 6; $j++) {
            $row[] = $gemTypes[array_rand($gemTypes)];
        }
        $grid[] = $row;
    }
    
    return $grid;
}

function findMatches($grid) {
    $matches = [];
    
    // Check horizontal matches
    for ($i = 0; $i < 6; $i++) {
        $count = 1;
        $currentGem = $grid[$i][0];
        $matchStart = 0;
        
        for ($j = 1; $j < 6; $j++) {
            if ($grid[$i][$j] === $currentGem) {
                $count++;
            } else {
                if ($count >= 3) {
                    $matches[] = [
                        'row' => $i,
                        'col' => $matchStart,
                        'length' => $count,
                        'direction' => 'horizontal',
                        'type' => $currentGem
                    ];
                }
                $count = 1;
                $currentGem = $grid[$i][$j];
                $matchStart = $j;
            }
        }
        if ($count >= 3) {
            $matches[] = [
                'row' => $i,
                'col' => $matchStart,
                'length' => $count,
                'direction' => 'horizontal',
                'type' => $currentGem
            ];
        }
    }
    
    // Check vertical matches
    for ($j = 0; $j < 6; $j++) {
        $count = 1;
        $currentGem = $grid[0][$j];
        $matchStart = 0;
        
        for ($i = 1; $i < 6; $i++) {
            if ($grid[$i][$j] === $currentGem) {
                $count++;
            } else {
                if ($count >= 3) {
                    $matches[] = [
                        'row' => $matchStart,
                        'col' => $j,
                        'length' => $count,
                        'direction' => 'vertical',
                        'type' => $currentGem
                    ];
                }
                $count = 1;
                $currentGem = $grid[$i][$j];
                $matchStart = $i;
            }
        }
        if ($count >= 3) {
            $matches[] = [
                'row' => $matchStart,
                'col' => $j,
                'length' => $count,
                'direction' => 'vertical',
                'type' => $currentGem
            ];
        }
    }
    
    return $matches;
}

function calculateWinnings($matches) {
    global $MULTIPLIERS;
    $totalWinnings = 0;
    
    foreach ($matches as $match) {
        if (isset($MULTIPLIERS[$match['length']])) {
            $totalWinnings += $MULTIPLIERS[$match['length']];
        }
    }
    
    return $totalWinnings;
}

$message = '';
$error = '';
$gameResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        // Process initial bet payment
        if (transfer_duinocoin($username, $password, $FAUCET_USERNAME, $BET_AMOUNT)) {
            // Generate and check grid
            $grid = generateGrid();
            $matches = findMatches($grid);
            $winnings = calculateWinnings($matches);
            
            // Store game state
            $gameResult = [
                'grid' => $grid,
                'matches' => $matches,
                'winnings' => $winnings
            ];
            
            // Process winnings if any
            if ($winnings > 0) {
                if (transfer_duinocoin($FAUCET_USERNAME, $FAUCET_PASSWORD, $username, $winnings)) {
                    $message = "You won {$winnings} DUCO!";
                } else {
                    $error = "Error processing winnings. Please contact support.";
                }
            } else {
                $message = "No matches. Better luck next time!";
            }
        } else {
            $error = "Transaction failed. Please check your balance and try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Gem Cascade</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #fff;
        }

        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 25px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .game-title {
            text-align: center;
            color: #ffcc00;
            font-size: 2em;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin: 30px 0;
        }

        .gem {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            border-radius: 10px;
            color: white;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
            transition: all 0.3s ease;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0); }
            to { opacity: 1; transform: scale(1); }
        }

        .form-container {
            margin-top: 30px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 2px solid rgba(255, 204, 0, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 16px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: 15px;
            background: #ffcc00;
            color: #000;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
        }

        button:hover {
            background: #ffd700;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 204, 0, 0.3);
        }

        .message {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            border-radius: 10px;
        }

        .success {
            background: rgba(39, 174, 96, 0.2);
            border: 1px solid #27ae60;
            color: #2ecc71;
        }

        .error {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }

        .rules {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .gem-types {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 15px;
        }

        .gem-type {
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="game-title">Gem Cascade</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($gameResult): ?>
            <div class="grid-container">
                <?php foreach ($gameResult['grid'] as $row): ?>
                    <?php foreach ($row as $gem): ?>
                        <div class="gem" style="background-color: <?php echo $GEMS[$gem]; ?>">
                            <?php echo $gem; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST">
                <input type="text" name="username" placeholder="Duino-Coin Username" required>
                <input type="password" name="password" placeholder="Duino-Coin Password" required>
                <button type="submit">Play (0.25 DUCO)</button>
            </form>
        </div>
        
        <div class="rules">
            <h3>Rules</h3>
            <p>Match 3 or more gems in a row or column!</p>
            <p>this game is completely random and provably fair, based on luck</p>
            <p>3 in a row = 0.25 DUCO</p>
            <p>4 in a row = 0.35 DUCO</p>
            <p>5 in a row = 0.50 DUCO</p>
            <div class="gem-types">
                <?php foreach ($GEMS as $type => $color): ?>
                    <div class="gem-type" style="background-color: <?php echo $color; ?>">
                        <?php echo $type; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>