<?php
session_start();

// Configuration
$DUINOCOIN_API_URL = "https://server.duinocoin.com";
$FAUCET_USERNAME = "ENTER_USERNAME_HERE";
$FAUCET_PASSWORD = "ENTER_PASSWORD_HERE";
$BET_AMOUNT = 0.25;

// Wheel segments configuration (24 segments)
$SEGMENTS = [
    ['value' => 0.00, 'color' => '#e74c3c', 'label' => '0x'],  // Red
    ['value' => 0.25, 'color' => '#3498db', 'label' => '1x'],  // Blue
    ['value' => 0.00, 'color' => '#e74c3c', 'label' => '0x'],
    ['value' => 0.30, 'color' => '#2ecc71', 'label' => '1.2x'], // Green
    ['value' => 0.00, 'color' => '#e74c3c', 'label' => '0x'],
    ['value' => 0.25, 'color' => '#3498db', 'label' => '1x'],
    ['value' => 0.00, 'color' => '#e74c3c', 'label' => '0x'],
    ['value' => 0.35, 'color' => '#9b59b6', 'label' => '1.4x'], // Purple
    ['value' => 0.00, 'color' => '#e74c3c', 'label' => '0x'],
    ['value' => 0.25, 'color' => '#3498db', 'label' => '1x'],
    ['value' => 0.00, 'color' => '#e74c3c', 'label' => '0x'],
    ['value' => 0.50, 'color' => '#f1c40f', 'label' => '2x'],  // Yellow
    ['value' => 0.00, 'color' => '#e74c3c', 'label' => '0x'],
    ['value' => 0.25, 'color' => '#3498db', 'label' => '1x'],
    ['value' => 0.00, 'color' => '#e74c3c', 'label' => '0x'],
    ['value' => 0.30, 'color' => '#2ecc71', 'label' => '1.2x'],
    ['value' => 0.00, 'color' => '#e74c3c', 'label' => '0x'],
    ['value' => 0.25, 'color' => '#3498db', 'label' => '1x'],
    ['value' => 0.00, 'color' => '#e74c3c', 'label' => '0x'],
    ['value' => 0.35, 'color' => '#9b59b6', 'label' => '1.4x'],
    ['value' => 0.00, 'color' => '#e74c3c', 'label' => '0x'],
    ['value' => 0.25, 'color' => '#3498db', 'label' => '1x'],
    ['value' => 0.00, 'color' => '#e74c3c', 'label' => '0x'],
    ['value' => 0.50, 'color' => '#f1c40f', 'label' => '2x']
];

