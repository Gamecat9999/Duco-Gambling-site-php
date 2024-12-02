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
        case 'deal':
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
            
            // Add a small delay to ensure transaction is processed
            usleep(500000); // 0.5 second delay

            // Create and shuffle deck
            $deck = createDeck();
            shuffle($deck);

            // Deal initial cards
            $playerHand = [array_pop($deck), array_pop($deck)];
            $dealerHand = [array_pop($deck), array_pop($deck)];

            echo json_encode([
                'success' => true,
                'playerHand' => $playerHand,
                'dealerHand' => [$dealerHand[0], 'hidden'],
                'deck' => $deck
            ]);
            break;

        case 'hit':
            if (!isset($data['deck']) || !isset($data['playerHand'])) {
                echo json_encode(['error' => 'Missing parameters']);
                exit;
            }

            $deck = $data['deck'];
            $playerHand = $data['playerHand'];
            
            // Draw new card
            $newCard = array_pop($deck);
            array_push($playerHand, $newCard);

            $playerValue = calculateHand($playerHand);
            $gameOver = $playerValue > 21;

            echo json_encode([
                'success' => true,
                'newCard' => $newCard,
                'playerHand' => $playerHand,
                'playerValue' => $playerValue,
                'gameOver' => $gameOver,
                'deck' => $deck
            ]);
            break;

        case 'stand':
            if (!isset($data['deck']) || !isset($data['playerHand']) || !isset($data['dealerHand']) || 
                !isset($data['username']) || !isset($data['password']) || !isset($data['bet'])) {
                echo json_encode(['error' => 'Missing parameters']);
                exit;
            }

            $deck = $data['deck'];
            $playerHand = $data['playerHand'];
            $dealerHand = $data['dealerHand'];
            $dealerHand[1] = $data['hiddenCard'];

            $playerValue = calculateHand($playerHand);
            $dealerValue = calculateHand($dealerHand);

            // Dealer must hit on 16 and below
            while ($dealerValue < 17) {
                $newCard = array_pop($deck);
                array_push($dealerHand, $newCard);
                $dealerValue = calculateHand($dealerHand);
            }

            // Determine winner
            $winner = determineWinner($playerValue, $dealerValue);
            $payout = 0;

            if ($winner === 'player') {
                // Check for blackjack
                if (count($playerHand) === 2 && $playerValue === 21) {
                    $payout = $data['bet'] * 2.5; // 3:2 payout for blackjack
                } else {
                    $payout = $data['bet'] * 2; // Regular win
                }
                
                $result = transfer_duinocoin($FAUCET_USERNAME, $FAUCET_PASSWORD, $data['username'], $payout);
                if (!$result) {
                    echo json_encode(['error' => 'Payout failed']);
                    exit;
                }
            }

            echo json_encode([
                'success' => true,
                'dealerHand' => $dealerHand,
                'dealerValue' => $dealerValue,
                'winner' => $winner,
                'payout' => $payout
            ]);
            break;
    }
    exit;
}

function createDeck() {
    $suits = ['hearts', 'diamonds', 'clubs', 'spades'];
    $values = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
    $deck = [];
    
    foreach ($suits as $suit) {
        foreach ($values as $value) {
            $deck[] = ['suit' => $suit, 'value' => $value];
        }
    }
    
    return $deck;
}

function calculateHand($hand) {
    $total = 0;
    $aces = 0;
    
    foreach ($hand as $card) {
        if ($card === 'hidden') continue;
        
        $value = $card['value'];
        if ($value === 'A') {
            $aces++;
            $total += 11;
        } elseif (in_array($value, ['K', 'Q', 'J'])) {
            $total += 10;
        } else {
            $total += intval($value);
        }
    }
    
    while ($total > 21 && $aces > 0) {
        $total -= 10;
        $aces--;
    }
    
    return $total;
}

function determineWinner($playerValue, $dealerValue) {
    if ($playerValue > 21) return 'dealer';
    if ($dealerValue > 21) return 'player';
    if ($playerValue > $dealerValue) return 'player';
    if ($dealerValue > $playerValue) return 'dealer';
    return 'tie';
}

