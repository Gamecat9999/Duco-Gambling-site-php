<?php
$DUINOCOIN_API_URL = "https://server.duinocoin.com";
$FAUCET_USERNAME = "ENTER_USERNAME_HERE";
$FAUCET_PASSWORD = "ENTER_PASSWORD_HERE";
$MIN_BET = 0.5;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['action'])) {
        echo json_encode(['error' => 'No action specified']);
        exit;
    }

    switch ($data['action']) {
        case 'play':
            if (!isset($data['username']) || !isset($data['password']) || !isset($data['bet'])) {
                echo json_encode(['error' => 'Missing parameters']);
                exit;
            }

            if ($data['bet'] < $MIN_BET) {
                echo json_encode(['error' => 'Minimum bet is ' . $MIN_BET . ' DUCO']);
                exit;
            }

            // Take initial bet
            $verify = transfer_duinocoin($data['username'], $data['password'], $FAUCET_USERNAME, $data['bet']);
            if ($verify === FALSE) {
                echo json_encode(['error' => 'Transaction failed']);
                exit;
            }

            // Create deck and draw cards
            $deck = createDeck();
            shuffle($deck);
            $playerCard = array_pop($deck);
            $dealerCard = array_pop($deck);

            // Calculate multiplier and winnings
            $multiplier = calculateMultiplier($playerCard, $dealerCard);
            $winner = determineWinner($playerCard, $dealerCard);
            $payout = 0;

            if ($winner === 'player') {
                $payout = $data['bet'] * $multiplier;
                $result = transfer_duinocoin($FAUCET_USERNAME, $FAUCET_PASSWORD, $data['username'], $payout);
                if (!$result) {
                    echo json_encode(['error' => 'Payout failed']);
                    exit;
                }
            } elseif ($winner === 'tie') {
                // Return the original bet on tie
                $payout = $data['bet'];
                $result = transfer_duinocoin($FAUCET_USERNAME, $FAUCET_PASSWORD, $data['username'], $payout);
                if (!$result) {
                    echo json_encode(['error' => 'Bet return failed']);
                    exit;
                }
            }

            echo json_encode([
                'success' => true,
                'playerCard' => $playerCard,
                'dealerCard' => $dealerCard,
                'winner' => $winner,
                'multiplier' => $multiplier,
                'payout' => $payout
            ]);
            break;
    }
    exit;
}

function createDeck() {
    $suits = ['hearts', 'diamonds', 'clubs', 'spades'];
    $values = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
    $deck = [];
    
    foreach ($suits as $suit) {
        foreach ($values as $value) {
            $deck[] = ['suit' => $suit, 'value' => $value];
        }
    }
    
    return $deck;
}

function calculateMultiplier($playerCard, $dealerCard) {
    if ($playerCard['value'] === 'A') return 2.5;
    if (in_array($playerCard['value'], ['K', 'Q', 'J'])) return 2.0;
    return 1.8;
}

function determineWinner($playerCard, $dealerCard) {
    $values = ['2'=>2, '3'=>3, '4'=>4, '5'=>5, '6'=>6, '7'=>7, '8'=>8, '9'=>9, '10'=>10, 
               'J'=>11, 'Q'=>12, 'K'=>13, 'A'=>14];
    
    if ($values[$playerCard['value']] > $values[$dealerCard['value']]) return 'player';
    if ($values[$playerCard['value']] < $values[$dealerCard['value']]) return 'dealer';
    return 'tie';
}

function transfer_duinocoin($sender_username, $sender_password, $recipient_username, $amount) {
    global $DUINOCOIN_API_URL;
    $url = "$DUINOCOIN_API_URL/transaction/?username=" . urlencode($sender_username) 
         . "&password=" . urlencode($sender_password) 
         . "&recipient=" . urlencode($recipient_username) 
         . "&amount=" . urlencode($amount) 
         . "&memo=CardWarGame";
    
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
    
    error_log("DUCO Transaction Response: " . $response);
    
    $result = json_decode($response, true);
    if ($result && !isset($result['error'])) {
        return TRUE;
    }
    
    return FALSE;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DUCO Card War</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <!-- Your provided stylesheet -->
    <style>
        /* Your provided styles here */
        
        /* Additional Card War specific styles */
        .game-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 25px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            text-align: center;
        }

        .cards-area {
            display: flex;
            justify-content: space-around;
            align-items: center;
            margin: 20px 0;
            min-height: 200px;
        }

        .card {
            width: 120px;
            height: 180px;
            background: #fff;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: #000;
            position: relative;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            transition: transform 0.3s ease;
        }

        .card.hearts, .card.diamonds {
            color: #e74c3c;
        }

        .card .value {
            font-size: 48px;
            font-weight: bold;
        }

        .card .suit {
            font-size: 36px;
        }

        .vs-text {
            font-size: 24px;
            font-weight: bold;
            color: #ffcc00;
        }

        .controls {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 300px;
            margin: 0 auto;
        }

        .login-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .login-container input {
            width: 200px;
        }

        .multiplier-info {
            margin: 20px 0;
            padding: 15px;
            background: rgba(255, 204, 0, 0.1);
            border-radius: 10px;
            border: 1px solid rgba(255, 204, 0, 0.2);
        }

        @media (max-width: 768px) {
            .cards-area {
                flex-direction: column;
                gap: 20px;
            }
            
            .card {
                width: 100px;
                height: 150px;
                font-size: 30px;
            }
            
            .login-container {
                flex-direction: column;
            }
            
            .login-container input {
                width: 100%;
            }
        }
    </style>
        <style>/* Add this to the <style> section of each game */
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

