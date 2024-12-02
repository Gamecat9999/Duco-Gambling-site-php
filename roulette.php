<?php
$DUINOCOIN_API_URL = "https://server.duinocoin.com";
$FAUCET_USERNAME = "ENTER_USERNAME_HERE";
$FAUCET_PASSWORD = "ENTER_PASSWORD_HERE";
$MIN_BET = 0.5;

// Roulette payouts and configurations
$ROULETTE_CONFIG = [
    'numbers' => range(0, 36),
    'payouts' => [
        'single' => 35,
        'split' => 17,
        'street' => 11,
        'corner' => 8,
        'line' => 5,
        'column' => 2,
        'dozen' => 2,
        'even_money' => 1
    ],
    'colors' => [
        '0' => 'green',
        '1' => 'red', '2' => 'black', '3' => 'red', '4' => 'black', '5' => 'red', '6' => 'black',
        '7' => 'red', '8' => 'black', '9' => 'red', '10' => 'black', '11' => 'black', '12' => 'red',
        '13' => 'black', '14' => 'red', '15' => 'black', '16' => 'red', '17' => 'black', '18' => 'red',
        '19' => 'red', '20' => 'black', '21' => 'red', '22' => 'black', '23' => 'red', '24' => 'black',
        '25' => 'red', '26' => 'black', '27' => 'red', '28' => 'black', '29' => 'black', '30' => 'red',
        '31' => 'black', '32' => 'red', '33' => 'black', '34' => 'red', '35' => 'black', '36' => 'red'
    ]
];

