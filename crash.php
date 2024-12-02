<?php
$DUINOCOIN_API_URL = "https://server.duinocoin.com";
$FAUCET_USERNAME = "ENTER_USERNAME_HERE";
$FAUCET_PASSWORD = "ENTER_PASSWORD_HERE";
$BET_AMOUNT = 0.5;

function transfer_duinocoin($sender_username, $sender_password, $recipient_username, $amount) {
    global $DUINOCOIN_API_URL;
    $url = "$DUINOCOIN_API_URL/transaction/?username=" . urlencode($sender_username) 
         . "&password=" . urlencode($sender_password) 
         . "&recipient=" . urlencode($recipient_username) 
         . "&amount=" . urlencode($amount) 
         . "&memo=CrashGame";
    
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
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'start' && isset($_POST['username']) && isset($_POST['password'])) {
            $username = $_POST['username'];
            $password = $_POST['password'];
            
            // Verify credentials with initial bet
            $verify = transfer_duinocoin($username, $password, $FAUCET_USERNAME, $BET_AMOUNT);
            if (!$verify) {
                echo json_encode(array("error" => "Invalid credentials or insufficient balance"));
                exit;
            }
            
            // Generate crash point (between 1.0 and 10.0, with higher numbers being less likely)
            $crash_point = round(1 / (mt_rand() / mt_getrandmax()) * 0.9, 2);
            if ($crash_point > 10) $crash_point = 10;
            
            echo json_encode(array(
                "success" => true,
                "crash_point" => $crash_point
            ));
            exit;
        }
        
        if ($action === 'cashout' && isset($_POST['username']) && isset($_POST['multiplier'])) {
            $username = $_POST['username'];
            $multiplier = floatval($_POST['multiplier']);
            $winAmount = $BET_AMOUNT * $multiplier;
            
            // Process payout
            $result = transfer_duinocoin($FAUCET_USERNAME, $FAUCET_PASSWORD, $username, $winAmount);
            if ($result) {
                echo json_encode(array("success" => true, "amount" => $winAmount));
            } else {
                echo json_encode(array("error" => "Payout failed"));
            }
            exit;
        }
    }
    
    echo json_encode(array("error" => "Invalid request"));
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Crash Game - KatFaucet</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Your existing styles */
        .game-area {
            text-align: center;
            margin-top: 20px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }
        
        .multiplier-display {
            font-size: 3em;
            font-weight: bold;
            color: #ffcc00;
            margin: 20px 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .crash-graph {
            width: 100%;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            margin: 20px 0;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 204, 0, 0.2);
        }
        
        .graph-line {
            position: absolute;
            bottom: 10%;
            left: 5%;
            width: 8px;
            background: #ffcc00;
            transform-origin: bottom left;
            box-shadow: 0 0 15px rgba(255, 204, 0, 0.7);
            filter: drop-shadow(0 0 8px rgba(255, 204, 0, 0.5));
        }
        
        .trail-point {
            position: absolute;
            width: 6px;
            height: 6px;
            background: #ffcc00;
            border-radius: 50%;
            box-shadow: 0 0 10px rgba(255, 204, 0, 0.5);
        }
        
        .point-label {
            position: absolute;
            color: #ffcc00;
            font-size: 12px;
            font-weight: bold;
            transform: translate(-50%, -20px);
            text-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
        }
        
        .crashed {
            color: #e74c3c !important;
        }
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Crash</h1>
        
        <!-- Login Form -->
        <div class="login-form" id="loginForm">
            <div class="info">
                <p>Watch the multiplier increase and cash out before it crashes!</p>
                <p>A deposit of 0.5 DUCO is required to play.</p>
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" required>
            </div>
            <button onclick="startGame()">Play (0.5 DUCO)</button>
        </div>

        <!-- Game Area -->
        <div class="game-area" id="gameArea" style="display: none;">
            <div class="crash-graph" id="graph"></div>
            <div class="multiplier-display" id="multiplier">1.00x</div>
            <button onclick="cashOut()" id="cashoutBtn">Cash Out</button>
            <div id="result" style="display: none;"></div>
        </div>
    </div>

    <div class="footer-container">
        <div class="disclaimer">
            <h3>How to Play</h3>
            <p>1. Place your bet of 0.5 DUCO</p>
            <p>2. Watch the multiplier increase</p>
            <p>3. Cash out before it crashes!</p>
            <p>4. The longer you wait, the more you can win</p>
        </div>
        <div class="copyright">
            <p>© 2024 KatFaucet</p>
        </div>
    </div>

    <script>
        let gameActive = false;
        let currentMultiplier = 1.0;
        let crashPoint = 0;
        let gameInterval;
        let currentUsername = '';
        let graphLine;
        let trailPoints = [];
        let startTime;
        let lastPointTime = 0;
        
        async function startGame() {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            if (!username || !password) {
                showResult('Please enter both username and password', 'error');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'start');
                formData.append('username', username);
                formData.append('password', password);

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data.error) {
                    showResult(data.error, 'error');
                    return;
                }

                currentUsername = username;
                crashPoint = data.crash_point;
                document.getElementById('loginForm').style.display = 'none';
                document.getElementById('gameArea').style.display = 'block';
                
                startCrashGame();
                
            } catch (error) {
                showResult('Game start failed: ' + error.message, 'error');
            }
        }

        function startCrashGame() {
            gameActive = true;
            currentMultiplier = 1.0;
            startTime = null;
            lastPointTime = 0;
            document.getElementById('cashoutBtn').disabled = false;
            document.getElementById('result').style.display = 'none';
            document.getElementById('multiplier').classList.remove('crashed');
            
            // Clear previous game
            const graph = document.getElementById('graph');
            graph.innerHTML = '';
            trailPoints = [];
            
            // Create new line
            graphLine = document.createElement('div');
            graphLine.className = 'graph-line';
            graph.appendChild(graphLine);
            
            // Add grid lines and labels
            const multipliers = [1, 1.5, 2, 3, 5, 10];
            multipliers.forEach(mult => {
                const gridLine = document.createElement('div');
                gridLine.style.position = 'absolute';
                gridLine.style.left = '0';
                gridLine.style.right = '0';
                gridLine.style.bottom = `${(mult/10) * 100}%`;
                gridLine.style.borderBottom = '1px solid rgba(255, 204, 0, 0.1)';
                graph.appendChild(gridLine);
                
                const label = document.createElement('div');
                label.style.position = 'absolute';
                label.style.right = '10px';
                label.style.bottom = `${(mult/10) * 100}%`;
                label.style.transform = 'translateY(50%)';
                label.style.color = 'rgba(255, 204, 0, 0.5)';
                label.style.fontSize = '12px';
                label.style.fontWeight = 'bold';
                label.textContent = mult.toFixed(1) + 'x';
                graph.appendChild(label);
            });
            
            gameInterval = setInterval(updateGame, 50);
        }

        function updateGame() {
            if (!gameActive) return;
            
            if (!startTime) startTime = Date.now();
            const elapsedTime = (Date.now() - startTime) / 1000; // Time in seconds
            
            currentMultiplier += 0.01;
            document.getElementById('multiplier').textContent = currentMultiplier.toFixed(2) + 'x';
            
            // Calculate horizontal progress (0 to 1)
            const xProgress = Math.min(1, elapsedTime / 15); // Takes 15 seconds to cross screen
            
            // Calculate vertical position based on multiplier
            const yProgress = (currentMultiplier - 1) * 20; // Scale factor for height
            
            // Update main line
            const x = 5 + (xProgress * 90); // Move from 5% to 95% of width
            const y = Math.min(80, 10 + yProgress); // Start at 10% height, max 90%
            
            graphLine.style.height = '8px'; // Keep line thickness consistent
            graphLine.style.width = `${Math.max(8, xProgress * 50)}px`; // Extend line width as it moves
            graphLine.style.left = `${x}%`;
            graphLine.style.bottom = `${y}%`;
            
            // Add point every 2 seconds
            if (elapsedTime - lastPointTime >= 2) {
                const point = document.createElement('div');
                point.className = 'trail-point';
                point.style.left = `${x}%`;
                point.style.bottom = `${y}%`;
                
                // Add label with multiplier
                const label = document.createElement('div');
                label.className = 'point-label';
                label.textContent = currentMultiplier.toFixed(2) + 'x';
                label.style.left = `${x}%`;
                label.style.bottom = `${y}%`;
                
                document.getElementById('graph').appendChild(point);
                document.getElementById('graph').appendChild(label);
                trailPoints.push({ point, label });
                lastPointTime = elapsedTime;
            }
            
            // Update glow intensity
            const intensity = Math.min(1, (currentMultiplier - 1) / 2);
            const glowSize = 15 + (intensity * 10);
            graphLine.style.boxShadow = `0 0 ${glowSize}px rgba(255, 204, 0, ${0.5 + (intensity * 0.5)})`;
            
            if (currentMultiplier >= crashPoint) {
                gameCrashed();
            }
        }

        async function cashOut() {
            if (!gameActive) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'cashout');
                formData.append('username', currentUsername);
                formData.append('multiplier', currentMultiplier);

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data.error) {
                    showResult(data.error, 'error');
                    return;
                }

                gameActive = false;
                clearInterval(gameInterval);
                showResult(`You won ${data.amount.toFixed(2)} DUCO!`, 'win');
                
                // Return to login form after delay
                setTimeout(() => {
                    document.getElementById('gameArea').style.display = 'none';
                    document.getElementById('loginForm').style.display = 'block';
                }, 3000);
                
            } catch (error) {
                showResult('Cashout failed: ' + error.message, 'error');
            }
        }

        function gameCrashed() {
            gameActive = false;
            clearInterval(gameInterval);
            document.getElementById('multiplier').classList.add('crashed');
            document.getElementById('cashoutBtn').disabled = true;
            showResult(`Crashed at ${crashPoint.toFixed(2)}x! You lost 0.5 DUCO`, 'lose');
            
            // Return to login form after delay
            setTimeout(() => {
                document.getElementById('gameArea').style.display = 'none';
                document.getElementById('loginForm').style.display = 'block';
                document.getElementById('multiplier').classList.remove('crashed');
            }, 3000);
        }

        function showResult(message, type) {
            const result = document.getElementById('result');
            result.textContent = message;
            result.className = type;
            result.style.display = 'block';
        }
    </script>
</body>
</html>