// Transaction function
function transfer_duinocoin($sender_username, $sender_password, $recipient_username, $amount) {
    global $DUINOCOIN_API_URL;
    $url = "$DUINOCOIN_API_URL/transaction/?username=" . urlencode($sender_username) 
         . "&password=" . urlencode($sender_password) 
         . "&recipient=" . urlencode($recipient_username) 
         . "&amount=" . urlencode($amount) 
         . "&memo=LuckyWheel";
    
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

$message = '';
$error = '';
$spinResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        // Process initial bet payment
        if (transfer_duinocoin($username, $password, $FAUCET_USERNAME, $BET_AMOUNT)) {
            // Generate random spin result
            $segmentIndex = array_rand($SEGMENTS);
            $winnings = $SEGMENTS[$segmentIndex]['value'];
            
            // Store spin result
            $spinResult = [
                'segment' => $segmentIndex,
                'winnings' => $winnings
            ];
            
            // Process winnings if any (only send if they won something)
            if ($winnings > 0) {
                if (transfer_duinocoin($FAUCET_USERNAME, $FAUCET_PASSWORD, $username, $winnings)) {
                    $message = "You won " . number_format($winnings, 2) . " DUCO!";
                } else {
                    $error = "Error processing winnings. Please contact support.";
                }
            } else {
                $message = "Better luck next time!";
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
    <title>Lucky Wheel</title>
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

        .wheel-container {
            position: relative;
            width: 600px;
            height: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #2c3e50;
            border-radius: 50%;
            box-shadow: 
                0 0 0 15px #34495e,
                0 0 0 30px #2c3e50,
                0 0 50px rgba(0,0,0,0.5);
        }

        .wheel {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            position: relative;
            overflow: hidden;
            transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
            transform: rotate(0deg);
            background: #2c3e50;
        }

        .segment {
            position: absolute;
            width: 100%;
            height: 100%;
            clip-path: polygon(50% 50%, 50% 0%, 53% 0%, 53% 100%, 50% 100%);
            transform-origin: 50% 50%;
            transition: filter 0.3s;
        }

        .segment.winner {
            filter: brightness(1.5);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.5);
            z-index: 5;
        }

        .segment-content {
            position: absolute;
            left: 48.5%;
            top: 8%;
            transform: rotate(90deg);
            font-size: 16px;
            font-weight: bold;
            color: white;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
            white-space: nowrap;
        }

        .pointer {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            width: 24px;
            height: 24px;
            background: #f1c40f;
            clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
            z-index: 100;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .center-circle {
            position: absolute;
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, #f1c40f, #f39c12);
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10;
            box-shadow: 
                0 0 0 15px #2c3e50,
                0 0 15px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        .outer-ring {
            position: absolute;
            width: calc(100% + 40px);
            height: calc(100% + 40px);
            top: -20px;
            left: -20px;
            border-radius: 50%;
            background: repeating-conic-gradient(
                from 0deg,
                #f1c40f 0deg 2deg,
                #2c3e50 2deg 15deg
            );
            z-index: -1;
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

        /* Add highlight effect behind pointer */
        .pointer::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            width: 4px;
            height: 20px;
            background: rgba(255, 196, 0, 0.5);
            box-shadow: 0 0 10px #f1c40f;
        }
    </style>
    
    <script>
        function spinWheel(segmentIndex) {
            const wheel = document.querySelector('.wheel');
            const segmentAngle = 360 / <?php echo count($SEGMENTS); ?>;
            const randomSpins = 5; // Fixed number of spins
            const finalAngle = (randomSpins * 360) + (segmentIndex * segmentAngle);
            
            // Remove previous winner class
            document.querySelector('.segment.winner')?.classList.remove('winner');
            
            wheel.style.transform = `rotate(${-finalAngle}deg)`;
            
            // Add winner class after spin completes
            setTimeout(() => {
                const segments = document.querySelectorAll('.segment');
                segments[segmentIndex].classList.add('winner');
            }, 4000); // Match the spin duration
        }
        
        <?php if ($spinResult): ?>
        window.onload = function() {
            setTimeout(() => {
                spinWheel(<?php echo $spinResult['segment']; ?>);
            }, 500);
        };
        <?php endif; ?>
    </script>
</head>
<body>
    <div class="container">
        <h1 class="game-title">Lucky Wheel</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="wheel-container">
            <div class="outer-ring"></div>
            <div class="pointer"></div>
            <div class="wheel">
                <?php foreach ($SEGMENTS as $i => $segment): ?>
                    <div class="segment" style="
                        transform: rotate(<?php echo $i * (360 / count($SEGMENTS)); ?>deg);
                        background: <?php echo $segment['color']; ?>;
                    ">
                        <div class="segment-content">
                            <?php echo $segment['label']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="center-circle">SPIN!</div>
        </div>
        
        <div class="form-container">
            <form method="POST">
                <input type="text" name="username" placeholder="Duino-Coin Username" required>
                <input type="password" name="password" placeholder="Duino-Coin Password" required>
                <button type="submit">Spin (0.25 DUCO)</button>
            </form>
        </div>
        
        <div class="rules">
            <h3>Rules</h3>
            <p>Spin the wheel to win!</p>
            <p>Red (0x) = No win</p>
            <p>Blue (1x) = 0.25 DUCO</p>
            <p>Green (1.2x) = 0.30 DUCO</p>
            <p>Yellow (1.4x) = 0.35 DUCO</p>
            <p>Purple (2x) = 0.50 DUCO</p>
        </div>
    </div>
</body>
</html>