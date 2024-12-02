<?php
$DUINOCOIN_API_URL = "https://server.duinocoin.com";
$FAUCET_USERNAME = "ENTER_USERNAME_HERE";
$FAUCET_PASSWORD = "ENTER_PASSWORD_HERE";
$BET_AMOUNT = 0.5;

// Multiplier tables for different risk levels
$MULTIPLIERS = [
    'low' => [1.2, 1.3, 1.4, 1.5, 1.4, 1.3, 1.2, 0.8, 0.8],
    'medium' => [0.1, 0.5, 1.5, 2.0, 3.0, 1.5, 0.5, 0.1, 0.1],
    'high' => [0.0, 0.0, 0.0, 0.0, 5.0, 0.0, 0.0, 0.0, 0.0]
];

function transfer_duinocoin($sender_username, $sender_password, $recipient_username, $amount) {
    global $DUINOCOIN_API_URL;
    $url = "$DUINOCOIN_API_URL/transaction/?username=" . urlencode($sender_username) 
         . "&password=" . urlencode($sender_password) 
         . "&recipient=" . urlencode($recipient_username) 
         . "&amount=" . urlencode($amount) 
         . "&memo=PlinkoGame";
    
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
    
    if (isset($_POST['username']) && isset($_POST['password']) && isset($_POST['risk'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $risk = $_POST['risk'];
        
        if (!array_key_exists($risk, $MULTIPLIERS)) {
            echo json_encode(array("error" => "Invalid risk level"));
            exit;
        }

        // Verify credentials with initial bet
        $verify = transfer_duinocoin($username, $password, $FAUCET_USERNAME, $BET_AMOUNT);
        if (!$verify) {
            echo json_encode(array("error" => "Invalid credentials or insufficient balance"));
            exit;
        }

        // Generate path for ball
        $path = [];
        $position = 4; // Start in middle
        for ($i = 0; $i < 8; $i++) { // 8 rows of pegs
            $path[] = $position;
            $position += (rand(0, 1) * 2 - 1); // Move left or right
            $position = max(0, min(8 - $i, $position)); // Keep within bounds
        }
        
        // Final position determines multiplier
        $multiplier = $MULTIPLIERS[$risk][$position];
        $winAmount = $BET_AMOUNT * $multiplier;
        
        if ($multiplier > 1) {
            // Process win
            $result = transfer_duinocoin($FAUCET_USERNAME, $FAUCET_PASSWORD, $username, $winAmount);
            if (!$result) {
                echo json_encode(array("error" => "Payout failed"));
                exit;
            }
        }
        
        echo json_encode(array(
            "success" => true,
            "path" => $path,
            "multiplier" => $multiplier,
            "amount" => $winAmount
        ));
        exit;
    }
    
    echo json_encode(array("error" => "Invalid request"));
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Plinko - KatFaucet</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Your existing styles */
        .plinko-board {
            width: 100%;
            max-width: 500px;
            aspect-ratio: 1;
            background: linear-gradient(to bottom, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.1));
            border-radius: 10px;
            margin: 20px auto;
            position: relative;
            overflow: hidden;
            box-shadow: inset 0 0 50px rgba(0, 0, 0, 0.2);
        }

        .peg {
            position: absolute;
            width: 10px;
            height: 10px;
            background: radial-gradient(circle at 30% 30%, #ffdd33, #ffcc00);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 
                0 0 5px rgba(255, 204, 0, 0.5),
                inset -2px -2px 4px rgba(0, 0, 0, 0.2);
            z-index: 1;
        }

        .ball {
            position: absolute;
            width: 16px;
            height: 16px;
            background: radial-gradient(circle at 30% 30%, #ff4d4d, #ff3300);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 
                0 0 10px rgba(255, 51, 0, 0.7),
                inset -2px -2px 4px rgba(0, 0, 0, 0.3);
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            z-index: 2;
        }

        .ball-trail {
            position: absolute;
            width: 8px;
            height: 8px;
            background: rgba(255, 51, 0, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: all 0.2s ease;
            pointer-events: none;
        }

        .multiplier-slot {
            position: absolute;
            bottom: 0;
            height: 50px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffcc00;
            font-weight: bold;
            font-size: 1.2em;
            text-shadow: 0 0 10px rgba(255, 204, 0, 0.3);
            transition: all 0.3s ease;
        }

        .multiplier-slot:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .risk-selector {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }

        .risk-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .risk-btn.low {
            background: #27ae60;
            color: white;
        }

        .risk-btn.medium {
            background: #f1c40f;
            color: black;
        }

        .risk-btn.high {
            background: #e74c3c;
            color: white;
        }

        .risk-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .risk-btn.selected {
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(255, 204, 0, 0.5);
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
    <div class="container">
        <h1>Plinko</h1>
        
        <div class="login-form" id="loginForm">
            <div class="info">
                <p>Drop the ball and watch it bounce! Choose your risk level wisely.</p>
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
            
            <div class="risk-selector">
                <button class="risk-btn low" onclick="selectRisk('low')">Low Risk</button>
                <button class="risk-btn medium" onclick="selectRisk('medium')">Medium Risk</button>
                <button class="risk-btn high" onclick="selectRisk('high')">High Risk</button>
            </div>
            
            <button onclick="playGame()" id="playBtn">Play (0.5 DUCO)</button>
        </div>

        <div class="plinko-board" id="plinkoBoard"></div>
        <div id="result"></div>
    </div>

    <script>
        let selectedRisk = 'medium';
        let gameActive = false;

        function selectRisk(risk) {
            selectedRisk = risk;
            document.querySelectorAll('.risk-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            document.querySelector(`.risk-btn.${risk}`).classList.add('selected');
            
            // Reinitialize board to show new multipliers
            initializeBoard();
        }

        function initializeBoard() {
            const board = document.getElementById('plinkoBoard');
            board.innerHTML = '';
            
            // Add pegs
            for (let row = 0; row < 8; row++) {
                const pegCount = 8 - row;
                for (let col = 0; col <= pegCount; col++) {
                    const peg = document.createElement('div');
                    peg.className = 'peg';
                    peg.style.left = `${(col * 100 / pegCount) + (50 / pegCount)}%`;
                    peg.style.top = `${(row + 1) * 10}%`;
                    board.appendChild(peg);
                }
            }
            
            // Add multiplier slots with exact same values as PHP
            const multipliers = {
                'low': [1.2, 1.3, 1.4, 1.5, 1.4, 1.3, 1.2, 0.8, 0.8],
                'medium': [0.1, 0.5, 1.5, 2.0, 3.0, 1.5, 0.5, 0.1, 0.1],
                'high': [0.0, 0.0, 0.0, 0.0, 5.0, 0.0, 0.0, 0.0, 0.0]
            }[selectedRisk];
            
            multipliers.forEach((mult, i) => {
                const slot = document.createElement('div');
                slot.className = 'multiplier-slot';
                slot.style.width = `${100 / multipliers.length}%`;
                slot.style.left = `${i * (100 / multipliers.length)}%`;
                if (mult < 1) {
                    slot.style.color = '#e74c3c'; // Red for losing multipliers
                    slot.style.background = 'rgba(231, 76, 60, 0.1)';
                    slot.style.borderColor = 'rgba(231, 76, 60, 0.2)';
                } else {
                    slot.style.color = '#2ecc71'; // Green for winning multipliers
                    slot.style.background = 'rgba(46, 204, 113, 0.1)';
                    slot.style.borderColor = 'rgba(46, 204, 113, 0.2)';
                }
                slot.textContent = mult.toFixed(1) + 'x';
                board.appendChild(slot);
            });
        }

        async function playGame() {
            if (gameActive) return;
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            if (!username || !password) {
                showResult('Please enter both username and password', 'error');
                return;
            }

            gameActive = true;
            document.getElementById('playBtn').disabled = true;
            
            try {
                const formData = new FormData();
                formData.append('username', username);
                formData.append('password', password);
                formData.append('risk', selectedRisk);

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data.error) {
                    showResult(data.error, 'error');
                    return;
                }

                // Animate ball drop with improved timing
                const board = document.getElementById('plinkoBoard');
                const ball = document.createElement('div');
                ball.className = 'ball';
                board.appendChild(ball);

                let currentPosition = 50; // Start in middle
                ball.style.left = currentPosition + '%';
                ball.style.top = '0%';

                // Animate through path with optimized timing
                for (let i = 0; i < data.path.length; i++) {
                    await new Promise(resolve => setTimeout(resolve, 300));
                    
                    // Calculate new position
                    const newPosition = (data.path[i] * 100 / 8) + (50 / 8);
                    const randomOffset = (Math.random() - 0.5) * 1; // Reduced random movement
                    
                    // Smoother bounce effect
                    ball.style.transition = 'all 0.25s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
                    ball.style.left = (newPosition + randomOffset) + '%';
                    ball.style.top = ((i + 1) * 10) + '%';
                }

                // Highlight final slot
                const finalPosition = data.path[data.path.length - 1];
                const slots = document.querySelectorAll('.multiplier-slot');
                const finalSlot = slots[finalPosition];
                finalSlot.style.transform = 'scale(1.1)';
                finalSlot.style.boxShadow = '0 0 20px rgba(255, 204, 0, 0.5)';

                // Get the exact multiplier from our defined values
                const multipliers = {
                    'low': [1.2, 1.3, 1.4, 1.5, 1.4, 1.3, 1.2, 0.8, 0.8],
                    'medium': [0.1, 0.5, 1.5, 2.0, 3.0, 1.5, 0.5, 0.1, 0.1],
                    'high': [0.0, 0.0, 0.0, 0.0, 5.0, 0.0, 0.0, 0.0, 0.0]
                }[selectedRisk];

                const finalMultiplier = multipliers[finalPosition];
                const winAmount = 0.5 * finalMultiplier;

                // Show result with delay
                await new Promise(resolve => setTimeout(resolve, 500));
                showResult(
                    finalMultiplier >= 1 
                        ? `You won ${winAmount.toFixed(2)} DUCO!` 
                        : `You lost ${(0.5 - winAmount).toFixed(2)} DUCO`,
                    finalMultiplier >= 1 ? 'win' : 'lose'
                );

            } catch (error) {
                showResult('Game failed: ' + error.message, 'error');
            } finally {
                gameActive = false;
                document.getElementById('playBtn').disabled = false;
                setTimeout(() => {
                    initializeBoard();
                }, 3000);
            }
        }

        function showResult(message, type) {
            const result = document.getElementById('result');
            result.textContent = message;
            result.className = type;
            result.style.display = 'block';
        }

        // Initialize board on load
        initializeBoard();
        selectRisk('medium');
    </script>
</body>
</html>