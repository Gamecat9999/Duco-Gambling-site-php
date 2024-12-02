<?php
session_start();

// Configuration
$DUINOCOIN_API_URL = "https://server.duinocoin.com";
$FAUCET_USERNAME = "ENTER_USERNAME_HERE";
$FAUCET_PASSWORD = "ENTER_PASSWORD_HERE";
$BET_AMOUNT = 0.25;

// Cards configuration
$CARDS = [
    '2' => 0.25,  // Card value => win multiplier
    '3' => 0.25,
    '4' => 0.25,
    '5' => 0.3,
    '6' => 0.3,
    '7' => 0.3,
    '8' => 0.35,
    '9' => 0.35,
    '10' => 0.4,
    'J' => 0.4,
    'Q' => 0.45,
    'K' => 0.45,
    'A' => 0.5
];

// Transaction function
function transfer_duinocoin($sender_username, $sender_password, $recipient_username, $amount) {
    global $DUINOCOIN_API_URL;
    $url = "$DUINOCOIN_API_URL/transaction/?username=" . urlencode($sender_username) 
         . "&password=" . urlencode($sender_password) 
         . "&recipient=" . urlencode($recipient_username) 
         . "&amount=" . urlencode($amount) 
         . "&memo=CardPairs";
    
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
function dealCards() {
    global $CARDS;
    $deck = array_keys($CARDS);
    $gameCards = [];
    
    // Select three random cards (will be duplicated for pairs)
    $selectedCards = array_rand($deck, 3);
    foreach ($selectedCards as $index) {
        $card = $deck[$index];
        $gameCards[] = $card;
        $gameCards[] = $card;
    }
    
    // Shuffle the cards
    shuffle($gameCards);
    return $gameCards;
}

function calculateWinnings($cards, $selected) {
    global $CARDS;
    
    // Check if selected cards match
    if ($cards[$selected[0]] === $cards[$selected[1]]) {
        $matchedCard = $cards[$selected[0]];
        return $CARDS[$matchedCard];
    }
    
    return 0;
}

$message = '';
$error = '';
$gameResult = null;
$selectedCards = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'play') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if ($username && $password) {
            // Process initial bet payment
            if (transfer_duinocoin($username, $password, $FAUCET_USERNAME, $BET_AMOUNT)) {
                // Deal cards and store in session
                $_SESSION['cards'] = dealCards();
                $_SESSION['username'] = $username;
                $_SESSION['password'] = $password;
                $_SESSION['revealed'] = [];  // Initialize revealed array
                $gameResult = [
                    'cards' => $_SESSION['cards'],
                    'revealed' => $_SESSION['revealed']
                ];
            } else {
                $error = "Transaction failed. Please check your balance and try again.";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'select') {
        // Make sure session data exists
        if (isset($_SESSION['cards']) && isset($_SESSION['revealed'])) {
            $selectedIndex = (int)$_POST['card'];
            $_SESSION['revealed'][] = $selectedIndex;
            
            if (count($_SESSION['revealed']) === 2) {
                $winnings = calculateWinnings($_SESSION['cards'], $_SESSION['revealed']);
                if ($winnings > 0) {
                    if (transfer_duinocoin($FAUCET_USERNAME, $FAUCET_PASSWORD, $_SESSION['username'], $winnings)) {
                        $message = "You won {$winnings} DUCO!";
                    } else {
                        $error = "Error processing winnings. Please contact support.";
                    }
                } else {
                    $message = "No match. Better luck next time!";
                }
                
                // Clear session after round
                $gameResult = [
                    'cards' => $_SESSION['cards'],
                    'revealed' => $_SESSION['revealed']
                ];
                
                // Clear session
                unset($_SESSION['cards']);
                unset($_SESSION['revealed']);
                unset($_SESSION['username']);
                unset($_SESSION['password']);
            } else {
                $gameResult = [
                    'cards' => $_SESSION['cards'],
                    'revealed' => $_SESSION['revealed']
                ];
            }
        } else {
            // Session expired or invalid
            $error = "Session expired. Please start a new game.";
            $gameResult = null;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Card Pairs</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

        .cards-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 30px 0;
        }

        .card {
            aspect-ratio: 2/3;
            background: #ffcc00;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            transform-style: preserve-3d;
            position: relative;
        }

        .card.face-down {
            background: linear-gradient(45deg, #2c3e50, #3498db);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(255, 204, 0, 0.3);
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

        .multipliers {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .multiplier {
            background: rgba(255, 204, 0, 0.1);
            padding: 5px;
            border-radius: 5px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="game-title">Card Pairs</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!$gameResult): ?>
            <div class="form-container">
                <form method="POST">
                    <input type="hidden" name="action" value="play">
                    <input type="text" name="username" placeholder="Duino-Coin Username" required>
                    <input type="password" name="password" placeholder="Duino-Coin Password" required>
                    <button type="submit">Play (0.25 DUCO)</button>
                </form>
            </div>
        <?php else: ?>
            <div class="cards-container">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <form method="POST" style="width: 100%">
                        <input type="hidden" name="action" value="select">
                        <input type="hidden" name="card" value="<?php echo $i; ?>">
                        <button class="card <?php echo !in_array($i, $gameResult['revealed']) ? 'face-down' : ''; ?>" 
                                <?php echo in_array($i, $gameResult['revealed']) ? 'disabled' : ''; ?>>
                            <?php echo in_array($i, $gameResult['revealed']) ? $gameResult['cards'][$i] : '<i class="fas fa-question"></i>'; ?>
                        </button>
                    </form>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
        
        <div class="rules">
            <h3>Rules</h3>
            <p>Match pairs of cards to win! Higher cards give better rewards.</p>
            <div class="multipliers">
                <?php foreach ($CARDS as $card => $multiplier): ?>
                    <div class="multiplier"><?php echo $card; ?> = <?php echo $multiplier; ?> DUCO</div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>