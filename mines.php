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
         . "&memo=MinesGame";
    
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
        
        if ($action === 'deposit' && isset($_POST['username']) && isset($_POST['password'])) {
            $username = $_POST['username'];
            $password = $_POST['password'];
            
            // Verify credentials with initial deposit
            $verify = transfer_duinocoin($username, $password, $FAUCET_USERNAME, $BET_AMOUNT);
            if (!$verify) {
                echo json_encode(array("error" => "Invalid credentials or insufficient balance"));
                exit;
            }
            echo json_encode(array("success" => true));
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mines Game - KatFaucet</title>
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
        /* Your existing stylesheet here */
        
        /* Additional styles for the mines game */
        .grid-container {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin: 20px 0;
        }

        .cell {
            aspect-ratio: 1;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 204, 0, 0.3);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            transition: all 0.3s ease;
        }

        .cell:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: #ffcc00;
        }

        .cell.revealed {
            pointer-events: none;
        }

        .cell.gem {
            background: rgba(46, 204, 113, 0.2);
            border-color: #2ecc71;
        }

        .cell.mine {
            background: rgba(231, 76, 60, 0.2);
            border-color: #e74c3c;
        }

        .game-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .game-controls button {
            width: auto;
            flex: 1;
        }

        .multiplier {
            font-size: 24px;
            color: #ffcc00;
            text-align: center;
            margin: 20px 0;
        }

        .login-form {
            text-align: center;
            margin-bottom: 20px;
        }

        .login-form input {
            margin-bottom: 10px;
        }

        .game-area {
            display: none; /* Hidden by default until login */
        }

        .deposit-form {
            display: none; /* Hidden until login successful */
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Mines</h1>
        
        <!-- Login Form -->
        <div class="login-form" id="loginForm">
            <div class="info">
                <p>Please login with your DuinoCoin account.</p>
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
            <button onclick="makeDeposit()">Deposit 0.5 DUCO</button>
        </div>

        <!-- Game Area (initially hidden) -->
        <div class="game-area" id="gameArea">
            <div class="info">
                <p>Each correct move multiplies your winnings by 1x.</p>
            </div>

            <div class="form-group">
                <label>Bet Amount: 0.5 DUCO</label>
                <div class="multiplier">
                    Current Multiplier: <span id="current-multiplier">1.00</span>x
                </div>
            </div>

            <div class="game-controls">
                <button onclick="startGame()">Start Game</button>
                <button onclick="cashOut()" id="cashout" disabled>Cash Out</button>
            </div>

            <div class="grid-container" id="grid"></div>
            <div id="result"></div>
        </div>
    </div>

    <script>
        let gameActive = false;
        let multiplier = 1.0;
        let revealedCells = 0;
        let minePositions = [];
        let currentUsername = '';
        let currentPassword = '';
        const BET_AMOUNT = 0.5;

        async function makeDeposit() {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            if (!username || !password) {
                showResult('Please enter both username and password', 'error');
                return;
            }

            currentUsername = username;
            currentPassword = password;
            
            try {
                const formData = new FormData();
                formData.append('action', 'deposit');
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

                document.getElementById('loginForm').style.display = 'none';
                document.getElementById('gameArea').style.display = 'block';
                
            } catch (error) {
                showResult('Deposit failed: ' + error.message, 'error');
            }
        }

        async function cashOut() {
            if (!gameActive) return;
            
            try {
                const formData = new FormData();
                formData.append('action', 'cashout');
                formData.append('username', currentUsername);
                formData.append('multiplier', multiplier);

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data.error) {
                    showResult(data.error, 'error');
                    return;
                }

                showResult(`You won ${data.amount.toFixed(2)} DUCO!`, 'win');
                gameOver(true);
                
                // Hide game area and show login form for new deposit
                document.getElementById('gameArea').style.display = 'none';
                document.getElementById('loginForm').style.display = 'block';
                
            } catch (error) {
                showResult('Cashout failed: ' + error.message, 'error');
            }
        }

        function startGame() {
            // Reset game state
            gameActive = true;
            multiplier = 1.0;
            revealedCells = 0;
            minePositions = [];
            document.getElementById('current-multiplier').textContent = multiplier.toFixed(2);
            document.getElementById('cashout').disabled = false;
            document.getElementById('result').style.display = 'none';

            // Generate mine positions (5 mines)
            while (minePositions.length < 5) {
                const pos = Math.floor(Math.random() * 25);
                if (!minePositions.includes(pos)) {
                    minePositions.push(pos);
                }
            }

            // Create grid
            const grid = document.getElementById('grid');
            grid.innerHTML = '';
            for (let i = 0; i < 25; i++) {
                const cell = document.createElement('div');
                cell.className = 'cell';
                cell.onclick = () => revealCell(i);
                grid.appendChild(cell);
            }
        }

        function revealCell(index) {
            if (!gameActive) return;

            const cells = document.getElementsByClassName('cell');
            const cell = cells[index];

            if (cell.classList.contains('revealed')) return;

            cell.classList.add('revealed');

            if (minePositions.includes(index)) {
                // Hit a mine
                cell.innerHTML = '<i class="fas fa-bomb" style="color: #e74c3c;"></i>';
                cell.classList.add('mine');
                gameOver(false);
            } else {
                // Found a gem
                cell.innerHTML = '<i class="fas fa-gem" style="color: #2ecc71;"></i>';
                cell.classList.add('gem');
                revealedCells++;
                multiplier += 1.0; // Increase by 1x for each correct move
                document.getElementById('current-multiplier').textContent = multiplier.toFixed(2);
            }
        }

        function gameOver(won) {
            gameActive = false;
            document.getElementById('cashout').disabled = true;

            // Reveal all mines
            const cells = document.getElementsByClassName('cell');
            minePositions.forEach(pos => {
                if (!cells[pos].classList.contains('revealed')) {
                    cells[pos].classList.add('revealed', 'mine');
                    cells[pos].innerHTML = '<i class="fas fa-bomb" style="color: #e74c3c;"></i>';
                }
            });

            if (!won) {
                showResult('Game Over! You hit a mine!', 'lose');
                // Hide game area and show login form for new deposit
                setTimeout(() => {
                    document.getElementById('gameArea').style.display = 'none';
                    document.getElementById('loginForm').style.display = 'block';
                }, 2000); // Wait 2 seconds before showing login form
            }
        }

        function showResult(message, type) {
            const result = document.getElementById('result');
            result.textContent = message;
            result.className = type;
            result.style.display = 'block';
        }
    </script>

    <div class="footer-container">
        <div class="disclaimer">
            <h3>How to Play</h3>
            <p>1. Login with your account</p>
            <p>2. Deposit 0.5 DUCO to play</p>
            <p>3. Click cells to reveal gems or mines</p>
            <p>4. Cash out before hitting a mine!</p>
            <p>Each gem multiplies your winnings by 1x</p>
        </div>
        <div class="copyright">
            <p>© 2024 KatFaucet</p>
        </div>
    </div>
</body>
</html>