function transfer_duinocoin($sender_username, $sender_password, $recipient_username, $amount) {
    global $DUINOCOIN_API_URL;
    $url = "$DUINOCOIN_API_URL/transaction/?username=" . urlencode($sender_username) 
         . "&password=" . urlencode($sender_password) 
         . "&recipient=" . urlencode($recipient_username) 
         . "&amount=" . urlencode($amount) 
         . "&memo=RouletteGame";
    
    $response = @file_get_contents($url);
    if ($response === FALSE) {
        $error = error_get_last();
        error_log("Transaction failed: " . $error['message']);
        return FALSE;
    }
    
    $result = json_decode($response, true);
    return isset($result['success']) && $result['success'] === true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action'])) {
        switch ($data['action']) {
            case 'spin':
                if (!isset($data['username']) || !isset($data['password']) || !isset($data['bets'])) {
                    echo json_encode(['error' => 'Missing parameters']);
                    exit;
                }

                $totalBet = array_sum(array_column($data['bets'], 'amount'));
                if ($totalBet < $MIN_BET) {
                    echo json_encode(['error' => 'Minimum bet is ' . $MIN_BET . ' DUCO']);
                    exit;
                }

                // Take initial bet
                $verify = transfer_duinocoin($data['username'], $data['password'], $FAUCET_USERNAME, $totalBet);
                if (!$verify) {
                    echo json_encode(['error' => 'Invalid credentials or insufficient balance']);
                    exit;
                }

                // Generate winning number
                $winningNumber = strval(rand(0, 36));
                $winningColor = $ROULETTE_CONFIG['colors'][$winningNumber];

                // Calculate winnings
                $totalWin = 0;
                foreach ($data['bets'] as $bet) {
                    switch ($bet['type']) {
                        case 'number':
                            if ($bet['value'] === $winningNumber) {
                                $totalWin += $bet['amount'] * 35;
                            }
                            break;
                        case 'color':
                            if ($bet['value'] === $winningColor) {
                                $totalWin += $bet['amount'] * 2;
                            }
                            break;
                        case 'even_odd':
                            $isEven = intval($winningNumber) % 2 === 0;
                            if (($bet['value'] === 'even' && $isEven) || 
                                ($bet['value'] === 'odd' && !$isEven && $winningNumber !== '0')) {
                                $totalWin += $bet['amount'] * 2;
                            }
                            break;
                        case 'high_low':
                            $num = intval($winningNumber);
                            if (($bet['value'] === 'high' && $num >= 19) || 
                                ($bet['value'] === 'low' && $num <= 18 && $num !== 0)) {
                                $totalWin += $bet['amount'] * 2;
                            }
                            break;
                        case 'dozen':
                            $num = intval($winningNumber);
                            $dozen = ceil($num / 12);
                            if ($bet['value'] === strval($dozen)) {
                                $totalWin += $bet['amount'] * 3;
                            }
                            break;
                    }
                }

                // Process win if any
                if ($totalWin > 0) {
                    $result = transfer_duinocoin($FAUCET_USERNAME, $FAUCET_PASSWORD, $data['username'], $totalWin);
                    if (!$result) {
                        echo json_encode(['error' => 'Payout failed']);
                        exit;
                    }
                }

                echo json_encode([
                    'success' => true,
                    'number' => $winningNumber,
                    'color' => $winningColor,
                    'winAmount' => $totalWin
                ]);
                break;
        }
        exit;
    }
    
    echo json_encode(['error' => 'Invalid action']);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Roulette - KatFaucet</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .game-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .roulette-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }

        .wheel-container {
            flex: 1;
            position: relative;
            aspect-ratio: 1;
            max-width: 500px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .wheel {
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: linear-gradient(45deg, #1a1a1a, #2c3e50);
            position: relative;
            transform-origin: center;
            transition: transform 5s cubic-bezier(0.17, 0.67, 0.83, 0.67);
            border: 10px solid #34495e;
            box-shadow: 
                0 0 30px rgba(0,0,0,0.5),
                inset 0 0 50px rgba(0,0,0,0.5);
        }

        .wheel-number {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }

        .betting-table {
            flex: 1;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2px;
            background: #2c3e50;
            padding: 8px;
            border-radius: 10px;
            max-height: 600px;
            overflow-y: auto;
            font-size: 16px;
        }

        .betting-spot {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            min-height: 50px;
            max-height: 50px;
            font-size: 1em;
            margin: 1px;
            border-radius: 3px;
        }

        .betting-spot:hover {
            transform: scale(1.05);
        }

        .betting-spot.red {
            background: #e74c3c;
        }

        .betting-spot.black {
            background: #2c3e50;
        }

        .betting-spot.green {
            background: #27ae60;
        }

        .betting-controls {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .bet-amount {
            padding: 10px;
            border: none;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            width: 100px;
        }

        .spin-btn {
            padding: 10px 20px;
            background: #f1c40f;
            color: #2c3e50;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .spin-btn:hover {
            transform: translateY(-2px);
        }

        .spin-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .bet-chip {
            position: absolute;
            width: 24px;
            height: 24px;
            background: #f1c40f;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            font-weight: bold;
            color: #2c3e50;
            z-index: 1;
        }

        #result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            display: none;
        }

        #result.win {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }

        #result.lose {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
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

        .info h3 {
            color: #ffcc00;
            margin-bottom: 15px;
        }

        .info p {
            margin: 8px 0;
        }

        .wheel-number.winner {
            animation: winningPulse 1s infinite;
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.8);
            z-index: 1;
        }

        @keyframes winningPulse {
            0% {
                transform: scale(1) rotate(var(--rotation));
                box-shadow: 0 0 20px rgba(255, 255, 255, 0.8);
            }
            50% {
                transform: scale(1.2) rotate(var(--rotation));
                box-shadow: 0 0 30px rgba(255, 255, 255, 0.9);
            }
            100% {
                transform: scale(1) rotate(var(--rotation));
                box-shadow: 0 0 20px rgba(255, 255, 255, 0.8);
            }
        }

        /* Add smooth scrollbar for betting table */
        .betting-table::-webkit-scrollbar {
            width: 8px;
        }

        .betting-table::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }

        .betting-table::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }

        .betting-table::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
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
        <h1><i class="fas fa-dice"></i> Roulette</h1>

        <div class="info">
            <h3>How to Play</h3>
            <p>1. Enter your username and password</p>
            <p>2. Place your bets on numbers, colors, or other options</p>
            <p>3. Click Spin to start the game (minimum bet: 0.5 DUCO)</p>
            <p>4. Win up to 35x your bet!</p>
        </div>

        <div class="login-container">
            <input type="text" id="username" placeholder="Username" />
            <input type="password" id="password" placeholder="Password" />
        </div>

        <div class="roulette-container">
            <div class="wheel-container">
                <div class="wheel" id="wheel">
                    <!-- Numbers will be added by JavaScript -->
                </div>
            </div>

            <div class="betting-table" id="bettingTable">
                <!-- Will be generated by JavaScript -->
            </div>
        </div>

        <div class="betting-controls">
            <input type="number" class="bet-amount" id="betAmount" value="0.5" min="0.5" step="0.1" />
            <button class="spin-btn" onclick="spin()" id="spinBtn">Spin</button>
            <button class="spin-btn" onclick="clearBets()" id="clearBtn">Clear Bets</button>
        </div>

        <div id="result"></div>
    </div>

    <script>
        let currentBets = [];
        let isSpinning = false;

        // Initialize wheel numbers
        function initializeWheel() {
            const wheel = document.getElementById('wheel');
            // Standard European roulette wheel sequence
            const numbers = [
                0, 32, 15, 19, 4, 21, 2, 25, 17, 34, 6, 27, 13, 36, 11, 30, 8, 23, 10, 5, 24, 16, 33, 1, 20, 14, 31, 9, 22, 18, 29, 7, 28, 12, 35, 3, 26
            ];
            
            const radius = 180;
            const numberSize = 30;
            
            numbers.forEach((num, i) => {
                const spot = document.createElement('div');
                spot.className = 'wheel-number';
                spot.textContent = num;
                
                // Calculate position
                const angle = (i * (360 / numbers.length)) * (Math.PI / 180);
                const x = Math.cos(angle) * radius;
                const y = Math.sin(angle) * radius;
                
                spot.style.width = numberSize + 'px';
                spot.style.height = numberSize + 'px';
                spot.style.position = 'absolute';
                spot.style.left = `calc(50% + ${x}px - ${numberSize/2}px)`;
                spot.style.top = `calc(50% + ${y}px - ${numberSize/2}px)`;
                
                // Rotate each number to be readable
                spot.style.transform = `rotate(${i * (360 / numbers.length)}deg)`;
                
                // Set correct colors based on roulette rules
                if (num === 0) {
                    spot.style.background = '#27ae60'; // Green for 0
                } else {
                    // Red numbers: 1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36
                    const redNumbers = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
                    spot.style.background = redNumbers.includes(num) ? '#e74c3c' : '#2c3e50';
                }
                
                spot.style.color = '#fff';
                spot.style.borderRadius = '50%';
                spot.style.border = '2px solid rgba(255, 255, 255, 0.1)';
                spot.style.fontSize = '0.9em';
                spot.style.fontWeight = 'bold';
                spot.style.display = 'flex';
                spot.style.alignItems = 'center';
                spot.style.justifyContent = 'center';
                
                wheel.appendChild(spot);
            });
        }

        // Initialize betting table
        function initializeBettingTable() {
            const table = document.getElementById('bettingTable');
            
            // Add 0
            const zero = document.createElement('div');
            zero.className = 'betting-spot green';
            zero.textContent = '0';
            zero.onclick = () => placeBet('number', '0');
            table.appendChild(zero);

            // Add numbers 1-36
            for (let i = 1; i <= 36; i++) {
                const spot = document.createElement('div');
                spot.className = `betting-spot ${i % 2 === 0 ? 'black' : 'red'}`;
                spot.textContent = i;
                spot.onclick = () => placeBet('number', i.toString());
                table.appendChild(spot);
            }

            // Add special bets
            const specialBets = [
                { type: 'color', value: 'red', text: 'RED' },
                { type: 'color', value: 'black', text: 'BLACK' },
                { type: 'even_odd', value: 'even', text: 'EVEN' },
                { type: 'even_odd', value: 'odd', text: 'ODD' },
                { type: 'high_low', value: 'low', text: '1-18' },
                { type: 'high_low', value: 'high', text: '19-36' },
                { type: 'dozen', value: '1', text: '1st 12' },
                { type: 'dozen', value: '2', text: '2nd 12' },
                { type: 'dozen', value: '3', text: '3rd 12' }
            ];

            specialBets.forEach(bet => {
                const spot = document.createElement('div');
                spot.className = 'betting-spot';
                spot.style.background = 'rgba(255, 255, 255, 0.1)';
                spot.textContent = bet.text;
                spot.onclick = () => placeBet(bet.type, bet.value);
                table.appendChild(spot);
            });
        }

        function placeBet(type, value) {
            if (isSpinning) return;

            const amount = parseFloat(document.getElementById('betAmount').value);
            if (amount < 0.5) {
                showResult('Minimum bet is 0.5 DUCO', 'lose');
                return;
            }

            const existingBet = currentBets.find(b => b.type === type && b.value === value);
            if (existingBet) {
                existingBet.amount += amount;
            } else {
                currentBets.push({ type, value, amount });
            }

            updateBetDisplay();
        }

        function updateBetDisplay() {
            // Clear existing chips
            document.querySelectorAll('.bet-chip').forEach(chip => chip.remove());

            // Add new chips
            currentBets.forEach(bet => {
                const chip = document.createElement('div');
                chip.className = 'bet-chip';
                chip.textContent = bet.amount.toFixed(1);

                let spot;
                if (bet.type === 'number') {
                    spot = document.querySelector(`.betting-spot:nth-child(${parseInt(bet.value) + 1})`);
                } else {
                    const text = {
                        'color': { 'red': 'RED', 'black': 'BLACK' },
                        'even_odd': { 'even': 'EVEN', 'odd': 'ODD' },
                        'high_low': { 'low': '1-18', 'high': '19-36' },
                        'dozen': { '1': '1st 12', '2': '2nd 12', '3': '3rd 12' }
                    }[bet.type][bet.value];
                    spot = Array.from(document.querySelectorAll('.betting-spot')).find(s => s.textContent === text);
                }

                if (spot) {
                    spot.appendChild(chip);
                }
            });
        }

        function clearBets() {
            currentBets = [];
            updateBetDisplay();
        }

        async function spin() {
            if (isSpinning) return;

            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            if (!username || !password) {
                showResult('Please enter both username and password', 'lose');
                return;
            }

            if (currentBets.length === 0) {
                showResult('Please place at least one bet', 'lose');
                return;
            }

            const totalBet = currentBets.reduce((sum, bet) => sum + bet.amount, 0);
            if (totalBet < 0.5) {
                showResult('Minimum total bet is 0.5 DUCO', 'lose');
                return;
            }

            isSpinning = true;
            document.getElementById('spinBtn').disabled = true;

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'spin',
                        username: username,
                        password: password,
                        bets: currentBets
                    })
                });

                const data = await response.json();
                
                if (data.error) {
                    showResult(data.error, 'lose');
                    isSpinning = false;
                    document.getElementById('spinBtn').disabled = false;
                    return;
                }

                // Remove previous winner highlights
                document.querySelectorAll('.wheel-number.winner').forEach(el => {
                    el.classList.remove('winner');
                });

                const numbers = [0, 32, 15, 19, 4, 21, 2, 25, 17, 34, 6, 27, 13, 36, 11, 30, 8, 23, 10, 5, 24, 16, 33, 1, 20, 14, 31, 9, 22, 18, 29, 7, 28, 12, 35, 3, 26];
                let spinInterval;
                let spinDuration = 7000; // Total spin time
                let slowdownStart = 5000; // When to start slowing down
                
                // Start rapid spinning
                spinInterval = setInterval(() => {
                    const randomNum = numbers[Math.floor(Math.random() * numbers.length)];
                    const randomSpot = Array.from(document.querySelectorAll('.wheel-number'))
                        .find(spot => spot.textContent === randomNum.toString());
                    
                    // Remove previous highlight
                    document.querySelectorAll('.wheel-number.winner').forEach(el => {
                        el.classList.remove('winner');
                    });
                    
                    // Add highlight to random number
                    if (randomSpot) {
                        randomSpot.classList.add('winner');
                    }
                }, 100); // Change number every 100ms

                // Start slowing down
                setTimeout(() => {
                    clearInterval(spinInterval);
                    // Slower spinning
                    spinInterval = setInterval(() => {
                        const randomNum = numbers[Math.floor(Math.random() * numbers.length)];
                        const randomSpot = Array.from(document.querySelectorAll('.wheel-number'))
                            .find(spot => spot.textContent === randomNum.toString());
                        
                        document.querySelectorAll('.wheel-number.winner').forEach(el => {
                            el.classList.remove('winner');
                        });
                        
                        if (randomSpot) {
                            randomSpot.classList.add('winner');
                        }
                    }, 300); // Change number every 300ms
                }, slowdownStart);

                // Stop on winning number
                setTimeout(() => {
                    clearInterval(spinInterval);
                    
                    // Highlight winning number
                    const winningSpot = Array.from(document.querySelectorAll('.wheel-number'))
                        .find(spot => spot.textContent === data.number.toString());
                    
                    if (winningSpot) {
                        document.querySelectorAll('.wheel-number.winner').forEach(el => {
                            el.classList.remove('winner');
                        });
                        winningSpot.classList.add('winner');
                    }

                    // Wait 4 seconds before showing result
                    setTimeout(() => {
                        isSpinning = false;
                        document.getElementById('spinBtn').disabled = false;

                        if (data.winAmount > 0) {
                            showResult(`Number ${data.number} - You won ${data.winAmount.toFixed(2)} DUCO`, 'win');
                        } else {
                            showResult(`Number ${data.number} - You lost ${totalBet.toFixed(2)} DUCO`, 'lose');
                        }

                        clearBets();
                    }, 4000);

                }, spinDuration);

            } catch (error) {
                showResult('Game failed: ' + error.message, 'lose');
                isSpinning = false;
                document.getElementById('spinBtn').disabled = false;
            }
        }

        function showResult(message, type) {
            const result = document.getElementById('result');
            result.textContent = message;
            result.className = type;
            result.style.display = 'block';
        }

        // Initialize game
        initializeWheel();
        initializeBettingTable();
    </script>
</body>
</html>