h1 {
    color: #ffcc00;
    text-align: center;
    font-size: 2.2em;
    margin-bottom: 20px;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
}

.info {
    text-align: center;
    color: #fff;
    margin-bottom: 25px;
    padding: 15px;
    background: rgba(255, 204, 0, 0.1);
    border-radius: 10px;
    border: 1px solid rgba(255, 204, 0, 0.2);
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    margin-bottom: 8px;
    color: #ffcc00;
    font-weight: bold;
}

input, select {
    width: 100%;
    padding: 12px;
    border: 2px solid rgba(255, 204, 0, 0.3);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    font-size: 16px;
    transition: all 0.3s ease;
}

input:focus, select:focus {
    outline: none;
    border-color: #ffcc00;
    box-shadow: 0 0 10px rgba(255, 204, 0, 0.3);
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
}

button:hover {
    background: #ffd700;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 204, 0, 0.3);
}

button:disabled {
    background: #666;
    cursor: not-allowed;
    transform: none;
}

#result {
    margin-top: 20px;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    font-weight: bold;
    display: none;
}

.win {
    background: rgba(39, 174, 96, 0.2);
    border: 1px solid #27ae60;
    color: #2ecc71;
}

.lose {
    background: rgba(231, 76, 60, 0.2);
    border: 1px solid #e74c3c;
    color: #e74c3c;
}

.error {
    background: rgba(231, 76, 60, 0.2);
    border: 1px solid #e74c3c;
    color: #e74c3c;
}

.footer-container {
    max-width: 600px;
    margin: 20px auto;
    padding: 25px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255, 255, 255, 0.18);
}

.disclaimer {
    padding: 20px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    margin-bottom: 20px;
}

.disclaimer h3 {
    color: #ffcc00;
    margin-bottom: 15px;
    font-size: 1.2em;
    text-align: center;
}

.disclaimer p {
    color: #fff;
    line-height: 1.6;
    margin-bottom: 10px;
    text-align: center;
}

.technical-note {
    font-size: 0.9em;
    color: #aaa;
    font-style: italic;
}

.copyright {
    text-align: center;
    color: #aaa;
    font-size: 0.9em;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

@media (max-width: 768px) {
    .container, .footer-container {
        margin: 10px;
        padding: 20px;
    }
    
    h1 {
        font-size: 1.8em;
    }
    
    button {
        padding: 12px;
        font-size: 16px;
    }
}</style>
</head>
<body>
    <div class="game-container">
        <h1>DUCO Card War</h1>
        
        <div class="how-to-play container">
            <h2>How to Play</h2>
            <div class="info">
                <p>Draw a card and beat the dealer's card to win!</p>
                <div class="multiplier-info">
                    <h3>Payouts:</h3>
                    <p>Ace: 2.5x</p>
                    <p>Face Cards (K, Q, J): 2.0x</p>
                    <p>Number Cards: 1.8x</p>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="login-container">
                <input type="text" id="username" placeholder="Username">
                <input type="password" id="password" placeholder="Password">
            </div>
            
            <div class="controls">
                <input type="number" id="betAmount" min="0.5" step="0.1" value="0.5" placeholder="Bet Amount">
                <button onclick="playGame()" id="playButton">Play (0.5 DUCO)</button>
            </div>

            <div class="cards-area">
                <div id="playerCard"></div>
                <div class="vs-text">VS</div>
                <div id="dealerCard"></div>
            </div>

            <div id="result" class="result"></div>
        </div>
    </div>

    <script>
        const betInput = document.getElementById('betAmount');
        const playButton = document.getElementById('playButton');

        betInput.addEventListener('input', function() {
            playButton.textContent = `Play (${parseFloat(this.value).toFixed(1)} DUCO)`;
        });

        function createCardElement(card) {
            if (!card) return '';
            
            const suitSymbol = {
                'hearts': '♥',
                'diamonds': '♦',
                'clubs': '♣',
                'spades': '♠'
            }[card.suit];

            return `
                <div class="card ${card.suit}">
                    <div class="value">${card.value}</div>
                    <div class="suit">${suitSymbol}</div>
                </div>
            `;
        }

        async function playGame() {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const betAmount = parseFloat(document.getElementById('betAmount').value);

            if (!username || !password) {
                showResult('Please enter username and password', 'error');
                return;
            }

            if (betAmount < 0.5) {
                showResult('Minimum bet is 0.5 DUCO', 'error');
                return;
            }

            playButton.disabled = true;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'play',
                        username: username,
                        password: password,
                        bet: betAmount
                    })
                });

                const data = await response.json();
                
                if (data.error) {
                    showResult(data.error, 'error');
                    playButton.disabled = false;
                    return;
                }

                document.getElementById('playerCard').innerHTML = createCardElement(data.playerCard);
                document.getElementById('dealerCard').innerHTML = createCardElement(data.dealerCard);

                if (data.winner === 'player') {
                    showResult(`You win ${data.payout.toFixed(2)} DUCO! (${data.multiplier}x)`, 'win');
                } else if (data.winner === 'dealer') {
                    showResult(`Dealer wins! You lose ${betAmount.toFixed(2)} DUCO`, 'lose');
                } else {
                    showResult('Tie! Bet returned', 'info');
                }

                playButton.disabled = false;

            } catch (error) {
                showResult('Game failed: ' + error.message, 'error');
                playButton.disabled = false;
            }
        }

        function showResult(message, type) {
            const result = document.getElementById('result');
            result.textContent = message;
            result.className = 'result ' + type;
            result.style.display = 'block';
        }
    </script>
</body>
</html>