<?php
$DUINOCOIN_API_URL = "https://server.duinocoin.com";
$FAUCET_USERNAME = "ENTER_USERNAME_HERE";
$FAUCET_PASSWORD = "ENTER_PASSWORD_HERE";
$BET_AMOUNT = 0.5;

// Define tower configurations for different difficulties
$TOWER_CONFIG = [
    'easy' => [
        'floors' => 8,
        'safe_spots' => 2,  // 2 out of 3 spots are safe
        'multipliers' => [1.2, 1.4, 1.6, 1.8, 2.0, 2.5, 3.0, 4.0]
    ],
    'medium' => [
        'floors' => 8,
        'safe_spots' => 1,  // 1 out of 3 spots are safe
        'multipliers' => [1.5, 2.0, 2.5, 3.0, 4.0, 5.0, 7.0, 10.0]
    ],
    'hard' => [
        'floors' => 8,
        'safe_spots' => 1,  // 1 out of 4 spots are safe
        'multipliers' => [2.0, 3.0, 4.0, 5.0, 7.0, 10.0, 15.0, 25.0]
    ]
];

function transfer_duinocoin($sender_username, $sender_password, $recipient_username, $amount) {
    global $DUINOCOIN_API_URL;
    $url = "$DUINOCOIN_API_URL/transaction/?username=" . urlencode($sender_username) 
         . "&password=" . urlencode($sender_password) 
         . "&recipient=" . urlencode($recipient_username) 
         . "&amount=" . urlencode($amount) 
         . "&memo=TowerGame";
    
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
            case 'start':
                if (!isset($data['username']) || !isset($data['password']) || !isset($data['difficulty'])) {
                    echo json_encode(['error' => 'Missing parameters']);
                    exit;
                }

                // Take initial deposit
                $verify = transfer_duinocoin($data['username'], $data['password'], $FAUCET_USERNAME, $BET_AMOUNT);
                if (!$verify) {
                    echo json_encode(['error' => 'Invalid credentials or insufficient balance']);
                    exit;
                }

                // Generate tower layout
                $config = $TOWER_CONFIG[$data['difficulty']];
                $tower = [];
                for ($i = 0; $i < $config['floors']; $i++) {
                    $spots = array_fill(0, $data['difficulty'] === 'hard' ? 4 : 3, false);
                    $safe_indices = array_rand(array_keys($spots), $config['safe_spots']);
                    if (!is_array($safe_indices)) $safe_indices = [$safe_indices];
                    foreach ($safe_indices as $idx) {
                        $spots[$idx] = true;
                    }
                    $tower[] = $spots;
                }

                echo json_encode([
                    'success' => true,
                    'tower' => $tower,
                    'multipliers' => $config['multipliers']
                ]);
                break;

            case 'cashout':
                if (!isset($data['username']) || !isset($data['password']) || !isset($data['floor'])) {
                    echo json_encode(['error' => 'Missing parameters']);
                    exit;
                }

                $config = $TOWER_CONFIG[$data['difficulty']];
                $multiplier = $config['multipliers'][$data['floor']];
                $winAmount = $BET_AMOUNT * $multiplier;

                // Process win
                $result = transfer_duinocoin($FAUCET_USERNAME, $FAUCET_PASSWORD, $data['username'], $winAmount);
                if (!$result) {
                    echo json_encode(['error' => 'Payout failed']);
                    exit;
                }

                echo json_encode([
                    'success' => true,
                    'amount' => $winAmount
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
    <title>Tower - KatFaucet</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .game-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .tower-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }

        .tower {
            flex: 1;
            display: flex;
            flex-direction: column-reverse;
            gap: 10px;
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 10px;
            min-height: 600px;
        }

        .floor {
            display: flex;
            gap: 10px;
            justify-content: center;
            position: relative;
            padding: 10px 0;
        }

        .floor-number {
            position: absolute;
            left: -40px;
            top: 50%;
            transform: translateY(-50%);
            color: #ffcc00;
            font-weight: bold;
        }

        .multiplier {
            position: absolute;
            right: -60px;
            top: 50%;
            transform: translateY(-50%);
            color: #2ecc71;
            font-weight: bold;
        }

        .spot {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 204, 0, 0.3);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            color: #ffcc00;
        }

        .spot:hover {
            background: rgba(255, 204, 0, 0.1);
            transform: translateY(-2px);
        }

        .spot.revealed-safe {
            background: rgba(46, 204, 113, 0.2);
            border-color: #2ecc71;
        }

        .spot.revealed-danger {
            background: rgba(231, 76, 60, 0.2);
            border-color: #e74c3c;
        }

        .controls {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 200px;
        }

        .difficulty-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }

        .difficulty-btn {
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .difficulty-btn.easy {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }

        .difficulty-btn.medium {
            background: rgba(241, 196, 15, 0.2);
            color: #f1c40f;
        }

        .difficulty-btn.hard {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }

        .difficulty-btn.selected {
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(255, 204, 0, 0.3);
        }

        .cashout-btn {
            padding: 15px;
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .cashout-btn:hover {
            background: rgba(46, 204, 113, 0.3);
            transform: translateY(-2px);
        }

        .cashout-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
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

        #result.error {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }

        .nav-container {
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            margin-bottom: 20px;
        }

        .nav-link {
            color: #ffcc00;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: #fff;
        }

        .start-btn {
            padding: 15px;
            background: rgba(255, 204, 0, 0.2);
            color: #ffcc00;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            margin-bottom: 10px;
        }

        .start-btn:hover {
            background: rgba(255, 204, 0, 0.3);
            transform: translateY(-2px);
        }

        .start-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
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

        .info h3 {
            color: #ffcc00;
            margin-bottom: 15px;
            font-size: 1.2em;
        }

        .info p {
            margin: 8px 0;
            line-height: 1.4;
        }

        .risk-info {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 204, 0, 0.2);
        }

        .risk-info p {
            color: #aaa;
        }

        .risk-info strong {
            color: #ffcc00;
        }

        #deposit-message {
            text-align: center;
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
            font-weight: bold;
        }

        #deposit-message.success {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
        }

        #deposit-message.error {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="game-container">
        <h1><i class="fas fa-tower-observation"></i> Tower</h1>
        
        <div class="info">
            <h3>How to Play</h3>
            <p>1. Choose your difficulty level (Easy, Medium, or Hard)</p>
            <p>2. Click Start Game to place your 0.5 DUCO bet</p>
            <p>3. Click safe spots on the bottom floor to climb the tower - higher floors give better rewards!</p>
            <p>4. Cash out anytime to secure your winnings</p>
            <p>5. Be careful! One wrong move and you lose everything</p>
            <div class="risk-info">
                <p><strong>Easy:</strong> 2/3 spots are safe - Up to 4x</p>
                <p><strong>Medium:</strong> 1/3 spots are safe - Up to 10x</p>
                <p><strong>Hard:</strong> 1/4 spots are safe - Up to 25x</p>
            </div>
        </div>
        
        <div class="login-container">
            <input type="text" id="username" placeholder="Username" />
            <input type="password" id="password" placeholder="Password" />
            <button class="start-btn" onclick="startGame()" id="startBtn">
                Start Game (0.5 DUCO)
            </button>
            <div id="deposit-message"></div>
        </div>

        <div class="tower-container">
            <div class="tower" id="tower">
                <!-- Tower will be generated here -->
            </div>

            <div class="controls">
                <div class="difficulty-buttons">
                    <button class="difficulty-btn easy" onclick="selectDifficulty('easy')">Easy Mode</button>
                    <button class="difficulty-btn medium selected" onclick="selectDifficulty('medium')">Medium Mode</button>
                    <button class="difficulty-btn hard" onclick="selectDifficulty('hard')">Hard Mode</button>
                </div>
                
                <button class="cashout-btn" onclick="cashout()" id="cashoutBtn" disabled>
                    Cash Out
                </button>
            </div>
        </div>

        <div id="result"></div>
    </div>

    <script>
        let selectedDifficulty = 'medium';
        let currentFloor = -1;
        let gameActive = false;
        let towerData = null;

        function selectDifficulty(difficulty) {
            selectedDifficulty = difficulty;
            document.querySelectorAll('.difficulty-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            document.querySelector(`.difficulty-btn.${difficulty}`).classList.add('selected');
            resetGame();
        }

        function createTower() {
            const tower = document.getElementById('tower');
            tower.innerHTML = '';
            
            const floorCount = 8;
            const spotCount = selectedDifficulty === 'hard' ? 4 : 3;
            
            for (let i = 0; i < floorCount; i++) {
                const floor = document.createElement('div');
                floor.className = 'floor';
                
                const floorNumber = document.createElement('div');
                floorNumber.className = 'floor-number';
                floorNumber.textContent = i + 1;
                floor.appendChild(floorNumber);
                
                for (let j = 0; j < spotCount; j++) {
                    const spot = document.createElement('div');
                    spot.className = 'spot';
                    spot.onclick = () => checkSpot(i, j);
                    floor.appendChild(spot);
                }
                
                const multiplier = document.createElement('div');
                multiplier.className = 'multiplier';
                multiplier.textContent = '?x';
                floor.appendChild(multiplier);
                
                tower.appendChild(floor);
            }
            
            // Enable start button
            document.getElementById('startBtn').disabled = false;
            document.getElementById('cashoutBtn').disabled = true;
        }

        async function startGame() {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            if (!username || !password) {
                showResult('Please enter both username and password', 'error');
                return;
            }

            // Disable start button while processing
            document.getElementById('startBtn').disabled = true;

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'start',
                        username: username,
                        password: password,
                        difficulty: selectedDifficulty
                    })
                });

                const data = await response.json();
                
                if (data.error) {
                    showResult(data.error, 'error');
                    document.getElementById('startBtn').disabled = false;
                    return;
                }

                // Show deposit confirmation
                showResult('Successfully deposited 0.5 DUCO. Good luck!', 'win');
                setTimeout(() => {
                    document.getElementById('result').style.display = 'none';
                }, 2000);

                towerData = data;
                gameActive = true;
                currentFloor = -1;
                
                // Update multipliers
                const multipliers = document.querySelectorAll('.multiplier');
                data.multipliers.forEach((mult, i) => {
                    multipliers[i].textContent = mult + 'x';
                });

            } catch (error) {
                showResult('Game failed: ' + error.message, 'error');
                document.getElementById('startBtn').disabled = false;
            }
        }

        async function checkSpot(floor, spot) {
            if (!gameActive || floor !== currentFloor + 1) return;
            
            const isSafe = towerData.tower[floor][spot];
            const spots = document.querySelectorAll(`.floor:nth-child(${8 - floor}) .spot`);
            
            if (isSafe) {
                spots[spot].classList.add('revealed-safe');
                spots[spot].innerHTML = '<i class="fas fa-check"></i>';
                currentFloor = floor;
                document.getElementById('cashoutBtn').disabled = false;
                
                if (floor === 7) {
                    await cashout();
                }
            } else {
                spots[spot].classList.add('revealed-danger');
                spots[spot].innerHTML = '<i class="fas fa-times"></i>';
                gameActive = false;
                showResult('Game Over! You lost your 0.5 DUCO deposit', 'lose');
                setTimeout(resetGame, 3000);
            }
        }

        async function cashout() {
            if (!gameActive || currentFloor < 0) return;
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'cashout',
                        username: username,
                        password: password,
                        difficulty: selectedDifficulty,
                        floor: currentFloor
                    })
                });

                const data = await response.json();
                
                if (data.error) {
                    showResult(data.error, 'error');
                    return;
                }

                showResult(`You won ${data.amount.toFixed(2)} DUCO!`, 'win');
                gameActive = false;
                setTimeout(resetGame, 3000);

            } catch (error) {
                showResult('Cashout failed: ' + error.message, 'error');
            }
        }

        function resetGame() {
            createTower();
            gameActive = false;
            currentFloor = -1;
            towerData = null;
            document.getElementById('cashoutBtn').disabled = true;
            document.getElementById('result').style.display = 'none';
            document.getElementById('startBtn').disabled = false;
        }

        function showResult(message, type) {
            // For deposit messages, use the deposit-message div
            if (message.includes('deposited')) {
                const depositMsg = document.getElementById('deposit-message');
                depositMsg.textContent = message;
                depositMsg.className = type === 'win' ? 'success' : 'error';
                return;
            }
            
            // For other messages, use the result div
            const result = document.getElementById('result');
            result.textContent = message;
            result.className = type;
            result.style.display = 'block';
        }

        // Initialize game
        createTower();
    </script>
</body>
</html>