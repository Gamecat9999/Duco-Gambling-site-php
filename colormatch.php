<?php
session_start();

// Configuration
$DUINOCOIN_API_URL = "https://server.duinocoin.com";
$FAUCET_USERNAME = "ENTER_USERNAME_HERE";
$FAUCET_PASSWORD = "ENTER_PASSWORD_HERE";
$BET_AMOUNT = 0.25;

// Colors array
$COLORS = ['red', 'blue', 'green', 'yellow'];

// Transaction function
function transfer_duinocoin($sender_username, $sender_password, $recipient_username, $amount) {
    global $DUINOCOIN_API_URL;
    $url = "$DUINOCOIN_API_URL/transaction/?username=" . urlencode($sender_username) 
         . "&password=" . urlencode($sender_password) 
         . "&recipient=" . urlencode($recipient_username) 
         . "&amount=" . urlencode($amount) 
         . "&memo=ColorMatch";
    
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
function playGame() {
    global $COLORS;
    $circles = [];
    for ($i = 0; $i < 3; $i++) {
        $circles[] = $COLORS[array_rand($COLORS)];
    }
    
    // Count matches
    $colorCounts = array_count_values($circles);
    $maxMatches = max($colorCounts);
    
    // Calculate winnings
    $winnings = 0;
    if ($maxMatches == 3) {
        $winnings = 0.5;
    } elseif ($maxMatches == 2) {
        $winnings = 0.25;
    }
    
    return [
        'circles' => $circles,
        'matches' => $maxMatches,
        'winnings' => $winnings
    ];
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
            // Play the game
            $gameResult = playGame();
            
            // If player won, process payment
            if ($gameResult['winnings'] > 0) {
                if (transfer_duinocoin($FAUCET_USERNAME, $FAUCET_PASSWORD, $username, $gameResult['winnings'])) {
                    $message = "You won {$gameResult['winnings']} DUCO!";
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
    <title>Color Match</title>
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
            max-width: 600px;
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

        .circles-container {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 30px 0;
        }

        .circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            transition: all 0.3s ease;
            opacity: 0;
            animation: revealCircle 0.5s forwards;
        }

        @keyframes revealCircle {
            from { transform: scale(0); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .red { background: #ff4444; box-shadow: 0 0 20px rgba(255, 68, 68, 0.5); }
        .blue { background: #4444ff; box-shadow: 0 0 20px rgba(68, 68, 255, 0.5); }
        .green { background: #44ff44; box-shadow: 0 0 20px rgba(68, 255, 68, 0.5); }
        .yellow { background: #ffff44; box-shadow: 0 0 20px rgba(255, 255, 68, 0.5); }

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
    </style>
</head>
<body>
    <div class="container">
        <h1 class="game-title">Color Match</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($gameResult): ?>
            <div class="circles-container">
                <?php foreach ($gameResult['circles'] as $i => $color): ?>
                    <div class="circle <?php echo $color; ?>" style="animation-delay: <?php echo $i * 0.2; ?>s"></div>
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
            <p>Match 2 colors = Get your bet back</p>
            <p>Match 3 colors = Win 0.5 DUCO</p>
            <p>No matches = Better luck next time!</p>
        </div>
    </div>
</body>
</html>