function transfer_duinocoin($sender_username, $sender_password, $recipient_username, $amount) {
    global $DUINOCOIN_API_URL;
    $url = "$DUINOCOIN_API_URL/transaction/?username=" . urlencode($sender_username) 
         . "&password=" . urlencode($sender_password) 
         . "&recipient=" . urlencode($recipient_username) 
         . "&amount=" . urlencode($amount) 
         . "&memo=BlackjackGame";
    
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
    
    // Log the response for debugging
    error_log("DUCO Transaction Response: " . $response);
    
    $result = json_decode($response, true);
    
    // Consider the transaction successful if we got any response
    // and it doesn't explicitly contain an error
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
    <title>DUCO Blackjack</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
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
}

.game-container, .how-to-play {
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

.cards-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 20px 0;
    justify-content: center;
}

.cards-container h3 {
    width: 100%;
    color: #ffcc00;
    text-align: center;
    margin-bottom: 15px;
}

.card {
    width: 100px;
    height: 140px;
    background: #fff;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #000;
    position: relative;
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    margin: 5px;
}

.controls {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 20px;
}

.controls button {
    width: auto;
    min-width: 120px;
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

.bet-controls {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-bottom: 20px;
}

.bet-controls input {
    width: 150px;
}

.how-to-play h2 {
    color: #ffcc00;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
}

.how-to-play ul, .how-to-play ol {
    text-align: left;
    max-width: 500px;
    margin: 0 auto;
}

@media (max-width: 768px) {
    .login-container {
        flex-direction: column;
    }
    
    .login-container input {
        width: 100%;
    }
    
    .bet-controls {
        flex-direction: column;
    }
    
    .bet-controls input {
        width: 100%;
    }
    
    .card {
        width: 80px;
        height: 112px;
        font-size: 20px;
    }
}
</style>
    
</head>
<body>
    <div class="how-to-play">
        <h2>How to Play Blackjack</h2>
        <div class="rules">
            <h3>Game Rules:</h3>
            <ul>
                <li>Goal: Get closer to 21 than the dealer without going over</li>
                <li>Card Values:
                    <ul>
                        <li>Number cards (2-10): Face value</li>
                        <li>Face cards (J, Q, K): 10 points</li>
                        <li>Ace: 1 or 11 points (whichever helps your hand)</li>
                    </ul>
                </li>
                <li>Blackjack (21 with first two cards) pays 3:2</li>
                <li>Regular win pays 1:1</li>
                <li>Dealer must hit on 16 and below, stand on 17 and above</li>
                <li>Minimum bet: 0.5 DUCO</li>
            </ul>

            <h3>How to Play:</h3>
            <ol>
                <li>Enter your DUCO username and password</li>
                <li>Set your bet amount (minimum 0.5 DUCO)</li>
                <li>Click "Deal" to start the game</li>
                <li>Choose to:
                    <ul>
                        <li>"Hit" - Take another card</li>
                        <li>"Stand" - Keep your current hand</li>
                    </ul>
                </li>
                <li>Win if your hand beats the dealer's without busting!</li>
            </ol>
        </div>
    </div>

    <div class="game-container">
        <div class="login-container">
            <input type="text" id="username" placeholder="Username">
            <input type="password" id="password" placeholder="Password">
        </div>
        
        <div class="bet-controls">
            <input type="number" id="betAmount" min="0.5" step="0.1" value="0.5">
            <button class="deal-btn" onclick="deal()">Deal</button>
        </div>

        <div id="dealerCards" class="cards-container">
            <h3>Dealer's Hand</h3>
        </div>

        <div id="playerCards" class="cards-container">
            <h3>Your Hand</h3>
        </div>

        <div class="controls">
            <button class="hit-btn" onclick="hit()" disabled>Hit</button>
            <button class="stand-btn" onclick="stand()" disabled>Stand</button>
        </div>

        <div id="result" class="result"></div>
    </div>

    <script>
        let deck = [];
        let playerHand = [];
        let dealerHand = [];
        let hiddenCard = null;
        let currentBet = 0;

        function createCardElement(card) {
            const cardDiv = document.createElement('div');
            cardDiv.className = `card ${card === 'hidden' ? 'hidden' : card.suit}`;
            
            if (card === 'hidden') {
                cardDiv.innerHTML = '<i class="fas fa-question"></i>';
            } else {
                const suitSymbol = {
                    'hearts': '♥',
                    'diamonds': '♦',
                    'clubs': '♣',
                    'spades': '♠'
                }[card.suit];

                cardDiv.innerHTML = `
                    <div class="card-value top">${card.value}</div>
                    <div class="card-suit">${suitSymbol}</div>
                    <div class="card-value bottom">${card.value}</div>
                `;
            }
            
            return cardDiv;
        }

        async function deal() {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const betAmount = parseFloat(document.getElementById('betAmount').value);

            if (!username || !password) {
                showResult('Please enter username and password', 'lose');
                return;
            }

            if (betAmount < 0.5) {
                showResult('Minimum bet is 0.5 DUCO', 'lose');
                return;
            }

            currentBet = betAmount;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'deal',
                        username: username,
                        password: password,
                        bet: betAmount
                    })
                });

                const data = await response.json();
                
                if (data.error) {
                    showResult(data.error, 'lose');
                    return;
                }

                // Clear previous hands
                document.getElementById('dealerCards').innerHTML = '<h3>Dealer\'s Hand</h3>';
                document.getElementById('playerCards').innerHTML = '<h3>Your Hand</h3>';
                document.getElementById('result').style.display = 'none';

                deck = data.deck;
                playerHand = data.playerHand;
                dealerHand = data.dealerHand;
                hiddenCard = dealerHand[1];

                // Display cards
                dealerHand.forEach(card => {
                    document.getElementById('dealerCards').appendChild(createCardElement(card));
                });

                playerHand.forEach(card => {
                    document.getElementById('playerCards').appendChild(createCardElement(card));
                });

                // Enable game buttons
                document.querySelector('.hit-btn').disabled = false;
                document.querySelector('.stand-btn').disabled = false;
                document.querySelector('.deal-btn').disabled = true;

            } catch (error) {
                showResult('Game failed: ' + error.message, 'lose');
            }
        }

        async function hit() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'hit',
                        deck: deck,
                        playerHand: playerHand
                    })
                });

                const data = await response.json();
                
                if (data.error) {
                    showResult(data.error, 'lose');
                    return;
                }

                deck = data.deck;
                playerHand = data.playerHand;

                // Add new card to display
                document.getElementById('playerCards').appendChild(createCardElement(data.newCard));

                if (data.gameOver) {
                    showResult('Bust! You lose ' + currentBet.toFixed(2) + ' DUCO', 'lose');
                    endGame();
                }

            } catch (error) {
                showResult('Game failed: ' + error.message, 'lose');
            }
        }

        async function stand() {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'stand',
                        deck: deck,
                        playerHand: playerHand,
                        dealerHand: dealerHand,
                        hiddenCard: hiddenCard,
                        username: username,
                        password: password,
                        bet: currentBet
                    })
                });

                const data = await response.json();
                
                if (data.error) {
                    showResult(data.error, 'lose');
                    return;
                }

                // Clear and redraw dealer's hand
                document.getElementById('dealerCards').innerHTML = '<h3>Dealer\'s Hand</h3>';
                data.dealerHand.forEach(card => {
                    document.getElementById('dealerCards').appendChild(createCardElement(card));
                });

                if (data.winner === 'player') {
                    showResult('You win ' + data.payout.toFixed(2) + ' DUCO!', 'win');
                } else if (data.winner === 'dealer') {
                    showResult('Dealer wins! You lose ' + currentBet.toFixed(2) + ' DUCO', 'lose');
                } else {
                    showResult('Push! Bet returned', 'tie');
                }

                endGame();

            } catch (error) {
                showResult('Game failed: ' + error.message, 'lose');
            }
        }

        function endGame() {
            document.querySelector('.hit-btn').disabled = true;
            document.querySelector('.stand-btn').disabled = true;
            document.querySelector('.deal-btn').disabled = false;
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